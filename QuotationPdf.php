<?php
/**
 * Download a saved DP or MRP quotation (any revision) as a PDF, with the same content as its detail page.
 * Owner only, like the detail page. QuotationPdf.php?id=<q_id>&type=DP|MRP
 */
include_once('DB/conn.php');
include_once('includes/quotation_lib.php');
include_once('includes/ai_provider.php');
include_once('includes/ai_import_lib.php');
error_reporting(0);
@session_start();
qt_require_login_page();

$type = isset($_GET['type']) && $_GET['type'] === 'MRP' ? 'MRP' : 'DP';
list($row, $error) = qt_fetch_owned_quotation($connect, isset($_GET['id']) ? $_GET['id'] : '', $type);
$items = $row ? qt_decode_items($row['q_items']) : null;
if ($error !== null || $items === null) {
	qt_flash_set($error !== null ? $error : 'The saved lines of this quotation cannot be read.');
	qt_redirect('ViewQuotation.php');
}
require_once 'includes/quotation_pdf.php';
list($ai) = qt_ai_settings();
@set_time_limit(300);
$rep = array('name' => isset($_SESSION['fullName']) ? (string) $_SESSION['fullName'] : '', 'phone' => isset($_SESSION['phone']) ? (string) $_SESSION['phone'] : '',
	'email' => isset($_SESSION['email']) ? (string) $_SESSION['email'] : '');
try {
	qt_pdf_send($row, $items, $rep, $ai['storage_dir'] !== '' ? $ai['storage_dir'] . '/cache/pdf-images' : '');
} catch (Exception $e) {
	qt_flash_set('The PDF could not be made. Use "Export to Excel" meanwhile.');
	qt_redirect(($type === 'MRP' ? 'DetailQuotationMRP.php' : 'DetailQuotationDp.php') . '?id=' . (int) $row['q_id']);
}
