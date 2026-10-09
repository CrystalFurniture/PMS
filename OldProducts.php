<?php
ini_set('max_execution_time', 0); // 0 = Unlimited
@session_start();
$userName = $_SESSION['username'];

		include_once('DB/conn.php');

		require_once('TCPDF/tcpdf.php');

$path = 'F:\xampp\htdocs\PMS\Core\Download';

error_reporting(E_ALL);
if(isset($_POST['submit'])){
	
	
	$content = $_POST["content"];
	$checkBox = serialize($_POST['check']);
	
	$forCheck = $_POST['check'];
	
	//echo "<h1>".$checkBox."<br>". $content."</h1>" ;
	if(!is_dir($path."/Crystal Catalogue Search/Non-Stock/".$userName."/")){
			  mkdir($path."/Crystal Catalogue Search/Non-Stock/".$userName."/");
			} 
	
	$directory = $path."/Crystal Catalogue Search/Non-Stock/".$userName."/".$content;
			if(!is_dir($directory)){
			  mkdir($directory);
			} 
			chmod($directory,0777);
	
// PDF Script


		// create new PDF document
		$pdf = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		// set document information
		$pdf->SetCreator(PDF_CREATOR);
		$pdf->SetAuthor('Nicola Asuni');
		$pdf->SetTitle('TCPDF Example 001');
		$pdf->SetSubject('TCPDF Tutorial');
		$pdf->SetKeywords('TCPDF, PDF, example, test, guide');

		// set default header data
		$pdf->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, ' ', '', array(1,64,255), array(1,64,128));
		$pdf->setFooterData(array(0,64,0), array(0,64,128));

		// set header and footer fonts
		$pdf->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdf->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdf->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set margins
		$pdf->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdf->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdf->SetFooterMargin(PDF_MARGIN_FOOTER);

		// set auto page breaks
		$pdf->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

		// set image scale factor
		$pdf->setImageScale(PDF_IMAGE_SCALE_RATIO);

		// set some language-dependent strings (optional)
		if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
			require_once(dirname(__FILE__).'/lang/eng.php');
			$pdf->setLanguageArray($l);
		}

		// ---------------------------------------------------------

		// set default font subsetting mode
		$pdf->setFontSubsetting(true);

		// Set font
		// dejavusans is a UTF-8 Unicode font, if you only need to
		// print standard ASCII chars, you can use core fonts like
		// helvetica or times to reduce file size.
		$pdf->SetFont('dejavusans', '', 10, '', true);

		// Add a page
		// This method has several options, check the source code documentation for more information.
		$pdf->AddPage();

		// set text shadow effect
		$pdf->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));

		$html = '';
		
		$html .= '
		
            <style>
				img{
					margin-top:30px;
					width:600px;
					height:450px;
				}
                td{
					font-size:20px;
					font-weight:800;
				}
                
                
                span{
					font-size:14px;
					color: red;
					margin-top:30px;
				}
				table, th, td { 
					border: 1px solid #ffc8c8; 
					border-collapse: collapse; 
					padding: 6px; 
					text-align:center; 
		} 
                </style>';
// Set some content to print
			foreach($forCheck as $key => $val) {
				
				$query = mysqli_query($connect, "SELECT * FROM products where id = '".$val."' AND catalogue = '0' ");
				 
				$rows = mysqli_fetch_array($query);
				
				$str = preg_split ("/\(/", $rows['name']); 


						$html .= '<img src="'.$rows['image'].'"/>
									<h2 style="text-align: Center;">'.$str[0].'</h2>
									<table>
										<tr>
											<th colspan=2 style="text-align: left;">Product</th> 
											<th style="text-align: left;">Dimension</th> 
										</tr>

										';


				$stmt = mysqli_query($connect, "SELECT * FROM products  where name Like '".$str[0]."%'  AND catalogue = '0' ");
				 
				
				while( $sql = mysqli_fetch_array($stmt) ) {

						$html .= '
									<tr> 
										<td style="text-align: left;" colspan=2>'.$sql['description'].'</td> 
										<th style="text-align: left;">'.$sql['dimension'].'</th> 
									</tr>';
										
				}		
										

						$html .= '<tr> 
									<th colspan="3" style="text-align: right;">Color : '.$rows['color'].'</th> 
									
								</tr>';
										

						$html .= '<!-- The last row -->
									</table> 
									
									<br>
									<br>
									<br>
									

									<span> Note: Non- Stock Item</span><br>
									<span> Minimum Order Quantity Apply</span>
						
								' ;

			}
