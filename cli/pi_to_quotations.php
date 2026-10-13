<?php
/**
 * Add PIs approved before PIs were saved to tbl_quotation, so they appear under Saved quotations.
 * New PIs are added automatically when they are approved. Shows what it would do; writes only with --apply.
 *
 *   php cli/pi_to_quotations.php            (preview)
 *   php cli/pi_to_quotations.php --apply
 * Refuses the live 'pms' database unless --i-approve-live.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("This script may only be run from the command line.\n");
}
$o = getopt('', array('apply', 'i-approve-live'));
$_SESSION = array();
chdir(__DIR__ . '/..');
ob_start(); include 'DB/conn.php'; ob_end_clean();
require 'includes/quotation_lib.php'; require 'includes/ai_provider.php'; require 'includes/ai_import_lib.php'; require 'includes/pi_lib.php';
if (strtolower($database) === 'pms' && !isset($o['i-approve-live'])) {
	fwrite(STDERR, "Refusing to change the live 'pms' database without --i-approve-live.\n");
	exit(1);
}
if (!(($c = mysqli_query($connect, "SHOW COLUMNS FROM pi_documents LIKE 'quotation_id'")) && mysqli_num_rows($c) > 0)) {
	fwrite(STDERR, "Run php cli/migrate_pi.php --database=$database first.\n");
	exit(1);
}
// Rows added before PIs took quotation numbers carry the PI number: give them the next quotation numbers
// (only unrevised rows; a revised row is left as it is).
$old = mysqli_query($connect, "SELECT d.id, d.pi_number, q.q_id FROM pi_documents d JOIN tbl_quotation q ON q.q_id = d.quotation_id
	WHERE q.q_num = d.pi_number AND q.q_revNum = 0 ORDER BY d.id");
while ($x = mysqli_fetch_assoc($old)) {
	echo (isset($o['apply']) ? 'renumbering' : 'would renumber') . " {$x['pi_number']} (quotation row {$x['q_id']}) into the quotation series\n";
	if (isset($o['apply'])) {
		mysqli_query($connect, "DELETE FROM tbl_quotation WHERE q_id = " . (int) $x['q_id']);
		mysqli_query($connect, "UPDATE pi_documents SET quotation_id = NULL WHERE id = " . (int) $x['id']);
	}
}
$r = mysqli_query($connect, "SELECT id, pi_number, username FROM pi_documents WHERE quotation_id IS NULL AND pi_number NOT LIKE 'QUOTATION %' ORDER BY id");
$n = 0;
while ($row = mysqli_fetch_assoc($r)) {
	$n++;
	if (!isset($o['apply'])) {
		echo "would add {$row['pi_number']} (made by {$row['username']})\n";
		continue;
	}
	list($qid, $err) = pi_save_to_quotations($connect, $row['id']);
	echo $err !== null ? "{$row['pi_number']}: $err\n" : "added {$row['pi_number']} as quotation row $qid\n";
}
echo "Database $database: " . (isset($o['apply']) ? "$n PI(s) processed." : "$n PI(s) not yet in tbl_quotation. Preview only. Add --apply to save.") . "\n";
