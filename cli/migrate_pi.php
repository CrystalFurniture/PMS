<?php
/**
 * CLI migration for the AI Assistant and Proforma Invoices (safe to run more than once).
 *
 *   php cli/migrate_pi.php --database=pms_local
 *   php cli/migrate_pi.php --database=pms --i-approve-live   (only with approval)
 *
 * Uses the host/user/password from DB/conn.php; only the database name is chosen here.
 * Optional --host --port --user (password from PMS_DB_PASS) for a separate test server.
 *
 * ai_conversations / ai_messages  chat history, one owner per conversation
 * ai_pi_drafts                    the authoritative draft per conversation (JSON + version + confirmation)
 * pi_documents                    generated Proforma Invoices (immutable snapshot + PDF file name)
 * doc_templates                   company details, bank details, terms and signatory used on documents.
 *                                 Seeded from the quotation print template in use (ExportToExcelDp.php);
 *                                 later versions are added as new rows, never edited in place.
 * price_states                    gains version (raised on every upload) and valid_until (NULL = no expiry).
 */
if (PHP_SAPI !== 'cli') {
	header('HTTP/1.1 403 Forbidden');
	die("This script may only be run from the command line.\n");
}
$opts = getopt('', array('database:', 'i-approve-live', 'host:', 'port:', 'user:'));
if (empty($opts['database'])) {
	fwrite(STDERR, "Usage: php cli/migrate_pi.php --database=NAME [--i-approve-live]\n");
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
$host = isset($opts['host']) ? $opts['host'] : $get('host');
$port = isset($opts['port']) ? (int) $opts['port'] : 3306;
$user = isset($opts['user']) ? $opts['user'] : $get('user');
$pass = isset($opts['user']) ? (string) getenv('PMS_DB_PASS') : $get('pass');
$db = @mysqli_connect($host, $user, $pass, $database, $port);
if (!$db) {
	fwrite(STDERR, "Cannot connect to database '$database'.\n");
	exit(1);
}
mysqli_set_charset($db, 'utf8mb4');

$run = function ($q) use ($db) {
	if (!mysqli_query($db, $q)) {
		fwrite(STDERR, 'Migration failed: ' . mysqli_error($db) . "\n");
		exit(1);
	}
};
$hasColumn = function ($table, $column) use ($db) {
	$r = mysqli_query($db, "SHOW COLUMNS FROM `$table` LIKE '" . mysqli_real_escape_string($db, $column) . "'");
	return $r && mysqli_num_rows($r) > 0;
};

$run("CREATE TABLE IF NOT EXISTS ai_conversations (
	id CHAR(32) NOT NULL,
	user_id INT NOT NULL,
	username VARCHAR(255) NOT NULL,
	title VARCHAR(200) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL,
	updated_at DATETIME NOT NULL,
	archived TINYINT(1) NOT NULL DEFAULT 0,
	PRIMARY KEY (id),
	KEY idx_user (user_id, updated_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$run("CREATE TABLE IF NOT EXISTS ai_messages (
	id INT NOT NULL AUTO_INCREMENT,
	conversation_id CHAR(32) NOT NULL,
	role VARCHAR(16) NOT NULL,
	client_id VARCHAR(64) NULL,
	body MEDIUMTEXT NOT NULL,
	created_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_client (conversation_id, client_id),
	KEY idx_conversation (conversation_id, id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$run("CREATE TABLE IF NOT EXISTS ai_pi_drafts (
	conversation_id CHAR(32) NOT NULL,
	version INT NOT NULL DEFAULT 1,
	data MEDIUMTEXT NOT NULL,
	confirmed_version INT NULL,
	confirmed_hash CHAR(64) NULL,
	document_id INT NULL,
	busy_until DATETIME NULL,
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$run("CREATE TABLE IF NOT EXISTS pi_documents (
	id INT NOT NULL AUTO_INCREMENT,
	pi_number VARCHAR(50) NOT NULL,
	fiscal_year VARCHAR(9) NOT NULL,
	seq INT NOT NULL,
	conversation_id CHAR(32) NOT NULL,
	user_id INT NOT NULL,
	username VARCHAR(255) NOT NULL,
	draft_version INT NOT NULL,
	preview_hash CHAR(64) NOT NULL,
	idempotency_key VARCHAR(100) NOT NULL,
	data MEDIUMTEXT NOT NULL,
	file_name VARCHAR(255) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_number (pi_number),
	UNIQUE KEY uq_sequence (fiscal_year, seq),
	UNIQUE KEY uq_idempotency (idempotency_key),
	KEY idx_conversation (conversation_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

$run("CREATE TABLE IF NOT EXISTS doc_templates (
	id INT NOT NULL AUTO_INCREMENT,
	doc_key VARCHAR(50) NOT NULL,
	version INT NOT NULL,
	content MEDIUMTEXT NOT NULL,
	source VARCHAR(255) NOT NULL,
	status VARCHAR(30) NOT NULL,
	created_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_key_version (doc_key, version)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Rates of the last preview shown, to list differences if prices change before finalization.
if (!$hasColumn('ai_pi_drafts', 'preview_snapshot')) {
	$run("ALTER TABLE ai_pi_drafts ADD COLUMN preview_snapshot MEDIUMTEXT NULL");
}

// Product retrieval index (one row per quotable product id) and approved documents.
$run("CREATE TABLE IF NOT EXISTS product_index (
	product_id INT NOT NULL,
	source_hash CHAR(40) NOT NULL,
	source_text TEXT NOT NULL,
	model VARCHAR(120) NOT NULL,
	dims INT NOT NULL,
	vector MEDIUMBLOB NOT NULL,
	indexed_at DATETIME NOT NULL,
	PRIMARY KEY (product_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$run("CREATE TABLE IF NOT EXISTS retrieval_meta (
	k VARCHAR(60) NOT NULL,
	v TEXT NOT NULL,
	PRIMARY KEY (k)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$run("CREATE TABLE IF NOT EXISTS approved_documents (
	id INT NOT NULL AUTO_INCREMENT,
	doc_key VARCHAR(60) NOT NULL,
	title VARCHAR(200) NOT NULL,
	doc_type VARCHAR(40) NOT NULL,
	version INT NOT NULL,
	status VARCHAR(20) NOT NULL DEFAULT 'draft',
	effective_from DATE NOT NULL,
	effective_to DATE NULL,
	states VARCHAR(400) NOT NULL DEFAULT 'ALL',
	source_location VARCHAR(255) NOT NULL DEFAULT '',
	content MEDIUMTEXT NOT NULL,
	created_by VARCHAR(255) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL,
	approved_by VARCHAR(255) NULL,
	approved_at DATETIME NULL,
	PRIMARY KEY (id),
	UNIQUE KEY uq_doc_version (doc_key, version),
	KEY idx_type_status (doc_type, status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$run("CREATE TABLE IF NOT EXISTS document_chunks (
	id INT NOT NULL AUTO_INCREMENT,
	document_id INT NOT NULL,
	seq INT NOT NULL,
	text TEXT NOT NULL,
	model VARCHAR(120) NULL,
	vector MEDIUMBLOB NULL,
	PRIMARY KEY (id),
	KEY idx_document (document_id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// State price lists: identity/version and optional expiry.
$exists = mysqli_query($db, "SHOW TABLES LIKE 'price_states'");
if ($exists && mysqli_num_rows($exists) > 0) {
	if (!$hasColumn('price_states', 'version')) {
		$run("ALTER TABLE price_states ADD COLUMN version INT NOT NULL DEFAULT 1");
	}
	if (!$hasColumn('price_states', 'valid_until')) {
		$run("ALTER TABLE price_states ADD COLUMN valid_until DATE NULL");
	}
	// Kinds of list: state (one state), region (several states, e.g. South), master (company master list,
	// used before the software's own prices). effective_from = "Price list effect from" date.
	if (!$hasColumn('price_states', 'kind')) {
		$run("ALTER TABLE price_states ADD COLUMN kind VARCHAR(10) NOT NULL DEFAULT 'state'");
	}
	if (!$hasColumn('price_states', 'covers')) {
		$run("ALTER TABLE price_states ADD COLUMN covers VARCHAR(400) NOT NULL DEFAULT ''");
	}
	if (!$hasColumn('price_states', 'effective_from')) {
		$run("ALTER TABLE price_states ADD COLUMN effective_from DATE NULL");
	}
	// Rates per list row: MRP, DP and NET (a third category: no discount). variant = '' or TEAK / MANGO etc.
	if (!$hasColumn('state_pricelist', 'net_price')) {
		$run("ALTER TABLE state_pricelist ADD COLUMN net_price DECIMAL(12,2) NULL");
	}
	if (!$hasColumn('state_pricelist', 'variant')) {
		$run("ALTER TABLE state_pricelist ADD COLUMN variant VARCHAR(20) NOT NULL DEFAULT ''");
		$run("ALTER TABLE state_pricelist DROP INDEX uq_state_product, ADD UNIQUE KEY uq_state_product_variant (state_code, p_prodId, variant)");
	}
	if (!$hasColumn('state_pricelist', 'source')) {
		$run("ALTER TABLE state_pricelist ADD COLUMN source VARCHAR(160) NOT NULL DEFAULT ''");
	}
} else {
	echo "Note: price_states does not exist yet; run cli/migrate_state_pricelists.php first, then this script again.\n";
}

// Seed document content from the quotation print template currently in use (only once).
$seeded = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM doc_templates"))[0];
if ((int) $seeded === 0) {
	$source = 'ExportToExcelDp.php / ExportToExcelMRP.php (quotation print template in use)';
	$templates = array(
		'company' => array(
			'name' => 'CRYSTAL FURNITECH PRIVATE LIMITED',
			'address' => 'Office : Umiya Chambers, Darodkar Square, Central Avenue, Nagpur-440032.',
			'phone' => 'Phone: 0712-2766405, 2770867, Fax:-6611795 M :- 9922919052',
			'email' => 'rakesh@crystalfurniture.in',
			'web' => 'www.crystalfurnitech.com',
			'gstin' => '27AAICC6954C1ZL',
			'state_code' => '27',
			'state_name' => 'Maharashtra',
		),
		'bank' => array(
			'heading' => 'Bank Details ( applicable in case of direct deposite )',
			'bank' => 'Axis Bank Ltd.',
			'branch' => 'Lakkadganj, Nagpur',
			'account_no' => '921030022445688',
			'ifsc' => 'UTIB0000330',
			'note' => 'The template prints IFSC UTIB0000330 in one branch and UTBI0000330 in the other; UTIB0000330 is used here. Confirm with accounts.',
		),
		'terms' => array(
			'heading' => 'Terms and conditions as applicable to this Quotation',
			'lines' => array(
				'Estimated delivery time from the date of confirmation of order, PO & Advance and Technical appoval ( Color & Etc. ) is 4- 6 Weeks.',
				'Once the order is proceed, changes in specification, color etc. will not be possible.',
				'Any extra work not mentioned in qoutation will be charged extra.',
				'In case of any specific exemptions from taxes, duties, the buyer shall provide the necessary documents.',
				'Payment Condition :- 50% advance at the time of order confirmation and remaining 50% before delivery.',
				'Transportation & Material Handling ( Unloading ) Charges Extra.',
				'Installation cost Extra. ( Lodging & Boarding Provided By You ).',
				"Rates are applicable only on crystal's standard colours and shades .",
				'Above Quotation is valid for 4 weeks.',
				'3 years warranty for any manufacturing defect, *Conditions apply.',
			),
		),
		'signatory' => array('for' => 'CRYSTAL FURNITECH PRIVATE LIMITED', 'name' => 'Ajit Bhartia', 'title' => '(CEO)'),
		'tax' => array('label' => 'GST', 'rate' => 18, 'applies_to' => 'basic amount after discount',
			'note' => 'Current documents charge a single 18% GST line; no CGST/SGST/IGST split exists in the application.'),
	);
	foreach ($templates as $key => $content) {
		$run("INSERT INTO doc_templates (doc_key, version, content, source, status, created_at) VALUES ('" . $key . "', 1, '"
			. mysqli_real_escape_string($db, json_encode($content, JSON_UNESCAPED_UNICODE)) . "', '" . mysqli_real_escape_string($db, $source) . "', 'in_use', NOW())");
	}
	echo "Seeded doc_templates (company, bank, terms, signatory, tax) from the quotation template in use.\n";
}
// Conversations the user pinned: listed first and never removed by the "latest N conversations" clean-up.
if (!$hasColumn('ai_conversations', 'pinned')) {
	$run("ALTER TABLE ai_conversations ADD COLUMN pinned TINYINT(1) NOT NULL DEFAULT 0");
}

// Order of the default (master) lists: lower first (Master price list 1, Office furniture 2).
if (!$hasColumn('price_states', 'priority')) {
	$run("ALTER TABLE price_states ADD COLUMN priority INT NOT NULL DEFAULT 100");
}

// An approved PI is also saved as a row of tbl_quotation (the table manual quotations use); this links the two.
if (!$hasColumn('pi_documents', 'quotation_id')) {
	$run("ALTER TABLE pi_documents ADD COLUMN quotation_id INT NULL");
}

// Quotation approval: '' = awaiting approval, 'approved' (Convert to order is offered), 'ordered'.
if (!$hasColumn('tbl_quotation', 'q_status')) {
	$run("ALTER TABLE tbl_quotation ADD COLUMN q_status VARCHAR(20) NOT NULL DEFAULT '',
		ADD COLUMN q_approvedBy VARCHAR(200) NOT NULL DEFAULT '', ADD COLUMN q_approvedAt DATETIME NULL, ADD COLUMN q_orderedAt DATETIME NULL");
}

// Quotations / sale orders sent by email and WhatsApp (includes/send_lib.php): one row per recipient,
// and the manager each user entered last (prefilled next time). app = 'pms' or 'tally'.
$run("CREATE TABLE IF NOT EXISTS send_log (
	id INT NOT NULL AUTO_INCREMENT,
	app VARCHAR(10) NOT NULL,
	doc_type VARCHAR(20) NOT NULL,
	doc_ref VARCHAR(100) NOT NULL,
	username VARCHAR(200) NOT NULL,
	field VARCHAR(20) NOT NULL,
	recipient VARCHAR(200) NOT NULL,
	ok TINYINT(1) NOT NULL,
	error VARCHAR(500) NOT NULL DEFAULT '',
	created_at DATETIME NOT NULL,
	PRIMARY KEY (id),
	KEY doc (app, doc_type, doc_ref)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
$run("CREATE TABLE IF NOT EXISTS send_managers (
	app VARCHAR(10) NOT NULL,
	username VARCHAR(200) NOT NULL,
	email VARCHAR(200) NOT NULL DEFAULT '',
	whatsapp VARCHAR(20) NOT NULL DEFAULT '',
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (app, username)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Series names by code prefix (CS-157 = Gothic): from the price lists' series headings, or added on AI Assistant settings.
// The catalogue search adds the name to every product whose name starts with the prefix.
$run("CREATE TABLE IF NOT EXISTS product_series (
	prefix VARCHAR(40) NOT NULL,
	name VARCHAR(80) NOT NULL,
	source VARCHAR(160) NOT NULL DEFAULT '',
	updated_by VARCHAR(100) NOT NULL DEFAULT '',
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (prefix)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Products that go with another one: the chairs of a dining table (SWDN-07 Sabi -> SWCH-14 Vedi), from the price
// lists' dining sheets. The assistant adds the chairs for "6 seater dining table" (6 chairs per table).
$run("CREATE TABLE IF NOT EXISTS product_companions (
	product_code VARCHAR(60) NOT NULL,
	companion_code VARCHAR(60) NOT NULL,
	kind VARCHAR(20) NOT NULL DEFAULT 'chair',
	source VARCHAR(160) NOT NULL DEFAULT '',
	updated_at DATETIME NOT NULL,
	PRIMARY KEY (product_code, kind)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");

// Version 2 from the company's current PI format (sample PI for Hind Furniture, 12-10-2026): office address
// at Ram Bagh, signature block "For, CRYSTAL FURNITECH PRIVATE LIMITED" without a name, IFSC UTIB0000330 confirmed.
$sampleSource = 'Sample PI Hind Furniture 12-10-2026 (current PI format)';
$updates = array(
	'company' => function ($c) {
		$c['address'] = "6th Floor, Plot No. 12, Ram Bagh, Great Nag Road,\nBaidyanath Chowk, Nagpur - 440027, Maharashtra, India";
		return $c;
	},
	'signatory' => function ($c) { $c['name'] = ''; $c['title'] = ''; return $c; },
	'bank' => function ($c) { unset($c['note']); return $c; },
);
foreach ($updates as $key => $change) {
	$done = mysqli_fetch_row(mysqli_query($db, "SELECT COUNT(*) FROM doc_templates WHERE doc_key = '$key' AND source = '" . mysqli_real_escape_string($db, $sampleSource) . "'"))[0];
	$cur = mysqli_fetch_assoc(mysqli_query($db, "SELECT version, content FROM doc_templates WHERE doc_key = '$key' ORDER BY version DESC LIMIT 1"));
	if ((int) $done === 0 && $cur) {
		$content = $change(json_decode($cur['content'], true));
		$run("INSERT INTO doc_templates (doc_key, version, content, source, status, created_at) VALUES ('" . $key . "', " . ((int) $cur['version'] + 1) . ", '"
			. mysqli_real_escape_string($db, json_encode($content, JSON_UNESCAPED_UNICODE)) . "', '" . mysqli_real_escape_string($db, $sampleSource) . "', 'in_use', NOW())");
		echo "Added doc_templates $key version " . ((int) $cur['version'] + 1) . " from the current PI format.\n";
	}
}
echo "AI Assistant / PI tables ready in '$database'.\n";
