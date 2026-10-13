<?php
/**
 * Benchmark scorer: compares a run (benchmark/run.php) with the ground truth.
 * Extraction, quantities and catalogue matching are reported separately; there is
 * deliberately no single combined accuracy score.
 *
 *   php benchmark/score.php --results=benchmark/results/<run> --truth=benchmark/samples/ground_truth.csv
 *                           [--overrides=benchmark/samples/alignment_overrides.csv]
 *
 * Writes report.md, items.csv and samples.csv into the results folder.
 * Ground-truth items are paired with extracted rows by text similarity; check the
 * "alignment" table in report.md and correct any wrong pairing in the overrides file
 * (sample_id,item_no,row_id — row_id "none" means the item was not extracted).
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("Command line only.\n");
}
$opts = getopt('', array('results:', 'truth:', 'overrides:'));
if (empty($opts['results']) || empty($opts['truth'])) {
	echo "Usage: php benchmark/score.php --results=DIR --truth=ground_truth.csv [--overrides=FILE]\n";
	exit(1);
}
$dir = rtrim($opts['results'], '/\\');
$run = json_decode((string) @file_get_contents("$dir/run.json"), true);
$truth = score_csv($opts['truth']);
$overrides = array();
foreach (!empty($opts['overrides']) ? score_csv($opts['overrides']) : array() as $o) {
	$overrides[$o['sample_id'] . '#' . $o['item_no']] = trim($o['row_id']);
}
$bySample = array();
foreach ($truth as $t) {
	$bySample[trim($t['sample_id'])][] = $t;
}

$samples = array();
$items = array();
$align = array();
$T = array(); // totals
foreach ($bySample as $sid => $gt) {
	$res = json_decode((string) @file_get_contents("$dir/$sid.json"), true);
	if (!$res) {
		$samples[] = array('sample_id' => $sid, 'status' => 'not run');
		continue;
	}
	$rows = $res['rows'];
	$pairs = score_align($sid, $gt, $rows, $overrides);
	$usedRows = array();
	$S = array('sample_id' => $sid, 'input' => $res['input_type'] . '/' . $res['language'], 'status' => $res['status'],
		'gt_items' => 0, 'found' => 0, 'missed' => 0, 'invented' => 0, 'extra_flagged' => 0,
		'crossed_ok' => 0, 'crossed_bad' => 0,
		'qty_exact' => 0, 'qty_exact_flagged' => 0, 'qty_wrong_flagged' => 0, 'qty_wrong_unflagged' => 0, 'qty_missing' => 0,
		'unres_kept' => 0, 'unres_defaulted' => 0, 'unres_flagged' => 0, 'unres_guessed' => 0,
		'uniq_correct' => 0, 'uniq_wrong' => 0, 'uniq_offered' => 0, 'uniq_not_found' => 0,
		'choice_ok' => 0, 'choice_preselected' => 0, 'choice_offered' => 0,
		'none_ok' => 0, 'none_wrong' => 0,
		'fix_errors' => 0, 'decisions' => 0,
		'seconds' => round($res['ms'] / 1000, 1), 'calls' => count($res['calls']), 'input_tokens' => $res['input_tokens'],
		'output_tokens' => $res['output_tokens'], 'cost_usd' => isset($res['cost_usd']) ? $res['cost_usd'] : 0,
		'refusals' => 0, 'fallback_served' => 0);
	foreach ($res['calls'] as $c) {
		$S['refusals'] += $c['error'] === 'refused' ? 1 : 0;
		$S['fallback_served'] += $c['served_by_fallback'] ? 1 : 0;
	}
	foreach ($gt as $k => $t) {
		$presence = strtolower(trim($t['expected_presence']));
		$ri = $pairs[$k];
		$row = $ri === null ? null : $rows[$ri];
		if ($ri !== null) {
			$usedRows[$ri] = true;
		}
		$align[] = array($sid, $t['item_no'], $t['source_text'], $row ? $row['row_id'] . ': ' . $row['original_text'] : '(not extracted)');
		$item = array('sample_id' => $sid, 'item_no' => $t['item_no'], 'presence' => $presence, 'extracted_row' => $row ? $row['row_id'] : '',
			'extraction' => '', 'quantity' => '', 'matching' => '');

		if ($presence === 'crossed_out' || $presence === 'not_item') {
			$flag = $presence === 'crossed_out' ? 'crossed_out' : 'not_an_item';
			$ok = $row === null || in_array($flag, $row['flags'], true);
			$S[$ok ? 'crossed_ok' : 'crossed_bad']++;
			$item['extraction'] = $ok ? ($row ? 'extracted and flagged ' . $flag : 'left out') : 'extracted WITHOUT ' . $flag . ' flag';
			if (!$ok) { $S['fix_errors']++; }
			$items[] = $item;
			continue;
		}

		$S['gt_items']++;
		if ($row === null) {
			$S['missed']++;
			$S['fix_errors']++;
			$item['extraction'] = 'MISSED';
			$items[] = $item;
			continue;
		}
		$S['found']++;
		$item['extraction'] = 'found';

		// Quantity
		$qs = strtolower(trim($t['quantity_status']));
		$expectedQty = trim($t['expected_quantity']) === '' ? null : (int) $t['expected_quantity'];
		$defaulted = !empty($row['quantity_defaulted']);
		$flagged = count($row['quantity_checks']) > 0 || $defaulted;
		if ($qs === 'clear' && $expectedQty !== null) {
			if ($row['quantity'] === $expectedQty) {
				$k2 = $flagged ? 'qty_exact_flagged' : 'qty_exact';
				if ($flagged) { $S['decisions']++; }
			} elseif ($row['quantity'] === null) {
				$k2 = 'qty_missing';
				$S['fix_errors']++;
			} else {
				$k2 = $flagged ? 'qty_wrong_flagged' : 'qty_wrong_unflagged';
				$S['fix_errors']++;
			}
		} else {
			$k2 = $row['quantity'] === null ? 'unres_kept' : ($defaulted ? 'unres_defaulted' : ($flagged ? 'unres_flagged' : 'unres_guessed'));
			$S['decisions']++;            // someone must get the quantity from the customer
			if ($k2 === 'unres_guessed') { $S['fix_errors']++; }
		}
		$S[$k2]++;
		$item['quantity'] = $k2 . ' (expected ' . ($expectedQty === null ? $qs : $expectedQty) . ', got ' . ($row['quantity'] === null ? 'none' : $row['quantity']) . ($defaulted ? ', default' : ($flagged ? ', flagged' : '')) . ')';

		// Matching
		$acceptable = array_map('intval', array_filter(array_map('trim', explode(';', $t['acceptable_product_ids'])), 'strlen'));
		$expect = strtolower(trim($t['match_expectation']));
		$pre = $row['preselected_product_id'];
		$offered = array_map('intval', $row['offered_product_ids']);
		if ($expect === 'unique') {
			if ($pre !== null && in_array((int) $pre, $acceptable, true)) {
				$k3 = 'uniq_correct';
			} elseif ($pre !== null) {
				$k3 = 'uniq_wrong';
				$S['fix_errors']++;
			} elseif (count(array_intersect($acceptable, $offered)) > 0) {
				$k3 = 'uniq_offered';
				$S['decisions']++;
			} else {
				$k3 = 'uniq_not_found';
				$S['fix_errors']++;
			}
		} elseif ($expect === 'choice') {
			$S['decisions']++;
			if ($pre !== null) {
				$k3 = 'choice_preselected';
				if (!in_array((int) $pre, $acceptable, true)) { $S['fix_errors']++; }
			} else {
				$k3 = 'choice_ok';
			}
			if (count(array_intersect($acceptable, $offered)) > 0) { $S['choice_offered']++; }
		} else {
			$k3 = $pre === null ? 'none_ok' : 'none_wrong';
			if ($pre !== null) { $S['fix_errors']++; }
		}
		$S[$k3]++;
		$item['matching'] = $k3 . ' (expected ' . $expect . ($acceptable ? ' ' . implode('/', $acceptable) : '') . ', preselected ' . ($pre === null ? 'none' : $pre)
			. ', offered ' . ($offered ? implode('/', $offered) : 'none') . ')';
		$items[] = $item;
	}
	foreach ($rows as $ri => $row) {
		if (!isset($usedRows[$ri])) {
			if (in_array('not_an_item', $row['flags'], true) || in_array('crossed_out', $row['flags'], true)) {
				$S['extra_flagged']++;
			} else {
				$S['invented']++;
				$S['fix_errors']++;
			}
			$align[] = array($sid, '-', '(no ground-truth item)', $row['row_id'] . ': ' . $row['original_text']);
		}
	}
	foreach ($S as $k => $v) {
		if (is_int($v) || is_float($v)) {
			$T[$k] = (isset($T[$k]) ? $T[$k] : 0) + $v;
		}
	}
	$samples[] = $S;
}

/* ---------------------------------------------------------------- output */

