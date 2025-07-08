<?php
session_start();
require_once '../auth/middleware.php';
require_once '../controllers/SettingsController.php';

// Rate limiting
if (!isset($_SESSION['settings_api_last_request'])) {
    $_SESSION['settings_api_last_request'] = time();
    $_SESSION['settings_api_request_count'] = 0;
}

$current_time = time();
$time_diff = $current_time - $_SESSION['settings_api_last_request'];

if ($time_diff < 1) { // 1 second rate limit
    http_response_code(429);
    echo json_encode([
        'success' => false,
        'message' => 'Too many requests. Please try again later.'
    ]);
    exit();
}

// Check if user is logged in and has admin role
try {
    AuthMiddleware::requireLogin();
    AuthMiddleware::requireRole(['Admin']);
} catch (Exception $e) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Unauthorized access'
    ]);
    exit();
}

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('X-XSS-Protection: 1; mode=block');

// Validate CSRF token
if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'message' => 'Invalid CSRF token'
    ]);
    exit();
}

try {
    $settingsController = new SettingsController();
    
    // Handle GET request for settings
    if ($_SERVER['REQUEST_METHOD'] === 'GET') {
        $settings = $settingsController->getSystemSettings();
        
        // Transform database settings
        $transformedSettings = [
            'system_name' => '',
            'version' => '',
            'theme' => [
                'primary_color' => '#3b82f6',
                'secondary_color' => '#60a5fa',
                'dark_mode' => false
            ],
            'notifications' => [
                'enabled' => true,
                'sound' => true,
                'popup' => true
            ],
            'security' => [
                'password_policy' => [
                    'min_length' => 8,
                    'require_uppercase' => true,
                    'require_lowercase' => true,
                    'require_number' => true,
                    'require_special_char' => true,
                    'expiration_days' => 90
                ],
                'session_timeout' => 30,
                'login_attempts' => 5,
                'password_history' => 5,
                'lockout_duration' => 30
            ],
            'email' => [
                'smtp_enabled' => true,
                'from_address' => '',
                'reply_to' => ''
            ]
        ];

        // Update values from database settings
        foreach ($settings as $category => $categorySettings) {
            foreach ($categorySettings as $setting) {
                if ($category === 'general') {
                    if ($setting['setting_key'] === 'app_name') {
                        $transformedSettings['system_name'] = $setting['value'];
                    } elseif ($setting['setting_key'] === 'version') {
                        $transformedSettings['version'] = $setting['value'];
                    }
                } elseif ($category === 'security') {
                    if ($setting['setting_key'] === 'password_min_length') {
                        $transformedSettings['security']['password_policy']['min_length'] = intval($setting['value']);
                    } elseif ($setting['setting_key'] === 'password_expiration_days') {
                        $transformedSettings['security']['password_policy']['expiration_days'] = intval($setting['value']);
                    } elseif ($setting['setting_key'] === 'login_attempts') {
                        $transformedSettings['security']['login_attempts'] = intval($setting['value']);
                    } elseif ($setting['setting_key'] === 'password_history') {
                        $transformedSettings['security']['password_history'] = intval($setting['value']);
                    } elseif ($setting['setting_key'] === 'lockout_duration') {
                        $transformedSettings['security']['lockout_duration'] = intval($setting['value']);
                    }
                } elseif ($category === 'display') {
                    if ($setting['setting_key'] === 'theme') {
                        $transformedSettings['theme']['dark_mode'] = $setting['value'] === 'dark';
                    }
                } elseif ($category === 'email') {
                    if ($setting['setting_key'] === 'smtp_enabled') {
                        $transformedSettings['email']['smtp_enabled'] = $setting['value'] === '1';
                    } elseif ($setting['setting_key'] === 'from_address') {
                        $transformedSettings['email']['from_address'] = $setting['value'];
                    } elseif ($setting['setting_key'] === 'reply_to') {
                        $transformedSettings['email']['reply_to'] = $setting['value'];
                    }
                }
            }
        }

        echo json_encode([
            'success' => true,
            'data' => $transformedSettings
        ]);
        exit();
    }

    // Handle POST request to update settings
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $settings = json_decode(file_get_contents('php://input'), true);
        
        if (!$settings) {
            throw new Exception('Invalid JSON data');
        }

        // Validate required fields
        $required_fields = [
            'system_name' => 'string',
            'theme' => 'array',
            'notifications' => 'array',
            'security' => 'array',
            'email' => 'array'
        ];

        foreach ($required_fields as $field => $type) {
            if (!isset($settings[$field]) || gettype($settings[$field]) !== $type) {
                throw new Exception("Missing or invalid field: $field");
            }
        }

        // Validate theme settings
        if (!isset($settings['theme']['primary_color']) || !preg_match('/^#[a-f0-9]{6}$/i', $settings['theme']['primary_color'])) {
            throw new Exception('Invalid primary color format');
        }

        // Validate security settings
        if ($settings['security']['password_policy']['min_length'] < 8) {
            throw new Exception('Minimum password length must be at least 8 characters');
        }

        // Update settings in database
        $result = $settingsController->updateSystemSettings($settings);
        
        echo json_encode([
            'success' => true,
            'message' => 'Settings updated successfully',
            'data' => $result
        ]);
        exit();
    }

    // If we reach here, method is not supported
    http_response_code(405);
    echo json_encode([
        'success' => false,
        'message' => 'Method not allowed'
    ]);

} catch (Exception $e) {
    http_response_code(500);
    error_log("Settings API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Internal server error: ' . $e->getMessage()
    ]);
}
