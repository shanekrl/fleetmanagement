<?php
  /**
   * Vehicle Telemetry (Monitoring) — KAYA
   * - Uses your shared navbar + sidebar + footer for consistent layout/behavior.
   * - Clean, “enterprise” card design aligned with Dashboard/Trips/Manage Vehicles.
   * - Embeds a responsive Google Maps iframe (center can be overridden via query).
   * - Keeps placeholder panels for Trip History, Current Route, Diagnostics.
   * - Inline comments mark spots to wire real GPS/OBD values later.
   */

  session_start();
  include('vendor/inc/config.php');
  include('vendor/inc/checklogin.php');
  check_login();
  $aid = require_admin();
?>
<!DOCTYPE html>
<html lang="en">
<?php include('vendor/inc/head.php'); // loads Bootstrap + your base CSS ?>
  <!-- Leaflet CSS -->
  <link rel="stylesheet" href="https://unpkg.com/leaflet/dist/leaflet.css"/>
<style>
    #map { height: 500px; width: 100%; }
</style>
<body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>

  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>

    <div id="content-wrapper">
      <div class="container-fluid">

        <!-- Page Title – matches the size/weight we used on other modernized pages -->
        <h1 class="kaya-page-title">Vehicle Telemetry</h1>

        <!-- Map card -->
        <section class="kaya-card mb-4">
          <div class="kaya-card__head">Live Map</div>

          <!-- Responsive map wrapper (keeps 16:9 ratio) -->
          <div class="kaya-map">
            <div id="map"></div>
          </div>

          <!-- NOTE: once GPS is wired, you can update ?lat= / ?lng= server-side
               or replace the iframe with a JS map (Leaflet/Google Maps) and
               move markers in real-time. -->
        </section>

        <!-- Three information cards -->
        <div class="row">
          <!-- Trip History -->
          <div class="col-lg-4 mb-4">
            <section class="kaya-card h-100">
              <div class="kaya-card__head">Trip History</div>
              <div class="kaya-card__body">
                <dl class="kaya-dl">
                <div class="kaya-dl__row">
                    <dt>Trip Movements:</dt><input type="date" id="get_trip_movements"/>
                </div>
                <div class="kaya-dl__row">
                    <dt>Date / Time</dt><dd id="th_date_time"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Start–End Location</dt><dd id="th_start_end_location"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Assigned Driver</dt><dd id="th_assigned_driver"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <!-- <dt>Distance (km)</dt><dd>300 km</dd> -->
                  </div>
                  <div class="kaya-dl__row">
                    <!-- <dt>Fuel Used</dt><dd>1 L</dd> -->
                  </div>
                </dl>
              </div>
            </section>
          </div>

          <!-- Current Route -->
          <div class="col-lg-7 mb-4">
            <section class="kaya-card h-100">
              <div class="kaya-card__head">Current Route</div>
              <div class="kaya-card__body">
                <dl class="kaya-dl">
                  <div class="kaya-dl__row">
                    <dt>Start Location</dt><dd id="cr_start_location"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Destination</dt><dd><dd id="cr_destination"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Assigned Driver</dt><dd id="cr_assigned_driver"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <!-- <dt>ETA</dt><dd>20 mins</dd> -->
                  </div>
                </dl>
              </div>
            </section>
          </div>

          <!-- Diagnostics (OBD) -->
          <div class="col-lg-7 mb-4">
            <section class="kaya-card h-100">
              <div class="kaya-card__head">Diagnostics</div>
              <div class="kaya-card__body">
                <dl class="kaya-dl">
                  <!--<div class="kaya-dl__row">
                    <dt>Fuel Level</dt><dd id="vehicleFuel"></dd>
                  </div> -->
                  <div class="kaya-dl__row">
                    <dt>Speed</dt><dd id="vehicleSpeed"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>RPM</dt><dd id="vehicleRPM"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Temperature</dt><dd id="vehicleTemperature"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Throttle</dt><dd id="vehicleThrottle"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Engine Load</dt><dd id="vehicleLoad"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Battery Voltage</dt><dd id="vehicleVoltage"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Odometer</dt><dd id="vehicleOdometer"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>MAF (Air Flow)</dt><dd id="vehicleMAF"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Ambient Temperature</dt><dd id="vehicleAmbient"></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>OBD Status</dt>
                    <dd><span class="kaya-badge kaya-badge--success" data-toggle="modal" data-target="#showDiagnosticsModal" id="obdStatus">Normal</span></dd>
                  </div>
                </dl>
              </div>
            </section>
          </div>
        </div>

        <!-- Timestamp (optional) -->
        <div class="text-muted small mt-2">
          <?php
            date_default_timezone_set("Asia/Manila");
            echo "The time is " . date("h:i:sa");
          ?>
        </div>
      </div>

      <!-- Diagnostics Modal -->
      <div class="modal fade" id="showDiagnosticsModal" tabindex="-1" role="dialog" aria-hidden="true">
        <div class="modal-dialog modal-xl" role="document">
          <form>
            <div class="modal-content" style="background:#f8fafc;color:#0f172a">
              <div class="modal-header">
                <h5 class="modal-title">Vehicle Diagnostics</h5>
                <button type="button" class="close" data-dismiss="modal"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <table id="diagnosticsTable" class="table table-bordered table-striped">
                  <thead>
                    <tr>
                      <th>ID</th>
                      <th>RPM</th>
                      <th>Speed</th>
                      <th>Coolant</th>
                      <th>Throttle</th>
                      <th>Load</th>
                      <th>Voltage</th>
                      <th>Check Engine Status</th>
                      <th>Overall</th>
                      <th>Created</th>
                    </tr>
                  </thead>
                  <tbody></tbody>
                </table>
              </div>
              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
              </div>
            </div>
          </form>
        </div>
      </div>

      <!-- TRIP MODAL -->
      <div class="modal fade" id="newTripModal" tabindex="-1" role="dialog" aria-labelledby="newTripLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg modal-dialog-scrollable" role="document">
          <div class="modal-content">
            <form id="newTripForm">
              <div class="modal-header">
                <h5 class="modal-title" id="newTripLabel">Create Trip</h5>
                <button type="button" class="close" data-dismiss="modal" aria-label="Close"><span>&times;</span></button>
              </div>
              <div class="modal-body">
                <input type="hidden" name="ajax_create_booking" value="1">
                <hr>
                <h1 id="vehicle_status_trip" style="font-size: 1.5em";>For Mainteance</h1>
                <hr>
                <br><br>
                <div class="form-row">
                <div class="form-group col-md-4">
                  <label>Date</label>
                  <input type="date" required class="form-control" name="sched_date">
                </div>
                <div class="form-group col-md-4">
                  <label>Time</label>
                  <input type="time" required class="form-control" name="sched_time">
                </div>
                <div class="form-group col-md-4">
                  <label>ETA Time <small class="text-muted">(optional)</small></label>
                  <input type="time" class="form-control" name="eta_time">
                </div>
              </div>


                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Customer</label>
                    <input type="text" required class="form-control" name="customer">
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

                  <!-- NEW: vehicle type (category) filter -->
                  <div class="form-group col-md-3">
                    <label>Vehicle Type</label>
                    <select class="form-control" id="modalVehicleType">
                      <option value="">— Any type —</option>
                      <option value="Bus">Bus</option>
                      <option value="Coaster">Coaster</option>
                      <option value="Sedan">Sedan</option>
                      <option value="SUV">SUV</option>
                      <option value="Van">Van</option>
                    </select>
                  </div>

                  <div class="form-group col-md-3">
                    <label>Vehicle</label>
                    <select class="form-control" name="vehicle_id" id="modalVehicleSelect">
                      <option value="">— None —</option>
                      <option value="8">Suzuki Ertiga · CBT4971</option>
                      <option value="7">Toyota Avanza · NHI3023</option>
                      <option value="9">Toyota Hiace GL Grandia · DBF9903</option>
                      <option value="23">Toyota Innova · NCJ4651</option>
                      <option value="10">Toyota Vios · CCD2879</option>
                    </select>
                  </div>
                  <div class="form-group col-md-3">
                    <label>Driver</label>
                    <select class="form-control" name="driver_id" id="modalDriverSelect">
                      <option value="">— None —</option>
                      <option value="9">Arnold Lagman</option>
                      <option value="12">Manuel Valencia</option>
                      <option value="8">Rey Cabral</option>
                      <option value="11">Richie Sibal</option>
                    </select>

                  </div>
                </div>

                <div class="form-row">
                  <div class="form-group col-md-6">
                    <label>Pickup</label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="pickup" name="pickup" placeholder="Type or use map">
                      <div class="input-group-append">
                        <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="pickup">
                          <i class="fas fa-map-marker-alt"></i>
                        </button>
                      </div>
                    </div>
                    <input type="hidden" id="pickup_lat" name="pickup_lat">
                    <input type="hidden" id="pickup_lng" name="pickup_lng">
                  </div>
                  <div class="form-group col-md-6">
                    <label>Destination</label>
                    <div class="input-group">
                      <input type="text" class="form-control" id="dropoff" name="dropoff" placeholder="Type or use map">
                      <div class="input-group-append">
                        <button class="btn btn-outline-primary" type="button" data-toggle="modal" data-target="#mapModal" data-for="dropoff">
                          <i class="fas fa-map-pin"></i>
                        </button>
                      </div>
                    </div>
                    <input type="hidden" id="dropoff_lat" name="dropoff_lat">
                    <input type="hidden" id="dropoff_lng" name="dropoff_lng">
                  </div>
                </div>

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
              </div>

              <div class="modal-footer">
                <button type="button" class="btn btn-outline-secondary" data-dismiss="modal">Close</button>
                <button type="submit" class="btn btn-kaya-primary">
                  <i class="fas fa-save mr-1"></i> Create Booking
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>


      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <!-- Scripts (your footer already wires the sidebar toggle for consistency) -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="https://unpkg.com/leaflet.smooth_marker_bouncing"></script>
  <script src="https://unpkg.com/leaflet.smoothmarkerbouncing"></script>
  <script src="https://unpkg.com/leaflet.marker.slideto"></script>
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/datatables/jquery.dataTables.js"></script>
  <script src="vendor/datatables/dataTables.bootstrap4.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="vendor/js/maps.js"></script>
  <script src="vendor/js/diagnostics.js"></script>

  <script>
  (function () {
    // Get vehicle_id from query string: admin-view-syslogs.php?vehicle_id=123
    const params = new URLSearchParams(location.search);
    const vehicleId = parseInt(params.get('vehicle_id') || '0', 10);
    if (!vehicleId) return;

    fetch(`api/vehicle_snapshot.php?vehicle_id=${vehicleId}`)
      .then(r => r.json())
      .then(data => {
        const thDriver = document.getElementById('th_assigned_driver');
        const crDriver = document.getElementById('cr_assigned_driver');
        const startLoc = document.getElementById('cr_start_location');
        const destLoc  = document.getElementById('cr_destination');

        if (thDriver) thDriver.textContent = data.assigned_driver || '—';
        if (crDriver) crDriver.textContent = data.assigned_driver || '—';
        if (startLoc) startLoc.textContent = data.start_location || '—';
        if (destLoc)  destLoc.textContent  = data.destination || '—';
      })
      .catch(console.error);
  })();
  </script>


  <style>
    html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}

    .kaya-page-title{
      font-weight:800; font-size:2rem; line-height:1.1; color:#000047; margin:0 0 1rem;
    }

    .kaya-card{
      background:#fff; border-radius:1rem; border:1px solid #e5e7eb;
      box-shadow:0 8px 24px rgba(0,0,0,.06);
    }
    .kaya-card__head{
      padding:.75rem 1rem; font-weight:600; color:#0f172a; border-bottom:1px solid #f1f5f9;
    }
    .kaya-card__body{ padding:1rem 1.25rem; }

    .kaya-map{ position:relative; width:100%;border-radius:.75rem; overflow:hidden; }
    .kaya-map iframe{ position:absolute; inset:0; width:100%; height:100%; border:0; }

    .kaya-dl{ margin:0; }
    .kaya-dl__row{
      display:flex; align-items:center; justify-content:space-between;
      padding:.5rem 0; border-top:1px solid #f1f5f9;
    }
    .kaya-dl__row:first-child{ border-top:0; }
    .kaya-dl dt{ margin:0; color:#374151; font-weight:600; }
    .kaya-dl dd{ margin:0; color:#0f172a; }

    .kaya-link{ text-decoration:none; color:#000047; font-weight:600; }
    .kaya-link:hover{ text-decoration:underline; }

    .kaya-badge{
      display:inline-block; padding:.125rem .5rem; border-radius:.375rem;
      font-size:.825rem; line-height:1.25; border:1px solid transparent;
    }
    .kaya-badge--warn{ background:#ed1c24; color:#fafafa; border-color:#fdba74; }

    .kaya-badge--success{ background:#28a745; color:#fafafa; border-color:#fdba74; }

    .row{
      gap: 10px;
      justify-content: center;
      overflow: auto;
      max-height: 500px;
    }

    #obdStatus{
      cursor: pointer;
    }
  </style>
</body>
</html>
