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
 <div class="modal fade" id="newTripModal" tabindex="-1" role="dialog" aria-labelledby="newTripLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
    <div class="modal-content">
      <form id="newTripForm">
        <div class="modal-header">
          <h5 class="modal-title" id="newTripLabel">Create Direct Booking</h5>
          <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
        </div>
        <div class="modal-body">
          <input type="hidden" name="ajax_create_direct" value="1">
          <div class="form-group"><label>Date</label><input class="form-control" type="date" name="sched_date"></div>
          <div class="form-group"><label>Time</label><input class="form-control" type="time" name="sched_time"></div>
          <div class="form-group"><label>Customer</label><input class="form-control" name="customer" required></div>
          <div class="form-group">
            <label>Phone</label>
            <input class="form-control" name="contact" placeholder="+63…" oninput="document.querySelector('[name=phone]').value=this.value;">
            <input type="hidden" name="phone">
          </div>
          <div class="form-group"><label>Pax</label><input class="form-control" name="pax" type="number" min="1" value="1"></div>

          <div class="form-group">
            <label>Pickup</label>
            <div class="input-group">
              <input class="form-control" id="pickup" name="pickup" placeholder="Type or use map" required>
              <div class="input-group-append">
                <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="pickup"><i class="fas fa-map-marker-alt"></i></button>
              </div>
            </div>
            <input type="hidden" id="pickup_lat" name="pickup_lat">
            <input type="hidden" id="pickup_lng" name="pickup_lng">
          </div>

          <div class="form-group">
            <label>Dropoff</label>
            <div class="input-group">
              <input class="form-control" id="dropoff" name="dropoff" placeholder="Type or use map" required>
              <div class="input-group-append">
                <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="dropoff"><i class="fas fa-map-pin"></i></button>
              </div>
            </div>
            <input type="hidden" id="dropoff_lat" name="dropoff_lat">
            <input type="hidden" id="dropoff_lng" name="dropoff_lng">
          </div>
        </div>
        <div class="modal-footer">
          <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Cancel</button>
          <button type="submit" class="btn btn-kaya"><i class="fas fa-save mr-1"></i> Create Booking</button>
        </div>
      </form>
    </div>
  </div>
</div>

<!-- Shared Map Picker Modal -->
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
            <div class="input-group-append"><button id="btnGeoSearch" class="btn btn-outline-secondary" type="button"><i class="fas fa-search"></i></button></div>
          </div>
          <div id="geoResults" class="nominatim-results mt-2"></div>
        </div>
        <div id="kayaMap"></div>
        <small class="text-muted d-block mt-2">Drag the marker to fine-tune. We will reverse-geocode and fill the field.</small>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnUsePoint" class="btn btn-kaya"><i class="fas fa-check mr-1"></i> Use this point</button>
        <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
      </div>
    </div>
  </div>
</div>
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
  // Submit Direct Booking via AJAX (+ guard if coords missing)
  (function(){
    const form = document.getElementById('newTripForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
      const need = ['pickup','dropoff'];
      for (const f of need){
        if (!form[f].value.trim()){ alert('Please enter '+f+'.'); e.preventDefault(); return; }
        if (!form[f+'_lat'].value || !form[f+'_lng'].value){
          if (!confirm('No coordinates for '+f+' — submit anyway?')){ e.preventDefault(); return; }
        }
      }
      e.preventDefault();
      const fd = new FormData(form);
      fetch('driver-trips.php', { method:'POST', credentials:'same-origin', body:fd })
        .then(r=>r.json())
        .then(res=>{
          if(res && res.ok){ $('#newTripModal').modal('hide'); setTimeout(()=>location.reload(), 350); }
          else{ alert(res && res.error ? res.error : 'Failed to create booking.'); }
        })
        .catch(()=>alert('Network / server error.'));
    });
  })();

  /* ===== Autocomplete + Map Picker (admin-parity; use fetch) ===== */
  function composePhotonLabel(f){
    if (!f || !f.properties) return '';
    const p = f.properties; const parts = [];
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
    return fetch(url,{mode:'cors'}).then(r=>r.json()).then(json=>json && json.features ? json.features : []).catch(()=>[]);
  }
  function nominatimSearch(q, limit=8){
    const url='https://nominatim.openstreetmap.org/search?format=jsonv2&limit='+limit+'&countrycodes=ph&viewbox='+
              [PH_BOUNDS.west,PH_BOUNDS.north,PH_BOUNDS.east,PH_BOUNDS.south].join(',')+
              '&bounded=1&q='+encodeURIComponent(q);
    return fetch(url,{mode:'cors',headers:{'Accept':'application/json','Accept-Language':'en-PH'}}).then(r=>r.json()).catch(()=>[]);
  }
  function nominatimReverse(lat, lon){
  const url = 'https://nominatim.openstreetmap.org/reverse?format=jsonv2'
            + '&lat='+lat+'&lon='+lon+'&zoom=18&addressdetails=1&namedetails=1'
            + '&accept-language=en-PH';
  return fetch(url, {mode:'cors', headers:{'Accept':'application/json'}})
    .then(r=>r.json())
    .then(rec => rec && (rec.name || rec.display_name) ? (rec.name || rec.display_name) : '');
}

