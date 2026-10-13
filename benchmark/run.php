<?php
/**
 * Benchmark runner: sends each anonymised sample through the same extraction and
 * catalogue-matching code the app uses, and records what came back, with time,
 * tokens and cost per call. Score the results with benchmark/score.php.
 *
 *   php benchmark/run.php --config=benchmark/benchmark_config.local.php [--live] [--sample=S01]
 *
 * Safety:
 * - A real (non-loopback) API is only called with --live, and only within max_usd.
 * - Refusal fallback must be off, so every answer comes from the configured model.
 * - The live "pms" database is never used; point "db" at a pilot copy (pilot_db.php).
 * - Every sample must name who anonymised it (samples.csv: anonymised_by).
 * See benchmark/README.md.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("Command line only.\n");
}
$opts = getopt('', array('config:', 'live', 'sample:', 'help'));
if (isset($opts['help']) || empty($opts['config'])) {
	echo "Usage: php benchmark/run.php --config=FILE [--live] [--sample=ID]\n";
	exit(isset($opts['help']) ? 0 : 1);
}
$bench = include $opts['config'];
if (!is_array($bench)) {
	fwrite(STDERR, "Benchmark config not readable.\n");
	exit(1);
}
foreach (array('ai_config', 'db', 'samples_dir', 'results_dir', 'price_input_per_mtok', 'price_output_per_mtok', 'max_usd', 'reserve_usd_per_sample', 'type') as $k) {
	if (!isset($bench[$k])) {
		fwrite(STDERR, "Benchmark config is missing '$k'.\n");
		exit(1);
	}
}

putenv('PMS_AI_CONFIG=' . $bench['ai_config']);
$_SESSION = array();
require_once __DIR__ . '/../includes/quotation_lib.php';
require_once __DIR__ . '/../includes/ai_provider.php';
require_once __DIR__ . '/../includes/ai_import_lib.php';

list($ai, $aiError) = qt_ai_settings();
if ($aiError !== null) {
	fwrite(STDERR, "AI config problem: $aiError\n");
	exit(1);
}
if ($ai['refusal_fallback'] !== '') {
	fwrite(STDERR, "refusal_fallback must be '' for the benchmark so results are attributable to one model.\n");
	exit(1);
}
$host = parse_url($ai['api_url'], PHP_URL_HOST);
$isLoopback = in_array($host, array('127.0.0.1', 'localhost'), true);
if (!$isLoopback && !isset($opts['live'])) {
	fwrite(STDERR, "This config calls the real API ($host). Re-run with --live to confirm (budget: \$" . $bench['max_usd'] . ").\n");
	exit(1);
}
$db = $bench['db'];
if (strtolower($db['name']) === 'pms') {
	fwrite(STDERR, "Refusing to use the live 'pms' database. Build a pilot copy with benchmark/pilot_db.php.\n");
	exit(1);
}
$connect = @mysqli_connect($db['host'], $db['user'], $db['pass'], $db['name'], (int) $db['port']);
if (!$connect) {
	fwrite(STDERR, "Cannot connect to the pilot database.\n");
	exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');
if (qt_config($bench['type']) === null) {
	fwrite(STDERR, "type must be DP or MRP.\n");
	exit(1);
}

$samples = bench_read_csv($bench['samples_dir'] . '/samples.csv');
if (!$samples) {
	fwrite(STDERR, "No samples found in " . $bench['samples_dir'] . "/samples.csv\n");
	exit(1);
}
$runId = date('Ymd-His') . ($isLoopback ? '-local' : '-live');
$outDir = rtrim($bench['results_dir'], '/\\') . '/' . $runId;
@mkdir($outDir, 0700, true);
file_put_contents("$outDir/run.json", json_encode(array(
	'run_id' => $runId, 'started' => date('c'), 'model' => $ai['model'], 'effort' => $ai['effort'] !== '' ? $ai['effort'] : 'model default',
	'api_host' => $host, 'live' => !$isLoopback, 'type' => $bench['type'], 'price_input_per_mtok' => $bench['price_input_per_mtok'],
	'price_output_per_mtok' => $bench['price_output_per_mtok'], 'price_source' => isset($bench['price_source']) ? $bench['price_source'] : '',
	'max_usd' => $bench['max_usd'], 'php' => PHP_VERSION,
), JSON_PRETTY_PRINT));

$spent = 0.0;
$done = 0;
echo "Run $runId: model {$ai['model']} at $host, budget \${$bench['max_usd']}\n";
foreach ($samples as $s) {
	$id = isset($s['sample_id']) ? trim($s['sample_id']) : '';
	if ($id === '' || (isset($opts['sample']) && $opts['sample'] !== $id)) {
		continue;
	}
	if (trim(isset($s['anonymised_by']) ? $s['anonymised_by'] : '') === '') {
		echo "  $id: SKIPPED (anonymised_by is empty)\n";
		continue;
	}
	if (($appBudget = qt_ai_budget_error($ai)) !== null) {
		echo "  STOPPED: $appBudget
";
		break;
	}
	if ($spent + (float) $bench['reserve_usd_per_sample'] > (float) $bench['max_usd']) {
		echo "  STOPPED: \$" . number_format($spent, 4) . " spent; the next sample could exceed the \${$bench['max_usd']} budget.\n";
		break;
	}
	$result = bench_run_sample($ai, $connect, $bench, $s);
	$cost = 0.0;
	foreach ($result['calls'] as &$c) {
		$c['cost_usd'] = round($c['input_tokens'] * $bench['price_input_per_mtok'] / 1e6 + $c['output_tokens'] * $bench['price_output_per_mtok'] / 1e6, 6);
		$cost += $c['cost_usd'];
	}
	unset($c);
	$result['cost_usd'] = round($cost, 6);
	$spent += $cost;
	$done++;
	file_put_contents("$outDir/$id.json", json_encode($result, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
	printf("  %-8s %-9s rows=%-3d calls=%d  %6.1fs  in=%-7d out=%-6d  \$%.4f%s\n", $id, $result['status'], count($result['rows']), count($result['calls']),
		$result['ms'] / 1000, $result['input_tokens'], $result['output_tokens'], $cost, $result['error'] ? '  ' . $result['error'] : '');
}
echo "Done: $done sample(s), \$" . number_format($spent, 4) . " estimated. Results: $outDir\n";
echo "Score: php benchmark/score.php --results=$outDir --truth=" . $bench['samples_dir'] . "/ground_truth.csv\n";

/* ---------------------------------------------------------------- helpers */

