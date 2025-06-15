<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/PolicyController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Initialize database connection
$db = getDBConnection();

// Initialize controller
$policyController = new PolicyController($db);

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/policies', '', $path);

// Handle preflight requests
if ($method === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Authenticate request
try {
    AuthMiddleware::authenticate();
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['message' => $e->getMessage()]);
    exit();
}

// Route requests
try {
    switch ($method) {
        case 'GET':
            if (empty($path) || $path === '/') {
                $policyController->getAll();
            } else {
                $id = trim($path, '/');
                $policyController->getById($id);
            }
            break;

        case 'POST':
            if (empty($path) || $path === '/') {
                $policyController->create();
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Endpoint not found']);
            }
            break;

        case 'PUT':
            if (empty($path) || $path === '/') {
                http_response_code(400);
                echo json_encode(['message' => 'Policy ID is required']);
            } else {
                $id = trim($path, '/');
                $policyController->update($id);
            }
            break;

        case 'DELETE':
            if (empty($path) || $path === '/') {
                http_response_code(400);
                echo json_encode(['message' => 'Policy ID is required']);
            } else {
                $id = trim($path, '/');
                $policyController->delete($id);
            }
            break;

        default:
            http_response_code(405);
            echo json_encode(['message' => 'Method not allowed']);
            break;
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['message' => 'An error occurred: ' . $e->getMessage()]);
} 