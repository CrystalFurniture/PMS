<?php
/**
 * CLI: delete AI-assistant drafts and uploaded images older than the configured
 * retention (retention_hours in the AI config). Uploads are also deleted as soon
 * as their items are added to a cart or the import is discarded; this catches
 * abandoned imports. Schedule it hourly (Windows Task Scheduler or cron):
 *
 *   php cli/ai_cleanup.php
 *
 * The config file is found through the PMS_AI_CONFIG environment variable
 * (see docs/ai-quotation-assistant.md).
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("This script may only be run from the command line.\n");
}
$_SESSION = array();
require_once __DIR__ . '/../includes/quotation_lib.php';
require_once __DIR__ . '/../includes/ai_provider.php';
require_once __DIR__ . '/../includes/ai_import_lib.php';

list($ai, $error) = qt_ai_settings();
if ($ai['storage_dir'] === '' || !is_dir($ai['storage_dir'] . '/drafts')) {
	fwrite(STDERR, "AI storage folder not found. Set PMS_AI_CONFIG to the AI config file.\n");
	exit(1);
}
$removed = qt_ai_cleanup($ai);
qt_ai_log($ai, 'cleanup', array('removed' => $removed));
echo "Removed $removed expired import(s).\n";

// AI Assistant conversations: keep each user's latest conversations_kept (default 20); finalized PIs are kept.
@include __DIR__ . '/../DB/conn.php';
if (isset($connect) && $connect && ($t = mysqli_query($connect, "SHOW TABLES LIKE 'ai_conversations'")) && mysqli_num_rows($t) > 0) {
	require_once __DIR__ . '/../includes/pi_lib.php';
	$deleted = 0;
	$users = mysqli_query($connect, "SELECT DISTINCT user_id FROM ai_conversations");
	while ($users && ($u = mysqli_fetch_row($users))) {
		$deleted += pi_conv_prune($connect, $ai['storage_dir'], (int) $u[0], $ai['conversations_kept']);
	}
	qt_ai_log($ai, 'conversations_pruned', array('deleted' => $deleted, 'kept_per_user' => (int) $ai['conversations_kept']));
	echo "Deleted $deleted old conversation(s); each user keeps the latest " . (int) $ai['conversations_kept'] . ".\n";
}
