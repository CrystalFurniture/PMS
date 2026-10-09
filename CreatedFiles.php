<?php
		include_once('DB/conn.php');
error_reporting(0);
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
                                
                                <a class="btn btn-success" id="nav-profile-tab" data-toggle="tab" href="#nav-profile" role="tab" aria-controls="nav-profile" aria-selected="false">Catalogue Items</a>
                                <a class="btn btn-danger" id="nav-contact-tab" data-toggle="tab" href="#nav-contact" role="tab" aria-controls="nav-contact" aria-selected="false">Non-Stock Items</a>
                            </div>
                        </nav>
                        <div class="tab-content" id="nav-tabContent">
                            
                            <div class="tab-pane fade" id="nav-profile" role="tabpanel" aria-labelledby="nav-profile-tab">
                                
<?php
	$url ="Download/Crystal Catalogue Search/Catalogue/".$userName."/";

if ($handle = opendir($url)) {

    while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..") {

        	//$url = $_GET['id'].'/'.$entry;
        	// echo '<a href="http://localhost/PMS/Core/'.$url.'" download="'.$entry.'">' .$entry.'</a> <br>';
       		$fullUrl = $url.$entry;
        ?>


			<?php 
			echo '<a href="Download.php?id='.$fullUrl.'"  class="small-box-footer">'
			?>
          <div class="col-lg-3 col-md-3 col-xs-6">
            <!-- small box -->
            <div class="small-box bg-aqua">
              <div class="inner">
				<p></p>
                <span><?= $entry ?></span>
              </div>
              <div class="icon">
			  
                <i class="ion-android-download"></i>
              </div>
                <?php echo '<span class="small-box-footer">File Created:  ' .date("d M Y", filemtime($url)).' <i class="fa fa-arrow-circle-right"></i> </span>'; ?>

            </div>
          </div>
      </a>

		  
<?php  
		 }
	}

    closedir($handle);
}


?> 

                            </div>
                           <!-- Second Tab -->
                            <div class="tab-pane fade" id="nav-contact" role="tabpanel" aria-labelledby="nav-contact-tab">
                                <!-- Content -->

<?php
	$url ="Download/Crystal Catalogue Search/Non-Stock/".$userName."/";

if ($handle = opendir($url)) {

    while (false !== ($entry = readdir($handle))) {

        if ($entry != "." && $entry != "..") {

        	//$url = $_GET['id'].'/'.$entry;
        	// echo '<a href="http://localhost/PMS/Core/'.$url.'" download="'.$entry.'">' .$entry.'</a> <br>';
       		$fullUrl = $url.$entry;
        ?>


			<?php 
			echo '<a href="Download.php?id='.$fullUrl.'"  class="small-box-footer">'
			?>
          <div class="col-lg-3 col-md-3 col-xs-6">
            <!-- small box -->
            <div class="small-box bg-aqua">
              <div class="inner">
				<p></p>
                <span><?= $entry ?></span>
              </div>
              <div class="icon">
			  
                <i class="ion-android-download"></i>
              </div>
                <?php echo '<span class="small-box-footer">File Created:  ' .date("d M Y", filemtime($url)).' <i class="fa fa-arrow-circle-right"></i> </span>'; ?>

            </div>
          </div>
      </a>

		  
<?php  
		 }
	}

    closedir($handle);
}


?> 
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
    }); 
  </script>
