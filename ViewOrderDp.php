<?php

session_start();
		include_once('DB/conn.php');
	
		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		
	error_reporting(E_ALL);	
		if(isset($_GET['id'])){
			unset($_SESSION["shopping_cartDp"]);
			
			$result = mysqli_query($connect,"select * from tbl_quotation where q_id = '".$_GET['id']."'");
			$row = mysqli_fetch_assoc($result);
			
			$_SESSION["shopping_cartDp"] = unserialize(base64_decode($row['q_items']));
			$_SESSION["nameDP"]			= $row['q_name'];
			$_SESSION["discountDP"]	 	= $row['q_discount'];
			$_SESSION["QidDP"]	 		= $_GET['id'];
			echo $row['q_items'];
	}
		
		
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
              <?php echo unserialize(base64_decode($_GET['msg'])); ?>
            </div>
          <?php elseif(isset($_GET['failmsg'])): ?>
            <div class="alert alert-error alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo unserialize(base64_decode($_GET['failmsg'])); ?>
            </div>
          <?php endif; ?>

		  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
      Manage
      <small>Items</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home </a></li>
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
						if(!empty($_SESSION["shopping_cartDp"])) {
						$cart_count = count(array_keys($_SESSION["shopping_cartDp"]));
						?>
														
						<form method="POST" action="ExportToExcelDp.php" id="form_action">
						
						  <div class="form-row">
							<div class="form-group col-md-4">
							  <label for="ClientName">Client Name</label>
							  <input type="Text" class="form-control" id="ClientName" name="ClientName" placeholder="Client Name" value="<?= ($_SESSION["nameDP"] != '') ? $_SESSION["nameDP"] : '' ?>">
							</div>
							<div class="form-group col-md-4">
							  <label for="inputDiscount">Discount</label>
							  <input type="text" class="form-control" id="Discount" name="Discount" placeholder="Enter Discount In %"  value="<?= ($_SESSION["discountDP"] != '') ? $_SESSION["discountDP"] : '' ?>">
							  <input type="hidden" class="form-control" id="id" name="id" value="<?= ($_SESSION["QidDp"] != '') ? $_SESSION["QidDp"] : '' ?>">
							</div>
							<div class="form-group col-md-2" style="top: 20px;">
							  <label for="inputImage">With Image</label>
							  <input type="checkbox"  name="formWheelchair" value="Yes" />
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
if(isset($_SESSION["shopping_cartDp"])){
    $total_price = 0;
?>		
<div id ="order_table" class="col-md-11">	
<table id="example" class="table table-bordered table-striped" data-page-length="200">
		<thead>
			<tr>
			<th width="15%"></th>
			<th width="20%">ITEM NAME</th>
			<th width="15%">SIZE</th>
			<th  width="20%">QUANTITY</th>
			<th width="10%">UNIT PRICE</th>
			<th width="10%">ITEMS TOTAL</th>
			<th width="10%">REMOVE</th>
			</tr>	
		</thead>
		

	<tbody>
			<?php		
			foreach ($_SESSION["shopping_cartDp"] as $product){
			?>
			<tr>
			<td><img src='<?php echo $product["image"]; ?>' width="50" height="40" /></td>

			<td><?php echo $product["name"]; ?></td>

			<td><?php echo $product["size"]; ?></td>

			<td>
			
				<div class="col-xs-6">

				<input type='hidden' name='name' value="<?php echo $product["name"]; ?>" />
				<input type='hidden' name='action' value="change" />
				
				<input type='number' class="form-control col-md-4 quantityDP" name='quantity' pattern="[0-9]{3}" value="<?= $product["quantity"]; ?>" data-product_id="<?php echo $product["id"]; ?>" autocomplete="off"/>
                </div>
			
			
			</td>

			<td><?php echo "&#8377; " .$product["price"]; ?></td>

			<td><?php echo "&#8377; " .$product["price"]*$product["quantity"]; ?></td>
			<td>
				
				
				<button name="delete" class="btn btn-danger btn-xs delete" id="<?= $product["id"]; ?>"> X </button>
				
			</td>
			</tr>
			<?php
			$total_price += ($product["price"]*$product["quantity"]);
			}
			?>
			<tr>
			<td colspan="6" align="right">
			<strong>TOTAL: <?php echo "&#8377; " .$total_price; ?></strong>
			</td>
			</tr>
		</tbody>

	</table>
	</div>				
		  <?php
}

/* 	 echo "<pre>";
	print_r($_SESSION["shopping_cartDp"]);
	echo "</pre>";  
 */?>		
				
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