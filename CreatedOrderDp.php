<?php
		include_once('DB/conn.php');
error_reporting(0);
		@session_start();
		$userName = $_SESSION['username'];

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		

?>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
	 
         

		  <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Create
        <small>Invoice</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Invoice</li>
      </ol>
    </section>
    <!-- Main content -->

	<section class="content">
        <div class="row">
            <div class="col-md-12">
				<div id="messages">
				
					
				  <?php if($status != ''): ?>
					<div class="alert alert-warning alert-dismissible" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					  <?php echo $status; ?>
					</div>
				  
				  <?php endif; ?>
					
					
					<?php
						if(!empty($_SESSION["shopping_cartDp"]))
					{
						$cart_count = count(array_keys($_SESSION["shopping_cartDp"]));
					?>
						<div class="cart_div">
						<a href="ViewOrderDp.php"><img src="assets/images/Excel.png" width="50"/> <span><?php echo $cart_count; ?></span></a>
						</div>
					<?php
					}
					?>
				</div>

				<div class="box">
				  <div class="box-header">
					<h3 class="box-title">Manage Orders</h3>
					
					
				  </div>
				  <!-- /.box-header -->
				  <div class="box-body">
					
					<table id="manageOrder" class="table table-bordered table-striped" data-page-length='25'>
					  <thead>
					  <tr>
						
						<th style="width: 5%;">Sr No</th>
						<th style="width: 15%;">Image</th>
						<th style="width: 15%;">Name</th>
						<th style="width: 20%;">Description</th>
						<th style="width: 20%;">Color</th>
						<th style="display:none">tags</th>
						<th style="width: 10%;">price</th>
						<th style="width: 10%;">Add</th>
					   
					  </tr>
					  </thead>
					  
					  <tbody>
					  <?php
							
						  //$sql = mysqli_query($connect, "SELECT o.id AS OrderID, o.customer_name, o.customer_address, o.customer_phone, o.user_id, o.gross_amount, o.received_amount, o.paid_status, o.date, u.id, u.firstname, u.lastname FROM orders o INNER JOIN users u ON o.user_id = u.id where u.id = '".$_SESSION['user_id']."'");
						  
						  $result = mysqli_query($connect,"SELECT p.*, pl.* FROM products p INNER JOIN pricelist pl on pl.p_prodId = p.id");
						  $i = 1;
							while($row = mysqli_fetch_assoc($result)){

						
						echo "<div class='product_wrapper'>
							<tr role='row' class='odd'>
							  
							  <input type='hidden' name='name' value=".str_replace(' ','',$row['name'])." />
							  <input type='hidden' name='description' value=". strip_tags($row['description'])." />
							  <td>".$i++." </td>
							  <td><img src='".$row['thumb']."' width='152' height='98'/></td>
							  <td>".$row['name']."</td>
							  <td>".$row['description']."</td>
							  <td>".$row['color']."</td>
							  <td  style=\"display:none\">".$row['tags']."</td>
							  <td>&#8377; ".$row['dp_price']."</td>
							  <td> <button type='submit' class='buy add_dp' id='".$row['id']."'><i class='fa fa-plus-circle fa-2x' aria-hidden='true'></i></button></td>
							
							</tr>
							  </div>";
						 
						}
					  ?>
					  
					  </tbody>

					</table>
					
				  </div>
				  <!-- /.box-body -->
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
    $(document).ready(function() {
      $("#dashboardMainMenu").addClass('active');
    }); 
  </script>
<script type="text/javascript">
// remove functions 
function removeFunc(id)
{
	
  if(id) {
    $("#removeForm").on('submit', function() {

      var form = $(this);

      // remove the text-danger
      $(".text-danger").remove();

      $.ajax({
        url: 'Delete.php',
        type: form.attr('method'),
        data: { order_id:id, table: 'orders'  },

        success: function(data) {
			
			console.log(data);

		//$("#manageOrder").load(" #manageOrder");
			location.reload(); // 

			if(data.success == false) {
			 
            $("#messages").html('<div class="alert alert-warning alert-dismissible" role="alert">'+
              '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
              '<strong> <span class="glyphicon glyphicon-exclamation-sign"></span> OOPs!!! Something went wrong </strong></div>'); 

            // hide the modal
            $("#removeModal").modal('hide');

          } else {
			 

             $("#messages").html('<div class="alert alert-success alert-dismissible" role="alert">'+
              '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
              '<strong> <span class="glyphicon glyphicon-ok-sign"></span> Successfully Deleted a Category! </strong></div>');

            // hide the modal
            $("#removeModal").modal('hide');
         }
        }
      }); 

      return false;
    });
  }

}

$(document).ready( function () {
	
  var table = $('#manageOrder').DataTable({
  
	"searching" : true,
	"ordering": true,
	"bLengthChange" : false,
	"bInfo": true,
            

     columnDefs: [
      {
        
        orderDataType: 'dom-checkbox'
      },
	   { 
       'searchable'    : true, 
       'targets'       : [1, 2, 4] 
		}
    ], 
		
  });
   
});


</script>
<script src="js/ajax.js"></script>