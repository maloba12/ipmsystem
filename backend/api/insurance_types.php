<?php
session_start();
require_once '../auth/middleware.php';
require_once '../config/config.php';
require_once '../controllers/InsuranceController.php';
require_once '../utils/RateLimiter.php';

// Check if user is logged in and has appropriate role
AuthMiddleware::requireLogin();
AuthMiddleware::requireAnyRole(['admin']);

// Get the action from query parameters
$action = $_GET['action'] ?? 'list';

// Initialize rate limiter
$rateLimiter = new RateLimiter($db);
$rateLimiter->setLimits([
    'add' => ['limit' => 5, 'window' => 3600], // 5 requests per hour
    'update' => ['limit' => 10, 'window' => 3600], // 10 requests per hour
    'delete' => ['limit' => 5, 'window' => 3600] // 5 requests per hour
]);

// Check CSRF token
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        throw new Exception('Invalid CSRF token');
    }
}

header('Content-Type: application/json');

try {
    // Check rate limit
    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $rateLimiter->checkLimit($action, $_SESSION['user']['id']);
    }

    $db = getDBConnection();
    $insuranceController = new InsuranceController($db);
    
    switch ($action) {
        case 'list':
            try {
                $types = $insuranceController->getInsuranceTypes();
                echo json_encode(['success' => true, 'data' => $types]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'check_name':
            try {
                $name = $_GET['check_name'] ?? '';
                $exists = $insuranceController->checkInsuranceTypeNameExists($name);
                echo json_encode(['success' => true, 'exists' => $exists]);
            } catch (Exception $e) {
                echo json_encode(['success' => false, 'error' => $e->getMessage()]);
            }
            break;

        case 'add':
            // Rate limit check already done above
            $result = $insuranceController->createInsuranceType($_POST);
            echo json_encode($result);
            break;
            
        case 'update':
            // Rate limit check already done above
            $result = $insuranceController->updateInsuranceType($_POST['id'], $_POST);
            echo json_encode($result);
            break;
            
        case 'delete':
            // Rate limit check already done above
            $result = $insuranceController->deleteInsuranceType($_POST['id']);
            echo json_encode($result);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
