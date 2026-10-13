<?php
/**
 * Price list (state) for the current quotation. Expects $qtType, $connect.
 * The list can only be changed while the cart is empty and no saved quotation is
 * being edited, so lines already in the cart are never repriced silently.
 */
$qtStates = qt_price_states($connect);
if ($qtStates):
	$qtCurrentState = qt_price_state($qtType);
	$qtLocked = count(qt_cart_get($qtType)) > 0 || qt_editing_id($qtType) !== null;
?>
	<div class="alert alert-<?= $qtCurrentState !== '' ? 'info' : 'default' ?>" style="border:1px solid #ddd; padding:8px 12px" id="price-state">
		<strong>Price list:</strong> <?= qt_e(qt_price_state_name($connect, $qtCurrentState)) ?><?= $qtCurrentState !== '' ? ' (' . qt_e($qtCurrentState) . ')' : '' ?>
		<?php if ($qtLocked): ?>
			<span class="text-muted">&nbsp; Fixed for this quotation. To use another state's prices, start a new quotation.</span>
			<form method="POST" action="action.php" style="display:inline; margin-left:8px">
				<input type="hidden" name="action" value="new_quotation">
				<input type="hidden" name="type" value="<?= qt_e($qtType) ?>">
				<input type="hidden" name="csrf_token" value="<?= qt_e(qt_csrf_token()) ?>">
				<button type="submit" class="btn btn-default btn-xs" id="price-state-new"
					onclick="return confirm('Start a new quotation? Products in the current cart that are not saved will be removed.');">Start a new quotation</button>
			</form>
		<?php else: ?>
			<form method="POST" action="action.php" style="display:inline; margin-left:12px">
				<input type="hidden" name="action" value="set_price_state">
				<input type="hidden" name="type" value="<?= qt_e($qtType) ?>">
				<input type="hidden" name="csrf_token" value="<?= qt_e(qt_csrf_token()) ?>">
				<select name="state" class="input-sm">
					<option value="">Standard price list</option>
					<?php foreach ($qtStates as $code => $name): ?>
						<option value="<?= qt_e($code) ?>" <?= $code === $qtCurrentState ? 'selected' : '' ?>><?= qt_e($name) ?> (<?= qt_e($code) ?>)</option>
					<?php endforeach; ?>
				</select>
				<button type="submit" class="btn btn-default btn-xs">Use this price list</button>
			</form>
			<span class="text-muted">&nbsp; Choose before adding products. Products missing from a state list use the standard price, with a warning.</span>
		<?php endif; ?>
	</div>
<?php endif; ?>