score_write_csv("$dir/samples.csv", $samples);
score_write_csv("$dir/items.csv", $items);
$n = count(array_filter($samples, function ($s) { return isset($s['gt_items']); }));
$md = array();
$md[] = '# Benchmark report';
$md[] = '';
$md[] = 'Run `' . ($run['run_id'] ?? basename($dir)) . '`: model `' . ($run['model'] ?? '?') . '`, effort ' . ($run['effort'] ?? '?') . ', '
	. ($run['live'] ?? false ? '**real API**' : '**local test double (not a real accuracy measurement)**') . ', ' . $n . ' sample(s) scored.';
$md[] = 'Prices used: $' . ($run['price_input_per_mtok'] ?? '?') . ' / $' . ($run['price_output_per_mtok'] ?? '?') . ' per million input/output tokens (' . ($run['price_source'] ?? '') . ').';
$md[] = '';
$md[] = 'Each table measures one thing. Do not add them into a single score.';
$md[] = '';
$md[] = '## 1. Extraction (did it find the right lines?)';
$md[] = score_table(array('Ground-truth items', 'Found', 'Missed', 'Invented (unflagged extra rows)', 'Extra rows flagged as not an item / crossed out', 'Crossed-out / non-item lines handled', 'Crossed-out / non-item lines NOT flagged'),
	array(array($T['gt_items'] ?? 0, $T['found'] ?? 0, $T['missed'] ?? 0, $T['invented'] ?? 0, $T['extra_flagged'] ?? 0, $T['crossed_ok'] ?? 0, $T['crossed_bad'] ?? 0)));
