<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

// Example: assuming you have a column like `isActive`
$stmt = $conn->prepare("SELECT nutritionID FROM plantnutrionneed WHERE isActive = 1 AND userID = ?");
$stmt->bind_param("i", $_SESSION['userID']);
$stmt->execute();
$result = $stmt->get_result();
$active = [];

while ($row = $result->fetch_assoc()) {
    $active[] = $row['nutritionID'];
}

echo json_encode([
    "success" => true,
    "active" => $active
]);