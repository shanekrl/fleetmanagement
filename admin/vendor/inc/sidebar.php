<?php
// Ensure session is available
if (session_status() !== PHP_SESSION_ACTIVE) {
  session_start();
}

// Smarter active helpers (handles arrays + ignores query strings)
function currentFile() {
  $path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH) ?? '';
  return basename($path ?: ($_SERVER['PHP_SELF'] ?? ''));
}
function isActive($files) {
  $cur = currentFile();
  foreach ((array)$files as $f) {
    if ($cur === $f) return 'is-active';
  }
  return '';
}
function isAny($files) {
  $cur = currentFile();
  foreach ((array)$files as $f) {
    if ($cur === $f) return true;
  }
  return false;
}

// Try to reuse checklogin helper if it exists, else fall back to session keys
function is_superadmin_like(): bool {
  if (function_exists('session_role')) {
    return session_role() === 'superadmin';
  }
  $role = $_SESSION['role'] ?? $_SESSION['a_role'] ?? null; // legacy a_role support
  return is_string($role) && strtolower($role) === 'superadmin';
}

// Group logic for Trips (treat any of these as "Trips" active)
$tripPages = [
  'admin-trip-appointment.php', // Upcoming (+ Calendar)
  'admin-view-booking.php',     // Completed
  'admin-manage-booking.php',   // Cancelled
  'admin-edit-booking.php',     // Edit screen should still light up Trips
];
$tripsActive = isAny($tripPages);
?>
<aside id="kayaSidebar" class="kaya-rail kaya-rail--light">
  <ul class="kaya-rail__nav">

    <li>
      <a class="kaya-rail__link <?= isActive('admin-dashboard.php') ?>" href="admin-dashboard.php">
        <i class="fas fa-tachometer-alt"></i><span>Dashboard</span>
      </a>
    </li>

    <!-- Trips (parent) -->
    <li>
      <a class="kaya-rail__link <?= $tripsActive ? 'is-active' : '' ?>" href="admin-trip-appointment.php">
        <i class="fas fa-book"></i><span>Trips</span>
      </a>
    </li>

    <li>
      <a class="kaya-rail__link <?= isActive('admin-manage-vehicle.php') ?>" href="admin-manage-vehicle.php">
        <i class="fas fa-bus"></i><span>Vehicles</span>
      </a>
    </li>

    <li>
      <a class="kaya-rail__link <?= isActive('admin-manage-driver.php') ?>" href="admin-manage-driver.php">
        <i class="fas fa-id-card"></i><span>Drivers</span>
      </a>
    </li>

    <?php if (is_superadmin_like()): ?>
      <li>
        <a class="kaya-rail__link <?= isActive('admin-manage-admins.php') ?>" href="admin-manage-admins.php">
          <i class="fas fa-user-shield"></i><span>Users</span>
        </a>
      </li>
    <?php endif; ?>

    <li>
      <a class="kaya-rail__link <?= isActive('admin-audit-logs.php') ?>" href="admin-audit-logs.php">
        <i class="fas fa-clipboard-check"></i><span>Audit Logs</span>
      </a>
    </li>

    <li>
      <a class="kaya-rail__link <?= isActive('admin-view-syslogs.php') ?>" href="admin-view-syslogs.php">
        <i class="fas fa-shield-alt"></i><span>Vehicle Telemetry</span>
      </a>
    </li>

    <li>
      <a class="kaya-rail__link <?= isActive('admin-reports.php') ?>" href="admin-reports.php">
        <i class="fas fa-comments"></i><span>Reports</span>
      </a>
    </li>
  </ul>

  <div class="kaya-rail__footer">
    <a class="btn btn-outline-danger btn-block kaya-logout" href="admin-logout.php">
      <i class="fas fa-sign-out-alt mr-1"></i><span>Logout</span>
    </a>
  </div>
</aside>

<style>
/* Optional: light styling for sublinks so they feel native */
.kaya-rail__subnav .kaya-rail__sublink {
  display:flex; align-items:center; gap:.5rem;
  padding:.35rem .5rem; border-radius:.375rem; color:#2d3559; text-decoration:none;
}
.kaya-rail__subnav .kaya-rail__sublink:hover { background:#f6f8ff; text-decoration:none; }
.kaya-rail__subnav .kaya-rail__sublink.is-active { background:#e9edff; font-weight:600; }
.kaya-rail__subnav i { width:1.1rem; text-align:center; }
</style>


