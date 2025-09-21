<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$driver_id = (int)($_SESSION['u_id'] ?? 0);
$id = (int)($_GET['id'] ?? 0);

if ($_SERVER['REQUEST_METHOD']==='POST') {
  $id = (int)($_POST['id'] ?? 0);
  $pax = (int)($_POST['pax'] ?? 1);
  $pickup = trim($_POST['pickup'] ?? '');
  $dropoff = trim($_POST['dropoff'] ?? '');
  $dt = trim($_POST['scheduled_at'] ?? '');
  $sql = "UPDATE bookings SET pax=?, pickup_point=?, dropoff_point=?, scheduled_start_at=?, updated_at=NOW()
          WHERE id=? AND booking_type='personal' AND created_by=? AND status IN ('pending','accepted')";
  if ($st=$mysqli->prepare($sql)){ $st->bind_param('isssii',$pax,$pickup,$dropoff,$dt,$id,$driver_id); $st->execute(); $st->close(); }
  header('Location: driver-trips.php'); exit;
}

$row = null;
if ($id>0) {
  if ($st=$mysqli->prepare("SELECT id,pax,pickup_point,dropoff_point,scheduled_start_at
                            FROM bookings WHERE id=? AND booking_type='personal' AND created_by=? LIMIT 1")){
    $st->bind_param('ii',$id,$driver_id); $st->execute(); $r=$st->get_result(); $row=$r->fetch_assoc(); $st->close();
  }
}
function h($v){return htmlspecialchars((string)$v,ENT_QUOTES,'UTF-8');}
?>
<!doctype html><html lang="en">
<?php include('vendor/inc/head.php'); ?>
<body id="page-top">
<?php include('vendor/inc/nav.php'); ?>
<div id="wrapper"><?php include('vendor/inc/sidebar.php'); ?><div id="content-wrapper"><div class="container-fluid">
  <ol class="breadcrumb"><li class="breadcrumb-item"><a href="driver-trips.php">Trips</a></li><li class="breadcrumb-item active">Edit</li></ol>
  <?php if(!$row): ?><div class="alert alert-warning">Not found or not editable.</div>
  <?php else: ?>
  <form method="post">
    <input type="hidden" name="id" value="<?php echo (int)$row['id']; ?>">
    <div class="card col-md-7 p-0">
      <div class="card-body">
        <div class="form-group"><label>Pax</label><input type="number" min="1" class="form-control" name="pax" value="<?php echo (int)$row['pax']; ?>"></div>
        <div class="form-group"><label>Pickup</label><input class="form-control" name="pickup" value="<?php echo h($row['pickup_point']); ?>"></div>
        <div class="form-group"><label>Dropoff</label><input class="form-control" name="dropoff" value="<?php echo h($row['dropoff_point']); ?>"></div>
        <div class="form-group"><label>Scheduled At</label><input type="datetime-local" class="form-control" name="scheduled_at"
          value="<?php echo $row['scheduled_start_at']? date('Y-m-d\TH:i',strtotime($row['scheduled_start_at'])):''; ?>"></div>
      </div>
      <div class="card-footer d-flex">
        <button class="btn btn-primary mr-2">Save</button>
        <a class="btn btn-outline-secondary" href="driver-trips.php">Cancel</a>
      </div>
    </div>
  </form>
  <?php endif; ?>
  <?php include('vendor/inc/footer.php'); ?>
</div></div></div>
<script src="vendor/jquery/jquery.min.js"></script><script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
</body></html>
