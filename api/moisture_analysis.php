<?php
require_once __DIR__ . '/../db.php';

function initialize_samples($conn, $job, $data){

    if(time() - $data['startTime'] < 120){
        return false;
    }

    $soilSensorID = $data['soilSensorID'];
    $targetTime = date('Y-m-d H:i:s', $data['startTime'] + 120);

    $query = $conn->prepare("
        SELECT SoilMois
        FROM sensordata
        WHERE SoilSensorID=? AND DateTime>=?
        ORDER BY DateTime DESC
        LIMIT 20
    ");

    $query->bind_param("is",$soilSensorID,$targetTime);
    $query->execute();
    $result=$query->get_result();

    if($result->num_rows < 20){
        return false;
    }

    // Clear old samples
    $clear = $conn->prepare("
        DELETE FROM soilmoisture_samples
        WHERE soilSensorID = ?
    ");
    $clear->bind_param("i", $soilSensorID);
    $clear->execute();
    $clear->close();

    while($row=$result->fetch_assoc()){

        $insert=$conn->prepare("
            INSERT INTO soilmoisture_samples
            (soilSensorID,SoilMois)
            VALUES(?,?)
        ");

        $insert->bind_param("id",$soilSensorID,$row['SoilMois']);
        $insert->execute();
    }

    $data['initialized']=true;
    file_put_contents($job,json_encode($data));

    return true;
}

function monitor_moisture($conn,$job,$data){

    if(empty($data['initialized'])){
        return;
    }

    $soilSensorID=$data['soilSensorID'];

    $latestQuery=$conn->prepare("
        SELECT SoilMois
        FROM sensordata
        WHERE SoilSensorID=?
        ORDER BY DateTime DESC
        LIMIT 1
    ");

    $latestQuery->bind_param("i",$soilSensorID);
    $latestQuery->execute();
    $latest=$latestQuery->get_result()->fetch_assoc()['SoilMois'];

    $avgQuery=$conn->prepare("
        SELECT AVG(SoilMois) avgVal
        FROM soilmoisture_samples
        WHERE soilSensorID=?
    ");

    $avgQuery->bind_param("i",$soilSensorID);
    $avgQuery->execute();
    $average=$avgQuery->get_result()->fetch_assoc()['avgVal'];

    $deviation=(($latest-$average)/$average)*100;

    if(abs($deviation) > 20){

        $notif="Sensor $soilSensorID abnormal moisture: $latest (avg $average)";

        $stmt=$conn->prepare("
            INSERT INTO notification(message,createdAT)
            VALUES(?,NOW())
        ");

        $stmt->bind_param("s",$notif);
        $stmt->execute();

        // Switch isPrimary flag to another sensor if exists
        $switch=$conn->prepare("
            UPDATE sensorinfo
            SET isPrimary=CASE WHEN soilSensorID=? THEN 0 ELSE 1 END
            WHERE soilSensorID IN (
                SELECT soilSensorID
                FROM sensorinfo
                WHERE userID = (SELECT userID FROM sensorinfo WHERE soilSensorID = ?)
            )
        ");
        $switch->bind_param("ii", $soilSensorID, $soilSensorID);
        $switch->execute();
        $switch->close();

        return;
    }
    else {
        // maintain rolling window
        $delete=$conn->prepare("
            DELETE FROM soilmoisture_samples
            WHERE soilSensorID=?
            ORDER BY sampleID ASC
            LIMIT 1
        ");
        $delete->bind_param("i",$soilSensorID);
        $delete->execute();

        $insert=$conn->prepare("
            INSERT INTO soilmoisture_samples(soilSensorID,SoilMois)
            VALUES(?,?)
        ");
        $insert->bind_param("id",$soilSensorID,$latest);
        $insert->execute();
    }
}

// background worker
while(true){

    $jobFiles = glob(__DIR__ . "/../moisture_jobs/*.json");

    foreach ($jobFiles as $job){

        $data = json_decode(file_get_contents($job), true);

        if(!$data) continue;

        initialize_samples($conn, $job, $data);
        monitor_moisture($conn, $job, $data);

    }

    sleep(5);
}