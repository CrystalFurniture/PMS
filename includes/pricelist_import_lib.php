<?php
/**
 * Import of the company's printed price lists (.xlsx: many sheets, each with its own layout, title rows,
 * photos) into price lists the AI Assistant uses (price_states + state_pricelist):
 *   master  company master list(s): used before the software's own prices
 *   state   one state (e.g. Bihar)        region  several states (e.g. South)
 * Only cell values are read (images are never loaded), so very large files work. Rates are rounded to the
 * nearest rupee and stored by category: MRP, DP or NET (NET = third category, no discount); solid-wood
 * sheets with TEAK WOOD / MANGO WOOD columns give one rate per variant.
 * Requires the zip and xmlreader extensions.
 */

define('PL_CODE_HDR', '/^(item\s*)?code(\s*&\s*colou?r)?$|^product\s*code$|^model\s*no\.?$|^item\s*code\s*&\s*name$/i');
define('PL_RATE_HDR', '/^(dp|mrp|net)(\s*rate)?$|^new\s*net\s*rate$|^rate$|^(dp|mrp)\s*price$/i');

/** Open an .xlsx: array(ZipArchive, shared strings, list of [sheet name, part]) or array(null, error). */
function pl_xlsx_open($path)
{
	if (strtolower(pathinfo($path, PATHINFO_EXTENSION)) !== 'xlsx') {
		return array(null, 'Only .xlsx files can be read. Open the file in Excel and "Save As" Excel Workbook (.xlsx).');
	}
	$zip = new ZipArchive();
	if ($zip->open($path) !== true) {
		return array(null, 'The file could not be opened as an Excel workbook.');
	}
	$shared = array();
	$xml = $zip->getFromName('xl/sharedStrings.xml');
	if ($xml !== false) {
		$r = new XMLReader();
		$r->XML($xml, 'UTF-8', LIBXML_PARSEHUGE);
		$cur = null;
		while ($r->read()) {
			if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'si') {
				$cur = '';
			} elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 't' && $cur !== null) {
				$cur .= $r->readString();
			} elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'si') {
				$shared[] = $cur;
				$cur = null;
			}
		}
	}
	$rels = array();
	$rx = simplexml_load_string($zip->getFromName('xl/_rels/workbook.xml.rels'));
	foreach ($rx->Relationship as $rel) {
		$rels[(string) $rel['Id']] = ltrim((string) $rel['Target'], '/');
	}
	$wb = simplexml_load_string($zip->getFromName('xl/workbook.xml'));
	$sheets = array();
	foreach ($wb->sheets->sheet as $s) {
		$rid = (string) $s->attributes('http://schemas.openxmlformats.org/officeDocument/2006/relationships')->id;
		$t = $rels[$rid];
		$sheets[] = array((string) $s['name'], strpos($t, 'xl/') === 0 ? $t : 'xl/' . $t);
	}
	return array(array($zip, $shared, $sheets), null);
}

function pl_col_index($ref)
{
	preg_match('/^[A-Z]+/', $ref, $m);
	$n = 0;
	foreach (str_split($m[0]) as $ch) {
		$n = $n * 26 + ord($ch) - 64;
	}
	return $n - 1;
}

/** Rows of one sheet as arrays of cell strings (streamed). */
function pl_xlsx_rows($zip, $shared, $part)
{
	$xml = $zip->getFromName($part);
	$out = array();
	if ($xml === false) {
		return $out;
	}
	$r = new XMLReader();
	$r->XML($xml, 'UTF-8', LIBXML_PARSEHUGE);
	$row = null;
	$cell = null;
	while ($r->read()) {
		if ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'row') {
			$row = array();
		} elseif ($r->nodeType === XMLReader::ELEMENT && $r->localName === 'c') {
			$cell = array('ref' => $r->getAttribute('r'), 't' => $r->getAttribute('t'), 'v' => null);
			if ($r->isEmptyElement) {
				$cell = null;
			}
		} elseif ($r->nodeType === XMLReader::ELEMENT && ($r->localName === 'v' || ($r->localName === 't' && $cell !== null && $cell['t'] === 'inlineStr')) && $cell !== null) {
			$cell['v'] = ($cell['v'] === null ? '' : $cell['v']) . $r->readString();
		} elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'c' && $cell !== null) {
			if ($cell['v'] !== null) {
				$v = $cell['t'] === 's' ? (isset($shared[(int) $cell['v']]) ? $shared[(int) $cell['v']] : '') : $cell['v'];
				$row[pl_col_index($cell['ref'])] = $v;
			}
			$cell = null;
		} elseif ($r->nodeType === XMLReader::END_ELEMENT && $r->localName === 'row') {
			if ($row) {
				$w = max(array_keys($row)) + 1;
				$line = array();
				for ($i = 0; $i < $w; $i++) {
					$line[] = isset($row[$i]) ? trim((string) $row[$i]) : '';
				}
				$out[] = $line;
			}
			$row = null;
		}
	}
	return $out;
}

