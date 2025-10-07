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
$inputData = json_decode($json, true);

// GET parameters for fetching data
$params_plate_no = $_GET['plate_no'] ?? null;
$params_date = $_GET['date'] ?? null;

// Extract fields from incoming JSON
$plate       = $inputData["basic_info"]["plate_no"] ?? "";
$latitude    = $inputData["location"]["latitude"] ?? "";
$longitude   = $inputData["location"]["longitude"] ?? "";

// Engine performance
$rpm         = $inputData["engine_performance"]["rpm"] ?? "";
$speed       = $inputData["engine_performance"]["speed"] ?? "";
$load        = $inputData["engine_performance"]["load"] ?? "";
$throttle    = $inputData["engine_performance"]["throttle"] ?? "";

// Temperatures
$coolant_temp     = $inputData["temperatures"]["coolant_temp"] ?? "";
$intake_air_temp  = $inputData["temperatures"]["intake_air_temp"] ?? "";
$ambient_temp     = $inputData["temperatures"]["ambient_temp"] ?? "";
$oil_temp         = $inputData["temperatures"]["oil_temp"] ?? "";

// Air/Fuel
$map          = $inputData["air_fuel"]["map"] ?? "";
$maf          = $inputData["air_fuel"]["maf"] ?? "";
$fuel_level   = $inputData["air_fuel"]["fuel_level"] ?? "";
$fuel_type    = $inputData["air_fuel"]["fuel_type"] ?? "Gasoline";

// Extra sensor data
$fuel_pressure      = $inputData["extra"]["fuel_pressure"] ?? "";
$fuel_rate          = $inputData["extra"]["fuel_rate"] ?? "";
$battery_voltage    = $inputData["extra"]["battery_voltage"] ?? "";
$odometer           = $inputData["extra"]["odometer"] ?? "";
$mil_status         = $inputData["extra"]["mil_status"] ?? "";
$timing_advance     = $inputData["extra"]["timing_advance"] ?? "";
$stft               = $inputData["extra"]["stft"] ?? "";
$ltft               = $inputData["extra"]["ltft"] ?? "";
$fuel_rail_pressure = $inputData["extra"]["fuel_rail_pressure"] ?? "";
$atf_temp           = $inputData["extra"]["atf_temp"] ?? "";
$distance_mil       = $inputData["extra"]["distance_mil"] ?? "";
$distance_clear     = $inputData["extra"]["distance_clear"] ?? "";
$run_time           = $inputData["extra"]["run_time"] ?? "";

