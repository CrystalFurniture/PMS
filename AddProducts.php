<?php
		include_once('DB/conn.php');

		
function filter_text($data) {
  $data = trim($data);
  $data = addslashes($data);
  //$data = htmlspecialchars($data);
  $data = str_replace("\n","",$data);
  return $data;
}

		error_reporting(E_ALL);
			
		if(isset($_POST['submit'])){
					
				if(isset($_POST['catalogue'])){
						$checkBox = 1;
					}else{
						$checkBox = 0;
					}	
			$category = addslashes($_POST['category']);
			$product_name = filter_text($_POST['product_name']);
			$color = addslashes($_POST['color']);
			$dimension = addslashes($_POST['dimension']);
			$desc = addslashes($_POST['description']);
			$tags = addslashes($_POST['tags']); 
			
			
			  $errors= array();

		if(isset($_FILES['product_image'])){
			  $file_name = $_FILES['product_image']['name'];
			  $file_size =$_FILES['product_image']['size'];
			  $file_tmp =$_FILES['product_image']['tmp_name'];
			  $file_type =$_FILES['product_image']['type'];
			  $file_ext = pathinfo($file_name, PATHINFO_EXTENSION);
			  $file_ext = strtolower($file_ext);
			  
			  $expensions= array("jpeg","jpg","png");
			  
			  if(in_array($file_ext,$expensions)=== false){
				  
				 $errors[]="extension not allowed, please choose a JPEG or PNG file.";
			  }
			  
			  if($file_size > 209715200){
				 $errors[]='File size must be excately 2 MB';
			  }
			  
			  if(empty($errors)==true){
				  
				  
					$sqls = mysqli_query($connect, "select * from categories where id = '".$category."'");
					
					$rows = mysqli_fetch_assoc($sqls);
					
					$path = "Category/Office Furniture";
					if(!is_dir($path)){
					  mkdir($path);
					} 


				  
				$file = round(microtime(true)).".".$file_ext;
				  
				 $upload = $path."/".$file;

		 		if(move_uploaded_file ($file_tmp,$upload)){
			
				$n_width=500;          // Fix the width of the thumb nail images
				$n_height=500;         // Fix the height of the thumb nail imaage

				$ThumbImage="Category/thumb/".round(microtime(true))."_thumb.".$file_ext;   // Path where thumb nail image will be stored
				
				//echo $ThumbImage;

				if (!($_FILES['product_image']['type'] =="image/jpeg" || $_FILES['product_image']['type']=="image/png"))
				{echo "Your uploaded file must be of JPG or GIF. Other file types are not allowed<BR>";
				exit;}

				if (@$_FILES['product_image']['type']=="image/png")
				{
				$im=ImageCreateFrompng($upload);
				$width=ImageSx($im);              		 // Original picture width is stored
				$height=ImageSy($im);                	// Original picture height is stored
				$n_height=($n_width/$width) * $height; // Add this line to maintain aspect ratio
				$newimage=imagecreatetruecolor($n_width,$n_height);
				imagefilledrectangle($newimage, 0, 0, $n_width, $n_height, imagecolorallocate($newimage, 255, 255, 255));
				imageCopyResized($newimage,$im,0,0,0,0,$n_width,$n_height,$width,$height);
				Imagepng($newimage,$ThumbImage);
				chmod("$ThumbImage",0777);
				}
				////////// end of png file thumb nail creation//////////

				if($_FILES['product_image']['type']=="image/jpeg"){
				$im=ImageCreateFromJPEG($upload); 
				$width=ImageSx($im);              // Original picture width is stored
				$height=ImageSy($im);             // Original picture height is stored
				$n_height=($n_width/$width) * $height; // Add this line to maintain aspect ratio
				$newimage=imagecreatetruecolor($n_width,$n_height);                 
				imageCopyResized($newimage,$im,0,0,0,0,$n_width,$n_height,$width,$height);
				ImageJpeg($newimage,$ThumbImage);
				chmod("$ThumbImage",0777);
				}

		}

				 
				 $result = mysqli_query($connect,"INSERT INTO products(name, color, dimension, image, thumb, description, category_id, catalogue, tags) VALUES
				 ('".$product_name."', '".$color."', '".$dimension."', '".$upload."', '".$ThumbImage."', '".$desc."', '".$category."', '".$checkBox."', '".$tags."')");
				 
						 if($result){
							 
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
						 }
								
							
							
							}else{
								$errmsg	=	base64_encode(serialize("Something is wrong"));
								header("Location:AddProducts.php?failmsg=$errmsg");
								exit;
							}
					   }
					   
					}			 
					
		$sql = mysqli_query($connect, "SELECT * FROM categories");

			$category = array();

			while( $row = mysqli_fetch_array($sql) ) {


				$category[$row["id"]] = $row["name"];
				//$category[] = ;

			}

	//$category = json_encode($category);
		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		


?>
<link href="assets/plugins/taginput/css/tagsinput.css" rel="stylesheet" type="text/css">

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
      <small>Products</small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Products</li>
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
            <h3 class="box-title">Add Product</h3>
          </div>
          <!-- /.box-header -->
          <form role="form" method="post" enctype="multipart/form-data" id="form_action">
              <div class="box-body">


                <div class="form-group">

                  <label for="product_image">Image</label>
                  <div class="kv-avatar">
                      <div class="file-loading">
                          <input id="product_image" name="product_image" type="file" >
                      </div>
                  </div>
                </div>
			
				<div class="form-group">
                  <label for="category">Category</label>
                  <select class="form-control select_group" id="category" name="category">
                    <?php foreach ($category as $k => $v): ?>
                      <option class="form-control" value="<?php echo $k ?>"><?php echo $v ?></option>
                    <?php endforeach ?>
                  </select>
                </div>
				
                <div class="form-group">
                  <label for="product_name" >Product name</label>
                  <input type="text" class="form-control" id="product_name" name="product_name" placeholder="Enter product name" autocomplete="off"/>
                </div>

                <div class="form-group">
                  <label for="color">Color</label>
                  <input type="text" class="form-control" id="color" name="color" placeholder="Enter Color" autocomplete="off" />
                </div>

                <div class="form-group">
                  <label for="dimension">Dimension</label>
                  <input type="text" class="form-control" id="dimension" name="dimension" placeholder="Enter dimension" autocomplete="off" />
                </div>

              
                <div class="form-group">
                  <label for="description">Description</label>
                  <textarea type="text" class="form-control" id="description" name="description" placeholder="Enter 
                  description" autocomplete="off">
                  </textarea>
                </div>
                
                <div class="form-group">
				  <label class="form-check-label" for="catalogue">Is Catalogue Item : </label>
                  <input type="checkbox" name="catalogue" value="checked" class="form-check-input" >
                </div>

                <div class="form-group">
				  <label class="form-check-label" for="tags">Tags : </label>
                  <input type="text" data-role="tagsinput" name="tags" value="">
                </div>

              </div>
              <!-- /.box-body -->

			  <hr>
				<blockquote>
				  <h2>Price Details</h2>
				</blockquote>
               <div class="box-body">
					<div class="form-group">
					  <label class="form-check-label" for="mrp_price">MRP Price : </label>
					  <input type="text" class="form-control" name="mrp_price" id="mrp_price">
					 </div>
					 
					 <div class="form-group">
					  <label class="form-check-label" for="dp_price">DP Price : </label>
					  <input type="text" class="form-control" name="dp_price" id="dp_price">
					 </div>

					 <div class="form-group">
					  <label class="form-check-label" for="special_price">Special Price : </label>
					  <input type="text" class="form-control" name="special_price" id="special_price" >
					 </div>

			  </div>

			  
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
<script src="assets/plugins/taginput/js/tagsinput.js"></script>

<script type="text/javascript">
  $(document).ready(function() {
    $(".select_group").select2();
    $("#description").wysihtml5();


    $("#mainProductNav").addClass('active');
    $("#addProductNav").addClass('active');
    
    var btnCust = '<button type="button" class="btn btn-secondary" title="Add picture tags" ' + 
        'onclick="alert(\'Call your custom code here.\')">' +
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
        allowedFileExtensions: ["jpg", "png", "gif"]
    });

  });
  
  

