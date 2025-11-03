<?php
/**
 * KAYA • Reports (Trip History • Vehicle Report)
 * Prefers NEW schema:
 *   - Trips/History:  bookings  (fallback: tms_bookings, then tms_user minimal)
 *   - Vehicle daily:  tms_driver_report + bookings (fallback: tms_bookings)
 * Vehicles list & labels still from legacy tms_vehicle (your current UI/data).
 */

session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$mysqli->set_charset('utf8mb4');

/* ---------- helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dt(?string $s){ return $s ? date('Y-m-d', strtotime($s)) : null; }
function table_exists(mysqli $db, string $name): bool {
  $n = $db->real_escape_string($name);
  $res = $db->query("SHOW TABLES LIKE '{$n}'");
  return $res && $res->num_rows > 0;
}
function column_exists(mysqli $db, string $table, string $col): bool {
  $t = $db->real_escape_string($table);
  $c = $db->real_escape_string($col);
  $r = $db->query("SHOW COLUMNS FROM `{$t}` LIKE '{$c}'");
  return $r && $r->num_rows > 0;
}

/* ---------- inputs (Trip History filters) ---------- */
$th_from   = dt($_GET['th_from']  ?? date('Y-m-d', strtotime('-30 days')));
$th_to     = dt($_GET['th_to']    ?? date('Y-m-d'));
$th_status = trim($_GET['th_status'] ?? '');

/* ---------- inputs (Vehicle Report filters) ---------- */
$vr_vehicle_id = (int)($_GET['vr_vehicle_id'] ?? 0);
$vr_from       = dt($_GET['vr_from'] ?? date('Y-m-01'));
$vr_to         = dt($_GET['vr_to']   ?? date('Y-m-d'));

/* ---------- presence checks ---------- */
$HAS_BOOKINGS      = table_exists($mysqli, 'bookings');
$HAS_TMS_BOOKINGS  = table_exists($mysqli, 'tms_bookings');
$HAS_TMS_VEHICLE   = table_exists($mysqli, 'tms_vehicle');
$HAS_DRIVER_REPORT = table_exists($mysqli, 'tms_driver_report');

/* ---------- Trip History (prefer NEW bookings) ---------- */
$trip_rows = [];
if ($HAS_BOOKINGS) {
  $sql = "SELECT 
            b.id AS booking_id,
            b.booking_type,
            b.pax AS seats_reserved,
            b.pickup_point,
            b.dropoff_point,
            b.scheduled_start_at,
            b.status,
            b.driver_id,
            b.vehicle_id,
            (SELECT name FROM accounts a WHERE a.id=b.driver_id) AS driver_name,
            (SELECT CONCAT(v_name,' (',v_reg_no,')') FROM tms_vehicle v WHERE v.v_id=b.vehicle_id) AS vehicle_label
          FROM bookings b
          WHERE 1=1";
  $params=[]; $types='';
  if ($th_from) { $sql .= " AND b.scheduled_start_at >= ?";                          $params[]=$th_from.' 00:00:00'; $types.='s'; }
  if ($th_to)   { $sql .= " AND b.scheduled_start_at < DATE_ADD(?, INTERVAL 1 DAY)"; $params[]=$th_to.' 00:00:00';   $types.='s'; }
  if ($th_status!==''){ $sql .= " AND b.status = ?";                                  $params[]=$th_status;           $types.='s'; }
  $sql .= " ORDER BY b.scheduled_start_at DESC, b.id DESC";

  if ($st = $mysqli->prepare($sql)) {
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $trip_rows[] = $row;
    $st->close();
  }

} elseif ($HAS_TMS_BOOKINGS) {
  $sql = "SELECT 
            b.booking_id,
            b.booking_type,
            b.seats_reserved,
            b.pickup_point,
            b.dropoff_point,
            b.scheduled_at      AS scheduled_start_at,
            b.status,
            b.driver_id,
            b.vehicle_id,
            (SELECT CONCAT(v_name,' (',v_reg_no,')') FROM tms_vehicle v WHERE v.v_id=b.vehicle_id) AS vehicle_label,
            (SELECT CONCAT(u_fname,' ',u_lname) FROM tms_user_add_driver d WHERE d.d_u_id=b.driver_id) AS driver_name
          FROM tms_bookings b
          WHERE 1=1";
  $params=[]; $types='';
  if ($th_from) { $sql .= " AND b.scheduled_at >= ?";                          $params[]=$th_from.' 00:00:00'; $types.='s'; }
  if ($th_to)   { $sql .= " AND b.scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)"; $params[]=$th_to.' 00:00:00';   $types.='s'; }
  if ($th_status!==''){ $sql .= " AND b.status = ?";                            $params[]=$th_status;           $types.='s'; }
  $sql .= " ORDER BY b.scheduled_at DESC, b.booking_id DESC";

  if ($st = $mysqli->prepare($sql)) {
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $trip_rows[] = $row;
    $st->close();
  }
}

