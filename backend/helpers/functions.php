<?php
require_once __DIR__ . '/../config/config.php';

/**
 * Sanitize input data
 */
function sanitize($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data);
    return $data;
}

/**
 * Generate a random token
 */
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

/**
 * Check if user is logged in
 * @return bool
 */
function isLoggedIn() {
    if (!isset($_SESSION['user_id'])) {
        error_log("isLoggedIn: user_id not set");
        return false;
    }
    
    if (!isset($_SESSION['user_role'])) {
        error_log("isLoggedIn: user_role not set");
        return false;
    }
    
    error_log("isLoggedIn: User ID: " . $_SESSION['user_id'] . ", Role: " . $_SESSION['user_role']);
    return true;
}

/**
 * Redirect to a specific URL
 */
function redirect($url) {
    header("Location: " . $url);
    exit();
}

/**
 * Format date to standard format
 */
function formatDate($date) {
    return date('Y-m-d H:i:s', strtotime($date));
}

/**
 * Send JSON response
 */
function sendJsonResponse($data, $status = 200) {
    header('Content-Type: application/json');
    http_response_code($status);
    echo json_encode($data);
    exit();
}

/**
 * Validate email format
 */
function isValidEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

/**
 * Log activity
 */
function logActivity($user_id, $action, $details = '') {
    try {
        $db = getDBConnection();
        $stmt = $db->prepare("INSERT INTO activity_logs (user_id, action, details, created_at) VALUES (?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $details]);
    } catch(PDOException $e) {
        error_log("Error logging activity: " . $e->getMessage());
    }
}
