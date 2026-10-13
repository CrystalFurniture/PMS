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

qt_json_exit(400, array('success' => false, 'error' => 'Unknown action.'));
