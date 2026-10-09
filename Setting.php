<?php
		include_once('DB/conn.php');

		$stmt= mysqli_query($connect, "SELECT * FROM users where id = '".$_SESSION['user_id']."'");
		$user_data = mysqli_fetch_array($stmt);
		
		if(isset($_POST['submit'])){
			$username = $_POST['username'];
			$email	  = $_POST['email'];
			$fname	  = $_POST['fname'];
			$lname	  = $_POST['lname'];
			$phone	  = $_POST['phone'];
			
					$result = mysqli_query($connect,"UPDATE users SET username='".$username."' , email= '".$email."', firstname= '".$fname."', lastname= '".$lname."', phone= '".$phone."' WHERE id =  '".$_SESSION['user_id']."'");
										
					echo "UPDATE users SET username='".$username."' , email= '".$email."', firstname= '".$fname."', lastname= '".$lname."', phone= '".$phone."' WHERE id =  '".$_SESSION['user_id']."'";

					if(!empty($_POST['password'])){
					$password = $_POST['password'];
				
				 $sql = mysqli_query($connect,"UPDATE users SET password= '".$password."' WHERE id =  '".$_SESSION['user_id']."'");
					}
					
					if($result){
						$sucmsg	=	base64_encode(serialize("Successfully Updated User!"));
						header("Location:setting.php?msg=$sucmsg");
						}else{
							$errmsg	=	base64_encode(serialize("Something Went wrong"));
							header("Location:setting.php?failmsg=$errmsg");
							exit;
						}
		}
		
		
		include_once('templates/header.php');

		include_once('templates/header_menu.php');

		include_once('templates/side_menubar.php');
		


?>


  <!-- Content Wrapper. Contains page content -->
  <div class="content-wrapper">
    <!-- Content Header (Page header) -->
    <section class="content-header">
      <h1>
        User
        <small>Setting</small>
      </h1>
      <ol class="breadcrumb">
        <li><a href="#"><i class="fa fa-dashboard"></i> Home</a></li>
        <li class="active">Setting</li>
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
              <h3 class="box-title">Update Information</h3>
            </div>
            <!-- /.box-header -->
            <form role="form" method="post" autocomplete="off" onsubmit="return checkForm(this);">
              <div class="box-body">

                

                <div class="form-group">
                  <label for="username">Username</label>
                  <input type="text" class="form-control" id="username" name="username" placeholder="Username" value="<?= $user_data['username'] ?>" required readonly="readonly">
                </div>

                <div class="form-group">
                  <label for="email">Email</label>
                  <input type="email" class="form-control" id="email" name="email" placeholder="Email" value="<?php echo $user_data['email'] ?>" autocomplete="off" required>
                </div>                

                <div class="form-group">
                  <label for="fname">First name</label>
                  <input type="text" class="form-control" id="fname" name="fname" placeholder="First name" value="<?php echo $user_data['firstname'] ?>" autocomplete="off" required>
                </div>

                <div class="form-group">
                  <label for="lname">Last name</label>
                  <input type="text" class="form-control" id="lname" name="lname" placeholder="Last name" value="<?php echo $user_data['lastname'] ?>" autocomplete="off" required>
                </div>

                <div class="form-group">
                  <label for="phone">Phone</label>
                  <input type="text" class="form-control" id="	  	  " name="phone" placeholder="Phone" value="<?php echo $user_data['phone'] ?>" autocomplete="off" required>
                </div>

                <div class="form-group">
                  <div class="alert alert-info alert-dismissible" role="alert">
                    <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span aria-hidden="true">&times;</span></button>
                      Leave the password field empty if you don't want to change.
                  </div>
                </div>

                <div class="form-group">
                  <label for="password">Password</label>
                  <input type="text" class="form-control" id="password" name="password" placeholder="Password" autocomplete="off">
                </div>

                <div class="form-group">
                  <label for="cpassword">Confirm password</label>
                  <input type="password" class="form-control" id="cpassword" name="cpassword" placeholder="Confirm Password" autocomplete="off" onChange="checkPasswordMatch();" />
                </div>
				<div class="registrationFormAlert" id="divCheckPasswordMatch">

              </div>
              <!-- /.box-body -->

              <div class="box-footer">
                <button type="submit" name="submit" class="btn btn-primary">Save Changes</button>
                <a href="#" class="btn btn-warning">Back</a>
              </div>
            </form>
          </div>
          <!-- /.box -->
        </div>
       
      </div>
      <!-- /.row -->
      

    </section>
    <!-- /.content -->
  </div>
  <!-- /.content-wrapper -->.
  
  <script>
	jQuery(function ($) {
    var validator = $('#form_action').validate({
        rules: {
            username: {
                required: true
            },
			email: {
                required: true,
				email:true
            },
            color: {
                required: true
            },
            
            fname: {
                required: true
            },
            lname: {
                required: true
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

 function checkForm(form)
  {
	  		 if ( form.password.value != form.cpassword.value  ) 
				{
					 document.getElementById("cpassword").style.borderColor="Red";
					 form.cpassword.focus();
				return false;
				}
 }					

  </script>

 
