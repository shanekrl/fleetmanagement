<?php
  session_start();
  include('vendor/inc/config.php');
  include('vendor/inc/checklogin.php');
  check_login();

  // ---------- Helpers ----------
  function count_q(mysqli $db, string $sql){
    if(!$stmt = $db->prepare($sql)) return 0;
    $stmt->execute();
    $stmt->bind_result($n);
    $stmt->fetch();
    $stmt->close();
    return (int)$n;
  }
  function table_exists(mysqli $db, string $table){
    $t = $db->real_escape_string($table);
    $res = $db->query("SHOW TABLES LIKE '{$t}'");
    return $res && $res->num_rows > 0;
  }
  function fetch_one(mysqli $db, string $sql){
    if (!$res = $db->query($sql)) return null;
    $row = $res->fetch_assoc();
    $res->close();
    return $row ?: null;
  }

  // Fleet summary helper (reuses logic from Reports page; prefers view if present)
  function get_fleet_summary(mysqli $db){
    if (table_exists($db,'v_fleet_summary')) {
      if ($r = $db->query("SELECT * FROM v_fleet_summary")) {
        $row = $r->fetch_assoc(); $r->close();
        if ($row) return array_map('intval', $row);
      }
    }
    $out = [
      'total_vehicles'=>0,'vehicles_available'=>0,'vehicles_in_use'=>0,
      'vehicles_maintenance'=>0,'vehicles_inactive'=>0,
      'trips_today'=>0,'trips_in_progress'=>0,'drivers_active_today'=>0,
    ];
    if (table_exists($db,'tms_vehicle')) {
      $sql = "SELECT 
                COUNT(*) AS total_vehicles,
                SUM(LOWER(v_status) LIKE 'avail%') AS vehicles_available,
                SUM(LOWER(v_status) REGEXP 'book|service|in use|in_use|on trip') AS vehicles_in_use,
                SUM(LOWER(v_status) REGEXP 'maint') AS vehicles_maintenance,
                SUM(LOWER(v_status) LIKE 'inactive%') AS vehicles_inactive
              FROM tms_vehicle
              WHERE deleted_at IS NULL";
      if ($q = $db->query($sql)) { $row=$q->fetch_assoc() ?: []; foreach($row as $k=>$v) $out[$k]=(int)$v; $q->close(); }
    }
    if (table_exists($db,'bookings')) {
      $sql = "SELECT 
                SUM(DATE(scheduled_start_at)=CURDATE()) AS trips_today,
                COUNT(DISTINCT CASE 
                  WHEN DATE(scheduled_start_at)=CURDATE() AND status IN ('accepted','in_progress','completed') 
                THEN driver_id END) AS drivers_active_today
              FROM bookings";
      if ($q = $db->query($sql)) { $r=$q->fetch_assoc() ?: []; $out['trips_today']=(int)($r['trips_today']??0); $out['drivers_active_today']=(int)($r['drivers_active_today']??0); $q->close(); }
    } elseif (table_exists($db,'tms_bookings')) {
      $sql = "SELECT 
                SUM(DATE(scheduled_at)=CURDATE()) AS trips_today,
                COUNT(DISTINCT CASE 
                  WHEN DATE(scheduled_at)=CURDATE() AND status IN ('accepted','completed','in_progress') 
                THEN driver_id END) AS drivers_active_today
              FROM tms_bookings";
      if ($q = $db->query($sql)) { $r=$q->fetch_assoc() ?: []; $out['trips_today']=(int)($r['trips_today']??0); $out['drivers_active_today']=(int)($r['drivers_active_today']??0); $q->close(); }
    }
    if (table_exists($db,'booking_runs')) {
      $sql = "SELECT COUNT(*) AS c
              FROM booking_runs
              WHERE pickup_button_at IS NOT NULL
                AND dropoff_button_at IS NULL
                AND DATE(COALESCE(pickup_button_at, NOW())) = CURDATE()";
      if ($q = $db->query($sql)) { $out['trips_in_progress']=(int)($q->fetch_assoc()['c'] ?? 0); $q->close(); }
    }
    return $out;
  }

  // ---------- What exists? ----------
  $hasVehiclesTbl   = table_exists($mysqli,'vehicles');
  $hasBookingsTbl   = table_exists($mysqli,'bookings');
  $hasAccountsTbl   = table_exists($mysqli,'accounts');
  $hasDriverProfTbl = table_exists($mysqli,'driver_profile');
  $hasFleetView     = table_exists($mysqli,'v_fleet_summary');

  $hasTmsVehicle    = table_exists($mysqli,'tms_vehicle');
  $hasTmsUser       = table_exists($mysqli,'tms_user');
  $hasTmsDriver     = table_exists($mysqli,'tms_user_add_driver');
  $hasAudit         = table_exists($mysqli,'tms_audit_log');

  // ---------- Fleet summary (for dashboard table) ----------
  $fleet = get_fleet_summary($mysqli);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('vendor/inc/head.php'); ?>

  <!-- Inter + tiny Tailwind token usage (cards/grid only) -->
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          fontFamily: { sans: ['Inter','ui-sans-serif','system-ui'] },
          colors: { kaya:{ navy:'#0A0F2C', ink:'#000047' } },
          borderRadius: { '2xl':'1rem' }
        }
      }
    }
  </script>
  <style>html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}</style>
