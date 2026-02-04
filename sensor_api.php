<?php
require_once 'db.php';

// Set headers for JSON and Cross-Origin
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to send JSON response (Structure maintained for ESP32/Arduino)
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Check if this is a POST request
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    
    // Fetch raw JSON payload from ESP32
    $json_payload = file_get_contents('php://input');
    $decoded_data = json_decode($json_payload, true);

    if (!$decoded_data) {
        sendResponse(false, 'Invalid JSON or empty payload');
    }

    // Map ESP32 JSON keys to PHP variables
    $soilSensorID = isset($decoded_data['SoilSensorID']) ? (int)$decoded_data['SoilSensorID'] : null;
    $locationID = isset($decoded_data['locationID']) ? (int)$decoded_data['locationID'] : null;
    $soilN = isset($decoded_data['soilN']) ? (float)$decoded_data['soilN'] : 0;
    $soilP = isset($decoded_data['soilP']) ? (float)$decoded_data['soilP'] : 0;
    $soilK = isset($decoded_data['soilK']) ? (float)$decoded_data['soilK'] : 0;
    $soilEC = isset($decoded_data['soilEC']) ? (float)$decoded_data['soilEC'] : 0;
    $soilPH = isset($decoded_data['soilpH']) ? (float)$decoded_data['soilpH'] : 0.0;
    $soilT = isset($decoded_data['soilT']) ? (float)$decoded_data['soilT'] : 0.0;
    $soilMois = isset($decoded_data['soilM']) ? (float)$decoded_data['soilM'] : 0.0;
    $liquidVolume = isset($decoded_data['soilLV']) ? (float)$decoded_data['soilLV'] : 0.0;
    
    // Validate required IDs
    if (!$soilSensorID || !$locationID) {
        sendResponse(false, 'SoilSensorID and locationID are required');
    }
    
    // Check if sensor exists
    $check_sensorStmt = $conn->prepare('SELECT soilSensorID FROM sensorinfo WHERE soilSensorID = ?');
    $check_sensorStmt->bind_param('i', $soilSensorID);
    $check_sensorStmt->execute();
    if ($check_sensorStmt->get_result()->num_rows === 0) {
        $check_sensorStmt->close();
        sendResponse(false, 'Sensor ID ' . $soilSensorID . ' does not exist');
    }
    $check_sensorStmt->close();

    // Check if location exists
    $check_locationStmt = $conn->prepare('SELECT locationID FROM farmlocation WHERE locationID = ?');
    $check_locationStmt->bind_param('i', $locationID);
    $check_locationStmt->execute();
    if ($check_locationStmt->get_result()->num_rows === 0) {
        $check_locationStmt->close();
        sendResponse(false, 'Location ID ' . $locationID . ' does not exist');
    }
    $check_locationStmt->close();

    // Validate data ranges
    if ($soilPH !== null && ($soilPH < 0 || $soilPH > 14)) {
        sendResponse(false, 'Soil pH must be between 0 and 14. Received: ' . $soilPH);
    }
    
    if ($soilMois !== null && ($soilMois < 0 || $soilMois > 100)) {
        sendResponse(false, 'Soil moisture must be between 0% and 100%. Received: ' . $soilMois . '%');
    }

    // Set current timestamp
    $dateTime = date('Y-m-d H:i:s');
    
    // Insert data into database
    $stmt = $conn->prepare('INSERT INTO sensordata (SoilSensorID, locationID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
    
    $stmt->bind_param('iidddddddd', 
        $soilSensorID, 
        $locationID,
        $soilN, 
        $soilP, 
        $soilK, 
        $soilEC, 
        $soilPH, 
        $soilT, 
        $soilMois, 
        $liquidVolume
    );
    
    if ($stmt->execute()) {
        $insertId = $conn->insert_id;
        sendResponse(true, 'Sensor data received and stored successfully', [
            'id' => $insertId,
            'sensor_id' => $soilSensorID,
            'location_id' => $locationID,
            'timestamp' => $dateTime,
            'values' => [
                'N' => $soilN,
                'P' => $soilP,
                'K' => $soilK,
                'EC' => $soilEC,
                'pH' => $soilPH,
                'Temperature' => $soilT,
                'Moisture' => $soilMois,
                'Volume' => $liquidVolume
            ]
        ]);
    } else {
        sendResponse(false, 'Failed to store sensor data: ' . $conn->error);
    }
    
    $stmt->close();
}

sendResponse(false, 'Only POST requests are accepted for sensor data');
?>