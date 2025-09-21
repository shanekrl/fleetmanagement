<?php // driver-trip-start.php
session_start(); include('vendor/inc/config.php'); include('vendor/inc/checklogin.php'); check_login();
$driver_id=(int)($_SESSION['u_id']??0); $id=(int)($_GET['id']??0);
if ($id>0) {
  // set accepted → in_progress and stamp pickup
  $mysqli->query("UPDATE bookings SET status='in_progress', updated_at=NOW() WHERE id={$id} AND driver_id={$driver_id} AND status='accepted'");
  // ensure run row exists
  $mysqli->query("INSERT IGNORE INTO booking_runs(booking_id,driver_id,pickup_button_at) VALUES({$id},{$driver_id},NOW())
                  ON DUPLICATE KEY UPDATE pickup_button_at=COALESCE(pickup_button_at,NOW())");
}
header('Location: driver-trips.php');
