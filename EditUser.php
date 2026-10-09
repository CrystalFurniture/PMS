<?php
		include_once('DB/conn.php');

				
			$id = $_GET['id'];
			$stmt= mysqli_query($connect, "SELECT * FROM users u INNER JOIN user_group ug ON u.id = ug.user_id where u.id = '".$_GET['id']."'");
			$user_data = mysqli_fetch_array($stmt);
			
			$sql = mysqli_query($connect, "SELECT * FROM groups");
			$category = array();
			while( $row = mysqli_fetch_array($sql) ) {
				$groups[$row["id"]] = $row["group_name"];
			}
		
		if(isset($_POST['submit'])){
			$username = $_POST['username'];
			$email	  = $_POST['email'];
			$fname	  = $_POST['fname'];
			$lname	  = $_POST['lname'];
			$phone	  = $_POST['phone'];
			$groups	  = $_POST['groups'];
			
					$result = mysqli_query($connect,"UPDATE users u INNER JOIN user_group ug ON u.id = ug.user_id 
					SET u.username='".$username."' , u.email= '".$email."', u.firstname= '".$fname."', u.lastname= '".$lname."', u.phone= '".$phone."', ug.group_id = '".$groups."'	WHERE u.id =  '".$id."'");
					
					
					if(!empty($_POST['password'])){
					$password = $_POST['password'];
				
					 $sql = mysqli_query($connect,"UPDATE users SET password= '".$password."' WHERE id =  '".$id."'");
					}
					
					if($result){
						$sucmsg	=	base64_encode(serialize("Successfully Updated User!"));
						header("Location:Users.php?msg=$sucmsg");
						}else{
							$errmsg	=	base64_encode(serialize("Something Went wrong"));
							header("Location:EditUser.php?failmsg=$errmsg&id=$id");
							exit;
						}
		}
	

		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		


?>

<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.12.0/jquery.validate.js"></script>
<script type="text/javascript" src="http://cdnjs.cloudflare.com/ajax/libs/jquery-validate/1.12.0/additional-methods.js"></script>
<style>
#form_action .error {
    color: #ff0000a8;
    border-color: #ff0000a1;
}
</style>


  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        Manage
        <small>Users</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Users</li>
      </ol>
    </section>

    <!-- Main content -->
    <section class="content">
      <!-- Small boxes (Stat box) -->
      <div class="row">
        <div class="col-md-12 col-xs-12">

          <?php if(isset($_GET['msg'])): ?>
            <div class="alert alert-success alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo unserialize(base64_decode($_GET['msg'])); ?>
            </div>
          <?php elseif(isset($_GET['failmsg'])): ?>
            <div class="alert alert-error alert-dismissible" role="alert">
              <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
              <?php echo unserialize(base64_decode($_GET['failmsg'])); ?>
            </div>
          <?php endif; ?>
          
          <div class="box">
            <div class="box-header">
              <h3 class="box-title">Edit User</h3>
            </div>
          <form role="form" method="post" enctype="multipart/form-data" id="form_action">
              <div class="box-body">

                <div class="form-group">
                  <label for="groups">Groups</label>
                  <select class="form-control" id="groups" name="groups">
                    <option value="">Select Groups</option>
                    <?php foreach ($groups as $k => $v): ?>
                      <option value="<?php echo $k ?>" <?php if($user_data['group_id'] == $k) { echo 'selected'; } ?> ><?php echo $v; ?></option> 
                    <?php endforeach ?>
                  </select>
                </div>

                <div class="form-group">
                  <label for="username">Username</label>
                  <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?php echo $user_data['username'] ?>" autocomplete="off">
                </div>

                <div class="form-group">
                  <label for="email">Email</label>
                  <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo $user_data['email'] ?>" autocomplete="off">
                </div>                

                <div class="form-group">
                  <label for="fname">First name</label>
                  <input type="text" class="form-control" id="fname" name="fname" placeholder="First name" value="<?php echo $user_data['firstname'] ?>" autocomplete="off">
                </div>

                <div class="form-group">
                  <label for="lname">Last name</label>
                  <input type="text" class="form-control" id="lname" name="lname" placeholder="Last name" value="<?php echo $user_data['lastname'] ?>" autocomplete="off">
                </div>

                <div class="form-group">
                  <label for="phone">Phone</label>
                  <input type="text" class="form-control" id="phone" name="phone" placeholder="Phone" value="<?php echo $user_data['phone'] ?>" autocomplete="off">
                </div>

                <div class="form-group">
                  <div class="alert alert-info alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                      Leave the password field empty if you don't want to change.
                  </div>
                </div>

                <div class="form-group">
                  <label for="password">Password</label>
                  <input type="text" class="form-control" id="password" name="password" placeholder="Password"  autocomplete="off">
                </div>

                <div class="form-group">
                  <label for="cpassword">Confirm password</label>
                  <input type="password" class="form-control" id="cpassword" name="cpassword" placeholder="Confirm Password" autocomplete="off">
                </div>

              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button type="submit" name="submit" class="btn btn-primary">Save Changes</button>
                <a onclick="goBack()" class="btn btn-warning">Back</a>
              </div>
            </form>
          </div>
          <!-- /.box -->
        </div>
        <!-- col-md-12 -->
      </div>
      <!-- /.row -->
      

    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->

<script type="text/javascript">
	jQuery(function ($) {
    var validator = $('#form_action').validate({
        rules: {
            groups: {
                required: true
            },
            fname: {
                required: true
            },
            lname: {
                required: true
            },
            phone: {
              required:true,
			  maxlength:10,
			  number: true
            },
			username: {
                required: true
            },
            email: {
                required: true,
				email : true
            },
            password: {
				required : false,
            },
			cpassword: {
                equalTo: '#password'
            }
        },
        messages: {},
        highlight: function (element) {
            $(element).addClass('error')
        },
        unhighlight: function (element) {
            $(element).removeClass('error')
        }
    });
});
	  
</script>
<?php
		include_once('templates/footer.php');

?>

