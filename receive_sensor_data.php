<?php
/**
 * receive_sensor_data.php
 * 
 * This file accepts sensor data from IoT devices via POST/GET requests
 * and saves the data to the database for real-time monitoring.
 * 
 * Usage:
 * - POST JSON data: POST to this file with JSON content
 * - GET parameters: GET request with query parameters
 * - Raw POST data: POST with any content type
 */

// Include database connection
require_once 'db.php';

// Set headers to allow cross-origin requests (useful for IoT devices)
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type');
header('Content-Type: application/json; charset=utf-8');

// Handle preflight OPTIONS request
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(400);
    exit();
}

// Initialize response array
$response = [
    'status' => 'success',
    'message' => 'Data received successfully',
    'timestamp' => date('Y-m-d H:i:s'),
    'method' => $_SERVER['REQUEST_METHOD'],
    'data_received' => null,
    'database_insert' => null
];

try {
    // Get all incoming data (GET, POST, COOKIE)
    $dataToLog = $_REQUEST;
    
    if (!empty($dataToLog)) {
        $response['data_received'] = 'Data received via ' . $_SERVER['REQUEST_METHOD'];
        
        // Extract sensor data from the request
        $soilSensorID = $dataToLog['SoilSensorID'] ?? null;
        $soilSensorID = $dataToLog['locationID'] ?? null;
        $soilN = $dataToLog['SoilN'] ?? null;
        $soilP = $dataToLog['SoilP'] ?? null;
        $soilK = $dataToLog['SoilK'] ?? null;
        $soilEC = $dataToLog['SoilEC'] ?? null;
        $soilPH = $dataToLog['SoilPH'] ?? null;
        $soilT = $dataToLog['SoilT'] ?? null;
        $soilMois = $dataToLog['SoilMois'] ?? null;
        $liquidVolume = $dataToLog['liquidVolume'] ?? null;
        
        // Validate required fields
        if ($soilSensorID === null) {
            throw new Exception('SoilSensorID is required');
        }
        
        // Prepare and execute the INSERT statement
        $stmt = $conn->prepare('INSERT INTO sensordata (SoilSensorID, locationID, SoilN, SoilP, SoilK, SoilEC, SoilPH, SoilT, SoilMois, liquidVolume, DateTime) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())');
        
        if ($stmt === null) {
            throw new Exception('Failed to prepare SQL statement: ' . $conn->error);
        }
        
        // Bind parameters
        $stmt->bind_param('iiiiiidddd', 
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
        
        // Execute the statement
        if ($stmt->execute()) {
            $insertedID = $conn->insert_id;
            $response['database_insert'] = 'Data inserted successfully with ID: ' . $insertedID;
            $response['message'] = 'Sensor data saved to database successfully';
        } else {
            throw new Exception('Failed to execute SQL statement: ' . $stmt->error);
        }
        
        $stmt->close();
        
    } else {
        $response['status'] = 'warning';
        $response['message'] = 'No data received';
        $response['data_received'] = 'Empty request';
    }
    
} catch (Exception $e) {
    $response['status'] = 'error';
    $response['message'] = 'Exception occurred: ' . $e->getMessage();
    $response['data_received'] = 'Exception during processing';
    $response['database_insert'] = 'Failed to insert data';
}

// Return JSON response
echo json_encode($response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

/* 
// COMMENTED OUT: File logging code (keeping for reference)
// Create logs directory if it doesn't exist
$logsDir = 'sensor_logs';
if (!is_dir($logsDir)) {
    mkdir($logsDir, 0755, true);
}

// Create daily subdirectory for better organization
$today = date('Y-m-d');
$dailyDir = $logsDir . '/' . $today;
if (!is_dir($dailyDir)) {
    mkdir($dailyDir, 0755, true);
}

// Generate filename based on timestamp
$fileName = 'sensor_data_' . date('H-i-s') . '.txt';

// Add metadata to the data
$logData = [
    'timestamp' => date('Y-m-d H:i:s'),
    'remote_ip' => $_SERVER['REMOTE_ADDR'] ?? 'unknown',
    'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'unknown',
    'request_method' => $_SERVER['REQUEST_METHOD'],
    'content_type' => $_SERVER['CONTENT_TYPE'] ?? 'unknown',
    'data' => $dataToLog
];

// Convert to readable format
$logContent = "=== SENSOR DATA LOG ===\n";
$logContent .= "Timestamp: " . $logData['timestamp'] . "\n";
$logContent .= "Remote IP: " . $logData['remote_ip'] . "\n";
$logContent .= "User Agent: " . $logData['user_agent'] . "\n";
$logContent .= "Request Method: " . $logData['request_method'] . "\n";
$logContent .= "Content Type: " . $logData['content_type'] . "\n";
$logContent .= "Data Received:\n";
$logContent .= json_encode($logData['data'], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
$logContent .= "\n\n=== END LOG ===\n";

// Save to file
$filePath = $dailyDir . '/' . $fileName;
if (file_put_contents($filePath, $logContent) !== false) {
    $response['file_saved'] = $filePath;
    $response['message'] = 'Data received and saved successfully';
} else {
    $response['status'] = 'error';
    $response['message'] = 'Failed to save data to file';
    $response['file_saved'] = 'Failed to save';
}

// Also log the response to a separate log file for debugging
$responseLog = date('Y-m-d H:i:s') . " - " . json_encode($response) . "\n";
$responseLogFile = $logsDir . '/api_responses.log';
file_put_contents($responseLogFile, $responseLog, FILE_APPEND | LOCK_EX);
*/
?>
