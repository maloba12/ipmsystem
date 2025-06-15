<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ClientController.php';
require_once __DIR__ . '/../middleware/auth.php';

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
$db = new PDO(
    "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME,
    DB_USER,
    DB_PASS,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
);

// Initialize client controller
$clientController = new ClientController($db);

// Authenticate request
$auth = new AuthMiddleware(['admin', 'agent']);
$user = $auth->handle();

// Route requests
$method = $_SERVER['REQUEST_METHOD'];
$action = isset($_GET['action']) ? $_GET['action'] : '';
$id = isset($_GET['id']) ? $_GET['id'] : null;

switch ("$method $action") {
    case 'GET ':
        if ($id) {
            $clientController->getById($id);
        } else {
            $clientController->getAll();
        }
        break;
        
    case 'POST ':
        $clientController->create();
        break;
        
    case 'PUT ':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['message' => 'Client ID is required']);
            break;
        }
        $clientController->update($id);
        break;
        
    case 'DELETE ':
        if (!$id) {
            http_response_code(400);
            echo json_encode(['message' => 'Client ID is required']);
            break;
        }
        $clientController->delete($id);
        break;
        
    default:
        http_response_code(404);
        echo json_encode(['message' => 'Not found']);
        break;
} 