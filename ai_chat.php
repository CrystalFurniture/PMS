<?php
/**
 * AI Assistant endpoint (AiAssistant.php). Every action needs a login; conversations, images
 * and documents belong to the user who created them.
 *
 * GET  action=list | state&conv= | image&conv=&file= | choices&conv=&row=
 * POST action=new | send | pick | confirm_zero | use_standard | quantity | remove_row | preview | generate
 *      (CSRF token required). Only "send" calls the AI; the other actions are server-side only.
 */
	include_once('DB/conn.php');
	include_once('includes/quotation_lib.php');
	include_once('includes/ai_provider.php');
	include_once('includes/ai_import_lib.php');
	include_once('includes/retrieval_lib.php');
	include_once('includes/pi_lib.php');
	include_once('includes/pi_assistant.php');
	include_once('includes/pi_render.php');
	error_reporting(0);
	@session_start();

	$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
	list($ai, $aiError) = qt_ai_settings();

	function chat_fail($status, $message, $extra = array())
	{
		qt_json_exit($status, array_merge(array('success' => false, 'error' => $message), $extra));
	}

	/** Everything the chat screen shows about one conversation. */
	function chat_state($connect, $convId, $afterId = 0)
	{
		list($draft, $version, $meta) = pi_draft_load($connect, $convId);
		$pi = pi_compute($connect, $draft);
		$docs = array();
		$r = mysqli_query($connect, "SELECT * FROM pi_documents WHERE conversation_id = '" . $convId . "' ORDER BY id");
		while ($r && ($row = mysqli_fetch_assoc($r))) {
			$docs[] = pi_document_view($row);
		}
		$numbers = pi_row_numbers($draft);
		$rows = array();
		foreach ($draft['rows'] as $row) {
			if (empty($row['removed'])) {
				$rows[] = array('row_id' => $row['row_id'], 'n' => $numbers[$row['row_id']], 'requirement' => $row['requirement'],
					'original_text' => $row['original_text'], 'source_ref' => $row['source_ref'], 'quantity' => $row['quantity'],
					'quantity_defaulted' => !empty($row['quantity_defaulted']), 'has_product' => $row['product_id'] !== null);
			}
		}
		$finalized = null;
		foreach ($docs as $d) {
			if ($d['draft_version'] === $version) {
				$finalized = $d;
			}
		}
		return array('success' => true, 'conversation' => $convId, 'messages' => pi_msg_list($connect, $convId, $afterId),
			'draft' => array('version' => $version, 'status' => $finalized !== null ? 'finalized' : 'draft', 'finalized' => $finalized, 'doc_type' => $draft['doc_type'], 'basis' => $draft['type'], 'pricing' => $draft['pricing'],
				'customer' => $draft['customer'], 'delivery_state' => $draft['delivery_state'], 'rows' => $rows, 'pi' => $pi,
				'preview' => pi_preview_card($pi, $version, $draft)),
			'documents' => $docs);
	}

	/** Apply one server-side draft change from a button, record it in the conversation, return the new state. */
	function chat_button($connect, $convId, $label, $change)
	{
		list($draft, $version) = pi_draft_load($connect, $convId);
		$expected = isset($_POST['version']) ? (int) $_POST['version'] : $version;
		if ($expected !== $version) {
			chat_fail(409, 'The draft changed since this was shown. The latest draft is displayed now.', array('state' => chat_state($connect, $convId)));
		}
		$error = $change($draft);
		if ($error !== null) {
			chat_fail(422, $error);
		}
		$saved = pi_draft_save($connect, $convId, $draft, $version);
		if ($saved === null) {
			chat_fail(409, 'The draft changed at the same moment. Please try again.', array('state' => chat_state($connect, $convId)));
		}
		$cards = array();
		$pi = pi_compute($connect, $draft);
		if (count($pi['blockers']) === 0 && count($pi['lines']) > 0) {
			$cards[] = pi_preview_card($pi, $saved, $draft);
			pi_remember_preview($connect, $convId, $cards[0]);
		}
		pi_msg_add($connect, $convId, 'event', array('text' => $label, 'cards' => $cards));
		pi_conv_touch($connect, $convId);
		qt_json_exit(200, chat_state($connect, $convId));
	}

	/* ---------------------------------------------------------------- GET */
	if ($_SERVER['REQUEST_METHOD'] === 'GET') {
		qt_require_login_ajax();
		if (!pi_tables_exist($connect)) {
			chat_fail(503, 'The AI Assistant is not set up on this database (run cli/migrate_pi.php).');
		}
		if ($action === 'list') {
			qt_json_exit(200, array('success' => true, 'conversations' => pi_conv_list($connect)));
		}
		list($conv, $error) = pi_conv_get($connect, isset($_GET['conv']) ? (string) $_GET['conv'] : '');
		if ($error !== null) {
			chat_fail(404, $error);
		}
		if ($action === 'state') {
			qt_json_exit(200, chat_state($connect, $conv['id'], isset($_GET['after']) ? (int) $_GET['after'] : 0));
		}
		if ($action === 'choices') {
			list($draft) = pi_draft_load($connect, $conv['id']);
			$i = qt_ai_find_row($draft, isset($_GET['row']) ? (string) $_GET['row'] : '');
			if ($i === null) {
				chat_fail(404, 'Item not found.');
			}
			$cards = pi_unresolved_choice_cards($connect, $draft, array($draft['rows'][$i]['row_id']));
			if (!count($cards)) {
				$found = pi_search_products($connect, $draft, $draft['rows'][$i]['requirement']);
				$numbers = pi_row_numbers($draft);
				$cards = array(array('type' => 'choices', 'row_id' => $draft['rows'][$i]['row_id'], 'n' => $numbers[$draft['rows'][$i]['row_id']],
					'requirement' => $draft['rows'][$i]['requirement'], 'title' => 'Other products for item ' . $numbers[$draft['rows'][$i]['row_id']],
					'products' => pi_card_products($connect, $draft, $found)));
			}
			qt_json_exit(200, array('success' => true, 'card' => $cards[0]));
		}
		if ($action === 'image') {
			$file = isset($_GET['file']) ? (string) $_GET['file'] : '';
			$path = $ai['storage_dir'] . '/chat/' . $conv['id'] . '/' . $file;
			if (!preg_match('/^[0-9a-f]{16}-\d{1,2}\.(jpg|png|webp)$/', $file) || !is_file($path)) {
				http_response_code(404);
				exit;
			}
			$mime = array('jpg' => 'image/jpeg', 'png' => 'image/png', 'webp' => 'image/webp');
			header('Content-Type: ' . $mime[pathinfo($file, PATHINFO_EXTENSION)]);
			header('Content-Length: ' . filesize($path));
			header('X-Content-Type-Options: nosniff');
			header('Cache-Control: private, no-store');
			readfile($path);
			exit;
		}
		chat_fail(400, 'Unknown action.');
	}

	/* ---------------------------------------------------------------- POST */
	qt_require_login_ajax();
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		chat_fail(405, 'Method not allowed.');
	}
	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		chat_fail(403, 'Your page has expired. Please reload it and try again.');
	}
	if (!pi_tables_exist($connect)) {
		chat_fail(503, 'The AI Assistant is not set up on this database (run cli/migrate_pi.php).');
	}

	if ($action === 'new') {
		$id = pi_conv_create($connect);
		if ($id === null) {
			chat_fail(500, 'The conversation could not be created.');
		}
		// Keep only the user's latest conversations (conversations_kept, default 20); older ones are deleted.
		pi_conv_prune($connect, $ai['storage_dir'], (int) $_SESSION['user_id'], $ai['conversations_kept']);
		qt_json_exit(200, array_merge(chat_state($connect, $id), array('conversations' => pi_conv_list($connect))));
	}

	list($conv, $error) = pi_conv_get($connect, isset($_POST['conv']) ? (string) $_POST['conv'] : '');
	if ($error !== null) {
		chat_fail(404, $error);
	}
	$convId = $conv['id'];
	$rowParam = isset($_POST['row']) ? (string) $_POST['row'] : '';

	// Conversation list actions: rename, pin / unpin, delete (owner only, checked by pi_conv_get above).
	if ($action === 'rename') {
		$title = trim(preg_replace('/\s+/u', ' ', isset($_POST['title']) ? (string) $_POST['title'] : ''));
		if ($title === '' || !preg_match('//u', $title) || qt_ai_strlen($title) > 200) {
			chat_fail(422, 'The name must be 1 to 200 characters.');
		}
		mysqli_query($connect, "UPDATE ai_conversations SET title = '" . pi_esc($connect, $title) . "' WHERE id = '" . $convId . "'");
		qt_json_exit(200, array('success' => true, 'conversations' => pi_conv_list($connect)));
	}
	if ($action === 'pin') {
		if (!pi_has_pinned($connect)) {
			chat_fail(503, 'Pinning is not set up: run cli/migrate_pi.php.');
		}
		$on = isset($_POST['pinned']) && $_POST['pinned'] === '1' ? 1 : 0;
		mysqli_query($connect, "UPDATE ai_conversations SET pinned = $on WHERE id = '" . $convId . "'");
		qt_json_exit(200, array('success' => true, 'conversations' => pi_conv_list($connect)));
	}
	if ($action === 'delete') {
		$d = mysqli_fetch_assoc(mysqli_query($connect, "SELECT busy_until >= NOW() AS busy FROM ai_pi_drafts WHERE conversation_id = '" . $convId . "'"));
		if ($d && (int) $d['busy']) {
			chat_fail(409, 'The assistant is still working on this conversation. Try again when it has answered.');
		}
		if (!pi_conv_delete($connect, $ai['storage_dir'], $convId)) {
			chat_fail(500, 'The conversation could not be deleted.');
		}
		qt_json_exit(200, array('success' => true, 'conversations' => pi_conv_list($connect)));
	}

	if ($action === 'send') {
		if ($aiError !== null) {
			chat_fail(503, $aiError . ' The AI Assistant cannot run; create the quotation manually for now.');
		}
		$clientId = isset($_POST['client_id']) ? (string) $_POST['client_id'] : '';
		if (pi_msg_by_client($connect, $convId, $clientId) !== null) {
			// The same message was already sent (double click or retry): show the result, do not process it twice.
			qt_json_exit(200, array_merge(chat_state($connect, $convId), array('duplicate' => true)));
		}
		list($text, $images, $error) = qt_ai_validate_input($ai, $_POST, $_FILES);
		if ($error !== null) {
			chat_fail(422, str_replace('Paste the customer message or add at least one image.', 'Type a message or attach at least one image.', $error));
		}
		if (!pi_draft_lock($connect, $convId, (int) $ai['total_deadline'] * 3 + 60)) {
			chat_fail(409, 'I am still working on your previous message. Please wait for it to finish.');
		}
		$dir = $ai['storage_dir'] . '/chat/' . $convId;
		if (count($images) && !is_dir($dir) && !@mkdir($dir, 0700, true)) {
			pi_draft_unlock($connect, $convId);
			chat_fail(500, 'The images could not be stored.');
		}
		$token = bin2hex(random_bytes(8));
		$stored = array();
		$attachments = array();
		foreach ($images as $i => $img) {
			$file = $token . '-' . ($i + 1) . '.' . $img['ext'];
			if (!move_uploaded_file($img['tmp'], $dir . '/' . $file)) {
				pi_draft_unlock($connect, $convId);
				chat_fail(500, 'The images could not be stored.');
			}
			$stored[] = array('file' => $file, 'mime' => $img['mime'], 'width' => $img['width'], 'height' => $img['height']);
			$attachments[] = array('path' => $dir . '/' . $file, 'mime' => $img['mime']);
		}
		$msgId = pi_msg_add($connect, $convId, 'user', array('text' => $text, 'images' => $stored), $clientId);
		if ($msgId === null) {
			pi_draft_unlock($connect, $convId);
			qt_json_exit(200, array_merge(chat_state($connect, $convId), array('duplicate' => true)));
		}
		pi_conv_touch($connect, $convId, $text !== '' ? $text : 'Requirement from image');
		$log = array('conversation' => $convId, 'user' => (string) $_SESSION['user_id']);
		qt_ai_log($ai, 'chat_message', array_merge($log, array('images' => count($stored), 'text_chars' => qt_ai_strlen($text))));

		// The AI call can take a while: let the employee keep using other pages meanwhile.
		session_write_close();
		ignore_user_abort(true);
		@set_time_limit((int) $ai['total_deadline'] * 3 + 120);
		$started = microtime(true);
		list($body, $status) = pi_run_turn(array('connect' => $connect, 'ai' => $ai, 'conv_id' => $convId, 'user_text' => $text,
			'attachments' => $attachments, 'message_id' => $msgId, 'log' => $log));
		pi_msg_add($connect, $convId, 'assistant', $body);
		pi_draft_unlock($connect, $convId);
		qt_ai_log($ai, 'chat_turn', array_merge($log, array('ms' => (int) ((microtime(true) - $started) * 1000), 'outcome' => $status === null ? 'ok' : $status)));
		qt_json_exit(200, array_merge(chat_state($connect, $convId), array('conversations' => pi_conv_list($connect))));
	}

	if ($action === 'pick') {
		$pid = qt_parse_id(isset($_POST['product_id']) ? $_POST['product_id'] : '');
		chat_button($connect, $convId, 'Chose a product for an item.', function (&$draft) use ($connect, $rowParam, $pid) {
			$i = qt_ai_find_row($draft, $rowParam);
			if ($i === null || !empty($draft['rows'][$i]['removed'])) {
				return 'That item is no longer on the draft.';
			}
			$views = $pid !== null ? qt_ai_product_views($connect, array($pid), $draft['type'] === 'MRP' ? 'MRP' : 'DP') : array();
			if (!isset($views[$pid])) {
				return 'That product cannot be quoted (not found, or it has no single price row).';
			}
			pi_set_product($draft['rows'][$i], $pid, 'user');
			pi_add_dining_chairs($connect, $draft, array($draft['rows'][$i]['row_id']));   // a 6 seater dining table brings its 6 chairs
			return null;
		});
	}
	if ($action === 'confirm_zero') {
		chat_button($connect, $convId, 'Confirmed a ₹0 rate.', function (&$draft) use ($rowParam) {
			$i = qt_ai_find_row($draft, $rowParam);
			if ($i === null || $draft['rows'][$i]['product_id'] === null) {
				return 'Choose a product for that item first.';
			}
			$draft['rows'][$i]['zero_price_confirmed'] = (int) $draft['rows'][$i]['product_id'];
			return null;
		});
	}
	if ($action === 'use_standard') {
		chat_button($connect, $convId, 'Accepted the master list / software rate for an item missing from the state price list.', function (&$draft) use ($connect, $rowParam) {
			$i = qt_ai_find_row($draft, $rowParam);
			$pricing = pi_effective_pricing($connect, $draft);
			if ($i === null || $draft['rows'][$i]['product_id'] === null || $pricing['mode'] !== 'state') {
				return 'This only applies to an item without a rate in the chosen state price list.';
			}
			$draft['rows'][$i]['standard_price_ok'] = $pricing['code'] . ':' . (int) $draft['rows'][$i]['product_id'];
			return null;
		});
	}
	if ($action === 'rate') {
		$raw = isset($_POST['rate']) ? trim((string) $_POST['rate']) : '';
		$rate = $raw === '' ? null : pi_parse_rate($raw);
		if ($raw !== '' && $rate === null) {
			chat_fail(422, 'Type the rate in rupees, more than 0 (for example 15465 or 15465.50).');
		}
		chat_button($connect, $convId, $rate === null ? 'Went back to the list rate for an item.' : 'Typed the rate ' . pi_inr_text($rate) . ' for an item.',
			function (&$draft) use ($rowParam, $rate) {
				$i = qt_ai_find_row($draft, $rowParam);
				if ($i === null) {
					return 'That item is no longer on the draft.';
				}
				return pi_set_manual_rate($draft['rows'][$i], $rate, $draft['type'], $_SESSION['username']);
			});
	}
	if ($action === 'variant') {
		$variant = strtoupper(isset($_POST['variant']) ? (string) $_POST['variant'] : '');
		chat_button($connect, $convId, 'Chose ' . strtolower($variant) . ' wood for an item.', function (&$draft) use ($rowParam, $variant) {
			$i = qt_ai_find_row($draft, $rowParam);
			if ($i === null || !in_array($variant, array('TEAK', 'MANGO'), true)) {
				return 'Choose teak or mango wood for an item on the draft.';
			}
			$draft['rows'][$i]['variant'] = $variant;
			return null;
		});
	}
	if ($action === 'quantity') {
		$qty = qt_parse_qty(isset($_POST['quantity']) ? $_POST['quantity'] : '');
		chat_button($connect, $convId, 'Changed a quantity to ' . ($qty === null ? '?' : $qty) . '.', function (&$draft) use ($connect, $rowParam, $qty) {
			$i = qt_ai_find_row($draft, $rowParam);
			if ($i === null || $qty === null) {
				return 'Quantity must be a whole number from 1 to ' . QT_MAX_QTY . '.';
			}
			$err = qt_ai_edit_row($connect, $draft, $draft['rows'][$i]['row_id'], $draft['rows'][$i]['rev'], array('quantity' => (string) $qty));
			if ($err === null) {
				pi_add_dining_chairs($connect, $draft, array($draft['rows'][$i]['row_id']));   // chairs follow the number of tables
			}
			return $err;
		});
	}
	if ($action === 'remove_row') {
		chat_button($connect, $convId, 'Removed an item.', function (&$draft) use ($rowParam) {
			$i = qt_ai_find_row($draft, $rowParam);
			if ($i === null) {
				return 'That item is no longer on the draft.';
			}
			$draft['rows'][$i]['removed'] = true;
			return null;
		});
	}
	if ($action === 'preview') {
		list($draft, $version) = pi_draft_load($connect, $convId);
		$pi = pi_compute($connect, $draft);
		$card = pi_preview_card($pi, $version, $draft);
		pi_remember_preview($connect, $convId, $card);
		pi_msg_add($connect, $convId, 'event', array('text' => 'Showed the preview.', 'cards' => array($card)));
		qt_json_exit(200, chat_state($connect, $convId));
	}
	if ($action === 'generate') {
		$version = isset($_POST['version']) ? (int) $_POST['version'] : -1;
		$hash = isset($_POST['hash']) ? (string) $_POST['hash'] : '';
		if (!preg_match('/^[0-9a-f]{64}$/', $hash)) {
			chat_fail(400, 'Invalid confirmation.');
		}
		list($doc, $error, $status) = pi_generate($connect, $ai, $convId, $version, $hash);
		if ($error !== null) {
			if ($status === 409) {
				// Prices moved after the employee reviewed the preview: show what changed and a fresh preview to approve.
				list($draft, $current) = pi_draft_load($connect, $convId);
				$pi = pi_compute($connect, $draft);
				$diff = $current === $version ? pi_preview_price_diff($connect, $convId, $hash, $pi) : null;
				if ($diff !== null && count($diff['lines']) > 0) {
					$card = pi_preview_card($pi, $current, $draft);
					pi_remember_preview($connect, $convId, $card);
					pi_msg_add($connect, $convId, 'event', array('text' => 'Prices changed after the preview you approved. Review the differences and approve the updated draft.',
						'cards' => array($diff, $card)));
				}
			}
			chat_fail($status, $error, array('state' => chat_state($connect, $convId)));
		}
		if ($status === 201) {
			pi_msg_add($connect, $convId, 'event', array('text' => ($doc['kind'] === 'PI' ? 'Proforma Invoice ' : 'Quotation ') . $doc['number'] . ' generated.',
				'cards' => array(array('type' => 'document', 'document' => $doc))));
			qt_ai_log($ai, 'pi_generated', array('conversation' => $convId, 'user' => (string) $_SESSION['user_id'], 'document' => $doc['id']));
		}
		qt_json_exit(200, array_merge(chat_state($connect, $convId), array('document' => $doc, 'already_generated' => $status === 200)));
	}
	chat_fail(400, 'Unknown action.');
