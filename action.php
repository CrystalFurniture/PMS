<?php
	include_once('DB/conn.php');
	error_reporting(0);
		@session_start();
		$userName = $_SESSION['username'];


	function SanatizeInput($data){
			
			  $data = trim($data);
			  $data = addslashes($data);
						
			return $data;
		}


		
if($_POST["action"] == "add_dp")  
{
	$status="";
	$id = $_POST['product_id'];


		$result = mysqli_query($connect,"SELECT p.*, pl.* FROM products p INNER JOIN pricelist pl on pl.p_prodId = p.id WHERE p.id ='".$id."'");
		$row = mysqli_fetch_assoc($result);
		$name = SanatizeInput($row['name']);
		$description = SanatizeInput($row['description']);

		$color = SanatizeInput($row['color']);
		$price = SanatizeInput($row['dp_price']);
		$image = SanatizeInput($row['thumb']);
		$size = SanatizeInput($row['dimension']);

		$cartArray = array(
			$name=>array(
			'id'=>$id,
			'name'=>$name,
			'description'=>$description,
			'color'=>$color,
			'price'=>$price,
			'quantity'=>1,
			'image'=>$image,
			'size'=>$size
			)
		);

		if(empty($_SESSION["shopping_cartDp"])) {
			$_SESSION["shopping_cartDp"] = $cartArray;
			$status = "Product is added to your cart!";
		}
		else{
				$array_keys = array_keys($_SESSION["shopping_cartDp"]);
				if(in_array($name,$array_keys)) {
					$status = "Product is already added to your cart!";	
				} 
				else {
				$_SESSION["shopping_cartDp"] = array_merge($_SESSION["shopping_cartDp"],$cartArray);
				$status = "Product is added to your cart!";
				}

			}

				if($status != ''):
					echo '<div class="alert alert-warning alert-dismissible" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					  '.$status.'
					</div>';
				  
				  endif;
					
					
					
					//if(!empty($_SESSION["shopping_cartDp"])) {
					$cart_count = count(array_keys($_SESSION["shopping_cartDp"]));
				
					echo '<div class="cart_div">
					<a href="ViewOrderDp.php"><img src="assets/images/Excel.png" width="50"/> <span>'.$cart_count.'</span></a>
					</div>';
					

}

if($_POST["action"] == "add_mrp")  
{
	$status="";
		
		$id = $_POST['product_id'];


		$result = mysqli_query($connect,"SELECT p.*, pl.* FROM products p INNER JOIN pricelist pl on pl.p_prodId = p.id WHERE p.id ='".$id."'");
		$row = mysqli_fetch_assoc($result);
		$name = SanatizeInput($row['name']);
		$description = SanatizeInput($row['description']);

		$color = SanatizeInput($row['color']);
		$price = SanatizeInput($row['mrp_price']);
		$image = SanatizeInput($row['thumb']);
		$size = SanatizeInput($row['dimension']);

		$cartArray = array(
			$name=>array(
			'id'=>$id,
			'name'=>$name,
			'description'=>$description,
			'color'=>$color,
			'price'=>$price,
			'quantity'=>1,
			'image'=>$image,
			'size'=>$size
			)
		);

		if(empty($_SESSION["shopping_cartMRP"]))
			{
				$_SESSION["shopping_cartMRP"] = $cartArray;
				$status = "Product is added to your cart!";
			}
			else
			{
				$array_keys = array_keys($_SESSION["shopping_cartMRP"]);
				if(in_array($name,$array_keys)) {
					$status = "Product is already added to your cart!";	
				} 
				else
				{
					$_SESSION["shopping_cartMRP"] = array_merge($_SESSION["shopping_cartMRP"],$cartArray);
					$status = "Product is added to your cart!";
				}

			}
			
			 if($status != ''):
					echo '<div class="alert alert-warning alert-dismissible" role="alert">
					  <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
					  '.$status.'
					</div>';
				  
			endif; 
					$cart_count = count(array_keys($_SESSION["shopping_cartMRP"]));
					
					echo '<div class="cart_div">
					<a href="ViewOrderMRP.php"><img src="assets/images/Excel.png" width="50"/> <span>'.$cart_count.'</span></a>
					</div>';
					
		
}