$pdf->writeHTMLCell(0, 0, '', '', $html, 0, 0, 0, true, '', true);

// ---------------------------------------------------------



// Supply a filename including the .pdf extension
$filename = $content.'.pdf';

// Create the full path
$full_path = $directory . '\\' . $filename;

// Output PDF
$pdf->Output($full_path, 'F');





//----------------------------------MRP------------------------------------------------------//

if(file_exists($directory . '/' . $filename)){
	
	
	$content1 = $_POST["content"];
	$checkBox = serialize($_POST['check']);
	
	$forCheck = $_POST['check'];
	
	//echo "<h1>".$checkBox."<br>". $content."</h1>" ;
	if(!is_dir($path."/Crystal Catalogue Search/Non-Stock/".$userName."/")){
			  mkdir($path."/Crystal Catalogue Search/Non-Stock/".$userName."/");
			} 
	
	$directory1 = $path."/Crystal Catalogue Search/Non-Stock/".$userName."/".$content1;
			if(!is_dir($directory1)){
			  mkdir($directory1);
			} 
			chmod($directory1,0777);
	
// PDF Script


		// create new PDF document
		$pdfMrp = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		// set document information
		$pdfMrp->SetCreator(PDF_CREATOR);
		$pdfMrp->SetAuthor('Nicola Asuni');
		$pdfMrp->SetTitle('TCPDF Example 001');
		$pdfMrp->SetSubject('TCPDF Tutorial');
		$pdfMrp->SetKeywords('TCPDF, PDF, example, test, guide');

		// set default header data
		$pdfMrp->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, ' ', '', array(1,64,255), array(1,64,128));
		$pdfMrp->setFooterData(array(0,64,0), array(0,64,128));

		// set header and footer fonts
		$pdfMrp->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdfMrp->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdfMrp->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set margins
		$pdfMrp->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdfMrp->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdfMrp->SetFooterMargin(PDF_MARGIN_FOOTER);

		// set auto page breaks
		$pdfMrp->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

		// set image scale factor
		$pdfMrp->setImageScale(PDF_IMAGE_SCALE_RATIO);

		// set some language-dependent strings (optional)
		if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
			require_once(dirname(__FILE__).'/lang/eng.php');
			$pdfMrp->setLanguageArray($l);
		}

		// ---------------------------------------------------------

		// set default font subsetting mode
		$pdfMrp->setFontSubsetting(true);

		// Set font
		// dejavusans is a UTF-8 Unicode font, if you only need to
		// print standard ASCII chars, you can use core fonts like
		// helvetica or times to reduce file size.
		$pdfMrp->SetFont('dejavusans', '', 10, '', true);

		// Add a page
		// This method has several options, check the source code documentation for more information.
		$pdfMrp->AddPage();

		// set text shadow effect
		$pdfMrp->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));

		$html = '';
		
		$html .= '
		
            <style>
				img{
					margin-top:30px;
					width:600px;
					height:450px;
				}
                td{
					font-size:20px;
					font-weight:800;
				}
                
                
                span{
					font-size:14px;
					color: red;
					margin-top:30px;
				}
				table, th, td { 
					border: 1px solid #ffc8c8; 
					border-collapse: collapse; 
					padding: 6px; 
					text-align:center; 
		} 
                </style>';
