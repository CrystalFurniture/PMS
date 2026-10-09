<?php
/**
 * Imports missing "Finished Goods" products from tally_io.itemmaster into
 * pms.products (+ categories, + pricelist), following the same conventions
 * already used by Core/AddProducts.php and Core/AddCategory.php:
 *   - categories folder = "Category/<exact category name>" (not slugified)
 *   - product image saved under that folder, thumbnail auto-generated via GD
 *     into "Category/thumb/" (matches AddProducts.php's 500px-wide resize)
 *   - raw mysqli + string-built SQL (no framework/ORM in this project)
 *
 * Safe to run repeatedly: an item already linked by tally_item_id, or whose
 * name matches an existing product, is skipped rather than re-inserted.
 */

class TallyProductImportService
{
    private $connect;
    private $dryRun;
    private $limit;
    private $itemId;
    private $backfill;

    private $hasTallyIdColumn = false;
    private $categoryCache = array();   // normalized name -> id
    private $tallyIdIndex  = array();   // tally ItemId -> products.id
    private $nameIndex     = array();   // normalized product name -> products.id

    private $stats;
    private $failures = array();
    private $logLines = array();

    public function __construct($connect, $options)
    {
        $this->connect  = $connect;
        $this->dryRun   = !empty($options['dry_run']);
        $this->limit    = isset($options['limit']) ? (int) $options['limit'] : null;
        $this->itemId   = isset($options['item_id']) ? (int) $options['item_id'] : null;
        $this->backfill = !isset($options['backfill']) || $options['backfill'];

        $this->stats = array(
            'finished_goods_found' => 0,
            'already_existing'     => 0,
            'backfilled'           => 0,
            'inserted'             => 0,
            'categories_used'      => 0,
            'categories_created'   => 0,
            'images_copied'        => 0,
            'thumbs_copied'        => 0,
            'images_missing'       => 0,
            'thumbs_missing'       => 0,
            'skipped_no_category'  => 0,
            'failed'               => 0,
        );
    }

    public function run()
    {
        $this->log('Mode: ' . ($this->dryRun ? 'DRY RUN (no DB/file changes)' : 'LIVE IMPORT')
            . ($this->itemId ? " | single item-id={$this->itemId}" : '')
            . ($this->limit !== null ? " | limit={$this->limit}" : ''));

        $this->ensureSchema();
        $this->loadCategoryCache();
        $this->loadExistingProductIndex();

        $where = "im.ItemGroup = 'Finished Goods'";
        if ($this->itemId) {
            $where .= ' AND im.ItemId = ' . (int) $this->itemId;
        }
        $sql = "SELECT ItemId, ItemName, ItemParent, ItemDesc, ItemColor,
                       ItemLength, ItemBreadth, ItemHeight,
                       ItemPrice, ItemPriceMrp, ItemImage, ItemThumb
                FROM " . IMPORT_TALLY_DB . ".itemmaster im
                WHERE $where
                ORDER BY ItemId"
              . ($this->limit !== null ? ' LIMIT ' . (int) $this->limit : '');

        $res = mysqli_query($this->connect, $sql);
        if (!$res) {
            throw new Exception('Could not read itemmaster: ' . mysqli_error($this->connect));
        }

        while ($row = mysqli_fetch_assoc($res)) {
            $this->stats['finished_goods_found']++;
            $this->processItem($row);
        }

        $this->printSummary();
        $this->writeLogFile();
    }

    /* -------------------------------------------------------------- */

    private function ensureSchema()
    {
        $col = mysqli_query($this->connect, "SHOW COLUMNS FROM products LIKE 'tally_item_id'");
        if ($col && mysqli_num_rows($col) > 0) {
            $this->hasTallyIdColumn = true;
            return;
        }

        if ($this->dryRun) {
            $this->log('[DRY-RUN] products.tally_item_id does not exist yet - would add it '
                . '(ALTER TABLE products ADD COLUMN tally_item_id INT NULL, ADD UNIQUE KEY). '
                . 'Running in dry-run without it: ID-based dedupe preview is skipped, name-matching only.');
            $this->hasTallyIdColumn = false;
            return;
        }

        $ok = mysqli_query(
            $this->connect,
            "ALTER TABLE products
                ADD COLUMN tally_item_id INT NULL DEFAULT NULL AFTER id,
                ADD UNIQUE KEY uq_products_tally_item_id (tally_item_id)"
        );
        if (!$ok) {
            throw new Exception('Failed to add products.tally_item_id column: ' . mysqli_error($this->connect));
        }
        $this->hasTallyIdColumn = true;
        $this->log('Added products.tally_item_id column + unique index (one-time schema change).');
    }

