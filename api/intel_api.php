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

        $conn->query("UPDATE tankpumpevent SET isActive = 1 WHERE liquidsensorID = 1 ORDER BY tankPumpEventID DESC LIMIT 1");
        $conn->query("UPDATE tankpumpevent SET isActive = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1");
        $conn->query("UPDATE tankpumpevent SET isActive = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1");

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

$isPumpRunning = false;
$isActive = 0;

if ($checkPumpEvent1) {

    $wateringFlag   = $checkPumpEvent1['wateringFlag'] ?? -1;
    $wateringStatus = $checkPumpEvent1['wateringstatus'] ?? -1;
    
    $isActive       = $checkPumpEvent1['isActive'];
    
    if ($wateringFlag >= 0 || $wateringStatus >= 0) {
        $isPumpRunning = true;
    }
}

/* ================= PROCESS SENSOR DATA ================= */

if ($row = $result->fetch_assoc()) {
    if ($row['SoilT'] < 30) {
        if ($row['SoilMois'] < $plantParams['meanMoistureThreshold']) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {

                    /* ================= TANK 1 COMMAND ================= */

                    // FIXED: This will now properly block if $isActive is 1
                    if (!$isPumpRunning || !$isActive == 1) {
                        $command = "trig_tsl1";
                        $liquidVolume = $plantParams['liquidVolume'] ?? 0;
                        sendResponse(true,
                            'Pump turned on',
                            $command,
                            $liquidVolume,
                            $conn
                        );
                    }
                }
                else {
                    $fertCount = $conn->query("SELECT COUNT(DISTINCT liquidsensorID) AS fertCount FROM `fertilizer` WHERE nutritionID = " . $plantParams['nutritionID'])->fetch_assoc()['fertCount'] ?? 0;  
                    if ($fertCount === 1) { // Check if only 1 fertilizer is needed
                        if (!$isPumpRunning || !$isActive == 1) {
                            // command for fertilizer here (calcium-based fertilizer | nitrabor OR phosphorus-based fertilizer | UNIK16/WINNER) depending on which one is needed
                            
                        }
                    }
                    else {
                        if ($checkPumpEvent2['fertFlag'] === 0 && $checkPumpEvent3['fertFlag'] === 1) { // If tank 3 is active and tank 2 is not alternate to tank 2 (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 2 fertilizer (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn off tank 3 fertilizer (phosphorus-based fertilizer | UNIK16/WINNER)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 2 (calcium-based fertilizer | nitrabor) here 
                            }
                        }
                        else if ($checkPumpEvent2['fertFlag'] === 1 && $checkPumpEvent3['fertFlag'] === 0) { // If tank 2 is active and tank 3 is not alternate to tank 3 (phosphorus-based fertilizer | UNIK16/WINNER)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn off tank 2 fertilizer (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 3 fertilizer (phosphorus-based fertilizer | UNIK16/WINNER)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 3 (phosphorus-based fertilizer | UNIK16/WINNER) here
                            }
                        }
                        else if ($checkPumpEvent2['fertFlag'] === 0 && $checkPumpEvent3['fertFlag'] === 0) { // If both tanks are inactive turn on tank 2 first (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 2 fertilizer (calcium-based fertilizer | nitrabor)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 2 (calcium-based fertilizer | nitrabor) here
                            }
                        }
                    }
                }
            } 
            else {
                if (!$isPumpRunning || !$isActive == 1) {
                    $command = "trig_tsl1";
                    $liquidVolume = $plantParams['liquidVolume'] ?? 0;
                    sendResponse(true,
                        'Pump turned on',
                        $command,
                        $liquidVolume,
                        $conn
                    );
                }
            }
        }
        else {
            return;
        }
    }

    else if ($row['SoilT'] >= 31 && $row['SoilT'] <= 34) {
        if ($row['SoilMois'] < ($plantParams['meanMoistureThreshold'] + 5)) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // Check if pump motor is on
                    if (!$isPumpRunning || !$isActive == 1) {
                        $command = "trig_tsl1";
                        $liquidVolume = $plantParams['liquidVolume'] ?? 0;
                        sendResponse(true,
                            'Pump turned on',
                            $command,
                            $liquidVolume,
                            $conn
                        );
                    }
                }
                else {
                    $fertCount = $conn->query("SELECT COUNT(DISTINCT liquidsensorID) AS fertCount FROM `fertilizer` WHERE nutritionID = " . $plantParams['nutritionID'])->fetch_assoc()['fertCount'] ?? 0;  
                    if ($fertCount === 1) { // Check if only 1 fertilizer is needed
                        if (!$isPumpRunning || !$isActive == 1) {
                            // command for fertilizer here (calcium-based fertilizer | nitrabor OR phosphorus-based fertilizer | UNIK16/WINNER) depending on which one is needed
                        }
                    }
                    else {
                        if ($checkPumpEvent2['fertFlag'] === 0 && $checkPumpEvent3['fertFlag'] === 1) { // If tank 3 is active and tank 2 is not alternate to tank 2 (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 2 fertilizer (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn off tank 3 fertilizer (phosphorus-based fertilizer | UNIK16/WINNER)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 2 (calcium-based fertilizer | nitrabor) here
                            }
                        }
                        else if ($checkPumpEvent2['fertFlag'] === 1 && $checkPumpEvent3['fertFlag'] === 0) { // If tank 2 is active and tank 3 is not alternate to tank 3 (phosphorus-based fertilizer | UNIK16/WINNER)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn off tank 2 fertilizer (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 3 fertilizer (phosphorus-based fertilizer | UNIK16/WINNER)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 3 (phosphorus-based fertilizer | UNIK16/WINNER) here
                            }
                        }
                        else if ($checkPumpEvent2['fertFlag'] === 0 && $checkPumpEvent3['fertFlag'] === 0) { // If both tanks are inactive turn on tank 2 first (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 2 fertilizer (calcium-based fertilizer | nitrabor)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 2 (calcium-based fertilizer | nitrabor) here
                            }
                        }
                    }
                }
            }
            else {
                if (!$isPumpRunning || !$isActive == 1) {
                    $command = "trig_tsl1";
                    $liquidVolume = $plantParams['liquidVolume'] ?? 0;
                    sendResponse(true,
                        'Pump turned on',
                        $command,
                        $liquidVolume,
                        $conn
                    );
                }
            }
        }
        else {
            return;
        }
    }

    else if ($row['SoilT'] > 34) {
        if ($row['SoilMois'] < ($plantParams['meanMoistureThreshold'] + 10)) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // Check if pump motor is on
                    if (!$isPumpRunning || !$isActive == 1) {
                        $command = "trig_tsl1";
                        $liquidVolume = $plantParams['liquidVolume'] ?? 0;
                        sendResponse(true,
                            'Pump turned on',
                            $command,
                            $liquidVolume,
                            $conn
                        );
                    }
                }
                else {
                    $fertCount = $conn->query("SELECT COUNT(DISTINCT liquidsensorID) AS fertCount FROM `fertilizer` WHERE nutritionID = " . $plantParams['nutritionID'])->fetch_assoc()['fertCount'] ?? 0;  
                    if ($fertCount === 1) { // Check if only 1 fertilizer is needed
                        if (!$isPumpRunning || !$isActive == 1) {
                            // command for fertilizer here (calcium-based fertilizer | nitrabor OR phosphorus-based fertilizer | UNIK16/WINNER) depending on which one is needed
                        }
                    }
                    else {
                        if ($checkPumpEvent2['fertFlag'] === 0 && $checkPumpEvent3['fertFlag'] === 1) { // If tank 3 is active and tank 2 is not alternate to tank 2 (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 2 fertilizer (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn off tank 3 fertilizer (phosphorus-based fertilizer | UNIK16/WINNER)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 2 (calcium-based fertilizer | nitrabor) here
                            }
                        }
                        else if ($checkPumpEvent2['fertFlag'] === 1 && $checkPumpEvent3['fertFlag'] === 0) { // If tank 2 is active and tank 3 is not alternate to tank 3 (phosphorus-based fertilizer | UNIK16/WINNER)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 0 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn off tank 2 fertilizer (calcium-based fertilizer | nitrabor)
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 3 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 3 fertilizer (phosphorus-based fertilizer | UNIK16/WINNER)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 3 (phosphorus-based fertilizer | UNIK16/WINNER) here
                            }
                        }
                        else if ($checkPumpEvent2['fertFlag'] === 0 && $checkPumpEvent3['fertFlag'] === 0) { // If both tanks are inactive turn on tank 2 first
                            $conn->query("UPDATE tankpumpevent SET fertFlag = 1 WHERE liquidsensorID = 2 ORDER BY tankPumpEventID DESC LIMIT 1"); // Turn on tank 2 fertilizer (calcium-based fertilizer | nitrabor)

                            if (!$isPumpRunning || !$isActive == 1) {
                                // command for tank 2 (calcium-based fertilizer | nitrabor) here
                            }
                        }
                    }
                }
            }
            else {
                if (!$isPumpRunning || !$isActive == 1) {
                    $command = "trig_tsl1";
                    $liquidVolume = $plantParams['liquidVolume'] ?? 0;
                    sendResponse(true,
                        'Pump turned on',
                        $command,
                        $liquidVolume,
                        $conn
                    );
                }
            }
        }
        else {
            return;
        }
    }
    else {
        return;
    }
}

/* ================= DEFAULT RESPONSE ================= */

sendResponse(
    false,
    'No action required based on current parameters',
    'none',
    0,
    $conn
);
?>