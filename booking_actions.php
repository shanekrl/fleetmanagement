<?php
// booking_actions.php (admin/driver form POST actions, NEW DB model)
// - uses bookings.id
// - logs to booking_events (actor_role/event_type/json details)
// - uses booking_runs for start/finish timestamps

session_start();

/* Load shared config + guard */
$HERE = __DIR__;
$ok = false;
foreach ([$HERE.'/admin/vendor/inc/config.php', $HERE.'/usr/vendor/inc/config.php'] as $p) {
  if (is_file($p)) { require_once $p; $ok = true; break; }
}
if (!$ok) { http_response_code(500); exit('Config not found'); }

$ok = false;
foreach ([$HERE.'/admin/vendor/inc/checklogin.php', $HERE.'/usr/vendor/inc/checklogin.php'] as $p) {
  if (is_file($p)) { require_once $p; $ok = true; break; }
}
if (!$ok) { http_response_code(500); exit('Auth guard not found'); }

if ($_SERVER['REQUEST_METHOD'] !== 'POST') { http_response_code(405); exit; }

/* Who is acting? default admin for admin forms; some driver pages still post here */
$actor_role = isset($_POST['actor_type']) && $_POST['actor_type'] === 'driver' ? 'driver' : 'admin';
$actor_id   = 0;
if ($actor_role === 'admin') {
  $actor_id = require_admin();   // accounts.id (admin/superadmin)
} else {
  $actor_id = require_driver();  // accounts.id (driver)
}

$redirect   = $_POST['redirect'] ?? 'admin-trip-appointment.php';
$action     = trim((string)($_POST['action'] ?? ''));
$id         = (int)($_POST['booking_id'] ?? 0);      // NEW: bookings.id
$driver_id  = isset($_POST['driver_id']) ? (int)$_POST['driver_id'] : 0;
$reason     = trim((string)($_POST['reason'] ?? ''));

/* Helpers */
function add_event(mysqli $db, int $bookingId, int $actorId, string $role, string $type, array $details = []) {
  $json = json_encode($details, JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);
  if ($st = $db->prepare("INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details) VALUES (?,?,?,?,?)")) {
    $st->bind_param('iisss', $bookingId, $actorId, $role, $type, $json);
    $st->execute(); $st->close();
  }
}

/* Load booking (for sanity/security checks) */
$booking = null;
if ($id > 0 && ($st = $mysqli->prepare("SELECT id, driver_id, booking_type, status, vehicle_id FROM bookings WHERE id=? LIMIT 1"))) {
  $st->bind_param('i', $id);
  $st->execute(); $booking = $st->get_result()->fetch_assoc(); $st->close();
}
if (!$booking) { header("Location: $redirect"); exit; }

