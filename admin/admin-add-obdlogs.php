<?php
// =======================
// Enable error reporting
// =======================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('vendor/inc/config.php'); // your DB connection

header('Content-Type: application/json');

// Get JSON input
$json = file_get_contents("php://input");
$data = json_decode($json, true);
$params_plate_no = $_GET['plate_no'] ?? null;

// Pampanga (Balibago, Angeles City) approximate bounding box
$minLat = 15.1340;
$maxLat = 15.1600;
$minLon = 120.5660;
$maxLon = 120.6000;

// Generate random latitude & longitude in that box
$randLat = $minLat + mt_rand() / mt_getrandmax() * ($maxLat - $minLat);
$randLon = $minLon + mt_rand() / mt_getrandmax() * ($maxLon - $minLon);
// Random speed between 20 and 120 km/h
$randSpeed = mt_rand(20, 120);
// static test data if none sent
if (!$data) {
    $data = [
        "engine_performance" => [
            "speed" => $randSpeed,
            "rpm" => "2500",
            "load" => "70",
            "throttle" => "35",
            "intake_manifold" => "10",
            "maf" => "15"
        ],
        "temperatures" => [
            "coolant_temp" => "90",
            "intake_air_temp" => "30",
            "ambient_temp" => "28",
            "oil_temp" => "95"
        ],
        "air_fuel" => [
            "fuel_level" => "50",
            "fuel_type" => "Gasoline"
        ],
        "basic_info" => [
            "plate_no" => "123456"
        ],
        "location" => [
            "longitude" => $randLon,
            "latitude" => $randLat
        ]
    ];
}

if ($data) {
    $speed            = $data["engine_performance"]["speed"] ?? "";
    $rpm              = $data["engine_performance"]["rpm"] ?? "";
    $engine_load      = $data["engine_performance"]["load"] ?? "";
    $throttle         = $data["engine_performance"]["throttle"] ?? "";
    $intake_manifold  = $data["engine_performance"]["intake_manifold"] ?? "";
    $maf              = $data["engine_performance"]["maf"] ?? "";
    $coolant_temp     = $data["temperatures"]["coolant_temp"] ?? "";
    $intake_air_temp  = $data["temperatures"]["intake_air_temp"] ?? "";
    $fuel_level       = $data["air_fuel"]["fuel_level"] ?? "";
    $fuel_type        = $data["air_fuel"]["fuel_type"] ?? "";
    $ambient_temp     = $data["temperatures"]["ambient_temp"] ?? "";
    $oil_temp         = $data["temperatures"]["oil_temp"] ?? "";
    $plate_no         = $data["basic_info"]["plate_no"]  ?? "";
    $latitude         = $data["location"]["latitude"]  ?? "";
    $longitude         = $data["location"]["longitude"]  ?? "";

    $query = "INSERT INTO obd_logs 
        (speed, rpm, engine_load, throttle, intake_manifold, maf, coolant_temp, intake_air_temp, fuel_level, fuel_type, ambient_temp, oil_temp,plate_no,latitude,longitude) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param(
        "sssssssssssssss", 
        $speed, $rpm, $engine_load, $throttle, $intake_manifold, $maf,
        $coolant_temp, $intake_air_temp, $fuel_level, $fuel_type, $ambient_temp, $oil_temp,$plate_no,$latitude,$longitude
    );

  if ($stmt->execute()) {
        $stmt->close();
        $sel = $mysqli->prepare("SELECT * FROM obd_logs WHERE plate_no = ? ORDER BY id DESC LIMIT 1");
        $sel->bind_param("s", $params_plate_no);
        $sel->execute();
        $result = $sel->get_result();

        $logs = [];
        while ($row = $result->fetch_assoc()) {
            $logs[] = $row;
        }
        $sel->close();

        echo json_encode([
            "status" => "success",
            "message" => "OBD Log Saved",
            "plate_no" => $plate_no,
            "logs" => $logs
        ]);
    } else {
        echo json_encode(["status"=>"error","message"=>$stmt->error]);
        $stmt->close();
    }


} else {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
}

$mysqli->close();
?>