    private function loadCategoryCache()
    {
        $res = mysqli_query($this->connect, 'SELECT id, name FROM categories');
        while ($res && $row = mysqli_fetch_assoc($res)) {
            $this->categoryCache[$this->normalize($row['name'])] = (int) $row['id'];
        }
    }

    private function loadExistingProductIndex()
    {
        if ($this->hasTallyIdColumn) {
            $res = mysqli_query($this->connect, 'SELECT id, tally_item_id FROM products WHERE tally_item_id IS NOT NULL');
            while ($res && $row = mysqli_fetch_assoc($res)) {
                $this->tallyIdIndex[(int) $row['tally_item_id']] = (int) $row['id'];
            }
        }
        $res2 = mysqli_query($this->connect, 'SELECT id, name FROM products');
        while ($res2 && $row = mysqli_fetch_assoc($res2)) {
            $this->nameIndex[$this->normalize($row['name'])] = (int) $row['id'];
        }
    }

    /* -------------------------------------------------------------- */

    private function processItem($row)
    {
        $itemId = (int) $row['ItemId'];
        $name   = trim((string) $row['ItemName']);

        if ($name === '') {
            $this->stats['failed']++;
            $this->failures[] = array('item_id' => $itemId, 'name' => '(blank)', 'error' => 'ItemName is empty');
            $this->log("[FAIL] #$itemId -> ItemName is empty, skipped");
            return;
        }

        $normName = $this->normalize($name);

        // 1) already linked by tally_item_id
        if ($this->hasTallyIdColumn && isset($this->tallyIdIndex[$itemId])) {
            $this->stats['already_existing']++;
            $this->log("[SKIP-ID] #$itemId $name -> products.id={$this->tallyIdIndex[$itemId]}");
            return;
        }

        // 2) already exists by normalized name -> skip, optionally backfill the id link only
        if (isset($this->nameIndex[$normName])) {
            $pid = $this->nameIndex[$normName];
            $this->stats['already_existing']++;
            $this->maybeBackfill($itemId, $name, $pid);
            return;
        }

        // 3) new product -> resolve category
        $categoryName = trim((string) $row['ItemParent']);
        if ($categoryName === '') {
            $this->stats['skipped_no_category']++;
            $this->failures[] = array('item_id' => $itemId, 'name' => $name,
                'error' => 'ItemParent is empty and no default/Uncategorized category exists in PMS - needs manual review');
            $this->log("[SKIP-NO-CATEGORY] #$itemId $name");
            return;
        }
        $categoryId = $this->resolveCategory($categoryName);
        if ($categoryId === null) {
            $this->stats['failed']++;
            $this->failures[] = array('item_id' => $itemId, 'name' => $name, 'error' => 'category resolution failed');
            return;
        }

        $imageResult = $this->handleImage($itemId, $categoryName, $row['ItemImage']);
        $thumbResult = $this->handleThumb($itemId, $categoryName, $row['ItemThumb'], $imageResult);
        $dimension   = $this->buildDimension($row['ItemLength'], $row['ItemBreadth'], $row['ItemHeight']);
        $mrp         = $this->toIntPrice($row['ItemPriceMrp']);
        $dp          = $this->toIntPrice($row['ItemPrice']);

        if ($this->dryRun) {
            $this->stats['inserted']++;
            $this->nameIndex[$normName] = -1;
            if ($this->hasTallyIdColumn) { $this->tallyIdIndex[$itemId] = -1; }
            $this->log("[DRY-RUN][WOULD-INSERT] #$itemId $name -> category='$categoryName' "
                . "mrp=$mrp dp=$dp image=" . ($imageResult['dest'] !== '' ? $imageResult['dest'] : '(none)')
                . ' thumb=' . ($thumbResult['dest'] !== '' ? $thumbResult['dest'] : '(none)'));
            return;
        }

        $this->insertProduct($itemId, $name, $normName, $categoryName, $categoryId, $dimension, $row, $mrp, $dp, $imageResult, $thumbResult);
    }

