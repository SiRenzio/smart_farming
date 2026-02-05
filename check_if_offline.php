<?php
require_once 'db.php';
date_default_timezone_set('Asia/Manila');

function checkIF_Offline($conn) {

    $currentTime = time();

    $checkSql = "SELECT soilSensorID, sensorMacAddress, last_sensor_online 
                 FROM sensorinfo 
                 WHERE sensorStatus = 1";

    $result = $conn->query($checkSql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $soilSensorID = $row['soilSensorID'];
            $macAddress   = $row['sensorMacAddress'];
            $lastOnline   = $row['last_sensor_online'];

            // Calculate time difference
            $lastOnlineTime = strtotime($lastOnline);
            $timeDiffSeconds = $currentTime - $lastOnlineTime;

            // If inactive for more than 30 seconds, update status
            if ($timeDiffSeconds > 5) {
                $updateSql = "UPDATE sensorinfo SET sensorStatus = 0 WHERE soilSensorID = ?";
                $stmt = $conn->prepare($updateSql);
                
                $stmt->bind_param("i", $soilSensorID);
                
                if ($stmt->execute()) {
                    echo "[" . date('Y-m-d H:i:s') . "] Sensor ID {$soilSensorID} (MAC: {$macAddress}) set to Offline.\n";
                }
                $stmt->close();
            }
        }
    }
}