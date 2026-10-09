<?php
		include_once('DB/conn.php');

		
		if(isset($_POST['submit'])){
			
			$category = $_POST['category_name'];
		
		echo "select * from categories where name = '".$category."'";
			
			$path = "Category/".$category;
			if(!is_dir($path)){
			  mkdir($path);
			} 
			$sql = mysqli_query($connect,"select * from categories where name = '".$category."'");

			$row = mysqli_num_rows($sql);

			if($row > 0){ 
			
				$errmsg	=	base64_encode(serialize("Category Already Exists!"));
				header("Location:AddCategory.php?failmsg=$errmsg");
				exit;

			 }
			else{
				$result = mysqli_query($connect,"INSERT INTO categories(name)values('".$category."')");
				
				echo $row;
						
						 if($result){
							
								$sucmsg	=	base64_encode(serialize("Successfully Added new Category!"));
								header("Location:Category.php?msg=$sucmsg");
								exit;
							
							}else{
								$errmsg	=	base64_encode(serialize("Error adding new Category!"));
								header("Location:AddCategory.php?failmsg=$errmsg");
								exit;
							} 
			} 
		
		}
?>
		
<?php
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
      <small>Category</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Category</li>
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
            <h3 class="box-title">Add Category</h3>
          </div>
          <!-- /.box-header -->
          <form role="form" method="post" id="form_action" enctype="multipart/form-data">
              <div class="box-body">

                <div class="form-group">
                  <label for="category_name">Category name</label>
                  <input type="text" class="form-control" id="category_name" name="category_name" placeholder="Enter Category name" autocomplete="off"/>
                </div>


              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button type="submit" class="btn btn-primary" name="submit">Save Changes</button>
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
<script>

	jQuery(function ($) {
    var validator = $('#form_action').validate({
        rules: {
            
			category_name: {
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

<?php
		include_once('templates/footer.php');

?>

