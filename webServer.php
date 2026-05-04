<?php
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');
date_default_timezone_set('Asia/Manila');

// Get the JSON data from the ESP32
$input = json_decode(file_get_contents('php://input'), true);

if (!isset($input['macAddress'])) {
    echo json_encode(["status" => "error", "message" => "MAC address is required"]);
    exit();
}

$mac = $conn->real_escape_string($input['macAddress']);
$ip  = $conn->real_escape_string($input['ipAddress']);
$dateTime = date('Y-m-d H:i:s');

// fetch sensor info based on MAC address, including isRestart flag
$sql = "SELECT soilSensorID, isRegistered, isRestart FROM sensorinfo WHERE sensorMacAddress='$mac' LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $sensor = $result->fetch_assoc();
    $sID = $sensor['soilSensorID'];
    $isRestart = (int)$sensor['isRestart'];

    if ($isRestart === 1) {
        $conn->query("UPDATE sensorinfo SET isRestart=0, sensorStatus=1, last_sensor_online='$dateTime' WHERE sensorMacAddress='$mac'");
    } else {
        $conn->query("UPDATE sensorinfo SET sensorStatus=1, last_sensor_online='$dateTime' WHERE sensorMacAddress='$mac'");
    }

    // If device is registered, we proceed to check deployment
    if ((int)$sensor['isRegistered'] === 1) {
        
        // fetch deployment details
        $depSql = "SELECT userID, soilSensorID, locationID, isConnected FROM deployment WHERE soilSensorID = '$sID' LIMIT 1";
        $depRes = $conn->query($depSql);

        if ($depRes && $depRes->num_rows > 0) {
            $deploy = $depRes->fetch_assoc();
            
            // Check if connected
            if ((int)$deploy['isConnected'] === 1) {
                echo json_encode([
                    "status" => "success",
                    "userID" => (int)$deploy['userID'],
                    "SoilSensorID" => (int)$deploy['soilSensorID'],
                    "locationID"   => (int)$deploy['locationID'],
                    "isRestart"    => $isRestart
                ]);
            } else {
                // Device is registered and online, but the admin set isConnected to 0
                echo json_encode([
                    "status" => "disconnected", 
                    "message" => "Device is set to disconnected",
                    "isRestart" => $isRestart
                ]);
            }
        } else {
            echo json_encode([
                "status" => "error", 
                "message" => "No deployment assigned",
                "isRestart" => $isRestart
            ]);
        }
    } else {
        echo json_encode([
            "status" => "unregistered", 
            "message" => "Waiting for registration",
            "isRestart" => $isRestart
        ]);
    }
} else {
    // If MAC is not in DB, insert as a new unregistered device (default isRestart to 0)
    $conn->query("INSERT INTO sensorinfo (sensorMacAddress, sensorStatus, isRegistered, last_sensor_online, isRestart) 
                  VALUES ('$mac', 1, 0, '$dateTime', 0)");
    echo json_encode(["status" => "new", "message" => "New device detected", "isRestart" => 0]);
}

$conn->close();
?>