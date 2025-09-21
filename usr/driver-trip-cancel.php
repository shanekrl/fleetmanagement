<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$driver_id = (int)($_SESSION['u_id'] ?? 0);
$id = (int)($_POST['id'] ?? 0);

if ($id>0) {
  // only allow cancel if personal + created_by = me and still pending/accepted
  $sql = "UPDATE bookings SET status='cancelled', updated_at=NOW()
          WHERE id=? AND booking_type='personal' AND created_by=? AND status IN ('pending','accepted')";
  if ($st=$mysqli->prepare($sql)) { $st->bind_param('ii',$id,$driver_id); $st->execute(); $st->close(); }
}
header('Location: driver-trips.php');
