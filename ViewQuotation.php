<?php
		include_once('DB/conn.php');
error_reporting(E_ALL);
		@session_start();
		$userName = $_SESSION['username'];

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		

?>
<style>
	.project-tab {
    padding: 10%;
    margin-top: -8%;
}
.project-tab #tabs{
    background: #007b5e;
    color: #eee;
}
.project-tab #tabs h6.section-title{
    color: #eee;
}
.project-tab #tabs .nav-tabs .nav-item.show .nav-link, .nav-tabs .nav-link.active {
    color: #0062cc;
    background-color: transparent;
    border-color: transparent transparent #f3f3f3;
    border-bottom: 3px solid !important;
    font-size: 16px;
    font-weight: bold;
}
.project-tab .nav-link {
    border: 1px solid transparent;
    border-top-left-radius: .25rem;
    border-top-right-radius: .25rem;
    color: #0062cc;
    font-size: 16px;
    font-weight: 600;
}
.project-tab .nav-link:hover {
    border: none;
}
.project-tab thead{
    background: #f3f3f3;
    color: #333;
}
.project-tab a{
    text-decoration: none;
    color: #333;
    font-weight: 600;
}

#nav-tabContent {
    padding: 10px;
}

input[type=search]{
	
	width : unset !important;
}
</style>
  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
	 
         

		  <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Created
        <small>Files</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Created</li>
      </ol>
    </section>
    <!-- Main content -->

<section id="tabs" class="project-tab">
                <div class="row">
                    <div class="col-md-12">
                        <nav>
                            <div class="nav nav-tabs nav-fill" id="nav-tab" role="tablist">
                                
                                <a class="btn btn-success" id="nav-profile-tab" data-toggle="tab" href="#nav-profile" role="tab" aria-controls="nav-profile" aria-selected="false">MRP</a>
                                <a class="btn btn-danger" id="nav-contact-tab" data-toggle="tab" href="#nav-contact" role="tab" aria-controls="nav-contact" aria-selected="false">DP</a>
                            </div>
                        </nav>
                        <div class="tab-content" id="nav-tabContent">
                            
                            <div class="tab-pane fade" id="nav-profile" role="tabpanel" aria-labelledby="nav-profile-tab">
                                
<table id="manageMRP" class="table table-bordered table-striped" data-page-length="25">
		<thead>
			<tr>
			<th></th>
			<th>Quotatio No</th>
			<th>Party Name</th>
			<th>Date</th>
			<th>REMOVE</th>
			</tr>	
		</thead>
	<tbody>
<?php
			$i = 1;

			$result = mysqli_query($connect,"select * from tbl_quotation where q_user = '".$_SESSION['username']."' AND q_type = 'MRP' ");
    while ($row = mysqli_fetch_assoc($result)) {

        ?>

			<tr>
			<td><?= $i++; ?></td>

			<td><?php echo $row["q_num"]; ?></td>

			<td><?php echo $row["q_name"]; ?></td>

			<td><?php echo $row["q_date"]; ?></td>

			<td>
					<a class="tip-bottom" href="DetailQuotationMRP.php?id=<?= $row["q_id"]; ?>" data-toggle="tooltip" title="View Quotation"><i class="fa fa-eye" aria-hidden="true"></i></a> |
					<a class="tip-bottom" href="ViewOrderMRP.php?id=<?= $row["q_id"]; ?>" data-toggle="tooltip" title="Edit Quotation"><i class="fa fa-pencil-square-o " aria-hidden="true" style="color:green"></i></a> |
					<a class="tip-bottom" href="#" onclick="removeMRP(<?= $row["q_id"]; ?>)" title="Delete"><i class="fa fa-trash-o" aria-hidden="true" style="color:indianred"></i></a>
			</td>
			</tr>
		
		

		  
<?php  
	}



?> 
		</tbody>

	</table>

                            </div>
                           <!-- Second Tab -->
                            <div class="tab-pane fade" id="nav-contact" role="tabpanel" aria-labelledby="nav-contact-tab">
                                <!-- Content -->

<table id="manageDP" class="table table-bordered table-striped" data-page-length="25">
		<thead>
			<tr>
			<th></th>
			<th>Quotatio No</th>
			<th>Party Name</th>
			<th>Date</th>
			<th>REMOVE</th>
			</tr>	
		</thead>
	<tbody>
