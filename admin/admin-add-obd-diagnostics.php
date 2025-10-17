<?php
// diagnostics.php
ini_set('display_errors', 1);
error_reporting(E_ALL);

include('vendor/inc/config.php');
header('Content-Type: application/json');

// --- If called via GET Fetch diagnostics
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    $plate_no = $_GET['plate_no'] ?? '';

    if (empty($plate_no)) {
        echo json_encode(["success" => false, "message" => "Missing plate number"]);
        exit;
    }

    $sql = "SELECT id, plate_no, rpm_status, speed_status, coolant_status, 
                   throttle_status, load_status, voltage_status, overall_status, created_at 
            FROM vehicle_diagnostics
            WHERE plate_no = ?
            ORDER BY id DESC";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("s", $plate_no);
    $stmt->execute();
    $result = $stmt->get_result();

    $rows = [];
    while ($row = $result->fetch_assoc()) {
        $rows[] = $row;
    }

    echo json_encode($rows);
    exit;
}

// --- If called via POST Save new diagnostic
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $data = json_decode(file_get_contents("php://input"), true);

    if (!$data) {
        echo json_encode(["success" => false, "message" => "No data received"]);
        exit;
    }

    $plate_no        = $data['plate_no'] ?? '';
    $rpm_status      = $data['rpm_status'] ?? '';
    $speed_status    = $data['speed_status'] ?? '';
    $coolant_status  = $data['coolant_status'] ?? '';
    $throttle_status = $data['throttle_status'] ?? '';
    $load_status     = $data['load_status'] ?? '';
    $voltage_status  = $data['voltage_status'] ?? '';
    $overall_status  = $data['overall_status'] ?? '';

    // Make sure plate_no is not empty
    if (empty($plate_no)) {
        echo json_encode(["success" => false, "message" => "Plate number missing"]);
        exit;
    }

    $sql = "INSERT INTO vehicle_diagnostics 
            (plate_no, rpm_status, speed_status, coolant_status, throttle_status, load_status, voltage_status, overall_status)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)";

    $stmt = $mysqli->prepare($sql);
    $stmt->bind_param("ssssssss", 
        $plate_no, $rpm_status, $speed_status, $coolant_status, 
        $throttle_status, $load_status, $voltage_status, $overall_status
    );

    if ($stmt->execute()) {
        $jsonFile = "latest_plate.json";
        if (file_exists($jsonFile)) {
            file_put_contents($jsonFile, json_encode(["plate" => ""]));
        }
        echo json_encode(["success" => true, "message" => "Diagnostic saved successfully"]);
    } else {
        echo json_encode(["success" => false, "message" => $stmt->error]);
    }
}
?>
