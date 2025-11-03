<?php
// KAYA · Trip Appointments (List first, Calendar below)
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
require_once 'vendor/inc/audit.php'; // ✅ add audit helper

$isAdmin = function_exists('is_admin') ? is_admin() : (
  isset($_SESSION['role']) && in_array(strtolower($_SESSION['role']), ['admin','superadmin'], true)
);
$aid = (int)($_SESSION['account_id'] ?? ($_SESSION['a_id'] ?? 0));
$actorId = $aid; // ✅ use this as the audit actor

/* ----- safety: avoid collation warnings ----- */
$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET collation_connection='utf8mb4_unicode_ci'");

/* ----- helpers ----- */
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

/* ===== driver id if not admin ===== */
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

/* ===== AJAX create booking ===== */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['ajax_create_booking'])) {
  header('Content-Type: application/json; charset=utf-8');

  // expose SQL errors while we wire things up
  mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

  $isNewModel = table_exists($mysqli,'bookings');

  // creator (first admin account)
  $creatorId = 1;
  if (table_exists($mysqli,'accounts')) {
    if ($rs = $mysqli->query("SELECT id FROM accounts WHERE role='admin' ORDER BY id LIMIT 1")) {
      if ($row = $rs->fetch_assoc()) $creatorId = (int)$row['id'];
    }
  }

  // inputs
  $sched_date  = trim($_POST['sched_date'] ?? '');
  $sched_time  = trim($_POST['sched_time'] ?? '');
  if ($sched_time && !preg_match('/^\d{2}:\d{2}(:\d{2})?$/', $sched_time)) {
    echo json_encode(['ok'=>0,'error'=>'Invalid time format']); exit;
  }
  $scheduled   = ($sched_date && $sched_time) ? ($sched_date.' '.$sched_time.(strlen($sched_time)>5?'':':00')) : null;

  $customer    = trim($_POST['customer'] ?? '');
  $phone       = trim($_POST['phone'] ?? '');
  $pax         = max(1, (int)$_POST['pax'] ?? 1);
  $pickup      = trim($_POST['pickup'] ?? '');
  $dropoff     = trim($_POST['dropoff'] ?? '');
  $pickup_lat  = trim($_POST['pickup_lat'] ?? '');
  $pickup_lng  = trim($_POST['pickup_lng'] ?? '');
  $dropoff_lat = trim($_POST['dropoff_lat'] ?? '');
  $dropoff_lng = trim($_POST['dropoff_lng'] ?? '');

  $booking_type= (($_POST['booking_type'] ?? 'admin') === 'personal') ? 'personal' : 'admin';

  // empty string -> NULL for FKs
  $driver_id   = isset($_POST['driver_id'])  && $_POST['driver_id']  !== '' ? (int)$_POST['driver_id']  : null;
  $vehicle_id  = isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== '' ? (int)$_POST['vehicle_id'] : null;
  $notes       = trim($_POST['notes'] ?? '');

  try {
    if ($isNewModel) {
      // if driver is already assigned, driver must accept
      $status    = $driver_id ? 'awaiting_driver' : 'pending';
      $payment   = 'unpaid';  // will be skipped if column not present
      $client_id = null;

      // Build INSERT only with columns that exist
      $cols = []; $ph = []; $typ = ''; $val = [];
      $add = function($col, $type, $value) use (&$cols,&$ph,&$typ,&$val,$mysqli){
        if (column_exists($mysqli,'bookings',$col)) {
          $cols[] = "`$col`";
          $ph[]   = '?';
          $typ   .= $type;
          $val[]  = $value;
        }
      };

      $add('booking_type',       's', $booking_type);
      $add('created_by',         'i', $creatorId);
      $add('client_id',          'i', $client_id);
      $add('driver_id',          'i', $driver_id);
      $add('vehicle_id',         'i', $vehicle_id);
      $add('pax',                'i', $pax);
      $add('contact_name',       's', $customer);
      $add('contact_phone',      's', $phone);
      $add('pickup_point',       's', $pickup);
      $add('dropoff_point',      's', $dropoff);
      $add('scheduled_start_at', 's', $scheduled);
      $add('status',             's', $status);
      $add('payment_status',     's', $payment); // safely skipped if missing
      $add('notes',              's', $notes);

      if (!$cols) { echo json_encode(['ok'=>0,'error'=>'No matching columns in bookings table']); exit; }

      $sql  = "INSERT INTO bookings (".implode(',', $cols).") VALUES (".implode(',', $ph).")";
      $stmt = $mysqli->prepare($sql);

      // bind by reference
      $bind = []; $bind[] = &$typ;
      foreach ($val as $i => $v) { $bind[] = &$val[$i]; }
      call_user_func_array([$stmt,'bind_param'], $bind);

      $stmt->execute();
      $newId = $stmt->insert_id;
      $stmt->close();

      /* ✅ AUDIT: booking created (new schema) */
      audit_log($mysqli, $actorId, 'booking_create', $newId, [
        'status'        => 'success',
        'portal'        => $isAdmin ? 'admin' : 'driver',
        'booking_type'  => $booking_type,
        'pax'           => (int)$pax,
        'driver_id'     => $driver_id,
        'vehicle_id'    => $vehicle_id,
        'scheduled_at'  => $scheduled,
        'pickup_point'  => $pickup,
        'dropoff_point' => $dropoff
      ]);

      echo json_encode(['ok'=>1,'id'=>$newId]); exit;

    } else {
      // legacy fallback
      $q = $mysqli->prepare("INSERT INTO tms_user
        (u_fname,u_lname,u_car_date,u_car_time,u_car_pax,u_car_pickup,u_car_destination,
         u_car_regno,u_car_type,u_car_driver,u_category,u_email,u_pwd,u_car_book_status)
        VALUES (?,?,?,?,isssssssss,'Pending')");
      $empty=''; $email=''; $pwd=password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);
      $paxS = (string)$pax; $reg=$empty; $type=$empty; $drv=$empty; $cat='User';
      $q->bind_param('ssssissssssss',
        $customer,$empty,$sched_date,$sched_time,$paxS,$pickup,$dropoff,
        $reg,$type,$drv,$cat,$email,$pwd
      );
      $q->execute(); $id=$q->insert_id; $q->close();

      /* ✅ AUDIT: booking created (legacy) */
      audit_log($mysqli, $actorId, 'booking_create_legacy', $id, [
        'status'        => 'success',
        'portal'        => $isAdmin ? 'admin' : 'driver',
        'pax'           => (int)$pax,
        'scheduled_date'=> $sched_date,
        'scheduled_time'=> $sched_time,
        'pickup_point'  => $pickup,
        'dropoff_point' => $dropoff
      ]);

      echo json_encode(['ok'=>1,'id'=>$id]); exit;
    }

  } catch (Throwable $e) {
    /* ✅ AUDIT: booking create failure */
    audit_log($mysqli, $actorId, 'booking_create', null, [
      'status'  => 'failure',
      'portal'  => $isAdmin ? 'admin' : 'driver',
      'error'   => $e->getMessage(),
      'input'   => [
        'booking_type' => $booking_type,
        'pax'          => (int)$pax,
        'driver_id'    => $driver_id,
        'vehicle_id'   => $vehicle_id,
        'scheduled_at' => $scheduled
      ]
    ]);

    echo json_encode(['ok'=>0,'error'=>$e->getMessage()]); exit;
  }
}

