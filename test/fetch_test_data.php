<?php
    include_once '../db.php';

    // Sensor Data
    $sensorStmt = $conn->prepare('SELECT s.*, sen.sensorName, f.farmName FROM sensordata s 
                                    JOIN sensorinfo sen ON s.SoilSensorID = sen.SoilSensorID 
                                    JOIN farmlocation f ON s.locationID = f.locationID 
                                    ORDER BY s.DateTime DESC LIMIT 1');
    $sensorStmt->execute();
    $sensorData = $sensorStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $sensorStmt->close();

    // Tank Data
    $tankStmt = $conn->prepare(
        'SELECT 
            s.liquidsensorID, 
            s.liquidtankname, 
            t.currentliquidlevel,
            t.dateandtime
        FROM liquidsensorinfo s
        LEFT JOIN liquidlevelsensor t ON s.liquidsensorID = t.liquidsensorID
        WHERE t.liquidsensorreadID = (
            SELECT MAX(liquidsensorreadID)
            FROM liquidlevelsensor
            WHERE liquidsensorID = s.liquidsensorID)'
        );
    $tankStmt->execute();
    $tankData = $tankStmt->get_result()->fetch_all(MYSQLI_ASSOC);
    $tankStmt->close();

    echo json_encode([
        'sensorData' => $sensorData,
        'tankData' => $tankData
    ]);
?>