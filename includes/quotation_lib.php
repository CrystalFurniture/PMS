<?php
/**
 * Shared helpers for the DP / MRP quotation flow:
 *   CreatedOrder*.php -> action.php (cart AJAX) -> ViewOrder*.php (cart)
 *   -> ExportToExcel*.php (save, then show) -> ViewQuotation.php / DetailQuotation*.php
 *
 * Kept procedural and dependency-free to match the rest of the app.
 * Requires DB/conn.php (session + $connect) to be included first.
 */

// Largest quantity accepted on one cart line (positive whole numbers only).
// Saved quotations peak at 300 per line, so this only stops typos and abuse.
define('QT_MAX_QTY', 9999);

// Mirrors the rule the cart form already enforced in the browser
// (jquery-validate "max: 35" in ViewOrderDp.php / ViewOrderMRP.php).
define('QT_MAX_DISCOUNT', 35);

// tbl_quotation.q_name is varchar(100); tbl_quotation.q_items is TEXT.
define('QT_MAX_CLIENT_NAME', 100);
define('QT_ITEMS_MAX_BYTES', 65535);

function qt_config($type)
{
	$types = array(
		'DP' => array(
			'cart' => 'shopping_cartDp', 'name' => 'nameDP', 'discount' => 'discountDP',
			'qid' => 'QidDP', 'qnum' => 'QnumDP', 'price' => 'dp_price',
			'qtyClass' => 'quantityDP', 'deleteClass' => 'delete', 'totalColspan' => 6,
			'create' => 'CreatedOrderDp.php', 'cartPage' => 'ViewOrderDp.php',
			'export' => 'ExportToExcelDp.php', 'detail' => 'DetailQuotationDp.php',
		),
		'MRP' => array(
			'cart' => 'shopping_cartMRP', 'name' => 'nameMRP', 'discount' => 'discountMRP',
			'qid' => 'QidMRP', 'qnum' => 'QnumMRP', 'price' => 'mrp_price',
			'qtyClass' => 'quantityMRP', 'deleteClass' => 'deleteMRP', 'totalColspan' => 5,
			'create' => 'CreatedOrderMRP.php', 'cartPage' => 'ViewOrderMRP.php',
			'export' => 'ExportToExcelMRP.php', 'detail' => 'DetailQuotationMRP.php',
		),
	);
	return isset($types[$type]) ? $types[$type] : null;
}

/* ---------------------------------------------------------------- auth */

function qt_is_logged_in()
{
	return !empty($_SESSION['user_id']) && isset($_SESSION['username']) && $_SESSION['username'] !== '';
}

function qt_require_login_page()
{
	if (!qt_is_logged_in()) {
		$msg = base64_encode(serialize("Please Login To Access You Account"));
		header("Location: index.php?failmsg=$msg");
		exit;
	}
}

function qt_json_exit($status, $data)
{
	http_response_code($status);
	header('Content-Type: application/json');
	echo json_encode($data);
	exit;
}

function qt_require_login_ajax()
{
	if (!qt_is_logged_in()) {
		qt_json_exit(401, array('success' => false, 'error' => 'Your session has expired. Please log in again.'));
	}
}

/* ---------------------------------------------------------------- csrf */

function qt_csrf_token()
{
	if (empty($_SESSION['qt_csrf'])) {
		$_SESSION['qt_csrf'] = bin2hex(random_bytes(32));
	}
	return $_SESSION['qt_csrf'];
}

function qt_csrf_valid($token)
{
	return is_string($token) && !empty($_SESSION['qt_csrf']) && hash_equals($_SESSION['qt_csrf'], $token);
}

function qt_csrf_field()
{
	return '<input type="hidden" name="csrf_token" id="qt_csrf" value="' . qt_e(qt_csrf_token()) . '">';
}

/* ---------------------------------------------------------------- output */

function qt_e($value)
{
	// Product data often holds entities already (3&rsquo;x5&rsquo;): show them, do not re-encode.
	return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
}

