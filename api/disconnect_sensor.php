<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$sensorID = $_POST['sensor_id'] ?? null;
$userID   = $_SESSION['userID'] ?? null; // Added user base check
$command  = 'disconnect';

if (!$sensorID || !$userID) {
    echo json_encode(['success' => false, 'message' => 'Missing sensor ID or User Session']);
    exit;
}

// Check the current status of the sensor being disconnected
$stateStmt = $conn->prepare("SELECT isPrimary FROM deployment WHERE soilSensorID = ? AND userID = ?");
$stateStmt->bind_param("ii", $sensorID, $userID);
$stateStmt->execute();
$stateResult = $stateStmt->get_result();

if ($stateResult->num_rows === 0) {
    echo json_encode(['success' => false, 'message' => 'Sensor not found in your deployment']);
    exit;
}

$sensorRow = $stateResult->fetch_assoc();
$wasPrimary = ($sensorRow['isPrimary'] == 1);
$stateStmt->close();

$response = sendToDisconnect($sensorID, $command);

// transfer primary status
if ($wasPrimary) {
    // Check if the user has another connected sensor available
    $nextStmt = $conn->prepare("
        SELECT soilSensorID 
        FROM deployment 
        WHERE userID = ? AND isConnected = 1 AND soilSensorID != ? 
        ORDER BY deploymentID ASC
    ");
    $nextStmt->bind_param("ii", $userID, $sensorID);
    $nextStmt->execute();
    $nextResult = $nextStmt->get_result();
    
    if ($nextResult->num_rows > 0) {
        // Found another connected sensor, transfer the primary flag
        $nextSensor = $nextResult->fetch_assoc();
        $newPrimaryID = $nextSensor['soilSensorID'];
        
        $promoteStmt = $conn->prepare("UPDATE deployment SET isPrimary = 1 WHERE soilSensorID = ?");
        $promoteStmt->bind_param("i", $newPrimaryID);
        $promoteStmt->execute();
        $promoteStmt->close();

        $jobPath = __DIR__ . "/../moisture_jobs/job_$newPrimaryID.json";

        if(file_exists($jobPath)){
            unlink($jobPath);
        }

        file_put_contents($jobPath, json_encode([
            "soilSensorID" => $newPrimaryID,
            "startTime" => time(),
            "userID" => $userID,
            "triggeredBy" => "primary_switch"
        ]));
    }
    $nextStmt->close();
}

// disconnect the sensor
$disconnectStmt = $conn->prepare("UPDATE deployment SET isConnected = 0, isPrimary = 0 WHERE soilSensorID = ?");
$disconnectStmt->bind_param("i", $sensorID);
$disconnectStmt->execute();
$disconnectStmt->close();

echo json_encode([
    'success' => true,
    'message' => 'Sensor disconnected successfully.',
    'esp32_response' => $response
]);
exit;


function sendToDisconnect($sensorID, $command) {
    $sensorIP = ""; 

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