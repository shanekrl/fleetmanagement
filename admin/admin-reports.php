<?php
/**
 * KAYA • Reports (Fleet Summary • Trip History • Vehicle Report)
 * Prefers NEW schema:
 *   - Trips/History:  bookings  (fallback: tms_bookings, then tms_user minimal)
 *   - Fleet today:    bookings  (fallback: tms_bookings) + booking_runs live
 *   - Vehicle daily:  tms_driver_report + bookings (fallback: tms_bookings)
 * Vehicles list & labels still from legacy tms_vehicle (your current UI/data).
 */

session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$aid = function_exists('require_admin') ? require_admin() : (int)($_SESSION['a_id'] ?? 0);

$mysqli->set_charset('utf8mb4');

/* ---------- helpers ---------- */
function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function dt(?string $s){ return $s ? date('Y-m-d', strtotime($s)) : null; }
function table_exists(mysqli $db, string $name): bool {
  $n = $db->real_escape_string($name);
  $res = $db->query("SHOW TABLES LIKE '{$n}'");
  return $res && $res->num_rows > 0;
}

/* ---------- inputs (Trip History filters) ---------- */
$th_from   = dt($_GET['th_from']  ?? date('Y-m-d', strtotime('-30 days')));
$th_to     = dt($_GET['th_to']    ?? date('Y-m-d')); // inclusive in UI; exclusive in SQL +1 day
$th_status = trim($_GET['th_status'] ?? '');         // '', pending/awaiting_driver/.../completed

/* ---------- inputs (Vehicle Report filters) ---------- */
$vr_vehicle_id = (int)($_GET['vr_vehicle_id'] ?? 0);
$vr_from       = dt($_GET['vr_from'] ?? date('Y-m-01'));
$vr_to         = dt($_GET['vr_to']   ?? date('Y-m-d'));

/* ---------- presence checks ---------- */
$HAS_BOOKINGS        = table_exists($mysqli, 'bookings');
$HAS_TMS_BOOKINGS    = table_exists($mysqli, 'tms_bookings');
$HAS_TMS_VEHICLE     = table_exists($mysqli, 'tms_vehicle');
$HAS_DRIVER_REPORT   = table_exists($mysqli, 'tms_driver_report');
$HAS_BOOKING_RUNS    = table_exists($mysqli, 'booking_runs');

/* ---------- Fleet summary (today) ---------- */
$fleet = [
  'total_vehicles'      => 0,
  'vehicles_available'  => 0,
  'vehicles_in_use'     => 0,
  'vehicles_maintenance'=> 0,
  'vehicles_inactive'   => 0,
  'trips_today'         => 0,
  'trips_in_progress'   => 0,
  'drivers_active_today'=> 0,
];

if ($HAS_TMS_VEHICLE) {
  $sql = "SELECT 
            COUNT(*)                                            AS total_vehicles,
            SUM(LOWER(v_status) LIKE 'avail%')                  AS vehicles_available,
            SUM(LOWER(v_status) REGEXP 'book|service')          AS vehicles_in_use,
            SUM(LOWER(v_status) REGEXP 'maint')                 AS vehicles_maintenance,
            SUM(LOWER(v_status) LIKE 'inactive%')               AS vehicles_inactive
          FROM tms_vehicle
          WHERE deleted_at IS NULL";
  if ($q = $mysqli->query($sql)) {
    $row = $q->fetch_assoc() ?: [];
    foreach ($fleet as $k => $v) if (isset($row[$k])) $fleet[$k] = (int)$row[$k];
    $q->close();
  }
}

if ($HAS_BOOKINGS) {
  // Prefer NEW bookings
  $sql = "SELECT 
            SUM(DATE(scheduled_start_at)=CURDATE()) AS trips_today,
            COUNT(DISTINCT CASE 
              WHEN DATE(scheduled_start_at)=CURDATE() AND status IN ('accepted','in_progress','completed') 
            THEN driver_id END) AS drivers_active_today
          FROM bookings";
  if ($q = $mysqli->query($sql)) {
    $r = $q->fetch_assoc() ?: [];
    $fleet['trips_today'] = (int)($r['trips_today'] ?? 0);
    $fleet['drivers_active_today'] = (int)($r['drivers_active_today'] ?? 0);
    $q->close();
  }
} elseif ($HAS_TMS_BOOKINGS) {
  // Fallback legacy
  $sql = "SELECT 
            SUM(DATE(scheduled_at)=CURDATE()) AS trips_today,
            COUNT(DISTINCT CASE 
              WHEN DATE(scheduled_at)=CURDATE() AND status IN ('accepted','completed','in_progress') 
            THEN driver_id END) AS drivers_active_today
          FROM tms_bookings";
  if ($q = $mysqli->query($sql)) {
    $r = $q->fetch_assoc() ?: [];
    $fleet['trips_today'] = (int)($r['trips_today'] ?? 0);
    $fleet['drivers_active_today'] = (int)($r['drivers_active_today'] ?? 0);
    $q->close();
  }
}

