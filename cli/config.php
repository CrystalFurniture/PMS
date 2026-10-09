<?php
/**
 * Config for the Tally -> PMS product import CLI.
 *
 * The rest of this app (Core/DB/conn.php, the Tally app's variables/conn.php)
 * hardcodes DB credentials directly in PHP rather than using a .env file -
 * there is no dotenv/config-loader anywhere in this project. This file keeps
 * that same convention (so it stays a native part of the app) but centralizes
 * the values in one place instead of repeating them per script.
 */

define('IMPORT_DB_HOST', 'localhost');
define('IMPORT_DB_USER', 'root');
define('IMPORT_DB_PASS', '');
define('IMPORT_DB_NAME', 'pms');          // primary connection selects pms; tally_io is queried via fully-qualified table names on the same server/user
define('IMPORT_TALLY_DB', 'tally_io');

// Core/cli/config.php -> Core/cli -> Core -> PMS -> htdocs -> tally
define('IMPORT_TALLY_APP_ROOT', dirname(dirname(dirname(__DIR__))) . '/tally');
define('IMPORT_TALLY_IMAGE_SUBDIR', 'uploads/item_images');

// Core/cli/config.php -> Core
define('IMPORT_PMS_CORE_ROOT', dirname(__DIR__));
define('IMPORT_LOG_DIR', __DIR__ . '/logs');
