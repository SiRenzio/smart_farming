<?php
require_once 'db.php';

// Set headers to allow cross-origin requests if needed
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST');
header('Access-Control-Allow-Headers: Content-Type');

// Function to send JSON response
function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Check if this is a GET request (from Arduino)
if ($_SERVER['REQUEST_METHOD'] === 'GET') {
    // Get parameters from query string (Arduino sends these)
    $soilSensorID = isset($_GET['SoilSensorID']) ? (int)$_GET['SoilSensorID'] : null;
    $locationID = isset($_GET['locationID']) ? (int)$_GET['locationID'] : null;
    $soilN = isset($_GET['SoilN']) ? (float)$_GET['SoilN'] : null;
    $soilP = isset($_GET['SoilP']) ? (float)$_GET['SoilP'] : null;
    $soilK = isset($_GET['SoilK']) ? (float)$_GET['SoilK'] : null;
    $soilEC = isset($_GET['SoilEC']) ? (float)$_GET['SoilEC'] : null;
    $soilPH = isset($_GET['SoilPH']) ? (float)$_GET['SoilPH'] : null;
    $soilT = isset($_GET['SoilT']) ? (float)$_GET['SoilT'] : null;
    $soilMois = isset($_GET['SoilMois']) ? (float)$_GET['SoilMois'] : null;
    $liquidVolume = isset($_GET['liquidVolume']) ? (float)$_GET['liquidVolume'] : null;
    
    // Validate required fields
    if (!$soilSensorID) {
        sendResponse(false, 'SoilSensorID is required');
    }
    if (!$locationID) {
        sendResponse(false, 'LocationID is required');
    }
    
    // Check if sensor exists
    $check_sensorStmt = $conn->prepare('SELECT soilSensorID FROM sensorinfo WHERE soilSensorID = ?');
    $check_sensorStmt->bind_param('i', $soilSensorID);
    $check_sensorStmt->execute();
    $check_sensorResult = $check_sensorStmt->get_result();
    
    if ($check_sensorResult->num_rows === 0) {
        sendResponse(false, 'Sensor ID ' . $soilSensorID . ' does not exist');
    }
    $check_sensorStmt->close();

    // Check if location exists
    $check_locationStmt = $conn->prepare('SELECT locationID FROM locationinfo WHERE locationID = ?');
    $check_locationStmt->bind_param('i', $locationID);
    $check_locationStmt->execute();
    $check_locationResult = $check_locationStmt->get_result();
    
    if ($check_locationResult->num_rows === 0) {
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
    $stmt = $conn->prepare('INSERT INTO sensordata (SoilSensorID, locationID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)');
    
    // Handle NULL values properly
    $bindN = $soilN ?? 0;
    $bindP = $soilP ?? 0;
    $bindK = $soilK ?? 0;
    $bindEC = $soilEC ?? 0;
    $bindPH = $soilPH ?? 0.0;
    $bindT = $soilT ?? 0.0;
    $bindMois = $soilMois ?? 0.0;
    $bindFlow = $liquidVolume ?? 0.0;
    
    $stmt->bind_param('iiiiiidddds', 
        $soilSensorID, 
        $locationID,
        $bindN, 
        $bindP, 
        $bindK, 
        $bindEC, 
        $bindPH, 
        $bindT, 
        $bindMois, 
        $bindFlow, 
        $dateTime
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
                'FlowRate' => $flowRate
            ]
        ]);
    } else {
        sendResponse(false, 'Failed to store sensor data: ' . $conn->error);
    }
    
    $stmt->close();
}

// If not GET request, send error
sendResponse(false, 'Only GET requests are accepted for sensor data');
?>
