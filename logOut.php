<?php
session_start();
session_destroy();
				
				$msg	=	base64_encode(serialize("Successfully logged Out"));
				header("location:index.php?msg=$msg");
				exit;

?>