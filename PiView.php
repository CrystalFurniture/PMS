<?php
/**
 * Proforma Invoice in the application's own document format: the same page layout as the quotation
 * print page (ExportToExcelDp.php / ExportToExcelMRP.php), with "PROFORMA INVOICE" in place of the
 * quotation number and the same "Export to Excel" button. All figures come from the PI snapshot saved
 * at finalization (pi_documents.data), so later catalogue or price changes never alter it. Owner only.
 */
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
		include_once('includes/ai_provider.php');
		include_once('includes/ai_import_lib.php');
		include_once('includes/pi_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();

	list($doc, $error) = pi_document_get($connect, isset($_GET['id']) ? $_GET['id'] : '');
	$snap = $doc ? json_decode($doc['data'], true) : null;
	if ($error !== null || !is_array($snap) || isset($snap['quotation_id'])) {
		http_response_code($error === 'You do not have access to this document.' ? 403 : 404);
		header('Content-Type: text/html; charset=utf-8');
		echo '<!doctype html><meta charset="utf-8"><title>Document not available</title><p>' . qt_e($error !== null ? $error : 'Document not found.')
			. '</p><p><a href="AiAssistant.php">Back to the AI Assistant</a></p>';
		exit;
	}
	$pi = $snap['pi'];
	$t = $snap['templates'];
	$co = $t['company']['content'];
	$bank = $t['bank']['content'];
	$sign = $t['signatory']['content'];
	$terms = isset($pi['terms']) ? $pi['terms'] : $t['terms']['content'];
	$c = $pi['customer'];
	$taxRate = (float) $pi['tax_rate'];
	$taxLabel = rtrim(rtrim(number_format($taxRate, 2), '0'), '.');
	$b = 'border: 1px solid black !important;';

	/** Amount with Indian digit grouping and two decimals, as on current PIs: 1,36,056.00 */
	function pi_view_amount($n)
	{
		if ($n === null) {
			return '';
		}
		$n = round((float) $n, 2);
		$sign = $n < 0 ? '-' : '';
		$parts = explode('.', number_format(abs($n), 2, '.', ''));
		$whole = $parts[0];
		if (strlen($whole) > 3) {
			$whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)) . ',' . substr($whole, -3);
		}
		return $sign . $whole . '.' . $parts[1];
	}

		include_once('templates/header.php');
		include_once('templates/header_menu.php');
		include_once('templates/side_menubar.php');
?>
<style>
th{
	width:14.28% !important;
}
</style>
  <div class="content-wrapper">

    <section class="content-header">
      <h1>
        Print
        <small>Proforma Invoice <?= qt_e($snap['number']) ?></small>
      </h1>
    </section>

  <div class="container">
<div class="col-md-11" >

	<span>&nbsp; </span>
		<span>&nbsp; </span>
			<input type="button"  class="btn btn-success btn-large right" id="btnExport" value=" Export to Excel " style="Margin-bottom: 10px;"/>
			<?php if (($cv = mysqli_query($connect, "SELECT 1 FROM ai_conversations WHERE id = '" . mysqli_real_escape_string($connect, (string) $snap['conversation_id']) . "'")) && mysqli_num_rows($cv) > 0) { ?>
			<a class="btn btn-default" href="AiAssistant.php?conv=<?= qt_e($snap['conversation_id']) ?>" style="Margin-bottom: 10px;">Back to the conversation</a>
			<?php } ?>
			<span>&nbsp; </span>
			<div id="excel">
