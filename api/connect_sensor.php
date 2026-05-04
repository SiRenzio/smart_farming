<?php
require_once '../db.php';
session_start();
header('Content-Type: application/json');

$data = json_decode(file_get_contents('php://input'), true);

$sensorID   = $data['sensor_id']   ?? null;
$locationID = $data['location_id'] ?? null;
$userID     = $_SESSION['userID']  ?? null; 

if (!$sensorID || !$locationID || !$userID) {
    echo json_encode([
        'success' => false,
        'message' => 'Invalid data or missing user session'
    ]);
    exit;
}

// check if user has already a primary sensor deployed
$primaryStmt = $conn->prepare("SELECT soilSensorID FROM deployment WHERE userID = ? AND isPrimary = 1");
$primaryStmt->bind_param("i", $userID);
$primaryStmt->execute();
$hasPrimary = $primaryStmt->get_result()->num_rows > 0;
$primaryStmt->close();

// check if sensor is already deployed
$checkStmt = $conn->prepare("SELECT soilSensorID, isPrimary FROM deployment WHERE soilSensorID = ?");
$checkStmt->bind_param("i", $sensorID);
$checkStmt->execute();
$checkResult = $checkStmt->get_result();
$existingSensor = $checkResult->fetch_assoc();
$checkStmt->close();

if ($existingSensor) {
    // re-deploying an existing sensor
    $setPrimary = (!$hasPrimary) ? 1 : $existingSensor['isPrimary'];

    $stmt = $conn->prepare("UPDATE deployment SET userID = ?, locationID = ?, isConnected = 1, isPrimary = ? WHERE soilSensorID = ?");
    $stmt->bind_param("iiii", $userID, $locationID, $setPrimary, $sensorID);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sensor re-deployed successfully.'
        ]);
    }
    $stmt->close();
} 
else {
    // deploying a new sensor
    $nutriStmt = $conn->prepare("
        SELECT nutritionID 
        FROM plantnutrionneed 
        WHERE isActive = 1 AND userID = ? 
        ORDER BY nutritionID DESC LIMIT 1
    ");
    $nutriStmt->bind_param("i", $userID);
    $nutriStmt->execute();
    $nutriRow = $nutriStmt->get_result()->fetch_assoc();
    $nutriStmt->close();

    if (!$nutriRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot deploy sensor: No active plant nutrition profile found.'
        ]);
        exit;
    }

    // Set as primary if no primary exists yet
    $isPrimaryFlag = $hasPrimary ? 0 : 1;
    $stmt = $conn->prepare("INSERT INTO deployment (userID, soilSensorID, locationID, nutritionID, isConnected, isPrimary) VALUES (?, ?, ?, ?, 1, ?)");
    $stmt->bind_param("iiiii", $userID, $sensorID, $locationID, $nutriRow['nutritionID'], $isPrimaryFlag);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sensor deployed successfully.'
        ]);
    }
    $stmt->close();
}

$conn->close();
?>