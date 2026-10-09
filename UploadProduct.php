 <?php  
 //cart.php  
error_reporting(E_ALL);
	$host       = "localhost";
	$user       = "root";
	$pass       = "";
	$database   = "pms";
//	$user       = "crystalsaleadmin";
//	$pass       = "Crystal!@#$";
	
	$connect    = mysqli_connect($host, $user, $pass, $database) or die("Error : ".mysql_error());		

 @session_start();  



if(isset($_POST['Submit']))
{
require('library/php-excel-reader/excel_reader2.php');
require('library/SpreadsheetReader.php');

	

	
	
    $mimes = array('application/vnd.ms-excel','text/xls','text/xlsx','application/vnd.oasis.opendocument.spreadsheet','application/vnd.openxmlformats-officedocument.spreadsheetml.sheet');
    if(in_array($_FILES["file"]["type"],$mimes))
    {
        $uploadFilePath = 'uploads/'.basename($_FILES['file']['name']);

		move_uploaded_file($_FILES['file']['tmp_name'], $uploadFilePath);
        $Reader = new SpreadsheetReader($uploadFilePath);

		$totalSheet = count($Reader->sheets());
		/* For Loop for all sheets */
        for($i=0;$i<$totalSheet;$i++)
        {
            $Reader->ChangeSheet($i);
			$a = 1; // array Incrementor
            foreach ($Reader as $Row)
            {

				$name 		= addslashes(isset($Row[0])  ? $Row[0]  : '');
                $desc 		= addslashes(isset($Row[2])  ? $Row[2]  : '');
                $width 		= $Row[3];
                $depth 		= $Row[4];
                $height		= $Row[5];
                $color		= $Row[6];
                $dp			= $Row[7];
                $mrp		= $Row[10];
                $category	= $Row[9];
				
				$dimension = $width .'x'. $depth .'x'. $height;
				
				$result = mysqli_query($connect,"select * from products  WHERE REPLACE(name, ' ', '') =  REPLACE('".$name."', ' ','' )");
				
				if(mysqli_num_rows($result) > 0){
					
					$row = mysqli_fetch_array($result);
						
					 $sql = mysqli_query($connect,"UPDATE products SET name='".$name."', color= '".$color."', dimension= '".$dimension."',  description= '".$desc."', category_id= '".$category."' , catalogue= '1' , tags= '' WHERE id =  '".$row['id']."'");
					if($sql){
						
						$query = mysqli_query($connect, "SELECT * FROM pricelist where p_prodId = '".$row['id']."'");
								
								$rowcount = mysqli_num_rows($query);
								
									if($rowcount > 0){
										$rows = mysqli_fetch_assoc($query);
										
										$stmt =  mysqli_query($connect,"UPDATE pricelist SET mrp_price='".$mrp."', dp_price= '".$dp."', special_price= ''  WHERE p_id =  '".$rows["p_id"]."'");									
									}else{
										
										$stmt =  mysqli_query($connect,"INSERT INTO pricelist(p_prodId, mrp_price, dp_price, special_price, price_date) VALUES
										 ('".$row['id']."', '".$mrp."', '".$dp."', '', '".date('d/m/Y')."')");

										
									}
					
								$sucmsg	=	base64_encode(serialize("Successfully Added new Product!"));
								header("Location:AddProducts.php?msg=$sucmsg");

					}else{
								$errmsg	=	base64_encode(serialize("Something is wrong"));
								//header("Location:AddProducts.php?failmsg=$errmsg");
								//exit;
					}
					
					
				}else{
					
				$sql = mysqli_query($connect,"INSERT INTO products(name, color, dimension, description, category_id, catalogue, tags) VALUES
				 ('".$name."', '".$color."', '".$dimension."',  '".$desc."', '".$category."', '1', '')");
				 
						 if($sql){
							 
							 $prod_id = mysqli_insert_id($connect);
							 
								$query = mysqli_query($connect, "SELECT * FROM pricelist where p_prodId = '".$prod_id."'");
								
								$rowcount = mysqli_num_rows($query);
								
									if($rowcount > 0){
										$rows = mysqli_fetch_assoc($query);
										
										$stmt =  mysqli_query($connect,"UPDATE pricelist SET mrp_price='".$_POST['mrp_price']."', dp_price= '".$_POST['dp_price']."', special_price= '".$_POST['special_price']."'  WHERE p_id =  '".$rows["p_id"]."'");									
									}else{
										
										$stmt =  mysqli_query($connect,"INSERT INTO pricelist(p_prodId, mrp_price, dp_price, special_price, price_date) VALUES
										 ('".$prod_id."', '".$_POST['mrp_price']."', '".$_POST['dp_price']."', '".$_POST['special_price']."', '".date('d/m/Y')."')");

										
									}
							 
							
								$sucmsg	=	base64_encode(serialize("Successfully Added new Product!"));
								header("Location:AddProducts.php?msg=$sucmsg");
						 }else{
								$errmsg	=	base64_encode(serialize("Something is wrong"));
								//header("Location:AddProducts.php?failmsg=$errmsg");
								//exit;
								}

				}
				
				//echo $name ."-->". $tags ."</br>";
				//$result = mysqli_query($connect,"UPDATE products tags= '".$tags."' WHERE REPLACE(name, ' ', '') =  REPLACE('".$name."', ' ','' )");
				
				
            }
        }

/* echo "<pre>";
print_r($_SESSION['NameTags']);
echo "</pre>";
 */		      if(unlink($uploadFilePath)){
            
				echo ("<script LANGUAGE='JavaScript'>
				window.alert('Succesfully Uploaded');
				
				</script>"); 
			  }
        }
       
}
?>

<html>  
      <head>  
           <title>PHP 2 Tally</title>  
			<meta name="viewport" content="width=device-width, initial-scale=1">
           <script src="https://ajax.googleapis.com/ajax/libs/jquery/2.2.0/jquery.min.js"></script>  
           <link rel="stylesheet" href="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/css/bootstrap.min.css" />  
           <script src="https://maxcdn.bootstrapcdn.com/bootstrap/3.3.6/js/bootstrap.min.js"></script>  
		   <script src="https://use.fontawesome.com/1016ea7b4c.js"></script>
		   

		   
	<script>
	$(window).load(function(){
		 $('.loader').fadeOut();
	});
	</script>
		   
		  <!-- // Datatables // -->
		 
           <link rel="stylesheet" href="https://cdn.datatables.net/1.10.20/css/jquery.dataTables.min.css" />  

           <script src="https://cdn.datatables.net/1.10.20/js/jquery.dataTables.min.js"></script>  

		   <link rel="stylesheet" href="css/select2.min.css" />
			<style>
			.select2-dropdown {top: 22px !important; left: 8px !important;}
			</style>
		   
		   <style>
		   div#manageTable_paginate, div#manageTable_length {
					display: none;
				}
		   </style>
			
			<style>
			input, textarea {
				border: none !important;
				border-bottom: 1px solid #ccc !important;
				box-shadow: none !important;
				font-size: 11px !important;
			}
			.order_table {
				font-family: "Helvetica Neue",Helvetica,Arial,sans-serif;
				font-size: 12px;
				line-height: 1.4 !important;
			}
			
					
			.prod {
			top:0;
			z-index:100;
			}
			
			</style>
			<script>
			$(document).ready(function() {
				$('#manageTable').DataTable({
			});
				
			} );

			
			
			</script>

      </head>  
<body>
<?php 		/* include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php'); */
		
 ?>
              <div class="container-fluid">

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
		<div class="col-md-4 offset-md-4">
		</div>
		<div class="col-md-4 offset-md-4">
	<form method="POST" action="" enctype="multipart/form-data" style="margin-top: 30px;">

	<div class="form-group">
            <label>Upload excel File</label>
            <input type="file" name="file" class="form-control" accept=".csv, application/vnd.openxmlformats-officedocument.spreadsheetml.sheet, application/vnd.ms-excel" />  
        </div>
        <div class="form-group">
            <button type="submit" name="Submit" class="btn btn-success">Upload</button>
        </div>
    </form>
	</div>

		
	
</div>

<!-- Footer -->
<?php //include 'footer.php'; ?>
<!-- Footer -->

</body>
</html>