<table id="example" class="table table-bordered" >
					<tr>
					<th colspan=2 style="text-align: -webkit-center;width: 200px;<?= $b ?>"><img src="http://192.168.1.111/pms/core/images/logo_1.png" class="thumbnail" style="width:150; padding:20px"></th>
					<th colspan=5 style="<?= $b ?> text-align: center;"><span style="font-size:16px"> <?= qt_e($co['name']) ?> </span></br>
						 <span style="font-weight:600"><?= nl2br(qt_e($co['address'])) ?><br>
						 <?= qt_e($co['phone']) ?><br>
						 E-mail: <?= qt_e($co['email']) ?>  Web  :  <?= qt_e($co['web']) ?> <br><br>
						 <p>&nbsp;</p></span></th>
					<th colspan=2 style="text-align: -webkit-center;width: 200px;<?= $b ?>"><img src="http://192.168.1.111/pms/core/images/3C.png" class="thumbnail" style="width:150px; padding:20px"></th>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=3 rowspan=5>Client Name & Address :- <?= qt_e($c['name']) ?><br><?= nl2br(qt_e($c['address'])) ?><?php
							if ($c['state_name'] !== '' && stripos($c['address'], $c['state_name']) === false) { ?><br><?= qt_e($c['state_name']) ?><?php } ?><br>GSTIN: <?= qt_e($c['gstin']) ?></td>
						<td style="<?= $b ?>" width="140" colspan=2>PROFORMA INVOICE</td>
						<td style="<?= $b ?>" colspan=2></td>
						<td style="<?= $b ?>">REV.NO</td>
						<td style="<?= $b ?>"></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=2>DATE</td>
						<td style="<?= $b ?>" colspan=2><?= qt_e($snap['date']) ?></td>
						<td style="<?= $b ?>">DATE</td>
						<td style="<?= $b ?>"></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=2>GST NO </td>
						<td style="<?= $b ?>"colspan=2><?= qt_e($co['gstin']) ?></td>
						<td style="<?= $b ?>">HSN Code</td>
						<td style="<?= $b ?>"></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=2>Representative :-</td>
						<td style="<?= $b ?>"colspan=2>Name</td>
						<td style="<?= $b ?>"colspan=2>E-Mail</td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=2></td>
						<td style="<?= $b ?>"colspan=2><?= qt_e(strtoupper($snap['prepared_by']['name'])) ?></td>
						<td style="<?= $b ?>"colspan=2><?= qt_e($snap['prepared_by']['email']) ?></td>
					</tr>
					<tr>
						<td colspan=9></td>
					</tr>
					<tr style="text-align: center;">
						<td style="<?= $b ?>">Sr.No.</td>
						<td style="<?= $b ?>"colspan=2>Particulars</td>
						<td width="20" style="<?= $b ?>" colspan=3>PRODUCT MODEL IMAGE</td>
						<td style="<?= $b ?>">Qty. </td>
						<td style="<?= $b ?>">Rate (Rs.)</td>
						<td style="<?= $b ?>">Amount (Rs.)</td>
					</tr>
					<?php
						// DP (or MRP) items first, then NET items in their own section (NET carries no discount).
						$sections = array(array(), array());
						foreach ($pi['lines'] as $line) {
							$sections[isset($line['rate_type']) && $line['rate_type'] === 'NET' ? 1 : 0][] = $line;
						}
						$hasNet = count($sections[1]) > 0;
						$sr = 0;
						$total_qty = 0;
						foreach ($sections as $s => $sectionLines) {
							foreach ($sectionLines as $line) {
								$p = $line['product'];
								$total_qty += (int) $line['quantity'];
								$sr++;
					?>
					<tr>
						<td style="text-align: center;<?= $b ?>vertical-align: middle;" rowspan=4><?= $sr ?></td>
						<td style="line-height: 2.5em; <?= $b ?>"colspan=2><?= qt_e($p['name']) ?></td>
						<td style="text-align: -webkit-center;width: 200px;<?= $b ?>" rowspan=4; colspan=3>
						<?php if ($p['thumb'] != '') { ?>
							<p>&nbsp;</p>
							<img src='http://192.168.1.111/pms/core/<?= qt_e($p['thumb']) ?>'  width="130" height="75" hspace="46" vspace="20">
							<p>&nbsp;</p>
						<?php } ?>
						</td>
						<td style="text-align: center;<?= $b ?>vertical-align: middle;" rowspan=4 ><?= (int) $line['quantity'] ?></td>
						<td style="text-align: right;<?= $b ?>vertical-align: middle;" rowspan=4 ><?= pi_view_amount($line['rate']) ?><?php
							if (!empty($line['variant'])) { ?><br><small>(<?= qt_e(ucfirst(strtolower($line['variant']))) ?> wood)</small><?php } ?></td>
						<td style="text-align: right;<?= $b ?>vertical-align: middle;" rowspan=4 ><?= pi_view_amount($line['amount']) ?></td>
					</tr>
					<tr>
						<td style="line-height: 2.5em;<?= $b ?>"colspan=2><?= qt_e($p['description']) ?></td>
					</tr>
					<tr>
						<td style="line-height: 2.5em;<?= $b ?>"colspan=2>Size :- <?= qt_e($p['size']) ?></td>
					</tr>
					<tr>
						<td style="line-height: 2.5em;<?= $b ?>"colspan=2>Color :- <?= qt_e($p['color']) ?></td>
					</tr>
					<?php
							}
							$y = 'background-color: yellow; text-align: center; ' . $b;
							if ($s === 0 && count($sectionLines) && ($hasNet || $pi['discount_percent'] > 0)) {
								$dpBasic = $pi['subtotal'] - (isset($pi['net_subtotal']) ? $pi['net_subtotal'] : 0);
					?>
					<tr>
						<td colspan=6></td>
						<td style="<?= $y ?>">BASIC</td>
						<td style="<?= $y ?>"></td>
						<td style="<?= $y ?>text-align: right;"><?= pi_view_amount($dpBasic) ?></td>
					</tr>
					<?php if ($pi['discount_percent'] > 0) { ?>
					<tr>
						<td colspan=6></td>
						<td style="<?= $y ?>">DISCOUNT %</td>
						<td style="<?= $y ?>"><?= qt_e(rtrim(rtrim(number_format($pi['discount_percent'], 2), '0'), '.')) ?>%</td>
						<td style="<?= $y ?>text-align: right;"><?= pi_view_amount($pi['discount']) ?></td>
					</tr>
					<?php } ?>
					<tr>
						<td colspan=6></td>
						<td style="<?= $y ?>">TOTAL</td>
						<td style="<?= $y ?>"></td>
						<td style="<?= $y ?>text-align: right;"><?= pi_view_amount($dpBasic - $pi['discount']) ?></td>
					</tr>
					<?php } elseif ($s === 1 && $hasNet) { ?>
					<tr>
						<td colspan=6></td>
						<td style="<?= $y ?>">TOTAL</td>
						<td style="<?= $y ?>"></td>
						<td style="<?= $y ?>text-align: right;"><?= pi_view_amount($pi['net_subtotal']) ?></td>
					</tr>
					<?php }
						} ?>
					<tr>
						<td colspan=9></td>
					</tr>
					<?php foreach ($pi['charges'] as $ch) { ?>
					<tr>
						<td colspan=5></td>
						<td style="<?= $b ?>" colspan=3><?= qt_e($ch['label']) ?></td>
						<td style="<?= $b ?>text-align: right;"><?= pi_view_amount($ch['amount']) ?></td>
					</tr>
					<?php } ?>
					<tr>
						<td style="<?= $b ?>"colspan=5></td>
						<td style="background-color: yellow; <?= $b ?>">TOTAL Qty</td>
						<td style="background-color: yellow; <?= $b ?>text-align: center;"><?= (int) $total_qty ?></td>
						<td style="background-color: yellow; <?= $b ?>">TOTAL AMOUNT</td>
						<td style="background-color: yellow; <?= $b ?>text-align: right;"><?= pi_view_amount($pi['basic']) ?></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=5><?= qt_e($bank['heading']) ?></td>
						<td style="<?= $b ?>text-align: center;" colspan=2><?= qt_e($pi['tax_label']) ?></td>
						<td style="<?= $b ?>text-align: center;"><?= qt_e($taxLabel) ?>%</td>
						<td style="<?= $b ?>text-align: right;"><?= pi_view_amount($pi['tax']) ?></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=2>Bank : <?= qt_e($bank['bank']) ?></td>
						<td style="<?= $b ?>"colspan=3>Branch : <?= qt_e($bank['branch']) ?></td>
						<td style="background-color: yellow; <?= $b ?>text-align: center;" colspan=3 rowspan=2>FINAL AMOUNT( INCLUDING <?= qt_e($taxLabel) ?>% <?= qt_e($pi['tax_label']) ?> )</td>
						<td style="background-color: yellow; <?= $b ?>text-align: right;" rowspan=2><?= pi_view_amount($pi['total']) ?></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"colspan=2>A/C No. : <?= qt_e($bank['account_no']) ?></td>
						<td style="<?= $b ?>"colspan=3>IFSC Code : <?= qt_e($bank['ifsc']) ?></td>
					</tr>
					<tr>
						<td colspan=9></td>
					</tr>
					<tr>
						<td style="<?= $b ?>text-align: center;" colspan=9 ><?= qt_e($terms['heading']) ?></td>
					</tr>
					<tr>
						<td colspan=9></td>
					</tr>
					<?php foreach ($terms['lines'] as $i => $termLine) { ?>
					<tr>
						<td style="<?= $b ?>text-align: right;"><?= $i + 1 ?></td>
						<td style="<?= $b ?>" colspan=8><?= qt_e($termLine) ?></td>
					</tr>
					<?php } ?>
					<tr>
						<td colspan=9></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"></td>
						<td style="<?= $b ?>"colspan=8><?= qt_e($sign['for'] !== '' ? 'For,' : '') ?></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"></td>
						<td style="<?= $b ?>"colspan=8><?= qt_e($sign['for']) ?></td>
					</tr>
					<?php if ($sign['name'] !== '' || $sign['title'] !== '') { ?>
					<tr>
						<td colspan=9></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"></td>
						<td style="<?= $b ?>"colspan=8><?= qt_e($sign['name']) ?></td>
					</tr>
					<tr>
						<td style="<?= $b ?>"></td>
						<td style="<?= $b ?>"colspan=8><?= qt_e($sign['title']) ?></td>
					</tr>
					<?php } ?>
					<?php
						$notes = array();
						if (!empty($pi['round_off'])) {
							$notes[] = 'Round off: ' . sprintf('%+.2f', $pi['round_off']);
						}
						if (!empty($pi['discount_note'])) {
							$notes[] = $pi['discount_note'];
						}
						if ($notes) {
					?>
					<tr>
						<td colspan=9></td>
					</tr>
					<tr>
						<td colspan=9><?= qt_e(implode('; ', $notes)) ?></td>
					</tr>
					<?php } ?>
</table>
	</div>
		</div>
</div>
  </div>
<?php include_once('templates/footer.php'); ?>
<script type="text/javascript">
		$("#btnExport").click(function (e) {
		var dt = new Date();
        var postfix = dt.getDate() + "." + (dt.getMonth() + 1) + "." + dt.getFullYear() + "_" + dt.getHours() + "." + dt.getMinutes();
        var a = document.createElement('a');
        var data_type = 'data:application/vnd.ms-excel';
        var table_div = document.getElementById('excel');
        var table_html = table_div.outerHTML.replace(/ /g, '%20');
        a.href = data_type + ', ' + table_html;
        a.download = <?= json_encode(preg_replace('/[^A-Za-z0-9._-]+/', '-', $snap['number'])) ?> + '_' + postfix + '.xls';
        a.click();
		e.preventDefault();
		});
</script>
