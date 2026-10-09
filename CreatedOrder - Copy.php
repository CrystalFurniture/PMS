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
        Created
        <small>Orders</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Created</li>
      </ol>
    </section>
    <!-- Main content -->

	<section class="content">
        <div class="row">
            <div class="col-md-12">
				<div id="messages"></div>
					
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
					
					<a href="AddOrder.php" class="btn btn-primary float-right"> Add Orders</a>


				<div class="box">
				  <div class="box-header">
					<h3 class="box-title">Manage Orders</h3>
					
					
				  </div>
				  <!-- /.box-header -->
				  <div class="box-body">
					
					<table id="manageOrder" class="table table-bordered table-striped" data-page-length='25'>
					  <thead>
					  <tr>
						
						<th>Sr No</th>
						<th>Name</th>
						<th>Address</th>
						<th>Contact</th>
						<th>Sales Person</th>
						<th>Total Amount</th>
						<th>Received Amount</th>
						<th>Payment Mode</th>
						<th>Date</th>

						<th>Action</th>
					   
					  </tr>
					  </thead>
					  
					  <tbody>
					  <?php
							if($_SESSION['user_id'] == '7'):
						  $sql = mysqli_query($connect, "SELECT o.id AS OrderID, o.customer_name, o.customer_address, o.customer_phone, o.user_id, o.gross_amount, o.received_amount, o.paid_status, o.date, u.id, u.firstname, u.lastname FROM orders o INNER JOIN users u ON o.user_id = u.id ");
						  else:
						  $sql = mysqli_query($connect, "SELECT o.id AS OrderID, o.customer_name, o.customer_address, o.customer_phone, o.user_id, o.gross_amount, o.received_amount, o.paid_status, o.date, u.id, u.firstname, u.lastname FROM orders o INNER JOIN users u ON o.user_id = u.id where u.id = '".$_SESSION['user_id']."'");
						  endif;

						  $i = 1;
	
						 while( $row=mysqli_fetch_array($sql) ) {  // preparing an array

					  ?>
					  
					  <tr role="row" class="odd">
						<td>
							<?php echo $i++; ?>
						</td>
						<td>
							<?php echo $row["customer_name"]; ?>
						</td>
						<td>
							<?php echo $row["customer_address"]; ?>
						</td>
						<td>
							<?php echo $row["customer_phone"]; ?>
						</td>
						<td>
							<?php echo $row["firstname"]." ". $row["lastname"]; ?>
						</td>
						<td>
							<?php echo $row["gross_amount"]; ?>
						</td>
						<td>
							<?php echo $row["received_amount"]; ?>
						</td>
						<td>
							<?php echo $row["paid_status"]; ?>
						</td>
						<td>
							<?php echo $row["date"]; ?>
						</td>

						<td>
							<a class="btn btn-default" href="ViewOrder.php?id=<?php echo $row['OrderID'] ?>"><i class="fa fa-eye"></i></a> 
							<button type="button" class="btn btn-default" onclick="removeFunc(<?php echo $row['OrderID'] ?>)" data-toggle="modal" data-target="#removeModal"><i class="fa fa-trash"></i></button>
						</td>

						</tr>
					  <?php
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