/* ----- upcoming rows (new -> legacy) ----- */
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
          LEFT JOIN accounts c     ON c.id=b.client_id
          LEFT JOIN accounts d     ON d.id=b.driver_id
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

/* ===== Pairing maps for auto Vehicle<->Driver ===== */
$vehToDrv = [];
$drvToVeh = [];
if (table_exists($mysqli,'v_vehicle_current_driver')) {
  $rs = $mysqli->query("SELECT v_id, driver_account_id FROM v_vehicle_current_driver WHERE driver_account_id IS NOT NULL");
  if ($rs) while($m=$rs->fetch_assoc()){
    $vid = (int)$m['v_id']; $did = (int)$m['driver_account_id'];
    if ($vid && $did) { $vehToDrv[$vid]=$did; if (!isset($drvToVeh[$did])) $drvToVeh[$did]=$vid; }
  }
} elseif (table_exists($mysqli,'tms_vehicle') && column_exists($mysqli,'tms_vehicle','default_driver_id')) {
  $rs = $mysqli->query("SELECT v_id AS v_id, default_driver_id AS driver_account_id FROM tms_vehicle WHERE default_driver_id IS NOT NULL");
  if ($rs) while($m=$rs->fetch_assoc()){
    $vid = (int)$m['v_id']; $did = (int)$m['driver_account_id'];
    if ($vid && $did) { $vehToDrv[$vid]=$did; if (!isset($drvToVeh[$did])) $drvToVeh[$did]=$vid; }
  }
}

