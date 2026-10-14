<?php
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();
		$userName = $_SESSION['username'];

	// Saving happens only on the cart form POST, which always redirects (PRG):
	// refreshing the result page shows the saved quotation again and never re-saves.
	if($_SERVER['REQUEST_METHOD'] === 'POST'){
		qt_handle_save($connect, 'DP');
	}

	list($row, $error) = qt_fetch_owned_quotation($connect, isset($_GET['id']) ? $_GET['id'] : '', 'DP');
	$shopping_cartDp = $row ? qt_decode_items($row['q_items']) : null;
	if($error !== null || $shopping_cartDp === null){
		qt_flash_set($error !== null ? $error : 'The saved lines of this quotation cannot be read.');
		qt_redirect('ViewQuotation.php');
	}

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
?>
<style>
th{
	width:14.28% !important;
}

</style>
  <div class="content-wrapper">

    <section class="content-header">
      <h1>
        Print
        <small>DP Invoice</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Invoice</li>
      </ol>
    </section>


			  	<?php 	/* $myresult = mysqli_query($connect,"select * from tbl_party tp INNER JOIN tbl_complete tc
												ON tp.party_id = tc.comp_partyId INNER JOIN tbl_employee te ON tc.comp_empID = te.emp_id LEFT JOIN tbl_partner tpar on tp.party_id = tpar.p_id where tp.party_id = ". $id);
		
						$row = mysqli_fetch_assoc($myresult);

*/


				?>

  <div class="container">
<div class="col-md-11" >

	<span>&nbsp; </span>
		<span>&nbsp; </span>
		
			<!--<button class="btn btn-success btn-large right" onclick="printDiv();">Print it</button>-->
			<input type="button"  class="btn btn-success btn-large right" id="btnExport" value=" Export to Excel " style="Margin-bottom: 10px;"/>
			<a class="btn btn-primary" id="btnPdf" href="QuotationPdf.php?id=<?= (int) $row['q_id'] ?>&amp;type=DP" style="Margin-bottom: 10px;">Download PDF</a>
			<span>&nbsp; </span>
			<div id="excel">
