<?php
session_start();
require_once 'db.php';
header('Content-Type: application/json');

// Fetch the most recent liquid level data
$data= [];
$stmt = $conn->prepare('SELECT l.liquidsensorID, l.currentliquidlevel
    FROM liquidlevelsensor l
    INNER JOIN (
        SELECT liquidsensorID, MAX(dateandtime) AS latest
        FROM liquidlevelsensor
        GROUP BY liquidsensorID
    ) latest_data
    ON l.liquidsensorID = latest_data.liquidsensorID
    AND l.dateandtime = latest_data.latest');

$stmt->execute();
$result = $stmt->get_result();
while ($row = $result->fetch_assoc()) {
    $data[] = $row;
}

echo json_encode($data);
?>