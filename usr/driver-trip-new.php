<?php
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';


$driver_id = require_driver();

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $pax = (int)($_POST['pax'] ?? 1);
  $pickup = trim($_POST['pickup'] ?? '');
  $drop = trim($_POST['dropoff'] ?? '');
  $dt = trim($_POST['scheduled_at'] ?? '');
  if ($pickup && $drop && $dt) {
    $sql = "INSERT INTO bookings(booking_type,created_by,driver_id,pax,pickup_point,dropoff_point,scheduled_start_at,status)
            VALUES('personal',?,?,?,?,?,?,'pending')";
    if ($st=$mysqli->prepare($sql)){ $st->bind_param('iiisss',$driver_id,$driver_id,$pax,$pickup,$drop,$dt); $st->execute(); $st->close(); }
    header('Location: driver-trips.php'); exit;
  }
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper"><?php include('vendor/inc/sidebar.php'); ?><div id="content-wrapper"><div class="container-fluid">
  <ol class="breadcrumb"><li class="breadcrumb-item"><a href="driver-trips.php">Trips</a></li><li class="breadcrumb-item active">New</li></ol>
  <form method="post">
    <div class="card col-md-7 p-0">
      <div class="card-body">
        <div class="form-group"><label>Pax</label><input type="number" min="1" class="form-control" name="pax" value="1"></div>
        <div class="form-group"><label>Pickup</label><input class="form-control" name="pickup" required></div>
        <div class="form-group"><label>Dropoff</label><input class="form-control" name="dropoff" required></div>
        <div class="form-group"><label>Scheduled At</label><input type="datetime-local" class="form-control" name="scheduled_at" required></div>
      </div>
      <div class="card-footer d-flex">
        <button class="btn btn-primary mr-2">Create</button>
        <a class="btn btn-outline-secondary" href="driver-trips.php">Cancel</a>
      </div>
    </div>
  </form>
  <?php include('vendor/inc/footer.php'); ?>
</div></div></div>
<script src="vendor/jquery/jquery.min.js"></script><script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body></html>
