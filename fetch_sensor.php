<?php
require_once 'db.php';

$result = $conn->query("
    SELECT 
        s.soilSensorID,
        s.sensorName,
        s.sensorStatus,
        s.isConnected,
        sd.locationID,
        fl.farmName
    FROM sensorinfo s
    LEFT JOIN sensordata sd ON s.soilSensorID = sd.soilSensorID
    LEFT JOIN farmlocation fl ON sd.locationID = fl.locationID
");

$sensors = [];
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}

header('Content-Type: application/json');
echo json_encode($sensors);
?>