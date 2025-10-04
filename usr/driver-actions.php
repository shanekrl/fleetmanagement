<?php
// driver-actions.php  (works from project root OR /usr)
// JSON API for driver actions on NEW DB (bookings, booking_runs, booking_events)

session_start();

/* Robust includes whether this file lives at / or /usr */
$HERE = __DIR__;
$tryPaths = [
  $HERE . '/vendor/inc/config.php',
  $HERE . '/../vendor/inc/config.php'
];
$found = false;
foreach ($tryPaths as $p) { if (file_exists($p)) { require_once $p; $found = true; break; } }
if (!$found) { http_response_code(500); echo json_encode(['error'=>'Config not found']); exit; }

$tryPaths = [
  $HERE . '/vendor/inc/checklogin.php',
  $HERE . '/../vendor/inc/checklogin.php'
];
foreach ($tryPaths as $p) { if (file_exists($p)) { require_once $p; break; } }
if (function_exists('check_login')) { check_login(); }

header('Content-Type: application/json');

$payload   = json_decode(file_get_contents('php://input'), true) ?: [];
$action    = $payload['action'] ?? null;
$bookingId = (int)($payload['booking_id'] ?? 0);
$reason    = trim((string)($payload['reason'] ?? ''));

if (!$action || !$bookingId) {
  http_response_code(400);
  echo json_encode(['error'=>'Invalid request']); exit;
}

/* Current driver id (accounts.id) */
$driverAccountId = (int)($_SESSION['account_id'] ?? $_SESSION['driver_account_id'] ?? 0);
if (!$driverAccountId) { http_response_code(403); echo json_encode(['error'=>'Not signed in']); exit; }

/* Load booking (NEW DB ONLY) */
$booking = null;
if ($s=$mysqli->prepare("SELECT id, driver_id, vehicle_id, booking_type, status FROM bookings WHERE id=? LIMIT 1")){
  $s->bind_param('i',$bookingId);
  $s->execute();
  $booking = $s->get_result()->fetch_assoc();
  $s->close();
}
if (!$booking) { http_response_code(404); echo json_encode(['error'=>'Booking not found']); exit; }

/* Security: only the assigned driver may act */
if ((int)$booking['driver_id'] !== $driverAccountId) {
  http_response_code(403);
  echo json_encode(['error'=>'Not your booking']); exit;
}

/* helper: event log */
function add_event(mysqli $db, int $bookingId, int $actorId, string $role, string $type, array $details = []) {
  $json = json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  $sql = "INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
          VALUES(?,?,?,?,?)";
  $st = $db->prepare($sql);
  $st->bind_param('iisss', $bookingId, $actorId, $role, $type, $json);
  $st->execute(); $st->close();
}

