<?php
session_start();
require_once '../auth/middleware.php';

// Check if user is logged in and has admin role
AuthMiddleware::requireLogin();
AuthMiddleware::requireRole(['Admin']);

header('Content-Type: application/json');

try {
    // Initialize settings array
    $settings = [
        'system_name' => 'IPMS - Integrated Pest Management System',
        'version' => '1.0.0',
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
                'require_special_char' => true
            ],
            'session_timeout' => 30, // minutes
            'login_attempts' => 5
        ]
    ];

    // Return settings as JSON
    echo json_encode([
        'success' => true,
        'data' => $settings
    ]);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => 'Failed to fetch system settings: ' . $e->getMessage()
    ]);
}