try {
  $mysqli->begin_transaction();

  switch ($action) {
    /* ===== Admin assigns a driver ===== */
    case 'assign': {
      if ($actor_role !== 'admin' || $driver_id <= 0) break;

      $q = $mysqli->prepare("UPDATE bookings
                                SET driver_id=?, status='awaiting_driver', updated_at=NOW()
                              WHERE id=?");
      $q->bind_param('ii', $driver_id, $id);
      $q->execute(); $q->close();

      add_event($mysqli, $id, $actor_id, 'admin', 'assign_driver', ['driver_id'=>$driver_id]);
      break;
    }

    /* ===== Driver accepts ===== */
    case 'accept': {
      if ($actor_role !== 'driver') break;

      $q = $mysqli->prepare("UPDATE bookings
                                SET status='accepted', updated_at=NOW()
                              WHERE id=? AND driver_id=? AND status IN ('pending','awaiting_driver')");
      $q->bind_param('ii', $id, $actor_id);
      $q->execute(); $q->close();

      add_event($mysqli, $id, $actor_id, 'driver', 'accept', []);
      break;
    }

    /* ===== Driver declines (rejected) ===== */
    case 'decline': {
      if ($actor_role !== 'driver') break;

      $q = $mysqli->prepare("UPDATE bookings
                                SET status='rejected', driver_id=NULL, updated_at=NOW(),
                                    notes = CONCAT(COALESCE(notes,''), CASE WHEN ?<>'' THEN CONCAT('\nDriver reject reason: ',?) ELSE '' END)
                              WHERE id=? AND driver_id=? AND status IN ('pending','awaiting_driver')");
      $q->bind_param('ssii', $reason, $reason, $id, $actor_id);
      $q->execute(); $q->close();

      add_event($mysqli, $id, $actor_id, 'driver', 'reject', ['reason'=>$reason]);
      break;
    }

    /* ===== Admin verdict on a decline (optional, keep simple) =====
       approve  → keep unassigned & set back to pending
       reject   → force-accept (assign back and mark accepted) */
    case 'admin_verdict': {
      if ($actor_role !== 'admin') break;
      $verdict = ($_POST['verdict'] ?? '') === 'approve' ? 'approved' : 'rejected';

      if ($verdict === 'approved') {
        $q = $mysqli->prepare("UPDATE bookings
                                  SET status='pending', driver_id=NULL, updated_at=NOW()
                                WHERE id=?");
        $q->bind_param('i', $id);
        $q->execute(); $q->close();
      } else {
        // if a driver_id is supplied, (re)assign and accept
        if ($driver_id > 0) {
          $q = $mysqli->prepare("UPDATE bookings
                                    SET driver_id=?, status='accepted', updated_at=NOW()
                                  WHERE id=?");
          $q->bind_param('ii', $driver_id, $id);
          $q->execute(); $q->close();
        } else {
          // else just mark accepted as admin decision (keeps current driver_id if any)
          $mysqli->query("UPDATE bookings SET status='accepted', updated_at=NOW() WHERE id={$id}");
        }
      }
      add_event($mysqli, $id, $actor_id, 'admin', 'admin_verdict', ['verdict'=>$verdict, 'driver_id'=>$driver_id ?: null]);
      break;
    }

    /* ===== Start Trip (driver) ===== */
    case 'start_trip': {
      if ($actor_role !== 'driver') break;

      $q = $mysqli->prepare("UPDATE bookings
                                SET status='in_progress', updated_at=NOW()
                              WHERE id=? AND driver_id=? AND status='accepted'");
      $q->bind_param('ii', $id, $actor_id);
      $q->execute(); $q->close();

      // ensure a booking_runs row exists & pickup time set
      if ($p = $mysqli->prepare("INSERT INTO booking_runs(booking_id, vehicle_id, driver_id, pickup_button_at)
                                 SELECT b.id, b.vehicle_id, b.driver_id, NOW()
                                   FROM bookings b
                                  WHERE b.id=? AND NOT EXISTS(SELECT 1 FROM booking_runs r WHERE r.booking_id=b.id)")) {
        $p->bind_param('i', $id); $p->execute(); $p->close();
      }
      $mysqli->query("UPDATE booking_runs SET pickup_button_at=COALESCE(pickup_button_at,NOW()) WHERE booking_id={$id}");

      add_event($mysqli, $id, $actor_id, 'driver', 'start_trip', []);
      break;
    }

    /* ===== Finish Trip (driver) ===== */
    case 'finish_trip': {
      if ($actor_role !== 'driver') break;

      $q = $mysqli->prepare("UPDATE bookings
                                SET status='completed', updated_at=NOW()
                              WHERE id=? AND driver_id=? AND status='in_progress'");
      $q->bind_param('ii', $id, $actor_id);
      $q->execute(); $q->close();

      $mysqli->query("UPDATE booking_runs
                         SET dropoff_button_at=COALESCE(dropoff_button_at,NOW()),
                             duration_seconds = CASE
                               WHEN pickup_button_at IS NOT NULL
                               THEN TIMESTAMPDIFF(SECOND, pickup_button_at, COALESCE(dropoff_button_at,NOW()))
                               ELSE duration_seconds
                             END
                       WHERE booking_id={$id}");

      add_event($mysqli, $id, $actor_id, 'driver', 'complete_trip', []);
      break;
    }

    /* ===== Cancel (admin or driver) ===== */
    case 'cancel': {
      // admin can cancel anytime; driver can cancel accepted/in_progress (admin bookings require reason)
      if ($actor_role === 'driver') {
        // fetch fresh booking type/status to enforce reason rule for admin bookings
        $bk = $mysqli->query("SELECT booking_type, status FROM bookings WHERE id={$id}")->fetch_assoc();
        if ($bk && $bk['booking_type'] === 'admin' && $reason === '') {
          throw new Exception('Reason is required for admin bookings.');
        }
      }

      $q = $mysqli->prepare("UPDATE bookings
                                SET status='cancelled', updated_at=NOW(),
                                    notes=CONCAT(COALESCE(notes,''), CASE WHEN ?<>'' THEN CONCAT('\nCancel reason: ',?) ELSE '' END)
                              WHERE id=?");
      $q->bind_param('ssi', $reason, $reason, $id);
      $q->execute(); $q->close();

      // if it was in progress, close run
      $mysqli->query("UPDATE booking_runs
                         SET dropoff_button_at=COALESCE(dropoff_button_at,NOW()),
                             duration_seconds = CASE
                               WHEN pickup_button_at IS NOT NULL
                               THEN TIMESTAMPDIFF(SECOND, pickup_button_at, COALESCE(dropoff_button_at,NOW()))
                               ELSE duration_seconds
                             END
                       WHERE booking_id={$id}");

      add_event($mysqli, $id, $actor_id, $actor_role, 'cancel', ['reason'=>$reason ?: null]);
      break;
    }

    /* ===== Restore a cancelled booking (admin only; drivers can only restore personal via driver API) ===== */
    case 'restore': {
      if ($actor_role !== 'admin') break;
      $q = $mysqli->prepare("UPDATE bookings SET status='pending', updated_at=NOW() WHERE id=? AND status='cancelled'");
      $q->bind_param('i', $id);
      $q->execute(); $q->close();

      add_event($mysqli, $id, $actor_id, 'admin', 'restore_cancelled', []);
      break;
    }

    /* ===== Hard delete (admin only) ===== */
    case 'hard_delete': {
      if ($actor_role !== 'admin') break;
      // optional safety: only allow if cancelled
      $mysqli->query("DELETE FROM bookings WHERE id={$id} AND status='cancelled'");
      add_event($mysqli, $id, $actor_id, 'admin', 'delete_cancelled', []);
      break;
    }
  }

  $mysqli->commit();
} catch (Throwable $e) {
  $mysqli->rollback();
  // surface error to admin UIs; for driver UIs you might prefer session flash
  $_SESSION['error'] = $e->getMessage();
}

header("Location: $redirect");
