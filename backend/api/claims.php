<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ClaimController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
$db = getDBConnection();

// Initialize claim controller
$claimController = new ClaimController($db);

// Authenticate request
try {
    AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['message' => $e->getMessage()]);
    exit();
}

// Get request method and path
$method = $_SERVER['REQUEST_METHOD'];
$path = parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH);
$path = str_replace('/api/claims', '', $path);

// Route requests
try {
    switch ($method) {
        case 'GET':
            if (empty($path) || $path === '/') {
                $claimController->index();
            } else {
                $id = trim($path, '/');
                $claimController->show($id);
            }
            break;

        case 'POST':
            if (empty($path) || $path === '/') {
                $claimController->store();
            } else {
                http_response_code(404);
                echo json_encode(['message' => 'Endpoint not found']);
            }
            break;

        case 'PUT':
            if (empty($path) || $path === '/') {
                http_response_code(400);
                echo json_encode(['message' => 'Claim ID is required']);
            } else {
                $id = trim($path, '/');
                $claimController->update($id);
            }
            break;

        case 'DELETE':
            if (empty($path) || $path === '/') {
                http_response_code(400);
                echo json_encode(['message' => 'Claim ID is required']);
            } else {
                $id = trim($path, '/');
                $claimController->delete($id);
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
