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
                <!-- Use <dl> for label/value pairs; replace placeholders later -->
                <dl class="kaya-dl">
                  <div class="kaya-dl__row">
                    <dt>Date / Time</dt><dd><a href="#" class="kaya-link">View</a></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Start–End Location</dt><dd><a href="#" class="kaya-link">View</a></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Assigned Driver</dt><dd>Felipe</dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Distance (km)</dt><dd>300 km</dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Fuel Used</dt><dd>1 L</dd>
                  </div>
                </dl>
              </div>
            </section>
          </div>

          <!-- Current Route -->
          <div class="col-lg-4 mb-4">
            <section class="kaya-card h-100">
              <div class="kaya-card__head">Current Route</div>
              <div class="kaya-card__body">
                <dl class="kaya-dl">
                  <div class="kaya-dl__row">
                    <dt>Start Location</dt><dd><a href="#" class="kaya-link">View</a></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Destination</dt><dd><a href="#" class="kaya-link">View</a></dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Assigned Driver</dt><dd>Felipe</dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>ETA</dt><dd>20 mins</dd>
                  </div>
                </dl>
              </div>
            </section>
          </div>

          <!-- Diagnostics (OBD) -->
          <div class="col-lg-4 mb-4">
            <section class="kaya-card h-100">
              <div class="kaya-card__head">Diagnostics</div>
              <div class="kaya-card__body">
                <dl class="kaya-dl">
                  <div class="kaya-dl__row">
                    <dt>Fuel Level</dt><dd>45%</dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>Speed</dt><dd id="vehicleSpeed">60 km/h</dd>
                  </div>
                  <div class="kaya-dl__row">
                    <dt>OBD Status</dt><dd><span class="kaya-badge kaya-badge--warn">Needs Attention</span></dd>
                  </div>
                </dl>

                <!-- When the OBD link is ready, you can update values here every X seconds.
                     Example approach:
                     - build a small endpoint `/telemetry.php?vehicle_id=...` returning JSON
                     - fetch() it periodically and update the DOM (dd elements). -->
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

      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <!-- Scripts (your footer already wires the sidebar toggle for consistency) -->
  <script src="https://unpkg.com/leaflet/dist/leaflet.js"></script>
  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
  <script src="vendor/js/maps.js"></script>

  <style>
    /* Keep typography consistent with your other refreshed pages */
    html,body{font-family:Inter,system-ui,-apple-system,"Segoe UI",Roboto,sans-serif}

    .kaya-page-title{
      font-weight:800; font-size:2rem; line-height:1.1; color:#000047; margin:0 0 1rem;
    }

    /* Card look & feel (same recipe we used elsewhere) */
    .kaya-card{
      background:#fff; border-radius:1rem; border:1px solid #e5e7eb;
      box-shadow:0 8px 24px rgba(0,0,0,.06);
    }
    .kaya-card__head{
      padding:.75rem 1rem; font-weight:600; color:#0f172a; border-bottom:1px solid #f1f5f9;
    }
    .kaya-card__body{ padding:1rem 1.25rem; }

    /* Responsive 16:9 iframe wrapper */
    .kaya-map{ position:relative; width:100%;border-radius:.75rem; overflow:hidden; }
    .kaya-map iframe{ position:absolute; inset:0; width:100%; height:100%; border:0; }

    /* Definition list as neat 2-column table */
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
    .kaya-badge--warn{ background:#fff7ed; color:#9a3412; border-color:#fdba74; }
  </style>
</body>
</html>
