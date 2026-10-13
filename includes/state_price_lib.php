<?php
/**
 * State price lists: read a state's Excel/CSV price list (the same format as the
 * standard price list: Code | Particulars | Description | Size | Colour | MRP | Dealer/DP | Special)
 * and match each Code to a product.
 *
 * Matching is by product name, ignoring case and spaces. A code shared by several
 * products, or not found, is reported and skipped: prices are never guessed.
 * Requires DB/conn.php and the spreadsheet reader in library/.
 */

function qt_sp_key($s)
{
	$s = function_exists('mb_strtolower') ? mb_strtolower((string) $s, 'UTF-8') : strtolower((string) $s);
	return preg_replace('/\s+/u', '', $s);
}

/** "₹ 42,731.20" -> 42731.2; '' -> null; anything else non-numeric -> false. */
function qt_sp_price($v)
{
	$v = trim(str_replace(array('₹', ',', 'Rs.', 'Rs', ' ', "\xC2\xA0"), '', (string) $v));
	if ($v === '' || $v === '-') {
		return null;
	}
	if (!is_numeric($v) || (float) $v < 0) {
		return false;
	}
	return round((float) $v, 2);
}

/** Header columns of a price-list sheet, or null when the row is not a header. */
function qt_sp_header($row)
{
	$cols = array();
	foreach ($row as $i => $cell) {
		$c = strtolower(trim((string) $cell));
		if ($c === '') {
			continue;
		}
		if (!isset($cols['code']) && preg_match('/^(item\s*)?code$/', $c)) {
			$cols['code'] = $i;
		} elseif (!isset($cols['mrp']) && strpos($c, 'mrp') !== false) {
			$cols['mrp'] = $i;
		} elseif (!isset($cols['dp']) && (strpos($c, 'dealer') !== false || preg_match('/^dp\b/', $c))) {
			$cols['dp'] = $i;
		} elseif (!isset($cols['special']) && strpos($c, 'special') !== false) {
			$cols['special'] = $i;
		}
	}
	return (isset($cols['code']) && (isset($cols['mrp']) || isset($cols['dp']))) ? $cols : null;
}

/**
 * Read a price-list file and match it to products.
 * Returns array(
 *   'prices' => array(product_id => array(mrp, dp, special, code, where)),
 *   'issues' => list of array(kind, code, where, detail),
 *   'stats'  => counts
 * ).
 */
function qt_sp_read($connect, $path, $originalName)
{
	require_once __DIR__ . '/../library/php-excel-reader/excel_reader2.php';
	require_once __DIR__ . '/../library/SpreadsheetReader.php';

	// Product names -> ids (several ids when a name is shared).
	$byName = array();
	$r = mysqli_query($connect, 'SELECT id, name FROM products');
	while ($r && $p = mysqli_fetch_assoc($r)) {
		$byName[qt_sp_key($p['name'])][] = (int) $p['id'];
	}

	$prices = array();
	$issues = array();
	$stats = array('sheets' => 0, 'sheets_without_header' => 0, 'rows_read' => 0, 'matched' => 0, 'not_found' => 0,
		'ambiguous' => 0, 'invalid_price' => 0, 'duplicate' => 0);

	// The reader picks the format from the file name, so give it the original extension.
	$reader = new SpreadsheetReader($path, $originalName);
	foreach ($reader->Sheets() as $index => $sheetName) {
		$reader->ChangeSheet($index);
		$stats['sheets']++;
		$cols = null;
		$line = 0;
		foreach ($reader as $row) {
			$line++;
			if (!is_array($row)) {
				continue;
			}
			if ($cols === null) {
				$cols = qt_sp_header($row);
				continue;
			}
			$code = isset($row[$cols['code']]) ? trim((string) $row[$cols['code']]) : '';
			if ($code === '') {
				continue;
			}
			$stats['rows_read']++;
			$where = $sheetName . ' row ' . $line;
			$mrp = isset($cols['mrp'], $row[$cols['mrp']]) ? qt_sp_price($row[$cols['mrp']]) : null;
			$dp = isset($cols['dp'], $row[$cols['dp']]) ? qt_sp_price($row[$cols['dp']]) : null;
			$special = isset($cols['special'], $row[$cols['special']]) ? qt_sp_price($row[$cols['special']]) : null;
			if ($mrp === false || $dp === false || $special === false || ($mrp === null && $dp === null)) {
				$stats['invalid_price']++;
				$issues[] = array('invalid_price', $code, $where, 'MRP/Dealer price missing or not a number');
				continue;
			}
			$key = qt_sp_key($code);
			if (!isset($byName[$key])) {
				$stats['not_found']++;
				$issues[] = array('not_found', $code, $where, 'No product with this name');
				continue;
			}
			if (count($byName[$key]) > 1) {
				$stats['ambiguous']++;
				$issues[] = array('ambiguous', $code, $where, count($byName[$key]) . ' products share this name (ids ' . implode(', ', $byName[$key]) . '); not loaded');
				continue;
			}
			$id = $byName[$key][0];
			if (isset($prices[$id])) {
				$stats['duplicate']++;
				$issues[] = array('duplicate', $code, $where, 'Listed again; the first price (' . $prices[$id]['where'] . ') is kept');
				continue;
			}
			$prices[$id] = array('mrp' => $mrp, 'dp' => $dp, 'special' => $special, 'code' => $code, 'where' => $where);
			$stats['matched']++;
		}
		if ($cols === null) {
			$stats['sheets_without_header']++;
			$issues[] = array('no_header', '', $sheetName, 'No header row with Code and MRP/Dealer columns; sheet skipped');
		}
	}
	return array('prices' => $prices, 'issues' => $issues, 'stats' => $stats);
}

