<?php
/**
 * CLI migration: create the state price-list tables (safe to run more than once).
 *
 *   php cli/migrate_state_pricelists.php --database=pms_pilot
 *   php cli/migrate_state_pricelists.php --database=pms --i-approve-live   (only with approval)
 *
 * Uses the host/user/password from DB/conn.php; only the database name is chosen here.
 *
 * price_states     one row per state price list (code, name, when and from which file it was loaded)
 * state_pricelist  one row per product per state: MRP / dealer (DP) / special price
 *
 * The standard pricelist table and tbl_quotation are not changed. Saved quotation
 * lines record which list their price came from (price_list) inside q_items.
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("This script may only be run from the command line.\n");
}
$opts = getopt('', array('database:', 'i-approve-live', 'host:', 'port:', 'user:'));
if (empty($opts['database'])) {
	fwrite(STDERR, "Usage: php cli/migrate_state_pricelists.php --database=NAME [--i-approve-live]\n");
	exit(1);
}
$database = $opts['database'];
if (strtolower($database) === 'pms' && !isset($opts['i-approve-live'])) {
	fwrite(STDERR, "Refusing to change the live 'pms' database without --i-approve-live.\n");
	exit(1);
}
$conn = file_get_contents(__DIR__ . '/../DB/conn.php');
$get = function ($name) use ($conn) {
	preg_match('/\$' . $name . '\s*=\s*"([^"]*)"/', $conn, $m);
	return isset($m[1]) ? $m[1] : '';
};
// Optional overrides (for a separate test server): --host --port --user; password from PMS_DB_PASS.
$host = isset($opts['host']) ? $opts['host'] : $get('host');
$port = isset($opts['port']) ? (int) $opts['port'] : 3306;
$user = isset($opts['user']) ? $opts['user'] : $get('user');
$pass = isset($opts['user']) ? (string) getenv('PMS_DB_PASS') : $get('pass');
$db = @mysqli_connect($host, $user, $pass, $database, $port);
if (!$db) {
	fwrite(STDERR, "Cannot connect to database '$database'.\n");
	exit(1);
}
$sql = array(
	"CREATE TABLE IF NOT EXISTS price_states (
		code VARCHAR(10) NOT NULL,
		name VARCHAR(100) NOT NULL,
		active TINYINT(1) NOT NULL DEFAULT 1,
		source_file VARCHAR(255) NOT NULL DEFAULT '',
		rows_loaded INT NOT NULL DEFAULT 0,
		updated_by VARCHAR(255) NOT NULL DEFAULT '',
		updated_at DATETIME NULL,
		PRIMARY KEY (code)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8",
	"CREATE TABLE IF NOT EXISTS state_pricelist (
		id INT NOT NULL AUTO_INCREMENT,
		state_code VARCHAR(10) NOT NULL,
		p_prodId INT NOT NULL,
		mrp_price DECIMAL(12,2) NULL,
		dp_price DECIMAL(12,2) NULL,
		special_price DECIMAL(12,2) NULL,
		PRIMARY KEY (id),
		UNIQUE KEY uq_state_product (state_code, p_prodId),
		KEY idx_product (p_prodId)
	) ENGINE=InnoDB DEFAULT CHARSET=utf8",
);
foreach ($sql as $q) {
	if (!mysqli_query($db, $q)) {
		fwrite(STDERR, 'Migration failed: ' . mysqli_error($db) . "\n");
		exit(1);
	}
}
echo "State price-list tables ready in '$database'.\n";
