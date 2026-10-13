<?php
/**
 * State price lists: upload a state's Excel price list (same format as the standard
 * price list), preview how its codes match products, then save it.
 * Flow: upload -> preview (nothing saved) -> confirm -> saved. Requires createProduct
 * permission (the same as Price List) and the tables from cli/migrate_state_pricelists.php.
 */
include_once('DB/conn.php');
include_once('includes/quotation_lib.php');
include_once('includes/state_price_lib.php');
error_reporting(0);
@session_start();
qt_require_login_page();
$UserPermission = isset($_SESSION['permission']) ? @unserialize($_SESSION['permission'], array('allowed_classes' => false)) : array();
if (!is_array($UserPermission) || !in_array('createProduct', $UserPermission, true)) {
	qt_flash_set('You do not have permission to manage price lists.');
	qt_redirect('dashboard.php');
}

$ready = qt_state_tables_exist($connect);
$action = isset($_POST['action']) ? (string) $_POST['action'] : '';
$errors = array();
$preview = isset($_SESSION['qt_sp_preview']) ? $_SESSION['qt_sp_preview'] : null;

if ($action !== '' && !qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
	qt_flash_set('Your form expired. Please try again.');
	qt_redirect('StatePriceList.php');
}

if ($ready && $action === 'cancel') {
	unset($_SESSION['qt_sp_preview']);
	qt_redirect('StatePriceList.php');
}

if ($ready && $action === 'preview') {
	unset($_SESSION['qt_sp_preview']);
	$code = strtoupper(trim(isset($_POST['code']) ? (string) $_POST['code'] : ''));
	$name = trim(isset($_POST['name']) ? (string) $_POST['name'] : '');
	$file = isset($_FILES['file']) ? $_FILES['file'] : null;
	$ext = $file ? strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) : '';
	if (!preg_match('/^[A-Z0-9]{2,10}$/', $code)) {
		$errors[] = 'State code must be 2-10 letters or digits, for example MH or MP.';
	}
	if ($name === '' || strlen($name) > 100) {
		$errors[] = 'Enter the state name (up to 100 characters).';
	}
	if (!$file || $file['error'] !== UPLOAD_ERR_OK || !is_uploaded_file($file['tmp_name'])) {
		$errors[] = 'Choose the price list file to upload.';
	} elseif (!in_array($ext, array('xlsx', 'xls', 'csv'), true)) {
		$errors[] = 'Upload an .xlsx, .xls or .csv file.';
	} elseif ($file['size'] > 10485760) {
		$errors[] = 'The file is larger than 10 MB.';
	}
	if (!$errors) {
		try {
			$result = qt_sp_read($connect, $file['tmp_name'], $file['name']);
		} catch (Exception $e) {
			$result = null;
			$errors[] = 'The file could not be read: ' . $e->getMessage();
		}
		@unlink($file['tmp_name']);
		if ($result) {
			$knownStates = qt_price_states($connect);
			$preview = array('code' => $code, 'name' => $name, 'file' => basename($file['name']), 'prices' => $result['prices'],
				'issues' => $result['issues'], 'stats' => $result['stats'],
				'existing' => isset($knownStates[$code]));
			$_SESSION['qt_sp_preview'] = $preview;
			qt_redirect('StatePriceList.php');
		}
	}
}

if ($ready && $action === 'save' && $preview) {
	if (count($preview['prices']) === 0) {
		qt_flash_set('Nothing to save: no rows matched a product.');
	} else {
		$error = qt_sp_save($connect, $preview['code'], $preview['name'], $preview['prices'], $preview['file'], $_SESSION['username']);
		if ($error === null) {
			qt_flash_set('Saved the ' . $preview['name'] . ' (' . $preview['code'] . ') price list: ' . count($preview['prices']) . ' products.', 'success');
			unset($_SESSION['qt_sp_preview']);
		} else {
			qt_flash_set($error);
		}
	}
	qt_redirect('StatePriceList.php');
}

