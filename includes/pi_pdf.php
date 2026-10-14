<?php
/**
 * Proforma Invoice as a PDF (A4, TCPDF), in the same layout as the PI page (PiView.php): logos and company,
 * client block, items with photos, DP section then NET section, totals, bank, terms, signature and notes.
 * Built only from the snapshot saved at approval. Product photos come from the PMS image server and are cached;
 * a photo that cannot be fetched is left out rather than delaying or failing the PDF.
 * Requires pi_lib.php (snapshot helpers) and the bundled TCPDF.
 */

define('PI_PDF_IMAGE_BASE', 'http://192.168.1.111/pms/core/');

/** 1,36,056.00 (Indian digit grouping, two decimals). */
function pi_pdf_amount($n)
{
	if ($n === null) {
		return '';
	}
	$n = round((float) $n, 2);
	$parts = explode('.', number_format(abs($n), 2, '.', ''));
	$w = $parts[0];
	if (strlen($w) > 3) {
		$w = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', substr($w, 0, -3)) . ',' . substr($w, -3);
	}
	return ($n < 0 ? '-' : '') . $w . '.' . $parts[1];
}

function pi_pdf_e($v)
{
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}

/** Whether TCPDF can embed this file: JPEG always; PNG with transparency only with GD or Imagick. */
function pi_pdf_image_ok($path)
{
	$h = @file_get_contents($path, false, null, 0, 32);
	if ($h === false || strlen($h) < 26) {
		return false;
	}
	if (substr($h, 0, 3) === "\xFF\xD8\xFF") {
		return true;
	}
	if (substr($h, 0, 8) === "\x89PNG\r\n\x1a\n") {
		$alpha = in_array(ord($h[25]), array(4, 6), true);
		return !$alpha || extension_loaded('gd') || extension_loaded('imagick');
	}
	return false;
}

/** A product photo as a local file (fetched once from the image server and cached), or null. */
function pi_pdf_product_image($thumb, $cacheDir)
{
	$thumb = ltrim((string) $thumb, '/');
	if ($thumb === '' || strpos($thumb, '..') !== false) {
		return null;
	}
	$local = dirname(__DIR__) . '/' . $thumb;   // a copy of the image folder on this server, if there is one
	if (is_file($local) && pi_pdf_image_ok($local)) {
		return $local;
	}
	if ($cacheDir === '' || (!is_dir($cacheDir) && !@mkdir($cacheDir, 0700, true))) {
		return null;
	}
	$file = $cacheDir . '/' . sha1($thumb) . '.img';
	if (is_file($file)) {
		return filesize($file) > 0 && pi_pdf_image_ok($file) ? $file : null;   // 0 bytes = known to be unavailable
	}
	$ch = curl_init(PI_PDF_IMAGE_BASE . str_replace('%2F', '/', rawurlencode($thumb)));
	curl_setopt_array($ch, array(CURLOPT_RETURNTRANSFER => true, CURLOPT_CONNECTTIMEOUT => 2, CURLOPT_TIMEOUT => 6, CURLOPT_FOLLOWLOCATION => false));
	$data = curl_exec($ch);
	$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
	curl_close($ch);
	@file_put_contents($file, ($code === 200 && is_string($data) && strlen($data) < 5000000) ? $data : '');
	return is_file($file) && filesize($file) > 0 && pi_pdf_image_ok($file) ? $file : null;
}

/** Company logos: the PNGs where they can be embedded, else white-background JPEG copies. */
function pi_pdf_logo($name)
{
	$base = dirname(__DIR__) . '/images/';
	foreach (array($name . '.png', $name . '_pdf.jpg') as $f) {
		if (is_file($base . $f) && pi_pdf_image_ok($base . $f)) {
			return $base . $f;
		}
	}
	return null;
}

