<?php
    session_start();
    require_once '../db.php';

    $nutritionIsActive = null;
    $nutritionIsInactive = null;

    if (!empty($_POST['active'])) {
        $nutritionIsActive = $_POST['active'] ?? '';
    }

    if (!empty($_POST['inactive'])) {
        $nutritionIsInactive = $_POST['inactive'] ?? '';
    }

    // Update deployed nutrition set
    $depStmt = $conn->prepare("UPDATE deployment SET nutritionID = ? WHERE userID = ?");
    $depStmt->bind_param("ii", $nutritionIsActive, $_SESSION['userID']);
    $depStmt->execute();
    $depStmt->close();

    // Update active status of plant nutrition need
    if ($nutritionIsActive) {
        $stmt = $conn->prepare("UPDATE plantnutrionneed SET isActive = 1 WHERE nutritionID = ?");
        $stmt->bind_param('i', $nutritionIsActive);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Status was not updated successfully.']);
        }
        $stmt->close();
    }
    else if ($nutritionIsInactive) {
        $stmt = $conn->prepare("UPDATE plantnutrionneed SET isActive = 0 WHERE nutritionID = ?");
        $stmt->bind_param('i', $nutritionIsInactive);
        if ($stmt->execute()) {
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Status was not updated successfully.']);
        }
        $stmt->close();
    }
?>