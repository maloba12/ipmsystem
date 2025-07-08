<?php
session_start();
require_once '../auth/middleware.php';
require_once '../config/database.php';
require_once '../controllers/UserController.php';

// Check if user is logged in and has admin role
AuthMiddleware::requireLogin();
AuthMiddleware::requireRole(['Admin']);

header('Content-Type: application/json');

try {
    // Initialize database connection
    $db = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=utf8mb4',
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false
        ]
    );

    // Initialize UserController
    $userController = new UserController($db);

    // Handle different actions
    $action = $_GET['action'] ?? '';

    switch ($action) {
        case 'create':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $userData = json_decode(file_get_contents('php://input'), true);
            if (!$userData) {
                throw new Exception('Invalid JSON data');
            }

            $result = $userController->createUser($userData);
            break;

        case 'list':
            $result = $userController->getUsers();
            break;

        case 'get':
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $result = $userController->getUser($userId);
            break;

        case 'update':
            if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
                throw new Exception('Method not allowed');
            }

            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $userData = json_decode(file_get_contents('php://input'), true);
            if (!$userData) {
                throw new Exception('Invalid JSON data');
            }

            $result = $userController->updateUser($userId, $userData);
            break;

        case 'delete':
            $userId = $_GET['id'] ?? null;
            if (!$userId) {
                throw new Exception('User ID is required');
            }

            $result = $userController->deleteUser($userId);
            break;

        default:
            throw new Exception('Invalid action');
    }

    echo json_encode($result);

} catch (Exception $e) {
    http_response_code(500);
    error_log("User API error: " . $e->getMessage());
    echo json_encode([
        'success' => false,
        'message' => 'Error: ' . $e->getMessage()
    ]);
}
