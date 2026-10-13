<?php
/**
 * Update the semantic product index (embeds new/changed products, removes deleted ones) and print its status.
 *
 *   php cli/index_products.php            update, then show status
 *   php cli/index_products.php --status   status only (no API calls)
 *
 * Uses DB/conn.php and the AI config (PMS_AI_CONFIG, or <parent of web root>/pms_private/ai_config.php).
 * Needs embed_api_key in the config (Voyage AI); costs about 0.3M tokens for ~4,000 products on the first run.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("This script may only be run from the command line.\n");
}
if (empty($_SERVER['DOCUMENT_ROOT'])) {
	$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');   // C:/xampp/htdocs for C:/xampp/htdocs/Quotation/PMS
}
$_SESSION = array();
chdir(__DIR__ . '/..');
ob_start();
include 'DB/conn.php';
ob_end_clean();
require 'includes/quotation_lib.php';
require 'includes/ai_provider.php';
require 'includes/ai_import_lib.php';
require 'includes/retrieval_lib.php';
list($ai, $error) = qt_ai_settings();
if ($error !== null) {
	fwrite(STDERR, $error . "\n");
	exit(1);
}
echo "Database: $database\n";
if (!in_array('--status', $argv, true)) {
	if (!rt_enabled($ai)) {
		echo "Semantic search is off: set embed_api_key in the AI config to build the index.\n";
	} else {
		list($stats, $err) = rt_index_sync($ai, $connect, 100000);
		echo "Indexed {$stats['indexed']}, removed {$stats['deleted']}, still pending {$stats['pending']}, tokens {$stats['tokens']}" . ($err ? " - error: $err" : '') . "\n";
	}
}
$s = rt_index_status($ai, $connect);
echo "Index model {$s['model']}: {$s['products']} quotable products, {$s['fresh']} up to date, {$s['stale']} changed since indexing, "
	. "{$s['other_model']} from another model, {$s['missing']} not indexed, {$s['orphans']} for deleted products. Last update: " . ($s['synced_at'] ?: 'never') . "\n";
