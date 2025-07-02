<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

// Get user ID from session or from request parameter
$user_id = $_SESSION['user_id'] ?? $_GET['user_id'] ?? null;

if (!$user_id) {
    echo json_encode(['error' => 'No user ID provided']);
    exit;
}

try {
    $db = getDBConnection();
    
    // Get user details
    $stmt = $db->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if (!$user) {
        echo json_encode(['error' => 'User not found']);
        exit;
    }
    
    // Format response with user status
    $response = [
        'user_id' => $user['id'],
        'email' => $user['email'],
        'role' => $user['role'],
        'status' => $user['status'],
        'created_at' => $user['created_at'],
        'last_login' => $user['last_login']
    ];
    
    echo json_encode($response);
    
} catch (PDOException $e) {
    error_log("Check user status error: " . $e->getMessage());
    echo json_encode(['error' => 'Database error']);
}
