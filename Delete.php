<?php
		include_once('DB/conn.php');

		error_reporting(E_ALL);

	if(empty($_SESSION['user_id'])){
		http_response_code(401);
		exit;
	}

	$id = $_REQUEST['product_id'];
	$table = $_REQUEST['table'];	

		if($table == 'categories'){
			
			$query = mysqli_query($connect,"select * from categories where id = ". $id);
			$rows = mysqli_fetch_assoc($query);
			
			$dirname = "Category/".$rows['name'];
			if(is_dir($dirname))
			{
			array_map('unlink', glob("$dirname/*.*"));

			rmdir($dirname);
			
				$sql = mysqli_query($connect,"Delete from products where category_id = ".$id."");		
			} 
		}
		else if($table == 'products'){
			
			$query = mysqli_query($connect,"select * from products where id = ". $id);
			
			$rows = mysqli_fetch_assoc($query);
			
			
			unlink ($rows['image']);

			$sql = mysqli_query($connect,"delete from products where id = ". $id);
			
		}
	
		else if($table == 'groups'){
			
			$query = mysqli_query($connect,"Delete from user_group where group_id = ". $id);
			
			$rows = mysqli_fetch_assoc($query);
		}
	
	
		else if($table == 'users'){
			
			$query = mysqli_query($connect,"Delete from user_group where user_id = ". $id);
			
			$rows = mysqli_fetch_assoc($query);
		}
	
		else if($table == 'tbl_quotation'){
			// Quotations are deleted through action.php (delete_quotation): POST, CSRF token, owner only.
			http_response_code(410);
			exit;
		}
	
	//$sql = mysqli_query($connect, "Delete from ".$table." where id = ".$id."");
	
	
	
?>