<?php
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$driver_id = require_driver();
$booking_id = (int)($_GET['id'] ?? 0);
$action = ($_GET['do'] ?? '') === 'accept' ? 'accepted' : 'rejected';

/* Mark the latest pending offer for this driver on that booking */
if ($booking_id>0) {
  $sql = "UPDATE booking_offers SET response=?, response_at=NOW()
          WHERE booking_id=? AND driver_id=? AND response='pending' ORDER BY id DESC LIMIT 1";
  if ($st=$mysqli->prepare($sql)){ $st->bind_param('sii',$action,$booking_id,$driver_id); $st->execute(); $st->close(); }
}
header('Location: driver-trips.php');
