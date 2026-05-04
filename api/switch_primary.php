<?php
    require_once '../db.php';
    session_start();
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $primarySensorID = $_POST['primarySensorID'] ?? null;

    // Get userID of the selected sensor
    $userQuery = $conn->prepare("SELECT userID FROM deployment WHERE soilSensorID = ?");
    $userQuery->bind_param("i", $primarySensorID);
    $userQuery->execute();
    $userResult = $userQuery->get_result()->fetch_assoc();
    $userQuery->close();

    if (!$userResult) {
        echo json_encode(['success' => false, 'message' => 'Sensor not found']);
        exit;
    }

    $userID = $userResult['userID'];

    // Remove existing primary for this user
    $removeStmt = $conn->prepare("UPDATE deployment SET isPrimary = 0 WHERE userID = ?");
    $removeStmt->bind_param("i", $userID);
    $removeStmt->execute();
    $removeStmt->close();

    // Set new primary
    $setStmt = $conn->prepare("UPDATE deployment SET isPrimary = 1 WHERE soilSensorID = ?");
    $setStmt->bind_param("i", $primarySensorID);
    $setStmt->execute();
    $setStmt->close();

    $jobDir = __DIR__ . "/../moisture_jobs/";

    // Get all job files
    $jobs = glob($jobDir . "job_*.json");

    // Delete each job file
    foreach ($jobs as $job) {
        if (file_exists($job)) {
            unlink($job);
        }
    }

    $jobPath = __DIR__ . "/../moisture_jobs/job_$primarySensorID.json";

    if(file_exists($jobPath)){
        unlink($jobPath);
    }

    file_put_contents($jobPath, json_encode([
        "soilSensorID" => $primarySensorID,
        "startTime" => time(),
        "triggeredBy" => "primary_switch"
    ]));

    echo json_encode(['success' => true, 'message' => 'Primary sensor switched successfully.']);
?>