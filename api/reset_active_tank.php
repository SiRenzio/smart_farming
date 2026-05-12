<?php
    include_once '../db.php';

    $sql = "UPDATE tankpumpevent SET isActive = 0 WHERE isActive = 1";
    $stmt = $conn->prepare($sql);
    if ($stmt->execute()) {
        echo json_encode(['success' => true, 'message' => 'Active tank reset successfully.']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Failed to reset active tank.']);
    }
?>