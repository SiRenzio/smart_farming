<?php
session_start();
require_once __DIR__ . '/../db.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

$userID = $_SESSION['userID'] ?? null; // Get userID from session for authentication

function sendResponse($success, $message, $data = null) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'data' => $data,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $json_payload = file_get_contents('php://input');
    $decoded_data = json_decode($json_payload, true);

    if (!$decoded_data) {
        sendResponse(false, 'Invalid JSON or empty payload');
    }

    $liquidsensorID     = isset($decoded_data['liquidsensorID']) ? (int)$decoded_data['liquidsensorID'] : null;
    $updateType         = isset($decoded_data['updateType']) ? $decoded_data['updateType'] : 'event';

    if (!$liquidsensorID) {
        sendResponse(false, 'liquidsensorID is required');
    }

    // RESET CYCLE
    if ($updateType === 'reset') {
        
        $resetQuery = "UPDATE tankpumpevent SET isActive = 0, fertFlag = 0 WHERE liquidsensorID = ? AND isActive = 1";
        $resetStmt = $conn->prepare($resetQuery);
        $resetStmt->bind_param("i", $liquidsensorID);
        
        if ($resetStmt->execute()) {
            $rowsChanged = $resetStmt->affected_rows;
            $resetStmt->close();
            
            if ($rowsChanged > 0) {
                sendResponse(true, 'Cycle reset complete. isActive set to 0 for Tank ' . $liquidsensorID, [
                    'liquidsensorID' => $liquidsensorID,
                    'rows_affected' => $rowsChanged,
                    'timestamp' => date('Y-m-d H:i:s')
                ]);
            } else {
                sendResponse(true, 'No update needed or record not found. All active records are already 0.', [
                    'liquidsensorID' => $liquidsensorID
                ]);
            }
        } else {
            $errorMsg = $resetStmt->error;
            $resetStmt->close();
            sendResponse(false, 'Database Error during reset: ' . $errorMsg);
        }
    }

    $currentliquidlevel = isset($decoded_data['currentliquidlevel']) ? (int)$decoded_data['currentliquidlevel'] : null;
    $wateringstatus     = isset($decoded_data['wateringstatus']) ? (int)$decoded_data['wateringstatus'] : null;
    $wateringFlag       = isset($decoded_data['wateringFlag']) ? (int)$decoded_data['wateringFlag'] : null;
    $isActiveFlag       = isset($decoded_data['isActive']) ? (int)$decoded_data['isActive'] : null;
    $wateringvolume     = isset($decoded_data['wateringvolume']) ? (int)$decoded_data['wateringvolume'] : null;

    $dateTime = date('Y-m-d H:i:s');


    // CONTINUOUS LEVEL UPDATE
    if ($updateType === 'continuous') {

        $jsonFile = '../failsafe/current_tank_levels.json';
        $tanksData = [];

        // Read existing data if the file already exists
        if (file_exists($jsonFile)) {
            $existingJson = file_get_contents($jsonFile);
            $decodedData = json_decode($existingJson, true);
            
            // Ensure it's a valid array before assigning
            if (is_array($decodedData)) {
                $tanksData = $decodedData;
            }
        }
        // Update the specific tank's data
        $tanksData[$liquidsensorID] = [
            'tank' => $liquidsensorID,
            'currentliquidlevel' => $currentliquidlevel,
            'dateandtime' => $dateTime // Assuming $dateTime is defined earlier in your script
        ];

        // Encode the updated data back to JSON with pretty print for readability
        $newJsonContent = json_encode($tanksData, JSON_PRETTY_PRINT);

        // Write to the file using an exclusive lock (LOCK_EX) to prevent file corruption from concurrent writes
        if (file_put_contents($jsonFile, $newJsonContent, LOCK_EX) !== false) {
            sendResponse(true, 'Level updated successfully in JSON', [
                'tank' => $liquidsensorID,
                'currentliquidlevel' => $currentliquidlevel,
                'dateandtime' => $dateTime
            ]);
        } else {
            sendResponse(false, 'Failed to update JSON file.');
        }
    }

    // PUMPING EVENT
    if ($updateType === 'event') {

        $stateQuery = "SELECT fertFlag, isActive FROM tankpumpevent WHERE liquidsensorID = ? ORDER BY tankPumpEventID DESC LIMIT 1";
        $stateStmt = $conn->prepare($stateQuery);
        $stateStmt->bind_param("i", $liquidsensorID);
        $stateStmt->execute();
        $stateRes = $stateStmt->get_result();
        $stateData = $stateRes->fetch_assoc();
        
        // Default to 0 if no record exists
        $currentFertFlag = $stateData['fertFlag'] ?? 0;
        $currentIsActive = $stateData['isActive'] ?? 0;
        $stateStmt->close();

        $stmt = $conn->prepare(
            'INSERT INTO tankpumpevent 
            (liquidsensorID, wateringstatus, wateringFlag, isActive, fertFlag, waterlevel, wateringvolume, dateandtime) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $stmt->bind_param(
            'iiiiiii',
            $liquidsensorID, $wateringstatus, $wateringFlag, $currentIsActive, 
            $currentFertFlag, $currentliquidlevel, $wateringvolume
        );

        if ($stmt->execute()) {

            $notifMessage = "";
            
            if ($wateringFlag === 0 && $wateringstatus === 0) {
                $notifMessage = "[Tank $liquidsensorID]: Water tank is now FULL, system HOLD WATERING waiting to mix the solution";
                
                $getlowquery = "SELECT waterlevel FROM tankpumpevent WHERE liquidsensorID = ? AND wateringFlag = 1 ORDER BY dateandtime DESC LIMIT 1";
                $lowStmt = $conn->prepare($getlowquery);
                $lowStmt->bind_param("i", $liquidsensorID);
                $lowStmt->execute();
                $res = $lowStmt->get_result();
                $lowStmt->close();

                if($row = $res->fetch_assoc()){
                    $lowValue = $row['waterlevel'] / 100 ;
                    $highValue = $currentliquidlevel / 100 ;
                    $heightDiff = abs($highValue - $lowValue);

                    $tankDiameter = 0.48; // in meters
                    $radius = $tankDiameter / 2; // in meters
                    $volume = pi() * pow($radius, 2) * $heightDiff;
                    $liters = round($volume * 1000, 2);

                    // get the fertilizer based on the liquidsensorID
                    $getFertQuery = "SELECT fertilizerName, fertilizerAmount FROM fertilizer WHERE liquidsensorID = ? ORDER BY fertilizerID DESC LIMIT 1";
                    $getFertStmt = $conn->prepare($getFertQuery);
                    $getFertStmt->bind_param("i", $liquidsensorID);
                    $getFertStmt->execute();
                    $fertResult = $getFertStmt->get_result();

                    if($fertRow = $fertResult->fetch_assoc()){
                        $fertilizerName = $fertRow['fertilizerName'];
                        $fertilizerAmount = $fertRow['fertilizerAmount'];
                        
                        // Fertilizer in grams and cups
                        $fertInGrams = $liters * $fertilizerAmount;
                        $fertInCup = round($fertInGrams / 150, 2);

                        // Alternatives
                        $alternativeAmount = $liters * 2.5; // 2.5ml/L of fermented fruit juice and fish amino acid
                        $fishaminoAmount = round($alternativeAmount / 150, 2); // for fish amino acid
                        $fermentfruitAmount = round($alternativeAmount / 150, 2); // for fermented fruit juice

                        // Fertilizer amount notification
                        $fertALert = "[Tank $liquidsensorID]: Tank is filled $liters liters of water. Mix in $fertInCup sardine-can scoops of $fertilizerName [$fertilizerAmount g/L]. [150grams sardine-can]";
                        $fertSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                        $alertStmt = $conn->prepare($fertSql);
                        $alertStmt->bind_param("s", $fertALert);
                        $alertStmt->execute();
                        $alertStmt->close();

                        // Alternative amount notification
                        $alternativesAlert = "[Tank $liquidsensorID]: Tank is filled $liters liters of water. Mix in $fishaminoAmount sardine-can scoops of Fish Amino Acid and $fermentfruitAmount sardine-can scoops of Fermented Fruit Juice. [150grams sardine-can]";
                        $alternativeSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                        $alernativeStmt = $conn->prepare($alternativeSql);
                        $alernativeStmt->bind_param("s", $alternativesAlert);
                        $alernativeStmt->execute();
                        $alernativeStmt->close();
                    }
                }
            }
            else if ($wateringFlag === 1 && $wateringstatus === 0) {
                $notifMessage = "[Tank $liquidsensorID]: LOW water tank level, system HOLD WATERING";
            }
            else {
                if ($liquidsensorID === 1) {
                    $notifMessage = "[Tank $liquidsensorID]: is Now FULL and watering is Abled. System READY!";
                }
                else {
                    $notifMessage = "[Tank $liquidsensorID]: Mixing process finished and watering is Abled. System READY!";
                }
            }

            if (!empty($notifMessage)) {
                $notifSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("s", $notifMessage);
                $notifStmt->execute();
                $notifStmt->close();
            }

            $insertId = $conn->insert_id;

            sendResponse(true, 'Sensor data received and stored successfully', [
                'id' => $insertId,
                'tank'=> $liquidsensorID,
                'timestamp'=> $dateTime,
                'values'=> [
                    'wStatus'=>$wateringstatus,
                    'wFlag'=>$wateringFlag,
                    'volume'=>$currentliquidlevel
                ]
            ]);
        } 
        else {
            sendResponse(false, 'Failed to store data: ' . $conn->error);
        }

        $stmt->close();
    }

    // WATERING EVENT
    if ($updateType === 'watering') {
        
        $fertQuery = "SELECT fertFlag FROM tankpumpevent WHERE liquidsensorID = ? ORDER BY tankPumpEventID DESC LIMIT 1";
        $fertStmt = $conn->prepare($fertQuery);
        $fertStmt->bind_param("i", $liquidsensorID);
        $fertStmt->execute();
        $fertRes = $fertStmt->get_result();
        $lastFert = $fertRes->fetch_assoc();
        $currentFertFlag = $lastFert['fertFlag'] ?? 0;
        $fertStmt->close();

        $wateringStmt = $conn->prepare(
            'INSERT INTO tankpumpevent 
            (liquidsensorID, wateringstatus, wateringFlag, isActive, waterlevel, wateringvolume, fertFlag, dateandtime) 
            VALUES (?, ?, ?, ?, ?, ?, ?, NOW())'
        );

        $wateringStmt->bind_param('iiiiiii', 
            $liquidsensorID, $wateringstatus, $wateringFlag, $isActiveFlag, 
            $currentliquidlevel, $wateringvolume, $currentFertFlag
        );

        if ($wateringStmt->execute()) {
            
            $soilQuery = $conn->query("SELECT soilSensorID FROM deployment WHERE isPrimary = 1 LIMIT 1");
            $soilRow = $soilQuery->fetch_assoc();
            $soilSensorID = $soilRow ? $soilRow['soilSensorID'] : null;

            if ($soilSensorID) {
                $jobPath = __DIR__ . "/../moisture_jobs/job_$soilSensorID.json";
                $jobData = [];

                // Read existing data so we don't wipe out tracking variables
                if (file_exists($jobPath)) {
                    $existingData = file_get_contents($jobPath);
                    $jobData = json_decode($existingData, true) ?: [];
                }

                // Update only the watering trigger fields
                $jobData["soilSensorID"] = $soilSensorID;
                $jobData["startTime"] = time();
                $jobData["triggeredBy"] = "watering";

                // Save it back
                file_put_contents($jobPath, json_encode($jobData));
                error_log("Creating job file for event $soilSensorID");
            } else {
                error_log("Warning: No primary deployment found. Job file not created.");
            }

            $notifMessage = "";

            if ($liquidsensorID === 1) {
                $notifMessage = "Tank $liquidsensorID solenoid watered the plants with an amount of $wateringvolume mL of water.";
            } else {
                $getFertQuery = "SELECT fertilizerName, fertilizerAmount FROM fertilizer WHERE liquidsensorID = ? ORDER BY fertilizerID DESC LIMIT 1";
                $getFertStmt = $conn->prepare($getFertQuery);
                $getFertStmt->bind_param("i", $liquidsensorID);
                $getFertStmt->execute();
                $fertResult = $getFertStmt->get_result();

                if ($fertRow = $fertResult->fetch_assoc()) {
                    $fertilizerName = $fertRow['fertilizerName'];
                    $notifMessage = "Tank $liquidsensorID solenoid watered the plants with an amount of $wateringvolume mL of $fertilizerName fertilizer.";
                }
                $getFertStmt->close();
            }

            if (!empty($notifMessage)) {
                $notifSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("s", $notifMessage);
                $notifStmt->execute();
                $notifStmt->close();
            }

            $wateringStmt->close();
            sendResponse(true, 'Watering event logged successfully'); 

        } else {
            $errorMsg = $conn->error;
            $wateringStmt->close();
            sendResponse(false, 'Failed to store watering data: ' . $errorMsg);
        }
    }

    // FAILSAFE NOTIFICATION
    if($updateType === 'failsafe') {
        // Updated message to accurately reflect the flow sensor timeout
        $notifMessage = "Failsafe triggered for Tank $liquidsensorID. Flow stopped or not detected! Watering process aborted.";
        $notifSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
        $notifStmt = $conn->prepare($notifSql);
        $notifStmt->bind_param("s", $notifMessage);
        $notifStmt->execute();
        
        // Always good practice to send a JSON response back to the ESP32 so it doesn't hang
        sendResponse(true, "Failsafe logged for Tank $liquidsensorID");
        $notifStmt->close();
    }

    // HANDSHAKE LOGIC
    if ($updateType === 'handshake') {
        $settingsPath = __DIR__ . '/../failsafe/settings.json';
        $settingsData = null;
        
        if (file_exists($settingsPath)) {
            $jsonConfig = file_get_contents($settingsPath);
            $settingsData = json_decode($jsonConfig, true);
        }

        // Notif if Tank Controller is connected
        $statusPath = __DIR__ . "/../failsafe/tank_status.json";
        $lastSeen = 0;
        if (file_exists($statusPath)) {
            $statusData = json_decode(file_get_contents($statusPath), true);
            $lastSeen = $statusData['last_seen'] ?? 0;
        }
        $timeSinceLastHandshake = time() - $lastSeen;
        
        // 60 seconds (1 minute) threshold
        $disconnectThreshold = 60; 
        if ($timeSinceLastHandshake > $disconnectThreshold) {
            // It has been offline longer than the threshold, so it just reconnected!
            $notifMessage = "Tank Controller is now connected.";
            $notifSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
            $notifStmt = $conn->prepare($notifSql);
            $notifStmt->bind_param("s", $notifMessage);
            $notifStmt->execute();
            $notifStmt->close();
        }

        // Update the last seen time 
        file_put_contents($statusPath, json_encode(['last_seen' => time()]));

        sendResponse(true, 'Handshake successful', [
            'liquidsensorID' => $liquidsensorID,
            'settings' => $settingsData
        ]);
    }

    sendResponse(false, 'Invalid updateType');
}

sendResponse(false, 'Only POST requests are accepted for sensor data');
?>