<?php
require_once '../db.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');


function sendResponse($success, $message, $command, $liquidVolume) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'command' => $command,
        'liquidVolume' => $liquidVolume,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

// Fetch the latese sensor data
$sensorDataID = $_GET['sensorDataID'] ?? null;

if ($sensorDataID) {
    $stmt = $conn->prepare("
        SELECT *
        FROM sensordata
        WHERE SensorDataID > ?
        ORDER BY DateTime DESC
        LIMIT 1
    ");

    $stmt->bind_param("s", $sensorDataID);

} else {
    $stmt = $conn->prepare("
        SELECT *
        FROM sensordata
        ORDER BY DateTime DESC
        LIMIT 1
    ");
}

$stmt->execute();
$result = $stmt->get_result();

// Fetch plant parameters
$plantParams = $conn->query("SELECT * FROM plantnutrionneed ORDER BY nutritionID DESC")->fetch_assoc();
$checkPumEvent = $conn->query("SELECT waterstatus, wateringFlag FROM tankpumpevent WHERE tankID = 1 ORDER BY tankPumpEventID DESC LIMIT 1")->fetch_assoc();

if ($row = $result->fetch_assoc()) {
    // Process the sensor data and determine actions
    if ($row['SoilT'] < 30) {
        if ($row['SoilMois'] < $plantParams['meanMoistureThreshold']) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // Check if motor is on condition here (Tank 1)
                    if ($checkPumEvent) {
                        if ($checkPumEvent['wateringFlag'] === 1) {
                            return;
                        }
                    } 
                    else if ($checkPumEvent['wateringstatus'] === NULL && $checkPumEvent['wateringFlag'] === NULL) {
                        // Send command to turn on the pump
                        $command = "trig_tsl1";
                        $liquidVolume = $plantParams['liquidVolume'];
                        sendResponse(true, 'Pump turned on', $command, $liquidVolume);
                    } 
                    

                }
                // Check if one type of fertiliizer is needed contition here (identify which type of fertilizer is needed based on the soil parameters)
                // tank 2 | nitrabor | trig_tsl2 ========== tank 3 | UNIK16/WINNER | trig_tsl3


            }
            else {
                //Check if motor is on condition here (Tank 1)
                if ($checkPumEvent) {
                    if ($checkPumEvent['wateringFlag'] === 1) {
                        return;
                    }
                } 
                else if ($checkPumEvent['wateringstatus'] === NULL && $checkPumEvent['wateringFlag'] === NULL) {
                    // Send command to turn on the pump
                    $command = "trig_tsl1";
                    $liquidVolume = $plantParams['liquidVolume'];
                    sendResponse(true, 'Pump turned on', $command, $liquidVolume);
                }
            }


        }
        // else continue monitoring soil temperature
    }
    else if ($row['SoilT'] >= 31 && $row['SoilT'] <= 34) {
        if ($row['SoilMois'] < ($plantParams['meanMoistureThreshold'] + 5)) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // Check if motor is on condition here
                }
                // Check if one type of fertiliizer is needed contition here
            }
        }
        // else continue monitoring soil temperature
    }
    else if ($row['SoilT'] > 34) {
        if ($row['SoilMois'] < ($plantParams['meanMoistureThreshold'] + 10)) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // Check if motor is on condition here
                }
                // Check if one type of fertiliizer is needed contition here
            }
        }
        // else continue monitoring soil temperature
    }

    
    echo json_encode([
        "status" => "updated",
        "sensor" => $row
    ]);

} else {
    echo json_encode([
        "status" => "no-change"
    ]);
}
?>