<table id="example" class="table table-bordered" >

		
					<tr>
					
					<th colspan=2 style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;"><img src="http://192.168.1.111/pms/core/images/logo_1.png" class="thumbnail" style="width:150; padding:20px"></th>
					<th colspan=5 style="border: 1px solid black !important; text-align: center;"><span style="font-size:16px"> CRYSTAL FURNITECH PRIVATE LIMITED </span></br>
						 <span style="font-weight:600">Office : Umiya Chambers, Darodkar Square, Central Avenue, Nagpur-440032.<br>
						 Phone: 0712-2766405, 2770867, Fax:-6611795 M :- 9922919052<br>
						 E-mail: rakesh@crystalfurniture.in  Web  :  www.crystalfurnitech.com <br><br>
						 <p>&nbsp;</p></span></th>
						 
					<th colspan=2 style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;"><img src="http://192.168.1.111/pms/core/images/3C.png" class="thumbnail" style="width:150px; padding:20px"></th>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;"colspan=3 rowspan=5>Client Name & Address :- <?php echo qt_e($row['q_name']); ?></td>
						<td style="border: 1px solid black !important;" width="140" colspan=2>QUOTATION NO.</td>
						<td style="border: 1px solid black !important;" colspan=2><?= qt_e($row['q_num']); ?></td>
						<td style="border: 1px solid black !important;">REV.NO</td>
						<td style="border: 1px solid black !important;"> <?= ($row['q_revDate'] != '') ? 'Cry/21-22/REV-'.(int)$row['q_revNum'] : '' ?></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"colspan=2>DATE</td>
						<td style="border: 1px solid black !important;" colspan=2><?= qt_e($row['q_date']) ?></td>
						<td style="border: 1px solid black !important;">DATE</td>
						<td style="border: 1px solid black !important;"><?= ($row['q_revDate'] != '') ? qt_e($row['q_revDate']) : '' ?></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"colspan=2>GST NO </td>
						<td style="border: 1px solid black !important;"colspan=2>27AAICC6954C1ZL</td>
						<td style="border: 1px solid black !important;">HSN Code</td>
						<td style="border: 1px solid black !important;"></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"colspan=2>Representative :-</td>
						<td style="border: 1px solid black !important;"colspan=2>Name</td>
						<td style="border: 1px solid black !important;"colspan=2>E-Mail</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"colspan=2></td>
						<td style="border: 1px solid black !important;"colspan=2><?php echo qt_e($_SESSION['fullName']); ?> (<?php echo qt_e($_SESSION['phone']); ?>)</td>
						<td style="border: 1px solid black !important;"colspan=2><?php echo qt_e($_SESSION['email']); ?></td>
					</tr>
					<tr>
						<td colspan=8></td>
					</tr>
					<tr style="text-align: center;">
						<td style="border: 1px solid black !important;">Sr.No.</td>
						<td style="border: 1px solid black !important;"colspan=2>Particulars</td>
						<td width="20" style="border: 1px solid black !important;" colspan=3>PRODUCT MODEL IMAGE</td>
						<!--<td style="border: 1px solid black !important;">Unit</td>-->
						<td style="border: 1px solid black !important;">Qty. </td>
						<td style="border: 1px solid black !important;">Rate (Rs.)</td>
						<td style="border: 1px solid black !important;">Amount (Rs.)</td>
					</tr>

					<?php		
							$i = 1;
						foreach ($shopping_cartDp as $product){
							
						?>
					
					<tr>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan=4>
						<?php echo $i++; ?></td>
						<td style="line-height: 2.5em; border: 1px solid black !important;"colspan=2><?php echo qt_e($product["name"]); ?></td>
						<td style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;" rowspan=4; colspan=3>
					
						<?php if($product["image"]!='')
						{?>
							<p>&nbsp;</p>
							<img src='http://192.168.1.111/pms/core/<?= qt_e($product["image"]);?>'  width="130" height="75" hspace="46" vspace="20">
							<p>&nbsp;</p>
						<?php }
						else{
							echo "";
						}
						?>
						</td>
						<!--<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan=3 >Nos.</td> -->
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan=4 ><?php echo qt_e($product["quantity"]) . " Nos."; ?></td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan=4 ><?php echo qt_e($product["price"]); ?></td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan=4 ><?php echo qt_e($product["price"]*$product["quantity"]); ?></td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">12</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;"colspan=2><?php echo qt_safe_html($product["description"]); ?></td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">123</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;"colspan=2>Size :- <?php echo qt_e($product["size"]); ?></td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">123</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;"colspan=2>Color :- <?php echo qt_e($product["color"]); ?></td>
					</tr>
					<!--<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"colspan=2>HSN Code : 94035090</td>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"></td>
					</tr>-->
					
					<!-- Extra Line -->
					
						<!--<tr>
						<td colspan=8></td>
					</tr> -->
				<?php
					$total_price += ($product["price"]*$product["quantity"]);
					
					$total_qty += $product["quantity"];
					}
				?>
				<!--- 1st Set Complete --->
					<tr>
						<td  colspan=8></td>
					</tr>

					<tr>
						<td colspan=8></td>
					</tr>
				<tr>
						<td style="border: 1px solid black !important;"colspan=5 style="text-align: center;"> </td>
						
						<td colspan=1 style="background-color: yellow; border: 1px solid black !important;">TOTAL Qty  </td>
						<td colspan=1 style="background-color: yellow; border: 1px solid black !important;"> <?php echo $total_qty  ; ?></td>
						
						<td colspan=1 style="background-color: yellow; border: 1px solid black !important;">TOTAL AMOUNT</td>
						<td style="background-color: yellow; border: 1px solid black !important;"><?php echo number_format($total_price, 2)  ; ?></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"colspan=5 style="text-align: center;">Bank Details ( applicable in case of direct deposite ) </td>

						
					<?php if($row['q_discount'] != ''){
						?>
						<td style="border: 1px solid black !important;" colspan=2>Discount In %</td>
						<td style="border: 1px solid black !important;"><?= qt_e($row['q_discount']). " %"; ?></td>
						<td style="border: 1px solid black !important;"><?php $discount = ((float)$row['q_discount'] * ($total_price)) /100; 
							echo  number_format($discount, 2);?></td>
					</tr>
					
					<tr>
						<td style="border: 1px solid black !important;"colspan=2>Bank : Axis Bank Ltd. </td>
						<td style="border: 1px solid black !important;"colspan=3>Branch : Lakkadganj, Nagpur</td>
						
						<td style="background-color: yellow; border: 1px solid black !important;"colspan=3>Basic Amount</td>
						<td style="background-color: yellow; border: 1px solid black !important;">
						<?php $basic = ($total_price - $discount); 
							echo  number_format($basic, 2);?></td>
					</tr>
					
					<tr>
						<td style="border: 1px solid black !important;"colspan=2>A/C No. : 921030022445688</td>
						<td style="border: 1px solid black !important;"colspan=3>IFSC Code : UTIB0000330</td>
						
						<td style="border: 1px solid black !important;"colspan=2>GST</td>
						<td style="border: 1px solid black !important;">18%</td>
						<td style="border: 1px solid black !important;"><?php  $GST = (18 / 100) * $basic;
								echo number_format($GST, 2); ?></td>
					</tr>
	
					
					<tr>
						<td style="border: 1px solid;border-right: 0;" colspan=2></td>
						<td style="border-bottom: 1px solid black;" colspan=3></td>
						
						<td style="background-color: yellow; border: 1px solid black !important;" colspan=3>FINAL AMOUNT</td>
						<td style="background-color: yellow; border: 1px solid black !important;">
						<?php echo number_format(($GST + $total_price) - $discount, 2); ?></td>
					</tr>
	
						
					<?php 
					}else{
					?>
					<td style="border: 1px solid black !important;"colspan=2>GST</td>
						<td style="border: 1px solid black !important;">18%</td>
						<td style="border: 1px solid black !important;"><?php  $GST = (18 / 100) * $total_price;
								echo number_format($GST, 2); ?></td>
					</tr>

										
					<tr>
						<td style="border: 1px solid black !important;"colspan=2>Bank : Axis Bank Ltd. </td>
						<td style="border: 1px solid black !important;"colspan=3>Branch : Lakkadganj, Nagpur</td>
						
						<td style="background-color: yellow; border: 1px solid black !important;"colspan=3>Basic Amount</td>
						<td style="background-color: yellow; border: 1px solid black !important;">
						<?php $basic = ($total_price - $discount); 
							echo  number_format($basic, 2);?></td>
					</tr>
					

					<tr>
						<td style="border: 1px solid black !important;"colspan=2>A/C No. : 921030022445688</td>
						<td style="border: 1px solid black !important;"colspan=3>IFSC Code : UTBI0000330</td>
						<td style="background-color: yellow; border: 1px solid black !important;" colspan=3>FINAL AMOUNT( INCLUDING 18% GST )</td>
						<td style="background-color: yellow; border: 1px solid black !important;"><?php echo number_format($GST + $total_price, 2); ?></td>
					</tr>
					<?php } ?>
					
					<tr>
						<td colspan=9></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;text-align: center;" colspan=9 >Terms and conditions as applicable to this Quotation</td>
						
					</tr>

					<tr>
						<td colspan=9></td>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;">1</td>
						<td style="border: 1px solid black !important;" colspan=8>Estimated delivery time from the date of confirmation of order, PO & Advance and Technical appoval ( Color & Etc. ) is 4- 6 Weeks.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">2</td>
						<td style="border: 1px solid black !important;" colspan=8>Once the order is proceed, changes in specification, color etc. will not be possible.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">3</td>
						<td style="border: 1px solid black !important;"colspan=8>Any extra work not mentioned in qoutation will be charged extra.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">4</td>
						<td style="border: 1px solid black !important;"colspan=8>In case of any specific exemptions from taxes, duties, the buyer shall provide the necessary documents.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">5</td>
						<td style="border: 1px solid black !important;"colspan=8>Payment Condition :- 50% advance at the time of order confirmation and remaining 50% before delivery.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">6</td>
						<td style="border: 1px solid black !important;"colspan=8>Transportation & Material Handling ( Unloading ) Charges Extra.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">7</td>
						<td style="border: 1px solid black !important;"colspan=8>Installation cost Extra. ( Lodging & Boarding Provided By You ).</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">8</td>
						<td style="border: 1px solid black !important;"colspan=8>Rates are applicable only on crystal's standard colours and shades .</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">9</td>
						<td style="border: 1px solid black !important;"colspan=8>Above Quotation is valid for 4 weeks.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">10</td>
						<td style="border: 1px solid black !important;"colspan=8>3 years warranty for any manufacturing defect, *Conditions apply.</td>
					</tr>

					<tr>
						<td colspan=8></td>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"colspan=8>For,</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"colspan=8>CRYSTAL FURNITECH PRIVATE LIMITED</td>
					</tr>

					<tr>
						<td colspan=9></td>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"colspan=8>Ajit Bhartia</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;"colspan=8>(CEO)</td>
					</tr>
