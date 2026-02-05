<?php
session_start();
require_once 'sending.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$sensorIP = $_POST['sensor_ip'] ?? null;
$command  = 'disconnect';

if (!$sensorIP) {
    echo json_encode(['success' => false, 'message' => 'Missing sensor IP']);
    exit;
}

$response = sendToDisconnect($sensorIP, $command);

$stmt = $conn->prepare(
    "UPDATE sensorinfo SET isConnected = 0 WHERE sensorIPAddress = ?"
);
$stmt->bind_param("s", $sensorIP);
$stmt->execute();

echo json_encode([
    'success' => true,
    'response' => $response
]);
?>