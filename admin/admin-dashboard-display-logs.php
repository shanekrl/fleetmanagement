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
    }
}

echo json_encode([
    "status" => "success",
    "live_vehicle_count" => $count
]);
?>