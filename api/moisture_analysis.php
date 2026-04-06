<?php
require_once __DIR__ . '/../db.php';

function moisture_analysis($conn) {
    // Fixed path with proper slash
    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job) {

        $data = json_decode(file_get_contents($job), true);

        // 1. Wait 2 minutes (120 seconds) before doing anything
        if (time() - $data['startTime'] < 120) {
            continue; 
        }

        $soilSensorID = $data['soilSensorID'];
        $eventID = $data['eventID'];

        // Calculate the exact database timestamp when the 2-minute wait finished
        $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

        // 2. Get up to 20 soil moisture readings AFTER the 2-minute wait ended
        $query = $conn->prepare("
            SELECT SoilMois 
            FROM sensordata
            WHERE SoilSensorID = ? AND DateTime >= ?
            ORDER BY DateTime ASC
            LIMIT 20
        ");

        $query->bind_param("is", $soilSensorID, $targetTime);
        $query->execute();
        $result = $query->get_result();

        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = $row['SoilMois'];
        }

        // 3. Only process if we have collected exactly 20 new readings
        if (count($values) == 20) {

            // Store the 20 samples
            $insert = $conn->prepare("
                INSERT INTO soilmoisture_samples 
                (soilSensorID, tankpumpeventID, SoilMois)
                VALUES (?, ?, ?)
            ");

            foreach ($values as $moistureVal) {
                $insert->bind_param("iid", $soilSensorID, $eventID, $moistureVal);
                $insert->execute();
            }

            // Calculate metrics
            $average = array_sum($values) / 20;
            // end() gets the last item in the array (the 20th chronological reading)
            $latest = end($values); 

            $deviation = (($latest - $average) / $average) * 100;

            if ($deviation > 20) {
                $notifMessage = "Alert: Soil moisture has increased by more than 20% compared to the average.";
            } elseif ($deviation < -20) {
                $notifMessage = "Alert: Soil moisture has decreased by more than 20% compared to the average.";
            }

            if (!empty($notifMessage)) {
                $notifSql = "INSERT INTO notification (message, createdAT) VALUES (?, NOW())";
                $notifStmt = $conn->prepare($notifSql);
                $notifStmt->bind_param("s", $notifMessage);
                $notifStmt->execute();
                $notifStmt->close();
            }

            // 4. Job complete, delete the file
            unlink($job);
        }
    }
}

// Background loop
while (true) {
    moisture_analysis($conn);
    sleep(30);
}

?>