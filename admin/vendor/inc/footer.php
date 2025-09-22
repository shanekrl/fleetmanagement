 
 <footer class="sticky-footer">
    

     <!-- KAYA layout bootstrap (shared on all pages) -->
<script>
(function () {
  var MOBILE_MAX = 991;
  var body = document.body;

  // 1) Keep --kaya-nav-h in sync with the actual navbar height
  function setNavH(){
    var nav = document.querySelector('.navbar.kaya-white');
    if (!nav) return;
    var h = Math.round(nav.getBoundingClientRect().height);
    document.documentElement.style.setProperty('--kaya-nav-h', h + 'px');
  }
  setNavH();
  window.addEventListener('resize', setNavH);

  // 2) Single backdrop for the mobile drawer
  if (!document.querySelector('.kaya-backdrop')) {
    var b = document.createElement('div');
    b.className = 'kaya-backdrop';
    b.addEventListener('click', function(){ body.classList.remove('kaya-drawer-open'); });
    document.body.appendChild(b);
  }

  // 3) Clean any legacy SB-Admin classes
  body.classList.remove('sidebar-open','sidebar-toggled');
  var old = document.querySelector('.sidebar.kaya-sidebar');
  if (old) old.classList.remove('toggled');

  // 4) One toggle handler for every page
  function handleToggle(e){
    e.preventDefault();
    if (window.innerWidth <= MOBILE_MAX) body.classList.toggle('kaya-drawer-open');
    else body.classList.toggle('kaya-collapsed');
  }
  ['#sidebarToggle','#sidebarToggleTop'].forEach(function(sel){
    var btn = document.querySelector(sel);
    if (btn) {
      btn.removeEventListener('click', handleToggle);
      btn.addEventListener('click', handleToggle);
    }
  });
})();
</script>

<!-- KAYA layout bootstrap (shared) -->
<script>
(function () {
  var MOBILE_MAX = 991, body = document.body;

  function setNavH(){
    var nav = document.querySelector('.navbar.kaya-white');
    if (!nav) return;
    var h = Math.round(nav.getBoundingClientRect().height);
    document.documentElement.style.setProperty('--kaya-nav-h', h + 'px');
  }
  setNavH(); window.addEventListener('resize', setNavH);

  if (!document.querySelector('.kaya-backdrop')) {
    var b = document.createElement('div');
    b.className = 'kaya-backdrop';
    b.addEventListener('click', function(){ body.classList.remove('kaya-drawer-open'); });
    document.body.appendChild(b);
  }

  function handleToggle(e){
    e.preventDefault();
    if (window.innerWidth <= MOBILE_MAX) body.classList.toggle('kaya-drawer-open');
    else body.classList.toggle('kaya-collapsed');
  }

  ['#sidebarToggle','#sidebarToggleTop'].forEach(function(sel){
    var btn = document.querySelector(sel);
    if (btn){ btn.addEventListener('click', handleToggle); }
  });
})();
</script>

<script>
(function () {
  var btn = document.getElementById('sidebarToggle');
  if (!btn) return;

  btn.addEventListener('click', function (e) {
    e.preventDefault();

    // Toggle SB-Admin body class so other pages keep working
    document.body.classList.toggle('sidebar-toggled');

    // Also toggle a rail-specific collapsed class (belt & suspenders)
    var rail = document.getElementById('kayaSidebar');
    if (rail) rail.classList.toggle('kaya-rail--collapsed');

    // Optional: keep content margin in sync if you prefer a body flag
    document.body.classList.toggle('kaya-rail-collapsed');
  });

  // Keep the sidebar flush under the fixed navbar
  function syncNavH(){
    var nav = document.querySelector('.navbar.kaya-white');
    if (!nav) return;
    var h = Math.round(nav.getBoundingClientRect().height || 64);
    document.documentElement.style.setProperty('--kaya-nav-h', h + 'px');
  }
  syncNavH(); window.addEventListener('resize', syncNavH);
})();
</script>


 </footer>
 