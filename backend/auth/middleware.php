<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class AuthMiddleware {
    public static function authenticate() {
        session_start();
        
        // Check if user is logged in
        if (!isLoggedIn()) {
            sendJsonResponse(['error' => 'Unauthorized access'], 401);
        }

        // Check session lifetime
        if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > SESSION_LIFETIME)) {
            session_unset();
            session_destroy();
            sendJsonResponse(['error' => 'Session expired'], 401);
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();

        // Verify user still exists and is active
        try {
            $db = getDBConnection();
            $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || $user['status'] !== 'active') {
                session_unset();
                session_destroy();
                sendJsonResponse(['error' => 'Account is no longer active'], 401);
            }
        } catch (PDOException $e) {
            error_log("Auth middleware error: " . $e->getMessage());
            sendJsonResponse(['error' => 'Authentication error'], 500);
        }
    }

    public static function requireRole($requiredRole) {
        self::authenticate();
        
        if (!isset($_SESSION['user_role']) || $_SESSION['user_role'] !== $requiredRole) {
            sendJsonResponse(['error' => 'Insufficient permissions'], 403);
        }
    }

    public static function requireAnyRole($roles) {
        self::authenticate();
        
        if (!isset($_SESSION['user_role']) || !in_array($_SESSION['user_role'], $roles)) {
            sendJsonResponse(['error' => 'Insufficient permissions'], 403);
        }
    }

    public static function validateCSRF() {
        if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT' || $_SERVER['REQUEST_METHOD'] === 'DELETE') {
            $token = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
            if (empty($token) || $token !== $_SESSION['csrf_token']) {
                sendJsonResponse(['error' => 'Invalid CSRF token'], 403);
            }
        }
    }
} 