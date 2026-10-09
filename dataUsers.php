<?php
include_once 'DB/conn.php';
include_once('templates/permission.php');

// storing  request (ie, get/post) global array to a variable  
$requestData= $_REQUEST;


$columns = array( 
// datatable column index  => database column name
	0 => 'username', 
	1 => 'email',
	2=>  'Name',
	3 => 'phone',
	4 => 'group_name'
);

// getting total number records without any search
$sql = "SELECT u.id AS u_id, u.username, email, CONCAT(u.firstname, ' ' , u.lastname) AS Name, u.phone, ug.id AS ug_id, ug.user_id, ug.group_id , g.id AS g_id, g.group_name, g.permission 
FROM users u LEFT JOIN user_group ug ON u.id = ug.user_id
LEFT JOIN groups g ON g.id = ug.group_id";

$query=mysqli_query($connect, $sql) ;
$totalData = mysqli_num_rows($query);
$totalFiltered = $totalData;  // when there is no search parameter then total number rows = total number filtered rows.

	$sql = "SELECT u.id AS u_id, u.username, email, CONCAT(u.firstname, ' ', u.lastname) AS Name, u.phone, ug.id AS ug_id, ug.user_id, ug.group_id , g.id AS g_id, g.group_name, g.permission 
FROM users u LEFT JOIN user_group ug ON u.id = ug.user_id
LEFT JOIN groups g ON g.id = ug.group_id ";

	if( !empty($requestData['search']['value']) ) {   // if there is a search parameter, $requestData['search']['value'] contains search parameter
		$sql.=" AND name LIKE '".$requestData['search']['value']."%'";    
		
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
	$nestedData=array(); 
	
	$id = $row["u_id"];
	
	//Update and Delete
	if( in_array('updateUser', $UserPermission) || in_array('viewUser', $UserPermission) || in_array('deleteUser', $UserPermission)){
	$action = '';
	}

	//Update
	if( in_array('updateUser', $UserPermission) || in_array('viewUser', $UserPermission)){
	$action .= '<a class="btn btn-default" href="EditUser.php?id='.$id.'" ><i class="fa fa-pencil"></i></a>';
	}

	// Delete
	if(in_array('deleteUser', $UserPermission)){
	$action .= '<button type="button" class="btn btn-default" onclick="removeFunc('.$id.')"  data-toggle="modal" data-target="#removeModal"><i class="fa fa-trash"></i></button>';
	}


	$nestedData[] = $i++;
	$nestedData[] = $row["username"];
	$nestedData[] = $row["email"];
	$nestedData[] = $row["Name"];
	$nestedData[] = $row["phone"];
	$nestedData[] = $row["group_name"];
	$nestedData[] = $action;
	
	$data[] = $nestedData;
}



$json_data = array(
			"draw"            => intval( $requestData['draw'] ),   // for every request/draw by clientside , they send a number as a parameter, when they recieve a response/data they first check the draw number, so we are sending same number in draw. 
			"recordsTotal"    => intval( $totalData ),  // total number of records
			"recordsFiltered" => intval( $totalFiltered ), // total number of records after searching, if there is no searching then totalFiltered = totalData
			"data"            => $data   // total data array
			);

echo json_encode($json_data);  // send data as json format

?>


