<?php
// Database configuration
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'ipmsystem');

// Application configuration
define('APP_NAME', 'IPM System');
define('APP_URL', 'http://localhost/ipmsystem');
define('APP_ROOT', dirname(dirname(__FILE__)));

// Session configuration
define('SESSION_LIFETIME', 3600); // 1 hour
define('SESSION_NAME', 'ipmsystem_session');

// Error reporting
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Time zone
date_default_timezone_set('UTC');

// Database connection function
function getDBConnection() {
    try {
        $conn = new PDO(
            "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
            DB_USER,
            DB_PASS,
            array(PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION)
        );
        return $conn;
    } catch(PDOException $e) {
        die("Connection failed: " . $e->getMessage());
    }
}
