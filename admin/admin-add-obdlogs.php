<?php
include('vendor/inc/config.php');
header('Content-Type: application/json');
$json = file_get_contents("php://input");
$data = json_decode($json, true);

if ($data) {
    $speed          = $data["engine_performance"]["speed"] ?? "";
    $rpm            = $data["engine_performance"]["rpm"] ?? "";
    $engine_load    = $data["engine_performance"]["engine_load"] ?? "";
    $throttle       = $data["engine_performance"]["throttle"] ?? "";
    $intake_manifold= $data["engine_performance"]["intake_manifold"] ?? "";
    $maf            = $data["engine_performance"]["maf"] ?? "";
    $coolant_temp   = $data["temperatures"]["coolant_temp"] ?? "";
    $intake_air_temp= $data["temperatures"]["intake_air_temp"] ?? "";
    $fuel_level     = $data["fuel"]["fuel_level"] ?? "";
    $fuel_type      = $data["fuel"]["fuel_type"] ?? "";
    $ambiant_temp   = $data["temperatures"]["ambient_temp"] ?? "";
    $oil_temp       = $data["temperatures"]["oil_temp"] ?? "";

    $query = "INSERT INTO obd_logs 
        (speed, rpm, engine_load, throttle, intake_manifold, maf, coolant_temp, intake_air_temp, fuel_level, fuel_type, ambiant_temp, oil_temp) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt  = $mysqli->prepare($query);
    $stmt->bind_param(
        "ssssssssssss", 
        $speed, $rpm, $engine_load, $throttle, $intake_manifold, $maf,
        $coolant_temp, $intake_air_temp, $fuel_level, $fuel_type, $ambiant_temp, $oil_temp
    );
    $stmt->execute();

    if ($stmt->affected_rows > 0) {
        echo json_encode(["status" => "success", "message" => "OBD Log Saved"]);
    } else {
        echo json_encode(["status" => "error", "message" => "Insert Failed"]);
    }

    $stmt->close();
} else {
    echo json_encode(["status" => "error", "message" => "Invalid JSON"]);
}

$mysqli->close();
?>