/* function checkForm(form)
  {
	 
			var elem = document.getElementById('product_image');
			  if(form.product_image.value == '')
			  {
					document.getElementById('err_prod').innerHTML="*Please enter a username*";
					 form.product_image.focus();
				return false;
			  }
			 

	  		 if ( form.product_name.value == '' ) 
				{
					document.getElementById("product_name").style.borderColor="Red";
					 form.product_name.focus();
				return false;
				}

	  		 if ( form.color.value == '' ) 
				{
					document.getElementById("color").style.borderColor="Red";
					 form.color.focus();
				return false;
				}

	  		 if ( form.dimension.value == '' ) 
				{
					document.getElementById("dimension").style.borderColor="Red";
					 form.dimension.focus();
				return false;
				}

	  		 if ( form.description.value == '' ) 
				{
					document.getElementById("description").style.borderColor="Red";
					 form.description.focus();
				return false;
				}
			
	  		 
 }					
 */

	jQuery(function ($) {
    var validator = $('#form_action').validate({
        rules: {
            product_image: {
                required: true
            },
			product_name: {
                required: true
            },
            color: {
                required: false
            },
            
            dimension: {
                required: false
            },
            description: {
                required: true
            },
            mrp_price: {
                number: true
            },
            dp_price: {
                number: true
            },
            special_price: {
                number: true
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

