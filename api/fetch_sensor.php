<?php
require_once '../db.php';
session_start();

$stmt = $conn->prepare("
    SELECT 
        s.soilSensorID,
        s.sensorName,
        s.sensorStatus,
        d.isConnected,
        s.isRegistered,
        d.locationID,
        fl.farmName,
        u.username
    FROM sensorinfo s
    LEFT JOIN deployment d ON s.soilSensorID = d.soilsensorID
    LEFT JOIN farmlocation fl ON d.locationID = fl.locationID
    LEFT JOIN users u ON u.userID = d.userID
    WHERE s.userID = ? OR s.sensorStatus = 1
");
$stmt->bind_param("i", $_SESSION['userID']);
$stmt->execute();
$result = $stmt->get_result();

$sensors = [];
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}

header('Content-Type: application/json');
echo json_encode($sensors);
?>