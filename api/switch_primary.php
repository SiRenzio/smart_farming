<?php
    header('Content-Type: application/json');

    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        echo json_encode(['success' => false, 'message' => 'Invalid request']);
        exit;
    }

    $primarySensorID = $_POST['primarySensorID'] ?? null;

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