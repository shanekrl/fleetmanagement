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
   - Legacy:     tms_user.u_id      => GET u_id (or booking_id fallback)
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
      if ($vid && $did){ $vehToDrv[$vid]=$did; if (!isset($drvToVeh[$did])) $drvToVeh[$did]=$vid; }
    }
  }

  // split scheduled for inputs
  $sd = $row['scheduled_start_at'] ? substr($row['scheduled_start_at'],0,10) : '';
  $st = $row['scheduled_start_at'] ? substr($row['scheduled_start_at'],11,5) : '';

  ?>
  <!DOCTYPE html>
  <html lang="en">
  <?php include('vendor/inc/head.php'); ?>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
  <!-- Autocomplete + Map CSS -->
  <link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
  <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
  <style>
    html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
    .kaya-page-title{font-weight:800;font-size:2rem;color:#000047;margin:0 0 1rem}
    .kaya-card{background:#fff;border-radius:1rem;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:1rem}
    /* Map picker modal sizing */
    #kayaMap { width:100%; height:420px; }
    .nominatim-results { max-height:160px; overflow:auto; border:1px solid #eaecef; border-radius:.25rem; }
    .geocode-item { cursor:pointer; padding:.375rem .5rem; border-bottom:1px solid #f1f3f7; }
    .geocode-item:last-child{ border-bottom:0; }
    .geocode-item:hover { background:#f6f8ff; }
  </style>
  <body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>
  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>
    <div id="content-wrapper">
      <div class="container-fluid">
        <h1 class="kaya-page-title">Edit Booking</h1>

        <div class="kaya-card p-3">
          <form method="post" id="editBookingForm">
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
                <div class="input-group">
                  <input type="text" class="form-control" id="pickup" name="pickup_point" value="<?= htmlspecialchars($row['pickup_point'] ?? '') ?>" placeholder="Type or use map">
                  <div class="input-group-append">
                    <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="pickup">
                      <i class="fas fa-map-marker-alt"></i>
                    </button>
                  </div>
                </div>
                <input type="hidden" id="pickup_lat" name="pickup_lat" value="">
                <input type="hidden" id="pickup_lng" name="pickup_lng" value="">
              </div>
              <div class="form-group col-md-6">
                <label>Dropoff</label>
                <div class="input-group">
                  <input type="text" class="form-control" id="dropoff" name="dropoff_point" value="<?= htmlspecialchars($row['dropoff_point'] ?? '') ?>" placeholder="Type or use map">
                  <div class="input-group-append">
                    <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="dropoff">
                      <i class="fas fa-map-pin"></i>
                    </button>
                  </div>
                </div>
                <input type="hidden" id="dropoff_lat" name="dropoff_lat" value="">
                <input type="hidden" id="dropoff_lng" name="dropoff_lng" value="">
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
              <button class="btn btn-kaya-primary" type="submit"><i class="fas fa-save mr-1"></i> Save</button>
              <a class="btn btn-outline-secondary" href="admin-trip-appointment.php">Back</a>
            </div>
          </form>
        </div>
      </div>
      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <!-- Map Picker Modal (shared for Pickup/Dropoff) -->
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

  <!-- JS -->
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <!-- Data not needed here, but keep easing if your theme expects it -->
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>

  <!-- Autocomplete + Map JS -->
  <script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
  <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

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
        if (VEH_TO_DRV && Object.prototype.hasOwnProperty.call(VEH_TO_DRV, vid)) {
          var want = String(VEH_TO_DRV[vid]);
          if (String($drv.val()) !== want) $drv.val(want).trigger('change');
        }
      });

      $drv.on('change', function(){
        var uid = $(this).val();
        if (!uid) return;
        if (DRV_TO_VEH && Object.prototype.hasOwnProperty.call(DRV_TO_VEH, uid)) {
          var want = String(DRV_TO_VEH[uid]);
          if (String($veh.val()) !== want) $veh.val(want).trigger('change');
        }
      });
    })();

    /* =============================================================
       Location Suggestions + Map Picker
       - Uses Photon (Komoot) first; falls back to Nominatim
       - Live search while typing (jQuery UI Autocomplete)
       ============================================================= */

    // ---------- Helpers to format addresses ----------
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
    function composeNominatimLabel(rec){
      return rec && rec.display_name ? rec.display_name : '';
    }

    // ---- Philippines bias (bounds + default center) ----
    const PH_BOUNDS = { west: 116.0, south: 4.4, east: 127.0, north: 21.3 };
    const PH_CENTER = { lat: 14.5995, lon: 120.9842 }; // Manila
    const PH_BBOX_STR = [PH_BOUNDS.west, PH_BOUNDS.south, PH_BOUNDS.east, PH_BOUNDS.north].join(',');

    // ---------- Forward search ----------
    function photonSearch(q, limit=8){
      const url =
        'https://photon.komoot.io/api/?' +
        'q=' + encodeURIComponent(q) +
        '&limit=' + limit +
        '&lat=' + encodeURIComponent(PH_CENTER.lat) +
        '&lon=' + encodeURIComponent(PH_CENTER.lon) +
        '&bbox=' + encodeURIComponent(PH_BBOX_STR) +
        '&lang=en';
      return fetch(url, {mode:'cors'})
        .then(r => r.json())
        .then(json => (json && json.features) ? json.features : []);
    }

    function nominatimSearch(q, limit=8){
      const url =
        'https://nominatim.openstreetmap.org/search?' +
        'format=jsonv2' +
        '&limit=' + limit +
        '&countrycodes=ph' +
        '&viewbox=' + [
          PH_BOUNDS.west, PH_BOUNDS.north,  // left,top
          PH_BOUNDS.east, PH_BOUNDS.south   // right,bottom
        ].join(',') +
        '&bounded=1' +
        '&q=' + encodeURIComponent(q);
      return fetch(url, {mode:'cors', headers:{'Accept':'application/json','Accept-Language':'en-PH'}})
        .then(r => r.json());
    }

    // ---------- Reverse geocode ----------
    function nominatimReverse(lat, lon){
      const url =
        'https://nominatim.openstreetmap.org/reverse?' +
        'format=jsonv2' +
        '&lat=' + encodeURIComponent(lat) +
        '&lon=' + encodeURIComponent(lon) +
        '&accept-language=en-PH';
      return fetch(url,{mode:'cors', headers:{'Accept':'application/json'}})
        .then(r=>r.json())
        .then(rec=>composeNominatimLabel(rec));
    }
    function reverseNice(lat, lon){
      return nominatimReverse(lat,lon).catch(()=>lat.toFixed(6)+', '+lon.toFixed(6));
    }

    // ---------- Autocomplete on Pickup / Destination fields ----------
    function attachAutocomplete($input, $lat, $lng){
      if ($input.data('kaya-autocomplete')) return;
      $input.data('kaya-autocomplete', 1);

      $input.autocomplete({
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
                resp((list||[]).map(it=>({label: composeNominatimLabel(it), value: composeNominatimLabel(it), lat: it.lat, lon: it.lon})));
              }).catch(()=>resp([]));
            }
          }).catch(()=>{
            nominatimSearch(req.term,8).then(list=>{
              resp((list||[]).map(it=>({label: composeNominatimLabel(it), value: composeNominatimLabel(it), lat: it.lat, lon: it.lon})));
            }).catch(()=>resp([]));
          });
        },
        select: function(e, ui){
          if (ui && ui.item){
            $lat.val(parseFloat(ui.item.lat).toFixed(8));
            $lng.val(parseFloat(ui.item.lon).toFixed(8));
          }
        },
        open: function(){ $('.ui-autocomplete').css('z-index', 2000); }
      });

      // If they edit text after choosing, clear the lat/lng so it's obvious it's a fresh string.
      $input.on('input', function(){ $lat.val(''); $lng.val(''); });
    }

    // Attach immediately (no need to wait for any modal)
    attachAutocomplete($('#pickup'),  $('#pickup_lat'),  $('#pickup_lng'));
    attachAutocomplete($('#dropoff'), $('#dropoff_lat'), $('#dropoff_lng'));

    // ===== Map Picker (pin-drop) – readable labels =====
    var Lmap, Lmarker, pickingFor='pickup';
    var lastCenter = {lat:14.5995, lng:120.9842, zoom:12};
    var lastPicked = {label:'', lat:null, lng:null};

    function initMap(){
      if (Lmap) { setTimeout(()=>Lmap.invalidateSize(), 100); return; }
      Lmap = L.map('kayaMap').setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom);
      L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        maxZoom: 19, attribution: '&copy; OpenStreetMap'
      }).addTo(Lmap);
      Lmarker = L.marker(Lmap.getCenter(), {draggable:true}).addTo(Lmap);

      Lmap.on('moveend', function(){
        lastCenter={lat:Lmap.getCenter().lat,lng:Lmap.getCenter().lng,zoom:Lmap.getZoom()};
      });

      Lmarker.on('dragend', function(){
        var p = Lmarker.getLatLng();
        reverseNice(p.lat, p.lng).then(function(lbl){
          lastPicked = {label: lbl || '', lat: p.lat, lng: p.lng};
        });
      });
    }

    function setMarker(lat,lng){
      Lmarker.setLatLng([lat,lng]);
      Lmap.setView([lat,lng], Math.max(15,Lmap.getZoom()));
      reverseNice(lat,lng).then(function(lbl){
        lastPicked = {label: lbl || '', lat: lat, lng: lng};
      });
    }

    // Open modal, set which field we're picking for and seed position
    $('#mapModal').on('shown.bs.modal', function(ev){
      var btn = $(ev.relatedTarget);
      pickingFor = (btn && btn.data('for')) ? String(btn.data('for')) : 'pickup';
      initMap();

      var lat = $('#'+pickingFor+'_lat').val();
      var lng = $('#'+pickingFor+'_lng').val();
      if (lat && lng) {
        setMarker(parseFloat(lat), parseFloat(lng));
      } else {
        var text = $('#'+pickingFor).val();
        if (text && text.length>3){
          photonSearch(text,1).then(function(r){
            if (r && r[0]) setMarker(r[0].geometry.coordinates[1], r[0].geometry.coordinates[0]);
            else return nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); });
          }).catch(function(){
            nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); });
          }).finally(function(){
            setTimeout(()=>Lmap.invalidateSize(), 150);
          });
          return;
        }
        Lmap.setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom);
        Lmarker.setLatLng(Lmap.getCenter());
        lastPicked = {label:'', lat:Lmap.getCenter().lat, lng:Lmap.getCenter().lng};
      }
      setTimeout(()=>Lmap.invalidateSize(), 150);
    });

    // In-modal simple search list
    $('#btnGeoSearch').on('click', function(){
      var q = $('#geoQuery').val().trim();
      var $list = $('#geoResults').empty();
      if (!q) return;
      $list.text('Searching…');
      photonSearch(q,10).then(function(features){
        if (!features || !features.length) throw new Error('no-photon');
        $list.empty();
        features.forEach(function(f){
          var label = composePhotonLabel(f);
          var lat = f.geometry.coordinates[1], lon = f.geometry.coordinates[0];
          var $it = $('<div class="geocode-item"></div>').text(label);
          $it.on('click', function(){ setMarker(lat, lon); lastPicked = {label: label, lat: lat, lng: lon}; });
          $list.append($it);
        });
      }).catch(function(){
        nominatimSearch(q,10).then(function(list){
          $list.empty();
          if (!list || !list.length){ $list.text('No results.'); return; }
          list.forEach(function(r){
            var label = composeNominatimLabel(r);
            var lat = parseFloat(r.lat), lon = parseFloat(r.lon);
            var $it = $('<div class="geocode-item"></div>').text(label);
            $it.on('click', function(){ setMarker(lat, lon); lastPicked = {label: label, lat: lat, lng: lon}; });
            $list.append($it);
          });
        }).catch(function(){ $list.text('Search failed.'); });
      });
    });

    // Apply picked point back to fields
    $('#btnUsePoint').on('click', function(){
      var pos = Lmarker.getLatLng();
      var apply = function(lbl){
        var labelToUse = lbl || lastPicked.label || $('#'+pickingFor).val() || (pos.lat.toFixed(6)+', '+pos.lng.toFixed(6));
        $('#'+pickingFor).val(labelToUse);
        $('#'+pickingFor+'_lat').val(pos.lat.toFixed(8));
        $('#'+pickingFor+'_lng').val(pos.lng.toFixed(8));
        $('#mapModal').modal('hide');
      };
      if (lastPicked.lat===pos.lat && lastPicked.lng===pos.lng && lastPicked.label){
        apply(lastPicked.label);
      } else {
        reverseNice(pos.lat,pos.lng).then(apply).catch(function(){ apply(''); });
      }
    });

    // Keep autocomplete dropdown above the modal backdrops
    $(function(){ $('.ui-autocomplete').css('z-index', 2000); });
  </script>

  <style>
    footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
    footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
    #wrapper #content-wrapper{ padding-bottom:0!important; }
  </style>

  </body>
  </html>
  <?php
  exit;
}

