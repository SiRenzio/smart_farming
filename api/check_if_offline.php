<?php
require_once 'db.php';
session_start();
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

// Function to check and update offline sensors
function checkIF_Offline($conn) {
    $currentTime = time();

    // Fetch userID as well to avoid relying on session in a continuous loop
    $checkSql = "SELECT s.soilSensorID, s.userID, s.sensorMacAddress, s.last_sensor_online, s.sensorStatus, s.isRegistered, d.locationID 
                 FROM sensorinfo s
                 LEFT JOIN deployment d ON s.soilSensorID = d.soilSensorID";

    $result = $conn->query($checkSql);

    if ($result && $result->num_rows > 0) {
        while ($row = $result->fetch_assoc()) {
            $soilSensorID = $row['soilSensorID'];
            $userID       = $row['userID'];
            $macAddress   = $row['sensorMacAddress'];
            $lastOnline   = $row['last_sensor_online'];
            $status       = (int)$row['sensorStatus'];
            $isRegistered = (int)$row['isRegistered'];
            $locationID   = $row['locationID'];
            
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
                
                // sensor offline
                $updateInfo = $conn->prepare("UPDATE sensorinfo SET sensorStatus = 0 WHERE soilSensorID = ?");
                $updateInfo->bind_param("i", $soilSensorID);
                $updateInfo->execute();
                $updateInfo->close();

                // disconnect
                $updateDep = $conn->prepare("UPDATE deployment SET isConnected = 0, isPrimary = 0 WHERE soilSensorID = ?");
                $updateDep->bind_param("i", $soilSensorID);
                if ($updateDep->execute()) {
                    echo "[" . date('Y-m-d H:i:s') . "] Sensor ID {$soilSensorID} (MAC: {$macAddress}) set to Offline.\n";
                }
                $updateDep->close();

                // look for another connected sensor or user
                $checkNextSql = "SELECT d.soilSensorID 
                                 FROM deployment d
                                 JOIN sensorinfo s ON d.soilSensorID = s.soilSensorID
                                 WHERE d.userID = ? 
                                   AND d.locationID = ? 
                                   AND s.sensorStatus = 1 
                                   AND d.isConnected = 1 
                                   AND d.isPrimary = 0 
                                   AND d.soilSensorID != ? 
                                 ORDER BY d.deploymentID ASC LIMIT 1";
                                 
                $checkStmt = $conn->prepare($checkNextSql);
                $checkStmt->bind_param("iii", $userID, $locationID, $soilSensorID);
                $checkStmt->execute();
                $checkResult = $checkStmt->get_result();
                
                if ($checkResult->num_rows > 0) {
                    // Found another connected sensor, transfer the primary flag
                    $nextSensor = $checkResult->fetch_assoc();
                    $newPrimaryID = $nextSensor['soilSensorID'];
                    
                    $promoteStmt = $conn->prepare("UPDATE deployment SET isPrimary = 1 WHERE soilSensorID = ?");
                    $promoteStmt->bind_param("i", $newPrimaryID);
                    if ($promoteStmt->execute()) {
                        echo "[" . date('Y-m-d H:i:s') . "] Sensor ID {$newPrimaryID} promoted to Primary.\n";
                    }
                    $promoteStmt->close();
                }
                $checkStmt->close();
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