if ($HAS_BOOKING_RUNS) {
  $sql = "SELECT COUNT(*) AS c
          FROM booking_runs
          WHERE pickup_button_at IS NOT NULL
            AND dropoff_button_at IS NULL
            AND DATE(COALESCE(pickup_button_at, NOW())) = CURDATE()";
  if ($q = $mysqli->query($sql)) {
    $fleet['trips_in_progress'] = (int)($q->fetch_assoc()['c'] ?? 0);
    $q->close();
  }
}

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
            b.payment_status,
            b.driver_id,
            b.vehicle_id,
            (SELECT name FROM accounts a WHERE a.id=b.driver_id) AS driver_name,
            (SELECT CONCAT(v_name,' (',v_reg_no,')') FROM tms_vehicle v WHERE v.v_id=b.vehicle_id) AS vehicle_label
          FROM bookings b
          WHERE 1=1";
  $params=[]; $types='';
  if ($th_from) { $sql .= " AND b.scheduled_start_at >= ?";                           $params[]=$th_from.' 00:00:00'; $types.='s'; }
  if ($th_to)   { $sql .= " AND b.scheduled_start_at < DATE_ADD(?, INTERVAL 1 DAY)";  $params[]=$th_to.' 00:00:00';   $types.='s'; }
  if ($th_status!==''){ $sql .= " AND b.status = ?";                                   $params[]=$th_status;           $types.='s'; }
  $sql .= " ORDER BY b.scheduled_start_at DESC, b.id DESC";

  if ($st = $mysqli->prepare($sql)) {
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) {
      // Normalize field names to what the template expects
      $row['scheduled_start_at'] = $row['scheduled_start_at'];
      $trip_rows[] = $row;
    }
    $st->close();
  }

} elseif ($HAS_TMS_BOOKINGS) {
  // Legacy fallback
  $sql = "SELECT 
            b.booking_id,
            b.booking_type,
            b.seats_reserved,
            b.pickup_point,
            b.dropoff_point,
            b.scheduled_at      AS scheduled_start_at,
            b.status,
            b.payment_status,
            b.driver_id,
            b.vehicle_id,
            (SELECT CONCAT(v_name,' (',v_reg_no,')') FROM tms_vehicle v WHERE v.v_id=b.vehicle_id) AS vehicle_label,
            (SELECT CONCAT(u_fname,' ',u_lname) FROM tms_user_add_driver d WHERE d.d_u_id=b.driver_id) AS driver_name
          FROM tms_bookings b
          WHERE 1=1";
  $params=[]; $types='';
  if ($th_from) { $sql .= " AND b.scheduled_at >= ?";                           $params[]=$th_from.' 00:00:00'; $types.='s'; }
  if ($th_to)   { $sql .= " AND b.scheduled_at < DATE_ADD(?, INTERVAL 1 DAY)";  $params[]=$th_to.' 00:00:00';   $types.='s'; }
  if ($th_status!==''){ $sql .= " AND b.status = ?";                             $params[]=$th_status;           $types.='s'; }
  $sql .= " ORDER BY b.scheduled_at DESC, b.booking_id DESC";

  if ($st = $mysqli->prepare($sql)) {
    if ($params) $st->bind_param($types, ...$params);
    $st->execute();
    $res = $st->get_result();
    while ($row = $res->fetch_assoc()) $trip_rows[] = $row;
    $st->close();
  }
} else {
  // Ultra-legacy / nothing available: keep table empty
}

/* ---------- Vehicle list (legacy source, as in your UI) ---------- */
$vehicles = [];
if ($HAS_TMS_VEHICLE) {
  if ($q = $mysqli->query("SELECT v_id AS v_id, CONCAT(v_name,' (',v_reg_no,')') AS label FROM tms_vehicle WHERE deleted_at IS NULL ORDER BY v_name, v_reg_no")) {
    while ($r = $q->fetch_assoc()) $vehicles[] = $r;
    $q->close();
  }
}

