 
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

<!-- notifs -->
<script>
(function() {
  // avoid double init
  if (window.__kayaNotifInit) return; window.__kayaNotifInit = true;

  const list  = document.getElementById('notifList');
  const badge = document.getElementById('notifCount');
  if (!list || !badge) return;

  function setCount(n){
    if (n > 0) { badge.textContent = n; badge.style.display = ''; }
    else { badge.style.display = 'none'; }
  }

  let unseen = 0;

  function addItem(n) {
    const a = document.createElement('a');
    a.className = 'dropdown-item d-flex align-items-start';
    a.href = n.url || '#';
    a.addEventListener('click', function(e){
      e.preventDefault();
      // mark as seen locally and navigate
      unseen = Math.max(0, unseen - 1);
      setCount(unseen);
      window.location.href = n.url;
    });

    const icon = document.createElement('div');
    icon.className = 'mr-3';
    icon.innerHTML = '<div class="icon-circle bg-primary" style="width:36px;height:36px;display:flex;align-items:center;justify-content:center;border-radius:50%;"><i class="fas fa-car text-white"></i></div>';

    const txt = document.createElement('div');
    txt.innerHTML = '<div class="small text-gray-500">' + (n.created_at || '') + '</div><span class="font-weight-bold">' + (n.title || 'New notification') + '</span>';

    a.appendChild(icon); a.appendChild(txt);
    list.prepend(a);
    unseen++; setCount(unseen);
  }

  // EventSource to /admin/notifications-stream.php
  let es;
  try {
    es = new EventSource('notifications-stream.php');
  } catch (e) { return; }

  es.onmessage = function(ev){
    // generic handler
    try { const n = JSON.parse(ev.data); addItem(n); } catch (_) {}
  };

  // named event (e.g., 'booking_created')
  es.addEventListener('booking_created', function(ev){
    try { const n = JSON.parse(ev.data); addItem(n); } catch (_) {}
  });

  es.onerror = function() {
    // allow browser to auto-reconnect; nothing else needed
  };
})();
</script>



 </footer>
 