<?php
			$i = 1;

			$result = mysqli_query($connect,"select * from tbl_quotation where q_user = '".$_SESSION['username']."' AND q_type = 'DP' ");
      while ($row = mysqli_fetch_assoc($result)) {

        ?>

			<tr>
			<td><?= $i++; ?></td>

			<td><?php echo $row["q_num"]; ?></td>

			<td><?php echo $row["q_name"]; ?></td>

			<td><?php echo $row["q_date"]; ?></td>

			<td>
					<a class="tip-bottom" href="DetailQuotationDp.php?id=<?= $row["q_id"]; ?>" data-toggle="tooltip" title="View Quotation"><i class="fa fa-eye" aria-hidden="true"></i></a> |
					<a class="tip-bottom" href="ViewOrderDp.php?id=<?= $row["q_id"]; ?>" data-toggle="tooltip" title="Edit Quotation"><i class="fa fa-pencil-square-o " aria-hidden="true" style="color:green"></i></a> |
					<a class="tip-bottom" href="#" onclick="removeDP(<?= $row["q_id"]; ?>)" title="Delete"><i class="fa fa-trash-o" aria-hidden="true" style="color:indianred"></i></a>
			</td>
			</tr>
		
		

		  
<?php  
	}



?> 
		</tbody>

	</table>
                            </div>
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
	  PartyTable();
    }); 
	
	
	
function PartyTable() {

	
  var table = $('#manageDP').DataTable({
  });
   
  var table = $('#manageMRP').DataTable({
  });
   
}
  </script>
<script type="text/javascript">
// remove functions 
function removeMRP(id)
{
	var result = confirm("Are you sure to delete?");
	  if(result) {
      var form = $(this);
			window.setTimeout(function() {
			$(".alert").fadeTo(500, 0).slideUp(500, function() {
				$(this).hide();
				
			});
		}, 1500);
      // remove the text-danger
      $(".text-danger").remove();

      $.ajax({
        url: 'Delete.php',
        type: form.attr('method'),
        data: { product_id:id, table: 'tbl_quotation'  },

        success: function(data) {
			
			console.log(data);
			$("#manageMRP").load(" #manageMRP");
			//$("#manageMRP").ajax.reload();
			PartyTable();
			//$("#PurchaseOrderTable").load(" #PurchaseOrderTable");
			

			if(data.success == false) {
			 
            $("#messages").html('<div class="alert alert-warning alert-dismissible" role="alert">'+
              '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
              '<strong> <span class="glyphicon glyphicon-exclamation-sign"></span> OOPs!!! Something went wrong </strong></div>'); 

            // hide the modal
            $("#removeModal").modal('hide');
          } else {
			 

             $("#messages").html('<div class="alert alert-success alert-dismissible" role="alert">'+
              '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
              '<strong> <span class="glyphicon glyphicon-ok-sign"></span> Successfully Deleted a Order! </strong></div>');

            // hide the modal
            $("#removeModal").modal('hide');
			//window.setTimeout(function(){location.reload()},2000)
        }
        }
      }); 

      return false;
  }

}

// remove functions 
function removeDP(id)
{
	var result = confirm("Are you sure to delete?");
	  if(result) {
      var form = $(this);
			window.setTimeout(function() {
			$(".alert").fadeTo(500, 0).slideUp(500, function() {
				$(this).hide();
				
			});
		}, 1500);
      // remove the text-danger
      $(".text-danger").remove();

      $.ajax({
        url: 'Delete.php',
        type: form.attr('method'),
        data: { product_id:id, table: 'tbl_quotation'  },

        success: function(data) {
			
			console.log(data);
			$("#manageDP").load(" #manageDP");
			PartyTable();
			

			if(data.success == false) {
			 
            $("#messages").html('<div class="alert alert-warning alert-dismissible" role="alert">'+
              '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
              '<strong> <span class="glyphicon glyphicon-exclamation-sign"></span> OOPs!!! Something went wrong </strong></div>'); 

            // hide the modal
            $("#removeModal").modal('hide');
          } else {
			 

             $("#messages").html('<div class="alert alert-success alert-dismissible" role="alert">'+
              '<button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>'+
              '<strong> <span class="glyphicon glyphicon-ok-sign"></span> Successfully Deleted a Order! </strong></div>');

            // hide the modal
            $("#removeModal").modal('hide');
			//window.setTimeout(function(){location.reload()},2000)
        }
        }
      }); 

      return false;
  }

}

</script>