/** Category and variant of a rate column label: array('DP'|'MRP'|'NET', ''|'TEAK'|'MANGO'). */
function pl_label_kind($label)
{
	$l = strtoupper($label);
	$type = strpos($l, 'NET') !== false ? 'NET' : (strpos($l, 'MRP') !== false ? 'MRP' : 'DP');
	$variant = strpos($l, 'TEAK') !== false ? 'TEAK' : (strpos($l, 'MANGO') !== false ? 'MANGO' : '');
	return array($type, $variant);
}

/**
 * Extract priced rows from every sheet: array(rows, error). Row: sheet, row, code, rates [ [type, variant, value] ].
 * Header rows (a code column and rate columns, the rate header possibly in the row above) set the layout;
 * repeated headers update it. "Code:- X" prefixes are removed. Values are rounded to the nearest rupee.
 */
function pl_extract($path)
{
	list($wb, $error) = pl_xlsx_open($path);
	if ($error !== null) {
		return array(null, $error);
	}
	list($zip, $shared, $sheets) = $wb;
	$out = array();
	foreach ($sheets as $sh) {
		list($name, $part) = $sh;
		$codeCol = null;
		$rateCols = array();
		$pending = null;
		$prev = array();
		$i = 0;
		foreach (pl_xlsx_rows($zip, $shared, $part) as $cells) {
			$i++;
			$codes = array();
			$rates = array();
			foreach ($cells as $j => $c) {
				if (preg_match(PL_CODE_HDR, $c)) { $codes[] = $j; }
				if (preg_match(PL_RATE_HDR, $c)) { $rates[$j] = $c; }
			}
			$prevRates = array();
			foreach ($prev as $j => $p) {
				if (preg_match(PL_RATE_HDR, $p)) { $prevRates[$j] = $p; }
			}
			if ($codes && ($rates || $prevRates)) {
				$codeCol = $codes[0];
				foreach ($codes as $j) {
					if (preg_match('/^product\s*code$/i', $cells[$j])) { $codeCol = $j; }
				}
				if (!$rates) { $rates = $prevRates; }
				$rateCols = array();
				foreach ($rates as $j => $lab) {
					$above = isset($prev[$j]) ? $prev[$j] : '';
					$rateCols[$j] = ($above !== '' && !preg_match(PL_RATE_HDR, $above) && !is_numeric($above)) ? $above . ' ' . $lab : $lab;
				}
				$prev = $cells;
				continue;
			}
			$prev = $cells;
			if ($codeCol === null || !$rateCols || !isset($cells[$codeCol])) {
				continue;
			}
			$code = trim(preg_replace('/^\s*(code|item\s*code)\s*[:\-]+\s*/i', '', $cells[$codeCol]));
			if ($code === '' || preg_match('/^(name|colou?r|fabric|polish|top|structure)\b/i', $code)) {
				continue;
			}
			$vals = array();
			foreach ($rateCols as $j => $lab) {
				$v = isset($cells[$j]) ? str_replace(',', '', $cells[$j]) : '';
				if (is_numeric($v) && (float) $v > 0) {
					list($type, $variant) = pl_label_kind($lab);
					$vals[] = array($type, $variant, (int) round((float) $v));
				}
			}
			// A heading row with rates but no code ("HAZEL | DINING TABLE SET | ... | 13150 | 6846") gives its rates to
			// the code on the next row when that row has none ("CODE :- SWDN-06(A) HAZEL").
			if (!$vals && $pending !== null && preg_match('/\d/', $code)) {
				$vals = $pending;
				$pending = null;
			} else {
				$pending = $vals && !preg_match('/\d/', $code) ? $vals : null;
			}
			if ($vals) {
				$out[] = array('sheet' => $name, 'row' => $i, 'code' => $code, 'rates' => $vals);
			}
		}
	}
	$zip->close();
	return array($out, null);
}

