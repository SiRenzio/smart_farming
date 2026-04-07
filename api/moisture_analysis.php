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
                (soilSensorID, SoilMois)
                VALUES (?, ?)
            ");

            foreach ($values as $moistureVal) {
                // get current stored samples
                $sampleQuery = $conn->prepare("
                    SELECT SoilMois 
                    FROM soilmoisture_samples
                    WHERE soilSensorID = ?
                    ORDER BY sampleID ASC
                ");

                $sampleQuery->bind_param("i", $soilSensorID);
                $sampleQuery->execute();
                $sampleResult = $sampleQuery->get_result();

                $existingValues = [];
                while ($row = $sampleResult->fetch_assoc()) {
                    $existingValues[] = $row['SoilMois'];
                }

                // if no samples yet, just insert
                if (count($existingValues) == 0) {

                    $insert = $conn->prepare("
                        INSERT INTO soilmoisture_samples (soilSensorID, SoilMois)
                        VALUES (?, ?)
                    ");
                    $insert->bind_param("id", $soilSensorID, $moistureVal);
                    $insert->execute();
                    $insert->close();

                    continue;
                }

                // calculate average
                $average = array_sum($existingValues) / count($existingValues);

                // deviation percentage
                $deviation = (($moistureVal - $average) / $average) * 100;

                if (abs($deviation) <= 20) {

                    // acceptable value → maintain rolling 20 rows

                    $delete = $conn->prepare("
                        DELETE FROM soilmoisture_samples
                        WHERE soilSensorID = ?
                        ORDER BY sampleID ASC
                        LIMIT 1
                    ");
                    $delete->bind_param("i", $soilSensorID);
                    $delete->execute();
                    $delete->close();

                    $insert = $conn->prepare("
                        INSERT INTO soilmoisture_samples (soilSensorID, SoilMois)
                        VALUES (?, ?)
                    ");
                    $insert->bind_param("id", $soilSensorID, $moistureVal);
                    $insert->execute();
                    $insert->close();

                } else {

                    // deviated value detected
                    $notifMessage = "Sensor $soilSensorID detected abnormal soil moisture value: $moistureVal (Deviation: " . round($deviation,2) . "%)";

                    $notifStmt = $conn->prepare("
                        INSERT INTO notification (message, createdAT)
                        VALUES (?, NOW())
                    ");
                    $notifStmt->bind_param("s", $notifMessage);
                    $notifStmt->execute();
                    $notifStmt->close();
                }
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