<?php
require_once '../db.php';

$result = $conn->query("
    SELECT 
        s.soilSensorID,
        s.sensorName,
        s.sensorStatus,
        d.isConnected,
        s.isRegistered,
        d.locationID,
        fl.farmName
    FROM sensorinfo s
    LEFT JOIN deployment d ON s.soilSensorID = d.soilsensorID
    LEFT JOIN farmlocation fl ON d.locationID = fl.locationID
");

$sensors = [];
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}

header('Content-Type: application/json');
echo json_encode($sensors);
?>