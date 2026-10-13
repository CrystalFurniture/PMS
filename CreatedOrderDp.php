<?php
		include_once('DB/conn.php');
		include_once('includes/quotation_lib.php');
		include_once('includes/ai_provider.php');
		include_once('includes/ai_import_lib.php');
error_reporting(0);
		@session_start();
		qt_require_login_page();
		$userName = $_SESSION['username'];
		$qtType = 'DP';
		$qtCfg = qt_config($qtType);
		$qtCart = qt_cart_get($qtType);

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');

		// Page body, product list and cart panel: shared with the other price type.
		include('templates/create_quotation.php');
