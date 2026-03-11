<?php
session_start();
require_once '../db.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

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

    /* ================= RESET CYCLE FROM ESP32 (AFTER 15-MINUTE WAIT) ================= */
    if ($updateType === 'reset') {
        $resetQuery = "UPDATE tankpumpevent SET isActive = 0 WHERE liquidsensorID = ? ORDER BY tankPumpEventID DESC LIMIT 1";
        $resetStmt = $conn->prepare($resetQuery);
        $resetStmt->bind_param("i", $liquidsensorID);
        $resetStmt->execute();
        $resetStmt->close();
        
        sendResponse(true, 'Cycle reset complete. isActive set to 0 for Tank ' . $liquidsensorID, [
            'liquidsensorID' => $liquidsensorID,
            'timestamp' => date('Y-m-d H:i:s')
        ]);
    }

    $currentliquidlevel = isset($decoded_data['currentliquidlevel']) ? (int)$decoded_data['currentliquidlevel'] : null;
    $wateringstatus     = isset($decoded_data['wateringstatus']) ? (int)$decoded_data['wateringstatus'] : null;
    $wateringFlag       = isset($decoded_data['wateringFlag']) ? (int)$decoded_data['wateringFlag'] : null;

    $dateTime = date('Y-m-d H:i:s');

    // Currentliquid level
    if ($updateType === 'continuous') {

        $stmtLevel = $conn->prepare(
            'INSERT INTO liquidlevelsensor 
            (liquidsensorID, currentliquidlevel, dateandtime) 
            VALUES (?, ?, NOW())'
        );
        $stmtLevel->bind_param('ii', $liquidsensorID, $currentliquidlevel);
        if ($stmtLevel->execute()) {
            $insertId = $conn->insert_id;
            sendResponse(true, 'Level updated successfully', [
                'id' => $insertId,
                'tank'=> $liquidsensorID,
                'data'=> $currentliquidlevel,
                'timestamp'=> $dateTime
            ]);
        } 
        else {
            sendResponse(false, 'Failed to store data: ' . $conn->error);
        }
        $stmtLevel->close();
    }

    // event
    if ($updateType === 'event') {

        $stmt = $conn->prepare(
            'INSERT INTO tankpumpevent 
            (liquidsensorID, wateringstatus, wateringFlag, wateringvolume, dateandtime) 
            VALUES (?, ?, ?, ?, NOW())'
        );

        $stmt->bind_param(
            'iiii',
            $liquidsensorID,
            $wateringstatus,
            $wateringFlag,
            $currentliquidlevel
        );

        if ($stmt->execute()) {

            $notifMessage = "";

            
            if ($wateringFlag === 0 && $wateringstatus === 0) {
                $notifMessage = "[Tank $liquidsensorID]: Water tank is now FULL, system HOLD WATERING waiting to mix the solution";
                
                $getlowquery = "SELECT wateringvolume FROM tankpumpevent WHERE liquidsensorID = ? AND wateringFlag =1 ORDER BY dateandtime DESC LIMIT 1";
                $lowStmt = $conn->prepare($getlowquery);
                $lowStmt->bind_param("i", $liquidsensorID);
                $lowStmt->execute();
                $res = $lowStmt->get_result();

                if($row = $res->fetch_assoc()){
                    $lowValue = $row['wateringvolume'] / 100 ;
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

                    if($row = $fertResult->fetch_assoc()){
                        $fertilizerName = $row['fertilizerName'];
                        $fertilizerAmount = $row['fertilizerAmount'];

                        $fertInGrams = $liters * $fertilizerAmount;
                        $fertInCup = round($fertInGrams / 150, 2);

                        $fertALert = "[Tank $liquidsensorID]: Tank is filled $liters liters of water. Mix in $fertInCup sardine-can scoops of $fertilizerName [$fertilizerAmount g/L]. [150grams sardine-can]";
                        $fertSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                        $alertStmt = $conn->prepare($fertSql);
                        $alertStmt->bind_param("s", $fertALert);
                        $alertStmt->execute();
                        $alertStmt->close();
                    }
                }
            }
            else if ($wateringFlag === 1 && $wateringstatus === 0) {
                $notifMessage = "[Tank $liquidsensorID]: LOW water tank level, system HOLD WATERING";
            }
            else {
                $notifMessage = "[Tank $liquidsensorID]:Mixing process finished and watering is Abled. System READY!";
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

    if ($updateType === 'handshake') {
        sendResponse(true, 'Handshake successful', [
            'liquidsensorID' => $liquidsensorID
        ]);
    }

    sendResponse(false, 'Invalid updateType');
}

sendResponse(false, 'Only POST requests are accepted for sensor data');
?>