$states = array();
if ($ready) {
	$r = mysqli_query($connect, 'SELECT s.code, s.name, s.active, s.source_file, s.updated_by, s.updated_at, COUNT(p.id) AS products
		FROM price_states s LEFT JOIN state_pricelist p ON p.state_code = s.code GROUP BY s.code ORDER BY s.name');
	while ($r && $row = mysqli_fetch_assoc($r)) {
		$states[] = $row;
	}
}

include_once('templates/header.php');
include_once('templates/header_menu.php');
include_once('templates/side_menubar.php');
?>
<div class="content-wrapper">
	<section class="content-header">
		<h1>State Price Lists <small>prices per state for quotations</small></h1>
	</section>
	<section class="content" style="max-width:1100px">
		<?php echo qt_flash_render(); ?>
		<?php foreach ($errors as $e): ?>
			<div class="alert alert-danger"><?= qt_e($e) ?></div>
		<?php endforeach; ?>

		<?php if (!$ready): ?>
			<div class="alert alert-warning">State price lists are not set up in this database yet. An administrator must run
				<code>php cli/migrate_state_pricelists.php --database=&lt;name&gt;</code> first.</div>
		<?php else: ?>

		<div class="box box-default">
			<div class="box-header with-border"><h3 class="box-title">Loaded price lists</h3></div>
			<div class="box-body">
				<?php if (!$states): ?>
					<p class="text-muted">None yet. Quotations use the standard price list.</p>
				<?php else: ?>
					<table class="table table-bordered">
						<tr><th>Code</th><th>State</th><th>Products</th><th>File</th><th>Updated</th></tr>
						<?php foreach ($states as $s): ?>
							<tr><td><?= qt_e($s['code']) ?></td><td><?= qt_e($s['name']) ?></td><td><?= (int) $s['products'] ?></td>
								<td><?= qt_e($s['source_file']) ?></td><td><?= qt_e($s['updated_at']) ?> by <?= qt_e($s['updated_by']) ?></td></tr>
						<?php endforeach; ?>
					</table>
				<?php endif; ?>
			</div>
		</div>

		<?php if (!$preview): ?>
		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Upload a state price list</h3></div>
			<div class="box-body">
				<p class="text-muted">Use the same Excel format as the standard price list: a header row with <b>Code</b> (or Item Code),
					<b>MRP Price</b>, <b>Dealer Price</b> (or DP Price) and optionally <b>Special Price</b>; all sheets are read.
					Each Code must equal a product name (case and spaces are ignored). Uploading a state again replaces its list.</p>
				<form method="post" enctype="multipart/form-data">
					<input type="hidden" name="action" value="preview">
					<?php echo qt_csrf_field(); ?>
					<div class="row">
						<div class="form-group col-md-2"><label>State code</label><input class="form-control" name="code" maxlength="10" placeholder="MH" required></div>
						<div class="form-group col-md-4"><label>State name</label><input class="form-control" name="name" maxlength="100" placeholder="Maharashtra" required></div>
						<div class="form-group col-md-6"><label>Price list file (.xlsx, .xls, .csv)</label><input type="file" name="file" accept=".xlsx,.xls,.csv" required></div>
					</div>
					<button type="submit" class="btn btn-primary"><i class="fa fa-eye"></i> Preview</button>
				</form>
			</div>
		</div>
		<?php else: $st = $preview['stats']; ?>
		<div class="box box-warning">
			<div class="box-header with-border"><h3 class="box-title">Preview: <?= qt_e($preview['name']) ?> (<?= qt_e($preview['code']) ?>) from <?= qt_e($preview['file']) ?></h3></div>
			<div class="box-body">
				<?php if ($preview['existing']): ?><div class="alert alert-info">This replaces the current <?= qt_e($preview['code']) ?> price list.</div><?php endif; ?>
				<table class="table table-bordered" style="max-width:600px">
					<tr><td>Rows read (<?= (int) $st['sheets'] ?> sheet(s))</td><td><?= (int) $st['rows_read'] ?></td></tr>
					<tr class="success"><td>Matched to a product (will be saved)</td><td><b><?= (int) $st['matched'] ?></b></td></tr>
					<tr><td>Code not found as a product name</td><td><?= (int) $st['not_found'] ?></td></tr>
					<tr><td>Code shared by several products (not loaded)</td><td><?= (int) $st['ambiguous'] ?></td></tr>
					<tr><td>Missing or invalid price</td><td><?= (int) $st['invalid_price'] ?></td></tr>
					<tr><td>Listed twice (first kept)</td><td><?= (int) $st['duplicate'] ?></td></tr>
					<tr><td>Sheets without a recognised header</td><td><?= (int) $st['sheets_without_header'] ?></td></tr>
				</table>
				<p class="text-muted">Products not in this list keep the standard price in quotations, with a warning on the line.</p>
				<?php if ($preview['issues']): ?>
					<h4>Rows not loaded (<?= count($preview['issues']) ?>)</h4>
					<div style="max-height:320px; overflow:auto">
						<table class="table table-condensed table-striped">
							<tr><th>Problem</th><th>Code</th><th>Where</th><th>Detail</th></tr>
							<?php foreach ($preview['issues'] as $i): ?>
								<tr><td><?= qt_e(str_replace('_', ' ', $i[0])) ?></td><td><?= qt_e($i[1]) ?></td><td><?= qt_e($i[2]) ?></td><td><?= qt_e($i[3]) ?></td></tr>
							<?php endforeach; ?>
						</table>
					</div>
				<?php endif; ?>
			</div>
			<div class="box-footer">
				<form method="post" style="display:inline-block">
					<input type="hidden" name="action" value="save"><?php echo qt_csrf_field(); ?>
					<button type="submit" class="btn btn-success" <?= $st['matched'] ? '' : 'disabled' ?>><i class="fa fa-check"></i> Save <?= (int) $st['matched'] ?> prices</button>
				</form>
				<form method="post" style="display:inline-block">
					<input type="hidden" name="action" value="cancel"><input type="hidden" name="csrf_token" value="<?= qt_e(qt_csrf_token()) ?>">
					<button type="submit" class="btn btn-default"><i class="fa fa-times"></i> Cancel</button>
				</form>
			</div>
		</div>
		<?php endif; ?>
		<?php endif; ?>
	</section>
</div>
<?php include_once('templates/footer.php'); ?>
