<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();

$driverAccountId = (int)($_SESSION['account_id'] ?? 0);
$bookingId = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if (!$driverAccountId || !$bookingId) {
  $_SESSION['error'] = "Invalid request.";
  header('Location: user-dashboard.php'); exit;
}

/* Verify booking is awaiting this driver */
$ok = false;
if ($s = $mysqli->prepare("SELECT id FROM bookings WHERE id=? AND driver_id=? AND status='awaiting_driver' LIMIT 1")){
  $s->bind_param('ii', $bookingId, $driverAccountId);
  $s->execute(); $s->bind_result($bid);
  if ($s->fetch()) $ok = true;
  $s->close();
}
if (!$ok) {
  $_SESSION['error'] = "Booking not found or not awaiting your response.";
  header('Location: user-dashboard.php'); exit;
}

/* Try offer-driven flow first (recommended) */
$hasOffer = false;
if ($s=$mysqli->prepare("SELECT id FROM booking_offers WHERE booking_id=? AND driver_id=? LIMIT 1")){
  $s->bind_param('ii',$bookingId,$driverAccountId);
  $s->execute(); $s->bind_result($oid);
  if ($s->fetch()) $hasOffer = true;
  $s->close();
}

if ($hasOffer) {
  if ($s=$mysqli->prepare("UPDATE booking_offers SET response='accepted', response_at=NOW() WHERE booking_id=? AND driver_id=? AND response='pending'")){
    $s->bind_param('ii',$bookingId,$driverAccountId);
    $s->execute();
    $rows = $s->affected_rows;
    $s->close();
    if ($rows>0) { $_SESSION['success']="Booking accepted."; header('Location: user-dashboard.php'); exit; }
  }
}

/* Fallback: direct update bookings (no offer row) */
if ($s=$mysqli->prepare("UPDATE bookings SET status='accepted', updated_at=NOW() WHERE id=? AND driver_id=? AND status='awaiting_driver'")){
  $s->bind_param('ii',$bookingId,$driverAccountId);
  $s->execute();
  $rows = $s->affected_rows;
  $s->close();

  if ($rows>0) {
    // audit trail
    if ($s=$mysqli->prepare("INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details) VALUES(?, ?, 'driver', 'accept', JSON_OBJECT('mode','direct'))")){
      $s->bind_param('ii',$bookingId,$driverAccountId); $s->execute(); $s->close();
    }
    $_SESSION['success'] = "Booking accepted.";
  } else {
    $_SESSION['error'] = "Could not accept booking.";
  }
}

header('Location: user-dashboard.php');
exit;
