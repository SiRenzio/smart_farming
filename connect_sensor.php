<?php
require_once 'db.php';
// Removed duplicate require_once 'sending.php'

session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$sensorID   = $data['sensor_id']   ?? null;
$locationID = $data['location_id'] ?? null;

if (!$sensorID || !$locationID) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data'
    ]);
    exit;
}

$checkStmt = $conn->prepare("SELECT soilSensorID FROM deployment WHERE soilSensorID = ?");
$checkStmt->bind_param("i", $sensorID);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();

if ($checkResult->num_rows > 0) {
    // Update existing deployment
    $stmt = $conn->prepare("UPDATE deployment SET locationID = ?, isConnected = 1 WHERE soilSensorID = ?");
    $stmt->bind_param("ii", $locationID, $sensorID);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sensor re-deployed successfully.'
        ]);
    }
    $stmt->close();
}
else {
    // Insert new deployment
    $stmt = $conn->prepare("INSERT INTO deployment (soilSensorID, locationID, isConnected) VALUES (?, ?, 1)");
    $stmt->bind_param("ii", $sensorID, $locationID);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sensor deployed successfully.'
        ]);
    }
    $stmt->close();
}

$checkStmt->close();
$conn->close();
?>