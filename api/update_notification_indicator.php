<?php
session_start();
require_once '../db.php';

$userId = $_SESSION['userID'];

$stmt = $conn->prepare(
        "UPDATE `notification`
        SET isRead = 1 
        WHERE userID = ? AND isRead = 0");
$stmt->bind_param("i", $userId);
$stmt->execute();

echo json_encode(["success" => true]);
?>