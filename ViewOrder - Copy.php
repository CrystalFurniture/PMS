<?php

		include_once('DB/conn.php');

		$stmt= mysqli_query($connect, "SELECT o.id AS OrderID, o.customer_name, o.customer_address, o.customer_phone, o.user_id,  o.prod_name,  o.prod_qty, o.gross_amount, o.received_amount, o.paid_status, o.date, u.id, u.firstname, u.lastname FROM orders o INNER JOIN users u ON o.user_id = u.id where o.id = '".$_GET['id']."'");
		$exce = mysqli_fetch_array($stmt);
		
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
      <small>Orders</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Orders</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <!-- Small boxes (Stat box) -->
  <div class="container">
  <h2> Details For <?php echo $exce['customer_name']; ?></h2>
<div class="col-md-11" >

	<span>&nbsp; </span>
		<span>&nbsp; </span>
		
						<button class="btn btn-success btn-large right" onclick="printDiv();">Print it</button>

			<span>&nbsp; </span>
<table id="example" class="Display table borderless " >

		<tr>
	    
		<th style="width: 200px;border-bottom: 3px solid black !important;"><img src="images/logo.png" class="thumbnail" style="width:200px; padding:20px"></th>
		<th colspan="2" style="border-bottom: 3px solid black !important; text-align: center;"><h4>CRYSTAL FURNITURE INDUSTRIES </h4>
			Office : Umiya Chambers, Darodkar Square, Central Avenue, Nagpur-440032.<br>
			 Phone: 0712-2766405, 2770867, Fax:-6611795 M :- 9922919052<br>
			 E-mail: rakesh@crystalfurniture.in  Web  :  www.crystalfurnitech.com</th>
			 
		<th style="width: 200px;border-bottom: 3px solid black !important;"><img src="images/3C.png" class="thumbnail" style="width:150px; padding:20px"></th>
		</tr>
		

		<tr>
	    
		<!--<th  colspan=2 style="text-align: right; padding-right: 40px;">-->

		</th>
		
		
		
		
		<th  colspan=4 style="text-align: right; padding-right: 40px;">
				<span style="font-size: 14px;"> Date : <?php  echo $exce['date']; ?></br></span>

		</th>
		
		</tr>
				

		
		<tr>
	    
		<th>Name  </th>
		<th colspan=3 style="text-align: justify;"><strong> <?php  echo $exce['customer_name']; ?> </strong></br>
		</tr>
				
		<th>Address  </th>
		<th colspan=3 style="text-align: justify;"><strong> <?php  echo $exce['customer_address']; ?> </strong></br>
		</tr>
				
		<th>Contact No  </th>
		<th colspan=3 style="text-align: justify;"><strong> <?php  echo $exce['customer_phone']; ?> </strong></br>
		</tr>

		<tr>
	    <th >Sales Person </th>
		<th colspan=3> <?php  echo $exce["firstname"]." ". $exce["lastname"]; ?></th>
		</tr>

		
		<?php

				$product = unserialize(urldecode($exce['prod_name']));
				$qty = unserialize(urldecode($exce['prod_qty']));
		?>
		<tr>
	    <th  rowspan="<?php echo count($product)+1; ?>" style="vertical-align: middle;">Products</th>
	    <th  colspan=2>Products Name</th>
	    <th>Product Qty</th>
		</tr>
				<?php
							
						foreach ($product as $key=>$val){
				?>
		<tr>
		<th colspan=2><?php  echo $val; ?> </th>
		<th><?php  echo $qty[$key]; ?> </th>
		</tr>

		<?php } 
			
			?>
				<tr>
	    <th >Order Amount </th>
		<th> <?php  echo $exce['gross_amount']; ?></th>


		<th >Received Amount </th>
		<th> <?php  echo $exce['received_amount'] ." ( ". $exce['paid_status'] ." )" ; ?></th>

		</tr>

</table>
					
				
				
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
	</script>
