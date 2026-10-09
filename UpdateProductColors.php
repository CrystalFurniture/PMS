<?php
/**
 * Bulk-update products.color from an uploaded Excel/CSV file.
 * Expected columns: A = product id, B = color. An optional header row is
 * auto-detected (skipped if column A of row 1 isn't numeric).
 *
 * Flow: upload -> preview (nothing written yet) -> confirm -> applied.
 * Preview data lives in the session between steps; the uploaded file is
 * deleted as soon as confirm/cancel runs (or immediately if the upload
 * itself was rejected).
 */

include_once('DB/conn.php');
include_once('templates/permission.php');

error_reporting(E_ALL);

if (empty($UserPermission) || !in_array('updateProduct', $UserPermission)) {
    $msg = base64_encode(serialize('You do not have permission to update products.'));
    header("Location:dashboard.php?failmsg=$msg");
    exit;
}

define('COLOR_IMPORT_DIR', __DIR__ . '/uploads/color_import');

$step        = 'upload';
$errors      = array();
$previewRows = array();
$summary     = array('update' => 0, 'skip_empty' => 0, 'not_found' => 0, 'invalid_id' => 0, 'header_skipped' => false);
$doneResult  = null;

function color_import_cleanup_session()
{
    if (!empty($_SESSION['color_import_file']) && is_file($_SESSION['color_import_file'])) {
        @unlink($_SESSION['color_import_file']);
    }
    unset($_SESSION['color_import_preview'], $_SESSION['color_import_summary'], $_SESSION['color_import_file']);
}

$action = isset($_POST['action']) ? $_POST['action'] : '';

/* ------------------------------------------------------------ cancel ------------------------------------------------------------ */
if ($action === 'cancel') {
    color_import_cleanup_session();
    header('Location: UpdateProductColors.php');
    exit;
}

/* ------------------------------------------------------------ preview ----------------------------------------------------------- */
if ($action === 'preview' && isset($_FILES['file'])) {
    color_import_cleanup_session();

    $ext = strtolower(pathinfo($_FILES['file']['name'], PATHINFO_EXTENSION));

    if ($_FILES['file']['error'] !== UPLOAD_ERR_OK) {
        $errors[] = 'File upload failed (error code ' . $_FILES['file']['error'] . ').';
    } elseif (!in_array($ext, array('xls', 'xlsx', 'csv'), true)) {
        $errors[] = 'Please upload a .xls, .xlsx or .csv file.';
    } else {
        if (!is_dir(COLOR_IMPORT_DIR)) {
            @mkdir(COLOR_IMPORT_DIR, 0777, true);
        }
        $savedPath = COLOR_IMPORT_DIR . '/' . uniqid('color_', true) . '.' . $ext;

        if (!move_uploaded_file($_FILES['file']['tmp_name'], $savedPath)) {
            $errors[] = 'Could not save the uploaded file.';
        } else {
            require_once('library/php-excel-reader/excel_reader2.php');
            require_once('library/SpreadsheetReader.php');

            $rawRows = array();
            try {
                $Reader = new SpreadsheetReader($savedPath);
                foreach ($Reader as $Row) {
                    $rawRows[] = $Row;
                }
            } catch (Exception $e) {
                $errors[] = 'Could not read the file: ' . $e->getMessage();
            }

            if (!empty($errors)) {
                @unlink($savedPath);
            } else {
                // Optional header row: if column A of the first row isn't numeric, skip it.
                $headerSkipped = false;
                if (isset($rawRows[0][0]) && trim($rawRows[0][0]) !== '' && !is_numeric(trim($rawRows[0][0]))) {
                    array_shift($rawRows);
                    $headerSkipped = true;
                }

                $ids = array();
                foreach ($rawRows as $r) {
                    $idVal = isset($r[0]) ? trim($r[0]) : '';
                    if ($idVal !== '' && is_numeric($idVal)) {
                        $ids[] = (int) $idVal;
                    }
                }
                $ids = array_values(array_unique($ids));

                $existing = array();
                if ($ids) {
                    $res = mysqli_query($connect, 'SELECT id, name, color FROM products WHERE id IN (' . implode(',', $ids) . ')');
                    while ($res && $row = mysqli_fetch_assoc($res)) {
                        $existing[(int) $row['id']] = array('name' => $row['name'], 'color' => $row['color']);
                    }
                }

                $rowNum = $headerSkipped ? 2 : 1;
                foreach ($rawRows as $r) {
                    $idVal    = isset($r[0]) ? trim($r[0]) : '';
                    $colorVal = isset($r[1]) ? trim($r[1]) : '';

                    if ($idVal === '' && $colorVal === '') {
                        $rowNum++;
                        continue; // fully blank row - ignore silently
                    }

                    $entry = array('row' => $rowNum, 'id' => $idVal, 'new_color' => $colorVal,
                        'product_name' => null, 'old_color' => null);

                    if ($idVal === '' || !is_numeric($idVal)) {
                        $entry['status'] = 'invalid_id';
                        $summary['invalid_id']++;
                    } elseif (!isset($existing[(int) $idVal])) {
                        $entry['status'] = 'not_found';
                        $summary['not_found']++;
                    } else {
                        $entry['product_name'] = $existing[(int) $idVal]['name'];
                        $entry['old_color']    = $existing[(int) $idVal]['color'];
                        if ($colorVal === '') {
                            $entry['status'] = 'skip_empty';
                            $summary['skip_empty']++;
                        } else {
                            $entry['status'] = 'update';
                            $summary['update']++;
                        }
                    }
                    $previewRows[] = $entry;
                    $rowNum++;
                }
                $summary['header_skipped'] = $headerSkipped;

                if (empty($previewRows)) {
                    $errors[] = 'No usable rows found. Expected column A = product id, column B = color.';
                    @unlink($savedPath);
                } else {
                    $_SESSION['color_import_preview'] = $previewRows;
                    $_SESSION['color_import_summary']  = $summary;
                    $_SESSION['color_import_file']     = $savedPath;
                    $step = 'preview';
                }
            }
        }
    }
}

