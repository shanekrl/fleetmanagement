<?php
// api/calendar-events.php
session_start();
header('Content-Type: application/json');

require_once __DIR__.'/../vendor/inc/config.php';
$mysqli->set_charset('utf8mb4');

// helpers
function table_exists(mysqli $db, string $t): bool {
  $t = $db->real_escape_string($t);
  $r = $db->query("SHOW TABLES LIKE '{$t}'");
  return $r && $r->num_rows > 0;
}

$start = isset($_GET['start']) ? $_GET['start'] : null; // ISO date from FullCalendar
$end   = isset($_GET['end'])   ? $_GET['end']   : null;

// optional filters
$status     = isset($_GET['status']) ? (array)$_GET['status'] : [];
$driver_id  = isset($_GET['driver_id']) ? (int)$_GET['driver_id'] : null;

// status map -> colors
$colors = [
  'pending'         => ['#8e9aaf', '#ffffff'],
  'awaiting_driver' => ['#00b4d8', '#ffffff'],
  'accepted'        => ['#2ecc71', '#ffffff'],
  'in_progress'     => ['#2d6cdf', '#ffffff'],
  'rejected'        => ['#f39c12', '#000000'],
  'cancelled'       => ['#e74c3c', '#ffffff'],
  'completed'       => ['#16a085', '#ffffff'],
];

$events = [];

/**
 * Strategy:
 *   1) Prefer v_appointments_calendar (new schema, has start/end/title).
 *   2) If not present, synthesize from v_booking_grid (legacy rows included).
 *   3) If neither view exists, synthesize from bookings (new table only).
 */
$where = [];
$params = []; $types = '';

if ($start && $end) {
  // Filter by range (covers events that start within range OR overlap)
  $where[] = "( (start_at IS NOT NULL AND start_at < ?) AND (COALESCE(end_at, DATE_ADD(start_at, INTERVAL 2 HOUR)) >= ?) )";
  $types  .= 'ss'; $params[] = $end; $params[] = $start;
}
if (!empty($status)) {
  // sanitize status
  $safe = array_values(array_filter(array_map(function($s){ return preg_replace('/[^a-z_]/','', strtolower($s)); }, $status)));
  if ($safe) {
    $in = implode(',', array_fill(0, count($safe), '?'));
    $where[] = "LOWER(status) IN ($in)";
    $types  .= str_repeat('s', count($safe));
    foreach ($safe as $s) $params[] = $s;
  }
}
if ($driver_id) {
  $where[] = "driver_id = ?";
  $types  .= 'i';
  $params[] = $driver_id;
}

function run_stmt($mysqli, $sql, $types, $params) {
  $stmt = $mysqli->prepare($sql);
  if (!$stmt) return false;
  if ($types) $stmt->bind_param($types, ...$params);
  $stmt->execute();
  return $stmt->get_result();
}

