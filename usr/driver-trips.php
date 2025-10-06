<?php
// usr/driver-trips.php — Driver Trips (Completed / Cancelled) + Direct Booking modal + location picker
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$driverAccountId = require_driver();

/* ---------- helpers ---------- */
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function column_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $r && $r->num_rows > 0;
}
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES,'UTF-8'); }
function fmt_when($dt){
  if (!$dt) return '—';
  $ts = strtotime($dt);
  return date('M j, Y • g:i A', $ts);
}

/* ---------- AJAX: Create Direct Booking ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_create_direct'])) {
  header('Content-Type: application/json; charset=utf-8');

  $sched_date  = trim($_POST['sched_date'] ?? '');
  $sched_time  = trim($_POST['sched_time'] ?? '');
  $scheduled   = ($sched_date && $sched_time) ? ($sched_date.' '.$sched_time.(strlen($sched_time)>5?'':':00')) : null;

  $customer    = trim($_POST['customer'] ?? '');
  $phone       = trim($_POST['phone'] ?? '');
  $pax         = max(1, (int)($_POST['pax'] ?? 1));
  $pickup      = trim($_POST['pickup'] ?? '');
  $dropoff     = trim($_POST['dropoff'] ?? '');

  // coords from hidden inputs (can be null)
  $pickup_lat   = ($_POST['pickup_lat']  !== '' ? (float)$_POST['pickup_lat']  : null);
  $pickup_lng   = ($_POST['pickup_lng']  !== '' ? (float)$_POST['pickup_lng']  : null);
  $dropoff_lat  = ($_POST['dropoff_lat'] !== '' ? (float)$_POST['dropoff_lat'] : null);
  $dropoff_lng  = ($_POST['dropoff_lng'] !== '' ? (float)$_POST['dropoff_lng'] : null);

  if (!$driverAccountId) { echo json_encode(['ok'=>0,'error'=>'No driver.']); exit; }
  if (!$customer || !$pickup || !$dropoff) { echo json_encode(['ok'=>0,'error'=>'Please fill out required fields.']); exit; }

  // Try to auto-find the vehicle assigned to this driver
  $vehicleId = null;
  if (table_exists($mysqli,'v_vehicle_current_driver')) {
    if ($q = $mysqli->prepare("SELECT v_id FROM v_vehicle_current_driver WHERE driver_account_id=? LIMIT 1")) {
      $q->bind_param('i',$driverAccountId); $q->execute(); $q->bind_result($vid);
      if ($q->fetch()) $vehicleId = (int)$vid; $q->close();
    }
  }
  if ($vehicleId===null && table_exists($mysqli,'tms_vehicle') && column_exists($mysqli,'tms_vehicle','default_driver_id')) {
    if ($q = $mysqli->prepare("SELECT v_id FROM tms_vehicle WHERE default_driver_id=? LIMIT 1")) {
      $q->bind_param('i',$driverAccountId); $q->execute(); $q->bind_result($vid);
      if ($q->fetch()) $vehicleId = (int)$vid; $q->close();
    }
  }

  try {
    $sql = "INSERT INTO bookings
            (booking_type, created_by, client_id, driver_id, vehicle_id,
             pax, contact_name, contact_phone,
             pickup_point, dropoff_point,
             pickup_lat, pickup_lng, dropoff_lat, dropoff_lng,
             scheduled_start_at, status, notes)
            VALUES ('personal', ?, NULL, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'accepted', 'Created by driver')";
    if ($st = $mysqli->prepare($sql)) {
      $st->bind_param(
        'iiiissssdddds',
        $driverAccountId,         // created_by
        $driverAccountId,         // driver_id
        $vehicleId,               // vehicle_id
        $pax,
        $customer, $phone,
        $pickup,  $dropoff,
        $pickup_lat, $pickup_lng, $dropoff_lat, $dropoff_lng,
        $scheduled
      );
      $ok = $st->execute(); $id = $st->insert_id; $st->close();
      echo json_encode(['ok'=>$ok?1:0,'id'=>$id]); exit;
    } else {
      echo json_encode(['ok'=>0,'error'=>'Prepare failed']); exit;
    }
  } catch (Throwable $e) {
    echo json_encode(['ok'=>0,'error'=>$e->getMessage()]); exit;
  }
}

/* ---------- POST: Restore a cancelled personal booking ---------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['restore_personal'])) {
  $bid = (int)($_POST['booking_id'] ?? 0);
  if ($bid && $driverAccountId) {
    if ($st = $mysqli->prepare("UPDATE bookings
                                   SET status='accepted', updated_at=NOW()
                                 WHERE id=? AND booking_type='personal'
                                   AND created_by=? AND status='cancelled'")) {
      $st->bind_param('ii',$bid,$driverAccountId);
      $st->execute(); $st->close();
    }
  }
  header('Location: driver-trips.php'); exit;
}

/* ---------- Fetch Completed & Cancelled ---------- */
$completed = $cancelled = [];
if ($driverAccountId) {
  $sqlC = "
    SELECT id AS booking_id, booking_type, created_by, driver_id, vehicle_id, pax,
           COALESCE(contact_name,'') AS client_name, contact_phone,
           pickup_point AS pickup, dropoff_point AS dropoff,
           COALESCE(scheduled_start_at, created_at) AS when_at,
           status, (SELECT v_reg_no FROM tms_vehicle WHERE v_id=vehicle_id) AS vehicle_reg_no
      FROM bookings
     WHERE status='completed'
       AND (driver_id=? OR (booking_type='personal' AND created_by=?))
     ORDER BY COALESCE(scheduled_start_at, created_at) DESC, id DESC";
  if ($st=$mysqli->prepare($sqlC)) {
    $st->bind_param('ii',$driverAccountId,$driverAccountId);
    $st->execute(); $r=$st->get_result(); while($row=$r->fetch_assoc()) $completed[]=$row; $st->close();
  }

  $sqlX = "
    SELECT id AS booking_id, booking_type, created_by, driver_id, vehicle_id, pax,
           COALESCE(contact_name,'') AS client_name, contact_phone,
           pickup_point AS pickup, dropoff_point AS dropoff,
           COALESCE(scheduled_start_at, created_at) AS when_at,
           status, (SELECT v_reg_no FROM tms_vehicle WHERE v_id=vehicle_id) AS vehicle_reg_no
      FROM bookings
     WHERE status='cancelled'
       AND (driver_id=? OR (booking_type='personal' AND created_by=?))
     ORDER BY COALESCE(scheduled_start_at, created_at) DESC, id DESC";
  if ($st=$mysqli->prepare($sqlX)) {
    $st->bind_param('ii',$driverAccountId,$driverAccountId);
    $st->execute(); $r=$st->get_result(); while($row=$r->fetch_assoc()) $cancelled[]=$row; $st->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include __DIR__ . '/vendor/inc/head.php'; ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <style> html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif} </style>
  <!-- Autocomplete + Map -->
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    .kaya-title{font-weight:800;font-size:1.65rem;color:#000047;margin:12px 0 12px}
    .kaya-card{background:#fff;border-radius:12px;box-shadow:0 1px 3px rgba(0,0,0,.08);padding:16px}
    .kaya-chip{display:inline-block;font-size:11px;font-weight:700;color:#fff;border-radius:999px;padding:2px 8px}
    .chip-personal{background:#f59e0b;} .chip-admin{background:#0ea5e9;}
    .chip-completed{background:#16a34a;} .chip-cancelled{background:#ef4444;}
    .trip-item{border:1px solid #eef0f5;border-radius:10px;padding:10px 12px;margin:8px 0}
    .trip-head{font-weight:700;color:#0b0f2f} .trip-meta{font-size:12px;color:#64748b}
    .btn-kaya{background:#0b0f2f;color:#fff;border-radius:10px;padding:8px 12px;font-weight:700}
    .section-title{font-weight:800;color:#0b0f2f;margin-bottom:8px}
    /* map modal */
    #kayaMap { width:100%; height:420px; }
    .nominatim-results{ max-height:160px; overflow:auto; border:1px solid #eaecef; border-radius:.25rem; }
    .geocode-item{ cursor:pointer; padding:.375rem .5rem; border-bottom:1px solid #f1f3f7; }
    .geocode-item:last-child{ border-bottom:0; } .geocode-item:hover{ background:#f6f8ff; }
    /* keep jQuery UI menu above Bootstrap modals */
    .ui-autocomplete { z-index: 2000 !important; }
  </style>
</head>
<body id="page-top">
<?php include __DIR__ . '/vendor/inc/nav.php'; ?>

<div id="wrapper">
  <?php include __DIR__ . '/vendor/inc/sidebar.php'; ?>

  <div id="content-wrapper">
    <div class="container-fluid">

      <div class="d-flex align-items-center" style="gap:.5rem;">
        <h1 class="kaya-title flex-grow-1 mb-0">Trips</h1>
        <button type="button" class="btn btn-kaya" data-toggle="modal" data-target="#newTripModal">
          <i class="fas fa-plus mr-1"></i> New Trip
        </button>
      </div>

      <!-- Completed -->
      <div class="kaya-card mt-2 mb-4">
        <div class="section-title">Completed</div>
        <?php if (!$completed): ?>
          <div class="text-muted" style="font-size:14px;">No completed trips yet.</div>
        <?php else: foreach($completed as $t): ?>
          <div class="trip-item">
            <div class="trip-head"><?= h($t['pickup']) ?> <span class="text-muted">→</span> <?= h($t['dropoff']) ?></div>
            <div class="trip-meta mb-1"><?= h(fmt_when($t['when_at'])) ?></div>
            <div class="mb-1" style="display:flex;gap:6px;flex-wrap:wrap;">
              <span class="kaya-chip chip-completed">COMPLETED</span>
              <span class="kaya-chip <?= ($t['booking_type']==='personal'?'chip-personal':'chip-admin') ?>">
                <?= strtoupper($t['booking_type']==='personal'?'DIRECT BOOKING':'ADMIN') ?>
              </span>
              <?php if (!empty($t['vehicle_reg_no'])): ?><span class="badge badge-light">Vehicle: <?= h($t['vehicle_reg_no']) ?></span><?php endif; ?>
            </div>
            <div class="trip-meta">Customer: <strong><?= h($t['client_name'] ?: '—') ?></strong> · Phone: <strong><?= h($t['contact_phone'] ?: '—') ?></strong></div>
          </div>
        <?php endforeach; endif; ?>
      </div>

      <!-- Cancelled -->
      <div class="kaya-card mb-5">
        <div class="section-title" style="color:#ef4444;">Cancelled</div>
        <?php if (!$cancelled): ?>
          <div class="text-muted" style="font-size:14px;">No cancelled trips.</div>
        <?php else: foreach($cancelled as $t): $isMine = ($t['booking_type']==='personal' && (int)$t['created_by']===$driverAccountId); ?>
          <div class="trip-item">
            <div class="trip-head"><?= h($t['pickup']) ?> <span class="text-muted">→</span> <?= h($t['dropoff']) ?></div>
            <div class="trip-meta mb-1"><?= h(fmt_when($t['when_at'])) ?></div>
            <div class="mb-2" style="display:flex;gap:6px;flex-wrap:wrap;">
              <span class="kaya-chip chip-cancelled">CANCELLED</span>
              <span class="kaya-chip <?= ($t['booking_type']==='personal'?'chip-personal':'chip-admin') ?>">
                <?= strtoupper($t['booking_type']==='personal'?'DIRECT BOOKING':'ADMIN') ?>
              </span>
              <?php if (!empty($t['vehicle_reg_no'])): ?><span class="badge badge-light">Vehicle: <?= h($t['vehicle_reg_no']) ?></span><?php endif; ?>
            </div>
            <?php if ($isMine): ?>
              <form method="post" class="mt-1">
                <input type="hidden" name="restore_personal" value="1">
                <input type="hidden" name="booking_id" value="<?= (int)$t['booking_id'] ?>">
                <button class="btn btn-outline-primary btn-sm"><i class="fas fa-undo mr-1"></i> Restore</button>
                <a class="btn btn-outline-secondary btn-sm" href="driver-trip-edit.php?booking_id=<?= (int)$t['booking_id'] ?>"><i class="fas fa-edit mr-1"></i> Edit</a>
              </form>
            <?php else: ?>
              <div class="trip-meta text-muted">Assigned by Admin — cannot be restored by driver.</div>
            <?php endif; ?>
          </div>
        <?php endforeach; endif; ?>
      </div>

    </div>
    <?php include __DIR__ . '/vendor/inc/footer.php'; ?>
  </div>
</div>

<!-- New Direct Booking Modal -->
<!-- ... (modal + map code unchanged) ... -->

<!-- JS -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="vendor/js/sb-admin.min.js"></script>

<!-- Autocomplete + Map JS -->
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  // (existing JS unchanged)
  // ...
</script>

<style>
  footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
  footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
  #wrapper #content-wrapper{ padding-bottom:0!important; }
</style>

</body>
</html>