/**
 * Product descriptions are stored as formatted HTML (mostly <p>, <br>, <b>).
 * Keep that formatting but drop every other tag and every attribute, so
 * scripts, event handlers and styles in product or saved text cannot run.
 */
function qt_safe_html($html)
{
	$allowed = '<p><br><b><strong><i><em><u><h4><div><blockquote><small><ul><ol><li><span>';
	$clean = strip_tags((string) $html, $allowed);
	return preg_replace('#<(/?)([a-z0-9]+)\b[^>]*>#i', '<$1$2>', $clean);
}

function qt_flash_set($text, $kind = 'danger')
{
	$_SESSION['qt_flash'] = array('text' => $text, 'kind' => $kind);
}

function qt_flash_render()
{
	if (empty($_SESSION['qt_flash'])) {
		return '';
	}
	$f = $_SESSION['qt_flash'];
	unset($_SESSION['qt_flash']);
	$kind = in_array($f['kind'], array('success', 'warning', 'danger', 'info'), true) ? $f['kind'] : 'danger';
	return '<div class="alert alert-' . $kind . ' alert-dismissible" role="alert">'
		. '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'
		. qt_e($f['text']) . '</div>';
}

/** Legacy ?msg= / ?failmsg= values: decode without allowing objects, then escape. */
function qt_legacy_msg($encoded)
{
	$text = @unserialize(base64_decode((string) $encoded), array('allowed_classes' => false));
	return is_string($text) ? qt_e($text) : '';
}

function qt_redirect($url)
{
	header('Location: ' . $url, true, 303);
	exit;
}

/* ---------------------------------------------------------------- input */

/** Positive whole number 1..QT_MAX_QTY, or null. */
function qt_parse_qty($value)
{
	$value = trim((string) $value);
	if ($value === '' || !ctype_digit($value) || strlen($value) > 6) {
		return null;
	}
	$qty = (int) $value;
	return ($qty >= 1 && $qty <= QT_MAX_QTY) ? $qty : null;
}

/** Positive integer id, or null. */
function qt_parse_id($value)
{
	$value = trim((string) $value);
	if ($value === '' || !ctype_digit($value) || strlen($value) > 10) {
		return null;
	}
	$id = (int) $value;
	return $id > 0 ? $id : null;
}

/* ---------------------------------------------------------------- cart */

/**
 * Decode tbl_quotation.q_items. Current rows are base64(serialize(array));
 * older rows are plain serialize(array). Returns the line array, an empty
 * array for a saved empty cart, or null when the value cannot be read.
 */
function qt_decode_items($raw)
{
	$raw = (string) $raw;
	$candidates = array();
	$decoded = base64_decode($raw, true);
	if ($decoded !== false) {
		$candidates[] = $decoded;
	}
	$candidates[] = $raw;
	foreach ($candidates as $c) {
		if ($c === 'N;' || $c === 'b:0;') {
			return array();
		}
		$items = @unserialize($c, array('allowed_classes' => false));
		if (is_array($items)) {
			return $items;
		}
	}
	return null;
}

function qt_encode_items($cart)
{
	return base64_encode(serialize($cart));
}

/**
 * Re-key cart lines by product id. Lines without a product id (older
 * quotations) keep their saved values under a "legacy-N" key: they are never
 * matched to a product by name. A repeated id gets its own suffixed key.
 */
function qt_normalize_cart($items)
{
	$cart = array();
	$legacy = 0;
	foreach ((array) $items as $line) {
		if (!is_array($line)) {
			continue;
		}
		foreach (array('id', 'name', 'description', 'color', 'price', 'quantity', 'image', 'size') as $field) {
			if (!isset($line[$field])) {
				$line[$field] = '';
			}
		}
		$id = qt_parse_id($line['id']);
		if ($id === null) {
			$key = 'legacy-' . (++$legacy);
		} else {
			$key = (string) $id;
			$n = 1;
			while (isset($cart[$key])) {
				$key = $id . '-' . (++$n);
			}
		}
		$cart[$key] = $line;
	}
	return $cart;
}

