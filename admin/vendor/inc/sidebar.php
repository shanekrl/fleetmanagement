<?php
function isActive($file){ return basename($_SERVER['PHP_SELF']) === $file ? 'is-active' : ''; }
?>
<aside id="kayaSidebar" class="kaya-rail kaya-rail--light">
  <ul class="kaya-rail__nav">
    <li><a class="kaya-rail__link <?= isActive('admin-dashboard.php') ?>" href="admin-dashboard.php">
      <i class="fas fa-tachometer-alt"></i><span>Dashboard</span></a></li>

    <li><a class="kaya-rail__link <?= isActive('admin-trip-appointment.php') ?>" href="admin-trip-appointment.php">
      <i class="fas fa-book"></i><span>Trips</span></a></li>

    <li><a class="kaya-rail__link <?= isActive('admin-manage-vehicle.php') ?>" href="admin-manage-vehicle.php">
      <i class="fas fa-bus"></i><span>Vehicles</span></a></li>

    <li><a class="kaya-rail__link <?= isActive('admin-manage-driver.php') ?>" href="admin-manage-driver.php">
      <i class="fas fa-id-card"></i><span>Drivers</span></a></li>

    <li><a class="kaya-rail__link <?= isActive('admin-view-syslogs.php') ?>" href="admin-view-syslogs.php">
      <i class="fas fa-shield-alt"></i><span>Vehicle Telemetry</span></a></li>

    <li><a class="kaya-rail__link <?= isActive('admin-reports.php') ?>" href="admin-reports.php">
      <i class="fas fa-comments"></i><span>Reports</span></a></li>
  </ul>

  <div class="kaya-rail__footer">
    <a class="btn btn-outline-danger btn-block kaya-logout" href="admin-logout.php">
      <i class="fas fa-sign-out-alt mr-1"></i><span>Logout</span>
    </a>
  </div>
</aside>


