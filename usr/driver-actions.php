<?php
include 'driver_bootstrap.php';
$driver_id = current_driver_id($mysqli);
if ($driver_id <= 0) { http_response_code(403); exit; }

$action = $_POST['action'] ?? '';
$redir  = $_SERVER['HTTP_REFERER'] ?? 'driver-trips.php';

function go($url){ header("Location: ".$url); exit; }

switch ($action) {

case 'accept_offer': {
    $offer_id = (int)($_POST['offer_id'] ?? 0);
    if ($offer_id) {
        $s = $mysqli->prepare("UPDATE booking_offers SET response='accepted', response_at=NOW() WHERE id=? AND driver_id=? AND response='pending'");
        $s->bind_param('ii', $offer_id, $driver_id); $s->execute(); $s->close();
    }
    go($redir);
}

case 'reject_offer': {
    $offer_id = (int)($_POST['offer_id'] ?? 0);
    $reason   = trim($_POST['driver_reason'] ?? '');
    if ($offer_id && $reason!=='') {
        $s = $mysqli->prepare("UPDATE booking_offers 
                                  SET response='rejected', driver_reason=?, response_at=NOW()
                                WHERE id=? AND driver_id=? AND response='pending'");
        $s->bind_param('sii', $reason, $offer_id, $driver_id); $s->execute(); $s->close();
    }
    go($redir);
}

case 'start_pickup': {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    // Guard: must belong to this driver and be accepted
    $q = $mysqli->query("SELECT id FROM bookings WHERE id={$booking_id} AND driver_id={$driver_id} AND status='accepted' LIMIT 1");
    if ($q && $q->num_rows) {
        // upsert run row
        $mysqli->query("INSERT INTO booking_runs (booking_id, driver_id, pickup_button_at) 
                        VALUES ({$booking_id}, {$driver_id}, NOW())
                        ON DUPLICATE KEY UPDATE driver_id=VALUES(driver_id),
                                                pickup_button_at=IFNULL(pickup_button_at, NOW())");
        $mysqli->query("UPDATE bookings SET status='in_progress', updated_at=NOW() WHERE id={$booking_id}");
        $mysqli->query("INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
                        VALUES({$booking_id}, {$driver_id}, 'driver', 'start_trip', JSON_OBJECT())");
    }
    go($redir);
}

case 'finish_dropoff': {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    $q = $mysqli->query("SELECT id FROM bookings WHERE id={$booking_id} AND driver_id={$driver_id} AND status='in_progress' LIMIT 1");
    if ($q && $q->num_rows) {
        $mysqli->query("
            UPDATE booking_runs 
               SET dropoff_button_at = NOW(),
                   duration_seconds   = CASE 
                                          WHEN pickup_button_at IS NULL THEN 0
                                          ELSE TIMESTAMPDIFF(SECOND, pickup_button_at, NOW())
                                        END
             WHERE booking_id={$booking_id}");
        $mysqli->query("UPDATE bookings SET status='completed', updated_at=NOW() WHERE id={$booking_id}");
        $mysqli->query("INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
                        VALUES({$booking_id}, {$driver_id}, 'driver', 'complete_trip', JSON_OBJECT())");
    }
    go($redir);
}

case 'cancel_personal': {
    $booking_id = (int)($_POST['booking_id'] ?? 0);
    // can cancel only if created_by me, type personal, not started
    $q = $mysqli->query("SELECT id FROM bookings 
                          WHERE id={$booking_id} AND booking_type='personal' 
                            AND created_by={$driver_id} 
                            AND status NOT IN ('in_progress','completed','cancelled')
                          LIMIT 1");
    if ($q && $q->num_rows) {
        $mysqli->query("UPDATE bookings SET status='cancelled', updated_at=NOW() WHERE id={$booking_id}");
        $mysqli->query("INSERT INTO booking_events(booking_id, actor_id, actor_role, event_type, details)
                        VALUES({$booking_id}, {$driver_id}, 'driver', 'cancel', JSON_OBJECT('by','driver'))");
    }
    go($redir);
}

default: go($redir);
}
