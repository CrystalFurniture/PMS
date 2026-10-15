<?php
/**
 * A saved DP/MRP quotation as a PDF (A4, TCPDF), with the same content as its detail page
 * (DetailQuotationDp.php / DetailQuotationMRP.php): header, client and quotation number, items with photos,
 * totals with discount and 18% GST, bank details, the printed terms and the signature.
 * Uses the PDF helpers of includes/pi_pdf.php (photos, logos, footer).
 */
require_once __DIR__ . '/pi_pdf.php';

/** Catalogue text stored with HTML entities ("6 x 6&frac12;", "&amp;") as plain text. */
function qt_pdf_text($s)
{
	return html_entity_decode(html_entity_decode((string) $s, ENT_QUOTES, 'UTF-8'), ENT_QUOTES, 'UTF-8');
}

/** The terms printed on every quotation (as on the detail pages). */
function qt_pdf_terms()
{
	return array(
		'Estimated delivery time from the date of confirmation of order, PO & Advance and Technical appoval ( Color & Etc. ) is 4- 6 Weeks.',
		'Once the order is proceed, changes in specification, color etc. will not be possible.',
		'Any extra work not mentioned in qoutation will be charged extra.',
		'In case of any specific exemptions from taxes, duties, the buyer shall provide the necessary documents.',
		'Payment Condition :- 50% advance at the time of order confirmation and remaining 50% before delivery.',
		'Transportation & Material Handling ( Unloading ) Charges Extra.',
		'Installation cost Extra. ( Lodging & Boarding Provided By You ).',
		"Rates are applicable only on crystal's standard colours and shades .",
		'Above Quotation is valid for 4 weeks.',
		'3 years warranty for any manufacturing defect, *Conditions apply.',
	);
}

