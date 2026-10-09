<aside class="main-sidebar">
	<!-- sidebar: style can be found in sidebar.less -->
	<section class="sidebar" style="height: auto;">
		<!-- sidebar menu: : style can be found in sidebar.less -->
		<ul class="sidebar-menu tree" data-widget="tree">
			<li id="dashboardMainMenu" class="active">
				<a href="dashboard.php"> <i class="fa fa-dashboard"></i>  <span>Dashboard</span>
				</a>
			</li>
        <?php if($UserPermission){ ?>
			
			
			<?php if(in_array('createGroup', $UserPermission) || in_array('updateGroup', $UserPermission) || in_array('viewGroup', $UserPermission) || in_array('deleteGroup', $UserPermission)){ ?>

			<li class="treeview" id="mainGroupNav">
				<a href="#"> <i class="fa fa-files-o"></i>
					<span>Groups</span>
					<span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
				</a>
				<ul class="treeview-menu">
					<?php if(in_array('createGroup', $UserPermission)): ?>
					<li id="addGroupNav"><a href="AddGroup.php"><i class="fa fa-circle-o"></i> Add Group</a>
					</li>
					<?php endif; ?>
					<?php if(in_array('updateGroup', $UserPermission) || in_array('viewGroup', $UserPermission) || in_array('deleteGroup', $UserPermission)): ?>
					<li id="manageGroupNav"><a href="Groups.php"><i class="fa fa-circle-o"></i> Manage Groups</a>
					</li>
					<?php endif; ?>
				</ul>
			</li>
			<?php } ?>
			
			 <?php if(in_array('createUser', $UserPermission) || in_array('updateUser', $UserPermission) || in_array('viewUser', $UserPermission) || in_array('deleteUser', $UserPermission)){ ?>

			<li class="treeview" id="mainUserNav">
				<a href="#"> <i class="fa fa-users"></i>
					<span>Users</span>
					<span class="pull-right-container">
                <i class="fa fa-angle-left pull-right"></i>
              </span>
				</a>
				<ul class="treeview-menu">
					<?php if(in_array('createUser', $UserPermission)){ ?>
					<li id="createUserNav"><a href="AddUser.php"><i class="fa fa-circle-o"></i> Add User</a>
					</li>
					<?php } ?>
					
					<?php if(in_array('updateUser', $UserPermission) || in_array('viewUser', $UserPermission) || in_array('deleteUser', $UserPermission)){ ?>
					<li id="manageUserNav"><a href="Users.php"><i class="fa fa-circle-o"></i> Manage Users</a>
					</li>
					<?php } ?>
				</ul>
			</li>
			<?php } ?>

			<?php if(in_array('createCategory', $UserPermission) || in_array('updateCategory', $UserPermission) || in_array('viewCategory', $UserPermission) || in_array('deleteCategory', $UserPermission)){ ?>
			<li id="categoryNav">
				<a href="Category.php"> <i class="fa fa-files-o"></i>  <span>Category</span>
				</a>
			</li>
			<?php } ?>
			
			<?php if(in_array('createProduct', $UserPermission) || in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){ ?>

			<li class="treeview" id="mainProductNav">
				<a href="#"> <i class="fa fa-cube"></i>
					<span>Products</span>
					<span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
				</a>
				<ul class="treeview-menu">
					<?php if(in_array('createProduct', $UserPermission)){ ?>
					<li id="addProductNav"><a href="AddProducts.php"><i class="fa fa-circle-o"></i> Add Product</a>
					</li>
					<li id="addProductNav1"><a href="UploadProduct.php" target="_blank"><i class="fa fa-circle-o"></i> Upload Product</a>
					</li>
					<?php } ?>
					
					<?php if(in_array('updateProduct', $UserPermission) || in_array('viewProduct', $UserPermission) || in_array('deleteProduct', $UserPermission)){ ?>
					
					<li id="manageProductNav"><a href="ManageProducts.php"><i class="fa fa-circle-o"></i> Catalogue Products</a>
					</li>

					<?php if(in_array('updateProduct', $UserPermission)){ ?>
					<li id="updateColorsNav"><a href="UpdateProductColors.php"><i class="fa fa-circle-o"></i> Update Colors (Excel)</a>
					</li>
					<?php } ?>

					<li id="oldProductNav"><a href="OldProducts.php"><i class="fa fa-circle-o"></i> Non Stock Items</a>
					</li>


					<?php /*
					$sel = mysqli_query($connect,"select * from categories");
					while( $cat =mysqli_fetch_array($sel) ) { ?>

					<li id="manageProductNav"><a href="ManageProducts.php?cat_id=<?= $cat['id'];?>">
					<span class="pull-right-container">
					  <i class="fa fa-angle-right pull-right"></i>
					</span>
					<?= $cat['name'];?> Products</a>
					</li>
					
					<?php
						}*/
					}
					?>
				</ul>
			</li>
			<?php } ?>
			
			<li id="createPDF">
				<a href="CreatedFiles.php"> <i class="fa fa-files-o"></i>  <span>Created PDF</span>
				</a>
			</li>
			<?php if(in_array('createProduct', $UserPermission)){ ?>
			<li id="brandNav">
				<a href="PriceList.php"> <i class="fa fa-files-o"></i>  <span>Price List</span>
				</a>
			</li>
			<?php } ?>
			

			<li class="treeview" id="mainQuotationv">
				<a href="#"> <i class="fa fa-cube"></i>
					<span>Create Quotation</span>
					<span class="pull-right-container">
                  <i class="fa fa-angle-left pull-right"></i>
                </span>
				</a>
				<ul class="treeview-menu">
					<li id="CreatedOrderDp"><a href="ViewQuotation.php"><i class="fa fa-circle-o"></i> View Quotation </a>
					</li>
										
					<li id="CreatedOrderDp"><a href="CreatedOrderDp.php"><i class="fa fa-circle-o"></i> Create DP Quotation </a>
					</li>
										
					<li id="CreatedOrderMRP"><a href="CreatedOrderMRP.php"><i class="fa fa-circle-o"></i> Create MRP Quotation </a>
					</li>

				</ul>
			</li>


			<?php if(in_array('updateSetting', $UserPermission)){ ?>

			<li>
				<a href="Setting.php">
					<i class="fa fa-wrench"></i>
					<span>Setting</span>
				</a>
			</li>
			<?php } ?>
		<?php } ?>
			<!-- user permission info -->
			<li><a href="logOut.php"><i class="glyphicon glyphicon-log-out"></i> <span>Logout</span></a>
			</li>
		</ul>
	</section>
	<!-- /.sidebar -->
</aside>