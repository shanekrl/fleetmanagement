<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET collation_connection='utf8mb4_unicode_ci'");

/* ---------------- Helpers ---------------- */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function badge_for($s){
  $s = strtolower((string)$s);
  if ($s==='completed') return ['badge badge-success','Completed'];
  if ($s==='cancelled') return ['badge badge-danger','Cancelled'];
  return ['badge badge-secondary', ucfirst($s ?: 'status')];
}

/* ---------------- Data ---------------- */
$rows = [];

if (table_exists($mysqli,'bookings')) {

  // vehicle join (new preferred, fallback if needed)
  if (table_exists($mysqli,'tms_vehicle')) {
    $vehicleSelect = "tv.v_reg_no AS vehicle_reg_no";
    $vehicleJoin   = "LEFT JOIN tms_vehicle tv ON tv.v_id = b.vehicle_id";
  } elseif (table_exists($mysqli,'vehicles')) {
    $vehicleSelect = "v.plate_no AS vehicle_reg_no";
    $vehicleJoin   = "LEFT JOIN vehicles v ON v.id = b.vehicle_id";
  } else {
    $vehicleSelect = "NULL AS vehicle_reg_no";
    $vehicleJoin   = "";
  }

  $sql = "SELECT b.id AS booking_id,
                 COALESCE(b.scheduled_start_at, b.created_at) AS scheduled_at,
                 b.created_at,
                 COALESCE(c.name,'') AS client_name,
                 b.pax,
                 b.pickup_point  AS pickup,
                 b.dropoff_point AS dropoff,
                 {$vehicleSelect},
                 b.booking_type,
                 d.name          AS driver_name,
                 b.status
          FROM bookings b
          LEFT JOIN accounts c ON c.id=b.client_id
          LEFT JOIN accounts d ON d.id=b.driver_id
          {$vehicleJoin}
          WHERE b.status='completed'
          ORDER BY COALESCE(b.scheduled_start_at, b.created_at) DESC, b.id DESC";
  if ($res = $mysqli->query($sql)) while($r=$res->fetch_assoc()) $rows[]=$r;

} elseif (table_exists($mysqli,'v_booking_grid')) {
  $sql = "SELECT booking_id, scheduled_at, created_at, client_name, pax,
                 pickup, dropoff, vehicle_reg_no, booking_type, driver_name, status
          FROM v_booking_grid
          WHERE status='completed'
          ORDER BY COALESCE(scheduled_at, created_at) DESC, booking_id DESC";
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
                 'completed'   AS status
          FROM tms_user
          WHERE u_car_book_status='Completed'
          ORDER BY u_id DESC";
  if ($res = $mysqli->query($sql)) while($r=$res->fetch_assoc()) $rows[]=$r;
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">

<style>
  html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
</style>
<style>
  /* Sidebar normalize to match other pages */
  #kayaSidebar{ width:14.5rem; } body.sidebar-toggled #kayaSidebar{ width:6rem; }
  #kayaSidebar .nav-link{ font-size:.95rem; line-height:1.25rem; } 
  #kayaSidebar .nav-item .nav-link i { font-size:1rem; }
  .sidebar{ max-width:14.5rem; }

  .btn.kaya-tab { background:#fff; border:1px solid #bfc6da; color:#000047; }
  .btn-group .btn.kaya-tab.active{
    border-color:#000047 !important; color:#000047 !important; background:#fff !important;
    box-shadow: inset 0 -2px 0 #000047;
  }
  .btn-group .btn.kaya-tab:not(.active){ border-color:#d9deee !important; background:#fff !important; }
  .btn-group .btn.kaya-tab:not(.active):hover{ border-color:#b9c2dd !important; background:#f6f8ff !important; }
</style>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>

  <div id="content-wrapper">
    <div class="container-fluid">

      <h1 class="kaya-page-title">Trip Appointments</h1>

      <div class="kaya-toolbar d-flex align-items-center mb-3" style="gap:.5rem;flex-wrap:wrap;">
        <!-- LEFT: filters group -->
        <div class="btn-group" role="group" aria-label="Filters">
          <a href="admin-trip-appointment.php" class="btn kaya-tab">Upcoming</a>
          <a href="admin-view-booking.php"   class="btn kaya-tab active">Completed</a>
          <a href="admin-manage-booking.php" class="btn kaya-tab">Cancelled</a>
        </div>
        <!-- RIGHT: actions -->
        <div class="kaya-actions ml-auto btn-group" role="group" aria-label="Actions" style="flex-wrap:nowrap;gap:.5rem;">
          <a href="admin-trip-appointment.php#new" class="btn btn-kaya-primary">
            <i class="fas fa-plus mr-1"></i> New Trip
          </a>
        </div>
      </div>

      <div class="kaya-card">
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
              <?php $i=1; foreach($rows as $r):
                $dt   = $r['scheduled_at'] ?: $r['created_at'];
                $date = $dt ? date('M j, Y', strtotime($dt)) : '';
                $time = $dt ? date('h:i A', strtotime($dt)) : '';
                [$cls,$txt] = badge_for($r['status'] ?? '');
              ?>
              <tr>
                <td><?= $i++ ?></td>
                <td><?= htmlspecialchars($date) ?></td>
                <td><?= htmlspecialchars($time) ?></td>
                <td><?= htmlspecialchars($r['client_name'] ?? '') ?></td>
                <td><?= (int)($r['pax'] ?? 1) ?></td>
                <td><?= htmlspecialchars($r['pickup'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['dropoff'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['vehicle_reg_no'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['booking_type'] ?? '') ?></td>
                <td><?= htmlspecialchars($r['driver_name'] ?? '') ?></td>
                <td><span class="<?= $cls ?> px-2 py-1"><?= $txt ?></span></td>
                <td class="actions" style="white-space:nowrap;"></td>
              </tr>
              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
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
<script>
  $('#dataTable').DataTable({
    pageLength: 10,
    order: [[0,'asc']],
    columnDefs: [{ targets: -1, orderable:false, searchable:false }]
  });
</script>
</body>
</html>
