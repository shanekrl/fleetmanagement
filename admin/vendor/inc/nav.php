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
      <li class="nav-item dropdown no-arrow">
        <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
           data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
          <i class="fas fa-user-circle fa-fw"></i>
        </a>
        <div class="dropdown-menu dropdown-menu-right" aria-labelledby="userDropdown">
          <a class="dropdown-item" href="admin-profile.php">Profile</a>
          <div class="dropdown-divider"></div>
          <a class="dropdown-item" href="#" data-toggle="modal" data-target="#logoutModal">Logout</a>
        </div>
      </li>
    </ul>
  </div>
</nav>

