<?php
session_start();
header('Content-Type: application/json');
require 'vendor/inc/config.php';
require 'vendor/inc/checklogin.php';
check_login();

$vid = isset($_GET['vehicle_id']) ? (int)$_GET['vehicle_id'] : 0;
if ($vid <= 0) { echo json_encode(['ok'=>false]); exit; }

$sql = "SELECT driver_account_id, driver_name, add_driver_id, add_driver_fname, add_driver_lname
        FROM v_vehicle_current_driver WHERE v_id = ? LIMIT 1";
if ($st = $mysqli->prepare($sql)) {
  $st->bind_param('i', $vid);
  $st->execute();
  $row = $st->get_result()->fetch_assoc();
  $st->close();
}

$driverId = null; $driverLabel = null;

// Prefer active account driver
if (!empty($row['driver_account_id'])) {
  $driverId = (int)$row['driver_account_id'];
  $driverLabel = $row['driver_name'];
} elseif (!empty($row['add_driver_id'])) {
  // Optional: if your driver select is bound to accounts, you may need a
  // crosswalk from add_driver to accounts. For now, just return label.
  $driverId = null; // no account id
  $driverLabel = trim(($row['add_driver_fname']??'').' '.($row['add_driver_lname']??''));
}

echo json_encode(['ok'=>true, 'driver_id'=>$driverId, 'driver_label'=>$driverLabel]);
