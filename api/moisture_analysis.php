<?php
require_once __DIR__ . '/../db.php';

function moisture_analysis($conn) {

    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job) {

        $data = json_decode(file_get_contents($job), true);

        if (!$data) continue;

        // wait 2 minutes
        if (time() - $data['startTime'] < 120) {
            continue;
        }

        $soilSensorID = $data['soilSensorID'];

        $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

        // get 20 new readings after the 2-minute delay
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

        if (count($values) != 20) {
            continue;
        }

        // get current stored samples once
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

        foreach ($values as $moistureVal) {

            // if table empty → insert directly
            if (count($existingValues) == 0) {

                $insert = $conn->prepare("
                    INSERT INTO soilmoisture_samples (soilSensorID, SoilMois)
                    VALUES (?, ?)
                ");

                $insert->bind_param("id", $soilSensorID, $moistureVal);
                $insert->execute();
                $insert->close();

                $existingValues[] = $moistureVal;
                continue;
            }

            $average = array_sum($existingValues) / count($existingValues);

            $deviation = (($moistureVal - $average) / $average) * 100;

            if (abs($deviation) <= 20) {

                // keep max 20 rows
                if (count($existingValues) >= 20) {

                    $delete = $conn->prepare("
                        DELETE FROM soilmoisture_samples
                        WHERE soilSensorID = ?
                        ORDER BY sampleID ASC
                        LIMIT 1
                    ");

                    $delete->bind_param("i", $soilSensorID);
                    $delete->execute();
                    $delete->close();

                    array_shift($existingValues);
                }

                $insert = $conn->prepare("
                    INSERT INTO soilmoisture_samples (soilSensorID, SoilMois)
                    VALUES (?, ?)
                ");

                $insert->bind_param("id", $soilSensorID, $moistureVal);
                $insert->execute();
                $insert->close();

                $existingValues[] = $moistureVal;

            } else {

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

        // keep job active for continuous monitoring
        $data['startTime'] = time();
        file_put_contents($job, json_encode($data));
    }
}

// background worker
while (true) {
    moisture_analysis($conn);
    sleep(30);
}