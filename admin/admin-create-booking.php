<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

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

$isNewModel = table_exists($mysqli,'bookings');

$succ = $err = '';

/* who is the creator (first admin account) */
$creatorId = 1;
if (table_exists($mysqli,'accounts')) {
  if ($rs = $mysqli->query("SELECT id FROM accounts WHERE role='admin' ORDER BY id LIMIT 1")) {
    if ($row = $rs->fetch_assoc()) $creatorId = (int)$row['id'];
  }
}

/* -----------------------------------------------------------
   POST: create booking
----------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD']==='POST' && isset($_POST['create_booking'])) {

  // shared inputs
  $sched_date  = trim($_POST['sched_date'] ?? '');
  $sched_time  = trim($_POST['sched_time'] ?? '');
  $scheduled   = ($sched_date && $sched_time) ? ($sched_date.' '.$sched_time.':00') : null;

  $customer    = trim($_POST['customer'] ?? '');
  $phone       = trim($_POST['phone'] ?? '');
  $pax         = max(1, (int)($_POST['pax'] ?? 1));

  $pickup      = trim($_POST['pickup'] ?? '');
  $dropoff     = trim($_POST['dropoff'] ?? '');

  $notes       = trim($_POST['notes'] ?? '');

  if ($isNewModel) {
    $booking_type = ($_POST['booking_type'] ?? 'admin') === 'personal' ? 'personal' : 'admin';

    // these will already be auto-filled by JS when user changes vehicle/driver
    $driver_id  = (int)($_POST['driver_id'] ?? 0);
    $vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
    if ($driver_id  <= 0) $driver_id  = null;
    if ($vehicle_id <= 0) $vehicle_id = null;

    $status    = $driver_id ? 'awaiting_driver' : 'pending';
    $payment   = 'unpaid';
    $client_id = null;

    $sql = "INSERT INTO `bookings`
            (`booking_type`,`created_by`,`client_id`,`driver_id`,`vehicle_id`,
             `pax`,`contact_name`,`contact_phone`,`pickup_point`,`dropoff_point`,
             `scheduled_start_at`,`status`,`payment_status`,`notes`)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    if ($stmt = $mysqli->prepare($sql)) {
      $pairs = [
        ['s', $booking_type],
        ['i', $creatorId],
        ['i', $client_id],
        ['i', $driver_id],
        ['i', $vehicle_id],
        ['i', $pax],
        ['s', $customer],
        ['s', $phone],
        ['s', $pickup],
        ['s', $dropoff],
        ['s', $scheduled],
        ['s', $status],
        ['s', $payment],
        ['s', $notes],
      ];
      $types=''; $values=[];
      foreach ($pairs as $p){ $types.=$p[0]; $values[]=$p[1]; }
      $refs=[]; foreach ($values as $i=>$v){ $refs[$i]=&$values[$i]; }
      try { $stmt->bind_param($types, ...$refs); $ok = $stmt->execute(); }
      catch (Throwable $e){ $ok=false; $err='Database error: '.$e->getMessage(); }
      $stmt->close();

      $succ = $ok ? "Booking created." : ($err ?: "Please try again later.");
    } else {
      $err = "DB error while preparing statement: ".$mysqli->error;
    }

  } else {
    // ===== LEGACY fallback => tms_user insert =====
    $u_fname = $customer;
    $u_lname = '';

    $query = "INSERT INTO tms_user (
      u_fname, u_lname, u_car_date, u_car_time, u_car_pax,
      u_car_pickup, u_car_destination, u_car_regno,
      u_car_type, u_car_driver, u_category, u_email, u_pwd,
      u_car_book_status
    ) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?, 'Pending')";

    $u_car_date = $sched_date;
    $u_car_time = $sched_time;
    $u_car_pax  = (string)$pax;
    $u_car_pickup = $pickup;
    $u_car_destination = $dropoff;

    $u_car_regno = trim($_POST['u_car_regno'] ?? '');
    $u_car_type  = trim($_POST['u_car_type'] ?? '');
    $u_car_driver= trim($_POST['u_car_driver'] ?? '');

    $u_category = 'User';
    $u_email = '';
    $u_pwd   = password_hash(bin2hex(random_bytes(4)), PASSWORD_DEFAULT);

    if ($stmt = $mysqli->prepare($query)) {
      $stmt->bind_param(
        'sssssssssssss',
        $u_fname, $u_lname, $u_car_date, $u_car_time, $u_car_pax,
        $u_car_pickup, $u_car_destination, $u_car_regno,
        $u_car_type, $u_car_driver, $u_category, $u_email, $u_pwd
      );
      try { $ok = $stmt->execute(); }
      catch (Throwable $e) { $ok=false; $err = 'Database error: '.$e->getMessage(); }
      $stmt->close();

      $succ = $ok ? "Booking created." : ($err ?: "Please try again later.");
    } else {
      $err = "DB error while preparing statement.";
    }
  }
}

/* -----------------------------------------------------------
   Aux lists for selects + pairing maps
   NEW MODEL pulls from accounts + tms_vehicle
----------------------------------------------------------- */
$drivers = $vehicles = [];
if ($isNewModel && table_exists($mysqli,'accounts')) {
  if ($q = $mysqli->query("SELECT id,name FROM accounts WHERE role='driver' AND is_active=1 ORDER BY name")) {
    while($r=$q->fetch_assoc()) $drivers[]=$r;
  }
}
if ($isNewModel && table_exists($mysqli,'tms_vehicle')) {
  if ($q = $mysqli->query("
        SELECT v_id AS id,
               COALESCE(NULLIF(v_name,''),'Vehicle') AS name,
               COALESCE(NULLIF(v_reg_no,''), CONCAT('ID-', v_id)) AS plate_no
        FROM tms_vehicle
        WHERE deleted_at IS NULL
        ORDER BY v_name, v_reg_no")) {
    while($r=$q->fetch_assoc()) $vehicles[]=$r;
  }
}

/* -------- Build pairing maps from CURRENT assignments ----------
   Use the view v_vehicle_current_driver:
   - driver_account_id -> accounts.id (new drivers)
   - Works even if there’s no active row (falls back to default)
----------------------------------------------------------------- */
$vehToDrv = [];
$drvToVeh = [];
if ($isNewModel && table_exists($mysqli,'v_vehicle_current_driver')) {
  $rs = $mysqli->query("
      SELECT v_id, driver_account_id
      FROM v_vehicle_current_driver
      WHERE driver_account_id IS NOT NULL");
  if ($rs) while($m=$rs->fetch_assoc()){
    $vid = (int)$m['v_id']; $did = (int)$m['driver_account_id'];
    if ($vid && $did) { $vehToDrv[$vid]=$did; if (!isset($drvToVeh[$did])) $drvToVeh[$did]=$vid; }
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>
  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>

    <div id="content-wrapper">
      <div class="container-fluid">

        <h1 class="kaya-page-title">Create Trip Appointments</h1>

        <div class="kaya-toolbar d-flex align-items-center mb-3">
          <div class="btn-group" role="group" aria-label="Filters">
            <a href="admin-trip-appointment.php" class="btn kaya-tab">Upcoming</a>
            <a href="admin-view-booking.php"   class="btn kaya-tab">Completed</a>
          </div>
          <div class="kaya-actions ml-auto btn-group" role="group" aria-label="Actions">
            <a href="admin-create-booking.php" class="btn btn-kaya-primary">New Trip</a>
            <a href="admin-manage-booking.php" class="btn btn-kaya-danger-outline">Cancelled</a>
          </div>
        </div>

        <?php if(!empty($succ)): ?>
          <div class="alert alert-success alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($succ) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
          </div>
        <?php endif; ?>
        <?php if(!empty($err)): ?>
          <div class="alert alert-danger alert-dismissible fade show" role="alert">
            <?= htmlspecialchars($err) ?>
            <button type="button" class="close" data-dismiss="alert" aria-label="Close"><span>&times;</span></button>
          </div>
        <?php endif; ?>

        <section class="kaya-card">
          <form method="POST" class="px-2">
            <input type="hidden" name="create_booking" value="1">

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
                <input type="text" required class="form-control" name="customer" placeholder="Contact / Client name">
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

              <?php if (!$isNewModel): ?>
                <!-- Legacy inline fields -->
                <div class="form-group col-md-3">
                  <label>Vehicle Type</label>
                  <input type="text" class="form-control" name="u_car_type">
                </div>
                <div class="form-group col-md-3">
                  <label>Reg No.</label>
                  <input type="text" class="form-control" name="u_car_regno">
                </div>
                <div class="form-group col-md-3">
                  <label>Driver</label>
                  <input type="text" class="form-control" name="u_car_driver">
                </div>
              <?php else: ?>
                <div class="form-group col-md-5">
                  <label>Vehicle</label>
                  <select class="form-control" name="vehicle_id" id="vehicleSelect">
                    <option value="">— None —</option>
                    <?php foreach($vehicles as $v): ?>
                      <option value="<?= (int)$v['id'] ?>">
                        <?= htmlspecialchars(($v['name'] ?: 'Vehicle').' · '.$v['plate_no']) ?>
                      </option>
                    <?php endforeach; ?>
                  </select>
                </div>
                <div class="form-group col-md-4">
                  <label>Driver</label>
                  <select class="form-control" name="driver_id" id="driverSelect">
                    <option value="">— None —</option>
                    <?php foreach($drivers as $d): ?>
                      <option value="<?= (int)$d['id'] ?>"><?= htmlspecialchars($d['name']) ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              <?php endif; ?>
            </div>

            <div class="form-row">
              <div class="form-group col-md-6">
                <label>Pickup Location</label>
                <input type="text" id="pickup" class="form-control" name="pickup" placeholder="Where from?">
                <input type="hidden" id="pickup_lat">
                <input type="hidden" id="pickup_lng">
              </div>
              <div class="form-group col-md-6">
                <label>Destination</label>
                <input type="text"  id="dropoff" class="form-control" name="dropoff" placeholder="Where to?">
                <input type="hidden" id="dropoff_lat">
                <input type="hidden" id="dropoff_lng">
              </div>
            </div>

            <?php if ($isNewModel): ?>
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
            <?php endif; ?>

            <button type="submit" class="btn btn-kaya-primary">Create Booking</button>
            <a href="admin-trip-appointment.php" class="btn btn-outline-secondary ml-2">Back</a>

            <!-- legacy-only hidden baggage (no-op for new model) -->
            <input type="hidden" name="u_lname" value="">
            <input type="hidden" name="u_car_type" value="">
            <input type="hidden" name="u_car_regno" value="">
            <input type="hidden" name="u_car_driver" value="">
          </form>
        </section>

      </div>
      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <!-- Vendor JS -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://unpkg.com/leaflet-geosearch/dist/geosearch.umd.js"></script>
  <script src="vendor/js/trip_booking.js"></script>

  <!-- Pairing maps from CURRENT assignments -->
  <script>
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

  <style>
    footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
    footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
    #wrapper #content-wrapper{ padding-bottom:0!important; }
  </style>
</body>
</html>
