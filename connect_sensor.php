<?php
require_once 'sending.php';

session_start();
require_once 'sending.php';

header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$sensorID   = $data['sensor_id']   ?? null;
$locationID = $data['location_id'] ?? null;
$sensorIP   = $data['sensor_ip']   ?? null;

if (!$sensorID || !$locationID || !$sensorIP) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data'
    ]);
    exit;
}

$response = sendToESP32($sensorIP, $sensorID, $locationID);

if (stripos($response, 'not reachable') !== false) {
    echo json_encode([
        'success' => false,
        'message' => $response
    ]);
    exit;
}

echo json_encode([
    'success' => true,
    'esp_response' => $response
]);
?>