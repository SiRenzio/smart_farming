<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

// Function to check and update offline sensors
function checkIF_Offline($conn) {
    $currentTime = time();

    $checkSql = "SELECT soilSensorID, sensorMacAddress, last_sensor_online, sensorStatus, isRegistered 
                 FROM sensorinfo";

    $result = $conn->query($checkSql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $soilSensorID = $row['soilSensorID'];
            $macAddress   = $row['sensorMacAddress'];
            $lastOnline   = $row['last_sensor_online'];
            $status       = (int)$row['sensorStatus'];
            $isRegistered = (int)$row['isRegistered'];

            // Calculate time difference
            $lastOnlineTime = strtotime($lastOnline);
            $timeDiffSeconds = $currentTime - $lastOnlineTime;

            // If unregistered and offline for more than 15 seconds, delete sensor
            if ($isRegistered === 0 && $status === 1 && $timeDiffSeconds > 15) {
                $deleteSql = "DELETE FROM sensorinfo WHERE soilSensorID = ?";
                $stmt = $conn->prepare($deleteSql);
                $stmt->bind_param("i", $soilSensorID);
                if ($stmt->execute()) {
                    echo "[" . date('Y-m-d H:i:s') . "] Sensor ID {$soilSensorID} (MAC: {$macAddress}) deleted.\n";
                }
                $stmt->close();
                continue; 
            }

            // If inactive for more than 15 seconds, update status
            if ($isRegistered === 1 && $status === 1 && $timeDiffSeconds > 15) {
                // Update sensorinfo
                $updateInfo = $conn->prepare("UPDATE sensorinfo SET sensorStatus = 0 WHERE soilSensorID = ?");
                $updateInfo->bind_param("i", $soilSensorID);
                $updateInfo->execute();
                $updateInfo->close();

                // Update deployment
                $updateDep = $conn->prepare("UPDATE deployment SET isConnected = 0 WHERE soilSensorID = ?");
                $updateDep->bind_param("i", $soilSensorID);
                
                if ($updateDep->execute()) {
                    echo "[" . date('Y-m-d H:i:s') . "] Sensor ID {$soilSensorID} (MAC: {$macAddress}) set to Offline.\n";
                }
                $updateDep->close();
            }
        }
    }
}


while(true) {
    checkIF_Offline($conn);
    sleep(5);
}

$conn->close();
?>