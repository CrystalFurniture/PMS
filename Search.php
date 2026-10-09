<?php
include_once('DB/conn.php');

$searchTerm = $_GET['term']; 
 
// Fetch matched data from the database 
$query =$stmt= mysqli_query($connect,"SELECT * FROM products WHERE name LIKE '%".$searchTerm."%' ORDER BY name ASC"); 
 
// Generate array with skills data 
$nameData = array(); 
if($query->num_rows > 0){ 
    while($row = $query->fetch_assoc()){ 
        $data['value'] = $row['name']; 
        array_push($nameData, $data); 
    } 
} 
 
// Return results as json encoded array 
echo json_encode($nameData); 
?>