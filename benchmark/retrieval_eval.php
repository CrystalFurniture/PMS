<?php
/**
 * Retrieval benchmark: how often the correct product is among the candidates shown to the model.
 *
 *   php benchmark/retrieval_eval.php benchmark/labels/retrieval_synthetic.csv [--modes=kw_v1,kw,hybrid] [--out=FILE.json]
 *
 * Modes:
 *   kw_v1   keyword scoring before the 12-Oct-2026 fixes (description weight 2, "unit" ignored, no type-coverage bonus)
 *   kw      current keyword scoring (exact code/name + keyword)
 *   hybrid  kw merged with semantic search (needs embed_api_key; paid embeddings calls, about 10 tokens per case)
 * Metrics per mode, separately for each "source" value (real / synthetic): recall@1, @5, @12, mean reciprocal
 * rank, and cases with no candidate at all. Read-only on the database. This measures candidate retrieval only;
 * wrong automatic matches, unresolved rows and employee corrections come from benchmark/run.php + score.php
 * (model in the loop) and from benchmark/pi_feedback.php (finalized PIs).
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("CLI only.\n");
}
$args = array_slice($argv, 1);
$file = null;
$modes = array('kw_v1', 'kw', 'hybrid');
$out = null;
foreach ($args as $a) {
	if (strpos($a, '--modes=') === 0) { $modes = explode(',', substr($a, 8)); }
	elseif (strpos($a, '--out=') === 0) { $out = substr($a, 6); }
	else { $file = $a; }
}
if ($file === null || !is_file($file)) {
	fwrite(STDERR, "Usage: php benchmark/retrieval_eval.php LABELS.csv [--modes=kw_v1,kw,hybrid] [--out=FILE.json]\n");
	exit(1);
}
if (empty($_SERVER['DOCUMENT_ROOT'])) {
	$_SERVER['DOCUMENT_ROOT'] = realpath(__DIR__ . '/../../..');
}
$_SESSION = array();
$file = realpath($file);
chdir(__DIR__ . '/..');
ob_start(); include 'DB/conn.php'; ob_end_clean();
require 'includes/quotation_lib.php'; require 'includes/ai_provider.php'; require 'includes/ai_import_lib.php'; require 'includes/retrieval_lib.php';
list($ai, $err) = qt_ai_settings();
if ($err !== null) { fwrite(STDERR, $err . "\n"); exit(1); }
$cat = qt_ai_catalogue($connect);

$fh = fopen($file, 'r');
$head = fgetcsv($fh);
$cases = array();
while (($r = fgetcsv($fh)) !== false) {
	if (count($r) < count($head)) { continue; }
	$c = array_combine($head, $r);
	$c['expected'] = array_map('intval', array_filter(explode('|', $c['expected_ids'])));
	if ($c['case_id'] !== '' && count($c['expected'])) { $cases[] = $c; }
}
fclose($fh);
$missing = array();
foreach ($cases as $c) {
	foreach ($c['expected'] as $id) {
		if (!isset($cat['products'][$id])) { $missing[] = $c['case_id'] . ':' . $id; }
	}
}
if ($missing) {
	echo "Note: expected products not quotable in database '$database' (counted as misses): " . implode(', ', $missing) . "\n";
}

/** Keyword scoring as it was before the fixes of 12-Oct-2026 (for comparison only). */
function kw_v1_tokens($s)
{
	static $stop = array('with', 'and', 'the', 'for', 'of', 'in', 'to', 'an', 'nos', 'no', 'pcs', 'pc', 'qty',
		'piece', 'pieces', 'unit', 'units', 'size', 'colour', 'color', 'finish', 'mm', 'cm', 'inch', 'ft', 'feet', 'x', 'type', 'model');
	$out = array();
	foreach (preg_split('/[^\p{L}\p{N}]+/u', qt_ai_lower(qt_ai_utf8($s)), -1, PREG_SPLIT_NO_EMPTY) as $w) {
		if (strlen($w) >= 2 && !in_array($w, $stop, true)) { $out[$w] = true; }
	}
	return $out;
}
function kw_v1_candidates($cat, $q, $limit)
{
	$code = qt_ai_compact($q['code']);
	$req = qt_ai_compact($q['text']);
	$tokens = kw_v1_tokens($q['text'] . ' ' . $q['terms'] . ' ' . $q['code']);
	$finish = kw_v1_tokens($q['finish']);
	$numbers = qt_ai_numbers($q['dimensions']);
	$scores = array();
	foreach ($cat['products'] as $id => $p) {
		$s = 0;
		if ($code !== '' && $p['compact'] === $code) { $s += 1000; }
		elseif ($req !== '' && $p['compact'] === $req) { $s += 800; }
		elseif ($code !== '' && strlen($code) >= 4 && strpos($p['compact'], $code) !== false) { $s += 200; }
		$tn = kw_v1_tokens($p['name']); $tt = $p['t_tags']; $tc = $p['t_cat']; $tco = $p['t_color']; $td = kw_v1_tokens($p['description']);
		foreach ($tokens as $t => $_) {
			if (isset($tn[$t])) $s += 10; if (isset($tt[$t])) $s += 6; if (isset($tc[$t])) $s += 5; if (isset($tco[$t])) $s += 3; if (isset($td[$t])) $s += 2;
		}
		foreach ($finish as $t => $_) { if (isset($tco[$t])) $s += 4; }
		foreach ($numbers as $n => $_) { if (isset($p['n_dim'][$n])) $s += 5; }
		if ($s >= 6) { $scores[$id] = $s; }
	}
	arsort($scores);
	return array_slice(array_keys($scores), 0, $limit);
}

