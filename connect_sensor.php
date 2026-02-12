<?php
require_once 'db.php';
require_once 'sending.php';

session_start();
require_once 'sending.php';

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

$deployedSensorID = [];
$sensorStmt = $conn->prepare("SELECT soilSensorID FROM deployment");
$sensorStmt->execute(); 
$sensorResult = $sensorStmt->get_result();

while ($row = $sensorResult->fetch_assoc()) {
    $deployedSensorID[] = $row['soilSensorID'];
}

foreach ($deployedSensorID as $deployedID) {
    if ($deployedID == $sensorID) {
        $stmt = $conn->prepare("UPDATE deployment SET locationID = ?, isConnected = 1 WHERE soilSensorID = ?");
        $stmt->bind_param("ii", $locationID, $sensorID);
        $stmt->execute();
        $stmt->close();
        echo json_encode([
            'success' => true,
            'message' => 'Sensor re-deployed successfully.'
        ]);
        exit;
    }
    else {
        $stmt = $conn->prepare("INSERT INTO deployment (soilSensorID, locationID, isConnected) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $sensorID, $locationID);
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'message' => 'Sensor deployed successfully.'
        ]);
        exit;
    }
}
?>