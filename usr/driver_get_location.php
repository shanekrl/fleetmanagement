<?php
session_start();
require_once __DIR__ . '/../admin/vendor/inc/config.php';
require_once __DIR__ . '/../admin/vendor/inc/checklogin.php';

if (!isset($_GET['location']) || empty($_GET['location'])) {
    echo json_encode(['error' => 'No location provided']);
    exit;
}

$location = urlencode($_GET['location']);
$url = "https://nominatim.openstreetmap.org/search?format=json&q={$location}";

// Create stream context with User-Agent (required by Nominatim)
$opts = [
    "http" => [
        "header" => "User-Agent: FleetSystem/1.0 (your_email@example.com)\r\n"
    ]
];
$context = stream_context_create($opts);

$response = @file_get_contents($url, false, $context);

if ($response === FALSE) {
    echo json_encode(['error' => 'Failed to connect to geocoding service']);
    exit;
}

$data = json_decode($response, true);

// Return the first result (if available)
if (!empty($data)) {
    echo json_encode([
        'lat' => $data[0]['lat'],
        'lon' => $data[0]['lon'],
        'display_name' => $data[0]['display_name']
    ]);
} else {
    echo json_encode(['error' => 'No results found']);
}