/** Replace one state's price list in a single transaction. Returns error text or null. */
function qt_sp_save($connect, $code, $name, $prices, $sourceFile, $user)
{
	$esc = function ($v) use ($connect) { return mysqli_real_escape_string($connect, (string) $v); };
	$num = function ($v) { return $v === null ? 'NULL' : number_format((float) $v, 2, '.', ''); };
	// Every upload is a new version of that state's list (column added by cli/migrate_pi.php).
	$versioned = ($vc = mysqli_query($connect, "SHOW COLUMNS FROM price_states LIKE 'version'")) && mysqli_num_rows($vc) > 0;
	mysqli_query($connect, 'START TRANSACTION');
	$ok = mysqli_query($connect, "INSERT INTO price_states (code, name, active, source_file, rows_loaded, updated_by, updated_at) VALUES ('"
		. $esc($code) . "', '" . $esc($name) . "', 1, '" . $esc($sourceFile) . "', " . count($prices) . ", '" . $esc($user) . "', NOW())
		ON DUPLICATE KEY UPDATE name = VALUES(name), active = 1, source_file = VALUES(source_file), rows_loaded = VALUES(rows_loaded),
		updated_by = VALUES(updated_by), updated_at = VALUES(updated_at)" . ($versioned ? ', version = version + 1' : ''))
		&& mysqli_query($connect, "DELETE FROM state_pricelist WHERE state_code = '" . $esc($code) . "'");
	$batch = array();
	foreach ($prices as $id => $p) {
		if (!$ok) {
			break;
		}
		$batch[] = "('" . $esc($code) . "', " . (int) $id . ', ' . $num($p['mrp']) . ', ' . $num($p['dp']) . ', ' . $num($p['special']) . ')';
		if (count($batch) === 300) {
			$ok = mysqli_query($connect, 'INSERT INTO state_pricelist (state_code, p_prodId, mrp_price, dp_price, special_price) VALUES ' . implode(',', $batch));
			$batch = array();
		}
	}
	if ($ok && $batch) {
		$ok = mysqli_query($connect, 'INSERT INTO state_pricelist (state_code, p_prodId, mrp_price, dp_price, special_price) VALUES ' . implode(',', $batch));
	}
	if (!$ok) {
		$error = mysqli_error($connect);
		mysqli_query($connect, 'ROLLBACK');
		return 'The price list could not be saved: ' . $error;
	}
	mysqli_query($connect, 'COMMIT');
	return null;
}
