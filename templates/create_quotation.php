<?php
/**
 * Create / edit quotation screen shared by CreatedOrderDp.php and CreatedOrderMRP.php.
 * Expects $connect, $qtType ('DP' or 'MRP'), $qtCfg and $qtCart from the page.
 * Product list on the left (Add buttons post to action.php via js/ajax.js),
 * the current cart with quantities and running total on the right.
 */
$qtIsDp = $qtType === 'DP';
$qtAddClass = $qtIsDp ? 'add_dp' : 'add_mrp';
$qtPriceCol = $qtIsDp ? 'dp_price' : 'mrp_price';
$qtEditingNow = qt_editing_id($qtType);
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

    <section class="content-header">
      <div class="pms-create-head">
        <div class="pms-grow">
          <h1 style="margin:0">
            <?= $qtEditingNow !== null ? 'Edit ' . qt_e($qtType) . ' quotation' : 'New ' . qt_e($qtType) . ' quotation' ?>
            <small class="pms-sub"><?= $qtIsDp ? 'Dealer prices (DP)' : 'Retail prices (MRP), discount on the next step' ?></small>
          </h1>
        </div>
        <nav class="pms-seg" aria-label="Price type">
          <a href="CreatedOrderDp.php" class="<?= $qtIsDp ? 'is-on' : '' ?>" <?= $qtIsDp ? 'aria-current="page"' : '' ?>>DP &middot; dealer</a>
          <a href="CreatedOrderMRP.php" class="<?= $qtIsDp ? '' : 'is-on' ?>" <?= $qtIsDp ? '' : 'aria-current="page"' ?>>MRP &middot; retail</a>
        </nav>
      </div>
    </section>

	<section class="content">
		<?php echo qt_csrf_field(); ?>
		<?php echo qt_flash_render(); ?>
		<?php include('templates/quotation_state.php'); ?>
		<?php include('templates/price_state_selector.php'); ?>
		<div class="alert alert-info" style="display:flex;flex-wrap:wrap;align-items:center;gap:10px">
			<span style="flex:1 1 300px">Have the customer's list or a photo of it? The <strong>AI Assistant</strong> reads it, matches products and prepares the PI or quotation.</span>
			<a class="btn btn-primary btn-sm" href="AiAssistant.php">Open AI Assistant</a>
		</div>
		<div id="messages" aria-live="polite"></div>

		<div class="pms-layout">
			<div class="box pms-catalogue">
				<div class="box-body">
					<div class="pms-toolbar" style="padding:0 16px; margin-bottom:0">
						<label style="display:flex; align-items:center; gap:8px; font-weight:400; margin:0; color:var(--pms-ink-2)">
							<input type="checkbox" id="qt-hide-unpriced" style="width:18px; height:18px; margin:0"> Hide products without a price
						</label>
					</div>

					<table id="manageOrder" class="table pms-products" data-page-length='25' style="width:100%">
					  <thead>
					  <tr>
						<th>Sr No</th>
						<th>Image</th>
						<th>Product</th>
						<th>Tags</th>
						<th>Price</th>
						<th>Add</th>
					  </tr>
					  </thead>

					  <tbody>
					  <?php
						  // Prices from the chosen state's list where it has the product; otherwise standard (marked *).
						  $qtListState = qt_state_tables_exist($connect) ? qt_price_state($qtType) : '';
						  $result = mysqli_query($connect, $qtListState !== ''
							? "SELECT p.*, pl.*, sp." . $qtPriceCol . " AS state_price FROM products p INNER JOIN pricelist pl on pl.p_prodId = p.id LEFT JOIN state_pricelist sp ON sp.p_prodId = p.id AND sp.state_code = '" . mysqli_real_escape_string($connect, $qtListState) . "'"
							: "SELECT p.*, pl.* FROM products p INNER JOIN pricelist pl on pl.p_prodId = p.id");
						  $i = 1;
						  while($row = mysqli_fetch_assoc($result)){
							$fromState = $qtListState !== '' && $row['state_price'] !== null;
							$price = $fromState ? $row['state_price'] : $row[$qtPriceCol];
							$noPrice = (float) $price <= 0;
							$inCart = isset($qtCart[(string) $row['id']]);
							$thumb = (string) $row['thumb'];
							echo "<tr data-price='" . ($noPrice ? '0' : '1') . "'>
							  <td class='pms-srno'>" . $i++ . "</td>
							  <td class='pms-thumb'><div class='pms-thumb-box'>"
								. ($thumb !== '' ? "<img src='" . qt_e(str_replace(' ', '%20', $thumb)) . "' alt='' loading='lazy' class='pms-thumb-img'>" : "photo")
								. "</div></td>
							  <td class='pms-info'><span class='pms-prod-code'>" . qt_e($row['name']) . "</span>"
								. "<span class='pms-prod-desc'>" . qt_safe_html($row['description']) . "</span>"
								. (trim((string) $row['color']) !== '' ? "<span class='pms-prod-meta'>Colour: " . qt_e($row['color']) . "</span>" : "")
								. "</td>
							  <td class='pms-hidden'>" . qt_e($row['tags']) . "</td>
							  <td class='pms-price'><strong>" . qt_inr($price) . "</strong>"
								. ($qtListState !== '' && !$fromState ? "<span class='text-warning' style='font-size:12px' title='Not in the state price list: standard price'>* standard price</span>" : "")
								. ($noPrice ? "<span class='pms-badge pms-badge-warn'>No price set</span>" : "")
								. "</td>
							  <td class='pms-add'><button type='button' class='buy pms-add-btn " . $qtAddClass . ($inCart ? ' is-added' : '') . "' id='" . (int) $row['id'] . "' aria-label='Add " . qt_e($row['name']) . " to the quotation'>" . ($inCart ? 'Added' : 'Add') . "</button></td>
							</tr>";
						  }
					  ?>
					  </tbody>
					</table>
				</div>
			</div>

			<aside class="box pms-cart" id="qt-cart-summary" data-type="<?= qt_e($qtType) ?>" aria-label="This quotation">
				<?php echo qt_render_cart_summary($qtType); ?>
			</aside>
		</div>
    </section>
  </div>
  <!-- /.content-wrapper -->
