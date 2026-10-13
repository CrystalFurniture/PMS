<?php
$pmsUserName = isset($_SESSION['fullName']) ? trim((string) $_SESSION['fullName']) : '';
if ($pmsUserName === '') {
	$pmsUserName = isset($_SESSION['username']) ? (string) $_SESSION['username'] : '';
}
$pmsUserInitial = $pmsUserName !== '' ? strtoupper(substr($pmsUserName, 0, 1)) : '?';
$pmsUserSub = isset($_SESSION['email']) ? (string) $_SESSION['email'] : '';
?>
<header class="main-header">
    <!-- Logo -->
    <a href="dashboard.php" class="logo">
      <!-- mini logo for sidebar mini 50x50 pixels -->
      <span class="logo-mini"><span class="pms-mark">CF</span></span>
      <!-- logo for regular state and mobile devices -->
      <span class="logo-lg"><span class="pms-mark">CF</span><span class="pms-brand"><strong>Crystal Furniture</strong><span>Quotations</span></span></span>
    </a>
    <!-- Header Navbar: style can be found in header.less -->
    <nav class="navbar navbar-static-top">
      <!-- Sidebar toggle button-->
      <a href="#" class="sidebar-toggle" data-toggle="push-menu" role="button">
        <span class="sr-only">Toggle navigation</span>
      </a>
      <div class="pms-topbar">
        <?php include __DIR__ . '/theme_toggle.php'; ?>
        <div class="pms-user">
          <span class="pms-avatar" aria-hidden="true"><?php echo htmlspecialchars($pmsUserInitial, ENT_QUOTES, 'UTF-8'); ?></span>
          <span class="pms-user-text">
            <strong><?php echo htmlspecialchars($pmsUserName, ENT_QUOTES, 'UTF-8'); ?></strong>
            <span><?php echo htmlspecialchars($pmsUserSub, ENT_QUOTES, 'UTF-8'); ?></span>
          </span>
        </div>
        <a href="logOut.php" class="btn btn-default">Log out</a>
      </div>
    </nav>
  </header>
  <!-- Left side column. contains the logo and sidebar -->
