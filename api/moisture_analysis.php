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

    // Scope the baseline check to the user so you don't wipe the whole table
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

    // Check primary status, connectivity, and current nutrition stage
    $statusQuery = $conn->prepare("SELECT isConnected, isPrimary, nutritionID FROM deployment WHERE soilSensorID = ? LIMIT 1");
    $statusQuery->bind_param("i", $soilSensorID);
    $statusQuery->execute();
    $sensorStatus = $statusQuery->get_result()->fetch_assoc();
    $statusQuery->close();

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

        if (file_exists($jobFile)) {
            unlink($jobFile);
        }
        
        return false;
    }

    $currentNutritionID = $sensorStatus['nutritionID'];

    if (!array_key_exists('nutritionID', $data)) {
        $data['nutritionID'] = $currentNutritionID;
        file_put_contents($jobFile, json_encode($data));
    } 
    else if ($data['nutritionID'] != $currentNutritionID) {
        
        // If the old state in the JSON was null or empty, this is an initial assignment, not a change.
        if ($data['nutritionID'] === null || $data['nutritionID'] === "") {
            $data['nutritionID'] = $currentNutritionID;
            file_put_contents($jobFile, json_encode($data));
        } else {
            // It is a genuine transition between two different growth stages
            $notif = "$sensorName entered a new growth stage. Monitoring paused until next watering cycle to record accurate baselines.";
            $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
            $stmt->bind_param("s", $notif);
            $stmt->execute();
            $stmt->close();

            // Update the job file with the new state flag
            $data['nutritionID'] = $currentNutritionID;
            $data['initialized'] = false;
            $data['waitingForWatering'] = true; 
            file_put_contents($jobFile, json_encode($data));

            return false;
        }
    }

    if (isset($data['waitingForWatering']) && $data['waitingForWatering'] === true) {
        if (!isset($data['triggeredBy']) || $data['triggeredBy'] !== "watering") {
            // Still waiting for the water pump. Abort processing.
            return false; 
        } else {
            // Watering HAS occurred! Wipe the old data now.
            $wipeQuery = $conn->prepare("DELETE FROM soilmoisture_samples WHERE soilSensorID = ?");
            $wipeQuery->bind_param("i", $soilSensorID);
            $wipeQuery->execute();
            $wipeQuery->close();

            unset($data['waitingForWatering']); 
            file_put_contents($jobFile, json_encode($data)); 
        }
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
        // Only restrict by Target Time if we are actively collecting a post-watering baseline
        if (isset($data['triggeredBy']) && $data['triggeredBy'] === "watering") {
            $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);
            $query = $conn->prepare("SELECT SoilMois FROM sensordata WHERE soilSensorID=? AND DateTime>=? ORDER BY DateTime DESC LIMIT 20");
            $query->bind_param("is", $soilSensorID, $targetTime);
        } else {
            $query = $conn->prepare("SELECT SoilMois FROM sensordata WHERE soilSensorID=? ORDER BY DateTime DESC LIMIT 20");
            $query->bind_param("i", $soilSensorID);
        }

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
        
        // Baseline established. Clear the holding flags.
        $data['initialized'] = true;
        if (isset($data['waitingForWatering'])) {
            unset($data['waitingForWatering']);
            
            // Clean up the watering trigger so it doesn't accidentally fire again
            if(isset($data['triggeredBy']) && $data['triggeredBy'] === "watering"){
                unset($data['triggeredBy']);
            }
        }

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

    // Make sure we haven't already processed this exact reading on a previous 5-second loop
    $latestTime = strtotime($latestData['DateTime']);
    if (isset($data['lastProcessedTime']) && $data['lastProcessedTime'] >= $latestTime) {
        return true; // We already have this reading. Do nothing.
    }

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

    // Save the timestamp of this reading so we don't duplicate it in 5 seconds
    $data['lastProcessedTime'] = $latestTime;
    file_put_contents($jobFile, json_encode($data));

    return true;
}

// Background worker
while(true){
    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job){
        $fileContents = file_get_contents($job);
        $data = json_decode($fileContents, true);
        
        if(!$data) continue; 
        
        process_sensor_job($conn, $job, $data);
    }

    sleep(5);
}