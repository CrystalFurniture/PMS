<?php
// Current page, used to highlight its menu entry (pages no longer need to do this themselves).
$pmsPage = basename($_SERVER['PHP_SELF']);
$pmsActive = function ($pages) use ($pmsPage) {
	return in_array($pmsPage, $pages, true) ? 'active' : '';
};
$pmsOpen = function ($pages) use ($pmsPage) {
	return in_array($pmsPage, $pages, true) ? ' active menu-open' : '';
};
$pmsShowList = function ($pages) use ($pmsPage) {
	return in_array($pmsPage, $pages, true) ? ' style="display: block;"' : '';
};
$pmsHas = function ($perms) use (&$UserPermission) {
	if (!$UserPermission) {
		return false;
	}
	foreach ($perms as $perm) {
		if (in_array($perm, $UserPermission)) {
			return true;
		}
	}
	return false;
};
$pmsGroupPages = array('AddGroup.php', 'Groups.php', 'EditGroup.php');
$pmsUserPages = array('AddUser.php', 'Users.php', 'EditUser.php');
$pmsProductPages = array('AddProducts.php', 'UploadProduct.php', 'ManageProducts.php', 'UpdateProductColors.php', 'OldProducts.php', 'EditProduct.php');
$pmsCanProducts = $pmsHas(array('createProduct', 'updateProduct', 'viewProduct', 'deleteProduct'));
$pmsCanCategory = $pmsHas(array('createCategory', 'updateCategory', 'viewCategory', 'deleteCategory'));
$pmsCanAdminProducts = $pmsHas(array('createProduct'));
$pmsCanUsers = $pmsHas(array('createUser', 'updateUser', 'viewUser', 'deleteUser'));
$pmsCanGroups = $pmsHas(array('createGroup', 'updateGroup', 'viewGroup', 'deleteGroup'));
$pmsCanSettings = $pmsHas(array('updateSetting'));
?>
<aside class="main-sidebar">
	<!-- sidebar: style can be found in sidebar.less -->
	<section class="sidebar" style="height: auto;">
		<!-- sidebar menu: : style can be found in sidebar.less -->
		<ul class="sidebar-menu tree" data-widget="tree">
			<li <?= $pmsPage === 'dashboard.php' ? 'id="dashboardMainMenu"' : 'id="dashboardNav"' ?> class="<?= $pmsActive(array('dashboard.php')) ?>">
				<a href="dashboard.php"> <i class="fa fa-dashboard"></i>  <span>Dashboard</span>
				</a>
			</li>
        <?php if($UserPermission){ ?>

			<li class="header">Quotations</li>
			<li id="CreatedOrderDp" class="<?= $pmsActive(array('CreatedOrderDp.php', 'ViewOrderDp.php')) ?>">
				<a href="CreatedOrderDp.php?new=1"> <i class="fa fa-plus-square-o"></i> <span>New DP quotation</span></a>
			</li>
			<li id="CreatedOrderMRP" class="<?= $pmsActive(array('CreatedOrderMRP.php', 'ViewOrderMRP.php')) ?>">
				<a href="CreatedOrderMRP.php?new=1"> <i class="fa fa-plus-square-o"></i> <span>New MRP quotation</span></a>
			</li>
			<li id="aiAssistantNav" class="<?= $pmsActive(array('AiAssistant.php')) ?>">
				<a href="AiAssistant.php"> <i class="fa fa-comments-o"></i> <span>AI Assistant</span></a>
			</li>
			<li id="viewQuotationNav" class="<?= $pmsActive(array('ViewQuotation.php', 'DetailQuotationDp.php', 'DetailQuotationMRP.php')) ?>">
				<a href="ViewQuotation.php"> <i class="fa fa-list-alt"></i> <span>Saved quotations</span></a>
			</li>
			<li id="createPDF" class="<?= $pmsActive(array('CreatedFiles.php')) ?>">
				<a href="CreatedFiles.php"> <i class="fa fa-file-pdf-o"></i>  <span>Created PDFs</span></a>
			</li>

			<?php if ($pmsCanProducts || $pmsCanCategory || $pmsCanAdminProducts) { ?>
			<li class="header">Catalogue</li>
			<?php } ?>

			<?php if($pmsCanProducts){ ?>
			<li class="treeview<?= $pmsOpen($pmsProductPages) ?>" id="mainProductNav">
				<a href="#"> <i class="fa fa-cube"></i>
					<span>Products</span>
					<span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
				</a>
				<ul class="treeview-menu"<?= $pmsShowList($pmsProductPages) ?>>
					<?php if(in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){ ?>
					<li id="manageProductNav" class="<?= $pmsActive(array('ManageProducts.php', 'EditProduct.php')) ?>"><a href="ManageProducts.php"><i class="fa fa-circle-o"></i> Catalogue products</a>
					</li>
					<li id="oldProductNav" class="<?= $pmsActive(array('OldProducts.php')) ?>"><a href="OldProducts.php"><i class="fa fa-circle-o"></i> Non-stock items</a>
					</li>
					<?php } ?>
					<?php if(in_array('createProduct', $UserPermission)){ ?>
					<li id="addProductNav" class="<?= $pmsActive(array('AddProducts.php')) ?>"><a href="AddProducts.php"><i class="fa fa-circle-o"></i> Add product</a>
					</li>
					<li id="addProductNav1"><a href="UploadProduct.php" target="_blank"><i class="fa fa-circle-o"></i> Upload products (Excel)</a>
					</li>
					<?php } ?>
					<?php if(in_array('updateProduct', $UserPermission)){ ?>
					<li id="updateColorsNav" class="<?= $pmsActive(array('UpdateProductColors.php')) ?>"><a href="UpdateProductColors.php"><i class="fa fa-circle-o"></i> Update colours (Excel)</a>
					</li>
					<?php } ?>
				</ul>
			</li>
			<?php } ?>

			<?php if($pmsCanCategory){ ?>
			<li id="categoryNav" class="<?= $pmsActive(array('Category.php', 'AddCategory.php', 'EditCategory.php')) ?>">
				<a href="Category.php"> <i class="fa fa-folder-o"></i>  <span>Categories</span>
				</a>
			</li>
			<?php } ?>

			<?php if($pmsCanAdminProducts){ ?>
			<li id="brandNav" class="<?= $pmsActive(array('PriceList.php')) ?>">
				<a href="PriceList.php"> <i class="fa fa-inr"></i>  <span>Price list</span>
				</a>
			</li>
			<li id="statePriceNav" class="<?= $pmsActive(array('StatePriceList.php')) ?>">
				<a href="StatePriceList.php"> <i class="fa fa-map-marker"></i>  <span>State price lists</span></a>
			</li>
			<?php } ?>

			<?php if ($pmsCanUsers || $pmsCanGroups || $pmsCanSettings) { ?>
			<li class="header">Admin</li>
			<?php } ?>

			<?php if($pmsCanUsers){ ?>
			<li class="treeview<?= $pmsOpen($pmsUserPages) ?>" id="mainUserNav">
				<a href="#"> <i class="fa fa-users"></i>
					<span>Users</span>
					<span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
				</a>
				<ul class="treeview-menu"<?= $pmsShowList($pmsUserPages) ?>>
					<?php if(in_array('updateUser', $UserPermission) || in_array('viewUser', $UserPermission) || in_array('deleteUser', $UserPermission)){ ?>
					<li id="manageUserNav" class="<?= $pmsActive(array('Users.php', 'EditUser.php')) ?>"><a href="Users.php"><i class="fa fa-circle-o"></i> Manage users</a>
					</li>
					<?php } ?>
					<?php if(in_array('createUser', $UserPermission)){ ?>
					<li id="createUserNav" class="<?= $pmsActive(array('AddUser.php')) ?>"><a href="AddUser.php"><i class="fa fa-circle-o"></i> Add user</a>
					</li>
					<?php } ?>
				</ul>
			</li>
			<?php } ?>

			<?php if($pmsCanGroups){ ?>
			<li class="treeview<?= $pmsOpen($pmsGroupPages) ?>" id="mainGroupNav">
				<a href="#"> <i class="fa fa-shield"></i>
					<span>Groups</span>
					<span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
				</a>
				<ul class="treeview-menu"<?= $pmsShowList($pmsGroupPages) ?>>
					<?php if(in_array('updateGroup', $UserPermission) || in_array('viewGroup', $UserPermission) || in_array('deleteGroup', $UserPermission)): ?>
					<li id="manageGroupNav" class="<?= $pmsActive(array('Groups.php', 'EditGroup.php')) ?>"><a href="Groups.php"><i class="fa fa-circle-o"></i> Manage groups</a>
					</li>
					<?php endif; ?>
					<?php if(in_array('createGroup', $UserPermission)): ?>
					<li id="addGroupNav" class="<?= $pmsActive(array('AddGroup.php')) ?>"><a href="AddGroup.php"><i class="fa fa-circle-o"></i> Add group</a>
					</li>
					<?php endif; ?>
				</ul>
			</li>
			<?php } ?>

			<?php if($pmsCanSettings){ ?>
			<li class="<?= $pmsActive(array('Setting.php')) ?>">
				<a href="Setting.php">
					<i class="fa fa-wrench"></i>
					<span>Settings</span>
				</a>
			</li>
			<?php if(in_array('createProduct', $UserPermission)){ ?>
			<li id="aiSettingsNav" class="<?= $pmsActive(array('AiSettings.php')) ?>">
				<a href="AiSettings.php"> <i class="fa fa-magic"></i>  <span>AI settings</span></a>
			</li>
			<li id="approvedDocsNav" class="<?= $pmsActive(array('ApprovedDocuments.php')) ?>">
				<a href="ApprovedDocuments.php"> <i class="fa fa-file-text-o"></i>  <span>Approved documents</span></a>
			</li>
			<?php } ?>
			<?php } ?>
		<?php } ?>
			<!-- user permission info -->
			<li><a href="logOut.php"><i class="glyphicon glyphicon-log-out"></i> <span>Log out</span></a>
			</li>
		</ul>
	</section>
	<!-- /.sidebar -->
</aside>
