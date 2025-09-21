<?php
// KAYA · Trip Appointments (List first, Calendar below)
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$isAdmin = function_exists('is_admin') ? is_admin() : isset($_SESSION['a_id']);
$aid     = (int)($_SESSION['a_id'] ?? 0);

/* ----- safety: avoid collation warnings ----- */
$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET collation_connection='utf8mb4_unicode_ci'");

/* ----- helpers ----- */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function badge_for($s){
  $s = strtolower((string)$s);
  switch ($s) {
    case 'pending':         return ['badge badge-light',   'Pending'];
    case 'awaiting_driver': return ['badge badge-info',    'Awaiting Driver'];
    case 'accepted':        return ['badge badge-success', 'Accepted'];
    case 'in_progress':     return ['badge badge-primary', 'In Progress'];
    case 'rejected':        return ['badge badge-warning', 'Rejected'];
    case 'cancelled':       return ['badge badge-danger',  'Cancelled'];
    case 'completed':       return ['badge badge-success', 'Completed'];
    default:                return ['badge badge-secondary', ucfirst($s)];
  }
}

/* ----- who is the driver (if not admin) ----- */
$currentDriverId = null;
if (!$isAdmin && isset($_SESSION['u_id'])) {
  if ($q = $mysqli->prepare("SELECT u_email FROM tms_user WHERE u_id=? LIMIT 1")) {
    $uid = (int)$_SESSION['u_id'];
    $q->bind_param('i',$uid);
    $q->execute(); $q->bind_result($em); $q->fetch(); $q->close();
    if ($em) {
      if (table_exists($mysqli,'accounts')) {
        if ($d = $mysqli->prepare("SELECT id FROM accounts WHERE email=? AND role='driver' LIMIT 1")) {
          $d->bind_param('s',$em); $d->execute(); $d->bind_result($did); if ($d->fetch()) $currentDriverId = (int)$did; $d->close();
        }
      }
      if ($currentDriverId===null && table_exists($mysqli,'tms_user_add_driver')) {
        if ($d = $mysqli->prepare("SELECT d_u_id FROM tms_user_add_driver WHERE u_email=? LIMIT 1")) {
          $d->bind_param('s',$em); $d->execute(); $d->bind_result($did); if ($d->fetch()) $currentDriverId = (int)$did; $d->close();
        }
      }
    }
  }
}

/* ----- get upcoming rows (new -> legacy) ----- */
$rows = [];
if (table_exists($mysqli,'v_booking_grid')) {
  $sql = "SELECT booking_id, scheduled_at, created_at, client_name, pax,
                 pickup, dropoff, vehicle_reg_no, booking_type, driver_name,
                 status, driver_id
          FROM v_booking_grid
          WHERE status IN ('pending','awaiting_driver','accepted','in_progress')
          ".(!$isAdmin && $currentDriverId!==null ? "AND driver_id=".(int)$currentDriverId : "")."
          ORDER BY COALESCE(scheduled_at, created_at) ASC, booking_id ASC";
  if ($res = $mysqli->query($sql)) while($r=$res->fetch_assoc()) $rows[]=$r;

} elseif (table_exists($mysqli,'bookings')) {
  $sql = "SELECT b.id AS booking_id,
                 COALESCE(b.scheduled_start_at, b.created_at) AS scheduled_at,
                 b.created_at,
                 COALESCE(c.name,'') AS client_name,
                 b.pax,
                 b.pickup_point  AS pickup,
                 b.dropoff_point AS dropoff,
                 tv.v_reg_no     AS vehicle_reg_no,
                 b.booking_type,
                 d.name          AS driver_name,
                 b.status,
                 b.driver_id
          FROM bookings b
          LEFT JOIN accounts c   ON c.id=b.client_id
          LEFT JOIN accounts d   ON d.id=b.driver_id
          LEFT JOIN tms_vehicle tv ON tv.v_id=b.vehicle_id
          WHERE b.status IN ('pending','awaiting_driver','accepted','in_progress')
          ".(!$isAdmin && $currentDriverId!==null ? "AND b.driver_id=".(int)$currentDriverId : "")."
          ORDER BY COALESCE(b.scheduled_start_at, b.created_at) ASC, b.id ASC";
  if ($res = $mysqli->query($sql)) while($r=$res->fetch_assoc()) $rows[]=$r;

} elseif (table_exists($mysqli,'tms_user')) {
  $sql = "SELECT u_id AS booking_id,
                 FROM_UNIXTIME(NULLIF(u_car_createdat,0)) AS created_at,
                 NULL AS scheduled_at,
                 CONCAT(COALESCE(u_fname,''),' ',COALESCE(u_lname,'')) AS client_name,
                 NULLIF(u_car_pax,'') AS pax,
                 u_car_pickup  AS pickup,
                 u_car_destination AS dropoff,
                 u_car_regno   AS vehicle_reg_no,
                 'admin'       AS booking_type,
                 u_car_driver  AS driver_name,
                 CASE WHEN u_car_book_status='Approved' THEN 'accepted' ELSE 'pending' END AS status,
                 NULL AS driver_id
          FROM tms_user
          WHERE u_car_book_status IN ('Pending','Approved')
          ORDER BY u_id ASC";
  if ($res = $mysqli->query($sql)) while($r=$res->fetch_assoc()) $rows[]=$r;
}

