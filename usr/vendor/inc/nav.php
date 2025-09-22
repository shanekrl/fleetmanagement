<?php
// vendor/inc/nav.php
?>
<nav class="navbar navbar-expand navbar-dark bg-dark static-top">
  <!-- Brand -->
  <a class="navbar-brand mr-1" href="user-dashboard.php">Kaya</a>

  <!-- Sidebar toggle (SB Admin listens to this ID) -->
  <button class="btn btn-link btn-sm text-white order-1 order-sm-0" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Right side: profile dropdown -->
  <ul class="navbar-nav ml-auto ml-md-0">
    <li class="nav-item dropdown no-arrow">
      <a class="nav-link dropdown-toggle" href="#" id="driverUserDropdown" role="button"
         data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-user-circle fa-fw"></i>
      </a>

      <div class="dropdown-menu dropdown-menu-right" aria-labelledby="driverUserDropdown">
        <a class="dropdown-item" href="user-view-profile.php">Profile</a>
        <div class="dropdown-divider"></div>

        <!-- Logout as POST (works everywhere, no modal required) -->
        <form method="post" action="user-logout.php" class="px-3 m-0"
              onsubmit="return confirm('Log out now?');">
          <input type="hidden" name="logout" value="1">
          <button type="submit" class="dropdown-item btn btn-link p-0">Logout</button>
        </form>
      </div>
    </li>
  </ul>
</nav>
