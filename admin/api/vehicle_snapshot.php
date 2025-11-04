<?php
// api/vehicle_snapshot.php
session_start();
require_once __DIR__.'/../vendor/inc/config.php';
require_once __DIR__.'/../vendor/inc/checklogin.php';
check_login();
$mysqli->set_charset('utf8mb4');

$vehicleId = (int)($_GET['vehicle_id'] ?? 0);
if (!$vehicleId) {
  http_response_code(400);
  echo json_encode(['error' => 'vehicle_id required']);
  exit;
}

/*
 We resolve the *assigned driver* from the most relevant booking:
 - Prefer an active trip (started/approved/ongoing-like status).
 - Otherwise, fall back to the most recent completed booking.
*/

$sql = "
SELECT 
  b.id AS booking_id,
  b.status,
  b.pickup_point,
  b.dropoff_point,
  b.pickup_time,
  a.name AS driver_name,
  a.id   AS driver_account_id
FROM bookings b
LEFT JOIN accounts a ON a.id = b.driver_id   /* drivers are in `accounts` */
WHERE b.vehicle_id = ?
  AND b.status IN ('approved','accepted','started','in_progress','ongoing','active')
ORDER BY b.pickup_time DESC, b.id DESC
LIMIT 1
";
$stmt = $mysqli->prepare($sql);
$stmt->bind_param('i', $vehicleId);
$stmt->execute();
$active = $stmt->get_result()->fetch_assoc();

if (!$active) {
  // fall back to latest finished one
  $sql2 = "
  SELECT 
    b.id AS booking_id,
    b.status,
    b.pickup_point,
    b.dropoff_point,
    b.pickup_time,
    a.name AS driver_name,
    a.id   AS driver_account_id
  FROM bookings b
  LEFT JOIN accounts a ON a.id = b.driver_id
  WHERE b.vehicle_id = ?
  ORDER BY b.pickup_time DESC, b.id DESC
  LIMIT 1";
  $stmt = $mysqli->prepare($sql2);
  $stmt->bind_param('i', $vehicleId);
  $stmt->execute();
  $active = $stmt->get_result()->fetch_assoc();
}

header('Content-Type: application/json');
echo json_encode([
  'assigned_driver' => $active['driver_name'] ?? null,
  'start_location'  => $active['pickup_point'] ?? null,
  'destination'     => $active['dropoff_point'] ?? null,
  'booking_status'  => $active['status'] ?? null,
  'booking_id'      => $active['booking_id'] ?? null,
]);