// ==========================
// FETCH MOVEMENTS BY DATE
// ==========================
if (!empty($params_date)) {
    if ($params_plate_no && $params_date) {
        $stmt = $mysqli->prepare("
            SELECT * 
            FROM obd_logs 
            WHERE plate_no = ? 
              AND DATE(created_at) = ?
            ORDER BY created_at ASC
        ");
        $stmt->bind_param("ss", $params_plate_no, $params_date);
        $stmt->execute();
        $result = $stmt->get_result();
        $logs = [];
        while ($row = $result->fetch_assoc()) $logs[] = $row;
        $stmt->close();

        echo json_encode([
            "status" => "success",
            "plate_no" => $params_plate_no,
            "date" => $params_date,
            "logs" => $logs
        ]);
        exit;
    } else {
        echo json_encode(["status" => "error", "message" => "plate_no and date are required"]);
        exit;
    }
}

// ==========================
// BUILD DATA OBJECT
// ==========================
$data = [
    "basic_info" => [
        "plate_no" => $plate
    ],
    "engine_performance" => [
        "rpm"      => $rpm,
        "speed"    => $speed,
        "load"     => $load,
        "throttle" => $throttle
    ],
    "temperatures" => [
        "coolant_temp"    => $coolant_temp,
        "intake_air_temp" => $intake_air_temp,
        "ambient_temp"    => $ambient_temp,
        "oil_temp"        => $oil_temp
    ],
    "air_fuel" => [
        "map"         => $map,
        "maf"         => $maf,
        "fuel_level"  => $fuel_level,
        "fuel_type"   => $fuel_type
    ],
    "location" => [
        "latitude"  => $latitude,
        "longitude" => $longitude
    ],
    "extra" => [
        "fuel_pressure"      => $fuel_pressure,
        "fuel_rate"          => $fuel_rate,
        "battery_voltage"    => $battery_voltage,
        "odometer"           => $odometer,
        "mil_status"         => $mil_status,
        "timing_advance"     => $timing_advance,
        "stft"               => $stft,
        "ltft"               => $ltft,
        "fuel_rail_pressure" => $fuel_rail_pressure,
        "atf_temp"           => $atf_temp,
        "distance_mil"       => $distance_mil,
        "distance_clear"     => $distance_clear,
        "run_time"           => $run_time
    ]
];

// ==========================
// INSERT TO DATABASE
// ==========================
if (!empty($plate)) {
    $logs_text = json_encode($data, JSON_UNESCAPED_UNICODE);

    $query = "INSERT INTO obd_logs 
        (speed, rpm, engine_load, throttle, coolant_temp, intake_air_temp, ambient_temp, oil_temp, 
         map, maf, fuel_level, fuel_type, plate_no, latitude, longitude, 
         fuel_pressure, fuel_rate, battery_voltage, odometer, mil_status, timing_advance, stft, ltft, 
         fuel_rail_pressure, atf_temp, distance_mil, distance_clear, run_time, logs_text) 
        VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)";

    $stmt = $mysqli->prepare($query);
    $stmt->bind_param(
        "sssssssssssssssssssssssssssss",
        $speed, $rpm, $load, $throttle, $coolant_temp, $intake_air_temp, $ambient_temp, $oil_temp,
        $map, $maf, $fuel_level, $fuel_type, $plate, $latitude, $longitude,
        $fuel_pressure, $fuel_rate, $battery_voltage, $odometer, $mil_status, $timing_advance, $stft, $ltft,
        $fuel_rail_pressure, $atf_temp, $distance_mil, $distance_clear, $run_time, $logs_text
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

// ==========================
// FETCH LATEST LOG / TRIPS
// ==========================
if ($params_plate_no) {
    $sel = $mysqli->prepare("SELECT * FROM obd_logs WHERE plate_no = ? ORDER BY id DESC LIMIT 1");
    $sel->bind_param("s", $params_plate_no);
    $sel->execute();
    $result = $sel->get_result();
    $logs = [];
    while ($row = $result->fetch_assoc()) $logs[] = $row;
    $sel->close();

    $tripSel = $mysqli->prepare("
        SELECT 
            vehicles.v_reg_no AS plate_no,
            CONCAT(bookings.pickup_point, ' - ', bookings.dropoff_point) AS start_end_location,
            CONCAT(driver.u_fname, ' ', driver.u_lname) AS driver_name,
            bookings.scheduled_start_at,
            bookings.pickup_point AS start_location,
            bookings.dropoff_point AS destination
        FROM bookings 
        LEFT JOIN tms_vehicle AS vehicles 
            ON bookings.vehicle_id = vehicles.v_id
        LEFT JOIN tms_user_add_driver AS driver 
            ON vehicles.default_driver_id = driver.d_u_id
        WHERE vehicles.v_reg_no = ?
        ORDER BY bookings.scheduled_start_at DESC
        LIMIT 2
    ");
    $tripSel->bind_param("s", $params_plate_no);
    $tripSel->execute();
    $tripResult = $tripSel->get_result();
    $trips_data = [];
    while ($row = $tripResult->fetch_assoc()) $trips_data[] = $row;
    $tripSel->close();

    echo json_encode([
        "status"     => "success",
        "plate_no"   => $params_plate_no,
        "logs"       => $logs,
        "trips_data" => $trips_data
    ]);
} else {
    $sql = "
        SELECT t.* FROM obd_logs t
        INNER JOIN (
            SELECT plate_no, MAX(id) AS max_id
            FROM obd_logs
            GROUP BY plate_no
        ) x ON t.plate_no = x.plate_no AND t.id = x.max_id
        ORDER BY t.plate_no ASC
    ";
    $result = $mysqli->query($sql);
    $logs = [];
    while ($row = $result->fetch_assoc()) $logs[] = $row;

    echo json_encode(["status" => "success", "logs" => $logs]);
}
?>
