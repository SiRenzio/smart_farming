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
    $currentliquidlevel = isset($decoded_data['currentliquidlevel']) ? (int)$decoded_data['currentliquidlevel'] : null;
    $wateringstatus     = isset($decoded_data['wateringstatus']) ? (int)$decoded_data['wateringstatus'] : null;
    $wateringFlag       = isset($decoded_data['wateringFlag']) ? (int)$decoded_data['wateringFlag'] : null;
    $updateType         = isset($decoded_data['updateType']) ? $decoded_data['updateType'] : 'event';

    if (!$liquidsensorID) {
        sendResponse(false, 'liquidsensorID is required');
    }

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

            if ($wateringFlag === 1 && $wateringstatus === 0) {
                $notifMessage = "LOW water tank level, system HOLD WATERING";
            }
            else if ($wateringFlag === 0 && $wateringstatus === 0) {
                $notifMessage = "Water tank is now FULL, system HOLD WATERING waiting to mix the solution";
            }
            else {
                $notifMessage = "Mixing process finished and watering is Abled. System READY!";
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