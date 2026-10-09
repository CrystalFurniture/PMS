<!DOCTYPE html> 
<html> 

<head> 
	<title> 
		How to convert an HTML element 
		or document into image ? 
	</title> 
	
	<script src= 
"https://ajax.googleapis.com/ajax/libs/jquery/1.11.2/jquery.min.js"> 
	</script> 
	
	<script src= 
"https://files.codepedia.info/files/uploads/iScripts/html2canvas.js"> 
	</script> 
</head> 

<body> 
	<center> 
	<h2 style="color:green"> 
		GeeksForGeeks 
	</h2> 
	
	<h2 style="color:purple"> 
		Convert div to image 
	</h2> 

	<input id="btn-Preview-Image" type="button"
				value="Preview" /> 
		
	<a id="btn-Convert-Html2Image" href="#"> 
		Download 
	</a> 

	<br/> 
	
	
	
	<script> 
		$(document).ready(function() { 
		
			// Global variable 
			var element = $("#excel"); 
		
			// Global variable 
			var getCanvas; 

			$("#btn-Preview-Image").on('click', function() { 
				html2canvas(element, { 
					onrendered: function(canvas) { 
						$("#previewImage").append(canvas); 
						getCanvas = canvas; 
					} 
				}); 
			}); 

			$("#btn-Convert-Html2Image").on('click', function() { 
				var imgageData = 
					getCanvas.toDataURL("image/png"); 
			
				// Now browser starts downloading 
				// it instead of just showing it 
				var newData = imgageData.replace( 
				/^data:image\/png/, "data:application/octet-stream"); 
			
				$("#btn-Convert-Html2Image").attr("download", "GeeksForGeeks.png").attr("href", newData); 
			}); 
		}); 
	</script> 
	</center> 
	
	<!DOCTYPE html>
<html>
  <head>
    <meta charset="utf-8" />
    <meta http-equiv="X-UA-Compatible" content="IE=edge" />
    <title>HTML to PDF Eample</title>
    <meta name="viewport" content="width=device-width, initial-scale=1" />
    <script src="https://cdnjs.cloudflare.com/ajax/libs/html2pdf.js/0.9.2/html2pdf.bundle.min.js"></script>

    <script>
      function generatePDF() {
        // Choose the element that our invoice is rendered in.
        const element = document.getElementById("excel");
        // Choose the element and save the PDF for our user.
        html2pdf(element,'P', 'A4', 'en', true, 'UTF-8', 0, {html2canvas:  { scale: 10 }})
          
      }
    </script>
	
  </head>
  <body>
    <button onclick="generatePDF()">Download as PDF</button>
	
			<div id="excel">
