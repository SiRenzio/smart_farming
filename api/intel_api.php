<?php
require_once '../db.php';
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST, GET');
header('Access-Control-Allow-Headers: Content-Type');

function sendResponse($success, $message, $command, $liquidVolume, $conn) {

    // Only activate pump event if a real command is triggered
    if ($command !== "none") {
        if ($command === "trig_tsl1") {
            $conn->query("UPDATE tankpumpevent SET isActive = 1 WHERE liquidsensorID = 1 ORDER BY tankPumpEventID DESC LIMIT 1");
        } elseif ($command === "trig_tsl2") {
            $conn->query("UPDATE tankpumpevent SET isActive = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");
        } elseif ($command === "trig_tsl3") {
            $conn->query("UPDATE tankpumpevent SET isActive = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1");
        }
    }

    echo json_encode([
        'success' => $success,
        'message' => $message,
        'command' => $command,
        'liquidVolume' => $liquidVolume,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

/* ================= FETCH LATEST SENSOR DATA ================= */

$sensorDataID = $_GET['sensorDataID'] ?? null;

if ($sensorDataID) {
    $stmt = $conn->prepare("
        SELECT * FROM sensordata
        WHERE SensorDataID > ?
        ORDER BY DateTime DESC LIMIT 1
    ");
    $stmt->bind_param("s", $sensorDataID);
} else {
    $stmt = $conn->prepare("
        SELECT * FROM sensordata
        ORDER BY DateTime DESC LIMIT 1
    ");
}

$stmt->execute();
$result = $stmt->get_result();

/* ================= FETCH PLANT PARAMETERS ================= */

$plantParams = $conn->query("
    SELECT * FROM plantnutrionneed
    ORDER BY nutritionID DESC
    LIMIT 1
")->fetch_assoc();

/* ================= FETCH PUMP STATUS ================= */

// Tank 1
$checkPumpEvent1 = $conn->query("
    SELECT wateringstatus, wateringFlag, isActive
    FROM tankpumpevent
    WHERE liquidsensorID = 1
    ORDER BY tankPumpEventID DESC
    LIMIT 1
")->fetch_assoc();

// Tank 2
$checkPumpEvent2 = $conn->query("
    SELECT wateringstatus, wateringFlag, isActive, fertFlag
    FROM tankpumpevent
    WHERE liquidsensorID = 2
    ORDER BY tankPumpEventID DESC
    LIMIT 1
")->fetch_assoc();

// Tank 3
$checkPumpEvent3 = $conn->query("
    SELECT wateringstatus, wateringFlag, isActive, fertFlag
    FROM tankpumpevent
    WHERE liquidsensorID = 3
    ORDER BY tankPumpEventID DESC
    LIMIT 1
")->fetch_assoc();

$totalLiquidVolume = $plantParams['liquidVolume'] * $plantParams['numberOfPlants'];

/* ================= FETCH FERTILIZERS ================= */

$fertilizers = [];
$fertStmt = $conn->prepare("SELECT * FROM fertilizer WHERE nutritionID = ? ORDER BY fertilizerID DESC LIMIT 3");
$fertStmt->bind_param("i", $plantParams['nutritionID']);
$fertStmt->execute();
$fertResult = $fertStmt->get_result();

while ($fertRow = $fertResult->fetch_assoc()) {
    $fertilizers[] = $fertRow;
}

$fertCount = count($fertilizers);

// getting the each tank status
$isTank1Running = isset($checkPumpEvent1['wateringFlag'], $checkPumpEvent1['wateringstatus']) 
    && (int)$checkPumpEvent1['wateringstatus'] === 0 
    && ((int)$checkPumpEvent1['wateringFlag'] === 1 || (int)$checkPumpEvent1['wateringFlag'] === 0);

$isTank2Running = isset($checkPumpEvent2['wateringFlag'], $checkPumpEvent2['wateringstatus']) 
    && (int)$checkPumpEvent2['wateringstatus'] === 0 
    && ((int)$checkPumpEvent2['wateringFlag'] === 1 || (int)$checkPumpEvent2['wateringFlag'] === 0);

$isTank3Running = isset($checkPumpEvent3['wateringFlag'], $checkPumpEvent3['wateringstatus']) 
    && (int)$checkPumpEvent3['wateringstatus'] === 0 
    && ((int)$checkPumpEvent3['wateringFlag'] === 1 || (int)$checkPumpEvent3['wateringFlag'] === 0);

// Check if there's a active command

$isTank1Active = isset($checkPumpEvent1['isActive']) && (int)$checkPumpEvent1['isActive'] === 1;
$isTank2Active = isset($checkPumpEvent2['isActive']) && (int)$checkPumpEvent2['isActive'] === 1;
$isTank3Active = isset($checkPumpEvent3['isActive']) && (int)$checkPumpEvent3['isActive'] === 1;

$isAnyCommandActive = $isTank1Active || $isTank2Active || $isTank3Active;

// If a command is currently active, block the checking of condition
if ($isAnyCommandActive) {
    sendResponse(false, 'A command is currently active and pending execution. Waiting for it to process.', 'none', 0, $conn);
}

/* ================= PROCESS SENSOR DATA ================= */

if ($row = $result->fetch_assoc()) {

    if ($row['SoilT'] < 30) {
        $moistureThreshold = $plantParams['meanMoistureThreshold'];
    }
    else if ($row['SoilT'] >= 31 && $row['SoilT'] <= 34) {
        $moistureThreshold = $plantParams['meanMoistureThreshold'] + 5;
    }
    else if ($row['SoilT'] > 34) {
        $moistureThreshold = $plantParams['meanMoistureThreshold'] + 10;
    }
    else {
        sendResponse(false,'No action required based on current parameters','none',0,$conn);
    }

    if ($row['SoilMois'] < $moistureThreshold) {

        if ($row['SoilN'] < $plantParams['soilN']) {

            if ($row['SoilEC'] > $plantParams['soilEC']) {
                
                if ($isTank1Running) {
                    sendResponse(false, 'Tank 1 is already running', 'none', 0, $conn);
                } else {
                    $command = "trig_tsl1";
                    sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                }

            }
            else {

                if ($fertCount === 1) {

                    if (strtolower($fertilizers[0]['fertilizerName']) === 'nitrabor') {
                        
                        if ($isTank2Running) {
                            sendResponse(false, 'Tank 2 is already running', 'none', 0, $conn);
                        } else {
                            $command = "trig_tsl2";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                        }

                    }
                    else if (strtolower($fertilizers[0]['fertilizerName']) === 'unik16' || strtolower($fertilizers[0]['fertilizerName']) === 'winner') {
                        
                        if ($isTank3Running) {
                            sendResponse(false, 'Tank 3 is already running', 'none', 0, $conn);
                        } else {
                            $command = "trig_tsl3";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                        }

                    }

                }
                else {

                    $tank2Flag = $checkPumpEvent2['fertFlag'] ?? 0;
                    $tank3Flag = $checkPumpEvent3['fertFlag'] ?? 0;

                    /* ================= ALTERNATING LOGIC ================= */
                    if ($isTank2Running || $isTank3Running) {
                        
                        sendResponse(false, 'A fertilizer tank is already running. Waiting for it to finish before alternating.', 'none', 0, $conn);
                        
                    } else {
                        
                        if ($tank2Flag == 0 && $tank3Flag == 1) {

                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1");

                            $command = "trig_tsl2";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);

                        }
                        else if ($tank2Flag == 1 && $tank3Flag == 0) {

                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1");

                            $command = "trig_tsl3";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);

                        }
                        else if ($tank2Flag == 0 && $tank3Flag == 0) {

                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");

                            $command = "trig_tsl2";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);

                        }
                    }
                }
            }
        }
        else {
            
            if ($isTank1Running) {
                sendResponse(false, 'Tank 1 is already running', 'none', 0, $conn);
            } else {
                $command = "trig_tsl1";
                sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
            }
        }

    }
    else {

        sendResponse(false,'No action required based on current parameters','none',0,$conn);

    }
}

// FOR TESTING PURPOSES ONLY - SIMULATE A COMMAND
$input = json_decode(file_get_contents("php://input"), true);

$manualCommand = $input['command'] ?? null;
$liquidAmount = $input['liquidAmount'] ?? 0;

if ($manualCommand) {

    switch ($manualCommand) {
        case 'pump1':
            sendResponse(true, 'Manual pump 1 activated', 'trig_tsl1', $liquidAmount, $conn);
            break;

        case 'pump2':
            sendResponse(true, 'Manual pump 2 activated', 'trig_tsl2', $liquidAmount, $conn);
            break;

        case 'pump3':
            sendResponse(true, 'Manual pump 3 activated', 'trig_tsl3', $liquidAmount, $conn);
            break;

        case 'valve1':
            sendResponse(true, 'Valve 1 toggled', 'valve_1', $liquidAmount, $conn);
            break;

        case 'valve2':
            sendResponse(true, 'Valve 2 toggled', 'valve_2', $liquidAmount, $conn);
            break;

        case 'valve3':
            sendResponse(true, 'Valve 3 toggled', 'valve_3', $liquidAmount, $conn);
            break;

        case 'alternate':
            sendResponse(true, 'Alternating triggered', 'alternate', $liquidAmount, $conn);
            break;
    }
}

/* ================= DEFAULT RESPONSE ================= */

sendResponse(false,'No action required based on current parameters','none',0,$conn);
?>