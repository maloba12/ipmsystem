<?php
session_start();
header('Content-Type: application/json');

try {
    // Check if session is active
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        $response = [
            'status' => 'success',
            'user_id' => $_SESSION['user_id'],
            'user_role' => $_SESSION['user_role'],
            'user_email' => $_SESSION['user_email'] ?? ''
        ];
    } else {
        $response = [
            'status' => 'error',
            'message' => 'Session not active'
        ];
    }
    
    echo json_encode($response);
} catch (Exception $e) {
    error_log("Session check error: " . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'status' => 'error',
        'message' => 'An error occurred while checking session'
    ]);
}
