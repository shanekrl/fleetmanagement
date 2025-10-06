<?php
// usr/user-dashboard.php — Driver dashboard using shared config + guards
session_start();

require_once __DIR__ . '/../admin/vendor/inc/config.php';      // share the same mysqli/config
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';  // unified guards

// Enforce authentication & driver role
check_login('driver');                 // sets session if legacy
$driverAccountId = require_driver();   // returns accounts.id (or legacy fallback)

// error visibility (remove on production)
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
ini_set('display_errors', 1);
error_reporting(E_ALL);

// Data buckets
$tripRequests  = [];
$incomingTrips = [];

// Fetch data only if we have a valid driver account id
if ($driverAccountId > 0) {
  // Trip Requests (awaiting decision)
  if ($s = $mysqli->prepare("
      SELECT id AS booking_id, booking_type, pickup_point, dropoff_point,
             COALESCE(scheduled_start_at, created_at) AS scheduled_start_at,
             status, contact_name, contact_phone
        FROM bookings
       WHERE driver_id=? AND status IN ('pending','awaiting_driver')
       ORDER BY COALESCE(scheduled_start_at, created_at) ASC, id ASC
  ")) {
    $s->bind_param('i', $driverAccountId);
    $s->execute();
    $tripRequests = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
  }

  // Incoming (accepted or in-progress)
  if ($s = $mysqli->prepare("
      SELECT id AS booking_id, booking_type, pickup_point, dropoff_point,
             COALESCE(scheduled_start_at, created_at) AS scheduled_start_at,
             status, contact_name, contact_phone
        FROM bookings
       WHERE driver_id=? AND status IN ('accepted','in_progress')
       ORDER BY COALESCE(scheduled_start_at, NOW()) ASC, id ASC
  ")) {
    $s->bind_param('i', $driverAccountId);
    $s->execute();
    $incomingTrips = $s->get_result()->fetch_all(MYSQLI_ASSOC);
    $s->close();
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

  <!-- fallback so start/resume is visible even if Tailwind fails -->
  <style>
    .btn-start {
      background:#000047 !important;
      color:#fff !important;
      padding:.5rem .75rem !important;
      border-radius:.75rem !important;
      font-weight:600 !important;
      display:inline-block !important;
      text-decoration:none !important;
    }
    .disclosure .route{overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
    .disclosure[aria-expanded="true"] .route{white-space:normal;overflow:visible}
    .disclosure[aria-expanded="false"] + .panel{display:none}
    .disclosure[aria-expanded="true"] + .panel{display:block}
    .disclosure .fa-chevron-down{transition:transform .2s ease}
    .disclosure[aria-expanded="true"] .fa-chevron-down{transform:rotate(180deg)}
  </style>
</head>
<body id="page-top" class="bg-slate-50 text-slate-900">
  <?php include __DIR__ . '/vendor/inc/nav.php'; ?>

  <div id="wrapper">
    <?php include __DIR__ . '/vendor/inc/sidebar.php'; ?>

    <div id="content-wrapper" class="w-full">
      <div class="container-fluid flex justify-center">
        <div class="w-full md:max-w-[520px]">
          <h1 class="font-extrabold text-[22px] text-kaya-navy mt-3 mb-2">Driver Dashboard</h1>

          <!-- Trip Requests -->
          <section class="mb-6">
            <h2 class="text-sm font-semibold text-slate-600 mb-2">Trip Requests</h2>
            <?php if (!$driverAccountId): ?>
              <p class="text-sm text-red-600">Driver account not linked to this session.</p>
            <?php elseif (!$tripRequests): ?>
              <p class="text-sm text-slate-500">No trip requests right now.</p>
            <?php else: foreach($tripRequests as $t):
              $bid   = (int)$t['booking_id'];
              $route = trim(($t['pickup_point'] ?? '—').' → '.($t['dropoff_point'] ?? '—'));
            ?>
              <article class="rounded-2xl shadow-lg mb-3 overflow-hidden">
                <button class="disclosure w-full bg-[#0B0F2F] text-white px-4 py-3 text-left" aria-expanded="false">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <div class="font-semibold route"><?= htmlspecialchars($route) ?></div>
                      <div class="text-[12px] opacity-80"><?= fmt_compact($t['scheduled_start_at']) ?></div>
                    </div>
                    <i class="fas fa-chevron-down ml-2"></i>
                  </div>
                </button>

                <div class="panel bg-white px-4 pb-4 pt-3">
                  <div class="mb-3 flex gap-2">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= badge_color($t['status']) ?>"><?= strtoupper($t['status']) ?></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= ($t['booking_type']==='personal'?'bg-amber-500':'bg-sky-500') ?>">
                      <?= strtoupper($t['booking_type'] ?: 'ADMIN') ?>
                    </span>
                  </div>

                  <div class="flex gap-3">
                    <button class="btn-accept inline-flex items-center justify-center px-4 py-2 rounded-xl bg-white text-[#0B0F2F] font-semibold border border-white shadow-sm"
                            data-id="<?= $bid ?>">Accept</button>
                    <button class="btn-reject inline-flex items-center justify-center px-4 py-2 rounded-xl border border-slate-300 font-semibold"
                            data-id="<?= $bid ?>">Reject</button>
                  </div>

                  <?php if ($t['contact_name'] || $t['contact_phone']): ?>
                    <div class="mt-3 text-xs">
                      <div>Customer: <span class="font-semibold"><?= htmlspecialchars($t['contact_name'] ?: '—') ?></span></div>
                      <div>Contact No: <span class="font-semibold"><?= htmlspecialchars($t['contact_phone'] ?: '—') ?></span></div>
                    </div>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; endif; ?>
          </section>

          <!-- Incoming Trips -->
          <section class="mb-6">
            <h2 class="text-sm font-semibold text-slate-600 mb-2">Incoming Trips</h2>
            <?php if ($driverAccountId && !$incomingTrips): ?>
              <p class="text-sm text-slate-500">No incoming trips yet.</p>
            <?php endif; ?>
            <?php foreach($incomingTrips as $t):
              $bid   = (int)$t['booking_id'];
              $route = trim(($t['pickup_point'] ?? '—').' → '.($t['dropoff_point'] ?? '—'));
            ?>
              <article class="rounded-2xl shadow-lg mb-3 overflow-hidden">
                <button class="disclosure w-full bg-[#0B0F2F] text-white px-4 py-3 text-left" aria-expanded="false">
                  <div class="flex items-start justify-between gap-2">
                    <div class="min-w-0">
                      <div class="font-semibold route"><?= htmlspecialchars($route) ?></div>
                      <div class="text-[12px] opacity-80"><?= fmt_compact($t['scheduled_start_at']) ?></div>
                    </div>
                    <i class="fas fa-chevron-down ml-2"></i>
                  </div>
                </button>

                <div class="panel bg-white px-4 pb-4 pt-3">
                  <div class="mb-3 w-full h-[180px] bg-slate-200 rounded-xl"></div>

                  <div class="mb-3 flex gap-2">
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= badge_color($t['status']) ?>"><?= strtoupper($t['status']) ?></span>
                    <span class="px-2 py-0.5 rounded-full text-[11px] font-bold text-white <?= ($t['booking_type']==='personal'?'bg-amber-500':'bg-sky-500') ?>">
                      <?= strtoupper($t['booking_type'] ?: 'ADMIN') ?>
                    </span>
                  </div>

                  <div class="flex gap-2 flex-wrap">
                    <?php
                      $status = strtolower(trim((string)$t['status']));
                      $btype  = strtolower(trim((string)$t['booking_type'] ?: 'admin'));
                      if ($status === 'accepted'):
                    ?>
                      <a class="btn-start text-sm"
                         href="driver-trip-start.php?booking_id=<?= $bid ?>">Start Trip</a>

                      <!-- Cancel via API (no redirect). Reason required for admin bookings -->
                      <button
                        type="button"
                        class="btn-cancel px-3 py-2 rounded-xl border border-slate-300 text-sm font-semibold"
                        data-id="<?= $bid ?>"
                        data-type="<?= htmlspecialchars($btype) ?>">
                        Cancel Trip
                      </button>
                    <?php else: /* in_progress */ ?>
                      <a class="btn-start text-sm"
                         href="driver-trip-start.php?booking_id=<?= $bid ?>">Resume</a>
                      <button class="px-3 py-2 rounded-xl bg-red-600/60 text-white text-sm font-semibold" disabled>End Trip (on map)</button>
                    <?php endif; ?>
                  </div>

                  <?php if ($t['contact_name'] || $t['contact_phone']): ?>
                    <div class="mt-3 text-xs">
                      <div>Customer: <span class="font-semibold"><?= htmlspecialchars($t['contact_name'] ?: '—') ?></span></div>
                      <div>Contact No: <span class="font-semibold"><?= htmlspecialchars($t['contact_phone'] ?: '—') ?></span></div>
                    </div>
                  <?php endif; ?>
                </div>
              </article>
            <?php endforeach; ?>
          </section>
        </div>
      </div>

      <?php include __DIR__ . '/vendor/inc/footer.php'; ?>
    </div>
  </div>

  <!-- Single-open accordion -->
  <script>
    document.querySelectorAll('.disclosure').forEach(b=>{
      b.addEventListener('click',()=>{
        const isOpen = b.getAttribute('aria-expanded')==='true';
        document.querySelectorAll('.disclosure').forEach(x=>x.setAttribute('aria-expanded','false'));
        if(!isOpen) b.setAttribute('aria-expanded','true');
      });
    });

    // Actions api endpoint (same folder)
    const ACTION_URL = 'driver-actions.php';

    const postJSON = (payload) =>
      fetch(ACTION_URL, {
        method:'POST',
        headers:{'Content-Type':'application/json'},
        body:JSON.stringify(payload)
      }).then(async r=>{
        const text = await r.text();
        let data;
        try { data = JSON.parse(text); } catch { data = { error:text }; }
        if (!r.ok || data.error) throw new Error(data.error || `Request failed (${r.status})`);
        return data;
      });

    // Accept or Reject (Requests)
    document.querySelectorAll('.btn-accept').forEach(b=>b.addEventListener('click',(e)=>{
      e.stopPropagation();
      postJSON({ action:'accept', booking_id:b.dataset.id })
        .then(()=>location.reload())
        .catch(err=>alert(err.message));
    }));
    document.querySelectorAll('.btn-reject').forEach(b=>b.addEventListener('click',(e)=>{
      e.stopPropagation();
      const reason = prompt('Reason for rejection?'); if(!reason) return;
      postJSON({ action:'reject', booking_id:b.dataset.id, reason })
        .then(()=>location.reload())
        .catch(err=>alert(err.message));
    }));

    // Cancel Trip - requires reason for admin bookings
    document.querySelectorAll('.btn-cancel').forEach(btn => {
      btn.addEventListener('click', (e) => {
        e.preventDefault();
        e.stopPropagation();

        const bookingId = Number(btn.dataset.id);
        const type = String(btn.dataset.type || 'admin').toLowerCase();

        let reason = '';
        if (type === 'admin') {
          reason = prompt('Reason for cancelling this admin booking?');
          if (!reason || !reason.trim()) {
            alert('A reason is required for admin bookings.');
            return;
          }
        }

        postJSON({ action: 'cancel_trip', booking_id: bookingId, reason })
          .then(() => location.reload())
          .catch(err => alert(err.message));
      });
    });
  </script>

  <!-- keep vendor bundles as-is -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- Trip Log Modal (kept for future) -->
  <div class="modal fade" id="logTripModal" tabindex="-1" role="dialog" aria-labelledby="logTripModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered" role="document" >
      <div class="modal-content">
        <div class="modal-header" style="background-color: #000047; border-color: navy; color:antiquewhite;">
          <h5 class="modal-title w-100 text-center" id="logTripModalLabel">PLEASE PROVIDE DETAILS OF THE TRIP</h5>
          <button type="button" class="close" data-dismiss="modal" aria-label="Close">
            <span aria-hidden="true">&times;</span>
          </button>
        </div>
        <div class="modal-body" style="background-color: #000047; border-color: navy; color:antiquewhite;">
          <form method="POST" id="logTripForm" action="">
            <div class="form-group text-center">
              <label for="trip_id">Trip ID</label>
              <input type="text" id="trip_id" name="trip_id" class="form-control w-50 mx-auto" required>
            </div>
            <div class="form-group text-center">
              <label for="date">Date</label>
              <input type="date" id="date" name="date" class="form-control w-50 mx-auto" required>
            </div>
            <div class="form-row">
              <div class="form-group col-md-6">
                <label for="pickup">Pick-Up Location</label>
                <input type="text" id="pickup" name="pickup" class="form-control" required>
              </div>
              <div class="form-group col-md-6">
                <label for="dropoff">Drop-Off Location</label>
                <input type="text" id="dropoff" name="dropoff" class="form-control" required>
              </div>
            </div>
            <div class="form-group text-center">
              <label for="odometer">Odometer Reading</label>
              <input type="number" id="odometer" name="odometer" class="form-control w-50 mx-auto" required>
            </div>
            <div class="text-center">
              <button type="submit" name="add_log" class="btn btn-success" style="background-color: navy; border-color: navy;">+ Log Trip</button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>
</body>
</html>