function qt_cart_get($type)
{
	$cfg = qt_config($type);
	$cart = (isset($_SESSION[$cfg['cart']]) && is_array($_SESSION[$cfg['cart']])) ? $_SESSION[$cfg['cart']] : array();
	if (empty($_SESSION['qt_cart_keyed'][$type])) {
		// Cart started before this version (keyed by product name): re-key once.
		$cart = qt_normalize_cart($cart);
		qt_cart_set($type, $cart);
	}
	return $cart;
}

function qt_cart_set($type, $cart)
{
	$cfg = qt_config($type);
	$_SESSION[$cfg['cart']] = $cart;
	$_SESSION['qt_cart_keyed'][$type] = true;
}

/** Forget the cart and any quotation being edited for this type. */
function qt_clear_state($type)
{
	$cfg = qt_config($type);
	unset($_SESSION[$cfg['cart']], $_SESSION[$cfg['name']], $_SESSION[$cfg['discount']],
		$_SESSION[$cfg['qid']], $_SESSION[$cfg['qnum']], $_SESSION['qt_submit'][$type],
		$_SESSION['qt_cart_keyed'][$type], $_SESSION['qt_price_state'][$type]);
}

/* ---------------------------------------------------------------- state price lists */

/** True when the state price-list tables exist (cli/migrate_state_pricelists.php has been run). */
function qt_state_tables_exist($connect)
{
	static $exists = null;
	if ($exists === null) {
		$r = mysqli_query($connect, "SHOW TABLES LIKE 'state_pricelist'");
		$exists = $r && mysqli_num_rows($r) === 1;
	}
	return $exists;
}

/** Active state price lists: code => name. Empty when none exist. */
function qt_price_states($connect)
{
	if (!qt_state_tables_exist($connect)) {
		return array();
	}
	$out = array();
	$r = mysqli_query($connect, 'SELECT code, name FROM price_states WHERE active = 1 ORDER BY name');
	while ($r && $row = mysqli_fetch_assoc($r)) {
		$out[$row['code']] = $row['name'];
	}
	return $out;
}

/** Price list chosen for the current quotation of this type: state code, or '' for the standard list. */
function qt_price_state($type)
{
	return isset($_SESSION['qt_price_state'][$type]) ? (string) $_SESSION['qt_price_state'][$type] : '';
}

function qt_set_price_state($type, $code)
{
	$_SESSION['qt_price_state'][$type] = (string) $code;
}

function qt_price_state_name($connect, $code)
{
	if ($code === '') {
		return 'Standard';
	}
	$states = qt_price_states($connect);
	return isset($states[$code]) ? $states[$code] : $code;
}

/** Prices are shown as the price list stores them: whole numbers without decimals. */
function qt_price_str($value)
{
	$f = (float) $value;
	return floor($f) == $f ? (string) (int) $f : number_format($f, 2, '.', '');
}

/**
 * Price of one product for a quotation type and price list.
 * Returns array(price, price_list, note): price_list is the state code when the
 * state list has the product; otherwise the standard price is used (price_list '')
 * and note explains why.
 */
function qt_state_price($connect, $productId, $type, $standardPrice, $state)
{
	if ($state === '' || !qt_state_tables_exist($connect)) {
		return array(qt_price_str($standardPrice), '', '');
	}
	$cfg = qt_config($type);
	// Plain rate first when a list also has variant rows (teak / mango): the manual screens use the plain rate.
	$hasVariant = ($vc = mysqli_query($connect, "SHOW COLUMNS FROM state_pricelist LIKE 'variant'")) && mysqli_num_rows($vc) > 0;
	$r = mysqli_query($connect, "SELECT mrp_price, dp_price FROM state_pricelist WHERE state_code = '"
		. mysqli_real_escape_string($connect, $state) . "' AND p_prodId = " . (int) $productId . ($hasVariant ? " ORDER BY variant = '' DESC, variant" : ''));
	$row = $r ? mysqli_fetch_assoc($r) : null;
	if ($row && $row[$cfg['price']] !== null && $row[$cfg['price']] !== '') {
		return array(qt_price_str($row[$cfg['price']]), $state, '');
	}
	return array(qt_price_str($standardPrice), '', 'Not in the ' . qt_price_state_name($connect, $state) . ' price list: standard price used.');
}