/* ------------------------------------------------------------ confirm ----------------------------------------------------------- */
if ($action === 'confirm') {
    if (empty($_SESSION['color_import_preview'])) {
        $errors[] = 'Preview expired or not found - please upload the file again.';
    } else {
        $rows = $_SESSION['color_import_preview'];
        $updated = 0;
        $failed  = 0;
        $failList = array();

        mysqli_query($connect, 'START TRANSACTION');
        foreach ($rows as $r) {
            if ($r['status'] !== 'update') {
                continue;
            }
            $escColor = mysqli_real_escape_string($connect, $r['new_color']);
            $ok = mysqli_query($connect, "UPDATE products SET color='$escColor' WHERE id=" . (int) $r['id']);
            if ($ok) {
                $updated++;
            } else {
                $failed++;
                $failList[] = array('id' => $r['id'], 'error' => mysqli_error($connect));
            }
        }
        mysqli_query($connect, 'COMMIT');

        color_import_cleanup_session();
        $step = 'done';
        $doneResult = array('updated' => $updated, 'failed' => $failed, 'failList' => $failList);
    }
}

/* -------------------------------------------------------- resume preview -------------------------------------------------------- */
if ($step === 'upload' && $action === '' && !empty($_SESSION['color_import_preview'])) {
    // stale preview left over from a previous session (e.g. closed tab) - drop it, require a fresh upload
    color_import_cleanup_session();
}

include_once('templates/header.php');
include_once('templates/header_menu.php');
include_once('templates/side_menubar.php');

