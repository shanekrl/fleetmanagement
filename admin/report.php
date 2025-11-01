<?php
include('vendor/inc/config.php');
header('Content-Type: application/json');

$plate_no  = isset($_POST['plate_no']) ? (int)$_POST['plate_no'] : 0;
$from_date = $_POST['from_date'] ?? '';
$to_date   = $_POST['to_date'] ?? '';
$plate_no_text = $_POST['plate_no_text'] ?? '';

if (empty($plate_no) || empty($from_date) || empty($to_date)) {
    echo json_encode(['error' => 'Missing parameters']);
    exit;
}


$totalTripsQuery = "
    SELECT COUNT(*) AS total_trips
    FROM v_trip_history AS trips
    LEFT JOIN tms_vehicle ON trips.vehicle_id = tms_vehicle.v_id
    WHERE tms_vehicle.v_id = ?
      AND DATE(trips.created_at) BETWEEN ? AND ?
";

$total_trips = 0;

if ($stmt = $mysqli->prepare($totalTripsQuery)) {
    $stmt->bind_param("iss", $plate_no, $from_date, $to_date);
    $stmt->execute();
    $result = $stmt->get_result()->fetch_assoc();
    $total_trips = $result['total_trips'] ?? 0;
    $stmt->close();
}


$obd_list = [];
$obdQuery = "
    SELECT 
        plate_no,
        created_at,
        rpm,
        speed,
        throttle,
        coolant_temp,
        fuel_type
    FROM obd_logs
    WHERE plate_no = ?
      AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at ASC
";

if ($stmt2 = $mysqli->prepare($obdQuery)) {
    $stmt2->bind_param("iss", $plate_no_text, $from_date, $to_date);
    $stmt2->execute();
    $res = $stmt2->get_result();
    while ($row = $res->fetch_assoc()) {
        $obd_list[] = $row;
    }
    $stmt2->close();
}


echo json_encode([
    'total_trips' => $total_trips,
    'obd_list' => $obd_list
]);
exit;
?>
