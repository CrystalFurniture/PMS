<?php
/**
 * AI assistant settings for the person in charge: standing instructions applied to
 * every customer-requirement import, plus a read-only view of the AI mode and spend.
 * Requires the createProduct permission (the admin group that manages products and price lists).
 */
include_once('DB/conn.php');
include_once('includes/quotation_lib.php');
include_once('includes/ai_provider.php');
include_once('includes/ai_import_lib.php');
include_once('includes/retrieval_lib.php');
error_reporting(0);
@session_start();
qt_require_login_page();
$UserPermission = isset($_SESSION['permission']) ? @unserialize($_SESSION['permission'], array('allowed_classes' => false)) : array();
if (!is_array($UserPermission) || !in_array('createProduct', $UserPermission, true)) {
	qt_flash_set('You do not have permission to change the AI assistant settings.');
	qt_redirect('dashboard.php');
}

list($ai, $aiError) = qt_ai_settings();
$storageReady = $ai['storage_dir'] !== '' && is_dir($ai['storage_dir'] . '/drafts');
$error = null;
// Product index update (semantic search). Bounded per click; the CLI handles a full rebuild.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && $_POST['act'] === 'index') {
	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		qt_flash_set('Your page has expired. Please try again.');
	} else {
		@set_time_limit(600);
		list($stats, $err) = rt_index_sync($ai, $connect, 5000);
		qt_flash_set($err !== null ? 'Index update stopped: ' . $err : 'Index updated: ' . $stats['indexed'] . ' indexed, ' . $stats['deleted'] . ' removed.', $err !== null ? 'danger' : 'success');
	}
	qt_redirect('AiSettings.php');
}
// Series names (CS-157 = Gothic): added or removed by hand; price-list imports add the rest.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['act']) && ($_POST['act'] === 'series_add' || $_POST['act'] === 'series_delete')) {
	$prefix = strtoupper(preg_replace('/\s+/', '', isset($_POST['prefix']) ? (string) $_POST['prefix'] : ''));
	$sname = trim(preg_replace('/\s+/', ' ', isset($_POST['series_name']) ? (string) $_POST['series_name'] : ''));
	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		qt_flash_set('Your page has expired. Please try again.');
	} elseif (!preg_match('/^[A-Z0-9][A-Z0-9().\/-]{0,38}\*?$/', $prefix)) {
		qt_flash_set('Code prefix: letters, digits and - ( ) . / only, for example CS-173 (or BSQ* for every code starting with BSQ).');
	} elseif ($_POST['act'] === 'series_delete') {
		mysqli_query($connect, "DELETE FROM product_series WHERE prefix = '" . mysqli_real_escape_string($connect, $prefix) . "'");
		qt_flash_set('Series ' . $prefix . ' removed.', 'success');
	} elseif (!preg_match('/^[\p{L}\p{N}][\p{L}\p{N} .&\'-]{0,79}$/u', $sname)) {
		qt_flash_set('Series name: up to 80 letters, digits and spaces, for example Arvella.');
	} else {
		mysqli_query($connect, "INSERT INTO product_series (prefix, name, source, updated_by, updated_at) VALUES ('" . mysqli_real_escape_string($connect, $prefix) . "', '"
			. mysqli_real_escape_string($connect, $sname) . "', 'manual', '" . mysqli_real_escape_string($connect, $_SESSION['username']) . "', NOW())
			ON DUPLICATE KEY UPDATE name = VALUES(name), source = 'manual', updated_by = VALUES(updated_by), updated_at = NOW()");
		qt_flash_set('Series ' . $prefix . ' = ' . $sname . ' saved. The AI Assistant finds these products by that name from now on.', 'success');
	}
	qt_redirect('AiSettings.php#series');
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
	$text = trim(str_replace("\r\n", "\n", isset($_POST['standing']) ? (string) $_POST['standing'] : ''));
	if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
		$error = 'Your form expired. Please try again.';
	} elseif (!$storageReady) {
		$error = 'The AI assistant storage folder is not set up.';
	} elseif (!preg_match('//u', $text) || qt_ai_strlen($text) > 2000) {
		$error = 'Standing instructions must be plain text of up to 2000 characters.';
	} elseif (!qt_ai_standing_set($ai, $text, $_SESSION['username'])) {
		$error = 'The instructions could not be saved.';
	} else {
		qt_ai_log($ai, 'standing_instructions_saved', array('user' => (string) $_SESSION['user_id'], 'chars' => qt_ai_strlen($text)));
		qt_flash_set('Standing instructions saved. They apply to imports started from now on.', 'success');
		qt_redirect('AiSettings.php');
	}
}
$standing = $storageReady ? qt_ai_standing_get($ai) : array('text' => '', 'updated_by' => '', 'updated_at' => '');

