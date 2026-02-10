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

            // If unregistered and offline for more than 60 seconds, delete sensor
            if ($isRegistered === 0 && $status === 0 && $timeDiffSeconds > 60) {
                $deleteSql = "DELETE FROM sensorinfo WHERE soilSensorID = ?";
                $stmt = $conn->prepare($deleteSql);
                $stmt->bind_param("i", $soilSensorID);
                $stmt->execute();
                $stmt->close();
                continue; 
            }

            // If inactive for more than 10 seconds, update status
            if ($isRegistered === 1 && $status === 1 && $timeDiffSeconds > 10) {
                $updateSql = "UPDATE sensorinfo SET sensorStatus = 0, isConnected = 0 WHERE soilSensorID = ?";
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

// Get POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!isset($data['macAddress']) || !isset($data['ipAddress'])) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid data", "message" => "Mac and IP are required"]);
    exit();
}

// Sanitize inputs
$mac = $conn->real_escape_string($data['macAddress']);
$ip  = $conn->real_escape_string($data['ipAddress']);

// Set current timestamp
$dateTime = date('Y-m-d H:i:s');

// Check if sensor exists
$sql = "SELECT sensorMacAddress, isRegistered FROM sensorinfo WHERE sensorMacAddress='$mac' LIMIT 1";
$result = $conn->query($sql);

$response = [];

if ($result && $result->num_rows > 0) {
    
    $row = $result->fetch_assoc();
    $isRegistered = $row['isRegistered'];

    if($isRegistered == 1) {
        // Update IP and set status to 1 (Online)
        $updateSql = "UPDATE sensorinfo 
                    SET sensorIPAddress = '$ip', sensorStatus = 1, last_sensor_online = '$dateTime'
                    WHERE sensorMacAddress = '$mac'";

        if ($conn->query($updateSql) === TRUE) {
            $response = [
                "status" => "success",
                "message" => "Device updated to Online",
                "received_mac" => $mac,
                "received_ip" => $ip
            ];
        } else {
            $response = [
                "status" => "error",
                "message" => "Database update failed: " . $conn->error
            ];
        }
    } else {
        // if not registered, just update IP and last online time without changing status
        $updateSql = "UPDATE sensorinfo 
                    SET sensorIPAddress = '$ip', last_sensor_online = '$dateTime'
                    WHERE sensorMacAddress = '$mac'";

        $conn->query($updateSql);
        $response = [
            "status" => "unregistered",
            "message" => "Device is not registered yet",
            "received_mac" => $mac,
            "received_ip" => $ip
        ];
    }

    
} else {
    //DEVICE DOES NOT EXIST

    $regiterSql = "INSERT INTO sensorinfo (sensorMacAddress, sensorIPAddress, sensorStatus, isConnected, isRegistered, last_sensor_online) 
                    VALUES ('$mac', '$ip', 0, 0, 0, '$dateTime')";
    if ($conn->query($regiterSql) === TRUE) {
        $response = [
            "status" => "success",
            "message" => "New device detected and ready to be registered",
            "received_mac" => $mac,
            "received_ip" => $ip
        ];
    } else {
        $response = [
            "status" => "error",
            "message" => "Failed to register new device: " . $conn->error
        ];
    }
}

// Return JSON to ESP32
echo json_encode($response);

while (true) {
    checkIF_Offline($conn);
    sleep(5);
}

$conn->close();
?>