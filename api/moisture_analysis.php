<?php
require_once __DIR__ . '/../db.php';

function process_sensor_job($conn, $jobFile, $data) {
    // Wait for the 120-second startup window
    if(time() - $data['startTime'] < 120){
        return false; 
    }

    $soilSensorID = $data['soilSensorID'];
    $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

    // 1. Check current baseline count and average
    $checkQuery = $conn->prepare("SELECT COUNT(*) as total, AVG(SoilMois) as avgVal FROM soilmoisture_samples WHERE soilSensorID = ?");
    $checkQuery->bind_param("i", $soilSensorID);
    $checkQuery->execute();
    $sampleStats = $checkQuery->get_result()->fetch_assoc();
    $count = $sampleStats['total'];
    $average = $sampleStats['avgVal'];

    // 2. CAPTURE BASELINE (First time or immediately after a sensor switch)
    if($count < 20){
        $query = $conn->prepare("
            SELECT SoilMois FROM sensordata 
            WHERE SoilSensorID=? AND DateTime>=? 
            ORDER BY DateTime DESC LIMIT 20
        ");
        $query->bind_param("is", $soilSensorID, $targetTime);
        $query->execute();
        $result = $query->get_result();

        if($result->num_rows < 20) return false; // Wait until we have 20 readings

        while($row = $result->fetch_assoc()){
            $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 1)");
            $insert->bind_param("id", $soilSensorID, $row['SoilMois']);
            $insert->execute();
        }
        
        $data['initialized'] = true;
        file_put_contents($jobFile, json_encode($data));
        return true;
    }

    // 3. SUBSEQUENT MONITORING
    $latestQuery = $conn->prepare("
        SELECT SoilMois FROM sensordata 
        WHERE SoilSensorID=? 
        ORDER BY DateTime DESC LIMIT 1
    ");
    $latestQuery->bind_param("i", $soilSensorID);
    $latestQuery->execute();
    $latestData = $latestQuery->get_result()->fetch_assoc();

    if(!$latestData || !$average) return false;

    $latest = $latestData['SoilMois'];
    $deviation = (($latest - $average) / $average) * 100;

    // 4. DEVIATION CHECK
    if(abs($deviation) > 20){
        // Do NOT insert into samples table. Log notification instead.
        $averageRounded = round($average, 2);
        $deviationRounded = round($deviation, 2);
        $notif = "Sensor $soilSensorID abnormal moisture: $latest (avg $averageRounded) | Deviation: $deviationRounded%";

        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();

        // Sensor Switching Logic
        $userQuery = $conn->prepare("SELECT userID FROM deployment WHERE soilSensorID = ? LIMIT 1");
        $userQuery->bind_param("i", $soilSensorID);
        $userQuery->execute();
        $userData = $userQuery->get_result()->fetch_assoc();

        if ($userData) {
            $uid = $userData['userID'];

            $backupQuery = $conn->prepare("
                SELECT soilSensorID FROM deployment 
                WHERE userID = ? AND soilSensorID != ? AND isConnected = 1 
                LIMIT 1
            ");
            $backupQuery->bind_param("ii", $uid, $soilSensorID);
            $backupQuery->execute();
            $backupData = $backupQuery->get_result()->fetch_assoc();

            if ($backupData) {
                $backupSensorID = $backupData['soilSensorID'];

                // Swap primary status in DB
                $switch = $conn->prepare("
                    UPDATE deployment 
                    SET isPrimary = CASE 
                        WHEN soilSensorID = ? THEN 0 
                        WHEN soilSensorID = ? THEN 1 
                    END 
                    WHERE soilSensorID IN (?, ?)
                ");
                $switch->bind_param("iiii", $soilSensorID, $backupSensorID, $soilSensorID, $backupSensorID);
                $switch->execute();

                // --- CRITICAL FIXES FOR JOB & BASELINE SWITCHING ---
                
                // A. Delete old samples for backup sensor so it is forced to fetch 20 fresh readings
                $clearBaseline = $conn->prepare("DELETE FROM soilmoisture_samples WHERE soilSensorID = ?");
                $clearBaseline->bind_param("i", $backupSensorID);
                $clearBaseline->execute();

                // B. Create the new job file for the new sensor
                $newJobFile = dirname($jobFile) . "/job_$backupSensorID.json";
                file_put_contents($newJobFile, json_encode([
                    "soilSensorID" => $backupSensorID,
                    "startTime" => time() // Restarts the 120s timer
                ]));

                // C. Delete the old job file to stop monitoring the broken sensor
                unlink($jobFile);
            }
        }
    } else {
        // 5. NORMAL READING
        // Deviation is safe, so we add it to the average pool
        $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 0)");
        $insert->bind_param("id", $soilSensorID, $latest);
        $insert->execute();
    }
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