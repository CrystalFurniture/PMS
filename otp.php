<?php
@session_start();
	include_once('DB/conn.php');

error_reporting(E_ALL);
	if(isset($_POST['submit'])):
		
		$email = $_SESSION['email'];
		
		$otp = $_POST['otp'];
		
		$sql = mysqli_query($connect,"select * from users where email = '".$email."' AND otp = '".$otp."' AND is_otp = '0'");
		
			mysqli_num_rows($sql);

			if(mysqli_num_rows($sql)> 0){
			
				$row	=	mysqli_fetch_assoc($sql);
				
				$stmt = mysqli_query($connect,"UPDATE users SET  is_otp='1' WHERE id =  '".$row['id']."'");

				$date = date("d-m-Y h:i:s a");
				
				$result = mysqli_query($connect,"UPDATE tbl_otp SET  is_login='1', login_ip = '".$_SERVER['REMOTE_ADDR']."', login_date = '".$date."' WHERE otp = '".$otp."' AND user_id = '".$row['id']."' ORDER by otp_id DESC limit 1");

				
			
				$query = mysqli_query($connect,"select u.*, g.* from user_group u inner join groups g ON u.group_id = g.id where u.user_id = '".$row['id']."'");
				$rows	=	mysqli_fetch_assoc($query);
			
				// this is for bookmark page
					$_SESSION['permission']	=	$rows['permission'];
					
					$_SESSION['user_id']	=	$row['id'];
					
					$_SESSION['username']	=	$row['username'];

					

				$msg	=	base64_encode(serialize("Successfully logged In"));
				header("location:dashboard.php?msg=$msg");
				exit;
			}

			else{
			
			$msg	=	base64_encode(serialize("Invalid OTP "));
			header("location:otp.php?failmsg=$msg");
			exit;
			
			}
		
	endif;


?>

<!DOCTYPE html>
<html>
<head>
  <meta charset="utf-8">
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Log in</title>
  <!-- Tell the browser to be responsive to screen width -->
  <meta content="width=device-width, initial-scale=1, maximum-scale=1, user-scalable=no" name="viewport">
  <!-- Bootstrap 3.3.7 -->
  
  
  <link rel="stylesheet" href="assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <!-- Font Awesome -->
  <link rel="stylesheet" href="assets/bower_components/font-awesome/css/font-awesome.min.css">
  <!-- Ionicons -->
  <link rel="stylesheet" href="assets/bower_components/Ionicons/css/ionicons.min.css">
  <!-- Theme style -->
  <link rel="stylesheet" href="assets/dist/css/AdminLTE.min.css">
  <!-- iCheck -->
  <link rel="stylesheet" href="assets/plugins/iCheck/square/blue.css">

  <!-- HTML5 Shim and Respond.js IE8 support of HTML5 elements and media queries -->
  <!-- WARNING: Respond.js doesn't work if you view the page via file:// -->
  <!--[if lt IE 9]>
  <script src="https://oss.maxcdn.com/html5shiv/3.7.3/html5shiv.min.js"></script>
  <script src="https://oss.maxcdn.com/respond/1.4.2/respond.min.js"></script>
  <![endif]-->

  <!-- Google Font -->
  <link rel="stylesheet" href="https://fonts.googleapis.com/css?family=Source+Sans+Pro:300,400,600,700,300italic,400italic,600italic">
  
</head>
<body class="hold-transition login-page">
<div class="login-box">
  <div class="login-logo">

  <?php if(isset($_GET['failmsg'])):

			 echo "<p class='text-center m-t-md' style='color:red'>".unserialize(base64_decode($_GET['failmsg']))."</p>";

			elseif(isset($_GET['msg'])):

				echo "<p class='text-center m-t-md' style='color:green'>".unserialize(base64_decode($_GET['msg']))."</p>";

			endif;

			?>
  </div>
  <!-- /.login-logo -->
  <div class="login-box-body">
    <p class="login-box-msg">Sign in to start your session</p>

   
    <form action="#" method="post">
      <div class="form-group has-feedback">
        <input type="text" class="form-control" name="otp" id="otp" placeholder="Enter OTP" autocomplete="off">
        <span class="glyphicon glyphicon-envelope form-control-feedback"></span>
      </div>

      <div class="row">
        <div class="col-xs-8">
          <div class="checkbox icheck">
            <!-- <label>
              <input type="checkbox"> Remember Me
            </label> -->
          </div>
        </div>
        <!-- /.col -->
        <div class="col-xs-4">
          <button type="submit" class="btn btn-primary btn-block btn-flat" name="submit">Sign In</button>
        </div>
        <!-- /.col -->
      </div>
    </form>

  </div>
  <!-- /.login-box-body -->
</div>
<!-- /.login-box -->

<!-- jQuery 3 -->

<script src="assets/bower_components/jquery/dist/jquery.min.js"></script>
<!-- Bootstrap 3.3.7 -->
<script src="assets/bower_components/bootstrap/dist/js/bootstrap.min.js"></script>
<!-- iCheck -->
<script src="assets/plugins/iCheck/icheck.min.js"></script>
<script>
  $(function () {
    $('input').iCheck({
      checkboxClass: 'icheckbox_square-blue',
      radioClass: 'iradio_square-blue',
      increaseArea: '20%' // optional
    });
  });
</script>
</body>
</html>