$mysqli->begin_transaction();
try {
  /* ACCEPT */
  if ($action === 'accept') {
    $sql = "UPDATE bookings
               SET status='accepted', updated_at=NOW()
             WHERE id=? AND driver_id=?
               AND status NOT IN ('accepted','in_progress','completed','cancelled','rejected')";
    $st = $mysqli->prepare($sql);
    $st->bind_param('ii',$bookingId,$driverAccountId);
    $st->execute();
    if ($st->affected_rows === 0) { throw new Exception('Nothing to update (already handled?)'); }
    $st->close();
    add_event($mysqli, $bookingId, $driverAccountId, 'driver', 'accept', []);
    $newStatus = 'accepted';
  }

  /* REJECT (requires reason) */
  elseif ($action === 'reject') {
    if ($reason==='') { throw new Exception('Reason is required'); }
    $sql = "UPDATE bookings
               SET status='rejected', driver_id=NULL, updated_at=NOW(),
                   notes=CONCAT(COALESCE(notes,''), CASE WHEN ?<>'' THEN CONCAT('\nDriver reject reason: ',?) ELSE '' END)
             WHERE id=? AND driver_id=?
               AND status NOT IN ('in_progress','completed','cancelled')";
    $st = $mysqli->prepare($sql);
    $st->bind_param('ssii',$reason,$reason,$bookingId,$driverAccountId);
    $st->execute();
    if ($st->affected_rows === 0) { throw new Exception('Nothing to update (already handled?)'); }
    $st->close();
    add_event($mysqli, $bookingId, $driverAccountId, 'driver', 'reject', ['reason'=>$reason]);
    $newStatus = 'rejected';
  }

  /* START TRIP: accepted -> in_progress, ensure booking_runs row + pickup timestamp */
  elseif ($action === 'start_trip') {
    $st = $mysqli->prepare("UPDATE bookings
                               SET status='in_progress', updated_at=NOW()
                             WHERE id=? AND driver_id=? AND status='accepted'");
    $st->bind_param('ii',$bookingId,$driverAccountId);
    $st->execute();
    if ($st->affected_rows === 0) { throw new Exception('Trip must be accepted first'); }
    $st->close();

    // create booking_runs if missing
    $q = $mysqli->prepare("INSERT INTO booking_runs(booking_id, vehicle_id, driver_id, pickup_button_at)
                           SELECT b.id, b.vehicle_id, b.driver_id, NOW()
                             FROM bookings b
                            WHERE b.id=? AND NOT EXISTS(SELECT 1 FROM booking_runs br WHERE br.booking_id=b.id)");
    $q->bind_param('i',$bookingId);
    $q->execute(); $q->close();

    // if exists but no pickup time yet, set it
    $mysqli->query("UPDATE booking_runs SET pickup_button_at=COALESCE(pickup_button_at,NOW())
                     WHERE booking_id={$bookingId}");

    add_event($mysqli, $bookingId, $driverAccountId, 'driver', 'start_trip', []);
    $newStatus = 'in_progress';
  }

  /* UNDO START: in_progress -> accepted (for accidental start) */
  elseif ($action === 'undo_start') {
    $st = $mysqli->prepare("UPDATE bookings
                               SET status='accepted', updated_at=NOW()
                             WHERE id=? AND driver_id=? AND status='in_progress'");
    $st->bind_param('ii',$bookingId,$driverAccountId);
    $st->execute();
    if ($st->affected_rows === 0) { throw new Exception('Trip is not in progress'); }
    $st->close();

    // clear pickup timestamp if dropoff hasn't been recorded
    $mysqli->query("UPDATE booking_runs
                       SET pickup_button_at=NULL, duration_seconds=NULL
                     WHERE booking_id={$bookingId} AND dropoff_button_at IS NULL");

    add_event($mysqli, $bookingId, $driverAccountId, 'driver', 'restore', ['from'=>'in_progress','to'=>'accepted']);
    $newStatus = 'accepted';
  }

  /* END TRIP: in_progress -> completed, set dropoff + duration */
  elseif ($action === 'end_trip') {
    $st = $mysqli->prepare("UPDATE bookings
                               SET status='completed', updated_at=NOW()
                             WHERE id=? AND driver_id=? AND status='in_progress'");
    $st->bind_param('ii',$bookingId,$driverAccountId);
    $st->execute();
    if ($st->affected_rows === 0) { throw new Exception('Trip is not in progress'); }
    $st->close();

    $mysqli->query("UPDATE booking_runs
                       SET dropoff_button_at=COALESCE(dropoff_button_at,NOW()),
                           duration_seconds = CASE
                             WHEN pickup_button_at IS NOT NULL
                             THEN TIMESTAMPDIFF(SECOND, pickup_button_at, COALESCE(dropoff_button_at,NOW()))
                             ELSE duration_seconds
                           END
                     WHERE booking_id={$bookingId}");

    add_event($mysqli, $bookingId, $driverAccountId, 'driver', 'complete_trip', []);
    $newStatus = 'completed';
  }

  /* CANCEL from accepted or in_progress (admin requires reason) */
  elseif ($action === 'cancel_trip') {
    if ($booking['booking_type']==='admin' && $reason==='') { throw new Exception('Reason is required'); }
    $st = $mysqli->prepare("UPDATE bookings
                               SET status='cancelled', updated_at=NOW(),
                                   notes=CONCAT(COALESCE(notes,''), CASE WHEN ?<>'' THEN CONCAT('\nDriver cancel reason: ',?) ELSE '' END)
                             WHERE id=? AND driver_id=? AND status IN ('accepted','in_progress')");
    $st->bind_param('ssii',$reason,$reason,$bookingId,$driverAccountId);
    $st->execute();
    if ($st->affected_rows === 0) { throw new Exception('Nothing to cancel'); }
    $st->close();

    // if trip was in progress, close the run with a dropoff at cancel time
    if ($booking['status'] === 'in_progress') {
      $mysqli->query("UPDATE booking_runs
                         SET dropoff_button_at=COALESCE(dropoff_button_at,NOW()),
                             duration_seconds = CASE
                               WHEN pickup_button_at IS NOT NULL
                               THEN TIMESTAMPDIFF(SECOND, pickup_button_at, COALESCE(dropoff_button_at,NOW()))
                               ELSE duration_seconds
                             END
                       WHERE booking_id={$bookingId}");
    }

    add_event($mysqli, $bookingId, $driverAccountId, 'driver', 'cancel', ['reason'=>$reason]);
    $newStatus = 'cancelled';
  }

  else {
    throw new Exception('Unsupported action');
  }

  $mysqli->commit();
  echo json_encode(['ok'=>true,'status'=>$newStatus]);
} catch (Throwable $e) {
  $mysqli->rollback();
  http_response_code(422);
  echo json_encode(['error'=>$e->getMessage()]);
}