function qt_editing_id($type)
{
	$cfg = qt_config($type);
	return isset($_SESSION[$cfg['qid']]) ? qt_parse_id($_SESSION[$cfg['qid']]) : null;
}

/**
 * One product as a cart line, priced from the database: from the chosen state's
 * price list when it has the product, otherwise the standard list (with a note).
 * $state null = the price list chosen for the current quotation.
 * Returns array(line, error).
 */
function qt_load_product_line($connect, $productId, $type, $state = null)
{
	if ($state === null) {
		$state = qt_price_state($type);
	}
	$cfg = qt_config($type);
	$id = (int) $productId;
	$result = mysqli_query($connect, "SELECT p.id, p.name, p.description, p.color, p.dimension, p.thumb, pl.mrp_price, pl.dp_price
		FROM products p INNER JOIN pricelist pl ON pl.p_prodId = p.id WHERE p.id = " . $id);
	$rows = $result ? mysqli_fetch_all($result, MYSQLI_ASSOC) : array();
	if (count($rows) === 0) {
		return array(null, 'This product was not found or has no price.');
	}
	if (count($rows) > 1) {
		// Not expected (one price row per product today); refuse rather than guess.
		return array(null, 'This product has more than one price row. Please correct it in Price List before quoting.');
	}
	$row = $rows[0];
	list($price, $priceList, $note) = qt_state_price($connect, $row['id'], $type, $row[$cfg['price']], $state);
	return array(array(
		'id' => (string) $row['id'],
		'name' => $row['name'],
		'description' => $row['description'],
		'color' => $row['color'],
		'price' => $price,
		'quantity' => 1,
		'image' => $row['thumb'],
		'size' => $row['dimension'],
		'price_list' => $priceList,      // state code, or '' for the standard list
		'price_note' => $note,
	), null);
}

/** Cart table shared by the cart page and the AJAX responses. */
function qt_render_cart_table($type)
{
	$cfg = qt_config($type);
	$cart = qt_cart_get($type);
	$html = '<table id="example" class="table table-bordered table-striped" data-page-length="200">
		<thead>
			<tr>
			<th width="15%"></th>
			<th width="20%">ITEM NAME</th>
			<th width="15%">SIZE</th>
			<th width="20%">QUANTITY</th>
			<th width="10%">UNIT PRICE</th>
			<th width="10%">ITEMS TOTAL</th>
			<th width="10%">REMOVE</th>
			</tr>
		</thead>
		<tbody>';
	$total = 0;
	foreach ($cart as $key => $line) {
		$lineTotal = (float) $line['price'] * (int) $line['quantity'];
		$total += $lineTotal;
		$html .= '
			<tr>
			<td><img src="' . qt_e(str_replace(' ', '%20', $line['image'])) . '" width="50" height="40" /></td>
			<td>' . qt_e($line['name'])
				. (!empty($line['price_list']) ? '<br><small class="text-muted">' . qt_e($line['price_list']) . ' price list</small>' : '')
				. (!empty($line['price_note']) ? '<br><small class="text-warning">&#9888; ' . qt_e($line['price_note']) . '</small>' : '')
				. (qt_is_zero_price($line['price']) ? '<br><small class="text-warning">&#9888; &#8377;0 price: no price in the price list' . (!empty($line['zero_confirmed']) ? ' (confirmed)' : '') . '</small>' : '') . '</td>
			<td>' . qt_e($line['size']) . '</td>
			<td><div class="col-xs-6">
				<input type="number" class="form-control col-md-4 ' . $cfg['qtyClass'] . '" name="quantity" min="1" max="' . QT_MAX_QTY . '" step="1" value="' . qt_e($line['quantity']) . '" data-line="' . qt_e($key) . '" autocomplete="off"/>
			</div></td>
			<td>&#8377; ' . qt_e($line['price']) . '</td>
			<td>&#8377; ' . qt_e($lineTotal) . '</td>
			<td><button type="button" name="delete" class="btn btn-danger btn-xs ' . $cfg['deleteClass'] . '" data-line="' . qt_e($key) . '"> X </button></td>
			</tr>';
	}
	$html .= '
			<tr>
			<td colspan="' . $cfg['totalColspan'] . '" align="right"><strong>TOTAL: &#8377; ' . qt_e($total) . '</strong></td>
			</tr>
		</tbody>
	</table>';
	return $html;
}

/* ---------------------------------------------------------------- quotations */

/**
 * Load a quotation the logged-in user may access: their own, of this type.
 * Returns array(row, error).
 */
function qt_fetch_owned_quotation($connect, $qid, $type)
{
	$id = qt_parse_id($qid);
	if ($id === null || qt_config($type) === null) {
		return array(null, 'Quotation not found.');
	}
	$result = mysqli_query($connect, "SELECT * FROM tbl_quotation WHERE q_id = " . $id
		. " AND q_type = '" . mysqli_real_escape_string($connect, $type) . "'");
	$row = $result ? mysqli_fetch_assoc($result) : null;
	if (!$row) {
		return array(null, 'Quotation not found.');
	}
	if ((string) $row['q_user'] !== (string) $_SESSION['username']) {
		return array(null, 'You do not have access to this quotation.');
	}
	return array($row, null);
}

/** Tally sale order page that opens an approved quotation as an order (tallyTest/api/import_quotation.php). */
function qt_sale_order_url($qid)
{
	return 'http://192.168.1.111/tallyTest/api/import_quotation.php?id=' . (int) $qid;
}

/** Whether tbl_quotation has the approval columns (added by cli/migrate_pi.php). */
function qt_has_status($connect)
{
	static $has = null;
	if ($has === null) {
		$has = ($c = mysqli_query($connect, "SHOW COLUMNS FROM tbl_quotation LIKE 'q_status'")) && mysqli_num_rows($c) > 0;
	}
	return $has;
}

/** Submit token for the cart form; consumed by a successful save. */
function qt_submit_token($type)
{
	if (empty($_SESSION['qt_submit'][$type])) {
		$_SESSION['qt_submit'][$type] = bin2hex(random_bytes(16));
	}
	return $_SESSION['qt_submit'][$type];
}

/**
 * Save an edited quotation as its next revision: a new row with the same quotation number, type, owner and
 * original date, revision number one above the highest saved for that number (R1, R2, ...), and today's
 * revision date. The row that was edited, the original and any other revision are left unchanged, so every
 * version can still be opened. Returns the new q_id or null.
 */
function qt_insert_revision($connect, $row, $clientName, $discount, $items)
{
	$esc = function ($v) use ($connect) { return mysqli_real_escape_string($connect, (string) $v); };
	$where = "q_num = '" . $esc($row['q_num']) . "' AND q_type = '" . $esc($row['q_type']) . "' AND q_user = '" . $esc($row['q_user']) . "'";
	mysqli_query($connect, 'START TRANSACTION');
	$max = mysqli_query($connect, "SELECT MAX(q_revNum) FROM tbl_quotation WHERE $where FOR UPDATE");
	$next = ($max ? (int) mysqli_fetch_row($max)[0] : (int) $row['q_revNum']) + 1;
	$ok = mysqli_query($connect, "INSERT INTO tbl_quotation (q_num, q_revNum, q_name, q_discount, q_items, q_date, q_revDate, q_user, q_type) VALUES ('"
		. $esc($row['q_num']) . "', " . $next . ", '" . $esc($clientName) . "', '" . $esc($discount) . "', '" . $esc($items) . "', '"
		. $esc($row['q_date']) . "', '" . date('d-m-Y') . "', '" . $esc($row['q_user']) . "', '" . $esc($row['q_type']) . "')");
	$id = $ok ? (int) mysqli_insert_id($connect) : null;
	mysqli_query($connect, $ok ? 'COMMIT' : 'ROLLBACK');
	return $id;
}

/**
 * Next quotation number, inserted while holding the counter lock so two
 * saves cannot take the same number. Format and counter file are unchanged:
 * Cry/{yy}-{yy+1}/Q-{n} from count.txt. Returns the new q_id or null.
 */
function qt_insert_new_quotation($connect, $type, $clientName, $discount, $items, $user = null, $date = null)
{
	// $user / $date: for rows saved on someone's behalf (an approved PI added later); default the signed-in user, today.
	$user = $user === null ? $_SESSION['username'] : $user;
	$date = $date === null ? date('d-m-Y') : $date;
	$counterFile = dirname(__DIR__) . '/count.txt';
	$fp = fopen($counterFile, 'c+');
	if (!$fp || !flock($fp, LOCK_EX)) {
		return null;
	}
	$count = (int) stream_get_contents($fp);

	$last = date("y");
	$year = $last + 1;
	$number = null;
	for ($attempt = 0; $attempt < 1000; $attempt++) {
		$count++;
		$candidate = "Cry/" . $last . '-' . $year . "/Q-" . $count;
		$exists = mysqli_query($connect, "SELECT 1 FROM tbl_quotation WHERE q_num = '" . mysqli_real_escape_string($connect, $candidate) . "' LIMIT 1");
		if ($exists && mysqli_num_rows($exists) === 0) {
			$number = $candidate;
			break;
		}
	}

	$newId = null;
	if ($number !== null) {
		$esc = function ($v) use ($connect) { return mysqli_real_escape_string($connect, $v); };
		// q_revNum / q_revDate have no column default; set them explicitly so strict SQL mode also accepts the row.
		$ok = mysqli_query($connect, "INSERT INTO tbl_quotation (q_num, q_revNum, q_name, q_discount, q_items, q_date, q_revDate, q_user, q_type) VALUES ('"
			. $esc($number) . "', 0, '" . $esc($clientName) . "', '" . $esc($discount) . "', '" . $esc($items) . "', '"
			. $esc($date) . "', '', '" . $esc($user) . "', '" . $esc($type) . "')");
		if ($ok) {
			$newId = mysqli_insert_id($connect);
			ftruncate($fp, 0);
			rewind($fp);
			fwrite($fp, (string) $count);
			fflush($fp);
		}
	}
	flock($fp, LOCK_UN);
	fclose($fp);
	return $newId;
}

/**
 * Handle the cart form POST for ExportToExcelDp.php / ExportToExcelMRP.php.
 * Always ends in a redirect: to the saved quotation, or back to the cart
 * with a message. Never returns.
 */
function qt_handle_save($connect, $type)
{
	$cfg = qt_config($type);
	$back = $cfg['cartPage'];

	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		qt_flash_set('Your form expired. Please review the cart and submit again.');
		qt_redirect($back);
	}

	$token = isset($_POST['submit_token']) ? (string) $_POST['submit_token'] : '';
	if ($token !== '' && isset($_SESSION['qt_saved'][$token])) {
		// Same form submitted again (double click, back + resubmit): show the first result.
		qt_redirect($cfg['export'] . '?id=' . (int) $_SESSION['qt_saved'][$token]);
	}
	if ($token === '' || empty($_SESSION['qt_submit'][$type]) || !hash_equals($_SESSION['qt_submit'][$type], $token)) {
		qt_flash_set('This form was already submitted or has expired. Please review the cart and submit again.');
		qt_redirect($back);
	}

	$editing = qt_editing_id($type);
	$postedEdit = isset($_POST['edit_id']) ? trim((string) $_POST['edit_id']) : '';
	if ($postedEdit !== ($editing === null ? '' : (string) $editing)) {
		qt_flash_set('The cart changed in another tab. Please review it and submit again.');
		qt_redirect($back);
	}

	$cart = qt_cart_get($type);
	if (count($cart) === 0) {
		qt_flash_set('Add at least one product before saving the quotation.');
		qt_redirect($back);
	}
	foreach ($cart as $line) {
		if (qt_parse_qty($line['quantity']) === null) {
			qt_flash_set('Quantity for "' . $line['name'] . '" must be a whole number from 1 to ' . QT_MAX_QTY . '.');
			qt_redirect($back);
		}
	}

	$clientName = isset($_POST['ClientName']) ? trim((string) $_POST['ClientName']) : '';
	$discount = isset($_POST['Discount']) ? trim((string) $_POST['Discount']) : '';
	$_SESSION[$cfg['name']] = $clientName;
	$_SESSION[$cfg['discount']] = $discount;
	if ($clientName === '') {
		qt_flash_set('Client name is required.');
		qt_redirect($back);
	}
	if (strlen($clientName) > QT_MAX_CLIENT_NAME) {
		qt_flash_set('Client name must be at most ' . QT_MAX_CLIENT_NAME . ' characters.');
		qt_redirect($back);
	}
	if ($discount !== '' && (!is_numeric($discount) || $discount < 0 || $discount > QT_MAX_DISCOUNT)) {
		qt_flash_set('Discount must be a number from 0 to ' . QT_MAX_DISCOUNT . '.');
		qt_redirect($back);
	}

	$items = qt_encode_items($cart);
	if (strlen($items) > QT_ITEMS_MAX_BYTES) {
		qt_flash_set('This quotation has too many lines to save in one document. Please split it into two quotations.');
		qt_redirect($back);
	}

	if ($editing !== null) {
		list($row, $error) = qt_fetch_owned_quotation($connect, $editing, $type);
		if ($error !== null) {
			qt_flash_set($error . ' The cart was not saved.');
			qt_redirect($back);
		}
		// A revision is a new row; the original and earlier revisions are kept as they were.
		$savedId = qt_insert_revision($connect, $row, $clientName, $discount, $items);
	} else {
		$savedId = qt_insert_new_quotation($connect, $type, $clientName, $discount, $items);
	}

	if ($savedId === null) {
		qt_flash_set('The quotation could not be saved. Please try again.');
		qt_redirect($back);
	}

	$_SESSION['qt_saved'][$token] = $savedId;
	if (count($_SESSION['qt_saved']) > 20) {
		$_SESSION['qt_saved'] = array_slice($_SESSION['qt_saved'], -20, null, true);
	}
	qt_clear_state($type);
	qt_redirect($cfg['export'] . '?id=' . (int) $savedId);
}

