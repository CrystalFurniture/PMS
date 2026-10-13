<?php
/**
 * Build the pilot/benchmark database: a copy of the representative catalogue
 * (categories, products, pricelist, permission groups) taken READ-ONLY from the
 * source database, plus empty quotation tables and named pilot users.
 * Customer data (tbl_quotation rows, real users) is never copied.
 *
 *   php benchmark/pilot_db.php --config=benchmark/benchmark_config.local.php [--replace]
 *
 * Uses "source_db" (read-only) and "db" (target) from the benchmark config.
 * Refuses to write to a database named "pms" or to the source itself.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("Command line only.\n");
}
$opts = getopt('', array('config:', 'replace'));
$bench = !empty($opts['config']) ? include $opts['config'] : null;
if (!is_array($bench) || empty($bench['source_db']) || empty($bench['db'])) {
	fwrite(STDERR, "Usage: php benchmark/pilot_db.php --config=FILE [--replace]  (config needs source_db and db)\n");
	exit(1);
}
$src = $bench['source_db'];
$dst = $bench['db'];
if (strtolower($dst['name']) === 'pms') {
	fwrite(STDERR, "Refusing to write to a database named 'pms'.\n");
	exit(1);
}
if ($src['host'] === $dst['host'] && (int) $src['port'] === (int) $dst['port'] && $src['name'] === $dst['name']) {
	fwrite(STDERR, "Source and target are the same database.\n");
	exit(1);
}

$s = @mysqli_connect($src['host'], $src['user'], $src['pass'], $src['name'], (int) $src['port']);
if (!$s) {
	fwrite(STDERR, "Cannot connect to the source database.\n");
	exit(1);
}
mysqli_set_charset($s, 'utf8mb4');
mysqli_query($s, 'SET SESSION TRANSACTION READ ONLY');
mysqli_query($s, 'START TRANSACTION READ ONLY');

$t = @mysqli_connect($dst['host'], $dst['user'], $dst['pass'], '', (int) $dst['port']);
if (!$t) {
	fwrite(STDERR, "Cannot connect to the target server.\n");
	exit(1);
}
mysqli_set_charset($t, 'utf8mb4');
$name = $dst['name'];
$esc = '`' . str_replace('`', '', $name) . '`';
mysqli_query($t, "CREATE DATABASE IF NOT EXISTS $esc");
mysqli_select_db($t, $name);
mysqli_query($t, "SET SESSION sql_mode='NO_ZERO_IN_DATE,NO_ZERO_DATE,NO_ENGINE_SUBSTITUTION'");

$tables = array('categories' => true, 'products' => true, 'pricelist' => true, 'groups' => true, 'users' => false, 'user_group' => false, 'tbl_quotation' => false);
$existing = array();
$r = mysqli_query($t, 'SHOW TABLES');
while ($r && $row = mysqli_fetch_row($r)) {
	$existing[] = $row[0];
}
$clash = array_intersect(array_keys($tables), $existing);
if ($clash && !isset($opts['replace'])) {
	fwrite(STDERR, "Target already has tables (" . implode(', ', $clash) . "). Re-run with --replace to rebuild them.\n");
	exit(1);
}
foreach ($tables as $table => $copyRows) {
	$create = mysqli_fetch_row(mysqli_query($s, "SHOW CREATE TABLE `$table`"));
	if (!$create) {
		fwrite(STDERR, "Source table $table not found.\n");
		exit(1);
	}
	mysqli_query($t, "DROP TABLE IF EXISTS `$table`");
	if (!mysqli_query($t, $create[1])) {
		fwrite(STDERR, "Could not create $table: " . mysqli_error($t) . "\n");
		exit(1);
	}
	if (!$copyRows) {
		echo sprintf("  %-14s structure only (no customer or user data copied)\n", $table);
		continue;
	}
	$res = mysqli_query($s, "SELECT * FROM `$table`", MYSQLI_USE_RESULT);
	$n = 0;
	$batch = array();
	while ($row = mysqli_fetch_assoc($res)) {
		$vals = array();
		foreach ($row as $v) {
			$vals[] = $v === null ? 'NULL' : "'" . mysqli_real_escape_string($t, $v) . "'";
		}
		$batch[] = '(' . implode(',', $vals) . ')';
		$cols = '`' . implode('`,`', array_keys($row)) . '`';
		if (count($batch) === 200) {
			mysqli_query($t, "INSERT INTO `$table` ($cols) VALUES " . implode(',', $batch)) or die(mysqli_error($t));
			$batch = array();
		}
		$n++;
	}
	mysqli_free_result($res);
	if ($batch) {
		mysqli_query($t, "INSERT INTO `$table` ($cols) VALUES " . implode(',', $batch)) or die(mysqli_error($t));
	}
	echo sprintf("  %-14s %d rows copied\n", $table, $n);
}
mysqli_query($s, 'COMMIT');

foreach (isset($bench['pilot_users']) ? $bench['pilot_users'] : array() as $i => $u) {
	$q = function ($v) use ($t) { return "'" . mysqli_real_escape_string($t, (string) $v) . "'"; };
	mysqli_query($t, 'INSERT INTO users (username, password, email, firstname, lastname, phone, otp, gender) VALUES ('
		. $q($u['username']) . ',' . $q($u['password']) . ',' . $q($u['email']) . ',' . $q($u['firstname']) . ",'Pilot','',0,0)") or die(mysqli_error($t));
	$uid = mysqli_insert_id($t);
	mysqli_query($t, 'INSERT INTO user_group (user_id, group_id) VALUES (' . (int) $uid . ',' . (int) $u['group_id'] . ')');
	echo "  pilot user    {$u['email']} (group {$u['group_id']})\n";
}

// Catalogue facts that affect matching and review.
$facts = array(
	'products' => 'SELECT COUNT(*) FROM products',
	'products with exactly one price row (quotable)' => 'SELECT COUNT(*) FROM (SELECT p.id FROM products p JOIN pricelist pl ON pl.p_prodId = p.id GROUP BY p.id HAVING COUNT(*) = 1) x',
	'products with no price row (never offered)' => 'SELECT COUNT(*) FROM products p LEFT JOIN pricelist pl ON pl.p_prodId = p.id WHERE pl.p_id IS NULL',
	'products with several price rows (never offered)' => 'SELECT COUNT(*) FROM (SELECT p_prodId FROM pricelist GROUP BY p_prodId HAVING COUNT(*) > 1) x',
	'names shared by several quotable products' => 'SELECT COUNT(*) FROM (SELECT p.name FROM products p JOIN pricelist pl ON pl.p_prodId = p.id GROUP BY p.name HAVING COUNT(DISTINCT p.id) > 1) x',
	'products with an empty colour' => "SELECT COUNT(*) FROM products WHERE TRIM(color) = ''",
	'products with an empty dimension' => "SELECT COUNT(*) FROM products WHERE TRIM(dimension) = ''",
	'products with an empty thumbnail path' => "SELECT COUNT(*) FROM products WHERE TRIM(thumb) = ''",
);
echo "Catalogue facts ($name):\n";
foreach ($facts as $label => $sql) {
	$v = mysqli_fetch_row(mysqli_query($t, $sql));
	echo sprintf("  %6d  %s\n", $v[0], $label);
}
echo "Done. Point the pilot app's DB/conn.php and the benchmark config at '$name'.\n";
