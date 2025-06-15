<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/DashboardController.php';
require_once __DIR__ . '/../middleware/auth.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
$db = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Initialize dashboard controller
$dashboardController = new DashboardController($db);

// Authenticate request
$auth = new AuthMiddleware(['admin', 'agent', 'user']);
$user = $auth->handle();

// Route requests
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';

switch ("$method $action") {
    case 'GET stats':
        $dashboardController->getStats();
        break;
        
    case 'GET activities':
        $dashboardController->getActivities();
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;
} 