/* ---------- Vehicle list (legacy source) ---------- */
$vehicles = [];
if ($HAS_TMS_VEHICLE) {
  $where = column_exists($mysqli,'tms_vehicle','deleted_at') ? "WHERE deleted_at IS NULL" : "";
  if ($q = $mysqli->query("SELECT v_id AS v_id, CONCAT(v_name,' (',v_reg_no,')') AS label FROM tms_vehicle $where ORDER BY v_name, v_reg_no")) {
    while ($r = $q->fetch_assoc()) $vehicles[] = $r;
    $q->close();
  }
}

/* ---------- Vehicle Report (tms_driver_report + bookings; shows rows even without driver reports) ---------- */
$vehicle_header  = null;
$vehicle_daily   = [];

if ($vr_vehicle_id > 0 && $HAS_TMS_VEHICLE) {
  if ($s = $mysqli->prepare("SELECT v_name AS name, v_reg_no AS plate_no, v_status AS status FROM tms_vehicle WHERE v_id=?")) {
    $s->bind_param('i', $vr_vehicle_id);
    $s->execute();
    $vehicle_header = $s->get_result()->fetch_assoc();
    $s->close();
  }

  $byDay = [];
  if ($HAS_DRIVER_REPORT) {
    $sql = "SELECT 
              trip_date                         AS d,
              SUM(COALESCE(total_km,0))         AS distance_km,
              SUM(COALESCE(fuel_used_liters,0)) AS fuel_used_liters,
              MIN(odometer_start)               AS odo_start_km,
              MAX(odometer_end)                 AS odo_end_km
            FROM tms_driver_report
            WHERE vehicle_id=? AND trip_date BETWEEN ? AND ?
            GROUP BY trip_date";
    if ($s = $mysqli->prepare($sql)) {
      $s->bind_param('iss', $vr_vehicle_id, $vr_from, $vr_to);
      $s->execute();
      $r = $s->get_result();
      while ($row = $r->fetch_assoc()) {
        $d = $row['d'];
        $byDay[$d] = [
          'service_date'     => $d,
          'trips'            => 0,
          'distance_km'      => (float)$row['distance_km'],
          'fuel_used_liters' => (float)$row['fuel_used_liters'],
          'odo_start_km'     => isset($row['odo_start_km']) ? (float)$row['odo_start_km'] : null,
          'odo_end_km'       => isset($row['odo_end_km'])   ? (float)$row['odo_end_km']   : null,
          'duration_seconds' => 0,
        ];
      }
      $s->close();
    }
  }

  $dateFrom = $vr_from.' 00:00:00';
  $dateTo   = $vr_to  .' 00:00:00';

  if ($HAS_BOOKINGS) {
    $sql = "SELECT DATE(scheduled_start_at) AS d, COUNT(*) AS trips
            FROM bookings
            WHERE vehicle_id=? AND scheduled_start_at >= ? AND scheduled_start_at < DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY DATE(scheduled_start_at)";
    if ($s = $mysqli->prepare($sql)) {
      $s->bind_param('iss', $vr_vehicle_id, $dateFrom, $dateTo);
      $s->execute();
      $r = $s->get_result();
      while ($row = $r->fetch_assoc()) {
        $d = $row['d'];
        if (!isset($byDay[$d])) {
          $byDay[$d] = [
            'service_date'     => $d,
            'trips'            => 0,
            'distance_km'      => 0.0,
            'fuel_used_liters' => 0.0,
            'odo_start_km'     => null,
            'odo_end_km'       => null,
            'duration_seconds' => 0,
          ];
        }
        $byDay[$d]['trips'] = (int)$row['trips'];
      }
      $s->close();
    }
  } elseif ($HAS_TMS_BOOKINGS) {
    $sql = "SELECT DATE(scheduled_at) AS d, COUNT(*) AS trips
            FROM tms_bookings
            WHERE vehicle_id=? AND scheduled_at >= ? AND scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)
            GROUP BY DATE(scheduled_at)";
    if ($s = $mysqli->prepare($sql)) {
      $s->bind_param('iss', $vr_vehicle_id, $dateFrom, $dateTo);
      $s->execute();
      $r = $s->get_result();
      while ($row = $r->fetch_assoc()) {
        $d = $row['d'];
        if (!isset($byDay[$d])) {
          $byDay[$d] = [
            'service_date'     => $d,
            'trips'            => 0,
            'distance_km'      => 0.0,
            'fuel_used_liters' => 0.0,
            'odo_start_km'     => null,
            'odo_end_km'       => null,
            'duration_seconds' => 0,
          ];
        }
        $byDay[$d]['trips'] = (int)$row['trips'];
      }
      $s->close();
    }
  }

  if ($byDay) { krsort($byDay); $vehicle_daily = array_values($byDay); }
}

