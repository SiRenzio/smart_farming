<?php
require_once 'db.php';

$result = $conn->query("
    SELECT 
        s.soilSensorID,
        s.sensorName,
        s.sensorStatus,
        sd.locationID
    FROM sensorinfo s
    LEFT JOIN sensordata sd ON s.soilSensorID = sd.soilSensorID
    WHERE s.sensorStatus = 1
    GROUP BY s.soilSensorID
");

$sensors = [];
while ($row = $result->fetch_assoc()) {
    $sensors[] = $row;
}

header('Content-Type: application/json');
echo json_encode($sensors);
?>