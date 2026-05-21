<?php
session_start();
require_once '../db.php';

header('Content-Type: application/json');

$userID = $_SESSION['userID'] ?? null;

if (!$userID) {
    echo json_encode([]);
    exit;
}

// Get the last ID the client has seen (default to 0 for initial load)
$last_id = isset($_GET['last_id']) ? (int)$_GET['last_id'] : 0;

// Fetch only notifications newer than the last_id, specific to this user
$stmt = $conn->prepare("
    SELECT notificationID, message, isRead, createdAt
    FROM notification
    WHERE notificationID > ?
    ORDER BY createdAt DESC
    LIMIT 25
");

$stmt->bind_param("i", $last_id);
$stmt->execute();
$result = $stmt->get_result();

$notifications = [];
while ($row = $result->fetch_assoc()) {
    $notifications[] = $row;
}

// If there are no new notifications, this returns an empty array []
echo json_encode($notifications);
?>