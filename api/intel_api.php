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

        $conn->query("
            UPDATE tankpumpevent
            SET isActive = 1
            WHERE liquidsensorID = 1
            ORDER BY tankPumpEventID DESC
            LIMIT 1
        ");

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
/* ================= RECEIVE RESET CMD FROM ESP32 ================= */




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

// FIXED: Added isActive to the SELECT query
$checkPumEvent = $conn->query("
    SELECT wateringstatus, wateringFlag, isActive
    FROM tankpumpevent
    WHERE liquidsensorID = 1
    ORDER BY tankPumpEventID DESC
    LIMIT 1
")->fetch_assoc();

$isPumpRunning = false;
$isActive = 0;

if ($checkPumEvent) {

    $wateringFlag   = $checkPumEvent['wateringFlag'] ?? -1;
    $wateringStatus = $checkPumEvent['wateringstatus'] ?? -1;
    
    // FIXED: Actually read the database state into the variable
    $isActive       = $checkPumEvent['isActive'];
    
    // Pump is busy if flag or status is active (>= 0)
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
                    if ($isPumpRunning || $isActive == 1) {

                        

                    } else {
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

                /* ================= FUTURE FERTILIZER LOGIC ================= */

                // tank 2 | nitrabor
                // trig_tsl2

                // tank 3 | UNIK16 / WINNER
                // trig_tsl3

            } else {
                if ($isPumpRunning || $isActive == 1) {
                    
                } else {
                    
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
    }

    else if ($row['SoilT'] >= 31 && $row['SoilT'] <= 34) {
        if ($row['SoilMois'] < ($plantParams['meanMoistureThreshold'] + 5)) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // future logic

                }
            }
        }
    }

    else if ($row['SoilT'] > 34) {
        if ($row['SoilMois'] < ($plantParams['meanMoistureThreshold'] + 10)) {
            if ($row['SoilN'] < $plantParams['soilN']) {
                if ($row['SoilEC'] > $plantParams['soilEC']) {
                    // future logic

                }
            }
        }
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