$md[] = '## 2. Quantities (on items that were found)';
$md[] = 'Clear quantities in the source:';
$md[] = score_table(array('Exact', 'Exact but flagged for a check', 'Wrong, flagged', 'Wrong, NOT flagged', 'Not read'),
	array(array($T['qty_exact'] ?? 0, $T['qty_exact_flagged'] ?? 0, $T['qty_wrong_flagged'] ?? 0, $T['qty_wrong_unflagged'] ?? 0, $T['qty_missing'] ?? 0)));
$md[] = 'Missing, unreadable or ambiguous quantities in the source:';
$md[] = score_table(array('Kept unresolved', 'Set to the configured default (shown on the row)', 'Read but flagged for a check', 'Guessed without a flag'),
	array(array($T['unres_kept'] ?? 0, $T['unres_defaulted'] ?? 0, $T['unres_flagged'] ?? 0, $T['unres_guessed'] ?? 0)));
$md[] = '## 3. Catalogue matching (on items that were found)';
$md[] = score_table(array('Expectation', 'Correct handling', 'Needs a person but acceptable product offered', 'Wrong product preselected', 'Acceptable product not offered'),
	array(
		array('One right product', $T['uniq_correct'] ?? 0, $T['uniq_offered'] ?? 0, $T['uniq_wrong'] ?? 0, $T['uniq_not_found'] ?? 0),
		array('Several fit: person must choose', $T['choice_ok'] ?? 0, ($T['choice_offered'] ?? 0) . ' with an acceptable option offered', $T['choice_preselected'] ?? 0, '-'),
		array('No catalogue product', $T['none_ok'] ?? 0, '-', $T['none_wrong'] ?? 0, '-'),
	));
