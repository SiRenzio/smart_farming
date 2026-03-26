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

// fetch sensor info based on MAC address
$sql = "SELECT soilSensorID, isRegistered FROM sensorinfo WHERE sensorMacAddress='$mac' LIMIT 1";
$result = $conn->query($sql);

if ($result && $result->num_rows > 0) {
    $sensor = $result->fetch_assoc();
    $sID = $sensor['soilSensorID'];

    // Update unregistered sensor status and last online time
    $conn->query("UPDATE sensorinfo SET sensorStatus=1, last_sensor_online='$dateTime' WHERE soilSensorID='$sID'");
    //file_get_contents("http://localhost/smart_farming/api/check_if_offline.php");
    //exec('php "' . __DIR__ . '/api/check_if_offline.php"');

    // If device is registered, we proceed to check deployment
    if ((int)$sensor['isRegistered'] === 1) {
        
        // Update deployment info
        $conn->query("UPDATE sensorinfo SET sensorStatus=1, last_sensor_online='$dateTime' WHERE soilSensorID='$sID'");

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
                    "locationID"   => (int)$deploy['locationID']
                ]);
            } else {
                // Device is registered and online, but the admin set isConnected to 0
                echo json_encode(["status" => "disconnected", "message" => "Device is set to disconnected"]);
            }
        } else {
            echo json_encode(["status" => "error", "message" => "No deployment assigned"]);
        }
    } else {
        echo json_encode(["status" => "unregistered", "message" => "Waiting for registration"]);
    }
} else {
    // If MAC is not in DB, insert as a new unregistered device
    $conn->query("INSERT INTO sensorinfo (sensorMacAddress, sensorStatus, isRegistered, last_sensor_online) 
                  VALUES ('$mac', 1, 0, '$dateTime')");
    echo json_encode(["status" => "new", "message" => "New device detected"]);
}

$conn->close();
?>