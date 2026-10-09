<?php
		include_once('DB/conn.php');

		error_reporting(E_ALL);
			
		if(isset($_POST['submit'])){
					
			$customer_name = $_POST['party_name'];
			$customer_address = $_POST['party_add'];
			$customer_phone = $_POST['party_contact'];
			$product = serialize($_POST['product']);
			$qty = serialize($_POST['qty']);
			$user_id = $_SESSION['user_id'];
			$gross_amount = $_POST['total'];
			$received_amount = $_POST['received'];
			$paid_status = $_POST['paid_status'];
			$date = date('d/m/Y'); 
			
			$result = mysqli_query($connect,"INSERT INTO orders(customer_name, customer_address, customer_phone, user_id, prod_name, prod_qty, gross_amount, received_amount, date, paid_status) VALUES 
			('".$customer_name."','".$customer_address."','".$customer_phone."','".$user_id."','".$product."','".$qty."','".$gross_amount."','".$received_amount."','".$date."','".$paid_status."')");
				 
				 
				echo  "INSERT INTO orders(customer_name, customer_address, customer_phone, user_id, prod_name, prod_qty, gross_amount, received_amount, date, paid_status) VALUES 
			('".$customer_name."','".$customer_address."','".$customer_phone."','".$user_id."','".$product."','".$qty."','".$gross_amount."','".$received_amount."','".$date."','".$paid_status."')";
				 
				 /* if($result){
				 $sucmsg	=	base64_encode(serialize("Successfully Added new Product!"));
								header("Location:CreatedOrder.php?msg=$sucmsg");
								
							
							
					}else{
								$errmsg	=	base64_encode(serialize("Something is wrong"));
								header("Location:AddOrder.php?failmsg=$errmsg");
								exit;
							} */
					   
					   
		}			 
					

	//$category = json_encode($category);
		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		


?>
<link href="assets/plugins/taginput/css/tagsinput.css" rel="stylesheet" type="text/css">

<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.12.0/jquery.validate.js"></script>
<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.12.0/additional-methods.js"></script>
<style>
#form_action .error {
    color: #ff0000a8;
    border-color: #ff0000a1;
}
label.error {
    display: none !important;
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
      <small>Products</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Products</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <!-- Small boxes (Stat box) -->
    <div class="row">
      <div class="col-md-12 col-xs-12">

        <div id="messages"></div>

        

        <div class="box">
          <div class="box-header">
            <h3 class="box-title">Add Product</h3>
          </div>
          <!-- /.box-header -->
          <form role="form" method="post" enctype="multipart/form-data" id="form_action">
              <div class="box-body">
				
                <div class="form-group">
                  <label for="party_name" >Party name</label>
                  <input type="text" class="form-control" id="party_name" name="party_name" placeholder="Enter Party name" autocomplete="off"/>
                </div>
              
                <div class="form-group">
                  <label for="party_add" >Party Address</label>
                  <input type="text" class="form-control" id="party_add" name="party_add" placeholder="Enter Address" autocomplete="off"/>
                </div>
              
                <div class="form-group">
                  <label for="party_contact" >Party Contact</label>
                  <input type="text" class="form-control" id="party_contact" name="party_contact" placeholder="Enter Mobile No" autocomplete="off"/>
                </div>

				<div class="form-group form-inline auto_add">

				<label for="product"  style="width: 10% !important;">Products</label>
						<input type="text" class="form-control auto" id="product" name="product[]"  placeholder = "Add Product Name" style="width: 40% !important;"  autocomplete="off" required />
						<input type="text" class="form-control" id="qty" name="qty[]" pattern="[0-9]{0,3}"  placeholder = "Add Quantity"  style="width: 40% !important;"  autocomplete="off" required />
						<a href="javascript:void(0);" class="add_button" title="Add field"><img src="assets/images/add.png"/></a>
				</div>
              
                <div class="form-group">
                  <label for="total" >Total amount</label>
                  <input type="text" class="form-control" id="total" name="total" placeholder="Enter Total Amount" autocomplete="off"/>
                </div>
              
                <div class="form-group">
                  <label for="received" >Received</label>
                  <input type="text" class="form-control" id="received" name="received" placeholder="Enter Received Amount" autocomplete="off"/>
                </div>
              
              	<div class="form-group">
                  <label for="paid_status">Payment Mode</label>
                  <select class="form-control select_group" id="paid_status" name="paid_status">
                   
                      <option class="form-control" value="">---Select Payment Option---</option>
                      <option class="form-control" value="Cash">Cash</option>
                      <option class="form-control" value="Cash">Cheque</option>
                      <option class="form-control" value="Cash">Card Payment</option>
                    
                  </select>
                </div>


              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button type="submit" name="submit" class="btn btn-primary">Save Changes</button>
                <a href="#" class="btn btn-warning">Back</a>
              </div>
            </form>
          <!-- /.box-body -->
        </div>
        <!-- /.box -->
      </div>
      <!-- col-md-12 -->
    </div>
    <!-- /.row -->
    

  </section>
  <!-- /.content -->
</div>
<!-- /.content-wrapper -->
<script src="assets/plugins/taginput/js/tagsinput.js"></script>

<script type="text/javascript">

	jQuery(function ($) {
    var validator = $('#form_action').validate({
        rules: {
            party_name: {
                required: true
            },
			party_add: {
                required: true
            },
            party_contact: {
                required: true,
				phoneUS: true
            },
            product: {
                required: true
            },
            qty: {
                required: true,
				number: true
            },
            total: {
                required: true,
				number: true
            },
            received: {
                required: true,
				number: true
            },
            paid_status: {
                required: true
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

<!-- jQuery library -->

<!-- jQuery UI library -->
<link rel="stylesheet" href="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/themes/smoothness/jquery-ui.css">
<script src="https://ajax.googleapis.com/ajax/libs/jqueryui/1.12.1/jquery-ui.min.js"></script>
<script type="text/javascript">
$(function() {
    
    //autocomplete
    $(".auto").autocomplete({
        source: "Search.php",
        minLength: 1
    });                

});
</script>

<script type="text/javascript">
$(document).ready(function(){
    var maxField = 10; //Input fields increment limitation
    var addButton = $('.add_button'); //Add button selector
    var wrapper = $('.auto_add'); //Input field wrapper
    var fieldHTML = '<div class="controls" style="padding-top: 10px;"> <label for="product" style="width: 10% !important;"></label> <input type="text" class="form-control auto" id="product" name="product[]"  placeholder = "Add Product Name" style="width: 40% !important;"  autocomplete="off" required /> <input type="text" class="form-control" id="qty" name="qty[]" pattern="[0-9]{0,3}" placeholder = "Add Quantity"  style="width: 40% !important;"  autocomplete="off" required /> <a href="javascript:void(0);" class="remove_button"><img src="assets/images/remove.png" /></a> </div> '; //New input field html 
    var x = 1; //Initial field counter is 1
    
    //Once add button is clicked
    $(addButton).click(function(){
        //Check maximum number of input fields
        if(x < maxField){ 
            x++; //Increment field counter
            $(wrapper).append(fieldHTML); //Add field html
        }
    });
    
    //Once remove button is clicked
    $(wrapper).on('click', '.remove_button', function(e){
        e.preventDefault();
        $(this).parent('.controls').remove(); //Remove field html
        x--; //Decrement field counter
    });
});
</script>
<?php
		include_once('templates/footer.php');

?>