</table>
					
	</div>			
				
		</div>
</div>


    <script src="js/rating/jquery.barrating.js"></script>
    <script src="js/rating/examples.js"></script>



	<script type="text/javascript">
		$( document ).ready(function() {
		$('#example').DataTable({

				 "dom": 'lBfrtip',
				 "buttons": [
					{
						extend: 'collection',
						text: 'Export',
						buttons: [
							'copy',
							'excel',
							'csv',
							'pdf',
							'print'
						]
					}
				]
				});
		});
	</script>
	
		<script type="text/javascript">
	function printDiv() {
    var divToPrint = document.getElementById('example');
    var htmlToPrint = ''+
		
		'<link rel="stylesheet" href="https://stackpath.bootstrapcdn.com/bootstrap/4.3.1/css/bootstrap.min.css" />' +
        '<style type="text/css">' +
        'table th, table td {' +
        'border:0.3px solid #000;' +
        'padding;0.5em;' +
		'border-collapse: collapse;'+
        '}' +
		'h3{text-align: center;}'+
        '</style>'
		;
    htmlToPrint += divToPrint.outerHTML;
    newWin = window.open("");
    newWin.document.write(htmlToPrint);
    newWin.print();
    newWin.close();
}
	</script>

	
 
  		<script type="text/javascript">
		

			function status(x, y){
			var checkstr =  confirm('Are you sure you want to Change this?');
			if(checkstr == true){
			 window.location.href = 'update_status.php?id='+x+'&status='+y;
			
			}else{
			return false;
			}
		  }
		</script>

