<?php
// Redirect to frontend
header("Location: frontend/");
exit;
session_start();

// Database configuration
require_once 'config/database.php';

// Autoload classes
spl_autoload_register(function($class) {
    $class = str_replace('\\', DIRECTORY_SEPARATOR, $class);
    require_once __DIR__ . DIRECTORY_SEPARATOR . 'classes' . DIRECTORY_SEPARATOR . $class . '.php';
});

// Initialize the application
try {
    // Your main application logic here
    echo "Welcome to IPM System";
} catch (Exception $e) {
    error_log("Application error: " . $e->getMessage());
    http_response_code(500);
    echo "An error occurred. Please try again later.";
}
