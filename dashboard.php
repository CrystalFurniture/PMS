<?php
		include_once('DB/conn.php');

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		
			$Catlogue = mysqli_query($connect,"select * from products where catalogue = '1'");
		
			$totalCatlogue = mysqli_num_rows($Catlogue);


			$Stop = mysqli_query($connect,"select * from products  where catalogue = '0'");
		
			$totalStop = mysqli_num_rows($Stop);


			$Cat = mysqli_query($connect,"select * from categories");
		
			$totalCat = mysqli_num_rows($Cat);


			$user = mysqli_query($connect,"select * from users");
		
			$totalUser = mysqli_num_rows($user);



?>

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
        Dashboard
        <small>Control panel</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Dashboard</li>
      </ol>
    </section>
    <!-- Main content -->
    <section class="content">
      <!-- Small boxes (Stat box) -->
      <?php //if($is_admin == true): ?>

        <div class="row">
			<?php if(in_array('createProduct', $UserPermission) || in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){ ?>
          <div class="col-lg-4 col-md-6 col-xs-12">
            <!-- small box -->
            <div class="small-box bg-aqua">
              <div class="inner">
                <h3><?php echo $totalCatlogue; ?></h3>

                <p>Total Catalogue Products</p>
              </div>
              <div class="icon">
			  
                <i class="ion ion-bag"></i>
              </div>
              <a href="ManageProducts.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
            </div>
          </div>
		  
          <div class="col-lg-4 col-md-6 col-xs-12">
            <!-- small box -->
            <div class="small-box bg-maroon">
              <div class="inner">
                <h3><?php echo $totalStop; ?></h3>

                <p>Total Non Stock items</p>
              </div>
              <div class="icon">
			  
                <i class="ion ion-filing"></i>
              </div>
              <a href="OldProducts.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
            </div>
          </div>
			<?php } ?>
          <!-- ./col -->
			<?php if(in_array('createCategory', $UserPermission) || in_array('updateCategory', $UserPermission) || in_array('viewCategory', $UserPermission) || in_array('deleteCategory', $UserPermission)){ ?>
          
		  <div class="col-lg-4 col-md-6 col-xs-12">
            <!-- small box -->
            <div class="small-box bg-green">
              <div class="inner">
                <h3><?php echo $totalCat; ?></h3>

                <p>Total Categories</p>
              </div>
              <div class="icon">
                <i class="ion ion-stats-bars"></i>
              </div>
              <a href="Category.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
            </div>
          </div>
			<?php } ?>
          <!-- ./col -->
			 <?php if(in_array('createUser', $UserPermission) || in_array('updateUser', $UserPermission) || in_array('viewUser', $UserPermission) || in_array('deleteUser', $UserPermission)){ ?>
          <div class="col-lg-4 col-md-6 col-xs-12">
            <!-- small box -->
            <div class="small-box bg-yellow">
              <div class="inner">
                <h3><?php echo $totalUser; ?></h3>

                <p>Total Users</p>
              </div>
              <div class="icon">
                <i class="ion ion-android-people"></i>
              </div>
              <a href="Users.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
            </div>
          </div>
			 <?php } ?>
          <!-- ./col -->
         
          <!-- ./col -->
			<?php if(in_array('updateSetting', $UserPermission)){ ?>
          <div class="col-lg-4 col-md-6 col-xs-12">
            <!-- small box -->
            <div class="small-box bg-olive">
              <div class="inner">
                <h3><p>&nbsp; </p><p>&nbsp; </p></h3>

                <p>Setting</p>
              </div>
              <div class="icon">
				<i class="ion ion-settings"></i>
              </div>
              <a href="Setting.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
            </div>
          </div>
			<?php } ?>
          <!-- ./col -->
         
          <!-- ./col -->
          <div class="col-lg-4 col-md-6 col-xs-12">
            <!-- small box -->
            <div class="small-box bg-red">
              <div class="inner">
                <h3><p>&nbsp; </p><p>&nbsp; </p></h3>

                <p>Log Out</p>
              </div>
              <div class="icon">
				<i class="ion ion-log-out"></i>
              </div>
              <a href="logOut.php" class="small-box-footer">More info <i class="fa fa-arrow-circle-right"></i></a>
            </div>
          </div>
          <!-- ./col -->
         
          <!-- ./col -->
        </div>
        <!-- /.row -->
      <?php //endif; ?>
      

    </section>
    <!-- /.content -->
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
