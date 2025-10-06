<?php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

$driver_id  = require_driver();
$booking_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

if ($driver_id && $booking_id) {
    $sql = "UPDATE tms_bookings
               SET status = 'declined', driver_id = NULL
             WHERE booking_id = ? AND driver_id = ? AND status = 'pending'";
    if ($stmt = $mysqli->prepare($sql)) {
        $stmt->bind_param('ii', $booking_id, $driver_id);
        $stmt->execute();
        if ($stmt->affected_rows > 0) {
            $_SESSION['success'] = "Booking declined.";
        } else {
            $_SESSION['error'] = "Could not decline booking. It may already be handled or not assigned to you.";
        }
        $stmt->close();
    }
}
header('Location: user-dashboard.php');
exit();
