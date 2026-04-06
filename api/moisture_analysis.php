<?php
require_once __DIR__ . '/../db.php';

function moisture_analysis($conn) {
    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job) {

        $data = json_decode(file_get_contents($job), true);

        // wait 2 minutes
        if (time() - $data['startTime'] < 120) {
            continue;
        }

        $soilSensorID = $data['soilSensorID'];
        $eventID = $data['eventID'];

        // get last 20 soil moisture readings
        $query = $conn->prepare("
            SELECT SoilMois 
            FROM sensordata
            WHERE SoilSensorID = ?
            ORDER BY DateTime DESC
            LIMIT 20
        ");

        $query->bind_param("i", $soilSensorID);
        $query->execute();
        $result = $query->get_result();

        $values = [];
        while ($row = $result->fetch_assoc()) {
            $values[] = $row['SoilMois'];

            // store sample
            $insert = $conn->prepare("
                INSERT INTO soilmoisture_samples 
                (soilSensorID, tankpumpeventID, SoilMois)
                VALUES (?, ?, ?)
            ");
            $insert->bind_param("iid", $soilSensorID, $eventID, $row['SoilMois']);
            $insert->execute();
        }

        if (count($values) == 20) {

            $average = array_sum($values) / 20;
            $latest = $values[0];

            $analysis = $conn->prepare("
                INSERT INTO soilmoisture_analysis
                (soilSensorID, tankpumpeventID, averageMoisture, latestMoisture)
                VALUES (?, ?, ?, ?)
            ");

            $analysis->bind_param("iidd", $soilSensorID, $eventID, $average, $latest);
            $analysis->execute();
        }

        unlink($job);
    }
}

while (true) {
    moisture_analysis($conn);
    sleep(30);
}

?>