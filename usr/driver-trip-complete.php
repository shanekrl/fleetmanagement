<?php // driver-trip-complete.php
session_start(); include('vendor/inc/config.php'); include('vendor/inc/checklogin.php'); check_login();
$driver_id=(int)($_SESSION['u_id']??0); $id=(int)($_GET['id']??0);
if ($id>0) {
  $mysqli->query("UPDATE bookings SET status='completed', updated_at=NOW() WHERE id={$id} AND driver_id={$driver_id} AND status IN ('in_progress','accepted')");
  $mysqli->query("UPDATE booking_runs SET dropoff_button_at=NOW(),
                  duration_seconds = TIMESTAMPDIFF(SECOND, COALESCE(pickup_button_at,NOW()), NOW())
                  WHERE booking_id={$id} AND (driver_id IS NULL OR driver_id={$driver_id})");
}
header('Location: driver-trips.php');
