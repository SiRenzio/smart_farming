<?php
require_once '../db.php';

header('Content-Type: application/json');

// Example: assuming you have a column like `isActive`
$result = $conn->query("SELECT nutritionID FROM plantnutrionneed WHERE isActive = 1");

$active = [];

while ($row = $result->fetch_assoc()) {
    $active[] = $row['nutritionID'];
}

echo json_encode([
    "success" => true,
    "active" => $active
]);