$md[] = '## 4. Employee work';
$md[] = score_table(array('Errors to fix (missed, invented, wrong quantity, wrong product)', 'Expected decisions (confirm quantity, choose among products, ask customer)'),
	array(array($T['fix_errors'] ?? 0, $T['decisions'] ?? 0)));
$md[] = 'Real correction counts from the pilot are in the app log (event "apply", fields edit_*).';
$md[] = '';
$md[] = '## 5. Time, tokens and cost per sample';
$rowsOut = array();
foreach ($samples as $s) {
	if (!isset($s['gt_items'])) {
		$rowsOut[] = array($s['sample_id'], $s['status'], '', '', '', '', '', '', '');
		continue;
	}
	$rowsOut[] = array($s['sample_id'], $s['input'], $s['status'], $s['seconds'], $s['calls'], $s['input_tokens'], $s['output_tokens'], '$' . number_format($s['cost_usd'], 4),
		($s['refusals'] ? $s['refusals'] . ' refusal(s) ' : '') . ($s['fallback_served'] ? $s['fallback_served'] . ' fallback-served call(s)' : ''));
}
$rowsOut[] = array('**Total**', '', '', round($T['seconds'] ?? 0, 1), $T['calls'] ?? 0, $T['input_tokens'] ?? 0, $T['output_tokens'] ?? 0, '$' . number_format($T['cost_usd'] ?? 0, 4), '');
$md[] = score_table(array('Sample', 'Input', 'Status', 'Seconds', 'API calls', 'Input tokens', 'Output tokens', 'Cost', 'Notes'), $rowsOut);
$md[] = '## 6. Alignment (check these pairings; fix any with the overrides file)';
$md[] = score_table(array('Sample', 'Item', 'Ground truth', 'Extracted row'), $align);
$md[] = 'Per-item details: items.csv. Per-sample counts: samples.csv.';
file_put_contents("$dir/report.md", implode("\n", $md) . "\n");
echo "Wrote $dir/report.md, items.csv, samples.csv\n";

/* ---------------------------------------------------------------- helpers */

function score_csv($file)
{
	$out = array();
	if (!is_file($file) || !($fh = fopen($file, 'r'))) {
		return $out;
	}
	$head = fgetcsv($fh);
	$head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
	while (($r = fgetcsv($fh)) !== false) {
		if (count($r) === 1 && trim($r[0]) === '') {
			continue;
		}
		$out[] = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
	}
	fclose($fh);
	return $out;
}

function score_norm($s)
{
	$s = strtr((string) $s, array('०' => '0', '१' => '1', '२' => '2', '३' => '3', '४' => '4', '५' => '5', '६' => '6', '७' => '7', '८' => '8', '९' => '9'));
	$s = function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
	return trim(preg_replace('/[^\p{L}\p{N}\p{M}]+/u', ' ', $s));
}

/**
 * Similarity of two lines: average of character-bigram Dice (tolerates small spelling
 * differences, works for Devanagari) and word Jaccard (requires the same words, so lines
 * that merely share a few letters do not pair).
 */
function score_similarity($a, $b)
{
	$wa = array_unique(preg_split('/\s+/u', score_norm($a), -1, PREG_SPLIT_NO_EMPTY));
	$wb = array_unique(preg_split('/\s+/u', score_norm($b), -1, PREG_SPLIT_NO_EMPTY));
	$union = count(array_unique(array_merge($wa, $wb)));
	$jaccard = $union ? count(array_intersect($wa, $wb)) / $union : 0.0;
	return (score_dice($a, $b) + $jaccard) / 2;
}

