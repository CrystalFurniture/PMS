<?php 
	
	include_once('DB/conn.php');

	if(isset($_POST['submit'])){
		error_reporting(E_ALL);		
		$email = $_POST['email'];
				
		$sql = mysqli_query($connect,"select * from users where email = '".$email."'");
		
			mysqli_num_rows($sql);

			if(mysqli_num_rows($sql)> 0){
			
				$row	=	mysqli_fetch_assoc($sql);
			
					$otp = rand(10000, 99999);
						
					$stmt = mysqli_query($connect,"UPDATE users SET otp='".$otp."', is_otp='0' WHERE id =  '".$row['id']."'");
					
					$_SESSION['phone']	=	$row['phone'];

					$_SESSION['email']		= 	$row['email'];
					
					$date = date("d-m-Y h:i:s a");
					
					$result = mysqli_query($connect,"INSERT INTO tbl_otp(otp, user_id, date)values('".$otp."', '".$row['id']."', '".$date."')");

				if($result){
					
					        $user = "kpsd-crystalfur"; //use your sms api username
							$pass     = "crystal4";  //enter your password
							$mobile   =     $_SESSION['phone'];
							
							$senderid    =     "CRYFUR";//use your sms api sender id
							$message = urlencode("You have initiated the Login Request for Crystal PMS. OTP for Login is : ".$otp.". Do not share this.");
							
							$sms_url = "http://103.16.101.52:8080/sendsms/bulksms?username=".$user."&password=".$pass."&type=0&dlr=1&destination=".$mobile."&source=".$senderid."&message=".$message."";
							
							
							$ch=curl_init();
							curl_setopt($ch,CURLOPT_URL,$sms_url);
							curl_setopt($ch, CURLOPT_POST, 1);
							curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
							curl_setopt($ch,CURLOPT_TIMEOUT, '3');
							$content = trim(curl_exec($ch));
							
							curl_close($ch);
							echo "<br>". $content;
							$arr = (explode("|",$content));
						
						if($arr[0] == '1701'){
						$msg	=	base64_encode(serialize(" OTP Sent Successfully to ".$_SESSION['phone']));
						header("location:otp.php?msg=$msg&response=$content");
						exit;
						}
				}

			}
			else{
			
			$msg	=	base64_encode(serialize("Invalid Username"));
			header("location:index.php?failmsg=$msg");
			exit;
			
			}
		
}

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
        <input type="email" class="form-control" name="email" id="email" placeholder="Email" autocomplete="off">
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
          <button type="submit" class="btn btn-primary btn-block btn-flat" name="submit">Get OTP</button>
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