/* ---------------------------------------------------------------- zero-price rule (pilot) */

/**
 * Pilot rule, not company policy: when the web server sets PMS_ZERO_PRICE_RULE=confirm,
 * a product whose quotation price is 0 is added only after the employee confirms it
 * (manual Add and AI import alike). Off when the variable is absent.
 */
function qt_zero_price_rule()
{
	return getenv('PMS_ZERO_PRICE_RULE') === 'confirm';
}

function qt_is_zero_price($price)
{
	return (float) $price <= 0;
}

/** Explanation shown before an employee confirms a 0 price. */
function qt_zero_price_text($type, $name)
{
	return '"' . $name . '" has no ' . $type . ' price in the price list (it would be quoted at ₹0). Add it at ₹0 anyway?';
}

/* ---------------------------------------------------------------- display helpers */

/** Rupee amount with Indian digit grouping (12,34,567); decimals only when present. */
function qt_inr($amount)
{
	$amount = round((float) $amount, 2);
	$sign = $amount < 0 ? '-' : '';
	$amount = abs($amount);
	$whole = (string) (int) floor($amount);
	$fraction = (int) round(($amount - floor($amount)) * 100);
	if (strlen($whole) > 3) {
		$head = substr($whole, 0, -3);
		$head = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $head);
		$whole = $head . ',' . substr($whole, -3);
	}
	return '&#8377; ' . $sign . $whole . ($fraction > 0 ? '.' . str_pad((string) $fraction, 2, '0', STR_PAD_LEFT) : '');
}

