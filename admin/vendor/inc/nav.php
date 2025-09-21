<nav class="navbar navbar-expand navbar-dark">
  <!-- Brand -->
  <a class="navbar-brand mr-2" href="user-dashboard.php">Kaya</a>

  <!-- Sidebar Toggle (left) -->
  <button class="btn btn-link btn-sm text-white" id="sidebarToggle" type="button" aria-label="Toggle sidebar">
    <i class="fas fa-bars"></i>
  </button>

  <!-- Spacer -->
  <div class="ml-auto d-none d-sm-block"></div>

  <!-- Right: user menu -->
  <ul class="navbar-nav ml-auto">
    <li class="nav-item dropdown no-arrow">
      <a class="nav-link dropdown-toggle" href="#" id="userDropdown" role="button"
         data-toggle="dropdown" aria-haspopup="true" aria-expanded="false">
        <i class="fas fa-user-circle fa-fw"></i>
      </a>
      <div class="dropdown-menu dropdown-menu-right shadow" aria-labelledby="userDropdown">
        <a class="dropdown-item" href="user-view-profile.php">Profile</a>
        <div class="dropdown-divider"></div>
        <a class="dropdown-item text-danger" href="#" data-toggle="modal" data-target="#logoutModal">Logout</a>
      </div>
    </li>
  </ul>
</nav>

<!-- Ensure the toggle always works even if SB Admin auto-init misses -->
<script>
  (function(){
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    btn.addEventListener('click', function(){
      document.body.classList.toggle('sidebar-toggled');
      var sb = document.querySelector('.sidebar');
      if (sb) sb.classList.toggle('toggled');
    });
  })();
</script>

<script>
  // remove unwanted driver-side items even if the old sidebar file loads
  (function(){
    const kill = [
      'usr-book-vehicle.php',      // Vehicles -> Book
      'user-view-booking.php',     // Bookings -> View
      'user-manage-booking.php',   // Bookings -> Manage
      'user-give-feedback.php'     // Feedbacks
    ];
    kill.forEach(href => {
      document.querySelectorAll('.sidebar .nav-link[href*="'+href+'"]')
        .forEach(a => { const li = a.closest('.nav-item'); if (li) li.remove(); });
    });
    // also remove the Bookings/Vehicles dropdown toggles if empty
    document.querySelectorAll('.sidebar .nav-item.dropdown').forEach(d=>{
      if(!d.querySelector('.dropdown-menu a')) d.remove();
    });
  })();
</script>

