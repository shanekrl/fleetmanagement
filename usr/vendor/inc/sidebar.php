<?php
// vendor/inc/sidebar.php — Off-canvas sidebar (mobile-first) matching the prototype
$active = basename($_SERVER['PHP_SELF']);
function is_active($file){ global $active; return $active === $file ? 'bg-slate-100' : ''; }
?>
<!-- Overlay -->
<div id="sbOverlay" class="fixed inset-0 bg-black/30 hidden z-40"></div>

<!-- Off-canvas -->
<aside id="mobileSidebar"
       class="fixed z-50 inset-y-0 left-0 w-64 max-w-[80vw] bg-white shadow-2xl transform -translate-x-full transition-transform duration-200">
  <div class="h-full flex flex-col">
    <!-- Header -->
    <div class="h-14 flex items-center justify-between px-4 border-b border-slate-200">
      <span class="text-[18px] font-bold text-indigo-900">KAYA</span>
      <button id="btnCloseSidebar" class="p-2 rounded-lg hover:bg-slate-100" aria-label="Close menu">
  <i class="fas fa-times text-slate-700 text-[18px]"></i>
</button>
    </div>

    <!-- Nav items -->
    <nav class="px-3 py-3 flex-1">
      <a href="user-dashboard.php"
   class="flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] hover:bg-slate-50 <?php echo is_active('user-dashboard.php'); ?>">
  <i class="fas fa-th-large text-slate-600 text-[16px]"></i>
  <span>Dashboard</span>
</a>

      <a href="driver-trips.php"
   class="mt-1 flex items-center gap-3 px-3 py-2 rounded-xl text-[15px] hover:bg-slate-50 <?php echo is_active('driver-trips.php'); ?>">
  <i class="fas fa-road text-slate-600 text-[16px]"></i>
  <span>Trips</span>
</a>
    </nav>

    <!-- Footer: Sign out -->
    <div class="p-3 border-t border-slate-200">
      <form method="post" action="user-logout.php">
        <input type="hidden" name="logout" value="1">
        <button type="submit"
        class="w-full flex items-center justify-center gap-2 px-3 py-2 rounded-xl font-semibold text-red-600 border border-red-200 hover:bg-red-50">
  <i class="fas fa-sign-out-alt text-[16px]"></i>
  Sign Out
</button>
      </form>
    </div>
  </div>
</aside>

<!-- JS: open/close -->
<script>
  (function(){
    const openBtn = document.getElementById('btnOpenSidebar');
    const closeBtn = document.getElementById('btnCloseSidebar');
    const sb = document.getElementById('mobileSidebar');
    const ov = document.getElementById('sbOverlay');
    function open(){ sb.classList.remove('-translate-x-full'); ov.classList.remove('hidden'); }
    function close(){ sb.classList.add('-translate-x-full'); ov.classList.add('hidden'); }
    if(openBtn){ openBtn.addEventListener('click', open); }
    if(closeBtn){ closeBtn.addEventListener('click', close); }
    if(ov){ ov.addEventListener('click', close); }
    // Close on ESC
    document.addEventListener('keydown', e => { if(e.key === 'Escape') close(); });
  })();
</script>
