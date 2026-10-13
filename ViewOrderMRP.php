<?php

session_start();
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
			error_reporting(0);
		qt_require_login_page();
		$qtType = 'MRP';
		$qtCfg = qt_config($qtType);

		// Open an existing quotation for editing, then drop ?id so a refresh keeps the edits.
		if(isset($_GET['id'])){
			list($row, $error) = qt_fetch_owned_quotation($connect, $_GET['id'], $qtType);
			$items = $row ? qt_decode_items($row['q_items']) : null;
			if($error !== null){
				qt_flash_set($error);
				qt_redirect('ViewQuotation.php');
			}
			if($items === null){
				qt_flash_set('The saved lines of quotation '.$row['q_num'].' cannot be read, so it was not opened for editing.');
				qt_redirect('ViewQuotation.php');
			}
			qt_clear_state($qtType);
			qt_cart_set($qtType, qt_normalize_cart($items));
			// Keep the price list the saved lines were priced from (standard when mixed or not recorded).
			$lineLists = array_unique(array_filter(array_map(function ($l) { return is_array($l) && isset($l['price_list']) ? (string) $l['price_list'] : ''; }, $items)));
			qt_set_price_state($qtType, count($lineLists) === 1 ? reset($lineLists) : '');
			$_SESSION["nameMRP"]			= $row['q_name'];
			$_SESSION["discountMRP"]	 	= $row['q_discount'];
			$_SESSION["QidMRP"]	 			= (int) $row['q_id'];
			$_SESSION["QnumMRP"]	 		= $row['q_num'];
			qt_redirect('ViewOrderMRP.php');
		}

		$qtCart = qt_cart_get($qtType);
		$qtEditing = qt_editing_id($qtType);

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
?>

<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.12.0/jquery.validate.js"></script>
<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.12.0/additional-methods.js"></script>
<style>
#form_action .error {
    color: #ff0000a8;
    border-color: #ff0000a1;
}
label.error {
    display: none;
}
</style>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">

          <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo qt_legacy_msg($_GET['msg']); ?>
            </div>
          <?php elseif(isset($_GET['failmsg'])): ?>
            <div class="alert alert-error alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo qt_legacy_msg($_GET['failmsg']); ?>
            </div>
          <?php endif; ?>
          <?php echo qt_csrf_field(); ?>
          <?php echo qt_flash_render(); ?>
          <?php include('templates/quotation_state.php'); ?>
          <?php include('templates/price_state_selector.php'); ?>

		  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
      Manage
      <small>Items</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Invoice</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <!-- Small boxes (Stat box) -->
  <div class="container">
  
<div class="col-md-12" >

	<span>&nbsp; </span>
		<span>&nbsp; </span>
		
						<?php
						if(!empty($qtCart)) {
						$cart_count = count($qtCart);
						?>

						<form method="POST" action="ExportToExcelMRP.php" id="form_action">
						  <input type="hidden" name="csrf_token" value="<?= qt_e(qt_csrf_token()) ?>">
						  <input type="hidden" name="submit_token" value="<?= qt_e(qt_submit_token($qtType)) ?>">
						  <input type="hidden" name="edit_id" value="<?= $qtEditing === null ? '' : (int) $qtEditing ?>">
						  <div class="form-row">
							<div class="form-group col-md-5">
							  <label for="ClientName">Client Name</label>
							  <input type="Text" class="form-control" id="ClientName" name="ClientName" placeholder="Client Name" maxlength="<?= QT_MAX_CLIENT_NAME ?>" value="<?= isset($_SESSION["nameMRP"]) ? qt_e($_SESSION["nameMRP"]) : '' ?>">
							</div>
							<div class="form-group col-md-5">
							  <label for="inputDiscount">Discount</label>
							  <input type="text" class="form-control" id="Discount" name="Discount" placeholder="Enter Discount In %" value="<?= isset($_SESSION["discountMRP"]) ? qt_e($_SESSION["discountMRP"]) : '' ?>">
							</div>
						  </div>

						<div class="cart_div  col-md-2" style="margin-top: 20px;">
						
							<button type="submit" class="btn btn-info btn-s" >SUBMIT</button>
							
						  </div>
							
							

						  </form>
							
						
						
						

						</div>
						<?php
						}
						?>

			<span>&nbsp; </span>
			
<?php
if(isset($_SESSION["shopping_cartMRP"])){
?>
<div id ="order_tableMRP" class="col-md-11">
<?php echo qt_render_cart_table($qtType); ?>
</div>
<?php
}
?>
				
		</div>

    </div>
    <!-- /.row -->
    

  </section>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->
		<script type="text/javascript">
	function printDiv() {
    var divToPrint = document.getElementById('example');
    var htmlToPrint = ''+
		
		'<link rel="stylesheet" href="assets/bower_components/bootstrap/dist/css/bootstrap.min.css" />' +
        '<style type="text/css">' +
        'table th, table td {' +
        'border:0.3px solid #000;' +
        'padding;0.5em;' +
		'border-collapse: collapse;'+
        '}' +
		'h3{text-align: center;}'+
        '</style>'
		;
    htmlToPrint += divToPrint.outerHTML;
    newWin = window.open("");
    newWin.document.write(htmlToPrint);
    newWin.print();
    newWin.close();
}


	jQuery(function ($) {
    var validator = $('#form_action').validate({
        rules: {
            ClientName: {
                required: true
            },
			Discount: {
				number : true,
				max: 35
            }
        },
        messages: {},
        highlight: function (element) {
            $(element).addClass('error')
        },
        unhighlight: function (element) {
            $(element).removeClass('error')
        }
    });
});
	  
</script>




<script src="js/ajax.js"></script>