<table id="example" CELLSPACING='0' cellpadding='2' class="table table-bordered">

		
					<tbody><tr>
					
					<th colspan="2" style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;"><img src="images/logo_1.png" class="thumbnail" style="width:150px; padding:20px"></th>
					<th colspan="4" style="border: 1px solid black !important; text-align: center;"><span style="font-size:16px"> CRYSTAL FURNITURE INDUSTRIES </span><br>
						 <span style="font-weight:600">Office : Umiya Chambers, Darodkar Square, Central Avenue, Nagpur-440032.<br>
						 Phone: 0712-2766405, 2770867, Fax:-6611795 M :- 9922919052<br>
						 E-mail: rakesh@crystalfurniture.in  Web  :  www.crystalfurnitech.com <br><br>
						 <p>&nbsp;</p></span></th>
						 
					<th colspan="2" style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;"><img src="images/3C.png" class="thumbnail" style="width:150px; padding:20px"></th>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;" colspan="3" rowspan="5">Client Name &amp; Address :- Shivek Sharma</td>
						<td style="border: 1px solid black !important;" colspan="2">QUOTATION NO.</td>
						<td style="border: 1px solid black !important;">Cry/19-20/Q-190</td>
						<td style="border: 1px solid black !important;">REV.NO</td>
						<td style="border: 1px solid black !important;"></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;" colspan="2">DATE</td>
						<td style="border: 1px solid black !important;">15.09.2020</td>
						<td style="border: 1px solid black !important;">DATE</td>
						<td style="border: 1px solid black !important;"></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;" colspan="2">GST NO </td>
						<td style="border: 1px solid black !important;">27ABBPB0801G1ZI</td>
						<td style="border: 1px solid black !important;">HSN Code</td>
						<td style="border: 1px solid black !important;"></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;" colspan="2">Representative :-</td>
						<td style="border: 1px solid black !important;">Name</td>
						<td style="border: 1px solid black !important;" colspan="2">E-Mail</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;" colspan="2"></td>
						<td style="border: 1px solid black !important;">Mr. Shivek Sharma (8669089707)</td>
						<td style="border: 1px solid black !important;" colspan="2">admin@admin.com</td>
					</tr>
					<tr>
						<td colspan="8"></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">Sr.No.</td>
						<td style="border: 1px solid black !important;" colspan="2">Particulars</td>
						<td style="border: 1px solid black !important;">PRODUCT MODEL IMAGE</td>
						<td style="border: 1px solid black !important;">Unit</td>
						<td style="border: 1px solid black !important;">Qty. </td>
						<td style="border: 1px solid black !important;">Rate (Rs.)</td>
						<td style="border: 1px solid black !important;">Amount (Rs.)</td>
					</tr>

										
					<tr>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">
						1</td>
						<td style="line-height: 2.5em; border: 1px solid black !important;" colspan="2">CS-103  (103.6 NHDS)</td>
						<td style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;" rowspan="3">
					
													<p>&nbsp;</p>
							<img src="Category/thumb/CS-103 (103.6 NHDS)_1569396707_thumb.jpg" width="100" height="100" style="margin: 20%;">
							<p>&nbsp;</p>
												</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">Nos.</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">5</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">24076</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">120380</td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">12</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;" colspan="2">Clay Bed with New Hydraulic</td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">123</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;" colspan="2">Size :- 6 x 6 ½</td>
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
									
					<tr>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">
						2</td>
						<td style="line-height: 2.5em; border: 1px solid black !important;" colspan="2">CS-102  (102.6 NHDS)</td>
						<td style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;" rowspan="3">
					
													<p>&nbsp;</p>
							<img src="Category/thumb/CS-102(102.6NHDS)_thumb.jpg" width="100" height="100" style="margin: 20%;">
							<p>&nbsp;</p>
												</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">Nos.</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">6</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">18623</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">111738</td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">12</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;" colspan="2">Sterling Oak Bed with New Hydraulic</td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">123</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;" colspan="2">Size :- 6 x 6 ½</td>
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
									
					<tr>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">
						3</td>
						<td style="line-height: 2.5em; border: 1px solid black !important;" colspan="2">CS-104  (104.6 NHDS)</td>
						<td style="text-align: -webkit-center;width: 200px;border: 1px solid black !important;" rowspan="3">
					
													<p>&nbsp;</p>
							<img src="Category/thumb/CS-104 (104.6 NHDS)_1569396875_thumb.jpg" width="100" height="100" style="margin: 20%;">
							<p>&nbsp;</p>
												</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">Nos.</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">4</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">21720</td>
						<td style="text-align: center;border: 1px solid black !important;vertical-align: middle;" rowspan="3">86880</td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">12</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;" colspan="2">Austin Bed with New Hydraulic</td>
					</tr>
					<tr>
						<!--<td style="border: 1px solid black !important;">123</td>-->
						<td style="line-height: 2.5em;border: 1px solid black !important;" colspan="2">Size :- 6 x 6½</td>
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
								<!--- 1st Set Complete --->
					<tr>
						<td colspan="8"></td>
					</tr>

					<tr>
						<td colspan="8"></td>
					</tr>
				<tr>
						<td style="border: 1px solid black !important;" colspan="5"> </td>
						
						<td colspan="1" style="background-color: yellow; border: 1px solid black !important;">TOTAL Qty : 15</td>
						
						<td colspan="1" style="background-color: yellow; border: 1px solid black !important;">TOTAL AMOUNT</td>
						<td style="background-color: yellow; border: 1px solid black !important;">318,998.00</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;" colspan="5">Bank Details ( applicable in case of direct deposite ) </td>

						
											<td style="border: 1px solid black !important;">Discount In %</td>
						<td style="border: 1px solid black !important;">15 %</td>
						<td style="border: 1px solid black !important;">47,849.70</td>
					</tr>
					
						<tr>
						<td style="border: 1px solid black !important;" colspan="2">Bank : INDUSIND BANK </td>
						<td style="border: 1px solid black !important;" colspan="3">Branch : C. A. ROAD, Nagpur Type - CC</td>
						
						<td style="background-color: yellow; border: 1px solid black !important;" colspan="2">Basic Amount</td>
						<td style="background-color: yellow; border: 1px solid black !important;">
						271,148.30</td>
					</tr>
					
					<tr>
					<td style="border: 1px solid black !important;" colspan="2">A/C No. : 650011346145</td>
						<td style="border: 1px solid black !important;" colspan="3">IFSC Code : INDB0000274</td>
						
						<td style="border: 1px solid black !important;">GST</td>
						<td style="border: 1px solid black !important;">18%</td>
						<td style="border: 1px solid black !important;">48,806.69</td>
					</tr>
	
					
					<tr>
						<td style="border: 1px solid;border-right: 0;" colspan="2"></td>
						<td style="border-bottom: 1px solid black;" colspan="3"></td>
						
						<td style="background-color: yellow; border: 1px solid black !important;" colspan="2">FINAL AMOUNT</td>
						<td style="background-color: yellow; border: 1px solid black !important;">
						319,954.99</td>
					</tr>
	
						
										
					<tr>
						<td colspan="8"></td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;text-align: center;" colspan="8">Terms and conditions as applicable to this Quotation</td>
						
					</tr>

					<tr>
						<td colspan="8"></td>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;">1</td>
						<td style="border: 1px solid black !important;" colspan="7">Estimated delivery time from the date of confirmation of order, PO &amp; Advance and Technical appoval ( Color &amp; Etc. ) is 4- 6 Weeks.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">2</td>
						<td style="border: 1px solid black !important;" colspan="7">Once the order is proceed, changes in specification, color etc. will not be possible.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">3</td>
						<td style="border: 1px solid black !important;" colspan="7">Any extra work not mentioned in qoutation will be charged extra.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">4</td>
						<td style="border: 1px solid black !important;" colspan="7">In case of any specific exemptions from taxes, duties, the buyer shall provide the necessary documents.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">5</td>
						<td style="border: 1px solid black !important;" colspan="7">Payment Condition :- 50% advance at the time of order confirmation and remaining 50% before delivery.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">6</td>
						<td style="border: 1px solid black !important;" colspan="7">Transportation &amp; Material Handling ( Unloading ) Charges Extra.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">7</td>
						<td style="border: 1px solid black !important;" colspan="7">Installation cost Extra. ( Lodging &amp; Boarding Provided By You ).</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">8</td>
						<td style="border: 1px solid black !important;" colspan="7">Rates are applicable only on crystal's standard colours and shades .</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">9</td>
						<td style="border: 1px solid black !important;" colspan="7">Above Quotation is valid for 4 weeks.</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;">10</td>
						<td style="border: 1px solid black !important;" colspan="7">3 years warranty for any manufacturing defect, *Conditions apply.</td>
					</tr>

					<tr>
						<td colspan="8"></td>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;" colspan="7">For,</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;" colspan="7">CRYSTAL FURNITURE INDUSTRIES</td>
					</tr>

					<tr>
						<td colspan="8"></td>
					</tr>

					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;" colspan="7">Ajit Bhartia</td>
					</tr>
					<tr>
						<td style="border: 1px solid black !important;"></td>
						<td style="border: 1px solid black !important;" colspan="7">(CEO)</td>
					</tr>
</tbody></table>
					
	</div>			
				
  </body>
</html>
	
</body> 

</html>					 
