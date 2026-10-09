<?php
include_once 'DB/conn.php';
include_once('templates/permission.php');
// storing  request (ie, get/post) global array to a variable  
$requestData= $_REQUEST;

if(isset($_POST['cat_id'])):
$columns = array( 
// datatable column index  => database column name
	
	0 => 'image',
    1 => 'name',
    2 => 'category',
    3 => 'color',
    4 => 'dimension',
    5 => 'description',
);

// getting total number records without any search
$sql = "SELECT p.id, p.image, p.name, p.color, p.dimension, p.description, c.name AS category FROM products p INNER JOIN categories c ON p.category_id = c.id where p.category_id = '".$_POST['cat_id']."'";

$query=mysqli_query($connect, $sql);
$totalData = mysqli_num_rows($query);
$totalFiltered = $totalData;  // when there is no search parameter then total number rows = total number filtered rows.


$sql = "SELECT p.id, p.image, p.name, p.color, p.dimension, p.description, c.name AS category FROM products p INNER JOIN categories c ON p.category_id = c.id where p.category_id = '".$_POST['cat_id']."'";

if( !empty($requestData['search']['value']) ) {   // if there is a search parameter, $requestData['search']['value'] contains search parameter
	$sql.=" AND ( p.name LIKE '%".$requestData['search']['value']."%' ";    
	$sql.=" OR p.color LIKE '%".$requestData['search']['value']."%'  OR p.description LIKE '%".$requestData['search']['value']."%' ";

	$sql.=" OR p.dimension LIKE '%".$requestData['search']['value']."%' )";
}
$query=mysqli_query($connect, $sql);
$totalFiltered = mysqli_num_rows($query);

 // when there is a search parameter then we have to modify total number filtered rows as per search result. 

 $sql.=" ORDER BY ". $columns[$requestData['order'][0]['column']]."   ".$requestData['order'][0]['dir']."  LIMIT ".$requestData['start']." ,".$requestData['length']."   ";

 /* $requestData['order'][0]['column'] contains colmun index, $requestData['order'][0]['dir'] contains order such as asc/desc  */	

 $query=mysqli_query($connect, $sql);


 $data = array();

 $i = 1;

 while( $row=mysqli_fetch_array($query) ) {  // preparing an array
	$dataArray=array(); 
	
	$img = '<img src="'.$row["image"].'" class="img-circle" width="50" height="50" />';
	
	$check = '<div class="form-check">
				<input type="checkbox" class="form-check-input" name="check">
				<label class="form-check-label" for="check"></label>
				</div>';

	//Update and Delete
	if(in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){
		$action = "";
	}

	
	//Update
	if(in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) ){
		$action .= '<a class="btn btn-default" href="EditProduct.php?id='.$row["id"].'" ><i class="fa fa-pencil"></i></a> ';
	}

	
	// Delete
	if(in_array('deleteProduct', $UserPermission)){
		$action .= '<button type="button" class="btn btn-default" onclick="removeFunc('.$row["id"].')" data-toggle="modal" data-target="#removeModal"><i class="fa fa-trash"></i></button>';
	}


    //$dataArray[] = $i++;
    $dataArray[] = $check;
    $dataArray[] = $img;
    $dataArray[] = $row["name"];
    $dataArray[] = $row["category"];
    $dataArray[] = $row["color"];
    $dataArray[] = $row["dimension"];
    $dataArray[] = $row["description"];
    $dataArray[] = $action;

	$data[] = $dataArray;

	}




	$json_data = array(
			"draw"            => intval( $requestData['draw'] ),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
			"recordsTotal"    => intval( $totalData ),  // total number of records
			"recordsFiltered" => intval( $totalFiltered ), // total number of records after searching, if there is no searching then totalFiltered = totalData
			"data"            => $data   // total data array
			);


			echo json_encode($json_data);  // send data as json format
			
			
else:

$columns = array( 
// datatable column index  => database column name
	
	0 => 'image',
    1 => 'name',
    2 => 'category',
    3 => 'color',
    4 => 'dimension',
    5 => 'description',
);

// getting total number records without any search
$sql = "SELECT p.id, p.image, p.name, p.color, p.dimension, p.description, c.name AS category FROM products p INNER JOIN categories c ON p.category_id = c.id";

$query=mysqli_query($connect, $sql);
$totalData = mysqli_num_rows($query);
$totalFiltered = $totalData;  // when there is no search parameter then total number rows = total number filtered rows.


$sql = "SELECT p.id, p.image, p.name, p.color, p.dimension, p.description, c.name AS category FROM products p INNER JOIN categories c ON p.category_id = c.id";

if( !empty($requestData['search']['value']) ) {   // if there is a search parameter, $requestData['search']['value'] contains search parameter
	$sql.=" AND ( p.name LIKE '".$requestData['search']['value']."%' ";    
	$sql.=" OR p.color LIKE '".$requestData['search']['value']."%'  OR p.description LIKE '%".$requestData['search']['value']."%' ";

	$sql.=" OR p.dimension LIKE '".$requestData['search']['value']."%' )";
}
$query=mysqli_query($connect, $sql);
$totalFiltered = mysqli_num_rows($query);

 // when there is a search parameter then we have to modify total number filtered rows as per search result. 

 $sql.=" ORDER BY ". $columns[$requestData['order'][0]['column']]."   ".$requestData['order'][0]['dir']."  LIMIT ".$requestData['start']." ,".$requestData['length']."   ";

 /* $requestData['order'][0]['column'] contains colmun index, $requestData['order'][0]['dir'] contains order such as asc/desc  */	

 $query=mysqli_query($connect, $sql);


 $data = array();

 $i = 1;

 while( $row=mysqli_fetch_array($query) ) {  // preparing an array
	$dataArray=array(); 
	
	$img = '<img src="'.$row["image"].'" class="img-circle" width="50" height="50" />';
	
	//Update and Delete
	if(in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){
		$action = "";
	}

	
	//Update
	if(in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) ){
		$action .= '<a class="btn btn-default" href="EditProduct.php?id='.$row["id"].'" ><i class="fa fa-pencil"></i></a> ';
	}

	
	// Delete
	if(in_array('deleteProduct', $UserPermission)){
		$action .= '<button type="button" class="btn btn-default" onclick="removeFunc('.$row["id"].')" data-toggle="modal" data-target="#removeModal"><i class="fa fa-trash"></i></button>';
	}



    //$dataArray[] = $i++;
    $dataArray[] = $img;
    $dataArray[] = $row["name"];
    $dataArray[] = $row["category"];
    $dataArray[] = $row["color"];
    $dataArray[] = $row["dimension"];
    $dataArray[] = $row["description"];
    $dataArray[] = $action;

	$data[] = $dataArray;

	}




	$json_data = array(
			"draw"            => intval( $requestData['draw'] ),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
			"recordsTotal"    => intval( $totalData ),  // total number of records
			"recordsFiltered" => intval( $totalFiltered ), // total number of records after searching, if there is no searching then totalFiltered = totalData
			"data"            => $data   // total data array
			);


			echo json_encode($json_data);  // send data as json format

endif;			

?>
