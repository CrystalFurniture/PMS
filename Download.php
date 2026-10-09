<?php
		include_once('DB/conn.php');

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		

?>

  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
	 
         

		  <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Download
        <small>Files</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Download</li>
      </ol>
    </section>
    <!-- Main content -->
    <section class="content">
      <!-- Small boxes (Stat box) -->
      <?php //if($is_admin == true): ?>

        <div class="row">

        <?php
if ($handle = opendir($_GET['id'])) {

    while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..") {

        	$url = $_GET['id'].'/'.$entry;
        	// echo '<a href="http://localhost/PMS/Core/'.$url.'" download="'.$entry.'">' .$entry.'</a> <br>';
       
        ?>


			<?php 
			echo '<a href="http://'.URL.'PMS/Core/'.$url.'" download="'.$entry.'" class="small-box-footer">'
			?>
          <div class="col-lg-6 col-md-6 col-xs-6 col-lg-offset-3 col-md-offset-3 col-xs-offset-3">
            <!-- small box -->
            <div class="small-box bg-aqua">
              <div class="inner">
                <h3><?= $entry ?></h3>
				<p><?php echo "File Created: ".date("d M Y", filemtime($url));?></p>
              </div>
              <div class="icon">
			  
                <i class="ion-android-download"></i>
              </div>
                <?php echo '<span class="small-box-footer">Download ' .$entry.' <i class="fa fa-arrow-circle-right"></i> </span>'; ?>

            </div>
          </div>
      </a>

		  
<?php  
		 }
	}

    closedir($handle);
}


?> 

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
