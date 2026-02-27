<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$userID = $_SESSION['userID'] ?? null;

if (!$userID) {
    echo json_encode([]);
    exit;
}

$stmt = $conn->prepare("
    SELECT notificationID, message, isRead, createdAt
    FROM notification
    ORDER BY createdAt DESC
    LIMIT 99
");
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

echo json_encode($notifications);
?>