    private function maybeBackfill($itemId, $name, $pid)
    {
        if (!$this->backfill || !$this->hasTallyIdColumn) {
            $this->log("[SKIP-NAME] #$itemId $name -> products.id=$pid");
            return;
        }
        if ($this->dryRun) {
            $this->stats['backfilled']++;
            $this->log("[DRY-RUN][SKIP-NAME+WOULD-BACKFILL] #$itemId $name -> products.id=$pid");
            return;
        }
        $chk = mysqli_query($this->connect, 'SELECT tally_item_id FROM products WHERE id=' . (int) $pid);
        $chkRow = $chk ? mysqli_fetch_assoc($chk) : null;
        if ($chkRow && $chkRow['tally_item_id'] === null) {
            mysqli_query($this->connect, 'UPDATE products SET tally_item_id=' . (int) $itemId . ' WHERE id=' . (int) $pid);
            $this->tallyIdIndex[$itemId] = $pid;
            $this->stats['backfilled']++;
            $this->log("[SKIP-NAME+BACKFILL] #$itemId $name -> products.id=$pid (tally_item_id set)");
        } else {
            $this->log("[SKIP-NAME] #$itemId $name -> products.id=$pid (already linked, no backfill needed)");
        }
    }

    private function resolveCategory($categoryName)
    {
        $normCat = $this->normalize($categoryName);
        if (isset($this->categoryCache[$normCat])) {
            $this->stats['categories_used']++;
            return $this->categoryCache[$normCat];
        }

        if ($this->dryRun) {
            $this->log("[DRY-RUN] would create category '$categoryName'");
            $this->categoryCache[$normCat] = -1;
            $this->stats['categories_created']++;
            return -1;
        }

        $escCat = mysqli_real_escape_string($this->connect, $categoryName);
        $ok = mysqli_query($this->connect, "INSERT INTO categories(name, active) VALUES ('$escCat', 1)");
        if (!$ok) {
            $this->log("[FAIL] could not create category '$categoryName': " . mysqli_error($this->connect));
            return null;
        }
        $categoryId = mysqli_insert_id($this->connect);

        $folder = IMPORT_PMS_CORE_ROOT . '/Category/' . $this->sanitizeFolderName($categoryName);
        if (!is_dir($folder)) {
            @mkdir($folder, 0777, true);
        }

        $this->categoryCache[$normCat] = $categoryId;
        $this->stats['categories_created']++;
        $this->log("[NEW-CATEGORY] '$categoryName' -> categories.id=$categoryId");
        return $categoryId;
    }

