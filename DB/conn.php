<?php
error_reporting(E_ALL);

@session_start();
	//database configuration

	$host       = "localhost";
	$user       = "root";
	$pass       = "";
	$database   = "pms";
//	$user       = "crystalsaleadmin";
//	$pass       = "Crystal!@#$";
	
	$connect    = mysqli_connect($host, $user, $pass, $database) or die("Error : ".mysqli_connect_error());		
	
//define('TITLE', 'Crystal Furniture');
define('URL', 'localhost/');

date_default_timezone_set('Asia/Calcutta'); 
	
?>