</head>

<body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>

  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>

    <div id="content-wrapper">
      <div class="container-fluid">

        <h1 class="kaya-page-title">Admin Dashboard</h1>

        <!-- ===== Fleet Summary (Today) replaces KPI cards ===== -->
        <section class="bg-white rounded-2xl shadow p-6 mb-8">
          <h3 class="text-base font-semibold text-kaya-ink mb-4">Fleet Summary (Today)</h3>
          <div class="overflow-x-auto">
            <table class="min-w-full text-left text-sm">
              <thead>
                <tr class="text-gray-500">
                  <th class="py-2 pr-4 font-medium">Metric</th>
                  <th class="py-2 pr-4 font-medium">Value</th>
                </tr>
              </thead>
              <tbody class="divide-y divide-gray-100">
                <tr><td class="py-2 pr-4">Total Vehicles</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['total_vehicles'] ?></td></tr>
                <tr><td class="py-2 pr-4">Available</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['vehicles_available'] ?></td></tr>
                <tr><td class="py-2 pr-4">In Use</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['vehicles_in_use'] ?></td></tr>
                <tr><td class="py-2 pr-4">Maintenance</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['vehicles_maintenance'] ?></td></tr>
                <tr><td class="py-2 pr-4">Inactive</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['vehicles_inactive'] ?></td></tr>
                <tr><td class="py-2 pr-4">Trips Today</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['trips_today'] ?></td></tr>
                <tr><td class="py-2 pr-4">Trips In Progress</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['trips_in_progress'] ?></td></tr>
                <tr><td class="py-2 pr-4">Drivers Active Today</td><td class="py-2 pr-4 font-semibold"><?= (int)$fleet['drivers_active_today'] ?></td></tr>
              </tbody>
            </table>
          </div>
        </section>

        <!-- ===== Two-up cards: Recent Bookings + Live Vehicles ===== -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-8">

          <!-- Recent Bookings -->
          <section class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-base font-semibold text-kaya-ink mb-4">Recent Bookings</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full text-left text-sm">
                <thead>
                  <tr class="text-gray-500">
                    <th class="py-2 pr-4 font-medium">Customer</th>
                    <th class="py-2 pr-4 font-medium">Driver</th>
                    <th class="py-2 pr-4 font-medium">When</th>
                    <th class="py-2 pr-4 font-medium">From → To</th>
                    <th class="py-2 pr-4 font-medium">Status</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php if ($hasBookingsTbl):
                    $q = $mysqli->prepare("
                      SELECT b.contact_name,
                             COALESCE(a.name,'—') AS driver_name,
                             b.scheduled_start_at,
                             b.pickup_point, b.dropoff_point,
                             b.status
                        FROM bookings b
                   LEFT JOIN accounts a ON a.id=b.driver_id
                    ORDER BY b.created_at DESC
                       LIMIT 8
                    ");
                    $q->execute();
                    $res = $q->get_result();
                    while($b = $res->fetch_object()):
                      $when = $b->scheduled_start_at ? date('M j, Y g:i A', strtotime($b->scheduled_start_at)) : '—';
                      $st   = (string)$b->status;
                      $badge  = in_array($st,['in_progress']) ? 'bg-green-100 text-green-700'
                              : ($st==='accepted'              ? 'bg-blue-100  text-blue-700'
                              : ($st==='completed'             ? 'bg-blue-100  text-blue-700'
                              : ($st==='cancelled'             ? 'bg-red-100   text-red-700'
                              : ($st==='rejected'              ? 'bg-red-100   text-red-700'
                              : 'bg-gray-100 text-gray-700'))));
                  ?>
                  <tr>
                    <td class="py-2 pr-4"><?= htmlspecialchars($b->contact_name ?: '—') ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($b->driver_name) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($when) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($b->pickup_point) ?> → <?= htmlspecialchars($b->dropoff_point) ?></td>
                    <td class="py-2 pr-4"><span class="px-2 py-1 rounded <?= $badge ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $st))) ?></span></td>
                  </tr>
                  <?php endwhile; $q->close(); ?>
                  <?php elseif ($hasTmsUser): // legacy fallback ?>
                  <?php
                    $q = $mysqli->prepare("
                      SELECT u_fname, u_lname, u_car_driver, u_car_date, u_car_time,
                             u_car_pickup, u_car_destination, u_car_book_status
                      FROM tms_user
                      ORDER BY u_id DESC
                      LIMIT 8
                    ");
                    $q->execute();
                    $res = $q->get_result();
                    while($b = $res->fetch_object()):
                      $when = trim(($b->u_car_date ?: '').' '.($b->u_car_time ?: ''));
                      $st   = $b->u_car_book_status ?: 'Pending';
                      $badge  = $st==='Available'   ? 'bg-green-100 text-green-700'
                              : ($st==='Approved'   ? 'bg-green-100 text-green-700'
                              : ($st==='Completed'  ? 'bg-blue-100  text-blue-700'
                              : ($st==='Maintenance'? 'bg-yellow-100 text-yellow-700'
                              : ($st==='In Active'  ? 'bg-red-100 text-red-700'
                              : ($st==='Cancel'     ? 'bg-red-100 text-red-700'
                                                    : 'bg-gray-100 text-gray-700')))));
                  ?>
                  <tr>
                    <td class="py-2 pr-4"><?= htmlspecialchars(trim($b->u_fname.' '.$b->u_lname)) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($b->u_car_driver) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($when) ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($b->u_car_pickup) ?> → <?= htmlspecialchars($b->u_car_destination) ?></td>
                    <td class="py-2 pr-4"><span class="px-2 py-1 rounded <?= $badge ?>"><?= htmlspecialchars($st) ?></span></td>
                  </tr>
                  <?php endwhile; $q->close(); else: ?>
                  <tr><td class="py-3 text-gray-500" colspan="5">No bookings table found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>

          <!-- Live Vehicles -->
          <section class="bg-white rounded-2xl shadow p-6">
            <h3 class="text-base font-semibold text-kaya-ink mb-4">Live Vehicles</h3>
            <div class="overflow-x-auto">
              <table class="min-w-full text-left text-sm">
                <thead>
                  <tr class="text-gray-500">
                    <th class="py-2 pr-4 font-medium">Vehicle</th>
                    <th class="py-2 pr-4 font-medium">Status</th>
                    <th class="py-2 pr-4 font-medium">Pick Up</th>
                    <th class="py-2 pr-4 font-medium">Destination</th>
                  </tr>
                </thead>
                <tbody class="divide-y divide-gray-100">
                  <?php if ($hasVehiclesTbl):
                    $sql = "
                      SELECT v.name AS vehicle_name, v.plate_no, v.status AS vehicle_status,
                             (SELECT b.pickup_point
                                FROM bookings b
                               WHERE b.vehicle_id=v.id AND b.status IN ('accepted','in_progress')
                               ORDER BY b.scheduled_start_at DESC LIMIT 1) AS last_pick,
                             (SELECT b.dropoff_point
                                FROM bookings b
                               WHERE b.vehicle_id=v.id AND b.status IN ('accepted','in_progress')
                               ORDER BY b.scheduled_start_at DESC LIMIT 1) AS last_dest,
                             (SELECT b.status
                                FROM bookings b
                               WHERE b.vehicle_id=v.id AND b.status IN ('accepted','in_progress')
                               ORDER BY b.scheduled_start_at DESC LIMIT 1) AS booking_status
                        FROM vehicles v
                       ORDER BY FIELD(v.status,'in_use','maintenance','available','inactive'), v.name
                       LIMIT 8
                    ";
                    if ($res = $mysqli->query($sql)):
                      while($v = $res->fetch_object()):
                        $status = $v->booking_status ?: $v->vehicle_status;
                        $status_lc = strtolower((string)$status);
                        $badge  = $status_lc==='in_progress' ? 'bg-green-100 text-green-700'
                                : ($status_lc==='accepted'    ? 'bg-blue-100  text-blue-700'
                                : ($status_lc==='maintenance' ? 'bg-yellow-100 text-yellow-700'
                                : ($status_lc==='inactive'    ? 'bg-red-100   text-red-700'
                                                              : 'bg-gray-100  text-gray-700')));
                  ?>
                  <tr>
                    <td class="py-2 pr-4"><?= htmlspecialchars($v->vehicle_name) ?> (<?= htmlspecialchars($v->plate_no) ?>)</td>
                    <td class="py-2 pr-4"><span class="px-2 py-1 rounded <?= $badge ?>"><?= htmlspecialchars(ucwords(str_replace('_',' ', $status))) ?></span></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($v->last_pick ?: '—') ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($v->last_dest ?: '—') ?></td>
                  </tr>
                  <?php endwhile; else: ?>
                  <tr><td class="py-3 text-gray-500" colspan="4">No vehicle data.</td></tr>
                  <?php endif; ?>

                  <?php elseif ($hasTmsVehicle): // legacy fallback ?>
                  <?php
                    $sql = "
                      SELECT v.v_reg_no, v.v_driver, v.v_status,
                             (SELECT u_car_pickup FROM tms_user u WHERE u.u_car_regno=v.v_reg_no ORDER BY u_id DESC LIMIT 1) AS last_pick,
                             (SELECT u_car_destination FROM tms_user u WHERE u.u_car_regno=v.v_reg_no ORDER BY u_id DESC LIMIT 1) AS last_dest
                        FROM tms_vehicle v
                       ORDER BY v.v_id DESC
                       LIMIT 8
                    ";
                    if ($res = $mysqli->query($sql)):
                      while($v = $res->fetch_object()):
                        $st = $v->v_status ?: '—';
                        $badge  = $st==='Available'        ? 'bg-green-100 text-green-700'
                                : ($st==='Booked'          ? 'bg-blue-100  text-blue-700'
                                : ($st==='On Trip'         ? 'bg-blue-100  text-blue-700'
                                : ($st==='Undermaintenance'? 'bg-yellow-100 text-yellow-700'
                                                             : 'bg-gray-100 text-gray-700')));
                  ?>
                  <tr>
                    <td class="py-2 pr-4"><?= htmlspecialchars($v->v_reg_no) ?> — <?= htmlspecialchars($v->v_driver) ?></td>
                    <td class="py-2 pr-4"><span class="px-2 py-1 rounded <?= $badge ?>"><?= htmlspecialchars($st) ?></span></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($v->last_pick ?: '—') ?></td>
                    <td class="py-2 pr-4"><?= htmlspecialchars($v->last_dest ?: '—') ?></td>
                  </tr>
                  <?php endwhile; else: ?>
                  <tr><td class="py-3 text-gray-500" colspan="4">No vehicle data.</td></tr>
                  <?php endif; else: ?>
                  <tr><td class="py-3 text-gray-500" colspan="4">No vehicles table found.</td></tr>
                  <?php endif; ?>
                </tbody>
              </table>
            </div>
          </section>
        </div>

        <!-- (Optional) Recent Activity block remains commented out -->

      </div><!-- /.container-fluid -->

      <?php include('vendor/inc/footer.php'); ?>
    </div><!-- /#content-wrapper -->
  </div><!-- /#wrapper -->

  <!-- Vendor JS -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
</body>
</html>
