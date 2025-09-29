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
$inputData = json_decode($json, true);

// Get plate_no from GET parameter
$params_plate_no = $_GET['plate_no'] ?? null;

// Always use real plate_no + GPS if provided, but randomize OBD data
$plate     = $inputData["basic_info"]["plate_no"] ?? "";
$latitude  = $inputData["location"]["latitude"] ?? "";
$longitude = $inputData["location"]["longitude"] ?? "";

// Always randomize OBD + sensor data
$data = [
    "basic_info" => [
        "plate_no" => $plate
    ],
    "engine_performance" => [
        "speed"           => rand(0, 80),
        "rpm"             => rand(700, 5000),
        "load"            => rand(20, 100),
        "throttle"        => rand(0, 100),
        "intake_manifold" => rand(10, 100),
        "maf"             => rand(2, 50)
    ],
    "temperatures" => [
        "coolant_temp"    => rand(70, 110),
        "intake_air_temp" => rand(20, 60),
        "ambient_temp"    => rand(15, 40),
        "oil_temp"        => rand(60, 120)
    ],
    "air_fuel" => [
        "fuel_level"      => rand(10, 100),
        "fuel_type"       => "Gasoline"
    ],
    "location" => [
        "latitude"  => $latitude,   // real GPS
        "longitude" => $longitude   // real GPS
    ]
];

// Insert into DB if plate_no exists
if (!empty($plate)) {
    $speed           = $data["engine_performance"]["speed"];
    $rpm             = $data["engine_performance"]["rpm"];
    $engine_load     = $data["engine_performance"]["load"];
    $throttle        = $data["engine_performance"]["throttle"];
    $intake_manifold = $data["engine_performance"]["intake_manifold"];
    $maf             = $data["engine_performance"]["maf"];

    $coolant_temp    = $data["temperatures"]["coolant_temp"];
    $intake_air_temp = $data["temperatures"]["intake_air_temp"];
    $ambient_temp    = $data["temperatures"]["ambient_temp"];
    $oil_temp        = $data["temperatures"]["oil_temp"];

    $fuel_level      = $data["air_fuel"]["fuel_level"];
    $fuel_type       = $data["air_fuel"]["fuel_type"];

    $logs_text       = json_encode($data, JSON_UNESCAPED_UNICODE);

    $query = "INSERT INTO obd_logs 
        (speed, rpm, engine_load, throttle, intake_manifold, maf, coolant_temp, intake_air_temp, fuel_level, fuel_type, ambient_temp, oil_temp, plate_no, latitude, longitude, logs_text) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param(
        "ssssssssssssssss", 
        $speed, $rpm, $engine_load, $throttle, $intake_manifold, $maf,
        $coolant_temp, $intake_air_temp, $fuel_level, $fuel_type, $ambient_temp, $oil_temp,
        $plate, $latitude, $longitude, $logs_text
    );
    
    if ($stmt->execute()) {
        echo json_encode([
            "status" => "insert_success",
            "plate_no" => $plate,
            "latitude" => $latitude,
            "longitude" => $longitude,
            "inserted_data" => $data
        ]);
    } else {
        echo json_encode([
            "status" => "insert_failed",
            "error"  => $stmt->error
        ]);
    }
    
    $stmt->close();
}

// If plate_no is provided, return latest log
if ($params_plate_no) {
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
        "status"   => "success",
        "plate_no" => $params_plate_no,
        "logs"     => $logs
        
    ]);
} else { 
    // No plate provided -> return all cars (latest per car)
    $sql = "
        SELECT t.*
        FROM obd_logs t
        INNER JOIN (
            SELECT plate_no, MAX(id) AS max_id
            FROM obd_logs
            GROUP BY plate_no
        ) x ON t.plate_no = x.plate_no AND t.id = x.max_id
        ORDER BY t.plate_no ASC
    ";
    $result = $mysqli->query($sql);

    $logs = [];
    while ($row = $result->fetch_assoc()) {
        $logs[] = $row;
    }

    echo json_encode([
        "status" => "success",
        "logs"   => $logs,
    ]);
}

$mysqli->close();
?>
