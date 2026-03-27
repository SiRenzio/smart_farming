<?php
require_once '../db.php';
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
    $stmt = $conn->prepare("UPDATE deployment SET userID = ?, locationID = ?, isConnected = 1 WHERE soilSensorID = ?");
    $stmt->bind_param("iii", $_SESSION['userID'], $locationID, $sensorID);
    
    if ($stmt->execute()) {
        echo json_encode([
            'success' => true,
            'message' => 'Sensor re-deployed successfully.'
        ]);
    }
    $stmt->close();
}
else {
    // active user nutrition settings
    $nutriStmt = $conn->prepare("
        SELECT nutritionID 
        FROM plantnutrionneed 
        WHERE isActive = 1 AND userID = ? 
        ORDER BY nutritionID DESC LIMIT 1
    ");
    $nutriStmt->bind_param("i", $_SESSION['userID']);
    $nutriStmt->execute();
    $nutriRow = $nutriStmt->get_result()->fetch_assoc();
    $nutriStmt->close();

    if (!$nutriRow) {
        echo json_encode([
            'success' => false,
            'message' => 'Cannot deploy sensor: No active plant nutrition profile found.'
        ]);
        exit; // Stop execution so it doesn't try to insert a blank record
    }

    // Insert new deployment
    $stmt = $conn->prepare("INSERT INTO deployment (userID, soilSensorID, locationID, nutritionID, isConnected) VALUES (?, ?, ?, ?, 1)");
    $stmt->bind_param("iiii", $_SESSION['userID'], $sensorID, $locationID, $nutriRow['nutritionID']);
    
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