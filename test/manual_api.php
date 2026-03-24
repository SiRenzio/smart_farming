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




?>