<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$driverUserId = (int)($_SESSION['u_id'] ?? 0);
$driverAddId = null;
if ($driverUserId) {
  if ($s=$mysqli->prepare("SELECT d.d_u_id FROM tms_user u JOIN tms_user_add_driver d ON d.u_email=u.u_email WHERE u.u_id=? LIMIT 1")){
    $s->bind_param('i',$driverUserId); $s->execute(); $s->bind_result($driverAddId); $s->fetch(); $s->close();
  }
}

if (!$driverAddId) { header('Location: driver-trips.php'); exit; }

$pickup = trim($_POST['pickup_point'] ?? '');
$drop   = trim($_POST['dropoff_point'] ?? '');
$pax    = max(1, (int)($_POST['pax'] ?? 1));
$veh    = !empty($_POST['vehicle_id']) ? (int)$_POST['vehicle_id'] : null;
$when   = !empty($_POST['scheduled_at']) ? date('Y-m-d H:i:s', strtotime($_POST['scheduled_at'])) : null;
$phone  = trim($_POST['contact_phone'] ?? '');
$notes  = trim($_POST['notes'] ?? '');

$sql = "INSERT INTO tms_bookings (client_id, booking_type, created_by_driver_id, driver_id, vehicle_id,
                                  pickup_point, dropoff_point, seats_reserved, scheduled_at, contact_phone, status, payment_status, notes)
        VALUES ( ?, 'personal', ?, ?, ?, ?, ?, ?, ?, ?, 'pending', 'unpaid', ?)";
/* We don’t have a separate “client” table here; reuse driver’s tms_user id for client_id placeholder */
$clientId = $driverUserId;

if ($s=$mysqli->prepare($sql)){
  $s->bind_param('iiiiississ', $clientId, $driverAddId, $driverAddId, $veh, $pickup, $drop, $pax, $when, $phone, $notes);
  $s->execute(); $s->close();
}
header('Location: driver-trips.php');
