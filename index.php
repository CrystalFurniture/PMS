<?php 
	
	include_once('DB/conn.php');

	if(isset($_POST['submit'])):
		
		$email = $_POST['email'];
		
		$pass = $_POST['password'];
		
		$sql = mysqli_query($connect,"select * from users where email = '".$email."' AND password = '".$pass."'");
		
			mysqli_num_rows($sql);

			if(mysqli_num_rows($sql)> 0):
			
				$row	=	mysqli_fetch_assoc($sql);
			
				$query = mysqli_query($connect,"select u.*, g.* from user_group u inner join groups g ON u.group_id = g.id where u.user_id = '".$row['id']."'");
				$rows	=	mysqli_fetch_assoc($query);
			
				// this is for bookmark page
					$_SESSION['permission']	=	$rows['permission'];
					
					$_SESSION['user_id']	=	$row['id'];
					
					$_SESSION['username']	=	$row['username'];

					$_SESSION['fullName']	=	$row['firstname']." ". $row['lastname'];

					$_SESSION['email']		= 	$row['email'];

					$_SESSION['phone']		= 	$row['phone'];

				$msg	=	base64_encode(serialize("Successfully logged In"));
				header("location:dashboard.php?msg=$msg");
				exit;

			else:
			
			$msg	=	base64_encode(serialize("Invalid Username / Password "));
			header("location:index.php?failmsg=$msg");
			exit;
			
			endif;
		
	endif;

?>

<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <?php include 'templates/theme_head.php'; ?>
  <meta http-equiv="X-UA-Compatible" content="IE=edge">
  <title>Sign in · Crystal Furniture</title>
  <meta content="width=device-width, initial-scale=1" name="viewport">
  <link rel="stylesheet" href="assets/bower_components/bootstrap/dist/css/bootstrap.min.css">
  <link rel="stylesheet" href="https://fonts.googleapis.com/css2?family=Source+Sans+3:wght@400;500;600;700&display=swap">
  <link rel="stylesheet" href="css/pms-theme.css?v=7">
</head>
<body>
<?php
	// Legacy ?msg= / ?failmsg= values: decoded without allowing objects, then escaped.
	$loginNote = function ($encoded) {
		$text = @unserialize(base64_decode((string) $encoded), array('allowed_classes' => false));
		return is_string($text) ? htmlspecialchars($text, ENT_QUOTES, 'UTF-8') : '';
	};
?>
<?php $pmsToggleClass = 'pms-login-toggle'; include 'templates/theme_toggle.php'; ?>
<main class="pms-login">
  <div class="pms-login-brand">
    <div class="pms-login-logo"><span class="pms-mark">CF</span> Crystal Furniture</div>
    <div>
      <h1>Quotations for dealers and retail, in one place.</h1>
      <p>Build DP and MRP quotations from the catalogue, apply state price lists, and export to PDF or Excel.</p>
    </div>
    <small>&copy; 2017&ndash;<?php echo date('Y'); ?> Crystal Furniture</small>
  </div>
  <div class="pms-login-form">
    <form action="#" method="post">
      <div>
        <h2>Sign in</h2>
        <p class="text-muted" style="margin:6px 0 0">Use your work email.</p>
      </div>
      <?php if (isset($_GET['failmsg'])): ?>
        <div class="alert alert-danger" role="alert"><?php echo $loginNote($_GET['failmsg']); ?></div>
      <?php elseif (isset($_GET['msg'])): ?>
        <div class="alert alert-success" role="status"><?php echo $loginNote($_GET['msg']); ?></div>
      <?php endif; ?>
      <label for="email">Email
        <input type="email" class="form-control" name="email" id="email" autocomplete="username" required>
      </label>
      <label for="password">Password
        <input type="password" class="form-control" name="password" id="password" autocomplete="current-password" required>
      </label>
      <button type="submit" class="btn btn-primary btn-block" name="submit">Sign in</button>
    </form>
  </div>
</main>
</body>
</html>