/* ---------- Vehicle Report (tms_driver_report + bookings; shows rows even without driver reports) ---------- */
$vehicle_header  = null;
$vehicle_daily   = [];

if ($vr_vehicle_id > 0 && $HAS_TMS_VEHICLE) {
  // Header
  if ($s = $mysqli->prepare("SELECT v_name AS name, v_reg_no AS plate_no, v_status AS status FROM tms_vehicle WHERE v_id=?")) {
    $s->bind_param('i', $vr_vehicle_id);
    $s->execute();
    $vehicle_header = $s->get_result()->fetch_assoc();
    $s->close();
  }

  // Seed map from driver daily reports (if any)
  $byDay = []; // 'YYYY-MM-DD' => metrics
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
          'trips'            => 0, // set below
          'distance_km'      => (float)$row['distance_km'],
          'fuel_used_liters' => (float)$row['fuel_used_liters'],
          'odo_start_km'     => isset($row['odo_start_km']) ? (float)$row['odo_start_km'] : null,
          'odo_end_km'       => isset($row['odo_end_km'])   ? (float)$row['odo_end_km']   : null,
          'duration_seconds' => 0, // not modeled here
        ];
      }
      $s->close();
    }
  }

  // Always overlay trips/day from bookings (fallback to tms_bookings)
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

  // Build final array sorted DESC by date
  if ($byDay) {
    krsort($byDay);
    $vehicle_daily = array_values($byDay);
  } else {
    $vehicle_daily = [];
  }
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
    .actions .btn + .btn{margin-left:.25rem}

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
        <li class="nav-item"><a class="nav-link active" id="tab-fleet" data-toggle="tab" href="#fleet" role="tab">Fleet Summary (Today)</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-history" data-toggle="tab" href="#history" role="tab">Trip History</a></li>
        <li class="nav-item"><a class="nav-link" id="tab-vehicle" data-toggle="tab" href="#vehicle" role="tab">Vehicle Report</a></li>
      </ul>

      <div class="tab-content">

        <!-- Fleet Summary -->
        <section class="tab-pane fade show active print-area" id="fleet" role="tabpanel" aria-labelledby="tab-fleet">
          <div class="kaya-card">
            <div class="d-flex align-items-center kaya-toolbar mb-3">
              <h5 class="m-0">Overview</h5>
              <div class="ml-auto no-print">
                <button class="btn btn-outline-secondary" onclick="printSection('#fleet')"><i class="fas fa-print mr-1"></i> Print</button>
              </div>
            </div>
            <div class="metrics">
              <div class="metric"><span class="label">Total Vehicles</span><span class="value"><?= (int)$fleet['total_vehicles'] ?></span></div>
              <div class="metric"><span class="label">Available</span><span class="value"><?= (int)$fleet['vehicles_available'] ?></span></div>
              <div class="metric"><span class="label">In Use</span><span class="value"><?= (int)$fleet['vehicles_in_use'] ?></span></div>
              <div class="metric"><span class="label">Maintenance</span><span class="value"><?= (int)$fleet['vehicles_maintenance'] ?></span></div>
              <div class="metric"><span class="label">Inactive</span><span class="value"><?= (int)$fleet['vehicles_inactive'] ?></span></div>
              <div class="metric"><span class="label">Trips Today</span><span class="value"><?= (int)$fleet['trips_today'] ?></span></div>
              <div class="metric"><span class="label">Trips In Progress</span><span class="value"><?= (int)$fleet['trips_in_progress'] ?></span></div>
              <div class="metric"><span class="label">Drivers Active Today</span><span class="value"><?= (int)$fleet['drivers_active_today'] ?></span></div>
            </div>
          </div>
        </section>

        <!-- Trip History -->
        <section class="tab-pane fade print-area" id="history" role="tabpanel" aria-labelledby="tab-history">
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
                    <th>Payment</th>
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
                    <td><?= h(ucfirst($r['payment_status'])) ?></td>
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
                <form class="form-inline" method="get">
                  <input type="hidden" name="tab" value="vehicle">
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">Vehicle</label>
                    <select name="vr_vehicle_id" class="form-control" required>
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
                    <input type="date" class="form-control" name="vr_from" value="<?= h($vr_from) ?>" required>
                  </div>
                  <div class="form-group mx-sm-2">
                    <label class="mr-2 muted">To</label>
                    <input type="date" class="form-control" name="vr_to" value="<?= h($vr_to) ?>" required>
                  </div>
                  <button class="btn btn-kaya-primary ml-2">Run</button>
                  <button class="btn btn-outline-secondary ml-2" type="button" onclick="printSection('#vehicle')"><i class="fas fa-print mr-1"></i> Print</button>
                </form>
              </div>
            </div>

            <?php if ($vr_vehicle_id && $vehicle_header): ?>
              <div class="mb-3">
                <strong><?= h($vehicle_header['name']) ?></strong>
                <span class="muted">• <?= h($vehicle_header['plate_no']) ?> • <?= h($vehicle_header['status']) ?></span>
              </div>

              <?php
                $sumTrips=$sumKm=0.0; $sumFuel=0.0; $sumDur=0; $odoStart=null; $odoEnd=null;
                foreach($vehicle_daily as $m){
                  $sumTrips += (int)$m['trips'];
                  $sumKm    += (float)$m['distance_km'];
                  $sumFuel  += (float)$m['fuel_used_liters'];
                  $sumDur   += (int)($m['duration_seconds'] ?? 0);
                  if (!is_null($m['odo_start_km'])) $odoStart = is_null($odoStart)? $m['odo_start_km'] : min($odoStart,$m['odo_start_km']);
                  if (!is_null($m['odo_end_km']))   $odoEnd   = is_null($odoEnd)?   $m['odo_end_km']   : max($odoEnd,$m['odo_end_km']);
                }
              ?>
              <div class="metrics mb-3">
                <div class="metric"><span class="label">Trips</span><span class="value"><?= (int)$sumTrips ?></span></div>
                <div class="metric"><span class="label">Distance (km)</span><span class="value"><?= number_format($sumKm,2) ?></span></div>
                <div class="metric"><span class="label">Fuel (L)</span><span class="value"><?= number_format($sumFuel,2) ?></span></div>
                <div class="metric"><span class="label">Odometer</span><span class="value">
                  <?= is_null($odoStart)||is_null($odoEnd)?'—':(number_format($odoStart,1).' → '.number_format($odoEnd,1)) ?>
                </span></div>
                <div class="metric"><span class="label">Engine/Trip Time (h)</span><span class="value"><?= number_format($sumDur/3600,2) ?></span></div>
              </div>

              <div class="table-responsive">
                <table id="vehicleDailyTable" class="table kaya-table table-borderless table-hover align-middle">
                  <thead class="thead-light">
                    <tr>
                      <th>#</th>
                      <th>Date</th>
                      <th>Trips</th>
                      <th>Distance (km)</th>
                      <th>Fuel (L)</th>
                      <th>Odo Start</th>
                      <th>Odo End</th>
                      <th>Duration (h)</th>
                    </tr>
                  </thead>
                  <tbody>
                    <?php $i=1; foreach($vehicle_daily as $m): ?>
                      <tr>
                        <td><?= $i++ ?></td>
                        <td><?= h($m['service_date']) ?></td>
                        <td><?= (int)$m['trips'] ?></td>
                        <td><?= number_format((float)$m['distance_km'],2) ?></td>
                        <td><?= number_format((float)$m['fuel_used_liters'],2) ?></td>
                        <td><?= is_null($m['odo_start_km'])?'—':number_format((float)$m['odo_start_km'],1) ?></td>
                        <td><?= is_null($m['odo_end_km'])?'—':number_format((float)$m['odo_end_km'],1) ?></td>
                        <td><?= number_format(((int)($m['duration_seconds'] ?? 0))/3600,2) ?></td>
                      </tr>
                    <?php endforeach; ?>
                    <?php if (!$vehicle_daily): ?>
                      <tr><td colspan="8" class="text-center muted">No data for the selected period.</td></tr>
                    <?php endif; ?>
                  </tbody>
                </table>
              </div>
            <?php else: ?>
              <div class="muted">Pick a vehicle and date range, then click <em>Run</em>.</div>
            <?php endif; ?>
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
    searching:false,
    paging:false,
    info:false,
    order:[[1,'desc']]
  });

  function printSection(sel){
    var a = document.querySelector('[href="'+sel+'"]');
    if (a) $(a).tab('show');
    setTimeout(function(){ window.print(); }, 100);
  }
</script>

</body>
</html>
