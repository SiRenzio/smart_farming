<?php
require_once '../db.php'; 

header('Content-Type: application/json');

// Read the JSON data sent by the Fetch API
$inputJSON = file_get_contents('php://input');
$data = json_decode($inputJSON, true);

$tankID = isset($data['tankID']) ? intval($data['tankID']) : 0;

if ($tankID == 2 || $tankID == 3) {
    
    // --- Check eligibility in the database ---
    $canCancelMixing = false;
    
    $checkStatusSql = "SELECT wateringstatus, wateringFlag FROM tankpumpevent WHERE liquidsensorID = ? ORDER BY dateandtime DESC LIMIT 1";
    $stmtCheck = $conn->prepare($checkStatusSql);
    $stmtCheck->bind_param("i", $tankID);
    $stmtCheck->execute();
    $checkRes = $stmtCheck->get_result();
    
    if ($checkRow = $checkRes->fetch_assoc()) {
        // Enable only if both wateringstatus and wateringFlag are strictly 0
        if ($checkRow['wateringstatus'] === 0 && $checkRow['wateringFlag'] === 0) {
            $canCancelMixing = true;
        }
    }
    $stmtCheck->close();

    // --- If eligible, execute the cancel logic ---
    if ($canCancelMixing) {
        $jsonFilePath = '../json/cancel_mixing.json';
        
        if (file_exists($jsonFilePath)) {
            $jsonContent = file_get_contents($jsonFilePath);
            $jsonData = json_decode($jsonContent, true);
            
            if (isset($jsonData[$tankID])) {
                $jsonData[$tankID]['isStop'] = 1;
                file_put_contents($jsonFilePath, json_encode($jsonData, JSON_PRETTY_PRINT));
                
                // Success!
                echo json_encode(['success' => true, 'message' => 'Mixing cancelled successfully.']);
                exit;
            } else {
                echo json_encode(['success' => false, 'error' => 'Tank ID not found in JSON configuration.']);
                exit;
            }
        } else {
            echo json_encode(['success' => false, 'error' => 'Failsafe file not found.']);
            exit;
        }
    } else {
        // The frontend sent a request, but the database says it's not allowed
        echo json_encode(['success' => false, 'error' => 'Action denied: System is not in the correct state to cancel.']);
        exit;
    }
}

// Fallback for invalid tank IDs
echo json_encode(['success' => false, 'error' => 'Invalid Tank ID provided.']);
?>