/** Quotation as TCPDF-friendly HTML. $rep = representative array(name, phone, email) as shown on the page. */
function qt_pdf_html($row, $items, $rep, $cacheDir)
{
	$y = ' bgcolor="#FFFF00"';
	$img = function ($path, $w) {
		return $path !== null ? '<img src="' . pi_pdf_e($path) . '" width="' . (int) $w . '">' : '';
	};
	$h = '<table border="1" cellpadding="4" cellspacing="0" width="100%">'
		. '<tr><td width="20%" align="center">' . $img(pi_pdf_logo('logo_1'), 90) . '</td>'
		. '<td width="60%" align="center"><b style="font-size:11pt">CRYSTAL FURNITECH PRIVATE LIMITED</b><br>'
		. 'Office : Umiya Chambers, Darodkar Square, Central Avenue, Nagpur-440032.<br>'
		. 'Phone: 0712-2766405, 2770867, Fax:-6611795 M :- 9922919052<br>'
		. 'E-mail: rakesh@crystalfurniture.in  Web : www.crystalfurnitech.com</td>'
		. '<td width="20%" align="center">' . $img(pi_pdf_logo('3C'), 90) . '</td></tr></table>';

	$revised = $row['q_revDate'] !== '';
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%">'
		. '<tr><td width="40%" rowspan="5">Client Name &amp; Address :- <b>' . pi_pdf_e(qt_pdf_text($row['q_name'])) . '</b></td>'
		. '<td width="18%">QUOTATION NO.</td><td width="18%">' . pi_pdf_e($row['q_num']) . '</td><td width="10%">REV.NO</td>'
		. '<td width="14%">' . ($revised ? 'Cry/21-22/REV-' . (int) $row['q_revNum'] : '') . '</td></tr>'
		. '<tr><td>DATE</td><td>' . pi_pdf_e($row['q_date']) . '</td><td>DATE</td><td>' . ($revised ? pi_pdf_e($row['q_revDate']) : '') . '</td></tr>'
		. '<tr><td>GST NO</td><td>27AAICC6954C1ZL</td><td>HSN Code</td><td></td></tr>'
		. '<tr><td>Representative :-</td><td>Name</td><td colspan="2">E-Mail</td></tr>'
		. '<tr><td></td><td>' . pi_pdf_e($rep['name']) . ($rep['phone'] !== '' ? ' (' . pi_pdf_e($rep['phone']) . ')' : '') . '</td>'
		. '<td colspan="2">' . pi_pdf_e($rep['email']) . '</td></tr></table><br>';

	$w = array(6, 36, 22, 8, 13, 15);
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%"><thead><tr style="font-weight:bold" align="center">'
		. '<td width="' . $w[0] . '%">Sr.No.</td><td width="' . $w[1] . '%">Particulars</td><td width="' . $w[2] . '%">PRODUCT MODEL IMAGE</td>'
		. '<td width="' . $w[3] . '%">Qty.</td><td width="' . $w[4] . '%">Rate (Rs.)</td><td width="' . $w[5] . '%">Amount (Rs.)</td></tr></thead>';
	$i = 0;
	$total = 0.0;
	$qty = 0;
	foreach ($items as $p) {
		$i++;
		$amount = (float) $p['price'] * (float) $p['quantity'];
		$total += $amount;
		$qty += (int) $p['quantity'];
		$desc = trim(html_entity_decode(strip_tags(str_replace(array('<br>', '<br/>', '<br />', '</p>'), "\n", (string) $p['description'])), ENT_QUOTES, 'UTF-8'));
		$h .= '<tr nobr="true"><td width="' . $w[0] . '%" align="center">' . $i . '</td>'
			. '<td width="' . $w[1] . '%"><b>' . pi_pdf_e(qt_pdf_text($p['name'])) . '</b><br>' . nl2br(pi_pdf_e($desc))
			. '<br>Size :- ' . pi_pdf_e(qt_pdf_text(isset($p['size']) ? $p['size'] : '')) . '<br>Color :- ' . pi_pdf_e(qt_pdf_text(isset($p['color']) ? $p['color'] : '')) . '</td>'
			. '<td width="' . $w[2] . '%" align="center">' . $img(isset($p['image']) && $p['image'] !== '' ? pi_pdf_product_image($p['image'], $cacheDir) : null, 110) . '</td>'
			. '<td width="' . $w[3] . '%" align="center">' . pi_pdf_e($p['quantity']) . ' Nos.</td>'
			. '<td width="' . $w[4] . '%" align="right">' . pi_pdf_e($p['price']) . '</td>'
			. '<td width="' . $w[5] . '%" align="right">' . pi_pdf_e(number_format($amount, 2, '.', '')) . '</td></tr>';
	}
	$h .= '</table><br>';

	$discountPct = $row['q_discount'] !== '' ? (float) $row['q_discount'] : null;
	$discount = $discountPct !== null ? $discountPct * $total / 100 : 0.0;
	$basic = $total - $discount;
	$gst = 0.18 * $basic;
	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%" nobr="true">'
		. '<tr><td width="48%"></td><td width="11%"' . $y . '>TOTAL Qty</td><td width="7%"' . $y . ' align="center">' . $qty . '</td>'
		. '<td width="19%"' . $y . '>TOTAL AMOUNT</td><td width="15%"' . $y . ' align="right">' . number_format($total, 2) . '</td></tr>'
		. '<tr><td width="48%">Bank Details ( applicable in case of direct deposite )</td>';
	if ($discountPct !== null) {
		$h .= '<td width="18%">Discount In %</td><td width="19%" align="center">' . pi_pdf_e($row['q_discount']) . ' %</td>'
			. '<td width="15%" align="right">' . number_format($discount, 2) . '</td></tr>'
			. '<tr><td width="20%">Bank : Axis Bank Ltd.</td><td width="28%">Branch : Lakkadganj, Nagpur</td>'
			. '<td width="37%"' . $y . '>Basic Amount</td><td width="15%"' . $y . ' align="right">' . number_format($basic, 2) . '</td></tr>'
			. '<tr><td width="20%">A/C No. : 921030022445688</td><td width="28%">IFSC Code : UTIB0000330</td>'
			. '<td width="18%">GST</td><td width="19%" align="center">18%</td><td width="15%" align="right">' . number_format($gst, 2) . '</td></tr>'
			. '<tr><td width="48%"></td><td width="37%"' . $y . '><b>FINAL AMOUNT</b></td><td width="15%"' . $y . ' align="right"><b>'
			. number_format($gst + $total - $discount, 2) . '</b></td></tr>';
	} else {
		$h .= '<td width="18%">GST</td><td width="19%" align="center">18%</td><td width="15%" align="right">' . number_format($gst, 2) . '</td></tr>'
			. '<tr><td width="20%">Bank : Axis Bank Ltd.</td><td width="28%">Branch : Lakkadganj, Nagpur</td><td width="52%" rowspan="2"></td></tr>'
			. '<tr><td width="20%">A/C No. : 921030022445688</td><td width="28%">IFSC Code : UTIB0000330</td></tr>'
			. '<tr><td width="48%"></td><td width="37%"' . $y . '><b>FINAL AMOUNT( INCLUDING 18% GST )</b></td><td width="15%"' . $y . ' align="right"><b>'
			. number_format($gst + $total, 2) . '</b></td></tr>';
	}
	$h .= '</table><br>';

	$h .= '<table border="1" cellpadding="3" cellspacing="0" width="100%"><tr><td colspan="2" align="center">Terms and conditions as applicable to this Quotation</td></tr>';
	foreach (qt_pdf_terms() as $n => $l) {
		$h .= '<tr nobr="true"><td width="6%" align="right">' . ($n + 1) . '</td><td width="94%">' . pi_pdf_e($l) . '</td></tr>';
	}
	$h .= '</table><br><table border="1" cellpadding="3" cellspacing="0" width="100%" nobr="true">'
		. '<tr><td width="6%"></td><td width="94%">For,</td></tr>'
		. '<tr><td width="6%"></td><td width="94%">CRYSTAL FURNITECH PRIVATE LIMITED</td></tr>'
		. '<tr><td width="6%"></td><td width="94%"><br><br>Rakesh Gupta</td></tr>'
		. '<tr><td width="6%"></td><td width="94%">(Authorized Signatory)</td></tr></table>';
	return $h;
}