if (table_exists($mysqli, 'v_appointments_calendar')) {
  $sql = "SELECT booking_id, title, start_at, end_at, status, driver_id, vehicle_id
          FROM v_appointments_calendar";
  if ($where) $sql .= " WHERE ".implode(' AND ', $where);

  $res = run_stmt($mysqli, $sql, $types, $params);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $st = strtolower($r['status'] ?? 'pending');
      [$bg,$fg] = $colors[$st] ?? ['#8e9aaf','#ffffff'];
      $events[] = [
        'id'    => (string)$r['booking_id'],
        'title' => $r['title'] ?: ('Booking #'.$r['booking_id']),
        'start' => $r['start_at'],
        'end'   => $r['end_at'] ?: null,
        'extendedProps' => [
          'status'    => $st,
          'driver_id' => $r['driver_id'],
          'vehicle_id'=> $r['vehicle_id'],
        ],
        'backgroundColor' => $bg,
        'borderColor'     => $bg,
        'textColor'       => $fg,
      ];
    }
  }
}
elseif (table_exists($mysqli, 'v_booking_grid')) {
  // synthesize: title, start from scheduled_at; assume 2h duration if missing
  $w = [];
  $t = ''; $p = [];
  if ($start && $end) { $w[]="(scheduled_at < ? AND scheduled_at >= DATE_SUB(?, INTERVAL 1 DAY))"; $t.='ss'; $p[]=$end; $p[]=$start; }
  if (!empty($status)) { $w[]="LOWER(status) IN (".implode(',', array_fill(0,count($status),'?')).")"; $t.=str_repeat('s',count($status)); foreach($status as $s){ $p[] = strtolower($s);} }
  if ($driver_id) { $w[]="driver_id = ?"; $t.='i'; $p[]=$driver_id; }

  $sql = "SELECT booking_id, scheduled_at, status, pickup, dropoff, booking_type, driver_id
          FROM v_booking_grid";
  if ($w) $sql .= " WHERE ".implode(' AND ',$w);

  $res = run_stmt($mysqli, $sql, $t, $p);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $st = strtolower($r['status'] ?? 'pending');
      [$bg,$fg] = $colors[$st] ?? ['#8e9aaf','#ffffff'];
      $title = strtoupper($r['booking_type'] ?: 'ADMIN').' • '.($r['pickup'] ?? '').' → '.($r['dropoff'] ?? '');
      $startAt = $r['scheduled_at'] ?: null;
      $endAt   = $startAt ? date('Y-m-d H:i:s', strtotime($startAt.' +2 hours')) : null;

      $events[] = [
        'id'    => (string)$r['booking_id'],
        'title' => $title,
        'start' => $startAt,
        'end'   => $endAt,
        'extendedProps' => [
          'status'    => $st,
          'driver_id' => $r['driver_id'],
        ],
        'backgroundColor' => $bg,
        'borderColor'     => $bg,
        'textColor'       => $fg,
      ];
    }
  }
}
elseif (table_exists($mysqli, 'bookings')) {
  // last fallback: bookings table only
  $w = [];
  $t = ''; $p = [];
  if ($start && $end) { $w[]="(COALESCE(scheduled_start_at, created_at) < ? AND COALESCE(scheduled_end_at, DATE_ADD(COALESCE(scheduled_start_at, created_at), INTERVAL 2 HOUR)) >= ?)"; $t.='ss'; $p[]=$end; $p[]=$start; }
  if (!empty($status)) { $w[]="LOWER(status) IN (".implode(',', array_fill(0,count($status),'?')).")"; $t.=str_repeat('s',count($status)); foreach($status as $s){ $p[] = strtolower($s);} }
  if ($driver_id) { $w[]="driver_id = ?"; $t.='i'; $p[]=$driver_id; }

  $sql = "SELECT id AS booking_id,
                 CONCAT(UCASE(booking_type),' • ', pickup_point,' → ', dropoff_point) AS title,
                 scheduled_start_at AS start_at,
                 scheduled_end_at   AS end_at,
                 status, driver_id
          FROM bookings";
  if ($w) $sql .= " WHERE ".implode(' AND ',$w);

  $res = run_stmt($mysqli, $sql, $t, $p);
  if ($res) {
    while ($r = $res->fetch_assoc()) {
      $st = strtolower($r['status'] ?? 'pending');
      [$bg,$fg] = $colors[$st] ?? ['#8e9aaf','#ffffff'];

      $events[] = [
        'id'    => (string)$r['booking_id'],
        'title' => $r['title'] ?: ('Booking #'.$r['booking_id']),
        'start' => $r['start_at'],
        'end'   => $r['end_at'] ?: null,
        'extendedProps' => [
          'status'    => $st,
          'driver_id' => $r['driver_id'],
        ],
        'backgroundColor' => $bg,
        'borderColor'     => $bg,
        'textColor'       => $fg,
      ];
    }
  }
}

echo json_encode($events);
