<?php
require_once __DIR__ . '/../db.php';
function initialize_samples($conn, $job, $data){
    if(time() - $data['startTime'] < 120){
        return false;
    }

    $soilSensorID = $data['soilSensorID'];
    $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

    // Check if we already have a baseline
    $checkQuery = $conn->prepare("SELECT COUNT(*) as total FROM soilmoisture_samples WHERE soilSensorID = ?");
    $checkQuery->bind_param("i", $soilSensorID);
    $checkQuery->execute();
    $count = $checkQuery->get_result()->fetch_assoc()['total'];

    if($count == 0){
        // FIRST TIME: Capture 20 readings as baseline
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
    } else {
        // SUBSEQUENT CYCLES: Add only 1 latest reading, labeled as new (is_baseline = 0)
        $query = $conn->prepare("
            SELECT SoilMois FROM sensordata 
            WHERE SoilSensorID=? AND DateTime>=? 
            ORDER BY DateTime DESC LIMIT 1
        ");
        $query->bind_param("is", $soilSensorID, $targetTime);
        $query->execute();
        $row = $query->get_result()->fetch_assoc();

        if($row){
            $insert = $conn->prepare("INSERT INTO soilmoisture_samples (soilSensorID, SoilMois, is_baseline) VALUES (?, ?, 0)");
            $insert->bind_param("id", $soilSensorID, $row['SoilMois']);
            $insert->execute();
        }
    }

    $data['initialized'] = true;
    file_put_contents($job, json_encode($data));
    return true;
}

function monitor_moisture($conn, $data){
    if(empty($data['initialized'])){
        return;
    }

    $soilSensorID = $data['soilSensorID'];

    // Get the very latest reading from the sensor
    $latestQuery = $conn->prepare("SELECT SoilMois FROM sensordata WHERE SoilSensorID=? ORDER BY DateTime DESC LIMIT 1");
    $latestQuery->bind_param("i", $soilSensorID);
    $latestQuery->execute();
    $latest = $latestQuery->get_result()->fetch_assoc()['SoilMois'];

    // Calculate average from all stored samples (Baseline + Subsequent additions)
    $avgQuery = $conn->prepare("SELECT AVG(SoilMois) avgVal FROM soilmoisture_samples WHERE soilSensorID=?");
    $avgQuery->bind_param("i", $soilSensorID);
    $avgQuery->execute();
    $average = $avgQuery->get_result()->fetch_assoc()['avgVal'];

    if(!$average) return;

    $deviation = (($latest - $average) / $average) * 100;

    if(abs($deviation) > 20){
        $average = round($average, 2);
        $deviation = round($deviation, 2);
        $notif = "Sensor $soilSensorID abnormal moisture: $latest (avg $average) | Deviation: $deviation%";

        $stmt = $conn->prepare("INSERT INTO notification(message, createdAT) VALUES (?, NOW())");
        $stmt->bind_param("s", $notif);
        $stmt->execute();

        // Switch isPrimary logic
        // 1. First, find the User ID associated with this failing sensor
        $userQuery = $conn->prepare("SELECT userID FROM deployment WHERE soilSensorID = ? LIMIT 1");
        $userQuery->bind_param("i", $soilSensorID);
        $userQuery->execute();
        $userData = $userQuery->get_result()->fetch_assoc();

        if ($userData) {
            $uid = $userData['userID'];

            // 2. Look for EXACTLY ONE other connected sensor for this user
            $backupQuery = $conn->prepare("
                SELECT soilSensorID 
                FROM deployment 
                WHERE userID = ? 
                AND soilSensorID != ? 
                AND isConnected = 1 
                LIMIT 1
            ");
            $backupQuery->bind_param("ii", $uid, $soilSensorID);
            $backupQuery->execute();
            $backupData = $backupQuery->get_result()->fetch_assoc();

            if ($backupData) {
                // A connected backup sensor was found!
                $backupSensorID = $backupData['soilSensorID'];

                // 3. Swap the primary status between the two specific sensors
                $switch = $conn->prepare("
                    UPDATE deployment 
                    SET isPrimary = CASE 
                        WHEN soilSensorID = ? THEN 0 
                        WHEN soilSensorID = ? THEN 1 
                    END 
                    WHERE soilSensorID IN (?, ?)
                ");
                
                // Bind the parameters (failing ID, backup ID, failing ID, backup ID)
                $switch->bind_param("iiii", $soilSensorID, $backupSensorID, $soilSensorID, $backupSensorID);
                $switch->execute();
                $switch->close();
                
            } else {
                // Optional: What happens if there are NO other connected sensors?
                // You might want to leave it as primary, or set it to 0 and send a critical alert.
                // e.g., error_log("User $uid has no active backup sensors!");
            }
        }
    }
}

// background worker
while(true){

    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job){

        $data = json_decode(file_get_contents($job), true);

        if(!$data) continue;

        initialize_samples($conn, $job, $data);
        monitor_moisture($conn, $data);

    }

    sleep(5);
}