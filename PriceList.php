<?php
		include_once('DB/conn.php');

		error_reporting(0);
			
require_once('vendor/php-excel-reader/excel_reader2.php');
require_once('vendor/SpreadsheetReader.php');
$date = date('d/m/Y');

if (isset($_POST["submit"]))
{
    
   
  $allowedFileType = ['application/vnd.ms-excel','text/xls','text/xlsx','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
  
  if(!empty($_FILES["file"]["type"])){
  

        $targetPath = 'uploads/'.$_FILES['file']['name'];
        move_uploaded_file($_FILES['file']['tmp_name'], $targetPath);
        
        $Reader = new SpreadsheetReader($targetPath);
        
					$a = 1;
        $sheetCount = count($Reader->sheets());
		
        for($i = 0; $i <= $sheetCount; $i++)
        {
            $Reader->ChangeSheet($i);
            $j =1;
            foreach ($Reader as $Row)
            // Start For each
			{ 
						$prod_id =  '';
								$ProductName = str_replace(" ","",$Row[1]);
								$Size = str_replace(" ","",$Row[4]);
								$MrpPrice =  ($Row[6] != '') ? $Row[6] : '';
								$DpPrice = ($Row[7] != '') ? $Row[7] : '';
								//$SpecialPrice = ($Row[8] != '') ? $Row[8] : '';

							$stmt= mysqli_query($connect, 'SELECT * FROM products where REPLACE (name, " " , "") = "'.$ProductName.'"');
							$num=mysqli_num_rows($stmt);
							
							
							 if($num > 0)
							 {
								$exce = mysqli_fetch_assoc($stmt);
								$prod_id = $exce['id'];
								//echo "<h1>".$prod_id."</h1></br>";
								$stmt1 = mysqli_query($connect, "SELECT * FROM pricelist where p_prodId = '".$prod_id."'");
								$num1 = mysqli_num_rows($stmt1);
								
								
								if($num1 > 0){

								$exce1 = mysqli_fetch_array($stmt1);
								$price_id = $exce1['p_id'];
									$result = mysqli_query($connect,"UPDATE pricelist SET mrp_price='".$MrpPrice."', dp_price= '".$DpPrice."' , price_date= '".$date."' WHERE p_id =  '".$price_id."'");
								
								} else {
									
									//echo "<h1> Not equal To 1 </h1>";
									
									$result = mysqli_query($connect,"INSERT INTO pricelist(p_prodId, mrp_price, dp_price, price_date) VALUES
									('".$prod_id."', '".$MrpPrice."', '".$DpPrice."',  '".$date."')");
									
									/* echo "INSERT INTO pricelist(p_prodId, mrp_price, dp_price, special_price, price_date) VALUES
									('".$prod_id."', '".$MrpPrice."', '".$DpPrice."', '".$SpecialPrice."', '".$date."')"; */
								}
							} 

        
         }
		 
  }
  
}
}

	//$category = json_encode($category);
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
      <small>Price List</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Price List</li>
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
            <h3 class="box-title">Upload Price List</h3>
          </div>
          <!-- /.box-header -->
          <form role="form" method="post" enctype="multipart/form-data" id="form_action">
              <div class="box-body">
                <div class="form-group">
                  <label for="product_image">Upload Price List</label>
                  <div class="kv-avatar">
                      <div class="file-loading">
                          <input id="product_image" name="file" type="file" >
                      </div>
                  </div>
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
<script type="text/javascript">
  $(document).ready(function() {
    $(".select_group").select2();
    $("#description").wysihtml5();


    $("#mainProductNav").addClass('active');
    $("#addProductNav").addClass('active');
    
    var btnCust = '<button type="button" class="btn btn-secondary" title="Add xls, xlsx, csv File" ' + 
        'onclick="">' +
        '<i class="glyphicon glyphicon-tag"></i>' +
        '</button>'; 
    $("#product_image").fileinput({
        overwriteInitial: true,
        maxFileSize: 1500,
        showClose: false,
        showCaption: false,
        browseLabel: '',
        removeLabel: '',
        browseIcon: '<i class="glyphicon glyphicon-folder-open"></i>',
        removeIcon: '<i class="glyphicon glyphicon-remove"></i>',
        removeTitle: 'Cancel or reset changes',
        elErrorContainer: '#kv-avatar-errors-1',
        msgErrorClass: 'alert alert-block alert-danger',
        // defaultPreviewContent: '<img src="/uploads/default_avatar_male.jpg" alt="Your Avatar">',
        layoutTemplates: {main2: '{preview} ' +  btnCust + ' {remove} {browse}'},
        allowedFileExtensions: ["xls", "xlsx", "csv"]
    });

  });
</script>
	<?php
		include_once('templates/footer.php');
	?>