// Set some content to print
			foreach($forCheck as $key => $val) {
				
				$query = mysqli_query($connect, "SELECT * FROM products p LEFT join pricelist pp on p.id = pp.p_prodId where p.id = '".$val."' AND p.catalogue = '0' ");
				 
				$rows = mysqli_fetch_array($query);
				
				$str = preg_split ("/\(/", $rows['name']); 


						$html .= '<img src="'.$rows['image'].'"/>
									<h2 style="text-align: Center;">'.$str[0].'</h2>
									<table>
										<tr>
											<th style="text-align: left;">Product</th> 
											<th style="text-align: left;">Dimension</th> 
											<th style="text-align: left;">MRP Price</th> 
										</tr>

										';


				$stmt = mysqli_query($connect, "SELECT * FROM products p LEFT join pricelist pp on p.id = pp.p_prodId  where p.name Like '".$str[0]."%' AND p.catalogue = '0' ");
				 
				
				while( $sql = mysqli_fetch_array($stmt) ) {

						$html .= '
									<tr> 
										<td style="text-align: left;">'.$sql['description'].'</td> 
										<th style="text-align: left;">'.$sql['dimension'].'</th> 
										<th style="text-align: left;">&#x20b9; '.$sql['mrp_price'].'</th> 
									</tr>';
										
				}		
										

						$html .= '<tr> 
									<th colspan="3" style="text-align: right;">Color : '.$rows['color'].'</th> 
									
								</tr>';
										

						$html .= '<!-- The last row -->
									</table> 
									
									<br>
									<br>
									<br>
									

									<span> Note: Non- Stock Item</span><br>
									<span> Minimum Order Quantity Apply</span>
						
								' ;

			}
$pdfMrp->writeHTMLCell(0, 0, '', '', $html, 0, 0, 0, true, '', true);

// ---------------------------------------------------------



// Supply a filename including the .pdf extension
$filename1 = $content1.'_MRP.pdf';

// Create the full path
$full_path1 = $directory1 . '/' . $filename1;

// Output PDF
$pdfMrp->Output($full_path1, 'F');


}

//----------------------------------DP------------------------------------------------------//