<script src="https://cdnjs.cloudflare.com/ajax/libs/jquery/1.8.3/jquery.min.js" integrity="sha256-YcbK69I5IXQftf/mYD8WY0/KmEDCv1asggHpJk1trM8=" crossorigin="anonymous"></script>

  		<script type="text/javascript">
		
		$("#btnExport").click(function (e) {
		var dt = new Date();
        var day = dt.getDate();
        var month = dt.getMonth() + 1;
        var year = dt.getFullYear();
        var hour = dt.getHours();
        var mins = dt.getMinutes();
        var postfix = day + "." + month + "." + year + "_" + hour + "." + mins;
        //creating a temporary HTML link element (they support setting file names)
        var a = document.createElement('a');
        //getting data from our div that contains the HTML table
        var data_type = 'data:application/vnd.ms-excel';
        var table_div = document.getElementById('excel');
        var table_html = table_div.outerHTML.replace(/ /g, '%20');
        a.href = data_type + ', ' + table_html;
        //setting the file name
        a.download = 'exported_table_' + postfix + '.xls';
        //triggering the function
        a.click();
        //just in case, prevent default behaviour
			
		//window.open('data:application/vnd.ms-excel,' +  encodeURIComponent($('#excel').html()));
		   e.preventDefault();
		});

		
		
		</script>

  
</div>
<?php
		include_once('templates/footer.php');

?>
