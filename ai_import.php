<?php
/**
 * AI quotation assistant endpoint (see includes/ai_import_lib.php).
 *
 * POST actions (login + CSRF token): create, process, search, update_row,
 * rematch, preview, apply, discard. GET action: image (owner only).
 * Only "apply" changes the quotation cart.
 */
	include_once('DB/conn.php');
	include_once('includes/quotation_lib.php');
	include_once('includes/ai_provider.php');
	include_once('includes/ai_import_lib.php');
	error_reporting(0);
	@session_start();

	$action = isset($_REQUEST['action']) ? (string) $_REQUEST['action'] : '';
	list($ai, $aiError) = qt_ai_settings();
	$storageReady = $ai['storage_dir'] !== '' && is_dir($ai['storage_dir'] . '/drafts');

	/* ---------------------------------------------------------------- uploaded image preview */
	if ($action === 'image' && $_SERVER['REQUEST_METHOD'] === 'GET') {
		if (!qt_is_logged_in() || !$storageReady) {
			http_response_code(404);
			exit;
		}
		list($draft, $error) = qt_ai_load_draft($ai, isset($_GET['draft']) ? (string) $_GET['draft'] : '');
		$n = isset($_GET['n']) ? qt_parse_id($_GET['n']) : null;
		if ($error !== null || $n === null || !isset($draft['input']['images'][$n - 1]) || !empty($draft['input']['images'][$n - 1]['deleted'])) {
			http_response_code(404);
			exit;
		}
		$img = $draft['input']['images'][$n - 1];
		$path = qt_ai_draft_dir($ai, $draft['id']) . '/' . $img['file'];
		if (!is_file($path)) {
			http_response_code(404);
			exit;
		}
		header('Content-Type: ' . $img['mime']);
		header('Content-Length: ' . filesize($path));
		header('X-Content-Type-Options: nosniff');
		header('Content-Disposition: inline; filename="requirement-' . (int) $n . '.' . pathinfo($img['file'], PATHINFO_EXTENSION) . '"');
		header('Cache-Control: private, no-store');
		readfile($path);
		exit;
	}

	/* ---------------------------------------------------------------- POST guard */
	qt_require_login_ajax();
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		qt_json_exit(405, array('success' => false, 'error' => 'Method not allowed.'));
	}
	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		qt_json_exit(403, array('success' => false, 'error' => 'Your page has expired. Please reload it and try again.'));
	}
	if (in_array($action, array('create', 'process', 'rematch'), true) && $aiError !== null) {
		qt_json_exit(503, array('success' => false, 'code' => 'not_configured', 'error' => $aiError . ' You can still add products manually.'));
	}
	if (in_array($action, array('create', 'process', 'rematch'), true) && ($budgetError = qt_ai_budget_error($ai)) !== null) {
		qt_ai_log($ai, 'budget_refused', array('user' => (string) $_SESSION['user_id'], 'action' => $action));
		qt_json_exit(402, array('success' => false, 'code' => 'budget', 'error' => $budgetError));
	}
	if (!$storageReady) {
		qt_json_exit(503, array('success' => false, 'code' => 'not_configured', 'error' => $aiError !== null ? $aiError : 'The AI assistant storage is not available.'));
	}

	$draftId = isset($_POST['draft']) ? (string) $_POST['draft'] : '';
	$log = array('draft' => $draftId, 'user' => (string) $_SESSION['user_id']);

function ai_fail($status, $message, $extra = array())
{
	qt_json_exit($status, array_merge(array('success' => false, 'error' => $message), $extra));
}

/** Release the session lock before a slow AI call; other tabs stay responsive. */
function ai_release_session()
{
	session_write_close();
	ignore_user_abort(true);
	list($ai) = qt_ai_settings();
	@set_time_limit((int) $ai['total_deadline'] * 2 + 120);
}