/** The PI as TCPDF-friendly HTML (tables, inline widths and colours only). */
function pi_pdf_html($snap, $cacheDir)
{
	$pi = $snap['pi'];
	$t = $snap['templates'];
	$co = $t['company']['content'];
	$bank = $t['bank']['content'];
	$sign = $t['signatory']['content'];
	$terms = isset($pi['terms']) ? $pi['terms'] : $t['terms']['content'];
	$c = $pi['customer'];
	$tax = rtrim(rtrim(number_format((float) $pi['tax_rate'], 2), '0'), '.');
	$y = ' bgcolor="#FFFF00"';
	$img = function ($path, $w) {
		return $path !== null ? '<img src="' . pi_pdf_e($path) . '" width="' . (int) $w . '">' : '';
	};

	$h = '<table border="1" cellpadding="4" cellspacing="0" width="100%">'
		. '<tr><td width="20%" align="center">' . $img(pi_pdf_logo('logo_1'), 90) . '</td>'
		. '<td width="60%" align="center"><b style="font-size:11pt">' . pi_pdf_e($co['name']) . '</b><br>' . nl2br(pi_pdf_e($co['address'])) . '<br>'
		. pi_pdf_e($co['phone']) . '<br>E-mail: ' . pi_pdf_e($co['email']) . '  Web : ' . pi_pdf_e($co['web']) . '</td>'
		. '<td width="20%" align="center">' . $img(pi_pdf_logo('3C'), 90) . '</td></tr></table>';

	$state = ($c['state_name'] !== '' && stripos($c['address'], $c['state_name']) === false) ? '<br>' . pi_pdf_e($c['state_name']) : '';
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%">'
		. '<tr><td width="40%" rowspan="5">Client Name &amp; Address :- <b>' . pi_pdf_e($c['name']) . '</b><br>' . nl2br(pi_pdf_e($c['address'])) . $state
		. '<br>GSTIN: ' . pi_pdf_e($c['gstin']) . '</td>'
		. '<td width="18%">PROFORMA INVOICE</td><td width="18%"></td><td width="12%">REV.NO</td><td width="12%"></td></tr>'
		. '<tr><td>DATE</td><td>' . pi_pdf_e($snap['date']) . '</td><td>DATE</td><td></td></tr>'
		. '<tr><td>GST NO</td><td>' . pi_pdf_e($co['gstin']) . '</td><td>HSN Code</td><td></td></tr>'
		. '<tr><td>Representative :-</td><td>Name</td><td colspan="2">E-Mail</td></tr>'
		. '<tr><td></td><td>' . pi_pdf_e(strtoupper($snap['prepared_by']['name'])) . '</td><td colspan="2">' . pi_pdf_e($snap['prepared_by']['email']) . '</td></tr>'
		. '</table><br>';

	// Items: DP (or MRP) lines first, then NET lines in their own section, numbered straight through.
	$sections = array(array(), array());
	foreach ($pi['lines'] as $line) {
		$sections[isset($line['rate_type']) && $line['rate_type'] === 'NET' ? 1 : 0][] = $line;
	}
	$hasNet = count($sections[1]) > 0;
	$w = array(6, 36, 22, 8, 13, 15);
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%"><thead><tr style="font-weight:bold" align="center">'
		. '<td width="' . $w[0] . '%">Sr.No.</td><td width="' . $w[1] . '%">Particulars</td><td width="' . $w[2] . '%">PRODUCT MODEL IMAGE</td>'
		. '<td width="' . $w[3] . '%">Qty.</td><td width="' . $w[4] . '%">Rate (Rs.)</td><td width="' . $w[5] . '%">Amount (Rs.)</td></tr></thead>';
	$sr = 0;
	$qty = 0;
	$blank = '<td width="' . ($w[0] + $w[1] + $w[2] + $w[3] - 14) . '%" border="0"></td>';
	foreach ($sections as $s => $lines) {
		foreach ($lines as $line) {
			$p = $line['product'];
			$sr++;
			$qty += (int) $line['quantity'];
			$wood = !empty($line['variant']) ? '<br><small>(' . pi_pdf_e(ucfirst(strtolower($line['variant']))) . ' wood)</small>' : '';
			$h .= '<tr nobr="true"><td width="' . $w[0] . '%" align="center">' . $sr . '</td>'
				. '<td width="' . $w[1] . '%"><b>' . pi_pdf_e($p['name']) . '</b><br>' . pi_pdf_e($p['description'])
				. '<br>Size :- ' . pi_pdf_e($p['size']) . '<br>Color :- ' . pi_pdf_e($p['color']) . '</td>'
				. '<td width="' . $w[2] . '%" align="center">' . $img(pi_pdf_product_image($p['thumb'], $cacheDir), 110) . '</td>'
				. '<td width="' . $w[3] . '%" align="center">' . (int) $line['quantity'] . '</td>'
				. '<td width="' . $w[4] . '%" align="right">' . pi_pdf_amount($line['rate']) . $wood . '</td>'
				. '<td width="' . $w[5] . '%" align="right">' . pi_pdf_amount($line['amount']) . '</td></tr>';
		}
		$row = function ($label, $mid, $amount) use ($w, $y, $blank) {
			return '<tr nobr="true">' . $blank . '<td width="14%"' . $y . ' align="center">' . $label . '</td>'
				. '<td width="' . $w[4] . '%"' . $y . ' align="center">' . $mid . '</td><td width="' . $w[5] . '%"' . $y . ' align="right">' . $amount . '</td></tr>';
		};
		if ($s === 0 && count($lines) && ($hasNet || $pi['discount_percent'] > 0)) {
			$dpBasic = $pi['subtotal'] - (isset($pi['net_subtotal']) ? $pi['net_subtotal'] : 0);
			$h .= $row('BASIC', '', pi_pdf_amount($dpBasic));
			if ($pi['discount_percent'] > 0) {
				$h .= $row('DISCOUNT %', pi_pdf_e(rtrim(rtrim(number_format($pi['discount_percent'], 2), '0'), '.')) . '%', pi_pdf_amount($pi['discount']));
			}
			$h .= $row('TOTAL', '', pi_pdf_amount($dpBasic - $pi['discount']));
		} elseif ($s === 1 && $hasNet) {
			$h .= $row('TOTAL', '', pi_pdf_amount($pi['net_subtotal']));
		}
	}
	$h .= '</table><br>';

	// Summary, bank and final amount.
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%" nobr="true">';
	foreach ($pi['charges'] as $ch) {
		$h .= '<tr><td width="48%" border="0"></td><td width="37%">' . pi_pdf_e($ch['label']) . '</td><td width="15%" align="right">' . pi_pdf_amount($ch['amount']) . '</td></tr>';
	}
	$h .= '<tr><td width="48%"></td><td width="11%"' . $y . '>TOTAL Qty</td><td width="7%"' . $y . ' align="center">' . $qty . '</td>'
		. '<td width="19%"' . $y . '>TOTAL AMOUNT</td><td width="15%"' . $y . ' align="right">' . pi_pdf_amount($pi['basic']) . '</td></tr>'
		. '<tr><td width="48%">' . pi_pdf_e($bank['heading']) . '</td><td width="29%" align="center">' . pi_pdf_e($pi['tax_label']) . '</td>'
		. '<td width="8%" align="center">' . pi_pdf_e($tax) . '%</td><td width="15%" align="right">' . pi_pdf_amount($pi['tax']) . '</td></tr>'
		. '<tr><td width="20%">Bank : ' . pi_pdf_e($bank['bank']) . '</td><td width="28%">Branch : ' . pi_pdf_e($bank['branch']) . '</td>'
		. '<td width="37%"' . $y . ' rowspan="2" align="center"><b>FINAL AMOUNT( INCLUDING ' . pi_pdf_e($tax) . '% ' . pi_pdf_e($pi['tax_label']) . ' )</b></td>'
		. '<td width="15%"' . $y . ' rowspan="2" align="right"><b>' . pi_pdf_amount($pi['total']) . '</b></td></tr>'
		. '<tr><td width="20%">A/C No. : ' . pi_pdf_e($bank['account_no']) . '</td><td width="28%">IFSC Code : ' . pi_pdf_e($bank['ifsc']) . '</td></tr>'
		. '</table><br>';

	// Terms, signature and notes.
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%"><tr><td colspan="2" align="center">' . pi_pdf_e($terms['heading']) . '</td></tr>';
	foreach ($terms['lines'] as $i => $l) {
		$h .= '<tr nobr="true"><td width="6%" align="right">' . ($i + 1) . '</td><td width="94%">' . pi_pdf_e($l) . '</td></tr>';
	}
	$h .= '</table><br><table border="1" cellpadding="3" cellspacing="0" width="100%" nobr="true">'
		. '<tr><td width="6%"></td><td width="94%">' . ($sign['for'] !== '' ? 'For,' : '') . '</td></tr>'
		. '<tr><td width="6%"></td><td width="94%">' . pi_pdf_e($sign['for']) . '</td></tr>';
	if ($sign['name'] !== '' || $sign['title'] !== '') {
		$h .= '<tr><td width="6%"></td><td width="94%"><br><br>' . pi_pdf_e($sign['name']) . '<br>' . pi_pdf_e($sign['title']) . '</td></tr>';
	}
	$h .= '</table>';
	$notes = array();
	if (!empty($pi['round_off'])) {
		$notes[] = 'Round off: ' . sprintf('%+.2f', $pi['round_off']);
	}
	if (!empty($pi['discount_note'])) {
		$notes[] = $pi['discount_note'];
	}
	if ($notes) {
		$h .= '<p style="font-size:8pt">' . pi_pdf_e(implode('; ', $notes)) . '</p>';
	}
	return $h;
}

