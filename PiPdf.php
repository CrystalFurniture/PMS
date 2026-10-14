<?php
/**
 * Download a Proforma Invoice as a PDF (same layout as PiView.php), built from the snapshot saved at approval.
 * Owner only, like the PI page.
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
require_once 'includes/pi_pdf.php';
list($ai) = qt_ai_settings();
@set_time_limit(300);
try {
	pi_pdf_send($snap, $ai['storage_dir'] !== '' ? $ai['storage_dir'] . '/cache/pdf-images' : '');
} catch (Exception $e) {
	http_response_code(500);
	header('Content-Type: text/html; charset=utf-8');
	echo '<!doctype html><meta charset="utf-8"><title>PDF not available</title><p>The PDF could not be made. Use "Export to Excel" or print the page meanwhile.</p>'
		. '<p><a href="PiView.php?id=' . (int) $doc['id'] . '">Back to the PI</a></p>';
}
