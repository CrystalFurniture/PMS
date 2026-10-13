<?php
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();
		$userName = $_SESSION['username'];

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');

		// The user's own quotations, DP and MRP together, newest first.
		$quotes = array();
		$result = mysqli_query($connect, "select * from tbl_quotation where q_user = '" . mysqli_real_escape_string($connect, $_SESSION['username']) . "' AND q_type IN ('DP', 'MRP') ORDER BY q_id DESC");
		while ($result && ($row = mysqli_fetch_assoc($result))) {
			$quotes[] = $row;
		}
		$countDp = 0;
		foreach ($quotes as $q) {
			if ($q['q_type'] === 'DP') {
				$countDp++;
			}
		}
		$countMrp = count($quotes) - $countDp;
?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">

    <section class="content-header">
      <div class="pms-create-head" style="margin:0">
        <div class="pms-grow">
          <h1 style="margin:0">
            Saved quotations
            <small class="pms-sub">DP and MRP quotations in one list. Open one to download PDF or Excel, or edit it to make a new revision.</small>
          </h1>
        </div>
        <div class="pms-page-actions" style="margin:0">
          <a href="CreatedOrderDp.php?new=1" class="btn btn-primary">New DP quotation</a>
          <a href="CreatedOrderMRP.php?new=1" class="btn btn-default">New MRP quotation</a>
        </div>
      </div>
    </section>

    <section class="content">
      <?php echo qt_csrf_field(); ?>
      <?php echo qt_flash_render(); ?>
      <div id="messages" aria-live="polite"></div>

      <div class="box">
        <div class="box-body">
          <div class="pms-chips" role="tablist" aria-label="Quotation type">
            <button type="button" class="pms-chip qt-type-tab" role="tab" aria-selected="true" data-type="">All <span class="pms-num"><?= count($quotes) ?></span></button>
            <button type="button" class="pms-chip qt-type-tab" role="tab" aria-selected="false" data-type="DP">DP <span class="pms-num"><?= $countDp ?></span></button>
            <button type="button" class="pms-chip qt-type-tab" role="tab" aria-selected="false" data-type="MRP">MRP <span class="pms-num"><?= $countMrp ?></span></button>
          </div>

          <div class="pms-scroll">
          <table id="manageQuotations" class="table" data-page-length="25" style="width:100%; min-width:900px">
            <thead>
              <tr>
                <th>Quotation</th>
                <th>Customer</th>
                <th>Type</th>
                <th style="text-align:right">Lines</th>
                <th style="text-align:right">Total</th>
                <th>Revision</th>
                <th>Date</th>
                <th style="text-align:right">Actions</th>
              </tr>
            </thead>
            <tbody>
            <?php foreach ($quotes as $row) {
              $type = $row['q_type'] === 'MRP' ? 'MRP' : 'DP';
              $cfg = qt_config($type);
              $id = (int) $row['q_id'];
              $totals = qt_quotation_totals($row);
              $rev = (int) $row['q_revNum'];
              $date = $row['q_revDate'] !== '' ? $row['q_revDate'] : $row['q_date'];
              $dateSort = preg_match('/^(\d{2})-(\d{2})-(\d{4})$/', (string) $date, $m) ? $m[3] . $m[2] . $m[1] : '';
            ?>
              <tr id="quotation-<?= $id ?>">
                <td style="font-weight:600"><a href="<?= qt_e($cfg['detail']) ?>?id=<?= $id ?>"><?= qt_e($row['q_num']) ?></a></td>
                <td><?= qt_e($row['q_name']) ?></td>
                <td data-search="<?= $type ?>"><span class="pms-badge <?= $type === 'DP' ? 'pms-badge-dp' : 'pms-badge-mrp' ?>"><?= $type ?></span></td>
                <td style="text-align:right" class="pms-num"><?= $totals === null ? '&mdash;' : (int) $totals[0] ?></td>
                <td style="text-align:right" class="pms-num" data-order="<?= $totals === null ? 0 : round($totals[4], 2) ?>"
                  title="<?= $totals !== null && $totals[3] > 0 ? qt_e('After ' . $totals[3] . '% discount, before GST') : 'Before GST' ?>"><?= $totals === null ? '&mdash;' : qt_inr($totals[4]) ?></td>
                <td class="text-muted"><?= $rev > 0 ? 'R' . $rev : 'Original' ?></td>
                <td class="text-muted" data-order="<?= qt_e($dateSort) ?>"><?= qt_e($date) ?></td>
                <td>
                  <div class="pms-qactions">
                    <a class="btn btn-default btn-sm" href="<?= qt_e($cfg['detail']) ?>?id=<?= $id ?>">Open</a>
                    <a class="btn btn-default btn-sm" href="<?= qt_e($cfg['cartPage']) ?>?id=<?= $id ?>">Edit</a>
                    <button type="button" class="btn btn-danger btn-sm" onclick="return removeQuotation('<?= $type ?>', <?= $id ?>)" aria-label="Delete quotation <?= qt_e($row['q_num']) ?>">Delete</button>
                  </div>
                </td>
              </tr>
            <?php } ?>
            </tbody>
          </table>
          </div>
        </div>
      </div>
    </section>

  </div>
  <!-- /.content-wrapper -->

<?php
		include_once('templates/footer.php');

?>

<script type="text/javascript">
$(document).ready(function () {
	var table = $('#manageQuotations').DataTable({
		order: [],
		bLengthChange: false,
		autoWidth: false,
		language: { search: '', searchPlaceholder: 'Search customer or quotation number', emptyTable: 'No quotations yet.', info: 'Showing _START_ to _END_ of _TOTAL_ quotations', infoFiltered: '(of _MAX_)' },
		columnDefs: [{ targets: [7], orderable: false, searchable: false }]
	});
	$('#manageQuotations_filter input').attr('aria-label', 'Search quotations');

	$('.qt-type-tab').on('click', function () {
		$('.qt-type-tab').attr('aria-selected', 'false');
		$(this).attr('aria-selected', 'true');
		var type = $(this).data('type');
		table.column(2).search(type ? '^' + type + '$' : '', true, false).draw();
	});
});

function removeQuotation(type, id)
{
	if(!confirm("Delete this quotation? This cannot be undone.")) {
		return false;
	}
	$.ajax({
		url: 'action.php',
		type: 'POST',
		dataType: 'json',
		data: { action: 'delete_quotation', type: type, id: id, csrf_token: $('#qt_csrf').val() },
		success: function(data) {
			$('#manageQuotations').DataTable().row($('#quotation-' + id)).remove().draw(false);
			$("#messages").html('<div class="alert alert-success alert-dismissible" role="alert">'+
				'<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
				'Quotation deleted.</div>');
		},
		error: function(xhr) {
			var message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Something went wrong. Please reload the page and try again.';
			$("#messages").html($('<div class="alert alert-warning" role="alert"></div>').text(message));
		}
	});
	return false;
}

// Older links and tests call these.
function removeMRP(id) { return removeQuotation('MRP', id); }

function removeDP(id) { return removeQuotation('DP', id); }
</script>
