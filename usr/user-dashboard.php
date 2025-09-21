<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$driverUserId = (int)($_SESSION['u_id'] ?? 0);

/* Resolve “driver add-table id” if you need it later */
$driverAddId = null;
if ($driverUserId) {
  if ($s=$mysqli->prepare("SELECT d.d_u_id FROM tms_user u JOIN tms_user_add_driver d ON d.u_email=u.u_email WHERE u.u_id=? LIMIT 1")){
    $s->bind_param('i',$driverUserId); $s->execute(); $s->bind_result($driverAddId); $s->fetch(); $s->close();
  }
}

/* Next pending assignment for this driver from tms_bookings */
$booking = null;
if ($driverAddId) {
  $sql = "SELECT booking_id, contact_phone, pickup_point, dropoff_point, scheduled_at, status, booking_type
            FROM tms_bookings
           WHERE driver_id=? AND status='pending' AND (scheduled_at IS NULL OR scheduled_at>=NOW())
        ORDER BY COALESCE(scheduled_at, NOW()) ASC LIMIT 1";
  if ($s=$mysqli->prepare($sql)) {
    $s->bind_param('i',$driverAddId); $s->execute();
    $r=$s->get_result(); $booking=$r->fetch_assoc(); $s->close();
  }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <?php include('vendor/inc/head.php'); ?>
  <link rel="stylesheet" href="css/driver-theme.css">
</head>
<body id="page-top">
  <?php include('vendor/inc/nav.php'); ?>
  <div id="wrapper">
    <?php include('vendor/inc/sidebar.php'); ?>
    <div id="content-wrapper">
      <div class="container-fluid">

        <h1 class="kaya-page-title text-center">Driver Dashboard</h1>

        <!-- New Appointment card -->
        <div class="kaya-card kaya-section">
          <div class="card-header py-2 px-3"><strong>New Appointment</strong></div>
          <div class="card-body">
            <?php if ($booking): ?>
              <div class="mb-2"><strong>When:</strong> <?= htmlspecialchars($booking['scheduled_at'] ?: '—') ?></div>
              <div class="mb-2"><strong>Pickup:</strong> <?= htmlspecialchars($booking['pickup_point']) ?></div>
              <div class="mb-2"><strong>Drop-off:</strong> <?= htmlspecialchars($booking['dropoff_point']) ?></div>
              <div class="mb-3">
                <span class="badge <?= $booking['booking_type']==='personal'?'badge-personal':'badge-admin' ?>">
                  <?= strtoupper($booking['booking_type']) ?>
                </span>
                <span class="badge badge-pending ml-1">PENDING</span>
              </div>
              <div class="d-flex" style="gap:.5rem;flex-wrap:wrap">
                <a class="btn btn-success btn-sm" href="accept_appointment.php?id=<?= (int)$booking['booking_id'] ?>">Accept</a>
                <a class="btn btn-danger btn-sm"  href="decline_appointment.php?id=<?= (int)$booking['booking_id'] ?>">Decline</a>
              </div>
            <?php else: ?>
              <div class="text-muted">No new assignment.</div>
            <?php endif; ?>
          </div>
        </div>

        <!-- Next accepted trip -->
        <div class="kaya-card">
          <div class="card-header py-2 px-3"><strong>Next Accepted Trip</strong></div>
          <div class="card-body">
            <?php
              $next=null;
              if ($driverAddId){
                $q="SELECT booking_id,pickup_point,dropoff_point,scheduled_at
                      FROM tms_bookings
                     WHERE driver_id=? AND status IN ('accepted','in_progress')
                  ORDER BY COALESCE(scheduled_at,NOW()) ASC LIMIT 1";
                if ($s=$mysqli->prepare($q)){ $s->bind_param('i',$driverAddId); $s->execute(); $next=$s->get_result()->fetch_assoc(); $s->close(); }
              }
            ?>
            <?php if ($next): ?>
              <div class="mb-2"><strong>When:</strong> <?= htmlspecialchars($next['scheduled_at'] ?: '—') ?></div>
              <div class="mb-2"><strong>Pickup:</strong> <?= htmlspecialchars($next['pickup_point']) ?></div>
              <div class="mb-3"><strong>Drop-off:</strong> <?= htmlspecialchars($next['dropoff_point']) ?></div>
              <a class="btn btn-kaya-primary" href="driver_map.php?booking_id=<?= (int)$next['booking_id'] ?>">Open Map</a>
            <?php else: ?>
              <div class="text-muted">Nothing accepted yet.</div>
            <?php endif; ?>
          </div>
        </div>
      </div>
      <?php include('vendor/inc/footer.php'); ?>
    </div>
  </div>

  <a class="scroll-to-top rounded" href="#page-top"><i class="fas fa-angle-up"></i></a>

  <script src="vendor/jquery/jquery.min.js"></script>
  <script src="vendor/bootstrap/js/bootstrap.bundle.min.js"></script>
  <script src="vendor/jquery-easing/jquery.easing.min.js"></script>
</body>
</html>
