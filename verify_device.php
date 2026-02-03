<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json; charset=utf-8');

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

// Check if sensor exists
$sql = "SELECT sensorMacAddress FROM sensorinfo WHERE sensorMacAddress='$mac' LIMIT 1";
$result = $conn->query($sql);

$response = [];

if ($result && $result->num_rows > 0) {   
    // Update IP and set status to 1 (Online)
    $updateSql = "UPDATE sensorinfo 
                  SET sensorIPAddress = '$ip', sensorStatus = 1 
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
    //DEVICE DOES NOT EXIST
    $response = [
        "status" => "error",
        "message" => "Device not registered in database"
    ];
}

// Return JSON to ESP32
echo json_encode($response);

$conn->close();
?>