<?php

session_start();
		include_once('DB/conn.php');
	
		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		
$status="";
if (isset($_POST['action']) && $_POST['action']=="remove"){
if(!empty($_SESSION["shopping_cart"])) {
	foreach($_SESSION["shopping_cart"] as $key => $value) {
		if($_POST["name"] == $key){
		unset($_SESSION["shopping_cart"][$key]);
		$status = "<div class='box' style='color:red;'>
		Product is removed from your cart!</div>";
		}
		if(empty($_SESSION["shopping_cart"]))
		unset($_SESSION["shopping_cart"]);
			}		
		}
}

if (isset($_POST['action']) && $_POST['action']=="change"){
  foreach($_SESSION["shopping_cart"] as &$value){
    if($value['name'] === $_POST["name"]){
        $value['quantity'] = $_POST["quantity"];
        break; // Stop the loop after we've found the product
    }
}
  	
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
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Invoice</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <!-- Small boxes (Stat box) -->
  <div class="container">
  
<div class="col-md-11" >

	<span>&nbsp; </span>
		<span>&nbsp; </span>
		
						<?php
						if(!empty($_SESSION["shopping_cart"])) {
						$cart_count = count(array_keys($_SESSION["shopping_cart"]));
						?>
														
						<form method="POST" action="ExportToExcel.php" id="form_action">
						  <div class="form-row">
							<div class="form-group col-md-6">
							  <label for="ClientName">Client Name</label>
							  <input type="Text" class="form-control" id="ClientName" name="ClientName" placeholder="Client Name">
							</div>
							<div class="form-group col-md-6">
							  <label for="inputDiscount">Discount</label>
							  <input type="text" class="form-control" id="Discount" name="Discount" placeholder="Enter Discount In %">
							</div>
						  </div>

						<div class="cart_div">
						
							<button type="submit" ><img src="assets/images/Excel.png" width="50"/></button>
							<span><?php echo $cart_count; ?></span>
						  </div>
							

						  </form>
							
						
						
						

						</div>
						<?php
						}
						?>

			<span>&nbsp; </span>
			
<?php
if(isset($_SESSION["shopping_cart"])){
    $total_price = 0;
?>				
<table id="example" class="table table-bordered table-striped" data-page-length='100'>
		<thead>
			<tr>
			<th></th>
			<th>ITEM NAME</th>
			<th>SIZE</th>
			<th>QUANTITY</th>
			<th>UNIT PRICE</th>
			<th>ITEMS TOTAL</th>
			<th>REMOVE</th>
			</tr>	
		</thead>
		

	<tbody>
			<?php		
			foreach ($_SESSION["shopping_cart"] as $product){
			?>
			<tr>
			<td><img src='<?php echo $product["image"]; ?>' width="50" height="40" /></td>

			<td><?php echo $product["name"]; ?></td>

			<td><?php echo $product["size"]; ?></td>

			<td>
			<form method='post' action=''>
				<div class="col-xs-6">

				<input type='hidden' name='name' value="<?php echo $product["name"]; ?>" />
				<input type='hidden' name='action' value="change" />
				
				<input type='number' class="form-control col-md-4" name='quantity' pattern="[0-9]{3}" value="<?= $product["quantity"]; ?>"  onchange="this.form.submit()" autocomplete="off"/>
                </div>
			
			
			</form>
			</td>

			<td><?php echo "&#8377; " .$product["price"]; ?></td>

			<td><?php echo "&#8377; " .$product["price"]*$product["quantity"]; ?></td>
			<td>
				<form method='post' action=''>
				
				<input type='hidden' name='name' value="<?php echo $product["name"]; ?>" />
				<input type='hidden' name='action' value="remove" />
				<button type='submit' class='remove'><i class="fa fa-times" aria-hidden="true"></i></button>
				</form>
			</td>
			</tr>
			<?php
			$total_price += ($product["price"]*$product["quantity"]);
			}
			?>
			<tr>
			<td colspan="5" align="right">
			<strong>TOTAL: <?php echo "&#8377; " .$total_price; ?></strong>
			</td>
			</tr>
		</tbody>

	</table>
					
		  <?php
}

	/* echo "<pre>";
	print_r($_SESSION["shopping_cart"]);
	echo "</pre>"; */
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
				max: 25
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



	</script>
