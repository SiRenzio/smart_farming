<?php
require_once '../db.php';

$setID = $_GET['setID'] ?? '';

// Fetch nutrition set
$stmt = $conn->prepare("
    SELECT nutritionID, soilType, meanMoistureThreshold, growthStage, soilN, soilP, soilK, soilEC, soilPH, liquidVolume
    FROM plantnutrionneed
    WHERE nutritionID = ?
");
$stmt->bind_param("i", $setID);
$stmt->execute();

$result = $stmt->get_result()->fetch_assoc();

// Validation
if (!$result) {
    echo json_encode([
        "nutrition" => null,
        "fertilizers" => []
    ]);
    exit;
}

// Fetch fertilizers for the set
$nutritionID = $result['nutritionID'];
$fertilizers = [];

$stmt2 = $conn->prepare("
    SELECT fertilizerName, fertilizerAmount
    FROM fertilizer
    WHERE nutritionID = ?
");
$stmt2->bind_param("i", $nutritionID);
$stmt2->execute();
$res2 = $stmt2->get_result();

while($row = $res2->fetch_assoc()){
    $fertilizers[] = $row;
}

echo json_encode([
    "nutrition" => $result,
    "fertilizers" => $fertilizers
]);
?>