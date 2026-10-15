<?php
	include_once('DB/conn.php');
	include_once('includes/quotation_lib.php');
	error_reporting(0);
		@session_start();

	// Every action here changes the session cart or a quotation: logged-in POST with a CSRF token only.
	qt_require_login_ajax();
	if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
		qt_json_exit(405, array('success' => false, 'error' => 'Method not allowed.'));
	}
	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		qt_json_exit(403, array('success' => false, 'error' => 'Your page has expired. Please reload it and try again.'));
	}

	$action = isset($_POST['action']) ? (string) $_POST['action'] : '';

	$cartActions = array(
		'add_dp' => array('add', 'DP'), 'add_mrp' => array('add', 'MRP'),
		'quantityDP_change' => array('quantity', 'DP'), 'quantityMRP_change' => array('quantity', 'MRP'),
		'removeDP' => array('remove', 'DP'), 'removeMRP' => array('remove', 'MRP'),
	);

function cart_alert($status, $kind = 'warning')
{
	return '<div class="alert alert-' . $kind . ' alert-dismissible" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					  ' . qt_e($status) . '
					</div>';
}

function cart_table_response($type, $error = null)
{
	$key = ($type === 'DP') ? 'order_table' : 'order_tableMRP';
	$out = array('success' => $error === null, $key => qt_render_cart_table($type), 'summary' => qt_render_cart_summary($type));
	if ($error !== null) {
		$out['error'] = $error;
	}
	qt_json_exit(200, $out);
}

if (isset($cartActions[$action])) {
	list($kind, $type) = $cartActions[$action];
	$cfg = qt_config($type);
	$cart = qt_cart_get($type);

	// Add product (HTML response: status message + cart badge)
	if ($kind === 'add') {
		$productId = qt_parse_id(isset($_POST['product_id']) ? $_POST['product_id'] : '');
		$status = '';
		if ($productId === null) {
			$status = 'Invalid product.';
		} elseif (isset($cart[(string) $productId])) {
			$status = 'Product is already added to your cart!';
		} else {
			list($line, $error) = qt_load_product_line($connect, $productId, $type);
			$zeroConfirmed = isset($_POST['confirm_zero']) && $_POST['confirm_zero'] === '1';
			if ($error === null && qt_zero_price_rule() && qt_is_zero_price($line['price']) && !$zeroConfirmed) {
				// Pilot rule: a 0 price needs the employee's explicit confirmation (the create screen asks, then resends).
				if (isset($_POST['view']) && $_POST['view'] === 'summary') {
					qt_json_exit(200, array(
						'success' => false,
						'needs_confirm' => true,
						'confirm_text' => qt_zero_price_text($type, $line['name']),
						'message' => '',
						'summary' => qt_render_cart_summary($type),
						'count' => count($cart),
					));
				}
				$error = 'This product has a 0 price. Add it from the product screen and confirm the 0 price.';
			}
			if ($error !== null) {
				$status = $error;
			} else {
				if (qt_is_zero_price($line['price'])) {
					$line['zero_confirmed'] = $zeroConfirmed;
				}
				$cart[(string) $productId] = $line;
				qt_cart_set($type, $cart);
				$status = 'Product is added to your cart!' . ($line['price_note'] !== '' ? ' ' . $line['price_note'] : '');
			}
		}

		// The create screens' cart panel asks for JSON: message, refreshed panel, line count.
		if (isset($_POST['view']) && $_POST['view'] === 'summary') {
			qt_json_exit(200, array(
				'success' => isset($cart[(string) $productId]),
				'message' => cart_alert($status, strpos($status, 'Product is added') === 0 ? 'success' : 'warning'),
				'summary' => qt_render_cart_summary($type),
				'count' => count($cart),
			));
		}

		echo cart_alert($status);
		echo '<div class="cart_div">
					<a href="' . $cfg['cartPage'] . '"><img src="assets/images/Excel.png" width="50"/> <span>' . count($cart) . '</span></a>
					</div>';
		exit;
	}

	$lineKey = isset($_POST['line']) ? (string) $_POST['line'] : '';
	if (!isset($cart[$lineKey])) {
		cart_table_response($type, 'This line is no longer in the cart.');
	}

	// Quantity change (JSON response: refreshed cart table)
	if ($kind === 'quantity') {
		$qty = qt_parse_qty(isset($_POST['quantity']) ? $_POST['quantity'] : '');
		if ($qty === null) {
			cart_table_response($type, 'Quantity must be a whole number from 1 to ' . QT_MAX_QTY . '.');
		}
		$cart[$lineKey]['quantity'] = $qty;
		qt_cart_set($type, $cart);
		cart_table_response($type);
	}

	// Remove line (JSON response: refreshed cart table)
	unset($cart[$lineKey]);
	qt_cart_set($type, $cart);
	cart_table_response($type);
}