// Quantity Change DP
		$order_table = '';  


	if($_POST["action"] == "quantityDP_change")  
      {  
		
           foreach($_SESSION["shopping_cartDp"] as $keys => $values)  
           {  
                if($_SESSION["shopping_cartDp"][$keys]['id'] == $_POST["id"])  
                {  
                     $_SESSION["shopping_cartDp"][$keys]['quantity'] = $_POST["quantity"];  
					 
					 $ProdTotal = $_SESSION["shopping_cartDp"][$keys]["quantity"] * $_SESSION["shopping_cartDp"][$keys]["price"];
					
					
                }  
           } 		

			      $order_table .= '  
           
           <table class="table table-bordered table-striped" data-page-length="200" id="example">  
                <tr>
					<th></th>
					<th>ITEM NAME</th>
					<th>SIZE</th>
					<th>QUANTITY</th>
					<th>UNIT PRICE</th>
					<th>ITEMS TOTAL</th>
					<th>REMOVE</th>
					</tr>	
           ';  
      if(!empty($_SESSION["shopping_cartDp"]))  
      {  
           $total = 0;  
           
		   
           foreach($_SESSION["shopping_cartDp"] as $keys => $values)  
           {  
                $order_table .= '  
                     <tr>  
                          <td><img src='. str_replace(" ", '%20', $values["image"]).' width="50" height="40" /></td>
                          <td>'.$values["name"].'</td> 
						  <td>'.$values["size"].'</td>	
						  

						  <td>
						 <div class="col-xs-6"> 

							<input type="hidden" name="name" value="'.$values["name"].'" />
							<input type="hidden" name="action"  value="change" />
							
							<input type="number" class="form-control col-md-4 quantityDP" name="quantity" pattern="[0-9]{3}" value="'.$values["quantity"].'" data-product_id="'.$values["id"].'" autocomplete="off"/>
							</div>
						  </td>
						  <td>&#8377 ' .$values["price"].'</td>
						  <td>&#8377 '.$values["price"]*$values["quantity"].'</td>
						  <td>
							<button name="delete" class="btn btn-danger btn-xs delete" id="'.$values["id"].'"> X </button>
						</td>  
					</tr>
						  
                          
                ';  
                $total = $total + ($values["quantity"] * $values["price"]);  
                
           }  
		   
           $order_table .= ' 
                <tr>
			<td colspan="6" align="right">
			<strong>TOTAL: &#8377; ' .$total.'</strong>
			</td>
			</tr>
           ';  
      }  
      $order_table .= '</table>';  
      $output = array(  
           'order_table'     =>     $order_table 
           
      );  
      echo json_encode($output);  

		   
   }  
	  
// Remove Product DP	  
	  if($_POST["action"] == "removeDP")  
      {  
           foreach($_SESSION["shopping_cartDp"] as $keys => $values)  
           {  
                if($values["id"] == $_POST["id"])  
                {  
                     unset($_SESSION["shopping_cartDp"][$keys]);  
                     //$message = '<label class="text-success">Product Removed</label>';  
                }  
           }  
		   
		   			      $order_table .= '  
           
           <table id="example" class="table table-bordered table-striped" data-page-length="200"> 
		   <thead>
                <tr>
					<th></th>
					<th>ITEM NAME</th>
					<th>SIZE</th>
					<th>QUANTITY</th>
					<th>UNIT PRICE</th>
					<th>ITEMS TOTAL</th>
					<th>REMOVE</th>
					</tr>
			</thead>
					
           ';  
      if(!empty($_SESSION["shopping_cartDp"]))  
      {  
           $total = 0;  
           
		   
           foreach($_SESSION["shopping_cartDp"] as $keys => $values)  
           {  
                $order_table .= '  
                     <tr>  
                          <td><img src='. $values["image"].' width="50" height="40" /></td>
                          <td>'.$values["name"].'</td> 
						  <td>'.$values["size"].'</td>	
						  <td>
							<input type="number" class="form-control col-md-4 quantityDP" name="quantity" pattern="[0-9]{3}" value="'.$values["quantity"].'" data-product_id="'.$values["id"].'" autocomplete="off"/>
						  </td>
						  <td>&#8377 ' .$values["price"].'</td>
						  <td>&#8377 '.$values["price"]*$values["quantity"].'</td>
						  <td>
							<button name="delete" class="btn btn-danger btn-xs delete" id="'.$values["id"].'"> X </button>
						</td>  
					</tr>
						  
                          
                ';  
                $total = $total + ($values["quantity"] * $values["price"]);  
                
           }  
		   
           $order_table .= ' 
                <tr>
			<td colspan="6" align="right">
			<strong>TOTAL: &#8377; ' .$total.'</strong>
			</td>
			</tr>
           ';  
      }  
      $order_table .= '</table>';  
      $output = array(  
           'order_table'     =>     $order_table 
           
      );  
      echo json_encode($output);  


 }  
 
 
 