function bench_read_csv($file)
{
	if (!is_file($file)) {
		return array();
	}
	$fh = fopen($file, 'r');
	$head = fgetcsv($fh);
	if (!$head) {
		return array();
	}
	$head[0] = preg_replace('/^\xEF\xBB\xBF/', '', $head[0]);
	$rows = array();
	while (($r = fgetcsv($fh)) !== false) {
		if (count($r) === 1 && trim($r[0]) === '') {
			continue;
		}
		$rows[] = array_combine($head, array_pad(array_slice($r, 0, count($head)), count($head), ''));
	}
	fclose($fh);
	return $rows;
}

function bench_run_sample($ai, $connect, $bench, $s)
{
	$dir = $bench['samples_dir'] . '/' . $s['sample_id'];
	$out = array('sample_id' => $s['sample_id'], 'input_type' => $s['input_type'], 'language' => $s['language'],
		'status' => 'ok', 'error' => null, 'document_notes' => '', 'rows' => array(), 'calls' => array(),
		'ms' => 0, 'input_tokens' => 0, 'output_tokens' => 0);
	$text = '';
	if (trim($s['text_file']) !== '') {
		$text = trim(str_replace("\r\n", "\n", (string) @file_get_contents($dir . '/' . trim($s['text_file']))));
	}
	$images = array();
	foreach (array_filter(array_map('trim', explode(';', $s['image_files']))) as $i => $file) {
		list($img, $error) = qt_ai_check_image($ai, $dir . '/' . $file, 'Image ' . ($i + 1) . " ($file)");
		if ($error !== null) {
			$out['status'] = 'invalid_input';
			$out['error'] = $error;
			return $out;
		}
		$images[] = array('path' => $img['tmp'], 'mime' => $img['mime']);
	}
	// Same staff instructions as the app: standing instructions + this sample's optional "instructions" column.
	$instructions = qt_ai_instruction_text(array('instructions' => array('standing' => qt_ai_standing_text($ai),
		'import' => isset($s['instructions']) ? trim($s['instructions']) : '')));
	$out['instructions_used'] = $instructions;
	$GLOBALS['qt_ai_meta_sink'] = array();
	$started = microtime(true);
	list($content, $error) = qt_ai_build_extraction_content($images, $text, $instructions);
	if ($error === null) {
		list($data, $callError) = qt_ai_call_json($ai, 'extract', qt_ai_extraction_system_prompt(), $content, qt_ai_extraction_schema(), array('benchmark' => $s['sample_id']));
		if ($callError !== null) {
			$out['status'] = 'extraction_failed';
			$out['error'] = $callError['code'] . ': ' . $callError['message'];
		} else {
			$pseudoDraft = array('input' => array('text' => $text, 'images' => $images));
			list($rows, $notes, $cleanError) = qt_ai_clean_extraction($ai, $pseudoDraft, $data);
			if ($cleanError !== null) {
				$out['status'] = 'extraction_failed';
				$out['error'] = 'invalid_response: ' . $cleanError;
			} else {
				$matchError = qt_ai_match_rows($ai, $connect, $rows, null, array('benchmark' => $s['sample_id']), $instructions);
				if ($matchError !== null) {
					$out['status'] = 'matching_degraded';
					$out['error'] = $matchError['code'] . ': ' . $matchError['message'];
				}
				$out['document_notes'] = $notes;
				foreach ($rows as $r) {
					$m = $r['match'];
					$out['rows'][] = array(
						'row_id' => $r['row_id'], 'source_ref' => $r['source_ref'], 'original_text' => $r['original_text'],
						'item_description' => $r['item_description'], 'product_code' => $r['product_code'],
						'quantity' => $r['quantity'], 'quantity_defaulted' => !empty($r['quantity_defaulted']), 'quantity_text' => $r['quantity_text'], 'quantity_checks' => $r['quantity_checks'],
						'flags' => $r['flags'], 'dimensions' => $r['dimensions'], 'finish' => $r['finish'],
						'applied_instructions' => $r['applied_instructions'],
						'match_status' => $m ? $m['status'] : 'unmatched',
						'preselected_product_id' => $r['product_id'],
						'ai_suggested_id' => $m ? $m['suggested_id'] : null,
						'offered_product_ids' => $m ? array_values(array_unique(array_merge($m['suggested_id'] !== null ? array($m['suggested_id']) : array(), $m['alternatives']))) : array(),
						'candidate_ids' => $m ? $m['candidate_ids'] : array(),
						'warnings' => $m ? $m['warnings'] : array(),
					);
				}
			}
		}
	} else {
		$out['status'] = 'invalid_input';
		$out['error'] = $error;
	}
	$out['ms'] = (int) ((microtime(true) - $started) * 1000);
	foreach ($GLOBALS['qt_ai_meta_sink'] as $meta) {
		$out['calls'][] = array('purpose' => $meta['purpose'], 'ms' => $meta['ms'], 'attempts' => $meta['attempts'],
			'input_tokens' => $meta['input_tokens'], 'output_tokens' => $meta['output_tokens'], 'stop_reason' => $meta['stop_reason'],
			'requested_model' => $meta['requested_model'], 'served_model' => $meta['served_model'], 'served_by_fallback' => $meta['served_by_fallback'],
			'refusal_category' => $meta['refusal_category'], 'request_id' => $meta['request_id'], 'error' => $meta['error']);
		$out['input_tokens'] += $meta['input_tokens'];
		$out['output_tokens'] += $meta['output_tokens'];
	}
	return $out;
}