if(file_exists($directory1 . '/' . $filename1)){
	
	
	$content2 = $_POST["content"];
	$checkBox = serialize($_POST['check']);
	
	$forCheck = $_POST['check'];
	
	//echo "<h1>".$checkBox."<br>". $content."</h1>" ;
	if(!is_dir($path."/Crystal Catalogue Search/Non-Stock/".$userName."/")){
			  mkdir($path."/Crystal Catalogue Search/Non-Stock/".$userName."/");
			} 
	
	$directory2 = $path."/Crystal Catalogue Search/Non-Stock/".$userName."/".$content2;
			if(!is_dir($directory2)){
			  mkdir($directory2);
			} 
			chmod($directory2,0777);
	
// PDF Script

		// create new PDF document
		$pdfDP = new TCPDF(PDF_PAGE_ORIENTATION, PDF_UNIT, PDF_PAGE_FORMAT, true, 'UTF-8', false);

		// set document information
		$pdfDP->SetCreator(PDF_CREATOR);
		$pdfDP->SetAuthor('Nicola Asuni');
		$pdfDP->SetTitle('TCPDF Example 001');
		$pdfDP->SetSubject('TCPDF Tutorial');
		$pdfDP->SetKeywords('TCPDF, PDF, example, test, guide');

		// set default header data
		$pdfDP->SetHeaderData(PDF_HEADER_LOGO, PDF_HEADER_LOGO_WIDTH, ' ', '', array(1,64,255), array(1,64,128));
		$pdfDP->setFooterData(array(0,64,0), array(0,64,128));

		// set header and footer fonts
		$pdfDP->setHeaderFont(Array(PDF_FONT_NAME_MAIN, '', PDF_FONT_SIZE_MAIN));
		$pdfDP->setFooterFont(Array(PDF_FONT_NAME_DATA, '', PDF_FONT_SIZE_DATA));

		// set default monospaced font
		$pdfDP->SetDefaultMonospacedFont(PDF_FONT_MONOSPACED);

		// set margins
		$pdfDP->SetMargins(PDF_MARGIN_LEFT, PDF_MARGIN_TOP, PDF_MARGIN_RIGHT);
		$pdfDP->SetHeaderMargin(PDF_MARGIN_HEADER);
		$pdfDP->SetFooterMargin(PDF_MARGIN_FOOTER);

		// set auto page breaks
		$pdfDP->SetAutoPageBreak(TRUE, PDF_MARGIN_BOTTOM);

		// set image scale factor
		$pdfDP->setImageScale(PDF_IMAGE_SCALE_RATIO);

		// set some language-dependent strings (optional)
		if (@file_exists(dirname(__FILE__).'/lang/eng.php')) {
			require_once(dirname(__FILE__).'/lang/eng.php');
			$pdfDP->setLanguageArray($l);
		}

		// ---------------------------------------------------------

		// set default font subsetting mode
		$pdfDP->setFontSubsetting(true);

		// Set font
		// dejavusans is a UTF-8 Unicode font, if you only need to
		// print standard ASCII chars, you can use core fonts like
		// helvetica or times to reduce file size.
		$pdfDP->SetFont('dejavusans', '', 10, '', true);

		// Add a page
		// This method has several options, check the source code documentation for more information.
		$pdfDP->AddPage();

		// set text shadow effect
		$pdfDP->setTextShadow(array('enabled'=>true, 'depth_w'=>0.2, 'depth_h'=>0.2, 'color'=>array(196,196,196), 'opacity'=>1, 'blend_mode'=>'Normal'));

		$html = '';
		
		$html .= '
		
            <style>
				img{
					margin-top:30px;
					width:600px;
					height:450px;
				}
                td{
					font-size:20px;
					font-weight:800;
				}
                
                
                span{
					font-size:14px;
					color: red;
					margin-top:30px;
				}
				table, th, td { 
					border: 1px solid #ffc8c8; 
					border-collapse: collapse; 
					padding: 6px; 
					text-align:center; 
		} 
                </style>';
// Set some content to print
			foreach($forCheck as $key => $val) {
				
				$query = mysqli_query($connect, "SELECT * FROM products p LEFT join pricelist pp on p.id = pp.p_prodId where p.id = '".$val."' AND p.catalogue = '0' ");
				 
				$rows = mysqli_fetch_array($query);
				
				$str = preg_split ("/\(/", $rows['name']); 


						$html .= '<img src="'.$rows['image'].'"/>
									<h2 style="text-align: Center;">'.$str[0].'</h2>
									<table>
										<tr>
											<th style="text-align: left;">Product</th> 
											<th style="text-align: left;">Dimension</th> 
											<th style="text-align: left;">DP Price</th> 
										</tr>

										';


				$stmt = mysqli_query($connect, "SELECT * FROM products p LEFT join pricelist pp on p.id = pp.p_prodId  where p.name Like '".$str[0]."%' AND p.catalogue = '0' ");
				 
				
				while( $sql = mysqli_fetch_array($stmt) ) {

						$html .= '
									<tr> 
										<td style="text-align: left;">'.$sql['description'].'</td> 
										<th style="text-align: left;">'.$sql['dimension'].'</th> 
										<th style="text-align: left;">&#x20b9; '.$sql['dp_price'].'</th> 
									</tr>';
										
				}		
										

						$html .= '<tr> 
									<th colspan="3" style="text-align: right;">Color : '.$rows['color'].'</th> 
									
								</tr>';
										

						$html .= '<!-- The last row -->
									</table> 
									
									<br>
									<br>
									<br>
									<span> Note: Above are Ex Factory Prices, Transpotation &amp; GST Extra </span><br>
									<span> Non- Stock Item</span><br>
									<span> Minimum Order Quantity Apply</span>
						
								' ;


			}
$pdfDP->writeHTMLCell(0, 0, '', '', $html, 0, 0, 0, true, '', true);

// ---------------------------------------------------------



// Supply a filename including the .pdf extension
$filename2 = $content.'_DP.pdf';

// Create the full path
$full_path2 = $directory2 . '/' . $filename2;

// Output PDF
$pdfDP->Output($full_path2, 'F');



		}	
		$url = "Download/Crystal Catalogue Search/Non-Stock/".$userName."/".$content;
		Header("Location:Download.php?id=$url");

}
		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');

