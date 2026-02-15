<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$sensorID = $_POST['sensor_id'] ?? null;
$command  = 'disconnect';

if (!$sensorID) {
    echo json_encode(['success' => false, 'message' => 'Missing sensor ID']);
    exit;
}

$response = sendToDisconnect($sensorID, $command);

$stmt = $conn->prepare(
    "UPDATE deployment SET isConnected = 0 WHERE soilSensorID = ?"
);
$stmt->bind_param("s", $sensorID);
$stmt->execute();

echo json_encode([
    'success' => true,
    'message' => 'Sensor disconnected successfully.'
]);
exit;

function sendToDisconnect($sensorID, $command) {
    if (empty($sensorIP)) {
        return "No IP address defined";
    }

    $esp32_url = "http://{$sensorIP}/receive";

    $data = [
        "command" => $command
    ];

    $options = [
        'http' => [
            'header'  => "Content-Type: application/json\r\n",
            'method'  => 'POST',
            'content' => json_encode($data),
            'timeout' => 5
        ]
    ];

    $context = stream_context_create($options);
    return @file_get_contents($esp32_url, false, $context) ?: "ESP32 not reachable";
}

?>