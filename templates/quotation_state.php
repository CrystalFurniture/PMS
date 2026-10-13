<?php
/**
 * Shows whether the DP/MRP cart belongs to a new quotation or to an existing
 * one being edited, with a button that explicitly discards the edit.
 * Expects $qtType and $qtCfg (see includes/quotation_lib.php).
 */
$qtEditingId = qt_editing_id($qtType);
if ($qtEditingId !== null):
	$qtEditingNum = isset($_SESSION[$qtCfg['qnum']]) ? $_SESSION[$qtCfg['qnum']] : ('#' . $qtEditingId);
	$qtAskedNew = isset($_GET['new']);
?>
	<div class="alert <?= $qtAskedNew ? 'alert-warning' : 'alert-info' ?>" role="alert">
		<?php if ($qtAskedNew): ?>
			<strong>Quotation <?= qt_e($qtEditingNum) ?> is still open for editing.</strong>
			Start a new quotation to discard its unsaved changes, or keep adding products to revise it.
		<?php else: ?>
			<strong>Editing quotation <?= qt_e($qtEditingNum) ?>.</strong>
			Products you add go into this quotation, and saving creates a new revision of it.
		<?php endif; ?>
		<form method="POST" action="action.php" style="display:inline; margin-left:10px;">
			<input type="hidden" name="action" value="new_quotation">
			<input type="hidden" name="type" value="<?= qt_e($qtType) ?>">
			<input type="hidden" name="csrf_token" value="<?= qt_e(qt_csrf_token()) ?>">
			<button type="submit" class="btn btn-default btn-xs"
				onclick="return confirm('Discard the unsaved changes to this quotation and start a new one?');">
				Start a new <?= qt_e($qtType) ?> quotation
			</button>
		</form>
	</div>
<?php endif; ?>