/** Dice coefficient on character bigrams. */
function score_dice($a, $b)
{
	$grams = function ($s) {
		$chars = preg_split('//u', score_norm($s), -1, PREG_SPLIT_NO_EMPTY);
		$g = array();
		for ($i = 0; $i < count($chars) - 1; $i++) {
			$k = $chars[$i] . $chars[$i + 1];
			$g[$k] = (isset($g[$k]) ? $g[$k] : 0) + 1;
		}
		return $g;
	};
	$ga = $grams($a);
	$gb = $grams($b);
	$total = array_sum($ga) + array_sum($gb);
	if ($total === 0) {
		return 0.0;
	}
	$common = 0;
	foreach ($ga as $k => $v) {
		if (isset($gb[$k])) {
			$common += min($v, $gb[$k]);
		}
	}
	return 2 * $common / $total;
}

/** Greedy best-pair alignment of ground-truth items to extracted rows (threshold 0.4), plus overrides. */
function score_align($sid, $gt, $rows, $overrides)
{
	$pairs = array_fill(0, count($gt), null);
	$takenRows = array();
	$rowIndex = array();
	foreach ($rows as $ri => $r) {
		$rowIndex[$r['row_id']] = $ri;
	}
	foreach ($gt as $k => $t) {
		$key = $sid . '#' . $t['item_no'];
		if (isset($overrides[$key])) {
			$v = $overrides[$key];
			$pairs[$k] = ($v !== 'none' && isset($rowIndex[$v])) ? $rowIndex[$v] : -1;
			if ($pairs[$k] >= 0) {
				$takenRows[$pairs[$k]] = true;
			}
		}
	}
	$candidates = array();
	foreach ($gt as $k => $t) {
		if ($pairs[$k] !== null) {
			continue;
		}
		foreach ($rows as $ri => $r) {
			if (isset($takenRows[$ri])) {
				continue;
			}
			$score = score_similarity($t['source_text'], $r['original_text']);
			$ref = strtolower(trim($t['source_ref']));
			if ($ref !== '' && $ref === strtolower($r['source_ref'])) {
				$score += 0.05;
			}
			if ($score >= 0.4) {
				$candidates[] = array($score, $k, $ri);
			}
		}
	}
	usort($candidates, function ($x, $y) { return $x[0] == $y[0] ? 0 : ($x[0] > $y[0] ? -1 : 1); });
	foreach ($candidates as $c) {
		list(, $k, $ri) = $c;
		if ($pairs[$k] === null && !isset($takenRows[$ri])) {
			$pairs[$k] = $ri;
			$takenRows[$ri] = true;
		}
	}
	foreach ($pairs as $k => $v) {
		if ($v === -1) {
			$pairs[$k] = null;
		}
	}
	return $pairs;
}

function score_table($head, $rows)
{
	$esc = function ($v) { return str_replace(array('|', "\n"), array('\\|', ' '), (string) $v); };
	$out = '| ' . implode(' | ', array_map($esc, $head)) . " |\n|" . str_repeat(' --- |', count($head)) . "\n";
	foreach ($rows as $r) {
		$out .= '| ' . implode(' | ', array_map($esc, $r)) . " |\n";
	}
	return $out;
}

function score_write_csv($file, $rows)
{
	$fh = fopen($file, 'w');
	if (count($rows)) {
		$keys = array();
		foreach ($rows as $r) {
			$keys = array_unique(array_merge($keys, array_keys($r)));
		}
		fputcsv($fh, $keys);
		foreach ($rows as $r) {
			$line = array();
			foreach ($keys as $k) {
				$line[] = isset($r[$k]) ? $r[$k] : '';
			}
			fputcsv($fh, $line);
		}
	}
	fclose($fh);
}
