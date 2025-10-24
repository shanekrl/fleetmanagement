<?php
// diagnostics.php
ini_set('display_errors', 1);
error_reporting(E_ALL);
include('vendor/inc/config.php');
header('Content-Type: application/json');

$file = 'live_vehicles.json';
$count = 0;

if (file_exists($file)) {
    $data = json_decode(file_get_contents($file), true);
    if (is_array($data)) {
        $count = count($data);
        $plates = array_keys($data);

        if (!empty($plates)) {
            $escapedPlates = array_map([$mysqli, 'real_escape_string'], $plates);
            $plateList = "'" . implode("','", $escapedPlates) . "'";

            // 1 Vehicles in JSON → Booked
            $mysqli->query("UPDATE tms_vehicle SET v_status = 'Booked' WHERE v_reg_no IN ($plateList)");

            // 2 Vehicles NOT in JSON → Available
            $mysqli->query("UPDATE tms_vehicle SET v_status = 'Available' WHERE v_reg_no NOT IN ($plateList)");
        } else {
            // If no vehicles in JSON, mark all as Available
            $mysqli->query("UPDATE tms_vehicle SET v_status = 'Available'");
        }
    }
}

echo json_encode([
    "status" => "success",
    "live_vehicle_count" => $count
]);
?>