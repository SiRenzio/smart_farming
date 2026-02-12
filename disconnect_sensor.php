<?php
session_start();
require_once 'db.php';
require_once 'sending.php';

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
?>