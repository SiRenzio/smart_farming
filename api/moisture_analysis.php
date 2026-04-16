<?php
require_once __DIR__ . '/../db.php';

function process_sensor_job($conn, $jobFile, $data) {
    // Only wait 120 seconds if the job was triggered by watering
    if(isset($data['triggeredBy']) && $data['triggeredBy'] === "watering"){
        if(time() - $data['startTime'] < 120){
            return false;
        }
    }

    $soilSensorID = $data['soilSensorID'];

    //sensor name
    $sNameQuery = $conn->prepare("SELECT sensorName FROM sensorinfo WHERE soilSensorID = ?");
    $sNameQuery->bind_param("i", $soilSensorID);
    $sNameQuery->execute();
    $sensorRow = $sNameQuery->get_result()->fetch_assoc();
    $sensorName = $sensorRow ? $sensorRow['sensorName'] : "Unknown Sensor";
    $sNameQuery->close();

    // Fetch userID for the sensor to find another connected sensor
    $userQuery = $conn->prepare("SELECT userID FROM deployment WHERE soilSensorID = ? LIMIT 1");
    $userQuery->bind_param("i", $soilSensorID);
    $userQuery->execute();
    $userResult = $userQuery->get_result()->fetch_assoc();
    $userQuery->close();

    // FIX: Scope the baseline check to the user so you don't wipe the whole table
    $existingQuery = $conn->prepare("
        SELECT sms.soilSensorID 
        FROM soilmoisture_samples sms
        JOIN deployment d ON sms.soilSensorID = d.soilSensorID
        WHERE sms.is_baseline = 1 AND d.userID = ?
        LIMIT 1
    ");
    $existingQuery->bind_param("i", $userResult['userID']);
    $existingQuery->execute();
    $existingBaseline = $existingQuery->get_result()->fetch_assoc();
    $existingQuery->close();

    if($existingBaseline && $existingBaseline['soilSensorID'] != $soilSensorID){
        // FIX: Only delete baselines for sensors belonging to this specific user
        $wipeQuery = $conn->prepare("
            DELETE sms FROM soilmoisture_samples sms
            JOIN deployment d ON sms.soilSensorID = d.soilSensorID
            WHERE d.userID = ?
        ");
        $wipeQuery->bind_param("i", $userResult['userID']);
        $wipeQuery->execute();
        $wipeQuery->close();
        error_log("Primary sensor changed for user $userResult[userID]. Baseline reset.");
    }

    $targetTime = isset($data['triggeredBy']) && $data['triggeredBy'] === "watering" 
        ? date('Y-m-d H:i:s', $data['startTime'] + 120) 
        : date('Y-m-d H:i:s', $data['startTime']);

    // Check primary status and connectivity of the sensor
    $statusQuery = $conn->prepare("SELECT isConnected, isPrimary FROM deployment WHERE soilSensorID = ? LIMIT 1");
    $statusQuery->bind_param("i", $soilSensorID);
    $statusQuery->execute();
    $sensorStatus = $statusQuery->get_result()->fetch_assoc();
    $statusQuery->close();

    // If the sensor was removed, disconnected, or is no longer primary
    if (!$sensorStatus || $sensorStatus['isPrimary'] == 0 || $sensorStatus['isConnected'] == 0) {
        
        $clearQuery = $conn->prepare("DELETE FROM soilmoisture_samples WHERE soilSensorID = ?");
        $clearQuery->bind_param("i", $soilSensorID);
        $clearQuery->execute();
        $clearQuery->close();

        $notif = (!$sensorStatus || $sensorStatus['isConnected'] == 0) 
            ? "$sensorName disconnected. Monitoring stopped." 
            : "$sensorName is no longer the primary sensor.";
            
        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();
        $stmt->close();

        // FIX: Ensure file exists before unlinking to prevent PHP warnings
        if (file_exists($jobFile)) {
            unlink($jobFile);
        }
        
        return false;
    }

    // Check current baseline count and average
    $checkQuery = $conn->prepare("SELECT COUNT(*) as total, AVG(SoilMois) as avgVal FROM soilmoisture_samples WHERE soilSensorID = ?");
    $checkQuery->bind_param("i", $soilSensorID);
    $checkQuery->execute();
    $sampleStats = $checkQuery->get_result()->fetch_assoc();
    $checkQuery->close();
    
    $count = $sampleStats['total'];
    $average = $sampleStats['avgVal'];

    // Capture baseline data if we have less than 20 samples
    if($count < 20){
        $query = $conn->prepare("
            SELECT SoilMois FROM sensordata 
            WHERE soilSensorID=? AND DateTime>=? 
            ORDER BY DateTime DESC LIMIT 20
        ");
        $query->bind_param("is", $soilSensorID, $targetTime);
        $query->execute();
        $result = $query->get_result();

        if($result->num_rows < 20) {
            $query->close();
            return false;
        }

        $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 1)");
        while($row = $result->fetch_assoc()){
            $insert->bind_param("id", $soilSensorID, $row['SoilMois']);
            $insert->execute();
        }
        $insert->close();
        $query->close();
        
        $data['initialized'] = true;
        file_put_contents($jobFile, json_encode($data));
        return true;
    }

    // Monitoring and deviation check
    $latestQuery = $conn->prepare("SELECT SoilMois FROM sensordata WHERE soilSensorID=? ORDER BY DateTime DESC LIMIT 1");
    $latestQuery->bind_param("i", $soilSensorID);
    $latestQuery->execute();
    $latestData = $latestQuery->get_result()->fetch_assoc();
    $latestQuery->close();

    if(!$latestData || !$average) return false;

    $latest = $latestData['SoilMois'];
    $deviation = (($latest - $average) / $average) * 100;

    if(abs($deviation) > 20){
        $averageRounded = round($average, 2);
        $deviationRounded = round($deviation, 2);


        $notif = "$sensorName abnormal moisture: $latest (avg $averageRounded) | Deviation: $deviationRounded%";

        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();
        $stmt->close();
        
        // Find another connected sensor
        $nextStmt = $conn->prepare("
            SELECT soilSensorID 
            FROM deployment 
            WHERE userID = ? AND isConnected = 1 AND soilSensorID != ? 
            ORDER BY deploymentID ASC LIMIT 1
        ");
        $nextStmt->bind_param("ii", $userResult['userID'], $soilSensorID);
        $nextStmt->execute();
        $nextResult = $nextStmt->get_result();
        
        if ($nextResult->num_rows > 0) {
            $nextSensor = $nextResult->fetch_assoc();
            $newPrimaryID = $nextSensor['soilSensorID'];
            
            // Demote the old sensor
            $demoteStmt = $conn->prepare("UPDATE deployment SET isPrimary = 0 WHERE soilSensorID = ?");
            $demoteStmt->bind_param("i", $soilSensorID);
            $demoteStmt->execute();
            $demoteStmt->close();

            // Promote the new sensor
            $promoteStmt = $conn->prepare("UPDATE deployment SET isPrimary = 1 WHERE soilSensorID = ?");
            $promoteStmt->bind_param("i", $newPrimaryID);
            $promoteStmt->execute();
            $promoteStmt->close();

            $jobPath = __DIR__ . "/../moisture_jobs/job_$newPrimaryID.json";
            if(file_exists($jobPath)){
                unlink($jobPath);
            }

            file_put_contents($jobPath, json_encode([
                "soilSensorID" => $newPrimaryID,
                "startTime"    => time(),
                "triggeredBy"  => "primary_switch"
            ]));
        }
        $nextStmt->close();
    } else {
        // Deviation is safe
        $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 0)");
        $insert->bind_param("id", $soilSensorID, $latest);
        $insert->execute();
        $insert->close();
    }

    return true;
}

// Background worker
while(true){
    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job){
        $fileContents = file_get_contents($job);
        $data = json_decode($fileContents, true);
        
        // Skip invalid JSON
        if(!$data) continue; 
        
        process_sensor_job($conn, $job, $data);
    }

    sleep(5);
}