/** Send the PI as a PDF download (file name from the PI number). */
function pi_pdf_send($snap, $cacheDir)
{
	if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
		define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
	}
	if (!class_exists('TCPDF')) {
		require_once dirname(__DIR__) . '/TCPDF/tcpdf.php';
	}
	$pdf = new PiPdfDocument('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->piNumber = $snap['number'];
	$pdf->SetCreator('Crystal PMS');
	$pdf->SetAuthor((string) $snap['templates']['company']['content']['name']);
	$pdf->SetTitle('Proforma Invoice ' . $snap['number']);
	$pdf->setPrintHeader(false);
	$pdf->SetMargins(10, 10, 10);
	$pdf->SetAutoPageBreak(true, 14);
	$pdf->SetFont('dejavusans', '', 8);
	$pdf->AddPage();
	$pdf->writeHTML(pi_pdf_html($snap, $cacheDir), true, false, true, false, '');
	$name = preg_replace('/[^A-Za-z0-9._-]+/', '-', 'PI-' . $snap['number'] . '-' . $snap['pi']['customer']['name']);
	$pdf->Output(trim($name, '-') . '.pdf', 'D');
}

if (!class_exists('PiPdfDocument')) {
	if (!class_exists('TCPDF')) {
		if (!defined('K_TCPDF_THROW_EXCEPTION_ERROR')) {
			define('K_TCPDF_THROW_EXCEPTION_ERROR', true);
		}
		require_once dirname(__DIR__) . '/TCPDF/tcpdf.php';
	}
	/** Footer: PI number and page x of y on every page. */
	class PiPdfDocument extends TCPDF
	{
		public $piNumber = '';
		public $footerLabel = 'Proforma Invoice';

		public function Footer()
		{
			$this->SetY(-10);
			$this->SetFont('dejavusans', '', 7);
			$this->Cell(0, 5, $this->footerLabel . ' ' . $this->piNumber . '  |  Page ' . $this->getAliasNumPage() . ' of ' . $this->getAliasNbPages(), 0, 0, 'R');
		}
	}
}
