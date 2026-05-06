<?php
    include_once '../db.php';

    // Sensor Data
    $sensorStmt = $conn->prepare('SELECT s.*, sen.sensorName, f.farmName FROM sensordata s 
                                    JOIN sensorinfo sen ON s.SoilSensorID = sen.SoilSensorID 
                                    JOIN farmlocation f ON s.locationID = f.locationID 
                                    ORDER BY s.DateTime DESC LIMIT 1');
    $sensorStmt->execute();
    $sensorData = $sensorStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sensorStmt->close();

    // Tank Data
    $jsonFilePath = 'current_tank_levels.json'; 
    $tankData = [];

    if (file_exists($jsonFilePath)) {
        $jsonContent = file_get_contents($jsonFilePath);
        $decodedData = json_decode($jsonContent, true); 
        
        if (json_last_error() === JSON_ERROR_NONE && is_array($decodedData)) {
            // Loop through the JSON object and format it for the frontend
            foreach ($decodedData as $key => $tankEntry) {
                $tankData[] = [
                    // Map "tank" from JSON to "liquidsensorID" for test.js
                    'liquidsensorID' => $tankEntry['tank'], 
                    'currentliquidlevel' => $tankEntry['currentliquidlevel'],
                    'dateandtime' => $tankEntry['dateandtime']
                ];
            }
        } else {
            error_log("Failed to parse $jsonFilePath: " . json_last_error_msg());
        }
    } else {
        error_log("Warning: $jsonFilePath not found.");
    }

    // Return Combined Data
    echo json_encode([
        'sensorData' => $sensorData,
        'tankData' => $tankData
    ]);
?>