// Discard the cart and any quotation being edited, then start a new one (form post).
if ($action === 'new_quotation') {
	$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
	$cfg = qt_config($type);
	if ($cfg === null) {
		qt_json_exit(400, array('success' => false, 'error' => 'Unknown quotation type.'));
	}
	qt_clear_state($type);
	qt_flash_set('Started a new ' . $type . ' quotation.', 'success');
	qt_redirect($cfg['create']);
}

// Choose the price list (state) for a new quotation (form post). Only while the cart is empty.
if ($action === 'set_price_state') {
	$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
	$cfg = qt_config($type);
	if ($cfg === null) {
		qt_json_exit(400, array('success' => false, 'error' => 'Unknown quotation type.'));
	}
	$state = isset($_POST['state']) ? (string) $_POST['state'] : '';
	$states = qt_price_states($connect);
	if ($state !== '' && !isset($states[$state])) {
		qt_flash_set('That price list does not exist.');
	} elseif (count(qt_cart_get($type)) > 0 || qt_editing_id($type) !== null) {
		qt_flash_set('The price list cannot be changed after products are added. Start a new quotation to use another price list.');
	} else {
		qt_set_price_state($type, $state);
		qt_flash_set('Price list for this ' . $type . ' quotation: ' . qt_price_state_name($connect, $state) . '.', 'success');
	}
	qt_redirect($cfg['create']);
}

// Delete one of the user's own quotations (JSON response).
if ($action === 'delete_quotation') {
	$type = isset($_POST['type']) ? (string) $_POST['type'] : '';
	list($row, $error) = qt_fetch_owned_quotation($connect, isset($_POST['id']) ? $_POST['id'] : '', $type);
	if ($error !== null) {
		qt_json_exit(404, array('success' => false, 'error' => $error));
	}
	$ok = mysqli_query($connect, "DELETE FROM tbl_quotation WHERE q_id = " . (int) $row['q_id']
		. " AND q_user = '" . mysqli_real_escape_string($connect, $_SESSION['username']) . "'");
	if (!$ok) {
		qt_json_exit(500, array('success' => false, 'error' => 'The quotation could not be deleted.'));
	}
	if (qt_editing_id($type) === (int) $row['q_id']) {
		qt_clear_state($type);
	}
	qt_json_exit(200, array('success' => true));
}

