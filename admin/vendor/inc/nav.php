<nav class="navbar navbar-expand navbar-light bg-white fixed-top kaya-white">
  <!-- Brand -->
  <a class="navbar-brand brand-kaya mr-3" href="admin-dashboard.php">KAYA</a>

  <!-- Right cluster: toggle + profile -->
  <div class="ml-auto d-flex align-items-center">
    <button class="btn btn-link btn-sm text-brand mr-2" id="sidebarToggle" type="button"
            aria-label="Toggle sidebar" aria-controls="kayaSidebar" aria-expanded="false">
      <i class="fas fa-bars"></i>
    </button>

    
    <ul class="navbar-nav">
      <li class="nav-item dropdown no-arrow mx-1" id="notifBell">
        <a class="nav-link dropdown-toggle" href="#" id="notifDropdown" role="button" data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-bell fa-fw"></i>
          <span class="badge badge-danger badge-counter" id="notifCount" style="display:none;">0</span>
        </a>
        <div class="dropdown-menu dropdown-menu-right shadow animated--grow-in" aria-labelledby="notifDropdown" style="min-width: 320px;">
          <h6 class="dropdown-header">Notifications</h6>
          <div id="notifList" style="max-height: 320px; overflow:auto;"></div>
          <!-- no "View trips" link here on purpose -->
        </div>
      </li>

      <li class="nav-item dropdown no-arrow">
        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-user-circle fa-fw"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
          <a class="dropdown-item" href="admin-profile.php">Profile</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="admin-logout.php">Logout</a>
        </div>
      </li>
    </ul>
  </div>
</nav>

