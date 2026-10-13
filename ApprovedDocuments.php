<?php
/**
 * Approved company documents (PI terms, warranty, delivery, payment, installation policies).
 * Only documents an administrator approves here are used by the AI Assistant and printed on PIs.
 * Each version is kept; approving a new version retires the previous one. Needs createProduct.
 */
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
		include_once('includes/ai_provider.php');
		include_once('includes/ai_import_lib.php');
		include_once('includes/retrieval_lib.php');
		include_once('includes/pi_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();
		$UserPermission = unserialize($_SESSION['permission']);
		if (!is_array($UserPermission) || !in_array('createProduct', $UserPermission, true)) {
			qt_flash_set('Only administrators can manage approved documents.');
			qt_redirect('dashboard.php');
		}
		list($ai) = qt_ai_settings();
		$ready = ($t = mysqli_query($connect, "SHOW TABLES LIKE 'approved_documents'")) && mysqli_num_rows($t) > 0;
		$types = array('pi_terms' => 'PI terms and conditions', 'warranty' => 'Warranty policy', 'delivery' => 'Delivery policy',
			'payment' => 'Payment terms', 'installation' => 'Installation policy', 'other' => 'Other policy');

		if ($ready && $_SERVER['REQUEST_METHOD'] === 'POST') {
			if (!qt_csrf_valid(isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '')) {
				qt_flash_set('Your page has expired. Please try again.');
				qt_redirect('ApprovedDocuments.php');
			}
			$act = isset($_POST['act']) ? (string) $_POST['act'] : '';
			$id = qt_parse_id(isset($_POST['id']) ? $_POST['id'] : '');
			if ($act === 'add') {
				$title = trim((string) (isset($_POST['title']) ? $_POST['title'] : ''));
				$type = isset($_POST['doc_type']) ? (string) $_POST['doc_type'] : '';
				$from = isset($_POST['effective_from']) ? (string) $_POST['effective_from'] : '';
				$to = isset($_POST['effective_to']) ? trim((string) $_POST['effective_to']) : '';
				$statesIn = strtoupper(preg_replace('/\s+/', '', isset($_POST['states']) ? (string) $_POST['states'] : 'ALL'));
				$content = trim(str_replace("\r\n", "\n", isset($_POST['content']) ? (string) $_POST['content'] : ''));
				$source = trim((string) (isset($_POST['source_location']) ? $_POST['source_location'] : ''));
				$key = isset($_POST['doc_key']) && preg_match('/^[a-z0-9_-]{2,60}$/', $_POST['doc_key']) ? $_POST['doc_key']
					: trim(preg_replace('/[^a-z0-9]+/', '_', strtolower($title)), '_');
				$okStates = $statesIn === 'ALL' || preg_match('/^(\d{2})(,\d{2})*$/', $statesIn);
				$valid = pi_states();
				foreach ($statesIn === 'ALL' ? array() : explode(',', $statesIn) as $sc) {
					if (!isset($valid[$sc])) { $okStates = false; }
				}
				if ($title === '' || !isset($types[$type]) || !preg_match('/^\d{4}-\d{2}-\d{2}$/', $from) || ($to !== '' && (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $to) || $to < $from))
					|| $content === '' || !$okStates || $key === '' || $source === '' || qt_ai_strlen($content) > 50000) {
					qt_flash_set('Fill in title, type, effective dates (to after from), states (ALL or GST codes like 27,24), source location and the text (max 50,000 characters).');
				} else {
					$v = mysqli_fetch_row(mysqli_query($connect, "SELECT COALESCE(MAX(version), 0) + 1 FROM approved_documents WHERE doc_key = '" . pi_esc($connect, $key) . "'"))[0];
					mysqli_query($connect, "INSERT INTO approved_documents (doc_key, title, doc_type, version, status, effective_from, effective_to, states, source_location, content, created_by, created_at)
						VALUES ('" . pi_esc($connect, $key) . "', '" . pi_esc($connect, qt_ai_cut($title, 200)) . "', '" . pi_esc($connect, $type) . "', " . (int) $v . ", 'draft', '"
						. pi_esc($connect, $from) . "', " . ($to === '' ? 'NULL' : "'" . pi_esc($connect, $to) . "'") . ", '" . pi_esc($connect, $statesIn) . "', '"
						. pi_esc($connect, qt_ai_cut($source, 255)) . "', '" . pi_esc($connect, $content) . "', '" . pi_esc($connect, $_SESSION['username']) . "', NOW())");
					qt_flash_set('Saved as draft version ' . (int) $v . '. Review it and press Approve to use it.', 'success');
				}
			} elseif ($act === 'approve' && $id !== null) {
				$err = rt_doc_approve($ai, $connect, $id, $_SESSION['username']);
				qt_flash_set($err !== null ? $err : 'Approved. The AI Assistant and new PIs use it from now on.', $err !== null ? 'danger' : 'success');
			} elseif ($act === 'retire' && $id !== null) {
				mysqli_query($connect, "UPDATE approved_documents SET status = 'retired' WHERE id = " . (int) $id);
				qt_flash_set('Retired: no longer used.', 'success');
			}
			qt_redirect('ApprovedDocuments.php');
		}
		$docs = array();
		if ($ready) {
			$r = mysqli_query($connect, "SELECT d.*, (SELECT COUNT(*) FROM document_chunks c WHERE c.document_id = d.id) AS chunks FROM approved_documents d ORDER BY doc_key, version DESC");
			while ($r && ($row = mysqli_fetch_assoc($r))) { $docs[] = $row; }
		}

		include_once('templates/header.php');
		include_once('templates/header_menu.php');
		include_once('templates/side_menubar.php');
?>
  <div class="content-wrapper">
    <section class="content-header">
      <h1>Approved documents <small class="pms-sub">Only approved versions are used by the AI Assistant and printed on PIs. Filtering by type, dates and state happens before any search.</small></h1>
    </section>
    <section class="content">
      <?php echo qt_flash_render(); ?>
      <?php if (!$ready): ?>
        <div class="alert alert-warning">Not set up on this database yet: run <code>php cli/migrate_pi.php --database=...</code>.</div>
      <?php else: ?>
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Documents</h3></div>
        <div class="box-body pms-scroll">
          <?php if (!count($docs)): ?><p class="pms-empty">No documents yet. PIs print the terms from the current quotation template until an approved "PI terms" document applies.</p><?php else: ?>
          <table class="table">
            <thead><tr><th>Title</th><th>Type</th><th>Version</th><th>Status</th><th>Effective</th><th>States</th><th>Source</th><th>Chunks</th><th></th></tr></thead>
            <tbody>
            <?php foreach ($docs as $d): ?>
              <tr>
                <td><strong><?= qt_e($d['title']) ?></strong><div class="text-muted" style="font-size:12px"><?= qt_e($d['doc_key']) ?> · by <?= qt_e($d['created_by']) ?><?= $d['approved_by'] ? ' · approved by ' . qt_e($d['approved_by']) . ' ' . qt_e($d['approved_at']) : '' ?></div>
                  <details><summary class="text-muted" style="font-size:12px">Text</summary><div style="white-space:pre-wrap;font-size:13px"><?= qt_e($d['content']) ?></div></details></td>
                <td><?= qt_e(isset($types[$d['doc_type']]) ? $types[$d['doc_type']] : $d['doc_type']) ?></td>
                <td>v<?= (int) $d['version'] ?></td>
                <td><span class="pms-badge <?= $d['status'] === 'approved' ? 'pms-badge-dp' : ($d['status'] === 'draft' ? 'pms-badge-warn' : 'pms-badge-muted') ?>"><?= qt_e($d['status']) ?></span></td>
                <td><?= qt_e($d['effective_from']) ?><?= $d['effective_to'] ? ' to ' . qt_e($d['effective_to']) : ' onwards' ?></td>
                <td><?= qt_e($d['states']) ?></td>
                <td><?= qt_e($d['source_location']) ?></td>
                <td><?= (int) $d['chunks'] ?></td>
                <td>
                  <?php if ($d['status'] !== 'retired'): ?>
                  <form method="post" style="display:inline">
                    <?php echo qt_csrf_field(); ?><input type="hidden" name="id" value="<?= (int) $d['id'] ?>">
                    <?php if ($d['status'] === 'draft'): ?>
                      <button class="btn btn-primary btn-sm" name="act" value="approve" onclick="return confirm('Approve this document? It will be used on PIs and by the assistant.')">Approve</button>
                    <?php endif; ?>
                    <button class="btn btn-default btn-sm" name="act" value="retire">Retire</button>
                  </form>
                  <?php endif; ?>
                </td>
              </tr>
            <?php endforeach; ?>
            </tbody>
          </table>
          <?php endif; ?>
        </div>
      </div>
      <div class="box">
        <div class="box-header with-border"><h3 class="box-title">Add a document or a new version</h3></div>
        <div class="box-body">
          <form method="post">
            <?php echo qt_csrf_field(); ?><input type="hidden" name="act" value="add">
            <div class="row">
              <div class="form-group col-md-4"><label for="d-title">Title</label><input id="d-title" class="form-control" name="title" maxlength="200" required></div>
              <div class="form-group col-md-3"><label for="d-type">Type</label><select id="d-type" class="form-control" name="doc_type"><?php foreach ($types as $k => $l): ?><option value="<?= qt_e($k) ?>"><?= qt_e($l) ?></option><?php endforeach; ?></select></div>
              <div class="form-group col-md-2"><label for="d-key">Document key (new version of)</label><input id="d-key" class="form-control" name="doc_key" placeholder="auto from title" pattern="[a-z0-9_-]{2,60}"></div>
              <div class="form-group col-md-3"><label for="d-src">Source location</label><input id="d-src" class="form-control" name="source_location" maxlength="255" placeholder="e.g. Office file F-12, approved 01-04-2026" required></div>
            </div>
            <div class="row">
              <div class="form-group col-md-3"><label for="d-from">Effective from</label><input id="d-from" type="date" class="form-control" name="effective_from" required></div>
              <div class="form-group col-md-3"><label for="d-to">Effective to (optional)</label><input id="d-to" type="date" class="form-control" name="effective_to"></div>
              <div class="form-group col-md-6"><label for="d-states">States: ALL, or GST state codes (27 Maharashtra, 24 Gujarat, …)</label><input id="d-states" class="form-control" name="states" value="ALL"></div>
            </div>
            <div class="form-group"><label for="d-content">Text (one term per line for PI terms)</label><textarea id="d-content" class="form-control" name="content" rows="8" required></textarea></div>
            <button class="btn btn-primary">Save as draft</button>
          </form>
        </div>
      </div>
      <?php endif; ?>
    </section>
  </div>
<?php include_once('templates/footer.php'); ?>
