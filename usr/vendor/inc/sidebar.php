<?php
$aid = $_SESSION['u_id'] ?? 0;
$who = ['name' => 'Driver'];
if ($stmt = $mysqli->prepare("SELECT CONCAT(u_fname,' ',u_lname) FROM tms_user WHERE u_id=?")) {
  $stmt->bind_param('i', $aid); $stmt->execute(); $stmt->bind_result($nm);
  if ($stmt->fetch()) $who['name'] = $nm; $stmt->close();
}
?>
<ul class="sidebar navbar-nav">
  <li class="nav-item active">
    <a class="nav-link" href="user-dashboard.php"><i class="fas fa-fw fa-tachometer-alt"></i><span>Dashboard</span></a>
  </li>

  <li class="nav-item">
    <a class="nav-link" href="driver-trips.php"><i class="fas fa-fw fa-road"></i><span>Trips</span></a>
  </li>

  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="settingsDropdown" role="button" data-toggle="dropdown">
      <i class="fas fa-fw fa-user"></i><span>Settings</span>
    </a>
    <div class="dropdown-menu" aria-labelledby="settingsDropdown">
      <h6 class="dropdown-header"><?php echo htmlspecialchars($who['name']); ?></h6>
      <a class="dropdown-item" href="user-view-profile.php">View</a>
      <a class="dropdown-item" href="user-update-profile.php">Update</a>
      <a class="dropdown-item" href="user-change-pwd.php">Change Password</a>
    </div>
  </li>
</ul>