/* ===== vehicles for modal (include display name + category) ===== */
$vehiclesForSelect = [];
if (table_exists($mysqli,'tms_vehicle')) {
  $q = $mysqli->query("
    SELECT v.v_id AS id,
           COALESCE(d.display_name, NULLIF(v.v_name,''), 'Vehicle') AS name,
           COALESCE(NULLIF(v.v_reg_no,''), CONCAT('ID-', v.v_id)) AS plate_no,
           COALESCE(v.v_category,'') AS v_category
    FROM tms_vehicle v
    LEFT JOIN v_vehicle_display d ON d.v_id=v.v_id
    WHERE ".(column_exists($mysqli,'tms_vehicle','deleted_at') ? "v.deleted_at IS NULL" : "1=1")."
    ORDER BY name, v.v_reg_no
  ");
  if ($q) while($row=$q->fetch_assoc()) $vehiclesForSelect[]=$row;
}

/* ===== categories (for Vehicle Type filter) ===== */
$categories_active = [];
if (table_exists($mysqli,'tms_vehicle_categories')) {
  $where = column_exists($mysqli,'tms_vehicle_categories','deleted_at')
         ? "WHERE is_active=1 AND deleted_at IS NULL"
         : "WHERE is_active=1";
  if ($rs=$mysqli->query("SELECT name FROM tms_vehicle_categories $where ORDER BY name"))
    while($r=$rs->fetch_assoc()) $categories_active[] = $r['name'];
}
if (!$categories_active) $categories_active = ['Bus','Sedan','SUV','Van'];

define('ACTION_ENDPOINT', 'booking_actions.php');
?>
<!DOCTYPE html>
<html lang="en">
  <style>
    .kaya-page-title{font-weight:800;font-size:2rem;line-height:1.1;color:#000047;margin:0 0 1rem}
    .btn.kaya-tab { background:#fff; border:1px solid #bfc6da; color:#000047; }
    .btn-group .btn.kaya-tab.active{
      border-color:#000047!important;color:#000047!important;background:#fff!important;
      box-shadow: inset 0 -2px 0 #000047;
    }
    .btn-group .btn.kaya-tab:not(.active){ border-color:#d9deee!important;background:#fff!important; }
    .btn-group .btn.kaya-tab:not(.active):hover{ border-color:#b9c2dd!important;background:#f6f8ff!important; }
    .fc .fc-toolbar-title { font-weight:800; color:#000047; }
    #kayaCalendar { min-height:520px; }
    #kayaMap { width:100%; height:420px; }
    .nominatim-results{ max-height:160px; overflow:auto; border:1px solid #eaecef; border-radius:.25rem; }
    .geocode-item{ cursor:pointer; padding:.375rem .5rem; border-bottom:1px solid #f1f3f7; }
    .geocode-item:last-child{ border-bottom:0; }
    .geocode-item:hover{ background:#f6f8ff; }
  </style>

<?php include('vendor/inc/head.php'); ?>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<style> html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif} </style>
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>

<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>

  <div id="content-wrapper">
    <div class="container-fluid">

      <h1 class="kaya-page-title">Trip Appointments</h1>

      <div class="kaya-toolbar d-flex align-items-center mb-3" style="gap:.5rem;flex-wrap:wrap;">
        <div class="btn-group" role="group" aria-label="Filters">
          <a href="admin-trip-appointment.php" class="btn kaya-tab active">Upcoming</a>
          <a href="admin-view-booking.php"   class="btn kaya-tab">Completed</a>
          <a href="admin-manage-booking.php" class="btn kaya-tab">Cancelled</a>
        </div>
        <div class="kaya-actions ml-auto btn-group" role="group" aria-label="Actions" style="flex-wrap:nowrap;gap:.5rem;">
          <button type="button" class="btn btn-kaya-primary" data-toggle="modal" data-target="#newTripModal">
            <i class="fas fa-plus mr-1"></i> New Trip
          </button>
        </div>
      </div>

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

                $canAdminApprove  = false; // hide/remove the Approve action for admins
                $canAdminComplete = false; //admin can't complete the trip that's up to the driver
                $canAdminCancel   = $isAdmin && in_array(strtolower($r['status']),['pending','awaiting_driver','accepted','in_progress']);


                $canDriverAccept  = !$isAdmin && $isMine && in_array(strtolower($r['status']),['pending','awaiting_driver']);
                $canDriverDecline = !$isAdmin && $isMine && in_array(strtolower($r['status']),['pending','awaiting_driver']);
                $canDriverStart   = !$isAdmin && $isMine && strtolower($r['status'])==='accepted';
                $canDriverDrop    = !$isAdmin && $isMine && strtolower($r['status'])==='in_progress';
              ?>
              <tr data-booking-id="<?= (int)$r['booking_id'] ?>" data-date="<?= htmlspecialchars($dt ? date('Y-m-d', strtotime($dt)) : '') ?>">
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

      <!-- New Trip Modal -->
      <div class="modal fade" id="newTripModal" tabindex="-1" role="dialog" aria-labelledby="newTripLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
          <div class="modal-content">
            <form id="newTripForm">
              <div class="modal-header">
                <h5 class="modal-title" id="newTripLabel">Create Trip</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="ajax_create_booking" value="1">

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Date</label>
                    <input type="date" required class="form-control" name="sched_date">
                  </div>
                  <div class="form-group col-md-6">
                    <label>Time</label>
                    <input type="time" required class="form-control" name="sched_time">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Customer</label>
                    <input type="text" required class="form-control" name="customer">
                  </div>
                  <div class="form-group col-md-6">
                    <label>Phone</label>
                    <input type="text" class="form-control" name="phone" placeholder="+63…">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-3">
                    <label>Pax</label>
                    <input type="number" class="form-control" name="pax" min="1" value="1">
                  </div>

                  <!-- NEW: vehicle type (category) filter -->
                  <div class="form-group col-md-3">
                    <label>Vehicle Type</label>
                    <select class="form-control" id="modalVehicleType">
                      <option value="">— Any type —</option>
                      <?php foreach ($categories_active as $cat): ?>
                        <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                      <?php endforeach; ?>
                    </select>
                  </div>

                  <div class="form-group col-md-3">
                    <label>Vehicle</label>
                    <select class="form-control" name="vehicle_id" id="modalVehicleSelect">
                      <!-- options built by JS from VEHICLES[] -->
                    </select>
                  </div>
                  <div class="form-group col-md-3">
                    <label>Driver</label>
                    <select class="form-control" name="driver_id" id="modalDriverSelect">
                      <option value="">— None —</option>
                      <?php
                      if (table_exists($mysqli,'accounts')) {
                        // Build a WHERE that works whether or not `deleted_at` exists (older DBs)
                        $hasDeletedAt = column_exists($mysqli,'accounts','deleted_at');
                        $deletedClause = $hasDeletedAt ? "AND a.deleted_at IS NULL" : "";

                        // If the legacy table exists, also hide archived/soft-deleted legacy rows that match by email.
                        $excludeViaLegacy = table_exists($mysqli,'tms_user_add_driver') ? "
                          AND NOT EXISTS (
                            SELECT 1
                            FROM tms_user_add_driver tu
                            WHERE tu.u_email <> '' AND LOWER(tu.u_email) = LOWER(a.email)
                              AND (tu.is_archived = 1 OR tu.deleted_at IS NOT NULL)
                          )
                        " : "";

                        $sql = "
                          SELECT a.id, a.name
                          FROM accounts a
                          WHERE a.role='driver'
                            AND a.is_active=1
                            $deletedClause
                            $excludeViaLegacy
                          ORDER BY a.name
                        ";
                        if ($q = $mysqli->query($sql)) {
                          while ($d = $q->fetch_assoc()) {
                            echo '<option value="'.(int)$d['id'].'">'.htmlspecialchars($d['name']).'</option>';
                          }
                        }
                      }
                      ?>
                    </select>

                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Pickup</label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="pickup" name="pickup" placeholder="Type or use map">
                      <div class="input-group-append">
                        <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="pickup">
                          <i class="fas fa-map-marker-alt"></i>
                        </button>
                      </div>
                    </div>
                    <input type="hidden" id="pickup_lat" name="pickup_lat">
                    <input type="hidden" id="pickup_lng" name="pickup_lng">
                  </div>
                  <div class="form-group col-md-6">
                    <label>Destination</label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="dropoff" name="dropoff" placeholder="Type or use map">
                      <div class="input-group-append">
                        <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="dropoff">
                          <i class="fas fa-map-pin"></i>
                        </button>
                      </div>
                    </div>
                    <input type="hidden" id="dropoff_lat" name="dropoff_lat">
                    <input type="hidden" id="dropoff_lng" name="dropoff_lng">
                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-4">
                    <label>Booking Type</label>
                    <select name="booking_type" class="form-control">
                      <option value="admin">Admin</option>
                      <option value="personal">Personal</option>
                    </select>
                  </div>
                  <div class="form-group col-md-8">
                    <label>Notes</label>
                    <input type="text" class="form-control" name="notes" placeholder="Optional notes">
                  </div>
                </div>
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-kaya-primary">
                  <i class="fas fa-save mr-1"></i> Create Booking
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <!-- Map Picker Modal (shared for Pickup/Destination) -->
      <div class="modal fade" id="mapModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
          <div class="modal-content">
            <div class="modal-header">
              <h5 class="modal-title"><i class="fas fa-map-marked-alt mr-1"></i> Choose Location</h5>
              <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
            </div>
            <div class="modal-body">
              <div class="form-group mb-2">
                <div class="input-group">
                  <input id="geoQuery" type="text" class="form-control" placeholder="Search address / place">
                  <div class="input-group-append">
                    <button id="btnGeoSearch" class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button>
                  </div>
                </div>
                <div id="geoResults" class="nominatim-results mt-2"></div>
              </div>
              <div id="kayaMap"></div>
              <small class="text-muted d-block mt-2">Drag the marker to fine-tune. We’ll reverse-geocode and fill the text field.</small>
            </div>
            <div class="modal-footer">
              <button type="button" id="btnUsePoint" class="btn btn-kaya-primary"><i class="fas fa-check mr-1"></i> Use this point</button>
              <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
            </div>
          </div>
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

<!-- FullCalendar (CDN) -->
<script src="https://cdn.jsdelivr.net/npm/fullcalendar@6.1.11/index.global.min.js"></script>
<script src="https://unpkg.com/sweetalert/dist/sweetalert.min.js"></script>

<!-- Location suggestions + map -->
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  // Pairing maps made available to JS
  const VEH_TO_DRV = <?= json_encode($vehToDrv, JSON_UNESCAPED_UNICODE) ?>;
  const DRV_TO_VEH = <?= json_encode($drvToVeh, JSON_UNESCAPED_UNICODE) ?>;

  // NEW: vehicles w/ name + category (for modal filtering)
  const VEHICLES = <?= json_encode($vehiclesForSelect, JSON_UNESCAPED_UNICODE) ?>;

  function rebuildVehicleOptions(typeValue){
    const $veh = $('#modalVehicleSelect');
    const cur  = $veh.val();
    $veh.empty().append($('<option/>').val('').text('— None —'));

    const list = (VEHICLES||[])
      .filter(v => !typeValue || String(v.v_category).toLowerCase() === String(typeValue).toLowerCase())
      .sort((a,b) => (a.name||'').localeCompare(b.name||'') || (a.plate_no||'').localeCompare(b.plate_no||''));

    list.forEach(v=>{
      const label = (v.name||'Vehicle') + ' · ' + (v.plate_no||('ID-'+v.id));
      $veh.append($('<option/>').val(v.id).text(label));
    });

    if (cur && $veh.find('option[value="'+cur+'"]').length) $veh.val(cur);
  }

  $(function(){
    $('#dataTable').DataTable({
      pageLength: 10,
      order: [[0,'asc']],
      searching: true,
      columnDefs: [{ targets: -1, orderable:false, searchable:false }]
    });
  });

  $(function(){
  const table = $('#dataTable').DataTable({
    pageLength: 10,
    order: [[0,'asc']],
    searching: true,
    columnDefs: [{ targets: -1, orderable:false, searchable:false }]
  });

  //highlight ?highlight=<id>
  (function(){
    const id = new URLSearchParams(location.search).get('highlight');
    if (!id) return;

    table.search(id).draw();

    setTimeout(function(){
      const row = document.querySelector('#dataTable tbody tr[data-booking-id="'+id+'"]');
      if (row) {
        row.classList.add('row-flash');
        row.scrollIntoView({ behavior: 'smooth', block: 'center' });
        setTimeout(function(){ table.search('').draw(); }, 1200);
        setTimeout(function(){ row.classList.remove('row-flash'); }, 1800);
      }
    }, 120);
  })();
});

  

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
      headerToolbar: { left:'prev,next today', center:'title', right:'dayGridMonth,timeGridWeek,timeGridDay,listWeek' },
      nowIndicator: true,
      eventTimeFormat: { hour: '2-digit', minute: '2-digit', meridiem: true },
      events: function(fetchInfo, success, failure) {
        const params = new URLSearchParams();
        params.set('start', fetchInfo.startStr);
        params.set('end',   fetchInfo.endStr);
        const s = (statusSel && statusSel.value) ? statusSel.value : '';
        if (s) params.append('status[]', s);
        if (driverInput && driverInput.value) params.set('driver_id', driverInput.value);

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

    if (statusSel) statusSel.addEventListener('change', () => calendar.refetchEvents());

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

  // sidebar sizing
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

  // 🔁 Auto-pair Vehicle <-> Driver in the MODAL
  (function(){
    var $veh = $('#modalVehicleSelect');
    var $drv = $('#modalDriverSelect');

    $('#newTripModal').on('shown.bs.modal', function(){
      rebuildVehicleOptions($('#modalVehicleType').val() || '');
    });
    $('#modalVehicleType').on('change', function(){
      rebuildVehicleOptions(this.value || '');
      $veh.trigger('change');
    });

    $veh.on('change', function(){
      var vid = $(this).val();
      if (!vid) return;
      if (VEH_TO_DRV && Object.prototype.hasOwnProperty.call(VEH_TO_DRV, vid)) {
        var want = String(VEH_TO_DRV[vid]);
        if (String($drv.val()) !== want) $drv.val(want).trigger('change');
      }
    });
    $drv.on('change', function(){
      var did = $(this).val();
      if (!did) return;
      if (DRV_TO_VEH && Object.prototype.hasOwnProperty.call(DRV_TO_VEH, did)) {
        var want = String(DRV_TO_VEH[did]);
        if (String($veh.val()) !== want) $veh.val(want).trigger('change');
      }
    });
  })();

  /* =============================================================
     Location Suggestions + Map Picker
     ============================================================= */
  function composePhotonLabel(f){
    if (!f || !f.properties) return '';
    const p = f.properties;
    const parts = [];
    if (p.name) parts.push(p.name);
    if (p.street) parts.push(p.street + (p.housenumber ? ' ' + p.housenumber : ''));
    if (p.suburb || p.district || p.city) parts.push(p.suburb || p.district || p.city);
    if (p.state) parts.push(p.state);
    if (p.country) parts.push(p.country);
    return parts.filter(Boolean).join(', ');
  }
  function composeNominatimLabel(rec){ return rec && rec.display_name ? rec.display_name : ''; }

  const PH_BOUNDS = { west:116.0, south:4.4, east:127.0, north:21.3 };
  const PH_CENTER = { lat:14.5995, lon:120.9842 };
  const PH_BBOX_STR = [PH_BOUNDS.west, PH_BOUNDS.south, PH_BOUNDS.east, PH_BOUNDS.north].join(',');

  function photonSearch(q, limit=8){
    const url='https://photon.komoot.io/api/?q='+encodeURIComponent(q)+'&limit='+limit+'&lat='+PH_CENTER.lat+'&lon='+PH_CENTER.lon+'&bbox='+PH_BBOX_STR+'&lang=en';
    return fetch(url,{mode:'cors'}).then(r=>r.json()).then(json=>json && json.features ? json.features : []);
  }
  function nominatimSearch(q, limit=8){
    const url='https://nominatim.openstreetmap.org/search?format=jsonv2&limit='+limit+'&countrycodes=ph&viewbox='+[PH_BOUNDS.west,PH_BOUNDS.north,PH_BOUNDS.east,PH_BOUNDS.south].join(',')+'&bounded=1&q='+encodeURIComponent(q);
    return fetch(url,{mode:'cors',headers:{'Accept':'application/json','Accept-Language':'en-PH'}}).then(r=>r.json());
  }
  function nominatimReverse(lat, lon){
    const url='https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+lat+'&lon='+lon+'&accept-language=en-PH';
    return fetch(url,{mode:'cors',headers:{'Accept':'application/json'}}).then(r=>r.json()).then(rec=>composeNominatimLabel(rec));
  }
  function reverseNice(lat, lon){ return nominatimReverse(lat,lon).catch(()=>lat.toFixed(6)+', '+lon.toFixed(6)); }

  (function enableGeoAutocomplete(){
    if ($('#geoQuery').data('geo-autocomplete')) return;
    $('#geoQuery').data('geo-autocomplete', 1);
    function toPhotonItem(f){ const label=composePhotonLabel(f); return {label,value:label,lat:f.geometry.coordinates[1],lon:f.geometry.coordinates[0]}; }
    function toNominatimItem(rec){ const label=composeNominatimLabel(rec); return {label,value:label,lat:parseFloat(rec.lat),lon:parseFloat(rec.lon)}; }
    $('#geoQuery').autocomplete({
      minLength: 2, delay: 250,
      source: function(req, resp){
        photonSearch(req.term, 8).then(features=>{
          if (features && features.length) return resp(features.map(toPhotonItem));
          return nominatimSearch(req.term, 8).then(list=>resp((list||[]).map(toNominatimItem)));
        }).catch(()=>{ nominatimSearch(req.term, 8).then(list=>resp((list||[]).map(toNominatimItem))).catch(()=>resp([])); });
      },
      select: function(e, ui){
        if (!ui || !ui.item) return;
        const lat = parseFloat(ui.item.lat), lon = parseFloat(ui.item.lon);
        setMarker(lat, lon);
        lastPicked = { label: ui.item.value, lat: lat, lng: lon };
        setTimeout(()=>$('#geoQuery').select(), 0);
      },
      open: function(){ $('.ui-autocomplete').css('z-index', 2000); }
    });
    $('#geoQuery').on('keydown', function(e){ if (e.key === 'Enter') e.preventDefault(); });
  })();

  function attachAutocomplete($input, $lat, $lng){
    if ($input.data('kaya-autocomplete')) return;
    $input.data('kaya-autocomplete', 1);
    $input.autocomplete({
      minLength: 2, delay: 250,
      source: function(req, resp){
        photonSearch(req.term, 8).then(features=>{
          if (features && features.length){
            resp(features.map(f=>{
              const label = composePhotonLabel(f);
              return { label: label, value: label, lat: f.geometry.coordinates[1], lon: f.geometry.coordinates[0] };
            }));
          } else {
            nominatimSearch(req.term,8).then(list=>{
              resp((list||[]).map(it=>({label: composeNominatimLabel(it), value: composeNominatimLabel(it), lat: it.lat, lon: it.lon})));
            }).catch(()=>resp([]));
          }
        }).catch(()=>{ nominatimSearch(req.term,8).then(list=>{ resp((list||[]).map(it=>({label: composeNominatimLabel(it), value: composeNominatimLabel(it), lat: it.lat, lon: it.lon}))); }).catch(()=>resp([])); });
      },
      select: function(e, ui){
        if (ui && ui.item){ $lat.val(parseFloat(ui.item.lat).toFixed(8)); $lng.val(parseFloat(ui.item.lon).toFixed(8)); }
      },
      open: function(){ $('.ui-autocomplete').css('z-index', 2000); }
    });
    $input.on('input', function(){ $lat.val(''); $lng.val(''); });
  }

  $('#newTripModal').on('shown.bs.modal', function(){
    attachAutocomplete($('#pickup'),  $('#pickup_lat'),  $('#pickup_lng'));
    attachAutocomplete($('#dropoff'), $('#dropoff_lat'), $('#dropoff_lng'));
  });

  // ===== Map Picker
  var Lmap, Lmarker, pickingFor='pickup';
  var lastCenter = {lat:14.5995, lng:120.9842, zoom:12};
  var lastPicked = {label:'', lat:null, lng:null};
  function initMap(){
    if (Lmap) { setTimeout(()=>Lmap.invalidateSize(), 100); return; }
    Lmap = L.map('kayaMap').setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 19, attribution: '&copy; OpenStreetMap' }).addTo(Lmap);
    Lmarker = L.marker(Lmap.getCenter(), {draggable:true}).addTo(Lmap);
    Lmap.on('moveend', function(){ lastCenter={lat:Lmap.getCenter().lat,lng:Lmap.getCenter().lng,zoom:Lmap.getZoom()}; });
    Lmarker.on('dragend', function(){ var p = Lmarker.getLatLng(); reverseNice(p.lat, p.lng).then(function(lbl){ lastPicked = {label: lbl || '', lat: p.lat, lng: p.lng}; }); });
  }
  function setMarker(lat,lng){
    Lmarker.setLatLng([lat,lng]); Lmap.setView([lat,lng], Math.max(15,Lmap.getZoom()));
    reverseNice(lat,lng).then(function(lbl){ lastPicked = {label: lbl || '', lat: lat, lng: lng}; });
  }
  $('#mapModal').on('shown.bs.modal', function(ev){
    var btn = $(ev.relatedTarget); pickingFor = (btn && btn.data('for')) ? String(btn.data='pickup') : 'pickup'; initMap();
    var lat = $('#'+pickingFor+'_lat').val(), lng = $('#'+pickingFor+'_lng').val();
    if (lat && lng) { setMarker(parseFloat(lat), parseFloat(lng)); }
    else { var text = $('#'+pickingFor).val();
      if (text && text.length>3){
        photonSearch(text,1).then(function(r){ if (r && r[0]) setMarker(r[0].geometry.coordinates[1], r[0].geometry.coordinates[0]); else return nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); }); })
        .catch(function(){ nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); }); })
        .finally(function(){ setTimeout(()=>Lmap.invalidateSize(), 150); });
        return;
      }
      Lmap.setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom); Lmarker.setLatLng(Lmap.getCenter());
      lastPicked = {label:'', lat:Lmap.getCenter().lat, lng:Lmap.getCenter().lng};
    }
    setTimeout(()=>Lmap.invalidateSize(), 150);
  });
  $('#btnGeoSearch').on('click', function(){
    var q = $('#geoQuery').val().trim(); var $list = $('#geoResults').empty(); if (!q) return;
    $list.text('Searching…');
    photonSearch(q,10).then(function(features){
      if (!features || !features.length) throw new Error('no-photon');
      $list.empty(); features.forEach(function(f){
        var label = (function(p){const parts=[]; if(p.name)parts.push(p.name); if(p.street)parts.push(p.street+(p.housenumber?' '+p.housenumber:'')); if(p.suburb||p.district||p.city)parts.push(p.suburb||p.district||p.city); if(p.state)parts.push(p.state); if(p.country)parts.push(p.country); return parts.filter(Boolean).join(', ');})(f.properties);
        var lat = f.geometry.coordinates[1], lon = f.geometry.coordinates[0];
        var $it = $('<div class="geocode-item"></div>').text(label);
        $it.on('click', function(){ setMarker(lat, lon); lastPicked = {label: label, lat: lat, lng: lon}; });
        $list.append($it);
      });
    }).catch(function(){
      nominatimSearch(q,10).then(function(list){
        $list.empty(); if (!list || !list.length){ $list.text('No results.'); return; }
        list.forEach(function(r){
          var label = r.display_name; var lat = parseFloat(r.lat), lon = parseFloat(r.lon);
          var $it = $('<div class="geocode-item"></div>').text(label);
          $it.on('click', function(){ setMarker(lat, lon); lastPicked = {label: label, lat: lat, lng: lon}; });
          $list.append($it);
        });
      }).catch(function(){ $list.text('Search failed.'); });
    });
  });
  $('#btnUsePoint').on('click', function(){
    var pos = Lmarker.getLatLng();
    var apply = function(lbl){
      var labelToUse = lbl || lastPicked.label || $('#'+pickingFor).val() || (pos.lat.toFixed(6)+', '+pos.lng.toFixed(6));
      $('#'+pickingFor).val(labelToUse);
      $('#'+pickingFor+'_lat').val(pos.lat.toFixed(8));
      $('#'+pickingFor+'_lng').val(pos.lng.toFixed(8));
      $('#mapModal').modal('hide');
    };
    if (lastPicked.lat===pos.lat && lastPicked.lng===pos.lng && lastPicked.label){ apply(lastPicked.label); }
    else { reverseNice(pos.lat,pos.lng).then(apply).catch(function(){ apply(''); }); }
  });

  // Modal submit -> AJAX to this page
  (function(){
    var form = document.getElementById('newTripForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
      e.preventDefault();
      var fd = new FormData(form);
      fetch('admin-trip-appointment.php', {
        method: 'POST', credentials: 'same-origin', body: fd
      }).then(r=>r.json()).then(function(res){
        if (res && res.ok) {
          $('#newTripModal').modal('hide');
          swal("Created!", "Booking has been created.", "success");
          setTimeout(function(){ location.reload(); }, 800);
        } else {
          swal("Oops", (res && res.error) ? res.error : "Failed to create booking.", "error");
        }
      }).catch(function(){ swal("Oops", "Network / server error.", "error"); });
    });
    if (location.hash === '#new') { $('#newTripModal').modal('show'); }
  })();
</script>

<style>
  footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
  footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
  #wrapper #content-wrapper{ padding-bottom:0!important; }
</style>

</body>
</html>
