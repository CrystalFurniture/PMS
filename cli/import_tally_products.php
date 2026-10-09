<?php
/**
 * CLI: import missing "Finished Goods" products from tally_io.itemmaster into pms.products.
 *
 * Usage (run from a shell on the server, e.g. via php.exe):
 *   php import_tally_products.php --dry-run
 *   php import_tally_products.php --dry-run --limit=10
 *   php import_tally_products.php --limit=10
 *   php import_tally_products.php --item-id=12345
 *   php import_tally_products.php
 *   php import_tally_products.php --no-backfill
 *
 * This project has no CLI/artisan framework (Core/ is plain procedural PHP),
 * so this is a standalone script rather than a framework command - kept
 * deliberately web-inaccessible below since it can write to the DB and
 * copy files.
 */

if (PHP_SAPI !== 'cli') {
    header('HTTP/1.1 403 Forbidden');
    die("This script may only be run from the command line.\n");
}

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/TallyProductImportService.php';

$longopts = array('dry-run', 'limit:', 'item-id:', 'no-backfill', 'help');
$opts = getopt('', $longopts);

if (isset($opts['help'])) {
    echo "Usage: php import_tally_products.php [--dry-run] [--limit=N] [--item-id=N] [--no-backfill]\n";
    exit(0);
}

$options = array(
    'dry_run'  => isset($opts['dry-run']),
    'limit'    => isset($opts['limit']) ? (int) $opts['limit'] : null,
    'item_id'  => isset($opts['item-id']) ? (int) $opts['item-id'] : null,
    'backfill' => !isset($opts['no-backfill']),
);

$connect = mysqli_connect(IMPORT_DB_HOST, IMPORT_DB_USER, IMPORT_DB_PASS, IMPORT_DB_NAME);
if (!$connect) {
    fwrite(STDERR, 'DB connection failed: ' . mysqli_connect_error() . "\n");
    exit(1);
}
mysqli_set_charset($connect, 'utf8mb4');

try {
    $service = new TallyProductImportService($connect, $options);
    $service->run();
} catch (Exception $e) {
    fwrite(STDERR, 'Import aborted: ' . $e->getMessage() . "\n");
    exit(1);
}

exit(0);
