<?php
// usr/driver-trip-start.php — full-screen live trip card
session_start();
require_once __DIR__ . '/../vendor/inc/config.php';
require_once __DIR__ . '/../vendor/inc/checklogin.php';
check_login();

$driverAccountId = (int)($_SESSION['account_id'] ?? $_SESSION['driver_account_id'] ?? 0);
$bookingId = (int)($_GET['booking_id'] ?? 0);

if (!$driverAccountId || !$bookingId) {
  header('Location: ../user-dashboard.php'); exit;
}

$booking = null;
if ($s = $mysqli->prepare("
    SELECT id AS booking_id, booking_type, pickup_point, dropoff_point,
           COALESCE(scheduled_start_at, created_at) AS scheduled_start_at,
           status, contact_name, contact_phone, vehicle_id
      FROM bookings
     WHERE id=? AND driver_id=? LIMIT 1
")) {
  $s->bind_param('ii', $bookingId, $driverAccountId);
  $s->execute();
  $booking = $s->get_result()->fetch_assoc();
  $s->close();
}
if (!$booking) { header('Location: ../user-dashboard.php'); exit; }

// helpers
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
  <?php include('../vendor/inc/head.php'); ?>
  <style>
    /* hide chrome to maximize space */
    .sidebar, .sticky-footer { display:none !important; }
    #content-wrapper, .container-fluid { padding:0 !important; }
    body, html { height:100%; }
  </style>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen">

  <!-- Top bar -->
  <div class="w-full bg-[#0B0F2F] text-white">
    <div class="max-w-[900px] mx-auto px-4 py-3">
      <div class="flex items-center justify-between">
        <div class="min-w-0">
          <div class="font-semibold whitespace-normal">
            <?= htmlspecialchars(($booking['pickup_point'] ?? '—').' → '.($booking['dropoff_point'] ?? '—')) ?>
          </div>
          <div class="text-[12px] opacity-80"><?= fmt_compact($booking['scheduled_start_at']) ?></div>
        </div>
        <a href="../user-dashboard.php" class="text-white/80 hover:text-white text-sm underline">Back</a>
      </div>
    </div>
  </div>

  <!-- Map + controls (full height) -->
  <div class="max-w-[900px] mx-auto w-full h-[calc(100vh-60px)] px-4 py-3">
    <div class="w-full h-[65%] bg-slate-200 rounded-xl mb-3"></div> <!-- map placeholder -->

    <div class="mb-3 flex gap-2 items-center">
      <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= badge_color($booking['status']) ?>">
        <?= strtoupper($booking['status']) ?>
      </span>
      <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= ($booking['booking_type']==='personal'?'bg-amber-500':'bg-sky-500') ?>">
        <?= strtoupper($booking['booking_type'] ?: 'ADMIN') ?>
      </span>
    </div>

    <div class="flex gap-2 flex-wrap mb-2">
      <?php if ($booking['status']==='accepted'): ?>
        <button id="btnStart" class="px-4 py-2 rounded-xl text-white bg-kaya-navy text-sm font-semibold">
          Start Trip
        </button>
        <button id="btnCancelAcc" class="px-4 py-2 rounded-xl border border-slate-300 text-sm font-semibold">
          Cancel Trip
        </button>
      <?php elseif ($booking['status']==='in_progress'): ?>
        <button id="btnUndo" class="px-4 py-2 rounded-xl bg-white text-[#0B0F2F] font-semibold border border-slate-300">
          Cancel (Undo Start)
        </button>
        <button id="btnEnd" class="px-4 py-2 rounded-xl text-white bg-green-600 text-sm font-semibold">
          End Trip
        </button>
      <?php else: ?>
        <a class="px-4 py-2 rounded-xl text-white bg-kaya-navy text-sm font-semibold"
           href="../user-dashboard.php">Back to Dashboard</a>
      <?php endif; ?>
    </div>

    <?php if ($booking['contact_name'] || $booking['contact_phone']): ?>
      <div class="mt-2 text-xs">
        <div>Customer: <span class="font-semibold"><?= htmlspecialchars($booking['contact_name'] ?: '—') ?></span></div>
        <div>Contact No: <span class="font-semibold"><?= htmlspecialchars($booking['contact_phone'] ?: '—') ?></span></div>
      </div>
    <?php endif; ?>
  </div>

  <script>
    // use correct path to the actions endpoint whatever the folder depth
    const ACTION_URL = window.location.pathname.includes('/usr/')
      ? '../driver-actions.php' : 'driver-actions.php';

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

    const bookingId = <?= (int)$booking['booking_id'] ?>;
    const bookingType = <?= json_encode($booking['booking_type'] ?: 'admin') ?>;

    const goBack = () => window.location.href = '../user-dashboard.php';

    const askReasonIfAdmin = (title) => {
      if (bookingType === 'admin') {
        const reason = prompt(title || 'Reason?');
        if (!reason) return null;
        return reason;
      }
      return '';
    };

    // accepted -> in_progress
    const btnStart = document.getElementById('btnStart');
    if (btnStart) btnStart.addEventListener('click', ()=>{
      postJSON({ action:'start_trip', booking_id:bookingId })
        .then(()=>location.reload())
        .catch(e=>alert(e.message));
    });

    // accepted -> cancelled (requires reason for admin)
    const btnCancelAcc = document.getElementById('btnCancelAcc');
    if (btnCancelAcc) btnCancelAcc.addEventListener('click', ()=>{
      const reason = askReasonIfAdmin('Reason for cancellation?');
      if (reason===null) return;
      postJSON({ action:'cancel_trip', booking_id:bookingId, reason })
        .then(goBack)
        .catch(e=>alert(e.message));
    });

    // in_progress -> accepted (undo)
    const btnUndo = document.getElementById('btnUndo');
    if (btnUndo) btnUndo.addEventListener('click', ()=>{
      if (!confirm('Undo trip start and go back to Accepted?')) return;
      postJSON({ action:'undo_start', booking_id:bookingId })
        .then(()=>location.reload())
        .catch(e=>alert(e.message));
    });

    // in_progress -> completed
    const btnEnd = document.getElementById('btnEnd');
    if (btnEnd) btnEnd.addEventListener('click', ()=>{
      if (!confirm('End trip now?')) return;
      postJSON({ action:'end_trip', booking_id:bookingId })
        .then(goBack)
        .catch(e=>alert(e.message));
    });
  </script>

  <!-- keep vendor bundles as-is -->
  <script src="../vendor/jquery/jquery.min.js"></script>
  <script src="../vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="../vendor/jquery-easing/jquery.easing.min.js"></script>
</body>
</html>
