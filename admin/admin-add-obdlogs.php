<?php
// =======================
// Enable error reporting
// =======================
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

include('vendor/inc/config.php'); // your DB connection

header('Content-Type: application/json');

// Get JSON input if any
$json = file_get_contents("php://input");
$data = json_decode($json, true);

// Get plate_no from GET parameter
$plate_no = $_GET['plate_no'] ?? null;

// If JSON exists, insert into database
if ($data && isset($data["basic_info"]["plate_no"])) {

    $speed           = $data["engine_performance"]["speed"] ?? "";
    $rpm             = $data["engine_performance"]["rpm"] ?? "";
    $engine_load     = $data["engine_performance"]["load"] ?? "";
    $throttle        = $data["engine_performance"]["throttle"] ?? "";
    $intake_manifold = $data["engine_performance"]["intake_manifold"] ?? "";
    $maf             = $data["engine_performance"]["maf"] ?? "";

    $coolant_temp    = $data["temperatures"]["coolant_temp"] ?? "";
    $intake_air_temp = $data["temperatures"]["intake_air_temp"] ?? "";
    $ambient_temp    = $data["temperatures"]["ambient_temp"] ?? "";
    $oil_temp        = $data["temperatures"]["oil_temp"] ?? "";

    $fuel_level      = $data["air_fuel"]["fuel_level"] ?? "";
    $fuel_type       = $data["air_fuel"]["fuel_type"] ?? "";

    $plate_no        = $data["basic_info"]["plate_no"] ?? "";
    $latitude        = $data["location"]["latitude"] ?? "";
    $longitude       = $data["location"]["longitude"] ?? "";

    $query = "INSERT INTO obd_logs 
        (speed, rpm, engine_load, throttle, intake_manifold, maf, coolant_temp, intake_air_temp, fuel_level, fuel_type, ambient_temp, oil_temp, plate_no, latitude, longitude) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param(
        "sssssssssssssss", 
        $speed, $rpm, $engine_load, $throttle, $intake_manifold, $maf,
        $coolant_temp, $intake_air_temp, $fuel_level, $fuel_type, $ambient_temp, $oil_temp,
        $plate_no, $latitude, $longitude
    );
    $stmt->execute();
    $stmt->close();
}

// If plate_no is provided, return latest data
if ($plate_no) {
    $sel = $mysqli->prepare("SELECT * FROM obd_logs WHERE plate_no = ? ORDER BY id DESC LIMIT 1");
    $sel->bind_param("s", $plate_no);
    $sel->execute();
    $result = $sel->get_result();

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }
    $sel->close();

    echo json_encode([
        "status" => "success",
        "plate_no" => $plate_no,
        "logs" => $logs
    ]);
} else {
    echo json_encode([
        "status" => "error",
        "message" => "No plate_no provided"
    ]);
}

$mysqli->close();
?>