/**
 * Totals of a saved quotation row: array(lines, quantity, gross, discountPercent, net).
 * net = gross less the quotation discount, before GST (as on the detail pages). Null if unreadable.
 */
function qt_quotation_totals($row)
{
	$items = qt_decode_items(isset($row['q_items']) ? $row['q_items'] : '');
	if ($items === null) {
		return null;
	}
	$gross = 0;
	$qty = 0;
	foreach ($items as $line) {
		if (!is_array($line)) {
			continue;
		}
		$q = isset($line['quantity']) ? (float) $line['quantity'] : 0;
		$gross += (isset($line['price']) ? (float) $line['price'] : 0) * $q;
		$qty += $q;
	}
	$discount = isset($row['q_discount']) && $row['q_discount'] !== '' ? (float) $row['q_discount'] : 0;
	return array(count($items), $qty, $gross, $discount, $gross - ($gross * $discount / 100));
}

/** Side panel on the create-quotation pages: the current cart with quantities and total. */
function qt_render_cart_summary($type)
{
	$cfg = qt_config($type);
	$cart = qt_cart_get($type);
	$total = 0;
	$lines = '';
	foreach ($cart as $key => $line) {
		$lineTotal = (float) $line['price'] * (int) $line['quantity'];
		$total += $lineTotal;
		$k = qt_e($key);
		$name = qt_e($line['name']);
		$lines .= '<div class="pms-cart-line">'
			. '<div class="pms-cart-line-top"><strong>' . $name . '</strong><span class="pms-num">' . qt_inr($lineTotal) . '</span></div>'
			. (!empty($line['price_note']) ? '<div class="pms-cart-line-note">&#9888; ' . qt_e($line['price_note']) . '</div>' : '')
			. (qt_is_zero_price($line['price']) ? '<div class="pms-cart-line-note">&#9888; &#8377;0 price: no price in the price list' . (!empty($line['zero_confirmed']) ? ' (confirmed by you)' : '') . '</div>' : '')
			. '<div class="pms-cart-line-ctl">'
			. '<button type="button" class="pms-qty-btn qt-sum-qty" data-line="' . $k . '" data-step="-1" aria-label="One fewer ' . $name . '">&minus;</button>'
			. '<input type="number" class="pms-qty-input qt-sum-qty-input" data-line="' . $k . '" min="1" max="' . QT_MAX_QTY . '" step="1" value="' . (int) $line['quantity'] . '" aria-label="Quantity of ' . $name . '">'
			. '<button type="button" class="pms-qty-btn qt-sum-qty" data-line="' . $k . '" data-step="1" aria-label="One more ' . $name . '">+</button>'
			. '<span class="text-muted pms-num">&times; ' . qt_inr($line['price']) . '</span>'
			. '<button type="button" class="pms-remove qt-sum-remove" data-line="' . $k . '">Remove</button>'
			. '</div></div>';
	}
	$count = count($cart);
	$html = '<div class="pms-cart-head"><h2>This quotation</h2><span>' . $count . ($count === 1 ? ' line' : ' lines') . ' &middot; ' . ($type === 'DP' ? 'Dealer prices' : 'Retail prices') . '</span></div>';
	if ($count === 0) {
		$html .= '<p class="pms-empty">No products yet. Use <strong>Add</strong> or import the customer&#39;s list.</p>';
	} else {
		$html .= '<div class="pms-cart-lines">' . $lines . '</div>';
	}
	$html .= '<div class="pms-cart-foot">'
		. '<div class="pms-cart-total"><span>Total</span><span class="pms-num">' . qt_inr($total) . '</span></div>'
		. '<div class="text-muted" style="font-size:13px">Before discount and GST</div>'
		. ($count > 0 ? '<a class="btn btn-primary" href="' . qt_e($cfg['cartPage']) . '">Continue to customer details</a>' : '')
		. '</div>'
		. '<div class="pms-mobile-bar" aria-hidden="' . ($count > 0 ? 'false' : 'true') . '"' . ($count > 0 ? '' : ' style="display:none"') . '>'
		. '<div><span>' . $count . ($count === 1 ? ' line' : ' lines') . '</span><strong>' . qt_inr($total) . '</strong></div>'
		. '<a class="btn btn-primary" href="' . qt_e($cfg['cartPage']) . '">Review</a></div>';
	return $html;
}
