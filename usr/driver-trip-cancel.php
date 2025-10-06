<?php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$driver_id = require_driver();
$id = (int)($_POST['id'] ?? 0);

if ($id>0) {
  // only allow cancel if personal + created_by = me and still pending/accepted
  $sql = "UPDATE bookings SET status='cancelled', updated_at=NOW()
          WHERE id=? AND booking_type='personal' AND created_by=? AND status IN ('pending','accepted')";
  if ($st=$mysqli->prepare($sql)) { $st->bind_param('ii',$id,$driver_id); $st->execute(); $st->close(); }
}
header('Location: driver-trips.php');
