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
		// Every revision is its own row (Original, R1, R2, ...); a quotation's revisions are listed together, newest first,
		// and the quotations by their latest change.
		$result = mysqli_query($connect, "select * from tbl_quotation where q_user = '" . mysqli_real_escape_string($connect, $_SESSION['username']) . "' AND q_type IN ('DP', 'MRP')
			ORDER BY MAX(q_id) OVER (PARTITION BY q_num, q_type) DESC, q_revNum DESC, q_id DESC");
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
		$hasStatus = qt_has_status($connect);
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
                <th>Status</th>
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
              $status = $hasStatus ? (string) $row['q_status'] : '';
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
                <td><?php if ($status === 'ordered') { ?><span class="pms-badge pms-badge-dp" title="<?= qt_e('Converted ' . $row['q_orderedAt']) ?>">Ordered</span><?php
                  } elseif ($status === 'approved') { ?><span class="pms-badge pms-badge-mrp" title="<?= qt_e('Approved by ' . $row['q_approvedBy'] . ' ' . $row['q_approvedAt']) ?>">Approved</span><?php
                  } else { ?><span class="pms-badge pms-badge-warn">Awaiting approval</span><?php } ?></td>
                <td>
                  <div class="pms-qactions">
                    <a class="btn btn-default btn-sm" href="<?= qt_e($cfg['detail']) ?>?id=<?= $id ?>">Open</a>
                    <a class="btn btn-default btn-sm" href="<?= qt_e($cfg['cartPage']) ?>?id=<?= $id ?>">Edit</a>
                    <?php if ($hasStatus && $status === '') { ?>
                    <button type="button" class="btn btn-default btn-sm" onclick="return openSendForm('<?= $type ?>', <?= $id ?>)" aria-label="Send quotation <?= qt_e($row['q_num']) ?>">Send quotation</button>
                    <button type="button" class="btn btn-primary btn-sm" onclick="return setQuotationStatus('approve_quotation', '<?= $type ?>', <?= $id ?>)" aria-label="Approve quotation <?= qt_e($row['q_num']) ?>">Approve</button>
                    <?php } elseif ($status === 'approved') { ?>
                    <button type="button" class="btn btn-success btn-sm" onclick="return setQuotationStatus('convert_quotation', '<?= $type ?>', <?= $id ?>)" aria-label="Convert quotation <?= qt_e($row['q_num']) ?> to order">Convert to order</button>
                    <?php } elseif ($status === 'ordered') { ?>
                    <a class="btn btn-default btn-sm" href="<?= qt_e(qt_sale_order_url($id)) ?>" target="_blank" rel="noopener">Open sale order</a>
                    <?php } ?>
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

    <!-- Approve: send the quotation PDF by email and WhatsApp -->
    <div class="modal fade" tabindex="-1" role="dialog" id="sendModal" aria-labelledby="sendModalTitle">
      <div class="modal-dialog" role="document">
        <form class="modal-content" id="sendForm" novalidate>
          <div class="modal-header">
            <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
            <h4 class="modal-title" id="sendModalTitle">Send quotation</h4>
            <small class="text-muted" id="sendModalSub"></small>
          </div>
          <div class="modal-body">
            <div id="sendMessages" aria-live="polite"></div>
            <?php foreach (array('cust' => 'Customer', 'sales' => 'Salesperson', 'mgr' => 'Manager (optional)') as $role => $who) { ?>
            <fieldset style="margin-bottom:6px">
              <legend style="font-size:14px; font-weight:600; margin-bottom:6px; border:0"><?= $who ?></legend>
              <div class="row">
                <div class="form-group col-sm-6">
                  <label for="send-<?= $role ?>-email">Email<?= $role !== 'mgr' ? ' <span class="text-danger">*</span>' : '' ?></label>
                  <input type="email" class="form-control" id="send-<?= $role ?>-email" name="<?= $role ?>_email" maxlength="150" autocomplete="off">
                </div>
                <div class="form-group col-sm-6">
                  <label for="send-<?= $role ?>-wa">WhatsApp no.<?= $role !== 'mgr' ? ' <span class="text-danger">*</span>' : '' ?></label>
                  <input type="tel" class="form-control" id="send-<?= $role ?>-wa" name="<?= $role ?>_wa" maxlength="20" placeholder="10 digit mobile" autocomplete="off">
                </div>
              </div>
            </fieldset>
            <?php } ?>
            <p class="text-muted" style="margin:0">The quotation PDF is sent to every filled-in email and WhatsApp number.</p>
          </div>
          <div class="modal-footer">
            <button type="button" class="btn btn-default" data-dismiss="modal">Cancel</button>
            <button type="submit" class="btn btn-primary" id="sendSubmit">Send</button>
          </div>
        </form>
      </div>
    </div>

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
		columnDefs: [{ targets: [8], orderable: false, searchable: false }]
	});
	$('#manageQuotations_filter input').attr('aria-label', 'Search quotations');

	$('.qt-type-tab').on('click', function () {
		$('.qt-type-tab').attr('aria-selected', 'false');
		$(this).attr('aria-selected', 'true');
		var type = $(this).data('type');
		table.column(2).search(type ? '^' + type + '$' : '', true, false).draw();
	});
});

