<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
check_login();
$mysqli->set_charset('utf8mb4');

function first_admin_id(mysqli $db): int {
  $rid = 1;
  if ($rs = $db->query("SELECT id FROM accounts WHERE role='admin' ORDER BY id LIMIT 1")) {
    if ($row = $rs->fetch_assoc()) $rid = (int)$row['id'];
  }
  return $rid;
}

$vehicle_id = (int)($_POST['vehicle_id'] ?? 0);
$driver_id  = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
$action     = $_POST['action'] ?? 'assign';

if ($vehicle_id <= 0) { header('Location: admin-manage-vehicle.php'); exit; }

$mysqli->begin_transaction();

try {
  // close any active pairing
  $stmt = $mysqli->prepare("UPDATE vehicle_assignments SET end_at=NOW()
                            WHERE vehicle_id=? AND end_at IS NULL");
  $stmt->bind_param('i',$vehicle_id);
  $stmt->execute();
  $stmt->close();

  if ($action==='assign' && $driver_id>0) {
    $assigned_by = first_admin_id($mysqli);
    $stmt = $mysqli->prepare("
      INSERT INTO vehicle_assignments (vehicle_id, driver_id, assigned_by, start_at)
      VALUES (?,?,?,NOW())
    ");
    $stmt->bind_param('iii', $vehicle_id, $driver_id, $assigned_by);
    $stmt->execute();
    $stmt->close();
  }

  $mysqli->commit();
  header('Location: admin-view-vehicle.php?v_id='.$vehicle_id.'&saved=1'); exit;
} catch(Throwable $e){
  $mysqli->rollback();
  header('Location: admin-view-vehicle.php?v_id='.$vehicle_id.'&err=1'); exit;
}