define('ACTION_ENDPOINT', 'booking_actions.php');
?>
<!DOCTYPE html>
<html lang="en">
  <style>
      .kaya-page-title{font-weight:800;font-size:2rem;line-height:1.1;color:#000047;margin:0 0 1rem}

      /* REVERTED: outline-only tabs (no fill even when active) */
      .btn.kaya-tab { background:#fff; border:1px solid #000047; color:#000047; }
      .btn-group .btn.kaya-tab.active,
      .btn.kaya-tab.active,
      .btn.kaya-tab:active,
      .btn.kaya-tab:focus {
        background:#fff !important;
        color:#000047 !important;
        border:1px solid #000047 !important;
        box-shadow:none !important;
        background-image:none !important;
      }

      .fc .fc-toolbar-title { font-weight:800; color:#000047; }
      #kayaCalendar { min-height:520px; }
    </style>

    <style>
    /* Keep outline look for both */
    .btn.kaya-tab { background:#fff; border:1px solid #bfc6da; color:#0a0e2a; }

    /* Subdue the NON-active tab (Completed on this page) */
    .btn-group .btn.kaya-tab:not(.active){
      border-color:#c7cfdf;
      color:#0a0e2a99;   /* softer text */
      background:#fff;
      box-shadow:none;
    }
    .btn-group .btn.kaya-tab:not(.active):hover{
      border-color:#9aa3bd;
      color:#0a0e2a;     /* sharpen on hover */
      background:#f6f8ff;
    }

    /* Make the active tab (Upcoming) stand out a bit more */
    .btn-group .btn.kaya-tab.active{
      border-color:#000047 !important;
      color:#000047 !important;
      background:#fff !important;
      box-shadow: inset 0 -2px 0 #000047; /* subtle underline */
    }
  </style>
<style>
  /* Same text color for both tabs */
  .btn-group .btn.kaya-tab,
  .btn-group .btn.kaya-tab:not(.active),
  .btn.kaya-tab {
    color: #000047 !important;   /* force normal text */
    opacity: 1 !important;       /* cancel any dimming */
  }

  /* Borders: dark for active, gray for non-active */
  .btn-group .btn.kaya-tab.active {
    border: 1px solid #000047 !important;
    background: #fff !important;
  }
  .btn-group .btn.kaya-tab:not(.active) {
    border: 1px solid #d9deee !important;   /* only the border is grayed out */
    background: #fff !important;
  }
  .btn-group .btn.kaya-tab:not(.active):hover {
    border-color: #b9c2dd !important;
    background: #f6f8ff !important;
  }
</style>



<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>

<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>

  <div id="content-wrapper">
    <div class="container-fluid">

      <h1 class="kaya-page-title">Trip Appointments</h1>

      <div class="kaya-toolbar d-flex align-items-center mb-3" style="gap:.5rem;flex-wrap:wrap;">
        <div class="btn-group" role="group" aria-label="Filters">
          <!-- keep .active for routing; CSS ensures outline-only look -->
          <a href="admin-trip-appointment.php" class="btn kaya-tab active">Upcoming</a>
          <a href="admin-view-booking.php"   class="btn kaya-tab">Completed</a>
        </div>
        <div class="kaya-actions ml-auto btn-group" role="group" aria-label="Actions" style="flex-wrap:nowrap;gap:.5rem;">
          <a href="admin-create-booking.php" class="btn btn-kaya-primary">New Trip</a>
          <a href="admin-manage-booking.php" class="btn btn-kaya-danger-outline">Cancelled</a>
        </div>
      </div>

      <!-- ===== Upcoming List (kept as-is) ===== -->
      <div class="kaya-card mb-4" id="upcoming-list">
        <div class="table-responsive px-2">
          <table id="dataTable" class="kaya-table table table-borderless">
            <thead>
              <tr>
                <th>#</th>
                <th>Date</th>
                <th>Time</th>
                <th>Customer</th>
                <th>Pax</th>
                <th>Pick Up</th>
                <th>Destination</th>
                <th>Reg No.</th>
                <th>Type</th>
                <th>Driver</th>
                <th>Status</th>
                <th class="actions">Actions</th>
              </tr>
            </thead>
            <tbody>
              <?php $n=1; foreach ($rows as $r):
                $dt   = $r['scheduled_at'] ?: $r['created_at'];
                $date = $dt ? date('M j, Y', strtotime($dt)) : '';
                $time = $dt ? date('h:i A', strtotime($dt)) : '';
                [$chipClass,$chipText] = badge_for($r['status']);

                $isMine = (!$isAdmin && $r['driver_id']!==null && (int)$r['driver_id']===(int)$currentDriverId);

                // Admin perms
                $canAdminApprove  = $isAdmin && in_array(strtolower($r['status']),['pending','awaiting_driver']);
                $canAdminComplete = $isAdmin && in_array(strtolower($r['status']),['accepted','in_progress']);
                $canAdminCancel   = $isAdmin && in_array(strtolower($r['status']),['pending','awaiting_driver','accepted','in_progress']);

                // Driver perms
                $canDriverAccept  = !$isAdmin && $isMine && in_array(strtolower($r['status']),['pending','awaiting_driver']);
                $canDriverDecline = !$isAdmin && $isMine && in_array(strtolower($r['status']),['pending','awaiting_driver']);
                $canDriverStart   = !$isAdmin && $isMine && strtolower($r['status'])==='accepted';
                $canDriverDrop    = !$isAdmin && $isMine && strtolower($r['status'])==='in_progress';
              ?>
              <tr data-date="<?= htmlspecialchars($dt ? date('Y-m-d', strtotime($dt)) : '') ?>">
                <td><?= $n++ ?></td>
                <td><?= htmlspecialchars($date) ?></td>
                <td><?= htmlspecialchars($time) ?></td>
                <td><?= htmlspecialchars($r['client_name'] ?? '') ?></td>
                <td><?= (int)($r['pax'] ?? 1) ?></td>
                <td><?= htmlspecialchars($r['pickup'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['dropoff'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['vehicle_reg_no'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['booking_type'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['driver_name'] ?? '') ?></td>
                <td><span class="<?= $chipClass ?> px-2 py-1"><?= $chipText ?></span></td>
                <td class="actions" style="white-space:nowrap;">
                  <?php if ($isAdmin): ?>
                    <a class="btn btn-sm btn-outline-secondary"
                       href="admin-edit-booking.php?booking_id=<?= (int)$r['booking_id'] ?>"
                       title="Edit"><i class="fas fa-pen"></i></a>
                  <?php endif; ?>

                  <?php if ($canAdminApprove): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline">
                      <input type="hidden" name="action" value="admin_approve">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <button class="btn btn-sm btn-outline-success" title="Approve"><i class="fas fa-check"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canAdminComplete): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline"
                          onsubmit="return confirm('Mark this trip as Completed?');">
                      <input type="hidden" name="action" value="admin_complete">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" title="Complete"><i class="fas fa-check-circle"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canAdminCancel): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline"
                          onsubmit="return confirm('Cancel this booking?');">
                      <input type="hidden" name="action" value="admin_cancel">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <button class="btn btn-sm btn-outline-danger" title="Cancel"><i class="fas fa-ban"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canDriverAccept): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline">
                      <input type="hidden" name="action" value="driver_accept">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <button class="btn btn-sm btn-outline-success" title="Accept"><i class="fas fa-thumbs-up"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canDriverDecline): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline driver-decline-form">
                      <input type="hidden" name="action" value="driver_decline">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <input type="hidden" name="reason" value="">
                      <button class="btn btn-sm btn-outline-warning" title="Decline"><i class="fas fa-thumbs-down"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canDriverStart): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline"
                          onsubmit="return confirm('Start trip? Record PICKUP time.');">
                      <input type="hidden" name="action" value="trip_start">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <button class="btn btn-sm btn-outline-primary" title="Start"><i class="fas fa-play"></i></button>
                    </form>
                  <?php endif; ?>

                  <?php if ($canDriverDrop): ?>
                    <form method="post" action="<?= ACTION_ENDPOINT ?>" class="d-inline"
                          onsubmit="return confirm('End trip? Record DROPOFF and complete.');">
                      <input type="hidden" name="action" value="trip_end">
                      <input type="hidden" name="id"     value="<?= (int)$r['booking_id'] ?>">
                      <button class="btn btn-sm btn-outline-success" title="Dropoff"><i class="fas fa-flag-checkered"></i></button>
                    </form>
                  <?php endif; ?>
                </td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>

      <!-- ===== Calendar BELOW the list ===== -->
      <div class="kaya-card" id="calendar-card">
        <div class="d-flex align-items-center mb-2" style="gap:.5rem;flex-wrap:wrap;">
          <h5 class="mb-0">Calendar</h5>
          <div class="ml-auto d-flex align-items-center" style="gap:.5rem;">
            <label for="kayaStatusFilter" class="mb-0 mr-1 small text-muted">Status</label>
            <select id="kayaStatusFilter" class="form-control form-control-sm">
              <option value="">All statuses</option>
              <option value="pending">Pending</option>
              <option value="awaiting_driver">Awaiting Driver</option>
              <option value="accepted">Accepted</option>
              <option value="in_progress">In Progress</option>
              <option value="rejected">Rejected</option>
              <option value="cancelled">Cancelled</option>
              <option value="completed">Completed</option>
            </select>
            <?php if (!$isAdmin && $currentDriverId !== null): ?>
              <input type="hidden" id="kayaDriverId" value="<?= (int)$currentDriverId ?>">
            <?php endif; ?>
          </div>
        </div>
        <div id="kayaCalendar"></div>
      </div>

    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<!-- JS -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.js"></script>
<script src="vendor/js/sb-admin.min.js"></script>

<!-- FullCalendar (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>

<script>
  // DataTable for the list
  $('#dataTable').DataTable({
    pageLength: 10,
    order: [[0,'asc']],
    columnDefs: [{ targets: -1, orderable:false, searchable:false }]
  });

  // FullCalendar init (below the list)
  let calendar;
  (function initCalendar(){
    const calEl = document.getElementById('kayaCalendar');
    if (!calEl) return;

    const statusSel   = document.getElementById('kayaStatusFilter');
    const driverInput = document.getElementById('kayaDriverId');

    calendar = new FullCalendar.Calendar(calEl, {
      initialView: (window.innerWidth < 768) ? 'listWeek' : 'dayGridMonth',
      height: 'auto',
      expandRows: true,
      headerToolbar: {
        left: 'prev,next today',
        center: 'title',
        right: 'dayGridMonth,timeGridWeek,timeGridDay,listWeek'
      },
      nowIndicator: true,
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: true },
      events: function(fetchInfo, success, failure) {
        const params = new URLSearchParams();
        params.set('start', fetchInfo.startStr);
        params.set('end',   fetchInfo.endStr);
        const s = (statusSel && statusSel.value) ? statusSel.value : '';
        if (s) params.append('status[]', s);
        if (driverInput && driverInput.value) params.set('driver_id', driverInput.value);

        // Adjust path if this page is inside /admin/: use '../api/calendar-events.php'
        fetch('api/calendar-events.php?' + params.toString(), { credentials: 'same-origin' })
          .then(r => r.json())
          .then(data => success(data))
          .catch(err => failure(err));
      },
      eventClick: function(info) {
        const id = info.event.id;
        window.location.href = 'admin-edit-booking.php?booking_id=' + encodeURIComponent(id);
      },
      eventDidMount: function(info){
        const p = info.event.extendedProps || {};
        info.el.setAttribute('title',
          (info.event.title || '') +
          (p.status ? ('\nStatus: ' + p.status) : '')
        );
      }
    });

    calendar.render();

    // Filter change -> refetch
    if (statusSel) statusSel.addEventListener('change', () => calendar.refetchEvents());

    // Optional UX: clicking a row scrolls calendar to that date
    document.querySelectorAll('#upcoming-list tbody tr[data-date]').forEach(function(row){
      row.addEventListener('click', function(){
        const d = row.getAttribute('data-date');
        if (d && calendar) {
          calendar.gotoDate(d);
          document.getElementById('calendar-card').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
      });
    });
  })();

  // sidebar sizing (unchanged)
  (function () {
    var btn = document.getElementById('sidebarToggle');
    if (!btn) return;
    btn.addEventListener('click', function (e) {
      e.preventDefault();
      document.body.classList.toggle('sidebar-toggled');
      var rail = document.getElementById('kayaSidebar');
      if (rail) rail.classList.toggle('kaya-rail--collapsed');
    });
    function syncNavH(){
      var nav = document.querySelector('.navbar.kaya-white');
      if (!nav) return;
      var h = Math.round(nav.getBoundingClientRect().height || 64);
      document.documentElement.style.setProperty('--kaya-nav-h', h + 'px');
    }
    syncNavH(); window.addEventListener('resize', syncNavH);
  })();
</script>

</body>
</html>
