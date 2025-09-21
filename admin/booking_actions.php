<?php
// KAYA booking actions (shared by Admin pages)
session_start();
require_once __DIR__ . '/vendor/inc/config.php';
require_once __DIR__ . '/vendor/inc/checklogin.php';
check_login();

// Always use UTF8MB4 to avoid collation issues
$mysqli->set_charset('utf8mb4');
@$mysqli->query("SET collation_connection='utf8mb4_unicode_ci'");

// helpers
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}
function is_admin_user(): bool {
  return (function_exists('is_admin') && is_admin())
      || isset($_SESSION['a_id']);
}
function actor_id(): int {
  // Prefer accounts.id if present
  if (isset($_SESSION['a_id'])) return (int)$_SESSION['a_id'];
  if (isset($_SESSION['u_id'])) return (int)$_SESSION['u_id'];
  return 0;
}
// Figure out where a booking id lives: bookings | tms_bookings | tms_user (legacy)
function resolve_booking_source(mysqli $db, int $id): ?string {
  // new schema
  if (table_exists($db,'bookings')) {
    if ($st=$db->prepare("SELECT 1 FROM bookings WHERE id=? LIMIT 1")) {
      $st->bind_param('i',$id); $st->execute(); $st->store_result();
      $ok = $st->num_rows > 0; $st->close();
      if ($ok) return 'bookings';
    }
  }
  // legacy intermediate
  if (table_exists($db,'tms_bookings')) {
    if ($st=$db->prepare("SELECT 1 FROM tms_bookings WHERE booking_id=? LIMIT 1")) {
      $st->bind_param('i',$id); $st->execute(); $st->store_result();
      $ok = $st->num_rows > 0; $st->close();
      if ($ok) return 'tms_bookings';
    }
  }
  // ultra-legacy request row
  if (table_exists($db,'tms_user')) {
    if ($st=$db->prepare("SELECT 1 FROM tms_user WHERE u_id=? LIMIT 1")) {
      $st->bind_param('i',$id); $st->execute(); $st->store_result();
      $ok = $st->num_rows > 0; $st->close();
      if ($ok) return 'tms_user';
    }
  }
  return null;
}

function log_event(mysqli $db, int $bookingId, ?int $actorId, string $actorRole, string $eventType, array $details = []): void {
  if (!table_exists($db,'booking_events')) return;
  $sql = "INSERT INTO booking_events(booking_id,actor_id,actor_role,event_type,details)
          VALUES(?,?,?,?,?)";
  if ($st = $db->prepare($sql)) {
    $json = json_encode($details, JSON_UNESCAPED_UNICODE);
    $st->bind_param('iisss', $bookingId, $actorId, $actorRole, $eventType, $json);
    $st->execute();
    $st->close();
  }
}
function back_to(string $fallback = 'admin-trip-appointment.php'){
  $to = $_SERVER['HTTP_REFERER'] ?? $fallback;
  header('Location: ' . $to);
  exit;
}

/* ================= Driver status sync (new schema only) ================= */
function ensure_driver_profile_row(mysqli $db, int $driverId): void {
  if (!$driverId || !table_exists($db,'driver_profile')) return;
  @$db->query("INSERT IGNORE INTO driver_profile(account_id,current_status) VALUES ($driverId,'available')");
}
function sync_driver_status_by_booking(mysqli $db, int $bookingId, ?string $overrideStatus = null): void {
  if (!$bookingId || !table_exists($db,'bookings')) return;

  $driverId = 0; $status = '';
  if ($st = $db->prepare("SELECT COALESCE(driver_id,0), status FROM bookings WHERE id=?")) {
    $st->bind_param('i',$bookingId);
    $st->execute(); $st->bind_result($driverId,$status); $st->fetch(); $st->close();
  }
  if (!$driverId || !table_exists($db,'driver_profile')) return;

  ensure_driver_profile_row($db, (int)$driverId);

  $st = strtolower((string)($overrideStatus ?? $status));
  $new = ($st === 'in_progress') ? 'on_trip' : 'available';

  if ($u = $db->prepare("UPDATE driver_profile SET current_status=?, updated_at=NOW() WHERE account_id=?")) {
    $u->bind_param('si',$new,$driverId);
    $u->execute();
    $u->close();
  }
}
/* ====================================================================== */

// route 
$action = $_POST['action'] ?? '';
$bid    = isset($_POST['id']) ? (int)$_POST['id'] : 0;