$limit = (int) $ai['candidates_per_row'];
$results = array();
$semantic = array();
if (in_array('hybrid', $modes, true)) {
	if (!rt_enabled($ai)) {
		echo "hybrid: skipped (no embed_api_key in the AI config; semantic search is off).\n";
		$modes = array_values(array_diff($modes, array('hybrid')));
	} else {
		$rows = array();
		foreach ($cases as $c) {
			$rows[] = array('row_id' => $c['case_id'], 'removed' => false, 'original_text' => $c['requirement'], 'requirement' => $c['requirement'],
				'item_description' => $c['english_terms'] !== '' ? $c['english_terms'] : $c['requirement'], 'product_code' => $c['product_code'],
				'search_terms' => $c['english_terms'], 'finish' => $c['finish'], 'dimensions' => $c['dimensions'], 'other_specs' => '', 'flags' => array(), 'basis' => null);
		}
		list($semantic, $status) = rt_semantic_for_rows($ai, $connect, $rows);
		if (!$status['used']) { echo "hybrid: semantic search unavailable ({$status['note']}).\n"; }
	}
}
foreach ($modes as $mode) {
	foreach ($cases as $c) {
		$q = array('code' => $c['product_code'], 'text' => trim($c['english_terms'] !== '' ? $c['english_terms'] . ' ' . $c['requirement'] : $c['requirement']),
			'terms' => $c['english_terms'], 'finish' => $c['finish'], 'dimensions' => $c['dimensions']);
		if ($mode === 'kw_v1') {
			$ids = kw_v1_candidates($cat, $q, $limit);
		} else {
			$ids = qt_ai_find_candidates($cat, $q, $limit);
			if ($mode === 'hybrid') {
				$ids = rt_fuse($cat, $c['product_code'], $ids, isset($semantic[$c['case_id']]) ? $semantic[$c['case_id']] : array(), $limit);
			}
		}
		$rank = null;
		foreach ($ids as $i => $id) {
			if (in_array($id, $c['expected'], true)) { $rank = $i + 1; break; }
		}
		$results[$mode][$c['source']][] = array('case' => $c['case_id'], 'rank' => $rank, 'candidates' => count($ids));
	}
}

$summary = array();
printf("\n%-8s %-10s %5s %7s %7s %7s %6s %6s\n", 'mode', 'source', 'cases', 'R@1', 'R@5', 'R@12', 'MRR', 'none');
foreach ($results as $mode => $bySource) {
	foreach ($bySource as $source => $list) {
		$n = count($list);
		$at = function ($k) use ($list) { return count(array_filter($list, function ($r) use ($k) { return $r['rank'] !== null && $r['rank'] <= $k; })); };
		$mrr = array_sum(array_map(function ($r) { return $r['rank'] ? 1 / $r['rank'] : 0; }, $list)) / max(1, $n);
		$none = count(array_filter($list, function ($r) { return $r['candidates'] === 0; }));
		$summary[$mode][$source] = array('cases' => $n, 'recall_at_1' => $at(1) / $n, 'recall_at_5' => $at(5) / $n, 'recall_at_12' => $at(12) / $n, 'mrr' => round($mrr, 3), 'no_candidates' => $none,
			'missed' => array_values(array_map(function ($r) { return $r['case']; }, array_filter($list, function ($r) { return $r['rank'] === null; }))));
		printf("%-8s %-10s %5d %6.0f%% %6.0f%% %6.0f%% %6.2f %6d\n", $mode, $source, $n, 100 * $at(1) / $n, 100 * $at(5) / $n, 100 * $at(12) / $n, $mrr, $none);
	}
}
foreach ($summary as $mode => $bySource) {
	foreach ($bySource as $source => $s) {
		if ($s['missed']) { echo "$mode/$source not found in the top $limit: " . implode(', ', $s['missed']) . "\n"; }
	}
}
if ($out !== null) {
	file_put_contents($out, json_encode(array('database' => $database, 'labels' => basename($file), 'limit' => $limit, 'index_model' => rt_enabled($ai) ? rt_index_model($ai) : null,
		'at' => date('c'), 'summary' => $summary, 'detail' => $results), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	echo "Saved $out\n";
}
