<?php

	if(empty($_SESSION['user_id'])):
		$msg	=	base64_encode(serialize("Please Login To Access You Account"));
		@header("location:index.php?failmsg=$msg");
		exit;
		else:
		 $UserPermission = unserialize($_SESSION['permission']); 
		
		/* echo "<pre>";
		print_r($UserPermission);
		echo "</pre>"; */
		
	endif;

?>