if (!$action || !$bid) back_to();

// Resolve where this id actually lives
$src = resolve_booking_source($mysqli, $bid);
$actorRole = is_admin_user() ? 'admin' : 'driver';
$actorId   = actor_id();

// ADMIN ACTIONS
if ($action === 'admin_cancel' && is_admin_user()) {
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
      log_event($mysqli,$bid,$actorId,'admin','cancel');
      sync_driver_status_by_booking($mysqli,$bid,'cancelled');
    }
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='cancelled' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Cancel' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to('admin-manage-booking.php');
}

if ($action === 'admin_approve' && is_admin_user()) {
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='accepted', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
      log_event($mysqli,$bid,$actorId,'admin','assign');
      sync_driver_status_by_booking($mysqli,$bid,'accepted'); // still available until start
    }
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='accepted' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Approved' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to();
}

if ($action === 'admin_complete' && is_admin_user()) {
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='completed', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
      log_event($mysqli,$bid,$actorId,'admin','complete_trip');
      sync_driver_status_by_booking($mysqli,$bid,'completed');
    }
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='completed' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Completed' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to('admin-view-booking.php');
}

// restore action (move back to queue / pending)
if ($action === 'admin_restore' && is_admin_user()) {
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='awaiting_driver', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
      log_event($mysqli,$bid,$actorId,'admin','restore');
      sync_driver_status_by_booking($mysqli,$bid,'awaiting_driver');
    }
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='pending' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Pending' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to('admin-trip-appointment.php');
}

if ($action === 'admin_delete' && is_admin_user()) {
  if ($src === 'bookings') {
    // booking_events/booking_offers/booking_runs should have ON DELETE CASCADE
    if ($s = $mysqli->prepare("DELETE FROM bookings WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
    // no status sync after hard delete
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("DELETE FROM tms_bookings WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("DELETE FROM tms_user WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to('admin-manage-booking.php');
}

// DRIVER ACTIONS
if ($action === 'driver_accept') {
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='accepted', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
      log_event($mysqli,$bid,$actorId,'driver','accept');
      sync_driver_status_by_booking($mysqli,$bid,'accepted');
    }
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='accepted' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Approved' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to();
}

if ($action === 'driver_decline') {
  $reason = trim((string)($_POST['reason'] ?? ''));
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='rejected', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
      log_event($mysqli,$bid,$actorId,'driver','reject',['reason'=>$reason]);
      sync_driver_status_by_booking($mysqli,$bid,'rejected');
    }
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='rejected' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else { // tms_user
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Cancel' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to('admin-manage-booking.php');
}

// TRIP START / END 
if ($action === 'trip_start') {
  if ($src === 'bookings') {
    // Start ride -> in_progress, record pickup_button_at
    if ($s = $mysqli->prepare("UPDATE bookings SET status='in_progress', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
    if (table_exists($mysqli,'booking_runs')) {
      $mysqli->query("INSERT IGNORE INTO booking_runs(booking_id,pickup_button_at) VALUES ($bid,NOW())");
      $mysqli->query("UPDATE booking_runs SET pickup_button_at=COALESCE(pickup_button_at,NOW()) WHERE booking_id=$bid");
    }
    log_event($mysqli,$bid,$actorId,$actorRole,'start_trip');
    sync_driver_status_by_booking($mysqli,$bid,'in_progress');
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='in_progress' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else {
    // ultra-legacy has no start/end markers
  }
  back_to();
}

if ($action === 'trip_end') {
  if ($src === 'bookings') {
    if ($s = $mysqli->prepare("UPDATE bookings SET status='completed', updated_at=NOW() WHERE id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
    if (table_exists($mysqli,'booking_runs')) {
      $mysqli->query("UPDATE booking_runs SET dropoff_button_at=NOW() WHERE booking_id=$bid");
    }
    log_event($mysqli,$bid,$actorId,$actorRole,'complete_trip');
    sync_driver_status_by_booking($mysqli,$bid,'completed');
  } elseif ($src === 'tms_bookings') {
    if ($s = $mysqli->prepare("UPDATE tms_bookings SET status='completed' WHERE booking_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  } else {
    if ($s = $mysqli->prepare("UPDATE tms_user SET u_car_book_status='Completed' WHERE u_id=?")) {
      $s->bind_param('i',$bid); $s->execute(); $s->close();
    }
  }
  back_to('admin-view-booking.php');
}

// Default fallback
back_to();
