<?php
session_start(); include('vendor/inc/config.php'); include('vendor/inc/checklogin.php'); check_login();
$driver_id = (int)($_SESSION['u_id'] ?? 0);
$booking_id = (int)($_GET['id'] ?? 0);
$action = ($_GET['do'] ?? '') === 'accept' ? 'accepted' : 'rejected';

/* Mark the latest pending offer for this driver on that booking */
if ($booking_id>0) {
  $sql = "UPDATE booking_offers SET response=?, response_at=NOW()
          WHERE booking_id=? AND driver_id=? AND response='pending' ORDER BY id DESC LIMIT 1";
  if ($st=$mysqli->prepare($sql)){ $st->bind_param('sii',$action,$booking_id,$driver_id); $st->execute(); $st->close(); }
}
header('Location: driver-trips.php');
