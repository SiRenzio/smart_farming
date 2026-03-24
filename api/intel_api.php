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

$isAnyActive = false;

$isPumpRunning = [
    'tank1' => false,
    'tank2' => false,
    'tank3' => false
];

$checkEvents = [
    'tank1' => $checkPumpEvent1,
    'tank2' => $checkPumpEvent2,
    'tank3' => $checkPumpEvent3
];

foreach ($checkEvents as $tank => $event) {
    if ($event) {

        $wateringFlag = $event['wateringFlag'] ?? -1;
        $wateringStatus = $event['wateringstatus'] ?? -1;
        $active = $event['isActive'] ?? 0;

        if ($wateringStatus === 0 && ($wateringFlag == 0 || $wateringFlag === 1)) {
            $isPumpRunning[$tank] = true;
        }
        if ($active == 1) {
            $isAnyActive = true;
        }
    }
}

$isPumping = $isPumpRunning;
$isActive = $isAnyActive;



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
                if ($isActive) {
                    sendResponse(false, 'Another pump is currently active', 'none', 0, $conn);
                }

                if(!empty($isPumping['tank1'])){
                    sendResponse(false,'Pump currently running','none',0,$conn);
                }
                else{
                    $command = "trig_tsl1";
                    sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                }

            }
            else {

                if ($fertCount === 1) {

                    if (strtolower($fertilizers[0]['fertilizerName']) === 'nitrabor') {
                        if ($isActive) {
                            sendResponse(false, 'Another pump is currently active', 'none', 0, $conn);
                        }

                        if(!empty($isPumping['tank2'])){
                            sendResponse(false,'Pump currently running','none',0,$conn);
                        }
                        else{
                            $command = "trig_tsl2";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                        }
                    }
                    else if (strtolower($fertilizers[0]['fertilizerName']) === 'unik16') {
                        if ($isActive) {
                            sendResponse(false, 'Another pump is currently active', 'none', 0, $conn);
                        }

                        if(!empty($isPumping['tank3'])){
                            sendResponse(false,'Pump currently running','none',0,$conn);
                        }
                        else{
                            $command = "trig_tsl3";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                        }

                    }
                    else if (strtolower($fertilizers[0]['fertilizerName']) === 'winner') {
                        if ($isActive) {
                            sendResponse(false, 'Another pump is currently active', 'none', 0, $conn);
                        }

                        if(!empty($isPumping['tank3'])){
                            sendResponse(false,'Pump currently running','none',0,$conn);
                        }
                        else{
                            $command = "trig_tsl3";
                            sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
                        }
                    }

                }
                else {
                    if ($isActive) {
                        sendResponse(false, 'Another pump is currently active', 'none', 0, $conn);
                    }

                    $tank2Flag = $checkPumpEvent2['fertFlag'] ?? 0;
                    $tank3Flag = $checkPumpEvent3['fertFlag'] ?? 0;

                    /* ================= ALTERNATING LOGIC ================= */

                    if ($tank2Flag == 0 && $tank3Flag == 1) {
                        if (!empty($isPumping['tank2']) ) {
                            sendResponse(false,'Tank 2 already running','none',0,$conn);
                        }

                        $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");
                        $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1");

                        $command = "trig_tsl2";
                        sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);

                    }
                    else if ($tank2Flag == 1 && $tank3Flag == 0) {
                        if (!empty($isPumping['tank3'])) {
                            sendResponse(false,'Tank 3 already running','none',0,$conn);
                        }

                        $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");
                        $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1");

                        $command = "trig_tsl3";

                        sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);

                    }
                    else if ($tank2Flag == 0 && $tank3Flag == 0) {
                        if (!empty($isPumping['tank2'])) {
                            sendResponse(false,'Tank 2 already running','none',0,$conn);
                        }

                        $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");

                        $command = "trig_tsl2";
                        sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);

                    }
                }
            }
        }
        else {
            if ($isActive) {
                sendResponse(false, 'Another pump is currently active', 'none', 0, $conn);
            }
            if(!empty($isPumping['tank1'])){
                sendResponse(false,'Pump currently running','none',0,$conn);
            }
            else{
                $command = "trig_tsl1";
                sendResponse(true,'Pump turned on',$command,$totalLiquidVolume,$conn);
            }
        }
    }
    else {

        sendResponse(false,'No action required based on current parameters','none',0,$conn);

    }
}

/* ================= DEFAULT RESPONSE ================= */

sendResponse(false,'No action required based on current parameters','none',0,$conn);

?>