// Quantity Change MRP
		$order_tableMRP = '';  


	if($_POST["action"] == "quantityMRP_change")  
      {  
		
           foreach($_SESSION["shopping_cartMRP"] as $keys => $values)  
           {  
                if($_SESSION["shopping_cartMRP"][$keys]['id'] == $_POST["id"])  
                {  
                     $_SESSION["shopping_cartMRP"][$keys]['quantity'] = $_POST["quantity"];  
					 
					 $ProdTotal = $_SESSION["shopping_cartMRP"][$keys]["quantity"] * $_SESSION["shopping_cartMRP"][$keys]["price"];
					
					
                }  
           } 		

			      $order_tableMRP .= '  
           
           <table class="table table-bordered table-striped" data-page-length="200" id="example">  
                <tr>
					<th></th>
					<th>ITEM NAME</th>
					<th>SIZE</th>
					<th>QUANTITY</th>
					<th>UNIT PRICE</th>
					<th>ITEMS TOTAL</th>
					<th>REMOVE</th>
					</tr>	
           ';  
      if(!empty($_SESSION["shopping_cartMRP"]))  
      {  
           $total = 0;  
           
		   
           foreach($_SESSION["shopping_cartMRP"] as $keys => $values)  
           {  
                $order_tableMRP .= '  
                     <tr>  
                          <td><img src='. $values["image"].' width="50" height="40" /></td>
                          <td>'.$values["name"].'</td> 
						  <td>'.$values["size"].'</td>	
						  <td>
							<input type="number" class="form-control col-md-4 quantityMRP" name="quantity" pattern="[0-9]{3}" value="'.$values["quantity"].'" data-product_id="'.$values["id"].'" autocomplete="off"/>
						  </td>
						  <td>&#8377 ' .$values["price"].'</td>
						  <td>&#8377 '.$values["price"]*$values["quantity"].'</td>
						  <td>
							<button name="delete" class="btn btn-danger btn-xs deleteMRP" id="'.$values["id"].'"> X </button>
						</td>  
					</tr>
						  
                          
                ';  
                $total = $total + ($values["quantity"] * $values["price"]);  
                
           }  
		   
           $order_tableMRP .= ' 
                <tr>
			<td colspan="6" align="right">
			<strong>TOTAL: &#8377; ' .$total.'</strong>
			</td>
			</tr>
           ';  
      }  
      $order_tableMRP .= '</table>';  
      $output = array(  
           'order_tableMRP'     =>     $order_tableMRP 
           
      );  
      echo json_encode($output);  

		   
   }  
	  
// Remove Product MRP	  
	  if($_POST["action"] == "removeMRP")  
      {  
           foreach($_SESSION["shopping_cartMRP"] as $keys => $values)  
           {  
                if($values["id"] == $_POST["id"])  
                {  
                     unset($_SESSION["shopping_cartMRP"][$keys]);  
                     //$message = '<label class="text-success">Product Removed</label>';  
                }  
           }  
		   
		   			      $order_tableMRP .= '  
           
           <table id="example" class="table table-bordered table-striped" data-page-length="200"> 
		   <thead>
                <tr>
					<th></th>
					<th>ITEM NAME</th>
					<th>SIZE</th>
					<th>QUANTITY</th>
					<th>UNIT PRICE</th>
					<th>ITEMS TOTAL</th>
					<th>REMOVE</th>
					</tr>
			</thead>
					
           ';  
      if(!empty($_SESSION["shopping_cartMRP"]))  
      {  
           $total = 0;  
           
		   
           foreach($_SESSION["shopping_cartMRP"] as $keys => $values)  
           {  
                $order_tableMRP .= '  
                     <tr>  
                          <td><img src='. $values["image"].' width="50" height="40" /></td>
                          <td>'.$values["name"].'</td> 
						  <td>'.$values["size"].'</td>	
						  <td>
							<input type="number" class="form-control col-md-4 quantityMRP" name="quantity" pattern="[0-9]{3}" value="'.$values["quantity"].'" data-product_id="'.$values["id"].'" autocomplete="off"/>
						  </td>
						  <td>&#8377 ' .$values["price"].'</td>
						  <td>&#8377 '.$values["price"]*$values["quantity"].'</td>
						  <td>
							<button name="delete" class="btn btn-danger btn-xs deleteMRP" id="'.$values["id"].'"> X </button>
						</td>  
					</tr>
						  
                          
                ';  
                $total = $total + ($values["quantity"] * $values["price"]);  
                
           }  
		   
           $order_tableMRP .= ' 
                <tr>
			<td colspan="6" align="right">
			<strong>TOTAL: &#8377; ' .$total.'</strong>
			</td>
			</tr>
           ';  
      }  
      $order_tableMRP .= '</table>';  
      $output = array(  
           'order_tableMRP'     =>     $order_tableMRP 
           
      );  
      echo json_encode($output);  


 }  
 
 
 


?>