<?php
		include_once('templates/footer.php');
?>
<script type="text/javascript">
// Missing product photos show a plain "photo" box (error events do not bubble: listen in the capture phase).
document.addEventListener('error', function (e) {
	var img = e.target;
	if (img && img.className === 'pms-thumb-img' && img.parentNode) {
		img.parentNode.textContent = 'photo';
	}
}, true);

$(document).ready(function () {
	var table = $('#manageOrder').DataTable({
		searching: true,
		ordering: true,
		bLengthChange: false,
		bInfo: true,
		autoWidth: false,
		language: { search: '', searchPlaceholder: 'Search by code, name, colour or tag', info: 'Showing _START_ to _END_ of _TOTAL_ products', infoFiltered: '(of _MAX_)' },
		columnDefs: [
			{ targets: [0, 3], visible: false },
			{ targets: [1, 5], orderable: false, searchable: false }
		]
	});
	$('#manageOrder_filter input').attr('aria-label', 'Search products');
	// Photos that failed before this script ran.
	$('img.pms-thumb-img').each(function () {
		if (this.complete && this.naturalWidth === 0 && this.parentNode) {
			this.parentNode.textContent = 'photo';
		}
	});

	// "Hide products without a price": filter on the row's data-price flag.
	$.fn.dataTable.ext.search.push(function (settings, data, dataIndex) {
		if (settings.nTable.id !== 'manageOrder' || !$('#qt-hide-unpriced').is(':checked')) {
			return true;
		}
		var node = settings.aoData[dataIndex].nTr;
		return !node || node.getAttribute('data-price') !== '0';
	});
	$('#qt-hide-unpriced').on('change', function () { table.draw(); });

});
</script>
<script src="js/ajax.js?v=ui2"></script>