$statusMeta = array(
    'update'     => array('label' => 'Will update',        'class' => 'label-success'),
    'skip_empty' => array('label' => 'Skipped - blank color', 'class' => 'label-default'),
    'not_found'  => array('label' => 'Not found in PMS',    'class' => 'label-danger'),
    'invalid_id' => array('label' => 'Invalid/missing ID',  'class' => 'label-warning'),
);
?>
<style>
.cic-box{max-width:1100px;}
.cic-dropzone{border:2px dashed #d2d6de;border-radius:6px;padding:30px;text-align:center;background:#fafbfc;}
.cic-summary-tiles{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:16px;}
.cic-tile{flex:1 1 160px;border-radius:6px;padding:14px 16px;color:#fff;}
.cic-tile .n{font-size:24px;font-weight:700;line-height:1;}
.cic-tile .l{font-size:11px;text-transform:uppercase;letter-spacing:.4px;opacity:.9;margin-top:4px;}
.cic-tile.t-update{background:#00a65a;}
.cic-tile.t-skip{background:#7f8c9a;}
.cic-tile.t-notfound{background:#dd4b39;}
.cic-tile.t-invalid{background:#f39c12;}
.cic-color-swatch{display:inline-block;width:13px;height:13px;border-radius:3px;border:1px solid rgba(0,0,0,.15);vertical-align:-1px;margin-right:5px;}
#colorPreviewTable_wrapper{margin-top:10px;}
</style>

<div class="content-wrapper">
  <?php if (isset($_GET['failmsg'])): ?>
    <div class="alert alert-danger alert-dismissible" role="alert" style="margin:15px;">
      <button type="button" class="close" data-dismiss="alert"><span>&times;</span></button>
      <?= unserialize(base64_decode($_GET['failmsg'])) ?>
    </div>
  <?php endif; ?>

  <section class="content-header">
    <h1>Products <small>Update Colors (Excel Import)</small></h1>
    <ol class="breadcrumb">
      <li><a href="dashboard.php"><i class="fa fa-dashboard"></i> Home</a></li>
      <li><a href="ManageProducts.php">Products</a></li>
      <li class="active">Update Colors</li>
    </ol>
  </section>

  <section class="content cic-box">

    <?php foreach ($errors as $e): ?>
      <div class="alert alert-danger"><i class="fa fa-exclamation-triangle"></i> <?= htmlspecialchars($e) ?></div>
    <?php endforeach; ?>

    <?php if ($step === 'upload'): ?>

      <div class="box box-primary">
        <div class="box-header with-border"><h3 class="box-title">Upload file</h3></div>
        <div class="box-body">
          <p class="text-muted">
            Column <b>A</b> = product id, column <b>B</b> = color. One optional header row is detected automatically
            (skipped if column A of row 1 isn't a number). Blank color cells are skipped, not applied.
            If an id appears more than once, the last row for that id wins. Nothing is changed until you review the
            preview and click <b>Confirm &amp; Update</b>.
          </p>
          <form method="post" enctype="multipart/form-data">
            <input type="hidden" name="action" value="preview">
            <div class="cic-dropzone">
              <i class="fa fa-file-excel-o fa-3x text-muted"></i>
              <p style="margin:12px 0;">
                <input type="file" name="file" accept=".xls,.xlsx,.csv" required>
              </p>
              <button type="submit" class="btn btn-primary"><i class="fa fa-eye"></i> Preview</button>
            </div>
          </form>
        </div>
      </div>

    <?php elseif ($step === 'preview'): ?>

      <div class="cic-summary-tiles">
        <div class="cic-tile t-update"><div class="n"><?= $summary['update'] ?></div><div class="l">Will update</div></div>
        <div class="cic-tile t-skip"><div class="n"><?= $summary['skip_empty'] ?></div><div class="l">Blank color - skipped</div></div>
        <div class="cic-tile t-notfound"><div class="n"><?= $summary['not_found'] ?></div><div class="l">Not found in PMS</div></div>
        <div class="cic-tile t-invalid"><div class="n"><?= $summary['invalid_id'] ?></div><div class="l">Invalid / missing id</div></div>
      </div>

      <?php if ($summary['header_skipped']): ?>
        <div class="alert alert-info"><i class="fa fa-info-circle"></i> Row 1 looked like a header and was skipped.</div>
      <?php endif; ?>

      <div class="box box-primary">
        <div class="box-header with-border">
          <h3 class="box-title">Preview - <?= count($previewRows) ?> row<?= count($previewRows) === 1 ? '' : 's' ?></h3>
        </div>
        <div class="box-body">
          <table id="colorPreviewTable" class="table table-bordered table-striped" style="width:100%">
            <thead>
              <tr>
                <th>Row</th><th>ID</th><th>Product</th><th>Current color</th><th>New color</th><th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($previewRows as $r): $meta = $statusMeta[$r['status']]; ?>
                <tr>
                  <td><?= (int) $r['row'] ?></td>
                  <td><?= htmlspecialchars($r['id']) ?></td>
                  <td><?= $r['product_name'] !== null ? htmlspecialchars($r['product_name']) : '<span class="text-muted">&mdash;</span>' ?></td>
                  <td><?= $r['old_color'] !== null ? htmlspecialchars($r['old_color']) : '<span class="text-muted">&mdash;</span>' ?></td>
                  <td><?= $r['new_color'] !== '' ? htmlspecialchars($r['new_color']) : '<span class="text-muted">(blank)</span>' ?></td>
                  <td><span class="label <?= $meta['class'] ?>"><?= $meta['label'] ?></span></td>
                </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
        <div class="box-footer">
          <form method="post" style="display:inline-block;">
            <input type="hidden" name="action" value="confirm">
            <button type="submit" class="btn btn-success" <?= $summary['update'] === 0 ? 'disabled' : '' ?>
              onclick="return confirm('Update color on <?= (int) $summary['update'] ?> product(s)? This cannot be undone automatically.');">
              <i class="fa fa-check"></i> Confirm &amp; Update (<?= $summary['update'] ?>)
            </button>
          </form>
          <form method="post" style="display:inline-block;">
            <input type="hidden" name="action" value="cancel">
            <button type="submit" class="btn btn-default"><i class="fa fa-times"></i> Cancel</button>
          </form>
        </div>
      </div>

    <?php elseif ($step === 'done'): ?>

      <div class="box box-success">
        <div class="box-header with-border"><h3 class="box-title">Import complete</h3></div>
        <div class="box-body">
          <div class="alert alert-success">
            <i class="fa fa-check-circle"></i> <?= (int) $doneResult['updated'] ?> product(s) updated.
          </div>
          <?php if ($doneResult['failed'] > 0): ?>
            <div class="alert alert-danger">
              <i class="fa fa-exclamation-triangle"></i> <?= (int) $doneResult['failed'] ?> row(s) failed:
              <ul>
                <?php foreach ($doneResult['failList'] as $f): ?>
                  <li>id <?= htmlspecialchars($f['id']) ?>: <?= htmlspecialchars($f['error']) ?></li>
                <?php endforeach; ?>
              </ul>
            </div>
          <?php endif; ?>
          <a href="UpdateProductColors.php" class="btn btn-primary"><i class="fa fa-upload"></i> Import another file</a>
          <a href="ManageProducts.php" class="btn btn-default">Back to Products</a>
        </div>
      </div>

    <?php endif; ?>

  </section>
</div>

<script>
$(function () {
  if ($('#colorPreviewTable').length) {
    $('#colorPreviewTable').DataTable({
      pageLength: 25,
      lengthMenu: [[25, 50, 100, -1], [25, 50, 100, 'All']],
      order: []
    });
  }
});
</script>

<?php include_once('templates/footer.php'); ?>
