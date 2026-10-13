<?php
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');

		$dashCount = function ($sql) use ($connect) {
			$r = mysqli_query($connect, $sql);
			$row = $r ? mysqli_fetch_row($r) : null;
			return $row ? (int) $row[0] : 0;
		};
		$dashFmt = function ($n) {
			return html_entity_decode(str_replace('&#8377; ', '', qt_inr($n)), ENT_QUOTES, 'UTF-8');
		};

		$dashPerm = is_array($UserPermission) ? $UserPermission : array();
		$canProducts = in_array('createProduct', $dashPerm) || in_array('updateProduct', $dashPerm) || in_array('viewProduct', $dashPerm) || in_array('deleteProduct', $dashPerm);
		$canCategory = in_array('createCategory', $dashPerm) || in_array('updateCategory', $dashPerm) || in_array('viewCategory', $dashPerm) || in_array('deleteCategory', $dashPerm);
		$canUsers = in_array('createUser', $dashPerm) || in_array('updateUser', $dashPerm) || in_array('viewUser', $dashPerm) || in_array('deleteUser', $dashPerm);

		$totalCatlogue = $dashCount("SELECT COUNT(*) FROM products WHERE catalogue = '1'");
		$totalStop = $dashCount("SELECT COUNT(*) FROM products WHERE catalogue = '0'");
		$totalCat = $dashCount("SELECT COUNT(*) FROM categories");
		$totalUser = $dashCount("SELECT COUNT(*) FROM users");

		$me = mysqli_real_escape_string($connect, (string) $_SESSION['username']);
		// q_date is stored as dd-mm-yyyy text.
		$monthCount = $dashCount("SELECT COUNT(*) FROM tbl_quotation WHERE q_user = '" . $me . "' AND q_date LIKE '%-" . date('m-Y') . "'");
		$recent = array();
		$res = mysqli_query($connect, "SELECT * FROM tbl_quotation WHERE q_user = '" . $me . "' ORDER BY q_id DESC LIMIT 5");
		while ($res && ($row = mysqli_fetch_assoc($res))) {
			$recent[] = $row;
		}

		// Catalogue products whose price would quote as zero.
		$noDp = $noMrp = 0;
		if ($canProducts) {
			$noDp = $dashCount("SELECT COUNT(*) FROM products p LEFT JOIN pricelist pl ON pl.p_prodId = p.id WHERE p.catalogue = '1' AND (pl.dp_price IS NULL OR pl.dp_price = 0)");
			$noMrp = $dashCount("SELECT COUNT(*) FROM products p LEFT JOIN pricelist pl ON pl.p_prodId = p.id WHERE p.catalogue = '1' AND (pl.mrp_price IS NULL OR pl.mrp_price = 0)");
		}
?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

    <section class="content-header">
      <h1>
        Dashboard
        <small class="pms-sub">Start a quotation or pick up where you left off.</small>
      </h1>
    </section>

    <section class="content">
          <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo qt_legacy_msg($_GET['msg']); ?>
            </div>
          <?php elseif(isset($_GET['failmsg'])): ?>
            <div class="alert alert-danger alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo qt_legacy_msg($_GET['failmsg']); ?>
            </div>
          <?php endif; ?>

      <div class="pms-actions">
        <a class="pms-action is-primary" href="CreatedOrderDp.php?new=1">
          <strong>New DP quotation</strong>
          <span>Dealer prices</span>
        </a>
        <a class="pms-action" href="CreatedOrderMRP.php?new=1">
          <strong>New MRP quotation</strong>
          <span>Retail prices with discount</span>
        </a>
        <a class="pms-action" href="AiAssistant.php">
          <strong>AI Assistant</strong>
          <span>Paste a requirement or attach photos &mdash; get a Proforma Invoice or quotation</span>
        </a>
      </div>

      <div class="pms-stats">
        <?php if ($canProducts) { ?>
        <a class="pms-stat" href="ManageProducts.php"><span>Catalogue products</span><strong><?= qt_e($dashFmt($totalCatlogue)) ?></strong></a>
        <a class="pms-stat" href="OldProducts.php"><span>Non-stock items</span><strong><?= qt_e($dashFmt($totalStop)) ?></strong></a>
        <?php } ?>
        <?php if ($canCategory) { ?>
        <a class="pms-stat" href="Category.php"><span>Categories</span><strong><?= qt_e($dashFmt($totalCat)) ?></strong></a>
        <?php } ?>
        <?php if ($canUsers) { ?>
        <a class="pms-stat" href="Users.php"><span>Users</span><strong><?= qt_e($dashFmt($totalUser)) ?></strong></a>
        <?php } ?>
        <a class="pms-stat" href="ViewQuotation.php"><span>Your quotations this month</span><strong><?= qt_e($dashFmt($monthCount)) ?></strong></a>
      </div>

      <div class="pms-row">
        <div class="box pms-main">
          <div class="box-header with-border pms-flex-head">
            <h3 class="box-title">Your recent quotations</h3>
            <a href="ViewQuotation.php" style="font-weight:600">View all</a>
          </div>
          <?php if (count($recent) === 0) { ?>
            <p class="pms-empty">No quotations yet. Start one above.</p>
          <?php } else { ?>
          <div class="pms-scroll">
            <table class="table" style="margin:0; min-width:560px">
              <thead>
                <tr>
                  <th>Quotation</th>
                  <th>Customer</th>
                  <th>Type</th>
                  <th style="text-align:right">Total</th>
                  <th>Date</th>
                </tr>
              </thead>
              <tbody>
              <?php foreach ($recent as $q) {
                $qType = $q['q_type'] === 'MRP' ? 'MRP' : 'DP';
                $qCfg = qt_config($qType);
                $totals = qt_quotation_totals($q);
              ?>
                <tr>
                  <td><a href="<?= qt_e($qCfg['detail']) ?>?id=<?= (int) $q['q_id'] ?>" style="font-weight:600"><?= qt_e($q['q_num']) ?></a></td>
                  <td><?= qt_e($q['q_name']) ?></td>
                  <td><span class="pms-badge <?= $qType === 'DP' ? 'pms-badge-dp' : 'pms-badge-mrp' ?>"><?= $qType ?></span></td>
                  <td style="text-align:right" class="pms-num"><?= $totals === null ? '&mdash;' : qt_inr($totals[4]) ?></td>
                  <td class="text-muted"><?= qt_e($q['q_revDate'] !== '' ? $q['q_revDate'] : $q['q_date']) ?></td>
                </tr>
              <?php } ?>
              </tbody>
            </table>
          </div>
          <?php } ?>
        </div>

        <?php if ($canProducts) { ?>
        <div class="box pms-side">
          <div class="box-header with-border"><h3 class="box-title">Needs attention</h3></div>
          <div class="box-body">
            <?php if ($noDp === 0 && $noMrp === 0) { ?>
              <div class="pms-attention is-ok"><span>Every catalogue product has a DP and an MRP price.</span></div>
            <?php } ?>
            <?php if ($noDp > 0) { ?>
              <a class="pms-attention" href="ManageProducts.php?missing=dp"><strong><?= qt_e($dashFmt($noDp)) ?></strong><span>catalogue products have no DP price &mdash; they would quote at &#8377; 0</span></a>
            <?php } ?>
            <?php if ($noMrp > 0) { ?>
              <a class="pms-attention" href="ManageProducts.php?missing=mrp"><strong><?= qt_e($dashFmt($noMrp)) ?></strong><span>catalogue products have no MRP price</span></a>
            <?php } ?>
          </div>
        </div>
        <?php } ?>
      </div>

    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->
<?php
		include_once('templates/footer.php');

?>