/**
 * Match codes to products by name (spaces/punctuation ignored); when the cell holds "CODE + name", the first
 * word is tried; a code ending in ")" also matches one product name that continues after it
 * ("CS-169 (SCB-5)" -> "CS-169 (SCB-5) COMFINA"). All products sharing that name get the rate.
 * Returns entries keyed "pid|variant" => [pid, variant, mrp, dp, net, source] plus a report.
 */
function pl_plan($connect, $rows, $fileLabel)
{
	$byKey = array();
	$r = mysqli_query($connect, "SELECT id, name FROM products");
	while ($x = mysqli_fetch_assoc($r)) {
		$byKey[qt_ai_compact(qt_ai_text($x['name']))][] = (int) $x['id'];
	}
	$entries = array();
	$report = array('rows' => count($rows), 'matched' => 0, 'shared_name' => 0, 'unmatched' => array(), 'conflicts' => array(), 'types' => array(), 'by_prefix' => 0);
	foreach ($rows as $row) {
		$ids = null;
		foreach (array($row['code'], strtok($row['code'], ' ')) as $try) {
			$k = qt_ai_compact($try);
			if ($k !== '' && isset($byKey[$k])) {
				$ids = $byKey[$k];
				break;
			}
		}
		if ($ids === null && substr(rtrim($row['code']), -1) === ')') {
			$k = qt_ai_compact($row['code']);
			$found = array();
			foreach ($byKey as $name => $pids) {
				if (strlen($name) > strlen($k) && strncmp($name, $k, strlen($k)) === 0) {
					$found[] = $pids;
				}
			}
			if (count($found) === 1) {
				$ids = $found[0];
				$report['by_prefix']++;
			}
		}
		if ($ids === null) {
			// The software may name the product with a collection word after the code ("SWCH-12 HAZEL" for "SWCH 12").
			$k = qt_ai_compact($row['code']);
			$found = array();
			foreach ($byKey as $name => $pids) {
				if (preg_match('/\d/', $k) && strlen($name) > strlen($k) && strncmp($name, $k, strlen($k)) === 0 && ctype_alpha(substr($name, strlen($k)))) {
					$found[] = $pids;
				}
			}
			if (count($found) === 1) {
				$ids = $found[0];
				$report['by_prefix']++;
			}
		}
		if ($ids === null) {
			$report['unmatched'][] = $row['sheet'] . ': ' . $row['code'];
			continue;
		}
		$report['matched']++;
		if (count($ids) > 1) {
			$report['shared_name']++;
		}
		foreach ($ids as $pid) {
			foreach ($row['rates'] as $rate) {
				list($type, $variant, $value) = $rate;
				$label = $type . ($variant !== '' ? ' ' . $variant : '');
				$report['types'][$label] = (isset($report['types'][$label]) ? $report['types'][$label] : 0) + 1;
				$key = $pid . '|' . $variant;
				if (!isset($entries[$key])) {
					$entries[$key] = array('pid' => $pid, 'variant' => $variant, 'MRP' => null, 'DP' => null, 'NET' => null, 'source' => $fileLabel . ' / ' . $row['sheet'] . ' row ' . $row['row']);
				}
				if ($entries[$key][$type] !== null && $entries[$key][$type] !== $value) {
					$report['conflicts'][$row['code'] . $variant . $type . $row['row']] = $row['code'] . ($variant ? ' ' . $variant : '') . ' ' . $type . ': ' . $entries[$key][$type] . ' vs ' . $value . ' (' . $row['sheet'] . ' row ' . $row['row'] . '; the later row is used)';
				}
				$entries[$key][$type] = $value;
			}
		}
	}
	return array($entries, $report);
}

/**
 * Save a list. $merge false = the list is replaced by these entries (new version); true = entries are added
 * or override existing ones for the same product and variant (e.g. Office list on top of Master).
 * Returns error or null.
 */