    private function insertProduct($itemId, $name, $normName, $categoryName, $categoryId, $dimension, $row, $mrp, $dp, $imageResult, $thumbResult)
    {
        mysqli_query($this->connect, 'START TRANSACTION');

        $escName  = mysqli_real_escape_string($this->connect, $name);
        $escColor = mysqli_real_escape_string($this->connect, trim((string) $row['ItemColor']));
        $escDim   = mysqli_real_escape_string($this->connect, $dimension);
        $escDesc  = mysqli_real_escape_string($this->connect, trim((string) $row['ItemDesc']));
        $escImage = mysqli_real_escape_string($this->connect, $imageResult['dest']);
        $escThumb = mysqli_real_escape_string($this->connect, $thumbResult['dest']);

        $cols = 'name, color, dimension, image, thumb, description, category_id, catalogue, tags';
        $vals = "'$escName','$escColor','$escDim','$escImage','$escThumb','$escDesc','" . (int) $categoryId . "','0',''";
        if ($this->hasTallyIdColumn) {
            $cols = 'tally_item_id, ' . $cols;
            $vals = (int) $itemId . ',' . $vals;
        }

        $ok = mysqli_query($this->connect, "INSERT INTO products($cols) VALUES ($vals)");
        if (!$ok) {
            mysqli_query($this->connect, 'ROLLBACK');
            $this->cleanupOrphanFiles($imageResult, $thumbResult);
            $this->stats['failed']++;
            $err = mysqli_error($this->connect);
            $this->failures[] = array('item_id' => $itemId, 'name' => $name, 'error' => "product insert failed: $err");
            $this->log("[FAIL] #$itemId $name -> $err");
            return;
        }
        $prodId = mysqli_insert_id($this->connect);

        $ok2 = mysqli_query(
            $this->connect,
            'INSERT INTO pricelist(p_prodId, mrp_price, dp_price, special_price, price_date) VALUES ('
                . (int) $prodId . ',' . (int) $mrp . ',' . (int) $dp . ",0,'" . date('d/m/Y') . "')"
        );
        if (!$ok2) {
            mysqli_query($this->connect, 'ROLLBACK');
            $this->cleanupOrphanFiles($imageResult, $thumbResult);
            $this->stats['failed']++;
            $err = mysqli_error($this->connect);
            $this->failures[] = array('item_id' => $itemId, 'name' => $name, 'error' => "pricelist insert failed: $err");
            $this->log("[FAIL] #$itemId $name -> $err");
            return;
        }

        mysqli_query($this->connect, 'COMMIT');

        $this->stats['inserted']++;
        $this->nameIndex[$normName] = $prodId;
        if ($this->hasTallyIdColumn) {
            $this->tallyIdIndex[$itemId] = $prodId;
        }
        $this->log("[INSERT] #$itemId $name -> products.id=$prodId category='$categoryName' mrp=$mrp dp=$dp "
            . 'image=' . ($imageResult['dest'] !== '' ? $imageResult['dest'] : '(none)')
            . ' thumb=' . ($thumbResult['dest'] !== '' ? $thumbResult['dest'] : '(none)'));
    }

    /* -------------------------- images -------------------------- */

    private function resolveSourceFile($rawPath)
    {
        $rawPath = trim((string) $rawPath);
        if ($rawPath === '') {
            return null;
        }
        $normalized = ltrim(str_replace('\\', '/', $rawPath), '/');

        $candidates = array();
        if (preg_match('#^[A-Za-z]:/#', $normalized)) {
            $candidates[] = $normalized;
        }
        $candidates[] = IMPORT_TALLY_APP_ROOT . '/' . $normalized;
        $candidates[] = IMPORT_TALLY_APP_ROOT . '/' . IMPORT_TALLY_IMAGE_SUBDIR . '/' . basename($normalized);

        foreach ($candidates as $cand) {
            if (is_file($cand)) {
                return $cand;
            }
        }
        $this->log('  tried: ' . implode(' | ', $candidates));
        return null;
    }

