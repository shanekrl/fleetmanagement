<?php
session_start();
include('vendor/inc/config.php');
include('vendor/inc/checklogin.php');
require_once 'vendor/inc/audit.php';
check_login();

$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET collation_connection='utf8mb4_unicode_ci'");

$action  = trim($_POST['action'] ?? '');
$booking = (int)($_POST['id'] ?? 0);
$reason  = trim($_POST['reason'] ?? '');
$aid     = (int)($_SESSION['account_id'] ?? 0);
$role    = strtolower($_SESSION['role'] ?? '');
$isAdmin = in_array($role, ['admin','superadmin'], true);

function b_exists(mysqli $db, int $id): bool {
  if (!$id) return false;
  $rs = $db->prepare("SELECT 1 FROM bookings WHERE id=? LIMIT 1");
  $rs->bind_param('i',$id); $rs->execute(); $rs->store_result();
  $ok = (bool)$rs->num_rows; $rs->close(); return $ok;
}

if (!$booking || !b_exists($mysqli,$booking)) {
  audit_log($mysqli, $aid, $action ?: 'booking_action', $booking ?: null, [
    'status' => 'failure', 'reason' => 'not_found'
  ]);
  header('Location: '.($_SERVER['HTTP_REFERER'] ?? 'admin-trip-appointment.php')); exit;
}

try {
  switch ($action) {

    case 'admin_cancel':
      if (!$isAdmin) throw new Exception('forbidden');
      $st = $mysqli->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?");
      $st->bind_param('i',$booking); $st->execute(); $st->close();
      audit_log($mysqli, $aid, 'booking_cancel', $booking, [
        'status'=>'success', 'portal'=>'admin', 'reason'=>$reason ?: null
      ]);
      break;

    case 'admin_restore':
      if (!$isAdmin) throw new Exception('forbidden');
      // restore to queue → if driver is set, go to awaiting_driver, else pending
      $rs = $mysqli->prepare("SELECT driver_id FROM bookings WHERE id=?");
      $rs->bind_param('i',$booking); $rs->execute(); $rs->bind_result($drv); $rs->fetch(); $rs->close();
      $to = $drv ? 'awaiting_driver' : 'pending';
      $st = $mysqli->prepare("UPDATE bookings SET status=?, updated_at=NOW() WHERE id=?");
      $st->bind_param('si',$to,$booking); $st->execute(); $st->close();
      audit_log($mysqli, $aid, 'booking_restore', $booking, [
        'status'=>'success', 'portal'=>'admin', 'to'=>$to
      ]);
      break;

    case 'driver_accept':
      if ($isAdmin) throw new Exception('forbidden'); // driver only
      $st = $mysqli->prepare("UPDATE bookings SET status='accepted', updated_at=NOW() WHERE id=?");
      $st->bind_param('i',$booking); $st->execute(); $st->close();
      audit_log($mysqli, $aid, 'booking_accept', $booking, [
        'status'=>'success', 'portal'=>'driver'
      ]);
      break;

    case 'driver_decline':
      if ($isAdmin) throw new Exception('forbidden'); // driver only
      $st = $mysqli->prepare("UPDATE bookings SET status='rejected', updated_at=NOW() WHERE id=?");
      $st->bind_param('i',$booking); $st->execute(); $st->close();
      audit_log($mysqli, $aid, 'booking_decline', $booking, [
        'status'=>'success', 'portal'=>'driver', 'reason'=>$reason ?: null
      ]);
      break;

    case 'trip_start':
      if ($isAdmin) throw new Exception('forbidden'); // driver only
      // if pickup_at column exists, set it
      $col = $mysqli->query("SHOW COLUMNS FROM bookings LIKE 'pickup_at'") && $mysqli->affected_rows !== -1;
      if ($col) {
        $st = $mysqli->prepare("UPDATE bookings SET status='in_progress', pickup_at=NOW(), updated_at=NOW() WHERE id=?");
      } else {
        $st = $mysqli->prepare("UPDATE bookings SET status='in_progress', updated_at=NOW() WHERE id=?");
      }
      $st->bind_param('i',$booking); $st->execute(); $st->close();
      audit_log($mysqli, $aid, 'trip_start', $booking, [
        'status'=>'success', 'portal'=>'driver'
      ]);
      break;

    case 'trip_end':
      if ($isAdmin) throw new Exception('forbidden'); // driver only
      // if dropoff_at column exists, set it
      $col = $mysqli->query("SHOW COLUMNS FROM bookings LIKE 'dropoff_at'") && $mysqli->affected_rows !== -1;
      if ($col) {
        $st = $mysqli->prepare("UPDATE bookings SET status='completed', dropoff_at=NOW(), updated_at=NOW() WHERE id=?");
      } else {
        $st = $mysqli->prepare("UPDATE bookings SET status='completed', updated_at=NOW() WHERE id=?");
      }
      $st->bind_param('i',$booking); $st->execute(); $st->close();
      audit_log($mysqli, $aid, 'trip_end', $booking, [
        'status'=>'success', 'portal'=>'driver'
      ]);
      break;

    default:
      audit_log($mysqli, $aid, 'booking_action_unknown', $booking, [
        'status'=>'failure', 'reason'=>'unknown_action', 'action'=>$action
      ]);
  }

} catch (Throwable $e) {
  audit_log($mysqli, $aid, $action ?: 'booking_action', $booking, [
    'status'=>'failure', 'error'=>$e->getMessage()
  ]);
}

header('Location: '.($_SERVER['HTTP_REFERER'] ?? 'admin-trip-appointment.php'));
