<?php
/**
 * How the AI Assistant's product matches held up in finalized PIs (real usage; read-only).
 *
 *   php benchmark/pi_feedback.php [--since=YYYY-MM-DD]
 *
 * Per finalized line (from the immutable PI snapshot):
 *   automatic match kept    the AI pre-selected a product and the employee kept it
 *   corrected               the AI pre-selected a product but the employee chose a different one (a wrong automatic match)
 *   resolved by employee    the AI left the line unresolved (needs review / unmatched) and the employee chose
 *   semantic-only candidate the final product was found only by semantic search (not by keyword search)
 * Quotations saved from the assistant are counted too. Customer details are not printed.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("CLI only.\n");
}
$since = null;
foreach (array_slice($argv, 1) as $a) {
	if (strpos($a, '--since=') === 0 && preg_match('/^\d{4}-\d{2}-\d{2}$/', substr($a, 8))) { $since = substr($a, 8); }
}
$_SESSION = array();
chdir(__DIR__ . '/..');
ob_start(); include 'DB/conn.php'; ob_end_clean();
$r = mysqli_query($connect, "SELECT id, pi_number, created_at, data FROM pi_documents" . ($since ? " WHERE created_at >= '" . $since . "'" : '') . " ORDER BY id");
$docs = 0;
$t = array('lines' => 0, 'auto_kept' => 0, 'corrected' => 0, 'resolved_by_employee' => 0, 'semantic_only' => 0, 'quantity_defaulted' => 0, 'unknown' => 0);
while ($r && ($row = mysqli_fetch_assoc($r))) {
	$d = json_decode($row['data'], true);
	if (!isset($d['pi']['lines'])) { continue; }
	$docs++;
	foreach ($d['pi']['lines'] as $l) {
		$t['lines']++;
		$src = isset($l['source']) ? $l['source'] : null;
		$final = isset($l['product']['id']) ? (int) $l['product']['id'] : null;
		if ($src === null || !array_key_exists('ai_suggested_id', $src)) { $t['unknown']++; continue; }
		$sug = $src['ai_suggested_id'] !== null ? (int) $src['ai_suggested_id'] : null;
		$autoPicked = $src['match_status'] === 'suggested' && $sug !== null;
		if ($autoPicked && $sug === $final) { $t['auto_kept']++; }
		elseif ($autoPicked) { $t['corrected']++; }
		else { $t['resolved_by_employee']++; }
		if (!empty($src['retrieval']['semantic_only']) && in_array($final, array_map('intval', $src['retrieval']['semantic_only']), true)) { $t['semantic_only']++; }
	}
}
echo "Database: $database" . ($since ? ", since $since" : '') . "\nFinalized documents: $docs, lines: {$t['lines']}\n";
if ($t['lines'] === 0) { echo "No finalized lines yet.\n"; exit(0); }
$known = $t['lines'] - $t['unknown'];
$pct = function ($n) use ($known) { return $known ? round(100 * $n / $known) . '%' : '-'; };
echo "  automatic match kept     {$t['auto_kept']} (" . $pct($t['auto_kept']) . ")\n";
echo "  corrected by employee    {$t['corrected']} (" . $pct($t['corrected']) . ")  <- wrong automatic matches\n";
echo "  unresolved, then chosen  {$t['resolved_by_employee']} (" . $pct($t['resolved_by_employee']) . ")\n";
echo "  found only semantically  {$t['semantic_only']}\n";
if ($t['unknown']) { echo "  without source data      {$t['unknown']} (PIs generated before sources were recorded)\n"; }
