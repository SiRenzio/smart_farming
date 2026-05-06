<?php
session_start();
date_default_timezone_set('Asia/Manila');

header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

function sendResponse($success, $message, $command) {
    echo json_encode([
        'success' => $success,
        'message' => $message,
        'command' => $command,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
    exit;
}

$input = json_decode(file_get_contents("php://input"), true) ?? [];
$manualCommand = $input['command'] ?? null;

$stateFile = 'manual_state.txt';

if (!empty($manualCommand)) {
    // Save the command to the text file
    file_put_contents($stateFile, $manualCommand);
    sendResponse(true, "Command '$manualCommand' sent to ESP32 queue.", $manualCommand);
} 

else {
    if (file_exists($stateFile)) {
        // Read the currently saved command
        $currentCommand = file_get_contents($stateFile);
        file_put_contents($stateFile, "none");
        
        sendResponse(true, "Fetched command", $currentCommand);
    } else {
        // If the file doesn't exist yet, just send "none"
        sendResponse(true, "No active command", "none");
    }
}
?>