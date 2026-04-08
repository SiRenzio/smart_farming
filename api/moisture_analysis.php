<?php
require_once __DIR__ . '/../db.php';

function initialize_samples($conn, $job, $data){
    // If already initialized for this cycle, skip to monitoring
    if(!empty($data['initialized'])) return true;

    if(time() - $data['startTime'] < 120){
        return false;
    }

    $soilSensorID = $data['soilSensorID'];
    $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

    // Check if we already have a baseline for THIS specific sensor
    $checkQuery = $conn->prepare("SELECT COUNT(*) as total FROM soilmoisture_samples WHERE soilSensorID = ?");
    $checkQuery->bind_param("i", $soilSensorID);
    $checkQuery->execute();
    $count = $checkQuery->get_result()->fetch_assoc()['total'];

    if($count == 0){
        // FIRST TIME FOR THIS SENSOR: Capture 20 readings as baseline
        $query = $conn->prepare("
            SELECT SoilMois FROM sensordata 
            WHERE SoilSensorID=? AND DateTime>=? 
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
        
        // Flag to tell monitor_moisture NOT to immediately add a 21st reading
        $data['baseline_just_captured'] = true; 
    } 

    $data['initialized'] = true;
    file_put_contents($job, json_encode($data));
    return true;
}

function monitor_moisture($conn, $job, $data){
    if(empty($data['initialized'])){
        return;
    }

    $soilSensorID = $data['soilSensorID'];

    // 1. Get the latest reading AND the average FIRST
    $latestQuery = $conn->prepare("SELECT SoilMois FROM sensordata WHERE SoilSensorID=? ORDER BY DateTime DESC LIMIT 1");
    $latestQuery->bind_param("i", $soilSensorID);
    $latestQuery->execute();
    $latest = $latestQuery->get_result()->fetch_assoc()['SoilMois'];

    $avgQuery = $conn->prepare("SELECT AVG(SoilMois) avgVal FROM soilmoisture_samples WHERE soilSensorID=?");
    $avgQuery->bind_param("i", $soilSensorID);
    $avgQuery->execute();
    $average = $avgQuery->get_result()->fetch_assoc()['avgVal'];

    if(!$average) return;

    // 2. Evaluate for Deviation BEFORE inserting anything
    $deviation = (($latest - $average) / $average) * 100;

    if(abs($deviation) > 20){
        $average = round($average, 2);
        $deviation = round($deviation, 2);
        $notif = "Sensor $soilSensorID abnormal moisture: $latest (avg $average) | Deviation: $deviation%";

        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();

        // Switch isPrimary logic
        $userQuery = $conn->prepare("SELECT userID FROM deployment WHERE soilSensorID = ? LIMIT 1");
        $userQuery->bind_param("i", $soilSensorID);
        $userQuery->execute();
        $userData = $userQuery->get_result()->fetch_assoc();

        if ($userData) {
            $uid = $userData['userID'];

            $backupQuery = $conn->prepare("
                SELECT soilSensorID FROM deployment 
                WHERE userID = ? AND soilSensorID != ? AND isConnected = 1 LIMIT 1
            ");
            $backupQuery->bind_param("ii", $uid, $soilSensorID);
            $backupQuery->execute();
            $backupData = $backupQuery->get_result()->fetch_assoc();

            if ($backupData) {
                $backupSensorID = $backupData['soilSensorID'];

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
                $switch->close();

                // START A NEW JOB FOR THE BACKUP SENSOR
                // This creates a new JSON file, restarting the 120s timer for the new sensor
                file_put_contents(__DIR__ . "/../moisture_jobs/job_$backupSensorID.json", json_encode([
                    "soilSensorID" => $backupSensorID,
                    "startTime" => time()
                ]));
            }
        }
        
        // KILL THE OLD JOB: Stop monitoring this broken sensor immediately
        unlink($job); 
        
        // RETURN EARLY: The deviated data is completely discarded and NEVER enters the samples table
        return; 
    }

    // 3. If NO deviation occurred, we can safely add the latest reading 
    // We check a flag to ensure we only add 1 reading per watering cycle.
    if(empty($data['cycle_reading_added'])) {
        
        // Only insert if we didn't JUST capture the 20 baseline readings 2 milliseconds ago
        if(empty($data['baseline_just_captured'])){
            $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 0)");
            $insert->bind_param("id", $soilSensorID, $latest);
            $insert->execute();
        }

        // Mark this job as having successfully checked/added the reading
        $data['cycle_reading_added'] = true;
        file_put_contents($job, json_encode($data));
    }
}

// background worker
while(true){
    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job){
        $data = json_decode(file_get_contents($job), true);
        if(!$data) continue;

        if (initialize_samples($conn, $job, $data)) {
            // Re-read data in case initialize_samples modified it
            $data = json_decode(file_get_contents($job), true); 
            monitor_moisture($conn, $job, $data);
        }
    }
    sleep(5);
}
?>