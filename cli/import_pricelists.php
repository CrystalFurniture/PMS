<?php
/**
 * Import a printed price list (.xlsx, any size; photos are ignored) into a price list for the AI Assistant.
 * Shows a preview by default; writes only with --apply.
 *
 *   php cli/import_pricelists.php --file="C:\Quotation\pricelists\Master.xlsx" --list=MASTER --name="Master price list" --kind=master --effective=2026-05-26
 *   php cli/import_pricelists.php --file="...\Office.xlsx" --list=MASTER --kind=master --merge          (adds/overrides on top of Master)
 *   php cli/import_pricelists.php --file="...\Bihar.xlsx" --list=BR --name="Bihar" --kind=state --covers=10
 *   php cli/import_pricelists.php --file="...\South.xlsx" --list=SOUTH --name="South Region" --kind=region --covers=29,33,32,37,36
 *   php cli/import_pricelists.php --file="...\Master.xlsx" --list=MASTER --series-only --apply   (only the series names, e.g. CS-157 = GOTHIC)
 *   ... add --apply to save. Refuses the live 'pms' database unless --i-approve-live.
 *
 * kind master: a default list, checked by the AI Assistant without being mentioned, before the software's own prices;
 *   several default lists are checked in --priority order (lower first): Master price list 1, Office furniture 2. state / region: chosen per PI
 * ("use Bihar prices"); covers = GST state codes of the states it serves.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("This script may only be run from the command line.\n");
}
ini_set('memory_limit', '1024M');
$o = getopt('', array('file:', 'list:', 'name:', 'kind:', 'covers:', 'effective:', 'priority:', 'merge', 'apply', 'series-only', 'i-approve-live'));
if (empty($o['file']) || empty($o['list']) || !preg_match('/^[A-Z0-9_-]{2,10}$/', $o['list'])) {
	fwrite(STDERR, "Usage: php cli/import_pricelists.php --file=PATH.xlsx --list=CODE [--name=NAME] [--kind=master|state|region] [--covers=27,24] [--effective=YYYY-MM-DD] [--merge] [--apply]\n");
	exit(1);
}
$kind = isset($o['kind']) ? $o['kind'] : 'state';
if (!in_array($kind, array('master', 'state', 'region'), true)) {
	fwrite(STDERR, "--kind must be master, state or region\n");
	exit(1);
}
$covers = isset($o['covers']) ? preg_replace('/\s+/', '', $o['covers']) : '';
if ($covers !== '' && !preg_match('/^\d{2}(,\d{2})*$/', $covers)) {
	fwrite(STDERR, "--covers must be GST state codes like 10 or 29,33\n");
	exit(1);
}
$effective = isset($o['effective']) ? $o['effective'] : null;
if ($effective !== null && !preg_match('/^\d{4}-\d{2}-\d{2}$/', $effective)) {
	fwrite(STDERR, "--effective must be YYYY-MM-DD\n");
	exit(1);
}
$_SESSION = array();
chdir(__DIR__ . '/..');
ob_start(); include 'DB/conn.php'; ob_end_clean();
require 'includes/quotation_lib.php'; require 'includes/ai_provider.php'; require 'includes/ai_import_lib.php'; require 'includes/pricelist_import_lib.php';
if (strtolower($database) === 'pms' && !isset($o['i-approve-live'])) {
	fwrite(STDERR, "Refusing to change the live 'pms' database without --i-approve-live.\n");
	exit(1);
}
$file = $o['file'];
if (!is_file($file)) {
	fwrite(STDERR, "File not found: $file\n");
	exit(1);
}
if (isset($o['series-only'])) {
	list($series, $error) = pl_extract_series($file);
	if ($error !== null) {
		fwrite(STDERR, $error . "\n");
		exit(1);
	}
	echo count($series) . " series names in " . basename($file) . ": " . implode(', ', array_map(function ($p, $n) { return "$p = $n"; }, array_keys($series), $series)) . "\n";
	$pairs = pl_extract_dining_pairs($file);
	echo count($pairs) . " dining table -> chair pairs: " . implode(', ', array_map(function ($t, $c) { return "$t -> $c"; }, array_keys($pairs), $pairs)) . "\n";
	if (isset($o['apply'])) {
		echo "Saved: " . pl_save_series($connect, $series, basename($file), 'import: ' . get_current_user()) . " series names (names added by hand are kept).\n";
		echo "Saved: " . pl_save_companions($connect, $pairs, basename($file)) . " table -> chair pairs.\n";
	} else {
		echo "Preview only. Add --apply to save.\n";
	}
	exit(0);
}
$started = microtime(true);
list($rows, $error) = pl_extract($file);
if ($error !== null) {
	fwrite(STDERR, $error . "\n");
	exit(1);
}
list($entries, $rep) = pl_plan($connect, $rows, basename($file));
list($series) = pl_extract_series($file);
$series = $series ?: array();

// Compare with the software's own prices (pricelist table) for the main category of the list.
$main = $kind === 'state' && isset($rep['types']['MRP']) && !isset($rep['types']['DP']) ? 'MRP' : 'DP';
$col = $main === 'MRP' ? 'mrp_price' : 'dp_price';
$soft = array();
$r = mysqli_query($connect, "SELECT p_prodId, COUNT(*) n, MAX($col) v FROM pricelist GROUP BY p_prodId");
while ($x = mysqli_fetch_assoc($r)) { $soft[(int) $x['p_prodId']] = $x; }
$fills = $same = $diff = 0;
$products = array();
foreach ($entries as $x) {
	if (isset($products[$x['pid']])) { continue; }
	$products[$x['pid']] = true;
	$cur = isset($soft[$x['pid']]) ? (float) $soft[$x['pid']]['v'] : 0;
	if ($cur <= 0) { $fills++; } elseif ($x['variant'] === '' && $x[$main] !== null) { if (abs($cur - $x[$main]) < 1) { $same++; } else { $diff++; } }
}
printf("Database: %s | file: %s (%.1f MB, read in %.1f s)\n", $database, basename($file), filesize($file) / 1048576, microtime(true) - $started);
printf("List %s \"%s\" (%s%s)%s\n", $o['list'], isset($o['name']) ? $o['name'] : $o['list'], $kind, $covers ? ', states ' . $covers : '', isset($o['merge']) ? ' - merge into existing list' : ' - replaces the list');
printf("  priced rows %d | matched %d (of which %d shared by several products) | not matched %d | matched by name prefix %d\n", $rep['rows'], $rep['matched'], $rep['shared_name'], count($rep['unmatched']), $rep['by_prefix']);
echo "  rates by category: " . json_encode($rep['types']) . "\n";
printf("  product rates stored: %d (incl. teak/mango variants)\n", count($entries));
printf("  vs software %s: %d products at Rs 0 in the software get a list rate | %d same | %d different (the list is used first by the assistant)\n", $main, $fills, $same, $diff);
if ($rep['conflicts']) { echo "  conflicts inside the file (" . count($rep['conflicts']) . "): " . implode(' | ', array_slice($rep['conflicts'], 0, 5)) . "\n"; }
if ($series) {
	$shown = array();
	foreach (array_slice($series, 0, 8, true) as $p => $n) { $shown[] = "$p = $n"; }
	echo "  series names (" . count($series) . "): " . implode(', ', $shown) . (count($series) > 8 ? ', ...' : '') . "\n";
}
if ($rep['unmatched']) { echo "  not matched (first 12 of " . count($rep['unmatched']) . "): " . implode(' | ', array_slice($rep['unmatched'], 0, 12)) . "\n"; }
if (!isset($o['apply'])) {
	echo "Preview only. Add --apply to save.\n";
	exit(0);
}
$err = pl_save($connect, array('code' => $o['list'], 'name' => isset($o['name']) ? $o['name'] : $o['list'], 'kind' => $kind, 'covers' => $covers, 'effective' => $effective,
	'priority' => isset($o['priority']) ? (int) $o['priority'] : null),
	$entries, isset($o['merge']), 'import: ' . get_current_user(), basename($file));
if ($err !== null) {
	fwrite(STDERR, $err . "\n");
	exit(1);
}
$v = mysqli_fetch_assoc(mysqli_query($connect, "SELECT version, rows_loaded FROM price_states WHERE code = '" . mysqli_real_escape_string($connect, $o['list']) . "'"));
echo "Saved: list {$o['list']} version {$v['version']}, {$v['rows_loaded']} product rates.\n";
if ($series) {
	echo "Series names saved: " . pl_save_series($connect, $series, basename($file), 'import: ' . get_current_user()) . " (names added by hand are kept).\n";
}
$pairs = pl_extract_dining_pairs($file);
if ($pairs) {
	echo "Dining table -> chair pairs saved: " . pl_save_companions($connect, $pairs, basename($file)) . ".\n";
}