/** Send the quotation as a PDF download, e.g. Cry-26-27-Q-2725-R2-Client.pdf. */
function qt_pdf_send($row, $items, $rep, $cacheDir)
{
	list($pdf, $file) = qt_pdf_build($row, $items, $rep, $cacheDir);
	$pdf->Output($file, 'D');
}

/** The same PDF as bytes, for sending by email/WhatsApp. Returns array(bytes, file name). */
function qt_pdf_bytes($row, $items, $rep, $cacheDir)
{
	list($pdf, $file) = qt_pdf_build($row, $items, $rep, $cacheDir);
	return array($pdf->Output($file, 'S'), $file);
}

/** Returns array(TCPDF document, file name). */
function qt_pdf_build($row, $items, $rep, $cacheDir)
{
	$label = 'Quotation ' . $row['q_num'] . ((int) $row['q_revNum'] > 0 ? ' R' . (int) $row['q_revNum'] : '');
	$pdf = new PiPdfDocument('P', 'mm', 'A4', true, 'UTF-8', false);
	$pdf->piNumber = $row['q_num'] . ((int) $row['q_revNum'] > 0 ? ' R' . (int) $row['q_revNum'] : '');
	$pdf->footerLabel = 'Quotation';
	$pdf->SetCreator('Crystal PMS');
	$pdf->SetAuthor('CRYSTAL FURNITECH PRIVATE LIMITED');
	$pdf->SetTitle($label);
	$pdf->setPrintHeader(false);
	$pdf->SetMargins(10, 10, 10);
	$pdf->SetAutoPageBreak(true, 14);
	$pdf->SetFont('dejavusans', '', 8);
	$pdf->AddPage();
	$pdf->writeHTML(qt_pdf_html($row, $items, $rep, $cacheDir), true, false, true, false, '');
	$name = preg_replace('/[^A-Za-z0-9._-]+/', '-', $row['q_num'] . ((int) $row['q_revNum'] > 0 ? '-R' . (int) $row['q_revNum'] : '') . '-' . $row['q_name']);
	return array($pdf, trim(substr($name, 0, 120), '-') . '.pdf');
}