function photonReverse(lat, lon){
  const url = 'https://photon.komoot.io/reverse?lat='+lat+'&lon='+lon+'&lang=en';
  return fetch(url, {mode:'cors'})
    .then(r=>r.json())
    .then(j => {
      const f = j && j.features && j.features[0];
      if (!f || !f.properties) return '';
      const p = f.properties;
      const parts = [];
      if (p.name) parts.push(p.name);
      if (p.street) parts.push(p.street + (p.housenumber ? ' ' + p.housenumber : ''));
      if (p.suburb || p.district || p.city) parts.push(p.suburb || p.district || p.city);
      if (p.state) parts.push(p.state);
      if (p.country) parts.push(p.country);
      return parts.filter(Boolean).join(', ');
    })
    .catch(()=> '');
}

/* Always try Nominatim first, then Photon; only then fall back to coordinates */
function reverseNice(lat, lon){
  return nominatimReverse(lat, lon).then(lbl => {
    if (lbl && !/^\s*\d+(\.\d+)?\s*,\s*\d+(\.\d+)?\s*$/.test(lbl)) return lbl;
    return photonReverse(lat, lon);
  }).then(lbl => {
    return (lbl && lbl.trim()) ? lbl.trim() : (lat.toFixed(6)+', '+lon.toFixed(6));
  }).catch(()=> (lat.toFixed(6)+', '+lon.toFixed(6)));
}


  /*** Autocomplete for Pickup/Dropoff inside #newTripModal ***/
  function attachAutocomplete($input, $lat, $lng){
    if (!$input.length || $input.data('kaya-autocomplete')) return;
    $input.data('kaya-autocomplete', 1);

    const $modal = $('#newTripModal');
    $input.autocomplete({
      appendTo: $modal.length ? $modal : 'body',
      minLength: 2,
      delay: 250,
      source: function(req, resp){
        photonSearch(req.term, 8).then(features=>{
          if (features && features.length){
            resp(features.map(f=>{
              const label = composePhotonLabel(f);
              return { label: label, value: label, lat: f.geometry.coordinates[1], lon: f.geometry.coordinates[0] };
            }));
          } else {
            nominatimSearch(req.term,8).then(list=>{
              resp((list||[]).map(it=>{
                const label = composeNominatimLabel(it);
                return { label: label, value: label, lat: parseFloat(it.lat), lon: parseFloat(it.lon) };
              }));
            }).catch(()=>resp([]));
          }
        }).catch(()=>resp([]));
      },
      select: function(e, ui){
        if (ui && ui.item){
          $lat.val(parseFloat(ui.item.lat).toFixed(8));
          $lng.val(parseFloat(ui.item.lon).toFixed(8));
        }
      },
      open: function(){ $('.ui-autocomplete').css('z-index', 2000); }
    });

    $input.on('input', function(){ $lat.val(''); $lng.val(''); });
    $input.on('keydown', function(e){ if (e.key === 'Enter' && $('.ui-autocomplete:visible').length) e.preventDefault(); });
  }

  // Enable Pickup/Dropoff autocomplete each time the booking modal opens
  function enableBookingFieldAutocomplete(){
    attachAutocomplete($('#pickup'),  $('#pickup_lat'),  $('#pickup_lng'));
    attachAutocomplete($('#dropoff'), $('#dropoff_lat'), $('#dropoff_lng'));
  }
  $('#newTripModal').on('shown.bs.modal', enableBookingFieldAutocomplete);
  $(enableBookingFieldAutocomplete);

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
    var btn = $(ev.relatedTarget); pickingFor = (btn && btn.data('for')) ? String(btn.data('for')) : 'pickup';
    initMap();
    var lat = $('#'+pickingFor+'_lat').val(), lng = $('#'+pickingFor+'_lng').val();
    if (lat && lng) { setMarker(parseFloat(lat), parseFloat(lng)); }
    else {
      var text = $('#'+pickingFor).val();
      if (text && text.length>3){
        photonSearch(text,1).then(function(r){
          if (r && r[0]) setMarker(r[0].geometry.coordinates[1], r[0].geometry.coordinates[0]);
          else return nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); });
        }).catch(function(){
          nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); });
        }).finally(function(){ setTimeout(()=>Lmap.invalidateSize(), 150); });
        return;
      }
      Lmap.setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom); Lmarker.setLatLng(Lmap.getCenter());
      lastPicked = {label:'', lat:Lmap.getCenter().lat, lng:Lmap.getCenter().lng};
    }
    setTimeout(()=>Lmap.invalidateSize(), 150);
  });

  // Live search list in the map modal (with debounce)
  function renderList(items){
    var $list = $('#geoResults').empty();
    if (!items || !items.length){ $list.text('No results.'); return; }
    items.forEach(function(it){
      var $it = $('<div class="geocode-item"></div>').text(it.label);
      $it.on('click', function(){ setMarker(it.lat, it.lon); lastPicked = {label: it.label, lat: it.lat, lng: it.lon}; });
      $list.append($it);
    });
  }
  function toPhotonItem(f){ return { label: composePhotonLabel(f), lat: f.geometry.coordinates[1], lon: f.geometry.coordinates[0] }; }
  function toNominatimItem(r){ return { label: r.display_name, lat: parseFloat(r.lat), lon: parseFloat(r.lon) }; }

  function searchAndFillList(q){
    var $list = $('#geoResults').empty().text('Searching…');
    photonSearch(q,10).then(function(features){
      if (features && features.length) return renderList(features.map(toPhotonItem));
      return nominatimSearch(q,10).then(function(list){ renderList((list||[]).map(toNominatimItem)); });
    }).catch(function(){
      nominatimSearch(q,10).then(function(list){ renderList((list||[]).map(toNominatimItem)); })
                          .catch(function(){ $list.text('Search failed.'); });
    });
  }
  var geoDebounce = null;
  $('#geoQuery').on('input', function(){
    var q = this.value.trim();
    clearTimeout(geoDebounce);
    if (q.length < 2){ $('#geoResults').empty(); return; }
    geoDebounce = setTimeout(function(){ searchAndFillList(q); }, 300);
  });
  $('#btnGeoSearch').on('click', function(){
    var q = $('#geoQuery').val().trim(); if (!q) return;
    searchAndFillList(q);
  });

  // Autocomplete on #geoQuery (moves map immediately)
  (function enableGeoAutocomplete(){
    var $q = $('#geoQuery');
    if (!$q.length || $q.data('geo-autocomplete')) return;
    $q.data('geo-autocomplete', 1);
    $q.autocomplete({
      appendTo: '#mapModal',
      minLength: 2, delay: 250,
      source: function(req, resp){
        photonSearch(req.term, 8).then(feats=>{
          if (feats && feats.length) return resp(feats.map(f=>{
            const label = composePhotonLabel(f);
            return {label: label, value: label, lat: f.geometry.coordinates[1], lon: f.geometry.coordinates[0]};
          }));
          return nominatimSearch(req.term, 8).then(list=>resp((list||[]).map(r=>{
            const label = composeNominatimLabel(r);
            return {label: label, value: label, lat: parseFloat(r.lat), lon: parseFloat(r.lon)};
          })));
        }).catch(()=>resp([]));
      },
      select: function(e, ui){
        if (!ui || !ui.item) return;
        setMarker(parseFloat(ui.item.lat), parseFloat(ui.item.lon));
        lastPicked = { label: ui.item.value, lat: parseFloat(ui.item.lat), lng: parseFloat(ui.item.lon) };
        setTimeout(()=>$('#geoQuery').select(), 0);
      },
      open: function(){ $('.ui-autocomplete').css('z-index', 2000); }
    });
    $q.on('keydown', function(e){ if (e.key === 'Enter' && $('.ui-autocomplete:visible').length) e.preventDefault(); });
  })();

  // Write the chosen point back to the correct field in the booking modal
  $('#btnUsePoint').on('click', function(){
  var pos = Lmarker.getLatLng();
  var $ctx = $('#newTripModal');
  var $text = $ctx.find('#' + pickingFor);
  var $lat  = $ctx.find('#' + pickingFor + '_lat');
  var $lng  = $ctx.find('#' + pickingFor + '_lng');

  // show a lightweight loading hint in the field while we reverse-geocode
  var previous = $text.val();
  $text.val(previous || 'Looking up address…');

  reverseNice(pos.lat, pos.lng).then(function(label){
    $text.val(label).trigger('input');    // <-- human-friendly label
    $lat.val(pos.lat.toFixed(8));
    $lng.val(pos.lng.toFixed(8));
    $('#mapModal').modal('hide');
  }).catch(function(){
    // last resort: numeric
    $text.val(pos.lat.toFixed(6)+', '+pos.lng.toFixed(6)).trigger('input');
    $lat.val(pos.lat.toFixed(8));
    $lng.val(pos.lng.toFixed(8));
    $('#mapModal').modal('hide');
  });
});


  // Sidebar mini helper
  (function(){var b=document.getElementById('sidebarToggle');
    if(b) b.addEventListener('click',function(e){e.preventDefault();document.body.classList.toggle('sidebar-toggled');
      var rail=document.getElementById('kayaSidebar'); if(rail) rail.classList.toggle('kaya-rail--collapsed');});
  })();
</script>

<style>
  footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
  footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
  #wrapper #content-wrapper{ padding-bottom:0!important; }
</style>

</body>
</html>