    private function handleImage($itemId, $categoryName, $srcRaw)
    {
        $srcAbs = $this->resolveSourceFile($srcRaw);
        if ($srcAbs === null) {
            $this->stats['images_missing']++;
            if (trim((string) $srcRaw) !== '') {
                $this->log("[IMG-MISSING] #$itemId source file not found for '$srcRaw'");
            }
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }

        $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
        if (!in_array($ext, array('jpg', 'jpeg', 'png', 'gif'), true)) {
            $this->stats['images_missing']++;
            $this->log("[IMG-SKIP] #$itemId disallowed extension '$ext'");
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }

        $destRel = 'Category/' . $this->sanitizeFolderName($categoryName) . "/tally_{$itemId}.{$ext}";
        $destAbs = IMPORT_PMS_CORE_ROOT . '/' . $destRel;

        if ($this->dryRun) {
            $this->stats['images_copied']++;
            return array('dest' => $destRel, 'destAbs' => $destAbs, 'copied' => true, 'newly_copied' => false, 'ext' => $ext);
        }

        $destDir = dirname($destAbs);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0777, true);
        }

        if (is_file($destAbs)) {
            // already copied by an earlier (possibly failed) run - reuse, don't overwrite
            $this->stats['images_copied']++;
            return array('dest' => $destRel, 'destAbs' => $destAbs, 'copied' => true, 'newly_copied' => false, 'ext' => $ext);
        }

        if (!@copy($srcAbs, $destAbs)) {
            $this->stats['images_missing']++;
            $this->log("[IMG-COPY-FAIL] #$itemId could not copy $srcAbs -> $destAbs");
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }

        $this->stats['images_copied']++;
        return array('dest' => $destRel, 'destAbs' => $destAbs, 'copied' => true, 'newly_copied' => true, 'ext' => $ext);
    }

    private function handleThumb($itemId, $categoryName, $srcThumbRaw, $imageResult)
    {
        // 1) an explicit source thumbnail file
        $srcAbs = $this->resolveSourceFile($srcThumbRaw);
        if ($srcAbs !== null) {
            $ext = strtolower(pathinfo($srcAbs, PATHINFO_EXTENSION));
            if (in_array($ext, array('jpg', 'jpeg', 'png', 'gif'), true)) {
                $destRel = "Category/thumb/tally_{$itemId}_thumb.{$ext}";
                $destAbs = IMPORT_PMS_CORE_ROOT . '/' . $destRel;

                if ($this->dryRun) {
                    $this->stats['thumbs_copied']++;
                    return array('dest' => $destRel, 'copied' => true, 'newly_copied' => false);
                }
                $destDir = dirname($destAbs);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0777, true);
                }
                if (is_file($destAbs)) {
                    $this->stats['thumbs_copied']++;
                    return array('dest' => $destRel, 'copied' => true, 'newly_copied' => false);
                }
                if (@copy($srcAbs, $destAbs)) {
                    $this->stats['thumbs_copied']++;
                    return array('dest' => $destRel, 'copied' => true, 'newly_copied' => true);
                }
            }
        } elseif (trim((string) $srcThumbRaw) !== '') {
            $this->log("[THUMB-SRC-MISSING] #$itemId ItemThumb set but file not found, generating from image instead");
        }

        // 2) fall back to auto-generating from the copied main image (existing AddProducts.php behavior)
        if (!$imageResult['copied'] || empty($imageResult['ext']) || !in_array($imageResult['ext'], array('jpg', 'jpeg', 'png'), true)) {
            $this->stats['thumbs_missing']++;
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }
        if (!extension_loaded('gd')) {
            $this->stats['thumbs_missing']++;
            $this->log("[THUMB-SKIP] #$itemId GD extension not available");
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }

        $ext = $imageResult['ext'];
        $destRel = "Category/thumb/tally_{$itemId}_thumb.{$ext}";
        $destAbs = IMPORT_PMS_CORE_ROOT . '/' . $destRel;

        if ($this->dryRun) {
            $this->stats['thumbs_copied']++;
            return array('dest' => $destRel, 'copied' => true, 'newly_copied' => false);
        }

        $destDir = dirname($destAbs);
        if (!is_dir($destDir)) {
            @mkdir($destDir, 0777, true);
        }
        if (is_file($destAbs)) {
            $this->stats['thumbs_copied']++;
            return array('dest' => $destRel, 'copied' => true, 'newly_copied' => false);
        }

        $srcForThumb = isset($imageResult['destAbs']) ? $imageResult['destAbs'] : null;
        if ($srcForThumb === null || !is_file($srcForThumb)) {
            $this->stats['thumbs_missing']++;
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }

        if (!$this->generateThumbFile($srcForThumb, $destAbs, $ext)) {
            $this->stats['thumbs_missing']++;
            $this->log("[THUMB-GEN-FAIL] #$itemId could not generate thumbnail from $srcForThumb");
            return array('dest' => '', 'copied' => false, 'newly_copied' => false);
        }

        $this->stats['thumbs_copied']++;
        return array('dest' => $destRel, 'copied' => true, 'newly_copied' => true);
    }

    // Same 500px-wide proportional resize AddProducts.php already does for manually-uploaded images.
    private function generateThumbFile($srcAbs, $destAbs, $ext)
    {
        $n_width = 500;
        $im = ($ext === 'png') ? @imagecreatefrompng($srcAbs) : @imagecreatefromjpeg($srcAbs);
        if (!$im) {
            return false;
        }
        $width  = imagesx($im);
        $height = imagesy($im);
        if ($width <= 0) {
            imagedestroy($im);
            return false;
        }
        $n_height = (int) round(($n_width / $width) * $height);
        $newimage = imagecreatetruecolor($n_width, max(1, $n_height));
        if ($ext === 'png') {
            imagefilledrectangle($newimage, 0, 0, $n_width, $n_height, imagecolorallocate($newimage, 255, 255, 255));
        }
        imagecopyresized($newimage, $im, 0, 0, 0, 0, $n_width, $n_height, $width, $height);
        $ok = ($ext === 'png') ? imagepng($newimage, $destAbs) : imagejpeg($newimage, $destAbs);
        imagedestroy($im);
        imagedestroy($newimage);
        if ($ok) {
            @chmod($destAbs, 0777);
        }
        return $ok;
    }

    private function cleanupOrphanFiles($imageResult, $thumbResult)
    {
        if (!empty($imageResult['newly_copied']) && !empty($imageResult['destAbs']) && is_file($imageResult['destAbs'])) {
            @unlink($imageResult['destAbs']);
        }
        if (!empty($thumbResult['newly_copied']) && !empty($thumbResult['dest'])) {
            $destAbs = IMPORT_PMS_CORE_ROOT . '/' . $thumbResult['dest'];
            if (is_file($destAbs)) {
                @unlink($destAbs);
            }
        }
    }

    /* -------------------------- helpers -------------------------- */

    private function buildDimension($l, $b, $h)
    {
        $parts = array();
        foreach (array($l, $b, $h) as $v) {
            $v = trim((string) $v);
            if ($v !== '') {
                $parts[] = $v;
            }
        }
        return implode(' x ', $parts);
    }

    private function toIntPrice($v)
    {
        $v = trim((string) $v);
        if ($v === '' || !is_numeric($v)) {
            return 0;
        }
        return (int) round((float) $v);
    }

    private function normalize($s)
    {
        $s = trim((string) $s);
        $s = preg_replace('/\s+/', ' ', $s);
        return function_exists('mb_strtolower') ? mb_strtolower($s, 'UTF-8') : strtolower($s);
    }

    private function sanitizeFolderName($name)
    {
        $name = trim((string) $name);
        $name = preg_replace('/[\\\\\/:*?"<>|]/', '', $name);
        $name = trim($name);
        return $name !== '' ? $name : 'Uncategorized';
    }

    private function log($msg)
    {
        echo $msg . "\n";
        $this->logLines[] = $msg;
    }

    private function printSummary()
    {
        $s = $this->stats;
        $this->log('');
        $this->log('================ IMPORT SUMMARY' . ($this->dryRun ? ' (DRY RUN - no changes made)' : '') . ' ================');
        $this->log('Finished Goods found in tally_io: ' . $s['finished_goods_found']);
        $this->log('Already existing in PMS: ' . $s['already_existing'] . ' (tally_item_id backfilled: ' . $s['backfilled'] . ')');
        $this->log('New products inserted: ' . $s['inserted']);
        $this->log('Existing categories used: ' . $s['categories_used']);
        $this->log('New categories created: ' . $s['categories_created']);
        $this->log('Images copied: ' . $s['images_copied']);
        $this->log('Thumbnails copied/generated: ' . $s['thumbs_copied']);
        $this->log('Missing images: ' . $s['images_missing']);
        $this->log('Missing thumbnails: ' . $s['thumbs_missing']);
        $this->log('Skipped - no category (empty ItemParent): ' . $s['skipped_no_category']);
        $this->log('Products failed: ' . $s['failed']);
        if (!empty($this->failures)) {
            $this->log('');
            $this->log('--- Failures (item id / name / error) ---');
            foreach ($this->failures as $f) {
                $this->log('  #' . $f['item_id'] . ' "' . $f['name'] . '" -> ' . $f['error']);
            }
        }
        $this->log('=====================================================================');
    }

    private function writeLogFile()
    {
        if (!is_dir(IMPORT_LOG_DIR)) {
            @mkdir(IMPORT_LOG_DIR, 0777, true);
        }
        $file = IMPORT_LOG_DIR . '/import_' . date('Ymd_His') . ($this->dryRun ? '_dryrun' : '') . '.log';
        @file_put_contents($file, implode("\n", $this->logLines) . "\n");
        echo "\nFull log written to: $file\n";
    }
}
