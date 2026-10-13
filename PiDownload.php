<?php
/**
 * Old download links for Proforma Invoices: the PI is now shown in the application's own document
 * format (PiView.php, same layout and Excel export as the quotation print page).
 */
	$id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
	header('Location: PiView.php?id=' . $id, true, 301);
	exit;
