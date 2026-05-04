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

    // Update active status of plant nutrition need
    if ($nutritionIsActive) {
        // reset first before setting new active nutrition set
        $reset = $conn->prepare("UPDATE plantnutrionneed SET isActive = 0 WHERE userID = ?");
        $reset->bind_param('i', $_SESSION['userID']);
        $reset->execute();
        $reset->close();                        
        // set new active nutrition set
        $stmt = $conn->prepare("UPDATE plantnutrionneed SET isActive = 1 WHERE nutritionID = ? AND userID = ?");
        $stmt->bind_param('ii', $nutritionIsActive, $_SESSION['userID']);
        if ($stmt->execute()) {
            // Update deployed nutrition set
            $depStmt = $conn->prepare("UPDATE deployment SET nutritionID = ? WHERE userID = ?");
            $depStmt->bind_param("ii", $nutritionIsActive, $_SESSION['userID']);
            $depStmt->execute();
            $depStmt->close();

            echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Status was not updated successfully.']);
        }
        $stmt->close();
    }
    else if ($nutritionIsInactive) {
        $stmt = $conn->prepare("UPDATE plantnutrionneed SET isActive = 0 WHERE nutritionID = ? AND userID = ?");
        $stmt->bind_param('ii', $nutritionIsInactive, $_SESSION['userID']);
        if ($stmt->execute()) {
            // undeploy nutrition
            $depStmt = $conn->prepare("UPDATE deployment SET nutritionID = NULL WHERE userID = ?");
            $depStmt->bind_param("i", $_SESSION['userID']);
            $depStmt->execute();
            $depStmt->close();
            echo json_encode(['success' => true, 'message' => 'Status updated successfully.']);
        }
        else {
            echo json_encode(['success' => false, 'message' => 'Status was not updated successfully.']);
        }
        $stmt->close();
    }
?>