/* ==================================================================
   LEGACY MODEL (tms_user)  — kept so your older rows still edit
   (Autocomplete + Map added here too)
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
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800&display=swap" rel="stylesheet">
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<style>
  html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}
  .kaya-page-title{font-weight:800;font-size:2rem;color:#000047;margin:0 0 1rem}
  .kaya-card{background:#fff;border-radius:1rem;border:1px solid #e5e7eb;box-shadow:0 8px 24px rgba(0,0,0,.06);padding:1rem}
  #kayaMap{width:100%;height:420px}
  .nominatim-results{max-height:160px;overflow:auto;border:1px solid #eaecef;border-radius:.25rem}
  .geocode-item{cursor:pointer;padding:.375rem .5rem;border-bottom:1px solid #f1f3f7}
  .geocode-item:last-child{border-bottom:0}
  .geocode-item:hover{background:#f6f8ff}
</style>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper">
    <div class="container-fluid">
      <h1 class="kaya-page-title">Edit Booking</h1>
      <div class="kaya-card p-3">
        <form method="post" id="legacyEditForm">
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
              <div class="input-group">
                <input type="text" id="pickup" class="form-control" name="u_car_pickup" value="<?= htmlspecialchars($row['u_car_pickup'] ?? '') ?>" placeholder="Type or use map">
                <div class="input-group-append">
                  <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="pickup"><i class="fas fa-map-marker-alt"></i></button>
                </div>
              </div>
              <input type="hidden" id="pickup_lat" name="pickup_lat" value="">
              <input type="hidden" id="pickup_lng" name="pickup_lng" value="">
            </div>
            <div class="form-group col-md-5"><label>Destination</label>
              <div class="input-group">
                <input type="text" id="dropoff" class="form-control" name="u_car_destination" value="<?= htmlspecialchars($row['u_car_destination'] ?? '') ?>" placeholder="Type or use map">
                <div class="input-group-append">
                  <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="dropoff"><i class="fas fa-map-pin"></i></button>
                </div>
              </div>
              <input type="hidden" id="dropoff_lat" name="dropoff_lat" value="">
              <input type="hidden" id="dropoff_lng" name="dropoff_lng" value="">
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
            <button class="btn btn-kaya-primary" name="save" value="1"><i class="fas fa-save mr-1"></i> Save</button>
            <a class="btn btn-outline-secondary" href="admin-trip-appointment.php">Back</a>
          </div>
        </form>
      </div>
    </div>
    <?php include('vendor/inc/footer.php'); ?>
  </div>
</div>

<!-- Map Modal (shared) -->
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

<script src="vendor/jquery/jquery.min.js"></script>
<script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
<script src="https://code.jquery.com/ui/1.13.2/jquery-ui.min.js"></script>
<script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>

<script>
  // ===== Same JS helpers as the new-model block (duplicated to keep this page self-contained) =====
  function composePhotonLabel(f){
    if (!f || !f.properties) return '';
    const p = f.properties, parts=[];
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
    const url='https://photon.komoot.io/api/?q='+encodeURIComponent(q)+'&limit='+limit+'&lat='+PH_CENTER.lat+'&lon='+PH_CENTER.lon+'&bbox='+encodeURIComponent(PH_BBOX_STR)+'&lang=en';
    return fetch(url,{mode:'cors'}).then(r=>r.json()).then(j=>(j&&j.features)?j.features:[]);
  }
  function nominatimSearch(q, limit=8){
    const url='https://nominatim.openstreetmap.org/search?format=jsonv2&limit='+limit+'&countrycodes=ph&viewbox='+[PH_BOUNDS.west,PH_BOUNDS.north,PH_BOUNDS.east,PH_BOUNDS.south].join(',')+'&bounded=1&q='+encodeURIComponent(q);
    return fetch(url,{mode:'cors',headers:{'Accept':'application/json','Accept-Language':'en-PH'}}).then(r=>r.json());
  }
  function nominatimReverse(lat,lon){
    const url='https://nominatim.openstreetmap.org/reverse?format=jsonv2&lat='+encodeURIComponent(lat)+'&lon='+encodeURIComponent(lon)+'&accept-language=en-PH';
    return fetch(url,{mode:'cors',headers:{'Accept':'application/json'}}).then(r=>r.json()).then(rec=>composeNominatimLabel(rec));
  }
  function reverseNice(lat,lon){ return nominatimReverse(lat,lon).catch(()=>lat.toFixed(6)+', '+lon.toFixed(6)); }

  function attachAutocomplete($input,$lat,$lng){
    if ($input.data('kaya-autocomplete')) return;
    $input.data('kaya-autocomplete',1);
    $input.autocomplete({
      minLength:2, delay:250,
      source:function(req,resp){
        photonSearch(req.term,8).then(feats=>{
          if (feats && feats.length){
            resp(feats.map(f=>({label:composePhotonLabel(f), value:composePhotonLabel(f), lat:f.geometry.coordinates[1], lon:f.geometry.coordinates[0]})));
          } else {
            nominatimSearch(req.term,8).then(list=>resp((list||[]).map(it=>({label:composeNominatimLabel(it), value:composeNominatimLabel(it), lat:it.lat, lon:it.lon})))).catch(()=>resp([]));
          }
        }).catch(()=>{
          nominatimSearch(req.term,8).then(list=>resp((list||[]).map(it=>({label:composeNominatimLabel(it), value:composeNominatimLabel(it), lat:it.lat, lon:it.lon})))).catch(()=>resp([]));
        });
      },
      select:function(e,ui){
        if (ui && ui.item){ $lat.val(parseFloat(ui.item.lat).toFixed(8)); $lng.val(parseFloat(ui.item.lon).toFixed(8)); }
      },
      open:function(){ $('.ui-autocomplete').css('z-index',2000); }
    });
    $input.on('input', function(){ $lat.val(''); $lng.val(''); });
  }

  // Attach to legacy inputs
  attachAutocomplete($('#pickup'),  $('#pickup_lat'),  $('#pickup_lng'));
  attachAutocomplete($('#dropoff'), $('#dropoff_lat'), $('#dropoff_lng'));

  // Map modal behavior (same as new-model)
  var Lmap, Lmarker, pickingFor='pickup';
  var lastCenter={lat:14.5995,lng:120.9842,zoom:12};
  var lastPicked={label:'',lat:null,lng:null};

  function initMap(){
    if (Lmap){ setTimeout(()=>Lmap.invalidateSize(),100); return; }
    Lmap = L.map('kayaMap').setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom);
    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{maxZoom:19,attribution:'&copy; OpenStreetMap'}).addTo(Lmap);
    Lmarker = L.marker(Lmap.getCenter(), {draggable:true}).addTo(Lmap);
    Lmap.on('moveend', function(){ lastCenter={lat:Lmap.getCenter().lat,lng:Lmap.getCenter().lng,zoom:Lmap.getZoom()}; });
    Lmarker.on('dragend', function(){ var p=Lmarker.getLatLng(); reverseNice(p.lat,p.lng).then(lbl=>{lastPicked={label:lbl||'',lat:p.lat,lng:p.lng};}); });
  }
  function setMarker(lat,lng){
    Lmarker.setLatLng([lat,lng]);
    Lmap.setView([lat,lng], Math.max(15,Lmap.getZoom()));
    reverseNice(lat,lng).then(lbl=>{ lastPicked={label:lbl||'',lat:lat,lng:lng}; });
  }

  $('#mapModal').on('shown.bs.modal', function(ev){
    var btn=$(ev.relatedTarget);
    pickingFor=(btn && btn.data('for'))?String(btn.data('for')):'pickup';
    initMap();

    var lat=$('#'+pickingFor+'_lat').val();
    var lng=$('#'+pickingFor+'_lng').val();
    if (lat && lng){
      setMarker(parseFloat(lat), parseFloat(lng));
    } else {
      var text=$('#'+pickingFor).val();
      if (text && text.length>3){
        photonSearch(text,1).then(function(r){
          if (r && r[0]) setMarker(r[0].geometry.coordinates[1], r[0].geometry.coordinates[0]);
          else return nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); });
        }).catch(function(){
          nominatimSearch(text,1).then(function(n){ if (n && n[0]) setMarker(parseFloat(n[0].lat), parseFloat(n[0].lon)); });
        }).finally(function(){ setTimeout(()=>Lmap.invalidateSize(),150); });
        return;
      }
      Lmap.setView([lastCenter.lat,lastCenter.lng], lastCenter.zoom);
      Lmarker.setLatLng(Lmap.getCenter());
      lastPicked={label:'',lat:Lmap.getCenter().lat,lng:Lmap.getCenter().lng};
    }
    setTimeout(()=>Lmap.invalidateSize(),150);
  });

  $('#btnGeoSearch').on('click', function(){
    var q=$('#geoQuery').val().trim();
    var $list=$('#geoResults').empty();
    if (!q) return;
    $list.text('Searching…');
    photonSearch(q,10).then(function(features){
      if (!features || !features.length) throw new Error('no-photon');
      $list.empty();
      features.forEach(function(f){
        var label=composePhotonLabel(f);
        var lat=f.geometry.coordinates[1], lon=f.geometry.coordinates[0];
        var $it=$('<div class="geocode-item"></div>').text(label);
        $it.on('click', function(){ setMarker(lat,lon); lastPicked={label:label,lat:lat,lng:lon}; });
        $list.append($it);
      });
    }).catch(function(){
      nominatimSearch(q,10).then(function(list){
        $list.empty();
        if (!list || !list.length){ $list.text('No results.'); return; }
        list.forEach(function(r){
          var label=composeNominatimLabel(r);
          var lat=parseFloat(r.lat), lon=parseFloat(r.lon);
          var $it=$('<div class="geocode-item"></div>').text(label);
          $it.on('click', function(){ setMarker(lat,lon); lastPicked={label:label,lat:lat,lng:lon}; });
          $list.append($it);
        });
      }).catch(function(){ $list.text('Search failed.'); });
    });
  });

  $('#btnUsePoint').on('click', function(){
    var pos=Lmarker.getLatLng();
    var apply=function(lbl){
      var labelToUse=lbl || lastPicked.label || $('#'+pickingFor).val() || (pos.lat.toFixed(6)+', '+pos.lng.toFixed(6));
      $('#'+pickingFor).val(labelToUse);
      $('#'+pickingFor+'_lat').val(pos.lat.toFixed(8));
      $('#'+pickingFor+'_lng').val(pos.lng.toFixed(8));
      $('#mapModal').modal('hide');
    };
    if (lastPicked.lat===pos.lat && lastPicked.lng===pos.lng && lastPicked.label){ apply(lastPicked.label); }
    else { reverseNice(pos.lat,pos.lng).then(apply).catch(function(){ apply(''); }); }
  });
</script>

<style>
  footer.sticky-footer{ background:transparent!important; height:0!important; border:0!important; box-shadow:none!important; }
  footer.sticky-footer .container, footer.sticky-footer .copyright{ display:none!important; }
  #wrapper #content-wrapper{ padding-bottom:0!important; }
</style>

</body>
</html>
