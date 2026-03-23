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

    // reset for isActive
    if ($updateType === 'reset') {
        $resetQuery = "UPDATE tankpumpevent SET isActive = 0, fertFlag = 0 WHERE liquidsensorID = ? ORDER BY tankPumpEventID DESC LIMIT 1";
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
    $isActiveFlag           = isset($decoded_data['isActive']) ? (int)$decoded_data['isActive'] : null;
    $wateringvolume     = isset($decoded_data['wateringvolume']) ? (int)$decoded_data['wateringvolume'] : null;

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
            (liquidsensorID, wateringstatus, wateringFlag, waterlevel, wateringvolume, dateandtime) 
            VALUES (?, ?, ?, ?, ?, NOW())'
        );

        $stmt->bind_param(
            'iiiii',
            $liquidsensorID,
            $wateringstatus,
            $wateringFlag,
            $currentliquidlevel,
            $wateringvolume
        );

        if ($stmt->execute()) {

            $notifMessage = "";

            
            if ($wateringFlag === 0 && $wateringstatus === 0) {
                $notifMessage = "[Tank $liquidsensorID]: Water tank is now FULL, system HOLD WATERING waiting to mix the solution";
                
                $getlowquery = "SELECT waterlevel FROM tankpumpevent WHERE liquidsensorID = ? AND wateringFlag =1 ORDER BY dateandtime DESC LIMIT 1";
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
                        
                        //Fertilizer in grams and cups
                        $fertInGrams = $liters * $fertilizerAmount;
                        $fertInCup = round($fertInGrams / 150, 2);

                        //Alternatives
                        $alternativeAmount = $liters * 2.5; // 2.5ml/L of fermented fruit juice and fish amino acid
                        $fishaminoAmount = round($alternativeAmount / 150, 2); // for fish amino acid
                        $fermentfruitAmount = round($alternativeAmount / 150, 2); // for fermented fruit juice

                        // for fertilizer amount
                        $fertALert = "[Tank $liquidsensorID]: Tank is filled $liters liters of water. Mix in $fertInCup sardine-can scoops of $fertilizerName [$fertilizerAmount g/L]. [150grams sardine-can]";
                        $fertSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                        $alertStmt = $conn->prepare($fertSql);
                        $alertStmt->bind_param("s", $fertALert);
                        $alertStmt->execute();
                        $alertStmt->close();

                        // for alternative amount notification
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

    if ($updateType === 'watering') {
        
        $lastFert = $conn->query("SELECT fertFlag FROM tankpumpevent WHERE liquidsensorID = $liquidsensorID ORDER BY tankPumpEventID DESC LIMIT 1")->fetch_assoc();
        $currentFertFlag = $lastFert['fertFlag'] ?? 0;

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


    if ($updateType === 'handshake') {
        sendResponse(true, 'Handshake successful', [
            'liquidsensorID' => $liquidsensorID
        ]);
    }

    sendResponse(false, 'Invalid updateType');
}

sendResponse(false, 'Only POST requests are accepted for sensor data');
?>