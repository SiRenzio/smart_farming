<?php
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

    $userID = isset($decoded_data['userID']) ? (int)$decoded_data['userID'] : null;
    $liquidsensorID = isset($decoded_data['liquidsensorID']) ? (int)$decoded_data['liquidsensorID'] : null;
    $currentliquidlevel = isset($decoded_data['currentliquidlevel']) ? (int)$decoded_data['currentliquidlevel'] : null;
    $wateringstatus = isset($decoded_data['wateringstatus']) ? (int)$decoded_data['wateringstatus'] : null;
    $wateringFlag = isset($decoded_data['wateringFlag']) ? (int)$decoded_data['wateringFlag'] : null;
    
    // Check if continuous, if not it was an pump event
    $updateType = isset($decoded_data['updateType']) ? $decoded_data['updateType'] : 'event';

    if (!$liquidsensorID || !$userID) {
        sendResponse(false, 'liquidsensorID, and userID are required');
    }

    // --- Validation Section ---
    $check_userStmt = $conn->prepare('SELECT userID FROM users WHERE userID = ?');
    $check_userStmt->bind_param('i', $userID);
    $check_userStmt->execute();
    if ($check_userStmt->get_result()->num_rows === 0) {
        $check_userStmt->close();
        sendResponse(false, 'User ID ' . $userID . ' does not exist');
    }
    $check_userStmt->close();

    $check_tankStmt = $conn->prepare('SELECT liquidsensorID FROM liquidsensorinfo WHERE liquidsensorID = ?');
    $check_tankStmt->bind_param('i', $liquidsensorID);
    $check_tankStmt->execute();
    if ($check_tankStmt->get_result()->num_rows === 0) {
        $check_tankStmt->close();
        sendResponse(false, 'Tank ID ' . $liquidsensorID . ' does not exist');
    }
    $check_tankStmt->close();

    $dateTime = date('Y-m-d H:i:s');

    // if continuous insert data to liquidlevelsensor tablle
    $stmtLevel = $conn->prepare('INSERT INTO liquidlevelsensor (userID, liquidsensorID, currentliquidlevel, dateandtime) VALUES (?, ?, ?, NOW())');
    $stmtLevel->bind_param('iii', $userID, $liquidsensorID, $currentliquidlevel);
    $stmtLevel->execute();
    $stmtLevel->close();

    // if event insert data to tankpumpevent
    if ($updateType === 'event') {
        $stmt = $conn->prepare('INSERT INTO tankpumpevent (userID, liquidsensorID, wateringstatus, wateringFlag, wateringvolume, dateandtime) VALUES (?, ?, ?, ?, ?, NOW());');
        $stmt->bind_param('iiiii', 
            $userID,
            $liquidsensorID,
            $wateringstatus,
            $wateringFlag,
            $currentliquidlevel
        );

        if ($stmt->execute()) {
            $insertId = $conn->insert_id;
            sendResponse(true, 'Sensor data received and stored successfully', [
                'id' => $insertId,
                'user'=> $userID,
                'tank'=> $liquidsensorID,
                'timestamp'=> $dateTime,
                'values'=> [
                    'wStatus'=>$wateringstatus,
                    'wFlag'=>$wateringFlag,
                    'volume'=>$currentliquidlevel
                ]
            ]);
        } else {
            sendResponse(false, 'Failed to store data: ' . $conn->error);
        }
        $stmt->close();
    }

    // If it was just a continuous update, send a simple success
    sendResponse(true, 'Level updated successfully');
}
sendResponse(false, 'Only POST requests are accepted for sensor data');
?>