// Send quotation (before approval): send_form returns the six contacts, the customer's filled in from the Tally
// ledger, salesperson and manager typed in; send_quotation sends the PDF to them. The status is not changed.
if ($action === 'send_form' || $action === 'send_quotation') {
	include_once('includes/send_lib.php');
	if (!qt_has_status($connect)) {
		qt_json_exit(500, array('success' => false, 'error' => 'Quotation approval is not set up yet: run cli/migrate_pi.php.'));
	}
	list($row, $error) = qt_fetch_owned_quotation($connect, isset($_POST['id']) ? $_POST['id'] : '', isset($_POST['type']) ? (string) $_POST['type'] : '');
	if ($error !== null) {
		qt_json_exit(404, array('success' => false, 'error' => $error));
	}
	$sendCfg = send_config();
	$tallyDb = isset($sendCfg['tally_db']) ? $sendCfg['tally_db'] : 'tally_io';
	$ledger = send_ledger_contact($connect, $tallyDb, (string) $row['q_name']);

	if ($action === 'send_form') {
		qt_json_exit(200, array('success' => true, 'title' => 'Quotation ' . $row['q_num'] . ' - ' . $row['q_name'],
			'ledger' => $ledger !== null ? $ledger['name'] : '',
			'values' => array(
				'cust_email' => $ledger !== null ? $ledger['email'] : '', 'cust_wa' => $ledger !== null ? $ledger['wa'] : '',
				'sales_email' => '', 'sales_wa' => '', 'mgr_email' => '', 'mgr_wa' => '')));
	}

	list($contacts, $errors) = send_read_form($_POST);
	if (count($errors)) {
		qt_json_exit(422, array('success' => false, 'error' => implode("\n", $errors), 'fields' => $errors));
	}
	foreach (array('email', 'wa') as $channel) {
		$problem = send_config_problem($sendCfg, $channel);
		if ($problem !== '') {
			qt_json_exit(500, array('success' => false, 'error' => $problem));
		}
	}
	include_once('includes/ai_provider.php');
	include_once('includes/ai_import_lib.php');
	require_once 'includes/quotation_pdf.php';
	list($ai) = qt_ai_settings();
	@set_time_limit(300);
	$items = qt_decode_items($row['q_items']);
	$rep = array('name' => isset($_SESSION['fullName']) ? trim((string) $_SESSION['fullName']) : '', 'phone' => isset($_SESSION['phone']) ? (string) $_SESSION['phone'] : '',
		'email' => isset($_SESSION['email']) ? (string) $_SESSION['email'] : '');
	try {
		list($pdf, $file) = qt_pdf_bytes($row, $items === null ? array() : $items, $rep, $ai['storage_dir'] !== '' ? $ai['storage_dir'] . '/cache/pdf-images' : '');
	} catch (Exception $e) {
		qt_json_exit(500, array('success' => false, 'error' => 'The quotation PDF could not be made, so it was not sent.'));
	}
	$number = $row['q_num'] . ((int) $row['q_revNum'] > 0 ? ' R' . (int) $row['q_revNum'] : '');
	$totals = qt_quotation_totals($row);   // [4] = after discount, before GST; the PDF's final amount adds 18% GST
	$results = send_document($sendCfg, $contacts, array('label' => 'Quotation ' . $number, 'filename' => $file, 'pdf' => $pdf,
		'number' => $number, 'total' => $totals !== null ? number_format($totals[4] * 1.18, 2) : '',
		'names' => array('cust' => $ledger !== null ? $ledger['name'] : '', 'sales' => '', 'mgr' => '')));
	send_log($connect, 'pms', 'quotation', $number, $_SESSION['username'], $results);
	$failed = 0;
	foreach ($results as $r) {
		$failed += $r[3] ? 0 : 1;
	}
	qt_flash_set('Quotation ' . $row['q_num'] . ': ' . ($failed ? $failed . ' of ' . count($results) . ' messages could not be sent; see the details shown.' : 'sent to all ' . count($results) . ' contacts.'),
		$failed ? 'warning' : 'success');
	qt_json_exit(200, array('success' => true, 'summary' => send_summary($results), 'failed' => $failed));
}

// Approve a quotation, then convert an approved one to an order. The status check is in the UPDATE itself.
if ($action === 'approve_quotation' || $action === 'convert_quotation') {
	if (!qt_has_status($connect)) {
		qt_json_exit(500, array('success' => false, 'error' => 'Quotation approval is not set up yet: run cli/migrate_pi.php.'));
	}
	list($row, $error) = qt_fetch_owned_quotation($connect, isset($_POST['id']) ? $_POST['id'] : '', isset($_POST['type']) ? (string) $_POST['type'] : '');
	if ($error !== null) {
		qt_json_exit(404, array('success' => false, 'error' => $error));
	}
	$user = mysqli_real_escape_string($connect, $_SESSION['username']);
	if ($action === 'approve_quotation') {
		$set = "q_status = 'approved', q_approvedBy = '" . $user . "', q_approvedAt = NOW()";
		$from = "''";
		$fail = 'Only a quotation awaiting approval can be approved.';
	} else {
		$set = "q_status = 'ordered', q_orderedAt = NOW()";
		$from = "'approved'";
		$fail = 'Only an approved quotation can be converted to an order.';
	}
	$ok = mysqli_query($connect, "UPDATE tbl_quotation SET " . $set . " WHERE q_id = " . (int) $row['q_id']
		. " AND q_user = '" . $user . "' AND q_status = " . $from);
	if (!$ok) {
		qt_json_exit(500, array('success' => false, 'error' => 'The quotation could not be updated.'));
	}
	if (mysqli_affected_rows($connect) !== 1) {
		qt_json_exit(409, array('success' => false, 'error' => $fail));
	}
	if ($action === 'approve_quotation') {
		qt_flash_set('Quotation ' . $row['q_num'] . ' approved.', 'success');
		qt_json_exit(200, array('success' => true));
	}
	qt_json_exit(200, array('success' => true, 'redirect' => qt_sale_order_url($row['q_id'])));
}

qt_json_exit(400, array('success' => false, 'error' => 'Unknown action.'));
