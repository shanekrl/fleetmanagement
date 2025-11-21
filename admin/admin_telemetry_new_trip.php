<?php
// diagnostics.php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

include('vendor/inc/config.php');
header('Content-Type: application/json');



    // --- If called via POST Save new diagnostic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $customer    = $mysqli->real_escape_string(trim($_POST['customer'] ?? ''));
    $phone       = $mysqli->real_escape_string(trim($_POST['phone'] ?? ''));
    $pax         = max(1, (int)($_POST['pax'] ?? 1));
    $pickup      = $mysqli->real_escape_string(trim($_POST['pickup'] ?? ''));
    $dropoff     = $mysqli->real_escape_string(trim($_POST['dropoff'] ?? ''));
    $pickup_lat  = $_POST['pickup_lat'] !== '' ? (float)$_POST['pickup_lat'] : 'NULL';
    $pickup_lng  = $_POST['pickup_lng'] !== '' ? (float)$_POST['pickup_lng'] : 'NULL';
    $dropoff_lat = $_POST['dropoff_lat'] !== '' ? (float)$_POST['dropoff_lat'] : 'NULL';
    $dropoff_lng = $_POST['dropoff_lng'] !== '' ? (float)$_POST['dropoff_lng'] : 'NULL';
    $booking_type= (($_POST['booking_type'] ?? 'admin') === 'personal') ? 'personal' : 'admin';
    $driver_id   = isset($_POST['driver_id']) && $_POST['driver_id'] !== '' ? (int)$_POST['driver_id'] : 'NULL';
    $vehicle_id  = isset($_POST['vehicle_id']) && $_POST['vehicle_id'] !== '' ? (int)$_POST['vehicle_id'] : 'NULL';
    $notes       = $mysqli->real_escape_string(trim($_POST['notes'] ?? ''));
    $created_by  = 1; // replace with logged-in user ID
    $scheduled_start_at = $_POST['scheduled_start_at'] ?? date('Y-m-d H:i:s');
    $scheduled_end_at   = $_POST['scheduled_end_at'] ?? 'NULL';
    $client_id   = 'NULL'; // optional client ID

    // Build raw SQL string
    $sql = "INSERT INTO bookings
        (booking_type, created_by, client_id, driver_id, vehicle_id, pax, contact_name, contact_phone,
        pickup_point, dropoff_point, pickup_lat, pickup_lng, dropoff_lat, dropoff_lng,
        scheduled_start_at, scheduled_end_at, status, notes)
        VALUES (
            '$booking_type',
            $created_by,
            $client_id,
            $driver_id,
            $vehicle_id,
            $pax,
            '$customer',
            '$phone',
            '$pickup',
            '$dropoff',
            $pickup_lat,
            $pickup_lng,
            $dropoff_lat,
            $dropoff_lng,
            '$scheduled_start_at',
            " . ($scheduled_end_at !== 'NULL' ? "'$scheduled_end_at'" : 'NULL') . ",
            'pending',
            " . ($notes !== '' ? "'$notes'" : 'NULL') . "
        )";

    if ($mysqli->query($sql)) {
        echo json_encode(["success" => true, "message" => "Booking created successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => $mysqli->error]);
    }
}



?>
