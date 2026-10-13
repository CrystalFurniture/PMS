<?php
/**
 * Proforma Invoice rendering: one HTML layout used for the PDF (TCPDF, already in the project).
 * Content comes only from the immutable document snapshot (pi_documents.data): server-computed
 * lines and totals plus the doc_templates versions in force when the PI was generated.
 */

function pi_h($v)
{
	return htmlspecialchars((string) $v, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8', false);
}

/** Indian digit grouping with two decimals (12,34,567.00). */
function pi_money($n)
{
	$n = round((float) $n, 2);
	$neg = $n < 0;
	$n = abs($n);
	$whole = (string) (int) floor($n);
	$frac = str_pad((string) (int) round(($n - floor($n)) * 100), 2, '0', STR_PAD_LEFT);
	if (strlen($whole) > 3) {
		$whole = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($whole, 0, -3)) . ',' . substr($whole, -3);
	}
	return ($neg ? '-' : '') . $whole . '.' . $frac;
}

function pi_document_html($snap)
{
	$pi = $snap['pi'];
	$t = $snap['templates'];
	$co = $t['company']['content'];
	$bank = $t['bank']['content'];
	$terms = isset($pi['terms']) ? $pi['terms'] : $t['terms']['content'];
	$sign = $t['signatory']['content'];
	$c = $pi['customer'];
	$b = 'border:0.5px solid #000;';
	$h = '<table cellpadding="4" style="width:100%;font-size:9px;">';
	$h .= '<tr><td colspan="8" style="' . $b . 'text-align:center;"><span style="font-size:13px;font-weight:bold;">' . pi_h($co['name']) . '</span><br>'
		. pi_h($co['address']) . '<br>' . pi_h($co['phone']) . '<br>E-mail: ' . pi_h($co['email']) . '  Web: ' . pi_h($co['web'])
		. '<br><b>GSTIN: ' . pi_h($co['gstin']) . '</b></td></tr>';
	$h .= '<tr><td colspan="8" style="' . $b . 'text-align:center;font-size:12px;font-weight:bold;background-color:#eeeeee;">PROFORMA INVOICE</td></tr>';
	$h .= '<tr><td colspan="4" rowspan="4" style="' . $b . '"><b>Bill to:</b> ' . pi_h($c['name']) . '<br>' . nl2br(pi_h($c['address']))
		. '<br>State: ' . pi_h($c['state_name']) . ' (' . pi_h($c['state_code']) . ')'
		. ($c['gstin'] !== '' ? '<br>GSTIN: ' . pi_h($c['gstin']) : '<br>GSTIN: not provided')
		. ($c['phone'] !== '' ? '<br>Phone: ' . pi_h($c['phone']) : '') . '</td>'
		. '<td colspan="2" style="' . $b . '">PI No.</td><td colspan="2" style="' . $b . '"><b>' . pi_h($snap['number']) . '</b></td></tr>';
	$h .= '<tr><td colspan="2" style="' . $b . '">Date</td><td colspan="2" style="' . $b . '">' . pi_h($snap['date']) . '</td></tr>';
	$h .= '<tr><td colspan="2" style="' . $b . '">Place of supply</td><td colspan="2" style="' . $b . '">' . pi_h($c['state_name']) . '</td></tr>';
	$h .= '<tr><td colspan="2" style="' . $b . '">Price list</td><td colspan="2" style="' . $b . '">' . pi_h($pi['price_list']['name']) . ' (' . pi_h($pi['basis'])
		. ', v' . pi_h($pi['price_list']['version']) . ')</td></tr>';
	if ($pi['delivery_state']['code'] !== '') {
		$h .= '<tr><td colspan="8" style="' . $b . '">Delivery state: ' . pi_h($pi['delivery_state']['name']) . '</td></tr>';
	}
	$h .= '<tr style="font-weight:bold;background-color:#eeeeee;text-align:center;"><td style="' . $b . '">Sr.</td><td colspan="3" style="' . $b . '">Particulars</td>'
		. '<td style="' . $b . '">Qty</td><td style="' . $b . '">Rate (Rs.)</td><td colspan="2" style="' . $b . '">Amount (Rs.)</td></tr>';
	foreach ($pi['lines'] as $l) {
		$p = $l['product'];
		$h .= '<tr><td style="' . $b . 'text-align:center;">' . (int) $l['n'] . '</td><td colspan="3" style="' . $b . '"><b>' . pi_h($p['name']) . '</b><br>'
			. pi_h($p['description']) . ($p['size'] !== '' ? '<br>Size: ' . pi_h($p['size']) : '') . ($p['color'] !== '' ? '<br>Colour: ' . pi_h($p['color']) : '')
			. '</td><td style="' . $b . 'text-align:center;">' . (int) $l['quantity'] . ' Nos.</td><td style="' . $b . 'text-align:right;">' . pi_money($l['rate'])
			. '</td><td colspan="2" style="' . $b . 'text-align:right;">' . pi_money($l['amount']) . '</td></tr>';
	}
	$row = function ($label, $value, $bold = false) use ($b) {
		return '<tr><td colspan="5" style="border:none;"></td><td style="' . $b . ($bold ? 'font-weight:bold;' : '') . '">' . $label
			. '</td><td colspan="2" style="' . $b . 'text-align:right;' . ($bold ? 'font-weight:bold;' : '') . '">' . $value . '</td></tr>';
	};
	$h .= $row('Total', pi_money($pi['subtotal']), true);
	if ($pi['discount_percent'] > 0) {
		$h .= $row('Discount ' . pi_h(rtrim(rtrim(number_format($pi['discount_percent'], 2), '0'), '.')) . '%', '-' . pi_money($pi['discount']));
	}
	foreach ($pi['charges'] as $ch) {
		$h .= $row(pi_h($ch['label']), pi_money($ch['amount']));
	}
	$h .= $row('Basic amount', pi_money($pi['basic']));
	$h .= $row(pi_h($pi['tax_label']) . ' ' . pi_h(rtrim(rtrim(number_format($pi['tax_rate'], 2), '0'), '.')) . '%', pi_money($pi['tax']));
	$h .= $row('Grand total', pi_money($pi['total']), true);
	$h .= '<tr><td colspan="8" style="' . $b . '"><b>' . pi_h($bank['heading']) . '</b><br>Bank: ' . pi_h($bank['bank']) . ', Branch: ' . pi_h($bank['branch'])
		. '<br>A/C No.: ' . pi_h($bank['account_no']) . '  IFSC: ' . pi_h($bank['ifsc']) . '</td></tr>';
	$h .= '<tr><td colspan="8" style="' . $b . '"><b>' . pi_h($terms['heading']) . '</b><br>';
	foreach ($terms['lines'] as $i => $line) {
		$h .= ($i + 1) . '. ' . pi_h($line) . '<br>';
	}
	$h .= '</td></tr>';
	$h .= '<tr><td colspan="4" style="' . $b . '">Prepared by: ' . pi_h($snap['prepared_by']['name']) . '<br>' . pi_h($snap['prepared_by']['email']) . '</td>'
		. '<td colspan="4" style="' . $b . 'text-align:right;">For, ' . pi_h($sign['for']) . '<br><br><br>' . pi_h($sign['name']) . '<br>' . pi_h($sign['title']) . '</td></tr>';
	$h .= '</table>';
	$h .= '<p style="font-size:7px;color:#555555;">Price list: ' . pi_h($pi['price_list']['name']) . ' ' . pi_h($pi['basis'])
		. ' version ' . pi_h($pi['price_list']['version'])
		. (isset($pi['price_list']['source_file']) && $pi['price_list']['source_file'] !== '' ? ', file ' . pi_h($pi['price_list']['source_file']) : '')
		. (isset($pi['price_list']['updated_at']) ? ', uploaded ' . pi_h($pi['price_list']['updated_at']) : '')
		. '. Document templates: ' . pi_h(implode(', ', array_map(function ($k, $v) { return $k . ' v' . $v; }, array_keys($pi['templates']), $pi['templates'])))
		. (isset($pi['terms']['source']['kind']) && $pi['terms']['source']['kind'] === 'approved_document'
			? '. Terms: ' . pi_h($pi['terms']['source']['title']) . ' v' . (int) $pi['terms']['source']['version'] . ' (' . pi_h($pi['terms']['source']['source']) . ')' : '')
		. '.</p>';
	return $h;
}

/** Write the PDF into private storage. Returns the file name or null. */
function pi_write_pdf($ai, $id, $snap)
{
	$dir = $ai['storage_dir'] . '/pi';
	if (!is_dir($dir) && !@mkdir($dir, 0700, true)) {
		return null;
	}
	if (!class_exists('TCPDF')) {
		require_once dirname(__DIR__) . '/TCPDF/tcpdf.php';
	}
	$pdf = new TCPDF('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->SetCreator('Crystal PMS');
	$pdf->SetTitle('Proforma Invoice ' . $snap['number']);
	$pdf->setPrintHeader(false);
	$pdf->setPrintFooter(false);
	$pdf->SetMargins(10, 10, 10);
	$pdf->SetAutoPageBreak(true, 10);
	$pdf->SetFont('dejavusans', '', 9);
	$pdf->AddPage();
	$pdf->writeHTML(pi_document_html($snap), true, false, true, false, '');
	$file = 'PI-' . (int) $id . '.pdf';
	$pdf->Output($dir . '/' . $file, 'F');
	return is_file($dir . '/' . $file) ? $file : null;
}
