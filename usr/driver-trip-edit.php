<?php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$driveraccountId = require_driver(); // ensure driver auth and get accounts.id
// Accept either ?id= or ?booking_id=
$id = (int)($_GET['id'] ?? $_GET['booking_id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id = (int)($_POST['id'] ?? 0);
  $pax = max(1,(int)($_POST['pax'] ?? 1));
  $pickup = trim($_POST['pickup'] ?? '');
  $dropoff = trim($_POST['dropoff'] ?? '');
  $dt = trim($_POST['scheduled_at'] ?? '');

  $pickup_lat   = ($_POST['pickup_lat']  !== '' ? (float)$_POST['pickup_lat']  : null);
  $pickup_lng   = ($_POST['pickup_lng']  !== '' ? (float)$_POST['pickup_lng']  : null);
  $dropoff_lat  = ($_POST['dropoff_lat'] !== '' ? (float)$_POST['dropoff_lat'] : null);
  $dropoff_lng  = ($_POST['dropoff_lng'] !== '' ? (float)$_POST['dropoff_lng'] : null);

  $sql = "UPDATE bookings
             SET pax=?,
                 pickup_point=?, dropoff_point=?,
                 pickup_lat=?,  pickup_lng=?,
                 dropoff_lat=?, dropoff_lng=?,
                 scheduled_start_at=?, updated_at=NOW()
           WHERE id=? AND booking_type='personal' AND created_by=? AND status IN ('pending','accepted')";
  if ($st=$mysqli->prepare($sql)){
    $st->bind_param('issddddsii',
      $pax, $pickup, $dropoff,
      $pickup_lat, $pickup_lng,
      $dropoff_lat, $dropoff_lng,
      $dt, $id, $driverAccountId
    );
    $st->execute(); $st->close();
  }
  header('Location: driver-trips.php'); exit;
}

$row = null;
if ($id>0) {
  if ($st=$mysqli->prepare("SELECT id,pax,pickup_point,dropoff_point,scheduled_start_at,
                                   pickup_lat,pickup_lng,dropoff_lat,dropoff_lng
                              FROM bookings
                             WHERE id=? AND booking_type='personal' AND created_by=? LIMIT 1")){
    $st->bind_param('ii',$id,$driverAccountId); $st->execute(); $r=$st->get_result(); $row=$r->fetch_assoc(); $st->close();
  }
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html>
<html lang="en">
<?php include('vendor/inc/head.php'); ?>
<link rel="stylesheet" href="https://code.jquery.com/ui/1.13.2/themes/base/jquery-ui.css">
<link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css"/>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper">
  <?php include('vendor/inc/sidebar.php'); ?>
  <div id="content-wrapper">
    <div class="container-fluid">
      <ol class="breadcrumb"><li class="breadcrumb-item"><a href="driver-trips.php">Trips</a></li><li class="breadcrumb-item active">Edit</li></ol>
      <?php if(!$row): ?>
        <div class="alert alert-warning">Not found or not editable.</div>
      <?php else: ?>
      <form method="post" id="editTripForm">
        <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
        <div class="card col-md-7 p-0">
          <div class="card-body">
            <div class="form-group"><label>Pax</label><input type="number" min="1" class="form-control" name="pax" value="<?php echo (int)$row['pax']; ?>"></div>

            <div class="form-group">
              <label>Pickup</label>
              <div class="input-group">
                <input class="form-control" id="pickup" name="pickup" value="<?php echo h($row['pickup_point']); ?>">
                <div class="input-group-append">
                  <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="pickup"><i class="fas fa-map-marker-alt"></i></button>
                </div>
              </div>
              <input type="hidden" id="pickup_lat" name="pickup_lat" value="<?php echo h($row['pickup_lat']); ?>">
              <input type="hidden" id="pickup_lng" name="pickup_lng" value="<?php echo h($row['pickup_lng']); ?>">
            </div>

            <div class="form-group">
              <label>Dropoff</label>
              <div class="input-group">
                <input class="form-control" id="dropoff" name="dropoff" value="<?php echo h($row['dropoff_point']); ?>">
                <div class="input-group-append">
                  <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="dropoff"><i class="fas fa-map-pin"></i></button>
                </div>
              </div>
              <input type="hidden" id="dropoff_lat" name="dropoff_lat" value="<?php echo h($row['dropoff_lat']); ?>">
              <input type="hidden" id="dropoff_lng" name="dropoff_lng" value="<?php echo h($row['dropoff_lng']); ?>">
            </div>

            <div class="form-group">
              <label>Scheduled At</label>
              <input type="datetime-local" class="form-control" name="scheduled_at"
                     value="<?php echo $row['scheduled_start_at']? date('Y-m-d\TH:i',strtotime($row['scheduled_start_at'])):''; ?>">
            </div>
          </div>
          <div class="card-footer d-flex">
            <button class="btn btn-primary mr-2">Save</button>
            <a class="btn btn-outline-secondary" href="driver-trips.php">Cancel</a>
          </div>
        </div>
      </form>
      <?php endif; ?>
      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>
</div>

<!-- Shared Map Picker Modal (same component) -->
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
          <div id="geoResults" class="nominatim-results mt-2" style="max-height:160px; overflow:auto; border:1px solid #eaecef; border-radius:.25rem;"></div>
        </div>
        <div id="kayaMap" style="width:100%; height:420px;"></div>
        <small class="text-muted d-block mt-2">Drag the marker to fine-tune. We’ll reverse-geocode and fill the field.</small>
      </div>
      <div class="modal-footer">
        <button type="button" id="btnUsePoint" class="btn btn-primary"><i class="fas fa-check mr-1"></i> Use this point</button>
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
  // Guard on save if coords missing
  (function(){
    const form = document.getElementById('editTripForm');
    if (!form) return;
    form.addEventListener('submit', function(e){
      const need = ['pickup','dropoff'];
      for (const f of need){
        if (!form[f].value.trim()){ alert('Please enter '+f+'.'); e.preventDefault(); return; }
        if (!form[f+'_lat'].value || !form[f+'_lng'].value){
          if (!confirm('No coordinates for '+f+' — save anyway?')){ e.preventDefault(); return; }
        }
      }
    });
  })();

  /* ===== Autocomplete (same engine as create modal) ===== */
  function composePhotonLabel(f){
    if (!f || !f.properties) return '';
    const p = f.properties, parts = [];
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
    return $.ajax({ url, method:'GET', dataType:'json', timeout:8000 })
             .then(json => (json && json.features) ? json.features : [])
             .catch(()=>[]);
  }
  function nominatimSearch(q, limit=8){
    const url='https://nominatim.openstreetmap.org/search';
    return $.ajax({
      url, method:'GET', dataType:'json', timeout:8000,
      headers: {'Accept':'application/json','Accept-Language':'en-PH'},
      data: {
        format:'jsonv2', limit: String(limit), countrycodes:'ph',
        viewbox: [PH_BOUNDS.west,PH_BOUNDS.north,PH_BOUNDS.east,PH_BOUNDS.south].join(','),
        bounded: '1', q
      }
    }).catch(()=>[]);
  }
  function nominatimReverse(lat, lon){
    const url='https://nominatim.openstreetmap.org/reverse';
    return $.ajax({
      url, method:'GET', dataType:'json', timeout:8000,
      headers: {'Accept':'application/json','Accept-Language':'en-PH'},
      data: { format:'jsonv2', lat, lon }
    }).then(rec=>rec && rec.display_name ? rec.display_name : null).catch(()=>null);
  }
  function reverseNice(lat, lon){ return nominatimReverse(lat,lon).then(lbl => lbl || (lat.toFixed(6)+', '+lon.toFixed(6))); }

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
              resp((list||[]).map(it=>{
                const label = composeNominatimLabel(it);
                return {label: label, value: label, lat: parseFloat(it.lat), lon: parseFloat(it.lon)};
              }));
            }).catch(()=>resp([]));
          }
        }).catch(()=>resp([]));
      },
      select: function(e, ui){ if (ui && ui.item){ $lat.val(parseFloat(ui.item.lat).toFixed(8)); $lng.val(parseFloat(ui.item.lon).toFixed(8)); } },
      open: function(){ $('.ui-autocomplete').css('z-index', 2000); }
    });
    $input.on('input', function(){ $lat.val(''); $lng.val(''); });
    $input.on('keydown', function(e){
      if (e.key === 'Enter' && $('.ui-autocomplete:visible').length) e.preventDefault();
    });
  }

  // init autocomplete on page load
  attachAutocomplete($('#pickup'),  $('#pickup_lat'),  $('#pickup_lng'));
  attachAutocomplete($('#dropoff'), $('#dropoff_lat'), $('#dropoff_lng'));

  // ===== Map Picker (shared)
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

  $('#btnGeoSearch').on('click', function(){
    var q = $('#geoQuery').val().trim(); var $list = $('#geoResults').empty(); if (!q) return;
    $list.text('Searching…');
    photonSearch(q,10).then(function(features){
      if (!features || !features.length) throw new Error('no-photon');
      $list.empty(); features.forEach(function(f){
        var label = composePhotonLabel(f); var lat = f.geometry.coordinates[1], lon = f.geometry.coordinates[0];
        var $it = $('<div class="geocode-item"></div>').text(label);
        $it.on('click', function(){ setMarker(lat, lon); lastPicked = {label: label, lat: lat, lng: lon}; });
        $list.append($it);
      });
    }).catch(function(){
      nominatimSearch(q,10).then(function(list){
        $list.empty(); if (!list || !list.length){ $list.text('No results.'); return; }
        list.forEach(function(r){
          var label = r.display_name; var lat = parseFloat(r.lat), lon = parseFloat(r.lon);
          var $it = $('<div class="geocode-item"></div>').text(label);
          $it.on('click', function(){ setMarker(lat, lon); lastPicked = {label: label, lat: lat, lng: lon}; });
          $list.append($it);
        });
      }).catch(function(){ $list.text('Search failed.'); });
    });
  });

  $('#btnUsePoint').on('click', function(){
    var pos = Lmarker.getLatLng();
    var apply = function(lbl){
      var labelToUse = lbl || lastPicked.label || $('#'+pickingFor).val() || (pos.lat.toFixed(6)+', '+pos.lng.toFixed(6));
      $('#'+pickingFor).val(labelToUse);
      $('#'+pickingFor+'_lat').val(pos.lat.toFixed(8));
      $('#'+pickingFor+'_lng').val(pos.lng.toFixed(8));
      $('#mapModal').modal('hide');
    };
    if (lastPicked.lat===pos.lat && lastPicked.lng===pos.lng && lastPicked.label){ apply(lastPicked.label); }
    else { reverseNice(pos.lat,pos.lng).then(apply).catch(function(){ apply(''); }); }
  });
</script>
</body>
</html>