/* ---------------------------------------------------------------- create: validate + store input */
if ($action === 'create') {
	$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
	if (qt_config($type) === null) {
		ai_fail(400, 'Unknown quotation type.');
	}
	qt_ai_cleanup($ai);
	list($text, $images, $error) = qt_ai_validate_input($ai, $_POST, $_FILES);
	$instructions = trim(str_replace("
", "
", isset($_POST['instructions']) ? (string) $_POST['instructions'] : ''));
	if ($error === null && (!preg_match('//u', $instructions) || qt_ai_strlen($instructions) > 1000)) {
		$error = 'Instructions must be plain text of up to 1000 characters.';
	}
	if ($error !== null) {
		qt_ai_log($ai, 'create_rejected', $log);
		ai_fail(422, $error);
	}
	list($draft, $error) = qt_ai_create_draft($ai, $type, $text, $images, $instructions);
	if ($error !== null) {
		ai_fail(500, $error);
	}
	qt_ai_log($ai, 'create', array_merge($log, array('draft' => $draft['id'], 'type' => $type, 'images' => count($images), 'text_chars' => qt_ai_strlen($text), 'instruction_chars' => qt_ai_strlen($instructions), 'price_state' => qt_ai_draft_state($draft))));
	qt_json_exit(200, array('success' => true, 'draft' => $draft['id'], 'review_url' => 'AiReview.php?draft=' . $draft['id']));
}

/* ---------------------------------------------------------------- process: extraction + matching */
if ($action === 'process') {
	$stale = (int) $ai['total_deadline'] * 2 + 120;
	list($state, $error) = qt_ai_update_draft($ai, $draftId, function (&$draft) use ($stale) {
		if ($draft['status'] === 'ready') {
			return array('ready', null);
		}
		if ($draft['status'] === 'applied') {
			return array(null, 'These items were already added to the cart.');
		}
		if ($draft['status'] === 'processing' && time() - (int) $draft['processing_started'] < $stale) {
			return array(null, 'This requirement is already being read. Please wait.');
		}
		$draft['status'] = 'processing';
		$draft['processing_started'] = time();
		$draft['error'] = null;
		return array('start', null);
	});
	if ($error !== null) {
		ai_fail(409, $error);
	}
	if ($state === 'ready') {
		qt_json_exit(200, array('success' => true, 'review_url' => 'AiReview.php?draft=' . $draftId));
	}

	ai_release_session();
	list($draft) = qt_ai_load_draft($ai, $draftId);
	$started = microtime(true);
	$rows = null;
	$notes = '';
	$failure = null;
	list($content, $error) = qt_ai_extraction_content($ai, $draft);
	if ($error !== null) {
		$failure = array('code' => 'input_missing', 'message' => $error);
	} else {
		list($data, $error) = qt_ai_call_json($ai, 'extract', qt_ai_extraction_system_prompt(), $content, qt_ai_extraction_schema(), $log);
		if ($error !== null) {
			$failure = $error;
		} else {
			list($rows, $notes, $cleanError) = qt_ai_clean_extraction($ai, $draft, $data);
			if ($cleanError !== null) {
				$failure = array('code' => 'invalid_response', 'message' => $cleanError);
			} else {
				qt_ai_match_rows($ai, $connect, $rows, null, $log, qt_ai_instruction_text($draft));
			}
		}
	}

	qt_ai_update_draft($ai, $draftId, function (&$d) use ($rows, $notes, $failure) {
		if ($failure !== null) {
			$d['status'] = 'failed';
			$d['error'] = array('code' => $failure['code'], 'message' => $failure['message']);
		} else {
			$d['status'] = 'ready';
			$d['rows'] = $rows;
			$d['document_notes'] = $notes;
			$d['plan'] = null;
		}
		return array(true, null);
	});
	qt_ai_log($ai, 'process', array_merge($log, array('outcome' => $failure === null ? 'ready' : $failure['code'],
		'rows' => $rows === null ? 0 : count($rows), 'ms' => (int) ((microtime(true) - $started) * 1000))));
	if ($failure !== null) {
		ai_fail(502, $failure['message'] . ' You can retry, or add products manually.', array('code' => $failure['code'], 'review_url' => 'AiReview.php?draft=' . $draftId));
	}
	qt_json_exit(200, array('success' => true, 'review_url' => 'AiReview.php?draft=' . $draftId));
}

/* ---------------------------------------------------------------- everything below needs a reviewable draft */
list($draft, $error) = qt_ai_load_draft($ai, $draftId);
if ($error !== null) {
	ai_fail(404, $error);
}

if ($action === 'discard') {
	qt_ai_delete_dir(qt_ai_draft_dir($ai, $draftId));
	qt_ai_log($ai, 'discard', $log);
	qt_json_exit(200, array('success' => true, 'redirect' => qt_config($draft['type'])['create']));
}

// A repeated "apply" (double click, retry after a lost response) returns the first result.
$applyToken = isset($_POST['plan_token']) ? (string) $_POST['plan_token'] : '';
if ($action === 'apply' && $applyToken !== '' && (isset($_SESSION['qt_ai_applied'][$applyToken]) || isset($draft['applied'][$applyToken]))) {
	qt_json_exit(200, array('success' => true, 'already_applied' => true, 'redirect' => qt_config($draft['type'])['cartPage']));
}

if ($draft['status'] !== 'ready') {
	ai_fail(409, $draft['status'] === 'applied' ? 'These items were already added to the cart.' : 'This import is not ready for review.');
}

if ($action === 'search') {
	qt_json_exit(200, array('success' => true, 'products' => qt_ai_search($connect, $draft['type'], isset($_POST['q']) ? (string) $_POST['q'] : '', qt_ai_draft_state($draft))));
}

if ($action === 'update_row') {
	$edit = array();
	foreach (array('requirement', 'quantity', 'product_id', 'removed', 'confirm_zero_price') as $field) {
		if (isset($_POST[$field])) {
			$edit[$field] = $field === 'removed' ? $_POST[$field] === '1' : (string) $_POST[$field];
		}
	}
	$rowId = isset($_POST['row_id']) ? (string) $_POST['row_id'] : '';
	$rev = isset($_POST['rev']) ? (int) $_POST['rev'] : -1;
	list($view, $error) = qt_ai_update_draft($ai, $draftId, function (&$d) use ($connect, $rowId, $rev, $edit) {
		$err = qt_ai_edit_row($connect, $d, $rowId, $rev, $edit);
		if ($err !== null) {
			return array(null, $err);
		}
		return array(qt_ai_row_view($connect, $d, $d['rows'][qt_ai_find_row($d, $rowId)]), null);
	});
	if ($error !== null) {
		ai_fail(409, $error);
	}
	qt_json_exit(200, array('success' => true, 'row' => $view));
}

if ($action === 'rematch') {
	$rowId = isset($_POST['row_id']) ? (string) $_POST['row_id'] : '';
	$rev = isset($_POST['rev']) ? (int) $_POST['rev'] : -1;
	$i = qt_ai_find_row($draft, $rowId);
	if ($i === null || $draft['rows'][$i]['removed'] || (int) $draft['rows'][$i]['rev'] !== $rev) {
		ai_fail(409, 'This row changed. Reload the review page.');
	}
	ai_release_session();
	$rows = array($draft['rows'][$i]);
	$warnings = array();
	if (!isset($rows[0]['basis']) || !is_array($rows[0]['basis'])) {
		// The employee corrected the wording (any language): re-read it with the same extraction prompt.
		list($basis, $basisWarning) = qt_ai_correction_basis($ai, $rows[0], $log, qt_ai_instruction_text($draft));
		$rows[0]['basis'] = $basis;
		if ($basisWarning !== null) {
			$warnings[] = $basisWarning;
		}
	}
	$matchError = qt_ai_match_rows($ai, $connect, $rows, array($rowId), $log, qt_ai_instruction_text($draft));
	if ($matchError !== null) {
		$warnings[] = $matchError['message'];
	}
	$updated = $rows[0];
	list($view, $error) = qt_ai_update_draft($ai, $draftId, function (&$d) use ($connect, $rowId, $rev, $updated) {
		$j = qt_ai_find_row($d, $rowId);
		if ($j === null || (int) $d['rows'][$j]['rev'] !== $rev) {
			return array(null, 'This row was changed while it was being matched. Rematch it again.');
		}
		$d['rows'][$j]['match'] = $updated['match'];
		$d['rows'][$j]['basis'] = $updated['basis'];
		$d['rows'][$j]['match_stale'] = false;
		$d['rows'][$j]['product_id'] = $updated['product_id'];
		$d['rows'][$j]['product_source'] = $updated['product_source'];
		$d['rows'][$j]['rev']++;
		qt_ai_count_edit($d, 'rematch');
		$d['plan'] = null;
		return array(qt_ai_row_view($connect, $d, $d['rows'][$j]), null);
	});
	qt_ai_log($ai, 'rematch', array_merge($log, array('outcome' => $error !== null ? 'conflict' : ($matchError !== null ? $matchError['code'] : 'ok'))));
	if ($error !== null) {
		ai_fail(409, $error);
	}
	qt_json_exit(200, array('success' => true, 'row' => $view, 'warning' => count($warnings) > 0 ? implode(' ', $warnings) : null));
}

if ($action === 'preview') {
	$contextError = qt_ai_context_error($draft);
	if ($contextError !== null) {
		ai_fail(409, $contextError, array('code' => 'stale_context'));
	}
	$approvals = json_decode(isset($_POST['approvals']) ? (string) $_POST['approvals'] : '', true);
	list($plan, $error, $changes) = qt_ai_build_plan($connect, $draft, $approvals);
	if ($error !== null) {
		ai_fail(409, $error, array('code' => count($changes) > 0 ? 'price_changed' : 'invalid', 'price_changes' => $changes));
	}
	$token = bin2hex(random_bytes(16));
	list($ok, $error) = qt_ai_update_draft($ai, $draftId, function (&$d) use ($plan, $approvals, $token) {
		$d['plan'] = array('token' => $token, 'fingerprint' => $plan['fingerprint'], 'approvals' => $approvals, 'created_at' => time());
		return array(true, null);
	});
	if ($error !== null) {
		ai_fail(409, $error);
	}
	qt_json_exit(200, array('success' => true, 'plan' => $plan, 'plan_token' => $token, 'cart_page' => qt_config($draft['type'])['cartPage']));
}

if ($action === 'apply') {
	$token = $applyToken;
	$cartPage = qt_config($draft['type'])['cartPage'];
	$decisions = json_decode(isset($_POST['decisions']) ? (string) $_POST['decisions'] : '', true);
	list($count, $error) = qt_ai_update_draft($ai, $draftId, function (&$d) use ($ai, $connect, $token, $decisions) {
		if ($token !== '' && isset($d['applied'][$token])) {
			return array(-1, null);
		}
		if (empty($d['plan']) || $token === '' || !hash_equals($d['plan']['token'], $token)) {
			return array(null, 'This preview is out of date. Review the approved items again.');
		}
		$contextError = qt_ai_context_error($d);
		if ($contextError !== null) {
			return array(null, $contextError);
		}
		list($plan, $planError) = qt_ai_build_plan($connect, $d, $d['plan']['approvals']);
		if ($planError !== null || $plan['fingerprint'] !== $d['plan']['fingerprint']) {
			return array(null, 'The cart or prices changed since the preview. Review the approved items again.');
		}
		list($count, $applyError) = qt_ai_apply_plan($connect, $d, $plan, is_array($decisions) ? $decisions : array());
		if ($applyError !== null) {
			return array(null, $applyError);
		}
		$_SESSION['qt_ai_applied'][$token] = true;
		$d['applied'][$token] = $count;
		$d['approved_rows'] = count($d['plan']['approvals']);
		$d['status'] = 'applied';
		$d['plan'] = null;
		qt_ai_delete_images($ai, $d);
		return array($count, null);
	});
	if ($error !== null && empty($_SESSION['qt_ai_applied'][$token])) {
		qt_ai_log($ai, 'apply', array_merge($log, array('outcome' => 'rejected')));
		ai_fail(409, $error);
	}
	if ($count !== -1) {
		qt_flash_set('Added ' . (int) $count . ' product line(s) from the customer requirement. Review the cart before saving.', 'success');
	}
	$metrics = array();
	list($done) = qt_ai_load_draft($ai, $draftId);
	if ($done && $count !== -1) {
		// Pilot measurement (counts only): how much the employee had to correct.
		$aiKept = 0; $removed = 0;
		foreach ($done['rows'] as $r) {
			if ($r['removed']) { $removed++; }
			if (!$r['removed'] && $r['product_source'] === 'ai') { $aiKept++; }
		}
		$metrics = array('rows' => count($done['rows']), 'rows_approved' => isset($done['approved_rows']) ? (int) $done['approved_rows'] : null,
			'rows_removed' => $removed, 'ai_products_kept' => $aiKept);
		foreach ((isset($done['edits']) ? $done['edits'] : array()) as $k => $v) { $metrics['edit_' . $k] = (int) $v; }
	}
	qt_ai_log($ai, 'apply', array_merge($log, array('outcome' => $count === -1 ? 'repeat' : 'ok', 'lines' => (int) $count), $metrics));
	qt_json_exit(200, array('success' => true, 'already_applied' => $count === -1, 'redirect' => $cartPage));
}

ai_fail(400, 'Unknown action.');
