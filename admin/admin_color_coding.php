<?php
// diagnostics.php
// ini_set('display_errors', 1);
// error_reporting(E_ALL);

include('vendor/inc/config.php');
header('Content-Type: application/json');


// Get plate_no from GET
$plate_no = $_GET['plate_no'] ?? '';
    
// --- Get vehicle status ---
$statusSql = "SELECT v_status FROM tms_vehicle WHERE v_reg_no = ?";
$stmtStatus = $mysqli->prepare($statusSql);
$stmtStatus->bind_param("s", $plate_no);
$stmtStatus->execute();
$resultStatus = $stmtStatus->get_result();

$vehicleColor = "#000000"; // default color if status not found
$v_status = "Unknown";

if ($rowStatus = $resultStatus->fetch_assoc()) {
    $v_status = $rowStatus['v_status'];

    // Map status to colors
    switch (strtolower($v_status)) {
        case 'maintenance':
            $vehicleColor = "#ed1c24"; // red
            break;
        case 'available':
            $vehicleColor = "#007BFF";
            break;
        case 'idle':
            $vehicleColor = "#28A745";
            break;
        case 'in_progress':
        case 'in progress':
            $vehicleColor = "#FFC107";
            break;
        default:
            $vehicleColor = "#FAFAFA";
            break;
    }
}

$stmtStatus->close();

// Return as JSON (example)
echo json_encode([
    "success" => true,
    "plate_no" => $plate_no,
    "v_status" => $v_status,
    "color" => $vehicleColor
]);



?>