include_once('templates/header.php');
include_once('templates/header_menu.php');
include_once('templates/side_menubar.php');
?>
<div class="content-wrapper">
	<section class="content-header"><h1>AI Assistant <small>settings</small></h1></section>
	<section class="content" style="max-width:1000px">
		<?php echo qt_flash_render(); ?>
		<?php if ($error !== null): ?><div class="alert alert-danger"><?= qt_e($error) ?></div><?php endif; ?>

		<div class="box box-primary">
			<div class="box-header with-border"><h3 class="box-title">Standing instructions</h3></div>
			<div class="box-body">
				<p class="text-muted">Applied to every customer-requirement import, together with any instructions typed for that import.
					Use them for house rules, for example <i>"All beds are king size unless stated"</i> or <i>"Wardrobe finish is Wenge unless stated"</i>.
					The customer's own wording always takes priority, and each row on the review screen shows which instruction was applied.
					They are sent to the AI provider with each import.</p>
				<?php if (!$storageReady): ?>
					<div class="alert alert-warning"><?= qt_e($aiError !== null ? $aiError : 'The AI assistant is not set up.') ?></div>
				<?php else: ?>
				<form method="post">
					<?php echo qt_csrf_field(); ?>
					<textarea name="standing" class="form-control" rows="6" maxlength="2000"><?= qt_e($standing['text']) ?></textarea>
					<p class="text-muted small" style="margin-top:6px">
						<?= $standing['updated_at'] !== '' ? 'Last saved ' . qt_e($standing['updated_at']) . ' by ' . qt_e($standing['updated_by']) . '.' : 'Not saved yet.' ?>
						Up to 2000 characters. Imports already started keep the instructions they were started with.</p>
					<button type="submit" class="btn btn-primary">Save standing instructions</button>
				</form>
				<?php endif; ?>
			</div>
		</div>

		<div class="box box-default">
			<div class="box-header with-border"><h3 class="box-title">Status</h3></div>
			<div class="box-body">
				<table class="table table-condensed" style="max-width:600px">
					<tr><td>AI assistant</td><td><?= $aiError === null ? '<span class="label ' . (qt_ai_mode($ai) === 'live' ? 'label-success' : 'label-warning') . '">' . qt_e(qt_ai_mode_label($ai)) . '</span>' : qt_e($aiError) ?></td></tr>
					<tr><td>Refusal fallback</td><td><?= $ai['refusal_fallback'] === '' ? 'Off (answers come from the configured model only)' : 'On' ?></td></tr>
					<?php if ($storageReady && (float) $ai['budget_usd'] > 0): ?>
						<tr><td>Estimated spend</td><td>$<?= number_format(qt_ai_spent_usd($ai), 2) ?> of $<?= number_format((float) $ai['budget_usd'], 2) ?>
							<?= qt_ai_budget_error($ai) !== null ? '<span class="text-danger">(new imports blocked)</span>' : '' ?></td></tr>
					<?php endif; ?>
				</table>
				<p class="text-muted small">Model, key and budget are set in the private configuration file on the server.</p>
			</div>
		</div>

		<?php
			$seriesRows = array();
			$hasSeries = ($t = mysqli_query($connect, "SHOW TABLES LIKE 'product_series'")) && mysqli_num_rows($t) > 0;
			if ($hasSeries) {
				$r = mysqli_query($connect, "SELECT prefix, name, source FROM product_series ORDER BY name");
				while ($r && ($x = mysqli_fetch_assoc($r))) { $seriesRows[] = $x; }
			}
		?>
		<div class="box box-default" id="series">
			<div class="box-header with-border"><h3 class="box-title">Series names</h3></div>
			<div class="box-body">
				<?php if (!$hasSeries): ?>
					<p class="text-muted">Not set up on this database: run <code>php cli/migrate_pi.php</code>.</p>
				<?php else: ?>
				<p class="text-muted">Customers write series names ("Gothic king bed") while the catalogue uses codes (CS-157 (157.6 NHDS)). Each name here is added to every product whose code starts with the prefix. Price-list imports fill this list from their series headings; add the series they do not have.</p>
				<form method="post" class="form-inline" style="margin-bottom:12px">
					<?php echo qt_csrf_field(); ?><input type="hidden" name="act" value="series_add">
					<label class="sr-only" for="series-prefix">Code prefix</label>
					<input class="form-control" id="series-prefix" name="prefix" placeholder="Code prefix, e.g. CS-173" required maxlength="40">
					<label class="sr-only" for="series-name">Series name</label>
					<input class="form-control" id="series-name" name="series_name" placeholder="Series name, e.g. Arvella" required maxlength="80">
					<button class="btn btn-primary">Save</button>
				</form>
				<table class="table table-condensed" style="max-width:700px">
					<thead><tr><th>Code prefix</th><th>Series</th><th>From</th><th></th></tr></thead>
					<tbody>
					<?php foreach ($seriesRows as $x): ?>
						<tr><td><?= qt_e($x['prefix']) ?></td><td><?= qt_e($x['name']) ?></td><td class="text-muted"><?= $x['source'] === 'manual' ? 'added by hand' : qt_e($x['source']) ?></td>
							<td><form method="post" style="margin:0"><?php echo qt_csrf_field(); ?><input type="hidden" name="act" value="series_delete"><input type="hidden" name="prefix" value="<?= qt_e($x['prefix']) ?>">
								<button class="btn btn-link btn-xs" aria-label="Remove series <?= qt_e($x['prefix']) ?>">Remove</button></form></td></tr>
					<?php endforeach; ?>
					<?php if (!$seriesRows): ?><tr><td colspan="4" class="text-muted">None yet.</td></tr><?php endif; ?>
					</tbody>
				</table>
				<?php endif; ?>
			</div>
		</div>

		<?php $rt = ($t = mysqli_query($connect, "SHOW TABLES LIKE 'product_index'")) && mysqli_num_rows($t) > 0 ? rt_index_status($ai, $connect) : null; ?>
		<div class="box box-default" id="product-index">
			<div class="box-header with-border"><h3 class="box-title">Product search index (semantic search)</h3></div>
			<div class="box-body">
				<?php if ($rt === null): ?>
					<p class="text-muted">Not set up on this database: run <code>php cli/migrate_pi.php</code>.</p>
				<?php else: ?>
				<table class="table table-condensed" style="max-width:600px">
					<tr><td>Semantic search</td><td><?= $rt['enabled'] ? 'On (' . qt_e($rt['model']) . ')' : 'Off: no embeddings key (Voyage AI) in the configuration. Keyword and exact-code search are used.' ?></td></tr>
					<tr><td>Quotable products</td><td><?= (int) $rt['products'] ?></td></tr>
					<tr><td>Up to date in the index</td><td><?= (int) $rt['fresh'] ?></td></tr>
					<tr><td>Changed since indexing</td><td><?= (int) $rt['stale'] ?><?= $rt['stale'] ? ' (left out of semantic search until updated)' : '' ?></td></tr>
					<tr><td>Not indexed / other model</td><td><?= (int) $rt['missing'] ?> / <?= (int) $rt['other_model'] ?></td></tr>
					<tr><td>Last update</td><td><?= $rt['synced_at'] ? qt_e($rt['synced_at']) : 'never' ?></td></tr>
				</table>
				<?php if ($rt['enabled']): ?>
				<form method="post"><?php echo qt_csrf_field(); ?><input type="hidden" name="act" value="index">
					<button class="btn btn-default">Update index now</button>
					<span class="text-muted small">Changed products are also updated automatically (up to <?= (int) $ai['embed_auto_index'] ?> at a time) before each search.</span>
				</form>
				<?php endif; ?>
				<?php endif; ?>
			</div>
		</div>
	</section>
</div>
<?php include_once('templates/footer.php'); ?>
