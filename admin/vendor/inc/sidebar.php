<?php
  $aid = $_SESSION['u_id'] ?? 0;
?>
<ul class="sidebar navbar-nav">
  <li class="nav-item">
    <a class="nav-link" href="user-dashboard.php">
      <i class="fas fa-fw fa-tachometer-alt"></i>
      <span>Dashboard</span>
    </a>
  </li>

  <li class="nav-item">
    <a class="nav-link" href="appointments.php">
      <i class="fas fa-fw fa-route"></i>
      <span>Trips</span>
    </a>
  </li>

  <!-- Keep only Settings (you asked to remove Vehicles, Bookings, Feedbacks) -->
  <li class="nav-item dropdown">
    <a class="nav-link dropdown-toggle" href="#" id="drvSettings" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
      <i class="fas fa-fw fa-user-cog"></i>
      <span>Settings</span>
    </a>
    <div class="dropdown-menu" aria-labelledby="drvSettings">
      <a class="dropdown-item" href="user-view-profile.php">View Profile</a>
      <a class="dropdown-item" href="user-update-profile.php">Update Profile</a>
      <a class="dropdown-item" href="user-change-pwd.php">Change Password</a>
    </div>
  </li>
</ul>
