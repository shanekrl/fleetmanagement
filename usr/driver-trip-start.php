<?php
// usr/driver-trip-start.php — full-screen live trip card
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$driverAccountId = require_driver();
$bookingId       = (int)($_GET['booking_id'] ?? 0);
if (!$driverAccountId || !$bookingId) { header('Location: user-dashboard.php'); exit; }

/* Load booking assigned to this driver */
$booking = null;
if ($s = $mysqli->prepare("
  SELECT id AS booking_id, booking_type, pickup_point, dropoff_point,
         COALESCE(scheduled_start_at, created_at) AS scheduled_start_at,
         status, contact_name, contact_phone, vehicle_id
    FROM bookings
   WHERE id=? AND driver_id=? LIMIT 1
")) {
  $s->bind_param('ii', $bookingId, $driverAccountId);
  $s->execute(); $booking = $s->get_result()->fetch_assoc(); $s->close();
}
if (!$booking) { header('Location: user-dashboard.php'); exit; }

/* If user landed here while the trip is still ACCEPTED, flip to IN PROGRESS */
if ($booking['status'] === 'accepted') {
  if ($u = $mysqli->prepare("UPDATE bookings SET status='in_progress', updated_at=NOW()
                              WHERE id=? AND driver_id=? AND status='accepted'")) {
    $u->bind_param('ii', $bookingId, $driverAccountId);
    $u->execute(); $u->close();
  }
  // Ensure there's a run row + pickup timestamp
  $mysqli->query("INSERT INTO booking_runs(booking_id, vehicle_id, driver_id, pickup_button_at)
                   SELECT b.id, b.vehicle_id, b.driver_id, NOW()
                     FROM bookings b
                    WHERE b.id={$bookingId}
                      AND NOT EXISTS(SELECT 1 FROM booking_runs r WHERE r.booking_id=b.id)");
  $mysqli->query("UPDATE booking_runs
                     SET pickup_button_at = COALESCE(pickup_button_at, NOW())
                   WHERE booking_id = {$bookingId}");
  // Refresh booking in memory
  if ($s = $mysqli->prepare("SELECT status FROM bookings WHERE id=? LIMIT 1")) {
    $s->bind_param('i', $bookingId); $s->execute();
    $s->bind_result($st); if ($s->fetch()) $booking['status']=$st; $s->close();
  }
}

/* helpers */
function fmt_compact($dt){
  if(!$dt) return '—';
  $ts = strtotime($dt);
  return (date('Y-m-d',$ts)===date('Y-m-d'))
    ? 'TODAY, '.strtoupper(date('g:i A',$ts))
    : strtoupper(date('M j, g:i A',$ts));
}
function badge_color($s){
  return [
    'in_progress'=>'bg-green-600',
    'accepted'   =>'bg-blue-600',
    'completed'  =>'bg-blue-600',
    'cancelled'  =>'bg-red-600',
    'rejected'   =>'bg-red-600',
    'pending'    =>'bg-gray-600',
    'awaiting_driver'=>'bg-gray-600',
  ][$s] ?? 'bg-gray-500';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include __DIR__ . '/vendor/inc/head.php'; ?>
  <style>
    .sidebar, .sticky-footer { display:none !important; }
    #content-wrapper { padding:0 !important; }
    html, body { height:100%; }
    #map { height: 370px; width: 100%; border-radius: 20px; }
  </style>
    <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">
  <?php include __DIR__ . '/vendor/inc/nav.php'; ?>

  <!-- Header -->
  <div class="w-full bg-[#0B0F2F] text-white">
    <div class="max-w-[900px] mx-auto px-4 py-3">
      <div class="flex items-center justify-between">
        <div class="min-w-0">
          <div class="font-semibold whitespace-normal">
            <p id="pickup_point" class="inline">
              <?= htmlspecialchars($booking['pickup_point'] ?? '—') ?>
            </p>
            →
            <p id="dropoff_point" class="inline">
              <?= htmlspecialchars($booking['dropoff_point'] ?? '—') ?>
            </p>
          </div>
          <div class="text-[12px] opacity-80"><?= fmt_compact($booking['scheduled_start_at']) ?></div>
        </div>
        <a href="user-dashboard.php" class="text-white/80 hover:text-white text-sm underline">Back</a>
      </div>
    </div>
  </div>

  <!-- Map + controls -->
  <div class="max-w-[900px] mx-auto w-full h-[calc(100vh-120px)] px-4 py-3">
    <div class="w-full h-[65%] bg-slate-200 rounded-xl mb-3">
      <div class="kaya-map">
        <div id="map"></div>
      </div>
    </div>

    <div class="mb-3 flex gap-2 items-center">
      <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= badge_color($booking['status']) ?>">
        <?= $booking['status']==='in_progress' ? 'ON GOING' : strtoupper($booking['status']) ?>
      </span>
      <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= ($booking['booking_type']==='personal'?'bg-amber-500':'bg-sky-500') ?>">
        <?= strtoupper($booking['booking_type'] ?: 'ADMIN') ?>
      </span>
    </div>

    <!-- Trip Started! + two buttons only -->
    <?php if ($booking['status']==='in_progress'): ?>
      <div class="text-sm font-semibold text-[#0B0F2F] mb-2">Trip Started!</div>
      <div class="flex gap-2 flex-wrap mb-2">
        <button id="btnUndo" class="px-4 py-2 rounded-xl border border-slate-300 text-sm font-semibold bg-white text-[#0B0F2F]">
          Cancel
        </button>
        <button id="btnEnd" class="px-4 py-2 rounded-xl text-white bg-red-600 text-sm font-semibold">
          End Trip
        </button>
      </div>
    <?php else: ?>
      <a class="px-4 py-2 rounded-xl text-white bg-[#000047] text-sm font-semibold" href="user-dashboard.php">
        Back to Dashboard
      </a>
    <?php endif; ?>

    <?php if ($booking['contact_name'] || $booking['contact_phone']): ?>
      <div class="mt-2 text-xs">
        <div>Customer: <span class="font-semibold"><?= htmlspecialchars($booking['contact_name'] ?: '—') ?></span></div>
        <div>Contact No: <span class="font-semibold"><?= htmlspecialchars($booking['contact_phone'] ?: '—') ?></span></div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    const ACTION_URL = 'driver-actions.php';
    const bookingId  = <?= (int)$booking['booking_id'] ?>;

    const postJSON = (payload) =>
      fetch(ACTION_URL, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(payload)
      }).then(async r=>{
        const t = await r.text(); let j; try{ j=JSON.parse(t); }catch{ j={error:t}; }
        if (!r.ok || j.error) throw new Error(j.error || `Request failed (${r.status})`);
        return j;
      });

    const goBack = () => window.location.href = 'user-dashboard.php';

    // Cancel = undo start (back to Accepted / Incoming Trips)
    const btnUndo = document.getElementById('btnUndo');
    if (btnUndo) btnUndo.addEventListener('click', ()=>{
      if (!confirm('Cancel this run and return it to Incoming Trips?')) return;
      postJSON({ action:'undo_start', booking_id: bookingId })
        .then(goBack)
        .catch(e=>alert(e.message));
    });

    // End Trip = complete
    const btnEnd = document.getElementById('btnEnd');
    if (btnEnd) btnEnd.addEventListener('click', ()=>{
      if (!confirm('End trip now?')) return;
      postJSON({ action:'end_trip', booking_id: bookingId })
        .then(goBack)
        .catch(e=>alert(e.message));
    });
  </script>

  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.smooth_marker_bouncing"></script>
  <script src="https://unpkg.com/leaflet.smoothmarkerbouncing"></script>
  <script src="https://unpkg.com/leaflet.marker.slideto"></script>
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="vendor/js/driver_maps.js"></script>
</body>
</html>