?>
   <!--prettyTag CSS-->
<link rel="stylesheet" href="css/prettytag.css" />
   <!--prettyTag JS-->

<style>
.form-control {
	font-size: 20px;
    font-weight: 600;
}
</style>
<!-- Content Wrapper. Contains page content -->
<div class="content-wrapper">
  <!-- Content Header (Page header) -->
  <section class="content-header">
    <h1>
      Non-Stock Items
      <small></small>
    </h1>
    <ol class="breadcrumb">
      <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
      <li class="active">Non-Stock Items</li>
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
              <?php echo ''.unserialize(base64_decode($_GET['msg'])) ; ?>
			  
					
           </div>
          <?php elseif(isset($_GET['failmsg'])): ?>
            <div class="alert alert-error alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo unserialize(base64_decode($_GET['failmsg'])); ?>
			  
			  
            </div>
          <?php endif; ?>

		  
			<?php if(in_array('createProduct', $UserPermission) ){ ?>

			<a href="AddProducts.php" class="btn btn-primary float-right"> Add Product</a>
			</br>
			</br>
			<?php } ?>

			<div class="box">
          <div class="box-header">
            <h3 class="box-title">Manage Non-Stock Items</h3>
			
			
          </div>
          <!-- /.box-header -->
          <div class="box-body">
		  <form method="post" name="myForm">

			<input  value='Download'  class="btn btn-primary pull-right"  data-toggle="modal" data-target="#textModal"/>
			</br>
			</br>
			
			
            <table id="manageTable" class="table table-bordered table-striped" data-page-length='25'>
              <thead>
              <tr>
                
                <th>Select</th>
                <th>Image</th>
                <th>Product</th>
                <th>Tags</th>
                <th>Category</th>
                <th>Color</th>
                <th>Dimension</th>
                <th>Description</th>
                <th>MRP</th>
                <th>DP</th>
				
				<?php if(in_array('updateProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){ ?>

                <th>Action</th>
				<?php } ?>
               
              </tr>
              </thead>
			  
			  <tbody>
			  <?php
					if(isset($_GET['cat_id'])):
				  $sql = mysqli_query($connect, "SELECT p.id, p.image, p.thumb, p.name, p.tags, p.color, p.dimension, p.description, c.name AS category , pp.mrp_price, pp.dp_price, pp.special_price FROM products p INNER JOIN categories c ON p.category_id = c.id 
				  left join pricelist pp on p.id = pp.p_prodId where p.category_id = '".$_GET['cat_id']."' AND p.catalogue = '0' ");
				  else:
				  $sql = mysqli_query($connect, "SELECT p.id, p.image, p.thumb, p.name, p.tags, p.color, p.dimension, p.description, c.name AS category , pp.mrp_price, pp.dp_price, pp.special_price FROM products p INNER JOIN categories c ON p.category_id = c.id 
				  left join pricelist pp on p.id = pp.p_prodId where p.catalogue = '0'  order by name asc, image desc ");
				  endif;

				  

				 while( $row=mysqli_fetch_array($sql) ) {  // preparing an array

			  ?>
			  
			  <tr role="row" class="odd">
				<td>
					<div class="form-check">
					<span class="counter" style=" display: none;">0</span>
					<input type="checkbox" class="form-check-input" name="check[]" value="<?php echo $row['id'] ?>">
					<label class="form-check-label" for="check"></label>
					</div>
				</td>
				<td>
					<img src="<?php echo $row["thumb"]; ?>" class="img-circle" width="50" height="50">
				</td>
				<td>
					<?php echo $row["name"]; ?>
				</td>
				<td>
					<ul class="cloud-tags">
					<?php $str = $row['tags'];
					$tags = explode(",",$str);
					foreach($tags as $key => $val){
						if($val !=''){
						echo '<li><a href="#">'.$val.'</a> </li>';
						}
						
						
					}
					?>
					</ul>
				</td>
				<td>
					<?php echo $row["category"]; ?>
				</td>
				<td>
					<?php echo $row["color"]; ?>
				</td>
				<td>
					<?php echo $row["dimension"]; ?>
				</td>
				<td>
					<?php echo $row["description"]; ?>
				</td>
				<td>
					<?php echo $row["mrp_price"]; ?>
				</td>
				<td>
					<?php echo $row["dp_price"]; ?>
				</td>

				<?php if(in_array('updateProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){ ?>
				<td>
					<a class="btn btn-default" href="EditProduct.php?id=<?php echo $row['id'] ?>"><i class="fa fa-pencil"></i></a> 
					<button type="button" class="btn btn-default" onclick="removeFunc(<?php echo $row['id'] ?>)" data-toggle="modal" data-target="#removeModal"><i class="fa fa-trash"></i></button>
				</td>
				<?php } ?>

				</tr>
			  <?php
				 }
				?>
				
			  </tbody>

            </table>
			
			<!-- Add Text brand modal -->
<div class="modal fade" tabindex="-1" role="dialog" id="textModal">
  <div class="modal-dialog" role="document">
    <div class="modal-content">
      <div class="modal-header">
        <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span aria-hidden="true">&times;</span></button>
        <h4 class="modal-title">Name Your Search</h4>
      </div>

        <div class="modal-body">
          <input type="text" name="content" id="inputText" class="form-control input-sm" placeholder="Name Your Search" >
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary" name="submit">Download</button>
<!--          <button type="submit" class="btn btn-primary" name="mrp">Download MRP</button>
          <button type="submit" class="btn btn-primary" name="dp">Download DP</button> -->
        </div>


    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

			
		</form>
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
        <h4 class="modal-title">Remove Product</h4>
      </div>

      <form role="form" action="#" method="post" id="removeForm">
        <div class="modal-body">
          <p>Do you really want to remove?</p>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-default" data-dismiss="modal">Close</button>
          <button type="submit" class="btn btn-primary">Save changes</button>
        </div>
      </form>


    </div><!-- /.modal-content -->
  </div><!-- /.modal-dialog -->
</div><!-- /.modal -->

<?php 
		include_once('templates/footer.php');

?>




<script type="text/javascript">

$(document).ready( function () {
	
	  $(':checkbox').on('change', function(e) {
    var row = $(this).closest('tr');
    var hmc = row.find(':checkbox:checked').length;
    var kluj = parseInt(hmc);
    row.find('span.counter').text(kluj);
    table.row(row).invalidate('dom');
  });   

	
  var table = $('#manageTable').DataTable({
  
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
       'targets'       : [2,5,6] 
    }
    ], 
	/* columnDefs: [ {
            orderable: false,
            className: 'select-checkbox',
            targets:   0
        } ],
        select: {
            style:    'os',
            selector: 'td:first-child'
        },
        order: [[ 1, 'asc' ]] */
		
  });
   
});


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
        data: { product_id:id, table: 'products'  },

        success: function(data) {
			
			console.log(data);

		//$("#manageTable").load(" #manageTable");
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


$.fn.dataTable.ext.order['dom-checkbox'] = function  ( settings, col )
{
    return this.api().column( col, {order:'index'} ).nodes().map( function ( td, i ) {
        return $('input', td).prop('checked') ? '1' : '0';
    } );
}
 

			$('#manageTable').on('search.dt', function() {
				var inputText = $('.dataTables_filter input').val();
				document.getElementById("inputText").value = inputText;
				console.log(inputText);
			});    


</script>
