<?php
/**
 * Review an imported customer requirement before anything reaches the cart.
 * Original text/images on the left; editable rows with catalogue matches on the right.
 */
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
		include_once('includes/ai_provider.php');
		include_once('includes/ai_import_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();
		$userName = $_SESSION['username'];

		list($ai, $aiError) = qt_ai_settings();
		$draft = null;
		if ($ai['storage_dir'] !== '' && is_dir($ai['storage_dir'] . '/drafts')) {
			list($draft, $error) = qt_ai_load_draft($ai, isset($_GET['draft']) ? (string) $_GET['draft'] : '');
		}
		if (!$draft) {
			qt_flash_set('That customer requirement import was not found. It may have expired or belong to another session.');
			qt_redirect('CreatedOrderDp.php');
		}
		$qtType = $draft['type'];
		$qtCfg = qt_config($qtType);

		$stale = (int) $ai['total_deadline'] * 2 + 120;
		$status = $draft['status'];
		if ($status === 'processing' && time() - (int) $draft['processing_started'] >= $stale) {
			$status = 'interrupted';
		}
		$rows = array();
		foreach ($draft['rows'] as $row) {
			$rows[] = qt_ai_row_view($connect, $draft, $row);
		}
		$state = array(
			'draft' => $draft['id'],
			'type' => $qtType,
			'status' => $status,
			'error' => $draft['error'] ? $draft['error']['message'] : '',
			'context_error' => $status === 'ready' ? qt_ai_context_error($draft) : null,
			'document_notes' => $draft['document_notes'],
			'rows' => $rows,
			'max_qty' => QT_MAX_QTY,
			'create_page' => $qtCfg['create'],
			'cart_page' => $qtCfg['cartPage'],
		);

		include_once('templates/header.php');
		include_once('templates/header_menu.php');
		include_once('templates/side_menubar.php');
?>
<style>
.ai-source-text { white-space: pre-wrap; background: #f9f9f9; border: 1px solid #ddd; padding: 8px; max-height: 420px; overflow: auto; }
.ai-source-img { display: block; max-width: 100%; max-height: 320px; width: auto; border: 1px solid #ddd; border-radius: 8px; }
.ai-req > summary { list-style: none; cursor: pointer; display: flex; flex-wrap: wrap; align-items: center; gap: 6px 14px; padding: 12px 18px; }
.ai-req > summary::-webkit-details-marker { display: none; }
.ai-req > summary:hover { background: #F8FAFB; border-radius: 12px; }
.ai-req-title { font-weight: 600; font-size: 16px; }
.ai-req-price { margin-left: auto; }
.ai-req-toggle::after { content: "Show"; font-weight: 600; color: #0B6E6E; }
.ai-req[open] .ai-req-toggle::after { content: "Hide"; }
.ai-req[open] > summary { border-bottom: 1px solid #E2E5EA; }
.ai-req-imgs { display: flex; flex-wrap: wrap; gap: 12px; }
.ai-req-imgs figure { margin: 0; }
.ai-req-imgs figcaption { font-size: 12px; color: #5A6573; margin-top: 4px; }
.ai-review-table { table-layout: fixed; }
.ai-review-table td { vertical-align: top !important; font-size: 13px; word-wrap: break-word; }
.ai-review-table th:nth-child(1) { width: 27%; } .ai-review-table th:nth-child(2) { width: 9%; } .ai-review-table th:nth-child(3) { width: 36%; } .ai-review-table th:nth-child(4) { width: 17%; } .ai-review-table th:nth-child(5) { width: 11%; }
.ai-review-table .ai-original { color: #666; font-size: 12px; }
.ai-thumb { width: 64px; height: 48px; object-fit: contain; border: 1px solid #eee; margin-right: 6px; float: left; }
.ai-warn { color: #8a6d3b; font-size: 12px; margin: 2px 0; }
.ai-status { display: inline-block; padding: 1px 6px; border-radius: 3px; font-size: 11px; color: #fff; }
.ai-status-suggested, .ai-status-chosen { background: #00a65a; } .ai-status-needs_review { background: #f39c12; } .ai-status-unmatched { background: #dd4b39; }
.ai-row-removed { opacity: .45; }
.ai-choice { cursor: pointer; padding: 3px; border: 1px solid transparent; }
.ai-choice:hover { border-color: #3c8dbc; }
.ai-search-results { max-height: 220px; overflow: auto; border: 1px solid #ddd; margin-top: 4px; }
.ai-plan-item { border-bottom: 1px solid #eee; padding: 6px 0; }
</style>

<div class="content-wrapper">
	<section class="content-header">
		<h1>Review customer requirement <small><?= qt_e($qtType) ?> quotation</small>
			<span class="label <?= qt_ai_mode($ai) === 'live' ? 'label-success' : 'label-warning' ?>" id="ai-mode" style="font-size:12px; vertical-align:middle"><?= qt_e(qt_ai_mode_label($ai)) ?></span></h1>
		<ol class="breadcrumb">
			<li><a href="<?= qt_e($qtCfg['create']) ?>"><i class="fa fa-dashboard"></i> Create <?= qt_e($qtType) ?> Quotation</a></li>
			<li class="active">Review import</li>
		</ol>
	</section>

	<section class="content">
		<?php echo qt_csrf_field(); ?>
		<?php echo qt_flash_render(); ?>
		<?php include('templates/quotation_state.php'); ?>
		<div id="ai-messages"></div>

		<?php
			$qtInstr = isset($draft['instructions']) ? $draft['instructions'] : array('standing' => '', 'import' => '');
			$qtImgCount = count($draft['input']['images']);
			$qtReqParts = array();
			if ($qtImgCount > 0) { $qtReqParts[] = $qtImgCount . ($qtImgCount === 1 ? ' image' : ' images'); }
			if ($draft['input']['text'] !== '') { $qtReqParts[] = 'pasted text'; }
			if (!empty($qtInstr['import']) || !empty($qtInstr['standing'])) { $qtReqParts[] = 'staff instructions'; }
		?>
		<!-- Customer requirement: collapsed bar so the review table gets the full width; open it to check the source. -->
		<details class="box ai-req" id="ai-requirement">
			<summary class="ai-req-summary">
				<span class="ai-req-title">Customer requirement</span>
				<span class="text-muted"><?= qt_e(implode(' · ', $qtReqParts)) ?></span>
				<span class="ai-req-price" id="ai-price-list"><strong>Price list:</strong> <?= qt_e(qt_price_state_name($connect, qt_ai_draft_state($draft))) ?></span>
				<span class="ai-req-toggle" aria-hidden="true"></span>
			</summary>
			<div class="box-body">
				<?php if ($draft['input']['text'] !== ''): ?>
					<div class="ai-source-text"><?= qt_e($draft['input']['text']) ?></div>
				<?php endif; ?>
				<?php if ($qtImgCount > 0): ?>
				<div class="ai-req-imgs">
				<?php foreach ($draft['input']['images'] as $i => $img): ?>
					<figure>
						<?php if (empty($img['deleted'])): ?>
							<a href="ai_import.php?action=image&amp;draft=<?= qt_e($draft['id']) ?>&amp;n=<?= $i + 1 ?>" target="_blank" rel="noopener" title="Open full size">
								<img class="ai-source-img" src="ai_import.php?action=image&amp;draft=<?= qt_e($draft['id']) ?>&amp;n=<?= $i + 1 ?>" alt="Customer requirement image <?= $i + 1 ?>">
							</a>
						<?php else: ?>
							<p class="text-muted">Image deleted after the items were added.</p>
						<?php endif; ?>
						<figcaption>Image <?= $i + 1 ?><?= empty($img['deleted']) ? ' &middot; click to open full size' : '' ?></figcaption>
					</figure>
				<?php endforeach; ?>
				</div>
				<?php endif; ?>
				<?php if (!empty($qtInstr['import']) || !empty($qtInstr['standing'])): ?>
					<p style="margin:10px 0 2px"><strong>Staff instructions used</strong></p>
					<?php if (!empty($qtInstr['import'])): ?><div class="ai-source-text" id="ai-instr-import"><?= qt_e($qtInstr['import']) ?></div><?php endif; ?>
					<?php if (!empty($qtInstr['standing'])): ?><div class="ai-source-text text-muted" id="ai-instr-standing" style="margin-top:4px">Standing: <?= qt_e($qtInstr['standing']) ?></div><?php endif; ?>
				<?php endif; ?>
			</div>
		</details>

		<div class="box">
			<div class="box-header pms-flex-head">
				<h3 class="box-title">Extracted items</h3>
				<div>
					<a class="btn btn-default btn-sm" href="<?= qt_e($qtCfg['create']) ?>">Back to products</a>
					<button type="button" class="btn btn-default btn-sm" id="ai-discard">Discard import</button>
				</div>
			</div>
			<div class="box-body">
				<div id="ai-review"></div>
			</div>
		</div>
	</section>
</div>

<?php include_once('templates/footer.php'); ?>
<script type="application/json" id="ai-state"><?= json_encode($state, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>
<script src="js/ai_review.js?v=2"></script>
