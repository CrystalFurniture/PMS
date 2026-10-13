<?php
/**
 * "Import customer requirement" panel for the DP/MRP product screens.
 * Expects $qtType (DP or MRP). The manual product table works with or without it.
 */
list($qtAi, $qtAiError) = qt_ai_settings();
?>
<div class="box box-default collapsed-box" id="ai-import-box">
	<div class="box-header with-border">
		<h3 class="box-title"><i class="fa fa-magic"></i> Import customer requirement</h3>
		<div class="box-tools pull-right">
			<button type="button" class="btn btn-box-tool" data-widget="collapse"><i class="fa fa-plus"></i></button>
		</div>
	</div>
	<div class="box-body">
		<?php if ($qtAiError !== null): ?>
			<p class="text-muted"><?= qt_e($qtAiError) ?> Use the product list below to add items manually.</p>
		<?php else: ?>
			<p><span class="label <?= qt_ai_mode($qtAi) === 'live' ? 'label-success' : 'label-warning' ?>" id="ai-mode"><?= qt_e(qt_ai_mode_label($qtAi)) ?></span></p>
			<p class="text-muted">
				Paste the customer's WhatsApp message and/or add photos or screenshots of their list.
				The AI reads the items and suggests catalogue products; nothing is added to the cart until you review and approve each row.
				Text and images are sent to the AI provider for reading.
			</p>
			<div class="row">
				<div class="col-md-6">
					<label for="ai-text">Customer message</label>
					<textarea id="ai-text" class="form-control" rows="6" maxlength="<?= (int) $qtAi['max_text_chars'] ?>" placeholder="Paste the message here"></textarea>
				</div>
				<div class="col-md-6">
					<label for="ai-files">Photos or screenshots (JPG, PNG, WebP; up to <?= (int) $qtAi['max_images'] ?>, <?= round($qtAi['max_image_bytes'] / 1048576, 1) ?> MB each)</label>
					<input type="file" id="ai-files" accept="image/jpeg,image/png,image/webp" multiple>
					<div id="ai-previews" style="margin-top:8px"></div>
				</div>
			</div>
			<?php
			$qtStanding = qt_ai_standing_get($qtAi);
			$qtCanEditStanding = isset($UserPermission) && is_array($UserPermission) && in_array('createProduct', $UserPermission, true);
			?>
			<div class="row" style="margin-top:10px">
				<div class="col-md-6">
					<label for="ai-instructions">Instructions for this requirement <small class="text-muted">(optional, from the person in charge)</small></label>
					<textarea id="ai-instructions" class="form-control" rows="3" maxlength="1000"
						placeholder="For example: All beds are king size. Wardrobes in Wenge unless stated."></textarea>
				</div>
				<div class="col-md-6">
					<label>Standing instructions <small class="text-muted">(applied to every import)</small></label>
					<div class="well well-sm" id="ai-standing" style="white-space:pre-wrap; margin-bottom:4px; min-height:40px"><?= $qtStanding['text'] !== '' ? qt_e($qtStanding['text']) : '<span class="text-muted">None set.</span>' ?></div>
					<?php if ($qtCanEditStanding): ?><a href="AiSettings.php" class="small">Edit standing instructions</a><?php endif; ?>
				</div>
			</div>
			<div style="margin-top:10px">
				<button type="button" class="btn btn-primary" id="ai-extract">Read requirement</button>
				<span id="ai-import-status" style="margin-left:10px"></span>
			</div>
			<script type="application/json" id="ai-import-config"><?= json_encode(array(
				'type' => $qtType,
				'max_images' => (int) $qtAi['max_images'],
				'max_image_bytes' => (int) $qtAi['max_image_bytes'],
				'max_text_chars' => (int) $qtAi['max_text_chars'],
			), JSON_HEX_TAG | JSON_HEX_AMP) ?></script>
		<?php endif; ?>
	</div>
</div>
