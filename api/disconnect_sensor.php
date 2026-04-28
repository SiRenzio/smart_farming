<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    echo json_encode(['success' => false, 'message' => 'Invalid request']);
    exit;
}

$sensorID = $_POST['sensor_id'] ?? null;
$locationID = $_POST['location_id'] ?? null;
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

$sNameQuery = $conn->prepare("SELECT sensorName FROM sensorinfo WHERE soilSensorID = ?");
$sNameQuery->bind_param("i", $sensorID);
$sNameQuery->execute();
$sensorRow = $sNameQuery->get_result()->fetch_assoc();
$sensorName = $sensorRow ? $sensorRow['sensorName'] : "Unknown Sensor";
$sNameQuery->close();

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
        WHERE userID = ? AND isConnected = 1 AND soilSensorID != ? AND locationID = ?
        ORDER BY deploymentID ASC
    ");
    $nextStmt->bind_param("iii", $userID, $sensorID, $locationID);
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

        // --- NEW: Fetch the NEW sensor's name for the notification ---
        $newNameQuery = $conn->prepare("SELECT sensorName FROM sensorinfo WHERE soilSensorID = ?");
        $newNameQuery->bind_param("i", $newPrimaryID);
        $newNameQuery->execute();
        $newNameRow = $newNameQuery->get_result()->fetch_assoc();
        $newSensorName = $newNameRow ? $newNameRow['sensorName'] : "Another sensor";
        $newNameQuery->close();

        // --- NEW: Insert the notification ---
        $notifMessage = "$sensorName disconnected. $newSensorName is now the primary sensor.";
        $notifStmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $notifStmt->bind_param("s", $notifMessage);
        $notifStmt->execute();
        $notifStmt->close();

        $jobPath = __DIR__ . "/../moisture_jobs/job_$newPrimaryID.json";

        if(file_exists($jobPath)){
            unlink($jobPath);
        }

        file_put_contents($jobPath, json_encode([
            "soilSensorID" => $newPrimaryID,
            "startTime" => time(),
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