?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>

  <style>
    html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
    .kaya-page-title{font-weight:800;font-size:2rem;line-height:1.1;color:#000047;margin:0 0 1rem}
    .kaya-card{background:#fff;border-radius:1rem;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:1rem;border:1px solid #e5e7eb}
    .muted{color:#6b7280}
    .metric{display:flex;flex-direction:column;padding:1rem;border:1px solid #e5e7eb;border-radius:.75rem}
    .metric .label{font-size:.8rem;color:#6b7280}
    .metric .value{font-size:1.4rem;font-weight:800;color:#0b132b}
    .metrics{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:.75rem}
    @media (min-width:768px){ .metrics{grid-template-columns:repeat(4,minmax(0,1fr));} }
    .kaya-table thead th{font-weight:600;color:#6b7280;border:0}
    .kaya-table tbody td{border-top:1px solid #f1f5f9;vertical-align:middle}
    .nav-kaya .nav-link{border:1px solid #e5e7eb;border-radius:.5rem;margin-right:.5rem;color:#111827}
    .nav-kaya .nav-link.active{background:#0A0F2C;border-color:#0A0F2C;color:#fff}
    @media print{
      nav.navbar, #accordionSidebar, .sidebar, .kaya-toolbar, .no-print{ display:none !important; }
      #content-wrapper{ margin:0 !important; padding:0 !important; }
      .kaya-card{ box-shadow:none !important; border:0 !important; }
      .print-area{ break-inside: avoid; }
    }
    .btn-kaya-primary{background:#0A0F2C;border:1px solid #0A0F2C;color:#fff}
    .btn-kaya-primary:hover{background:#0c1438;color:#fff}
  </style>

  <div id="content-wrapper">
    <div class="container-fluid">

      <h1 class="kaya-page-title">Reports</h1>

      <ul class="nav nav-pills nav-kaya mb-3" id="reportTabs" role="tablist">
        <li class="nav-item"><a class="nav-link active" id="tab-history" data-toggle="tab" href="#history" role="tab">Trip History</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-vehicle" data-toggle="tab" href="#vehicle" role="tab">Vehicle Report</a></li>
      </ul>

      <div class="tab-content">

        <!-- Trip History -->
        <section class="tab-pane fade show active print-area" id="history" role="tabpanel" aria-labelledby="tab-history">
          <div class="kaya-card">
            <div class="d-flex align-items-center kaya-toolbar mb-3">
              <h5 class="m-0">Trip History</h5>
              <div class="ml-auto no-print">
                <form class="form-inline" method="get">
                  <input type="hidden" name="tab" value="history">
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">From</label>
                    <input type="date" class="form-control" name="th_from" value="<?= h($th_from) ?>">
                  </div>
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">To</label>
                    <input type="date" class="form-control" name="th_to" value="<?= h($th_to) ?>">
                  </div>
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">Status</label>
                    <select class="form-control" name="th_status">
                      <?php
                        $statuses = ['','pending','awaiting_driver','accepted','rejected','cancelled','in_progress','completed'];
                        foreach($statuses as $s){
                          $sel = ($s===$th_status)?'selected':''; 
                          echo '<option value="'.h($s).'" '.$sel.'>'.($s===''?'All':ucwords(str_replace('_',' ',$s))).'</option>';
                        }
                      ?>
                    </select>
                  </div>
                  <button class="btn btn-kaya-primary ml-2">Apply</button>
                  <button class="btn btn-outline-secondary ml-2" type="button" onclick="printSection('#history')"><i class="fas fa-print mr-1"></i> Print</button>
                </form>
              </div>
            </div>

            <div class="table-responsive">
              <table id="tripHistoryTable" class="table kaya-table table-borderless table-hover align-middle">
                <thead class="thead-light">
                  <tr>
                    <th>#</th>
                    <th>When</th>
                    <th>Type</th>
                    <th>Pax</th>
                    <th>Pickup</th>
                    <th>Dropoff</th>
                    <th>Driver</th>
                    <th>Vehicle</th>
                    <th>Status</th>
                  </tr>
                </thead>
                <tbody>
                  <?php $n=1; foreach($trip_rows as $r): ?>
                  <tr>
                    <td><?= $n++ ?></td>
                    <td><?= h($r['scheduled_start_at']) ?></td>
                    <td><?= h(ucfirst($r['booking_type'])) ?></td>
                    <td><?= (int)($r['seats_reserved'] ?? 0) ?></td>
                    <td><?= h($r['pickup_point'] ?: '—') ?></td>
                    <td><?= h($r['dropoff_point'] ?: '—') ?></td>
                    <td><?= h($r['driver_name'] ?: ($r['driver_id'] ?: '—')) ?></td>
                    <td><?= h($r['vehicle_label'] ?: ($r['vehicle_id'] ?: '—')) ?></td>
                    <td><?= h(ucwords(str_replace('_',' ',$r['status']))) ?></td>
                  </tr>
                  <?php endforeach; ?>
                </tbody>
              </table>
            </div>
          </div>
        </section>

        <!-- Vehicle Report -->
        <section class="tab-pane fade print-area" id="vehicle" role="tabpanel" aria-labelledby="tab-vehicle">
          <div class="kaya-card">
            <div class="d-flex align-items-center kaya-toolbar mb-3">
              <h5 class="m-0">Vehicle Report</h5>
              <div class="ml-auto no-print">
                <form class="form-inline" id="report_form">
                  <input type="hidden" name="tab" value="vehicle">
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">Vehicle</label>
                    <select name="vr_vehicle_id" id="trips_plate_no" class="form-control" required>
                      <option value="">— select —</option>
                      <?php foreach($vehicles as $v): ?>
                        <option value="<?= (int)$v['v_id'] ?>" <?= $vr_vehicle_id===(int)$v['v_id']?'selected':'' ?>>
                          <?= h($v['label']) ?>
                        </option>
                      <?php endforeach; ?>
                    </select>
                  </div>
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">From</label>
                    <input type="date" class="form-control" id="trips_from_date" name="vr_from" value="<?= h($vr_from) ?>" required>
                  </div>
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">To</label>
                    <input type="date" class="form-control" id="trips_to_date" name="vr_to" value="<?= h($vr_to) ?>" required>
                  </div>
                  <button type="submit" class="btn btn-kaya-primary ml-2">Run</button>
                  <button class="btn btn-outline-secondary ml-2" type="button" onclick="printSection('#vehicle')"><i class="fas fa-print mr-1"></i> Print</button>
                </form>
              </div>
            </div>

              <div class="metrics mb-3">
                <div class="metric"><span class="label">Trips</span><span class="value" id="total_trips"></span></div>
              </div>

              <div class="table-responsive">
                <table id="vehicleDailyTable" class="table kaya-table table-borderless table-hover align-middle">
                  <thead class="thead-light">
                    <tr>
                      <th>#</th>
                      <th>Date</th>
                      <th>Plate #</th>
                      <th>RPM</th>
                      <th>Speed</th>
                      <th>Throttle</th>
                      <th>Coolant Temp</th>
                      <th>Fuel Type</th>
                    </tr>
                  </thead>
                  <tbody>
           
                  </tbody>
                </table>
              </div>
              <div class="muted">Pick a vehicle and date range, then click <em>Run</em>.</div>

          </div>
        </section>

      </div>
    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

<!-- Vendor JS -->
<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="vendor/jquery-easing/jquery.easing.min.js"></script>
<script src="vendor/datatables/jquery.dataTables.js"></script>
<script src="vendor/datatables/dataTables.bootstrap4.js"></script>
<script src="js/sb-admin.min.js"></script>
 <script src="vendor/js/report.js"></script>

<script>
  (function(){
    var p = new URLSearchParams(location.search);
    var tab = p.get('tab');
    if (tab) {
      var el = document.querySelector('[href="#'+tab+'"]');
      if (el) $(el).tab('show');
    }
  })();

  $('#tripHistoryTable').DataTable({
    pageLength: 10,
    lengthMenu: [10,25,50,100],
    order: [[1,'desc']]
  });
  $('#vehicleDailyTable').DataTable({
    pageLength: 10,
    lengthMenu: [10,25,50,100],
    order: [[1,'desc']]
  });

  function printSection(sel){
    var a = document.querySelector('[href="'+sel+'"]');
    if (a) $(a).tab('show');
    setTimeout(function(){ window.print(); }, 100);
  }
</script>

</body>
</html>