function pl_save($connect, $list, $entries, $merge, $user, $sourceFile)
{
	$e = function ($v) use ($connect) { return mysqli_real_escape_string($connect, (string) $v); };
	$n = function ($v) { return $v === null ? 'NULL' : (string) (int) $v; };
	mysqli_query($connect, 'START TRANSACTION');
	$ok = mysqli_query($connect, "INSERT INTO price_states (code, name, active, source_file, rows_loaded, updated_by, updated_at, version, kind, covers, effective_from)
		VALUES ('" . $e($list['code']) . "', '" . $e($list['name']) . "', 1, '" . $e($sourceFile) . "', " . count($entries) . ", '" . $e($user) . "', NOW(), 1, '"
		. $e($list['kind']) . "', '" . $e($list['covers']) . "', " . ($list['effective'] ? "'" . $e($list['effective']) . "'" : 'NULL') . ")
		ON DUPLICATE KEY UPDATE name = VALUES(name), active = 1, source_file = " . ($merge ? "CONCAT(source_file, ' + ', VALUES(source_file))" : 'VALUES(source_file)')
		. ", updated_by = VALUES(updated_by), updated_at = NOW(), version = version + 1, kind = VALUES(kind), covers = VALUES(covers),
		effective_from = COALESCE(VALUES(effective_from), effective_from)");
	if ($ok && !$merge) {
		$ok = mysqli_query($connect, "DELETE FROM state_pricelist WHERE state_code = '" . $e($list['code']) . "'");
	}
	$batch = array();
	foreach ($entries as $x) {
		if (!$ok) {
			break;
		}
		$batch[] = "('" . $e($list['code']) . "', " . (int) $x['pid'] . ', ' . $n($x['MRP']) . ', ' . $n($x['DP']) . ', ' . $n($x['NET']) . ", '" . $e($x['variant']) . "', '" . $e(substr($x['source'], 0, 160)) . "')";
		if (count($batch) === 300) {
			$ok = pl_insert_batch($connect, $batch);
			$batch = array();
		}
	}
	if ($ok && $batch) {
		$ok = pl_insert_batch($connect, $batch);
	}
	if ($ok) {
		$ok = mysqli_query($connect, "UPDATE price_states SET rows_loaded = (SELECT COUNT(*) FROM state_pricelist WHERE state_code = '" . $e($list['code']) . "') WHERE code = '" . $e($list['code']) . "'");
	}
	$hasPriority = ($c = mysqli_query($connect, "SHOW COLUMNS FROM price_states LIKE 'priority'")) && mysqli_num_rows($c) > 0;
	if ($ok && $hasPriority && isset($list['priority']) && $list['priority'] !== null) {
		$ok = mysqli_query($connect, "UPDATE price_states SET priority = " . (int) $list['priority'] . " WHERE code = '" . $e($list['code']) . "'");
	}
	if (!$ok) {
		$err = mysqli_error($connect);
		mysqli_query($connect, 'ROLLBACK');
		return 'The price list could not be saved: ' . $err;
	}
	mysqli_query($connect, 'COMMIT');
	return null;
}

function pl_insert_batch($connect, $batch)
{
	return mysqli_query($connect, 'INSERT INTO state_pricelist (state_code, p_prodId, mrp_price, dp_price, net_price, variant, source) VALUES ' . implode(',', $batch)
		. ' ON DUPLICATE KEY UPDATE mrp_price = COALESCE(VALUES(mrp_price), mrp_price), dp_price = COALESCE(VALUES(dp_price), dp_price),
		net_price = COALESCE(VALUES(net_price), net_price), source = VALUES(source)');
}

/**
 * Series headings in a price list: a row holding only a code prefix and a name ("CS-157 | GOTHIC").
 * Returns array(prefix => name) or array(null, error) on an unreadable file.
 */
function pl_extract_series($path)
{
	list($wb, $error) = pl_xlsx_open($path);
	if ($error !== null) {
		return array(null, $error);
	}
	list($zip, $shared, $sheets) = $wb;
	$out = array();
	foreach ($sheets as $sh) {
		foreach (pl_xlsx_rows($zip, $shared, $sh[1]) as $cells) {
			$cells = array_values(array_filter($cells, function ($c) { return $c !== ''; }));
			if (count($cells) === 2 && preg_match('/^[A-Z]{1,6}\s*-?\s*\d+[A-Z]?$/i', $cells[0]) && preg_match('/^[A-Za-z][A-Za-z .&\'-]{1,40}$/', $cells[1])) {
				$out[strtoupper(preg_replace('/\s+/', '', $cells[0]))] = trim(preg_replace('/\s+/', ' ', $cells[1]));
			}
		}
	}
	$zip->close();
	return array($out, null);
}

/**
 * Dining tables and their chairs from a price list's dining sheets: in a block, the table codes (SWDN-07(A),
 * SWDN-07(B)) are followed by the chair code (SWCH-14); the first chair after the tables is theirs. Blocks with
 * more than two table codes (a plain list of tables, then a list of chairs) are not pairs and are skipped.
 * Returns array(table code => chair code).
 */
function pl_extract_dining_pairs($path)
{
	list($wb, $error) = pl_xlsx_open($path);
	if ($error !== null) {
		return array();
	}
	list($zip, $shared, $sheets) = $wb;
	$pairs = array();
	foreach ($sheets as $sh) {
		if (!preg_match('/din/i', $sh[0])) {
			continue;
		}
		$tables = array();
		foreach (pl_xlsx_rows($zip, $shared, $sh[1]) as $cells) {
			$line = implode(' | ', $cells);
			if (preg_match('/\bSWDN[\s-]*(\d+)\s*(\(\s*[A-Z]\s*\))?/i', $line, $m)) {
				$tables[] = 'SWDN-' . str_pad($m[1], 2, '0', STR_PAD_LEFT) . (isset($m[2]) ? strtoupper(preg_replace('/\s+/', '', $m[2])) : '');
			} elseif (preg_match('/\bSWCH[\s-]*(\d+)\b(?!\s*\(F\))/i', $line, $m)) {
				if (count($tables) > 0 && count($tables) <= 2) {
					foreach ($tables as $t) {
						if (!isset($pairs[$t])) {
							$pairs[$t] = 'SWCH-' . str_pad($m[1], 2, '0', STR_PAD_LEFT);
						}
					}
				}
				$tables = array();
			}
		}
	}
	$zip->close();
	return $pairs;
}

/** Save table -> chair pairs (kind chair). Returns the number saved. */
function pl_save_companions($connect, $pairs, $source)
{
	if (!(($t = mysqli_query($connect, "SHOW TABLES LIKE 'product_companions'")) && mysqli_num_rows($t) > 0)) {
		return 0;
	}
	$n = 0;
	foreach ($pairs as $table => $chair) {
		if (mysqli_query($connect, "INSERT INTO product_companions (product_code, companion_code, kind, source, updated_at) VALUES ('"
			. mysqli_real_escape_string($connect, $table) . "', '" . mysqli_real_escape_string($connect, $chair) . "', 'chair', '"
			. mysqli_real_escape_string($connect, $source) . "', NOW()) ON DUPLICATE KEY UPDATE companion_code = VALUES(companion_code), source = VALUES(source), updated_at = NOW()")) {
			$n++;
		}
	}
	return $n;
}

/** Save series names; names added by hand on the settings page are kept. Returns the number saved. */
function pl_save_series($connect, $series, $source, $user)
{
	if (!(($t = mysqli_query($connect, "SHOW TABLES LIKE 'product_series'")) && mysqli_num_rows($t) > 0)) {
		return 0;
	}
	$n = 0;
	$e = function ($v) use ($connect) { return mysqli_real_escape_string($connect, (string) $v); };
	foreach ($series as $prefix => $name) {
		if (mysqli_query($connect, "INSERT INTO product_series (prefix, name, source, updated_by, updated_at) VALUES ('" . $e($prefix) . "', '" . $e($name) . "', '"
			. $e($source) . "', '" . $e($user) . "', NOW()) ON DUPLICATE KEY UPDATE name = IF(source = 'manual', name, VALUES(name)),
			source = IF(source = 'manual', source, VALUES(source)), updated_at = IF(source = 'manual', updated_at, NOW())")) {
			$n++;
		}
	}
	return $n;
}
