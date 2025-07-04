<?php
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class AuthMiddleware {
    public static function authenticate() {
        // Ensure session is started
        if (session_status() === PHP_SESSION_NONE) {
            error_log("AuthMiddleware: Starting new session");
            session_start();
        }

        // Check if session is properly initialized
        if (!isset($_SESSION['initialized'])) {
            error_log("AuthMiddleware: Session not properly initialized");
            $_SESSION['initialized'] = true;
            $_SESSION['last_activity'] = time();
        }

        // Log session variables
        error_log("AuthMiddleware: Session variables:");
        error_log("AuthMiddleware: user_id: " . (isset($_SESSION['user_id']) ? $_SESSION['user_id'] : 'not set'));
        error_log("AuthMiddleware: user_role: " . (isset($_SESSION['user_role']) ? $_SESSION['user_role'] : 'not set'));
        error_log("AuthMiddleware: last_activity: " . (isset($_SESSION['last_activity']) ? $_SESSION['last_activity'] : 'not set'));

        // Check if user is logged in and session is valid
        if (!isset($_SESSION['user_id']) || !isset($_SESSION['user_role'])) {
            error_log("AuthMiddleware: Required session variables not set");
            // Clear invalid session
            session_unset();
            session_destroy();
            session_start();

            // Check if we're on a protected page
            $protected_pages = [
                'settings.php', 'users.php', 'policies.php',
                'analytics.php', 'security.php', 'admin_dashboard.php',
                'agent_dashboard.php', 'client_dashboard.php'
            ];

            $current_page = basename($_SERVER['PHP_SELF']);
            if (in_array($current_page, $protected_pages)) {
                // Redirect to login with redirect_to parameter
                $redirect_to = urlencode('/ipmsystem/frontend/' . $current_page);
                header("Location: /ipmsystem/frontend/login.php?redirect_to=" . $redirect_to);
                exit();
            }

            return;
        }

        // Update last activity time
        $_SESSION['last_activity'] = time();
        error_log("AuthMiddleware: Updated last activity time to: " . $_SESSION['last_activity']);

        // Verify user still exists and is active
        try {
            $db = getDBConnection();
            error_log("AuthMiddleware: Attempting to verify user with ID: " . $_SESSION['user_id']);

            $stmt = $db->prepare("SELECT status FROM users WHERE id = ?");
            $stmt->execute([$_SESSION['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                error_log("AuthMiddleware: User not found in database");
                session_unset();
                session_destroy();
                sendJsonResponse(['error' => 'Account not found', 'debug' => 'User ID not found in database'], 401);
                return;
            }

            if ($user['status'] !== 'active') {
                error_log("AuthMiddleware: User account not active");
                session_unset();
                session_destroy();
                sendJsonResponse(['error' => 'Account is no longer active', 'debug' => 'User status: ' . $user['status']], 401);
                return;
            }

            error_log("AuthMiddleware: User verified successfully");
        } catch (PDOException $e) {
            error_log("AuthMiddleware: Database error: " . $e->getMessage());
            // Don't fail authentication on database error, just log it
        }
    }

    // Role-based authorization methods
    public static function requireRole($requiredRole) {
        // Check both $_SESSION['user_role'] and $_SESSION['user']['role'] for compatibility
        $userRole = strtolower($_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? '');
        
        if (empty($userRole)) {
            header("Location: /ipmsystem/frontend/login.php?error=Unauthorized+access");
            exit();
        }
        
        // Map role names to a consistent format
        $roleMap = [
            'admin' => 'admin',
            'insurance agent' => 'agent',
            'agent' => 'agent',
            'insurance_agent' => 'agent'
        ];
        
        $normalizedRole = $roleMap[$userRole] ?? $userRole;
        $normalizedRequiredRole = $roleMap[strtolower($requiredRole)] ?? strtolower($requiredRole);
        
        if ($normalizedRole !== $normalizedRequiredRole) {
            header("Location: /ipmsystem/frontend/unauthorized.php");
            exit();
        }
    }

    public static function hasPermission($feature) {
        // Define feature permissions based on roles
        $permissions = [
            'admin' => [
                'all' => true
            ],
            'agent' => [
                'clients' => true,
                'policies' => true,
                'performance' => true,
                'settings' => false
            ],
            'nurse' => [
                'patients' => true,
                'appointments' => true,
                'vitals' => true,
                'reports' => true
            ],
            'doctor' => [
                'patients' => true,
                'appointments' => true,
                'prescriptions' => true,
                'diagnoses' => true,
                'reports' => true
            ]
        ];

        // Get user role
        $userRole = strtolower($_SESSION['user_role'] ?? $_SESSION['user']['role'] ?? '');
        $normalizedRole = $permissions[$userRole] ?? null;

        if (!$normalizedRole) {
            return false;
        }

        // Check if feature is allowed for this role
        if ($normalizedRole['all'] ?? false) {
            return true;
        }

        return $normalizedRole[$feature] ?? false;
    }

    public static function requireAnyRole($roles) {
        if (!isset($_SESSION['user_role'])) {
            header("Location: /ipmsystem/frontend/login.php?error=Unauthorized+access");
            exit();
        }
        $userRole = strtolower($_SESSION['user_role']);
        $roles = array_map('strtolower', $roles);
        if (!in_array($userRole, $roles)) {
            header("Location: /ipmsystem/frontend/unauthorized.php");
            exit();
        }
    }

    public static function validateCSRF() {
        if (!isset($_SESSION['csrf_token']) || !isset($_POST['csrf_token'])) {
            header("Location: /ipmsystem/frontend/login.php?error=Invalid+request");
            exit();
        }
        if ($_POST['csrf_token'] !== $_SESSION['csrf_token']) {
            header("Location: /ipmsystem/frontend/login.php?error=Invalid+request");
            exit();
        }
    }
}
