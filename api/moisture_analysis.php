<?php
require_once __DIR__ . '/../db.php';

function process_sensor_job($conn, $jobFile, $data) {
    // Wait for the 120-second startup window
    if(isset($data['startTime']) && (time() - $data['startTime'] < 120)){
        return false; 
    }

    $soilSensorID = $data['soilSensorID'];
    $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

    // Check primary status and connectivity of the sensor before doing anything else
    $statusQuery = $conn->prepare("SELECT isConnected, isPrimary FROM deployment WHERE soilSensorID = ? LIMIT 1");
    $statusQuery->bind_param("i", $soilSensorID);
    $statusQuery->execute();
    $sensorStatus = $statusQuery->get_result()->fetch_assoc();

    // If the sensor was removed, disconnected, or is no longer primary
    if (!$sensorStatus || $sensorStatus['isPrimary'] == 0 || $sensorStatus['isConnected'] == 0) {
        
        // Wipe all baseline data for this sensor
        $clearQuery = $conn->prepare("DELETE FROM soilmoisture_samples WHERE soilSensorID = ?");
        $clearQuery->bind_param("i", $soilSensorID);
        $clearQuery->execute();
        $clearQuery->close();

        // Log a notification about the sensor status change
        $notif = "Sensor $soilSensorID is no longer active/primary. Baseline data cleared.";
        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();

        // Delete the job file so we stop monitoring this dead sensor
        unlink($jobFile);
        
        return false; // Exit immediately
    }

    // Check current baseline count and average
    $checkQuery = $conn->prepare("SELECT COUNT(*) as total, AVG(SoilMois) as avgVal FROM soilmoisture_samples WHERE soilSensorID = ?");
    $checkQuery->bind_param("i", $soilSensorID);
    $checkQuery->execute();
    $sampleStats = $checkQuery->get_result()->fetch_assoc();
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

        if($result->num_rows < 20) return false;

        while($row = $result->fetch_assoc()){
            $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 1)");
            $insert->bind_param("id", $soilSensorID, $row['SoilMois']);
            $insert->execute();
        }
        
        $data['initialized'] = true;
        file_put_contents($jobFile, json_encode($data));
        return true;
    }

    // Monitoring and deviation check
    $latestQuery = $conn->prepare("SELECT SoilMois FROM sensordata WHERE soilSensorID=? ORDER BY DateTime DESC LIMIT 1");
    $latestQuery->bind_param("i", $soilSensorID);
    $latestQuery->execute();
    $latestData = $latestQuery->get_result()->fetch_assoc();

    if(!$latestData || !$average) return false;

    $latest = $latestData['SoilMois'];
    $deviation = (($latest - $average) / $average) * 100;

    if(abs($deviation) > 20){
        // Abnormal Deviation: Log it and let the external system handle the failover
        $averageRounded = round($average, 2);
        $deviationRounded = round($deviation, 2);
        $notif = "Sensor $soilSensorID abnormal moisture: $latest (avg $averageRounded) | Deviation: $deviationRounded%";

        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();
        
        // NOTE: We do not switch the sensor or delete data here anymore.
        // We assume your external hardware/system will see this abnormality, 
        // update the deployment table to isPrimary=0, and the NEXT loop will catch it at step 1.
    } else {
        // Deviation is safe
        $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 0)");
        $insert->bind_param("id", $soilSensorID, $latest);
        $insert->execute();
    }

    return true;
}

// Background worker
while(true){
    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job){
        $data = json_decode(file_get_contents($job), true);
        if(!$data) continue;
        
        // Process everything in one cohesive step
        process_sensor_job($conn, $job, $data);
    }

    sleep(5);
}