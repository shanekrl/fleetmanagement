<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
if (!function_exists('is_admin') || !is_admin()) { header('Location: admin-trip-appointment.php'); exit; }

$mysqli->set_charset('utf8mb4');

/* ----------------- Helpers ----------------- */
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

/* ------------------------------------------------------------------
   Decide which record we’re editing:
   - New model:  bookings.id        => GET booking_id
   - Legacy:     tms_user.u_id      => GET u_id
-------------------------------------------------------------------*/
$booking_id = isset($_GET['booking_id']) ? (int)$_GET['booking_id'] : 0;
$legacy_id  = isset($_GET['u_id'])       ? (int)$_GET['u_id']       : 0;

$is_new_model = $booking_id && table_exists($mysqli,'bookings');

/* ==================================================================
   NEW MODEL (bookings)
==================================================================*/
if ($is_new_model) {

  // ---------- Save ----------
  if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_booking'])) {

    $booking_type = ($_POST['booking_type'] ?? 'admin') === 'personal' ? 'personal' : 'admin';
    $pax          = (int)($_POST['pax'] ?? 1);
    $contact_name = trim($_POST['contact_name'] ?? '');
    $contact_phone= trim($_POST['contact_phone'] ?? '');
    $pickup_point = trim($_POST['pickup_point'] ?? '');
    $dropoff_point= trim($_POST['dropoff_point'] ?? '');

    $vehicle_id   = (int)($_POST['vehicle_id'] ?? 0);
    if ($vehicle_id <= 0) $vehicle_id = null;

    $driver_id    = (int)($_POST['driver_id'] ?? 0);
    if ($driver_id  <= 0) $driver_id  = null;

    $sd = trim($_POST['sched_date'] ?? '');
    $st = trim($_POST['sched_time'] ?? '');
    $scheduled = ($sd && $st) ? ($sd . ' ' . $st . ':00') : null;

    $status = $_POST['status'] ?? 'pending';
    $allowed = ['pending','awaiting_driver','assigned','accepted','in_progress','cancelled','completed'];
    if (!in_array($status,$allowed,true)) $status = 'pending';

    $notes  = trim($_POST['notes'] ?? '');

    $sql = "UPDATE bookings
            SET booking_type=?, pax=?, contact_name=?, contact_phone=?,
                pickup_point=?, dropoff_point=?, vehicle_id=?, driver_id=?,
                scheduled_start_at=?, status=?, notes=?, updated_at=NOW()
            WHERE id=?";
    if ($s = $mysqli->prepare($sql)) {
      // NOTE: binding NULL to 'i' becomes 0 in mysqli; if you need real NULLs,
      // you can run a separate SET or use dynamic SQL. Keeping your original pattern.
      $s->bind_param(
        'sisssssisssi',
        $booking_type, $pax, $contact_name, $contact_phone,
        $pickup_point, $dropoff_point, $vehicle_id, $driver_id,
        $scheduled, $status, $notes, $booking_id
      );
      $s->execute(); $s->close();
    }

    header('Location: admin-trip-appointment.php?updated=1'); exit;
  }

  // ---------- Load ----------
  $row = null;
  if ($s = $mysqli->prepare("
        SELECT b.*,
               d.name       AS driver_name,
               tv.v_reg_no  AS vehicle_reg_no,
               tv.v_name    AS vehicle_name
          FROM bookings b
          LEFT JOIN accounts    d  ON d.id     = b.driver_id
          LEFT JOIN tms_vehicle tv ON tv.v_id  = b.vehicle_id
         WHERE b.id=? LIMIT 1")) {
    $s->bind_param('i',$booking_id);
    $s->execute();
    $res = $s->get_result();
    $row = $res->fetch_assoc();
    $s->close();
  }
  if (!$row) { header('Location: admin-trip-appointment.php'); exit; }

  // Aux lists
  $drivers  = [];
  if (table_exists($mysqli,'accounts')) {
    if ($q=$mysqli->query("SELECT id,name FROM accounts WHERE role='driver' AND is_active=1 ORDER BY name")) {
      while($r=$q->fetch_assoc()) $drivers[]=$r;
    }
  }

  // Vehicles come from tms_vehicle (not vehicles)
  $vehicles = [];
  if (table_exists($mysqli,'tms_vehicle')) {
    if ($q=$mysqli->query("
          SELECT v_id AS id,
                 COALESCE(NULLIF(v_reg_no,''), CONCAT('ID-', v_id)) AS plate_no,
                 v_name AS name
          FROM tms_vehicle
          WHERE deleted_at IS NULL
          ORDER BY v_name, v_reg_no")) {
      while($r=$q->fetch_assoc()) $vehicles[]=$r;
    }
  }

  // Build pairing maps (Vehicle -> Driver) and (Driver -> Vehicle)
  $vehToDrv = [];
  $drvToVeh = [];

  if (table_exists($mysqli,'tms_vehicle') && column_exists($mysqli,'tms_vehicle','default_driver_id')) {
    $rs = $mysqli->query("SELECT v_id AS v_id, default_driver_id AS d_id
                          FROM tms_vehicle
                          WHERE default_driver_id IS NOT NULL");
    if ($rs) while($m=$rs->fetch_assoc()){
      $vid=(int)$m['v_id']; $did=(int)$m['d_id'];
      if ($vid && $did){ $vehToDrv[$vid]=$did; $drvToVeh[$did]=$vid; }
    }
  }

  // split scheduled for inputs
  $sd = $row['scheduled_start_at'] ? substr($row['scheduled_start_at'],0,10) : '';
  $st = $row['scheduled_start_at'] ? substr($row['scheduled_start_at'],11,5) : '';

  ?>
  <!DOCTYPE html>
  <html lang="en">
  <?php include('vendor/inc/head.php'); ?>
  <body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>
  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>
    <div id="content-wrapper">
      <div class="container-fluid">
        <h1 class="kaya-page-title">Edit Booking</h1>

        <div class="kaya-card p-3">
          <form method="post">
            <input type="hidden" name="save_booking" value="1">

            <div class="form-row">
              <div class="form-group col-md-3">
                <label>Booking Type</label>
                <select name="booking_type" class="form-control">
                  <option value="admin"    <?= $row['booking_type']==='admin'?'selected':''; ?>>Admin</option>
                  <option value="personal" <?= $row['booking_type']==='personal'?'selected':''; ?>>Personal</option>
                </select>
              </div>
              <div class="form-group col-md-3">
                <label>Pax</label>
                <input type="number" class="form-control" name="pax" min="1" value="<?= (int)$row['pax'] ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Scheduled Date</label>
                <input type="date" class="form-control" name="sched_date" value="<?= htmlspecialchars($sd) ?>">
              </div>
              <div class="form-group col-md-3">
                <label>Scheduled Time</label>
                <input type="time" class="form-control" name="sched_time" value="<?= htmlspecialchars($st) ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Contact Name</label>
                <input type="text" class="form-control" name="contact_name" value="<?= htmlspecialchars($row['contact_name'] ?? '') ?>">
              </div>
              <div class="form-group col-md-6">
                <label>Contact Phone</label>
                <input type="text" class="form-control" name="contact_phone" value="<?= htmlspecialchars($row['contact_phone'] ?? '') ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Pickup</label>
                <input type="text" class="form-control" name="pickup_point" value="<?= htmlspecialchars($row['pickup_point'] ?? '') ?>">
              </div>
              <div class="form-group col-md-6">
                <label>Dropoff</label>
                <input type="text" class="form-control" name="dropoff_point" value="<?= htmlspecialchars($row['dropoff_point'] ?? '') ?>">
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Vehicle</label>
                <select name="vehicle_id" id="vehicleSelect" class="form-control">
                  <option value="">— None —</option>
                  <?php foreach($vehicles as $v): ?>
                    <option value="<?= (int)$v['id'] ?>" <?= ((int)$row['vehicle_id']===(int)$v['id'])?'selected':''; ?>>
                      <?= htmlspecialchars(($v['name'] ?: 'Vehicle').' · '.$v['plate_no']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label>Driver</label>
                <select name="driver_id" id="driverSelect" class="form-control">
                  <option value="">— None —</option>
                  <?php foreach($drivers as $d): ?>
                    <option value="<?= (int)$d['id'] ?>" <?= ((int)$row['driver_id']===(int)$d['id'])?'selected':''; ?>>
                      <?= htmlspecialchars($d['name']) ?>
                    </option>
                  <?php endforeach; ?>
                </select>
              </div>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Status</label>
                <select name="status" class="form-control">
                  <?php
                    $opts=['pending','awaiting_driver','assigned','accepted','in_progress','cancelled','completed'];
                    foreach($opts as $opt){
                      $sel = ($row['status']===$opt)?'selected':''; echo "<option $sel>".htmlspecialchars($opt)."</option>";
                    }
                  ?>
                </select>
              </div>
              <div class="form-group col-md-6">
                <label>Notes</label>
                <input type="text" class="form-control" name="notes" value="<?= htmlspecialchars($row['notes'] ?? '') ?>">
              </div>
            </div>

            <div class="text-right">
              <button class="btn btn-kaya-primary" type="submit">Save</button>
              <a class="btn btn-outline-secondary" href="admin-trip-appointment.php">Back</a>
            </div>
          </form>
        </div>
      </div>
      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>

  <script>
    // Pairing maps from PHP (no AJAX needed)
    const VEH_TO_DRV = <?= json_encode($vehToDrv, JSON_UNESCAPED_UNICODE) ?>;
    const DRV_TO_VEH = <?= json_encode($drvToVeh, JSON_UNESCAPED_UNICODE) ?>;

    (function(){
      var $veh = $('#vehicleSelect');
      var $drv = $('#driverSelect');

      $veh.on('change', function(){
        var vid = $(this).val();
        if (!vid) return;
        if (VEH_TO_DRV.hasOwnProperty(vid)) {
          var want = String(VEH_TO_DRV[vid]);
          if (String($drv.val()) !== want) $drv.val(want).trigger('change');
        }
      });

      $drv.on('change', function(){
        var uid = $(this).val();
        if (!uid) return;
        if (DRV_TO_VEH.hasOwnProperty(uid)) {
          var want = String(DRV_TO_VEH[uid]);
          if (String($veh.val()) !== want) $veh.val(want).trigger('change');
        }
      });
    })();
  </script>
  </body>
  </html>
  <?php
  exit;
}

/* ==================================================================
   LEGACY MODEL (tms_user)  — kept so your older rows still edit
==================================================================*/

$u_id = $legacy_id ?: (int)($_GET['booking_id'] ?? 0); // allow booking_id to map to legacy id

if (!$u_id || !table_exists($mysqli,'tms_user')) { header('Location: admin-trip-appointment.php'); exit; }

if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['save_legacy'])) {
  $date  = trim($_POST['u_car_date'] ?? '');
  $time  = trim($_POST['u_car_time'] ?? '');
  $fname = trim($_POST['u_fname'] ?? '');
  $lname = trim($_POST['u_lname'] ?? '');
  $pax   = trim($_POST['u_car_pax'] ?? '');
  $pick  = trim($_POST['u_car_pickup'] ?? '');
  $dest  = trim($_POST['u_car_destination'] ?? '');
  $reg   = trim($_POST['u_car_regno'] ?? '');
  $type  = trim($_POST['u_car_type'] ?? '');
  $drv   = trim($_POST['u_car_driver'] ?? '');
  $status= trim($_POST['u_car_book_status'] ?? '');

  $sql = "UPDATE tms_user
          SET u_car_date=?, u_car_time=?, u_fname=?, u_lname=?, u_car_pax=?,
              u_car_pickup=?, u_car_destination=?, u_car_regno=?, u_car_type=?,
              u_car_driver=?, u_car_book_status=?
          WHERE u_id=?";
  if ($s=$mysqli->prepare($sql)) {
    $s->bind_param('ssssissssssi',$date,$time,$fname,$lname,$pax,$pick,$dest,$reg,$type,$drv,$status,$u_id);
    $s->execute(); $s->close();
  }
  header('Location: admin-trip-appointment.php?updated=1'); exit;
}

// load legacy row
$row = null;
if ($s=$mysqli->prepare("SELECT * FROM tms_user WHERE u_id=? LIMIT 1")) {
  $s->bind_param('i',$u_id);
  $s->execute();
  $res = $s->get_result();
  $row = $res->fetch_assoc();
  $s->close();
}
if (!$row) { header('Location: admin-trip-appointment.php'); exit; }
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper">
    <div class="container-fluid">
      <h1 class="kaya-page-title">Edit Booking</h1>
      <div class="kaya-card p-3">
        <form method="post">
          <input type="hidden" name="save_legacy" value="1">
          <div class="form-row">
            <div class="form-group col-md-3">
              <label>Date</label>
              <input type="date" class="form-control" name="u_car_date" value="<?= htmlspecialchars($row['u_car_date'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3">
              <label>Time</label>
              <input type="time" class="form-control" name="u_car_time" value="<?= htmlspecialchars($row['u_car_time'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3">
              <label>First name</label>
              <input type="text" class="form-control" name="u_fname" value="<?= htmlspecialchars($row['u_fname'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3">
              <label>Last name</label>
              <input type="text" class="form-control" name="u_lname" value="<?= htmlspecialchars($row['u_lname'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-2"><label>Pax</label>
              <input type="number" class="form-control" name="u_car_pax" value="<?= htmlspecialchars($row['u_car_pax'] ?? '') ?>">
            </div>
            <div class="form-group col-md-5"><label>Pickup</label>
              <input type="text" class="form-control" name="u_car_pickup" value="<?= htmlspecialchars($row['u_car_pickup'] ?? '') ?>">
            </div>
            <div class="form-group col-md-5"><label>Destination</label>
              <input type="text" class="form-control" name="u_car_destination" value="<?= htmlspecialchars($row['u_car_destination'] ?? '') ?>">
            </div>
          </div>

          <div class="form-row">
            <div class="form-group col-md-3"><label>Reg No.</label>
              <input type="text" class="form-control" name="u_car_regno" value="<?= htmlspecialchars($row['u_car_regno'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3"><label>Vehicle Type</label>
              <input type="text" class="form-control" name="u_car_type" value="<?= htmlspecialchars($row['u_car_type'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3"><label>Driver</label>
              <input type="text" class="form-control" name="u_car_driver" value="<?= htmlspecialchars($row['u_car_driver'] ?? '') ?>">
            </div>
            <div class="form-group col-md-3"><label>Status</label>
              <select name="u_car_book_status" class="form-control">
                <?php
                  $opts=['Pending','Approved','Completed','Cancel','Maintenance','In Active','Available'];
                  foreach($opts as $opt){
                    $sel = ($row['u_car_book_status']===$opt)?'selected':''; echo "<option $sel>".htmlspecialchars($opt)."</option>";
                  }
                ?>
              </select>
            </div>
          </div>

          <div class="text-right">
            <button class="btn btn-kaya-primary" name="save" value="1">Save</button>
            <a class="btn btn-outline-secondary" href="admin-trip-appointment.php">Back</a>
          </div>
        </form>
      </div>
    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body>
</html>
