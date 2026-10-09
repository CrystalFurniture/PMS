<?php
		include_once('DB/conn.php');

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
error_reporting(E_ALL);
?>


<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
      Manage
      <small>Categories</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Categories</li>
    </ol>
  </section>

  <!-- Main content -->
  <section class="content">
    <!-- Small boxes (Stat box) -->
    <div class="row">
      <div class="col-md-12 col-xs-12">

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
			
			<?php if(in_array('createCategory', $UserPermission)){ ?>
			<a href="AddCategory.php" class="btn btn-primary float-right"> Add Categories</a>
			<?php } ?>

			<div class="box">
          <div class="box-header">
            <h3 class="box-title">Manage Categories</h3>
			
			
          </div>
          <!-- /.box-header -->
          <div class="box-body">
            <table id="manageTable" class="table table-bordered table-striped" data-page-length='25'>
              <thead>
              <tr>
                
                <!-- <th>Sr No.</th>-->
                <th>Category Name</th>
                <th>Status</th>
				  <?php if(in_array('updateCategory', $UserPermission) || in_array('deleteCategory', $UserPermission)){ ?>

                  <th>Action</th>
				  <?php } ?>
                               
               
              </tr>
              </thead>

            </table>
          </div>
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

<!-- remove brand modal -->
<div class="modal fade" tabindex="-1" role="dialog" id="removeModal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Remove Category</h4>
      </div>

      <form role="form" action="#" method="post" id="removeForm">
        <div class="modal-body">
          <p>Do you really want to remove?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit"  class="btn btn-primary">Save changes</button>
        </div>
      </form>


    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->
<?php 
		include_once('templates/footer.php');

?>


<script type="text/javascript" language="javascript" >
    $(document).ready(function() {
        var dataTable = $('#manageTable').DataTable( {
            "processing": true,
            "serverSide": true,
            "searching": false,
			"ordering": true,
            "ajax":{
                url :"dataCategory.php", // json datasource
                type: "post",  // method  , by default get
                error: function(){  // error handling
                    $(".employee-grid-error").html("");
                    $("#manageTable").append('<tbody class="employee-grid-error"><tr><th colspan="3">No data found in the server</th></tr></tbody>');
                    $("#employee-grid_processing").css("display","none");
 
                }
            }
        } );
		
    } );

	// remove functions 

function removeFunc(id)
{
	/* dataTable.destroy();
 */
	var dataTable = $('#manageTable').DataTable( {
            "processing": true,
            "serverSide": true,
            "searching": false,
			"ordering": true,
            "ajax":{
                url :"dataCategory.php", // json datasource
                type: "post",  // method  , by default get
                error: function(){  // error handling
                    $(".employee-grid-error").html("");
                    $("#manageTable").append('<tbody class="employee-grid-error"><tr><th colspan="3">No data found in the server</th></tr></tbody>');
                    $("#employee-grid_processing").css("display","none");
 
                }
            }
        } );
	
  if(id) {
    $("#removeForm").on('submit', function() {

      var form = $(this);

      // remove the text-danger
      $(".text-danger").remove();

      $.ajax({
        url: 'Delete.php',
        type: form.attr('method'),
        data: { product_id:id, table: 'categories'  },

        success: function(data) {
			
			console.log(data);

		dataTable.ajax.reload(null, false); 
         // 

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


</script>
