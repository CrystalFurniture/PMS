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
	
	$connect    = mysqli_connect($host, $user, $pass, $database) or die("Error : ".mysql_error());		
	
//define('TITLE', 'Crystal Furniture');
define('URL', '192.168.1.227/');

date_default_timezone_set('Asia/Calcutta'); 
	
?>