// Send quotation (before approval) opens the send form, with the customer's contacts filled in by the server;
// submitting it sends the PDF. Approving is separate and has no form.
var sendTarget = null;

function sendAlert(kind, text)
{
	$('#sendMessages').html($('<div class="alert" role="alert" style="white-space:pre-line"></div>').addClass('alert-' + kind).text(text));
}

function openSendForm(type, id)
{
	sendTarget = { type: type, id: id };
	$('#sendForm')[0].reset();
	$('#sendForm .form-group').removeClass('has-error');
	$('#sendMessages').html('');
	$('#sendModalSub').text('Loading contacts...');
	$('#sendSubmit').prop('disabled', true).text('Send');
	$('#sendModal').modal('show');
	$.ajax({
		url: 'action.php',
		type: 'POST',
		dataType: 'json',
		data: { action: 'send_form', type: type, id: id, csrf_token: $('#qt_csrf').val() },
		success: function(data) {
			$('#sendModalSub').text(data.title);
			$.each(data.values, function(name, value) {
				$('#sendForm [name="' + name + '"]').val(value);
			});
			if (!data.ledger) {
				sendAlert('info', 'This customer was not found in the Tally ledger: enter the customer email and WhatsApp number.');
			}
			$('#sendSubmit').prop('disabled', false);
		},
		error: function(xhr) {
			$('#sendModalSub').text('');
			sendAlert('warning', (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Something went wrong. Please reload the page and try again.');
		}
	});
	return false;
}

$(document).on('submit', '#sendForm', function(e) {
	e.preventDefault();
	if (!sendTarget) {
		return false;
	}
	var data = $(this).serializeArray();
	data.push({ name: 'action', value: 'send_quotation' }, { name: 'type', value: sendTarget.type }, { name: 'id', value: sendTarget.id },
		{ name: 'csrf_token', value: $('#qt_csrf').val() });
	$('#sendForm .form-group').removeClass('has-error');
	$('#sendSubmit').prop('disabled', true).text('Sending...');
	$.ajax({
		url: 'action.php',
		type: 'POST',
		dataType: 'json',
		data: data,
		success: function(res) {
			if (res.failed) {
				sendAlert('warning', 'Not everything was sent:\n' + res.summary);
				$('#sendSubmit').text('Send');
				$('#sendModal').one('hidden.bs.modal', function() { location.reload(); });
				return;
			}
			location.reload();
		},
		error: function(xhr) {
			var res = xhr.responseJSON || {};
			$.each(res.fields || {}, function(name) {
				$('#sendForm [name="' + name + '"]').closest('.form-group').addClass('has-error');
			});
			sendAlert('warning', res.error ? res.error : 'Something went wrong. Please reload the page and try again.');
			$('#sendSubmit').prop('disabled', false).text('Send');
		}
	});
	return false;
});

function setQuotationStatus(action, type, id)
{
	var question = action === 'approve_quotation' ? 'Approve this quotation?' : 'Convert this approved quotation to an order?';
	if(!confirm(question)) {
		return false;
	}
	// The sale order opens in a new tab; it is opened now, while the click still allows it, and filled in once converted.
	var orderTab = action === 'convert_quotation' ? window.open('', '_blank') : null;
	$.ajax({
		url: 'action.php',
		type: 'POST',
		dataType: 'json',
		data: { action: action, type: type, id: id, csrf_token: $('#qt_csrf').val() },
		success: function(data) {
			if (data && data.redirect) {
				if (orderTab) {
					orderTab.location.href = data.redirect;
				} else {
					window.open(data.redirect, '_blank');
				}
			}
			location.reload();
		},
		error: function(xhr) {
			if (orderTab) {
				orderTab.close();
			}
			var message = (xhr.responseJSON && xhr.responseJSON.error) ? xhr.responseJSON.error : 'Something went wrong. Please reload the page and try again.';
			$("#messages").html($('<div class="alert alert-warning" role="alert"></div>').text(message));
		}
	});
	return false;
}

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
