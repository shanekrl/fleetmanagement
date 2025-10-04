<?php
// vendor/inc/nav.php — Tailwind mobile-first nav matching the driver prototype
?>
<header class="bg-white border-b border-slate-200">
  <div class="mx-auto max-w-screen-md px-4 h-14 flex items-center justify-between">
    <!-- Left: Brand -->
    <a href="user-dashboard.php" class="text-[20px] font-extrabold tracking-wide text-indigo-900">KAYA</a>

    <!-- Right: Icons -->
    <div class="flex items-center gap-3">
      <!-- Hamburger -->
      <button id="btnOpenSidebar" type="button" aria-label="Open menu"
        class="p-2 rounded-lg hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
  <i class="fas fa-bars text-slate-700 text-[18px]"></i>
</button>



      <!-- Profile dropdown -->
      <div class="relative">
        <button id="btnProfile" type="button" aria-haspopup="true" aria-expanded="false"
        class="p-2 rounded-full hover:bg-slate-100 focus:outline-none focus:ring-2 focus:ring-indigo-500">
  <i class="fas fa-user-circle text-slate-700 text-[20px]"></i>
</button>

        <!-- menu -->
        <div id="profileMenu"
             class="hidden absolute right-0 mt-2 w-44 rounded-xl border border-slate-200 bg-white shadow-xl z-40">
          <div class="px-4 py-3">
            <p class="text-xs text-slate-500">Signed in as</p>
            <p class="mt-0.5 text-sm font-semibold text-slate-900">
              <?php
                $nm = 'Driver';
                if (!empty($_SESSION['u_id'])) {
                  if ($s=$mysqli->prepare("SELECT CONCAT(u_fname,' ',u_lname) FROM tms_user WHERE u_id=? LIMIT 1")) {
                    $s->bind_param('i', $_SESSION['u_id']); $s->execute(); $s->bind_result($x);
                    if ($s->fetch() && trim($x)!=='') $nm = $x; $s->close();
                  }
                }
                echo htmlspecialchars($nm);
              ?>
            </p>
          </div>
          <div class="py-1">
            <a href="user-view-profile.php" class="block px-4 py-2 text-sm hover:bg-slate-50">Profile</a>
          </div>
          <div class="border-t border-slate-200"></div>
          <form method="post" action="user-logout.php" class="p-2">
            <input type="hidden" name="logout" value="1">
            <button type="submit"
                    class="w-full px-3 py-2 text-left text-sm font-semibold text-red-600 hover:bg-red-50 rounded-lg">
              Sign Out
            </button>
          </form>
        </div>
      </div>
    </div>
  </div>
</header>

<!-- tiny JS for dropdown -->
<script>
  (function(){
    const btn = document.getElementById('btnProfile');
    const menu = document.getElementById('profileMenu');
    if(btn && menu){
      btn.addEventListener('click', e => {
        e.stopPropagation(); menu.classList.toggle('hidden');
      });
      document.addEventListener('click', e => { if(!menu.classList.contains('hidden')) menu.classList.add('hidden'); });
    }
  })();
</script>
