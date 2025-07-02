<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../controllers/ReportController.php';
require_once __DIR__ . '/../middleware/AuthMiddleware.php';

// Set headers
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization');

// Handle preflight requests
if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(200);
    exit();
}

// Initialize database connection
$db = getDBConnection();

// Initialize report controller
$reportController = new ReportController($db);

// Authenticate request
try {
    AuthMiddleware::requireAnyRole(['admin', 'agent']);
} catch (Exception $e) {
    http_response_code(401);
    echo json_encode(['message' => $e->getMessage()]);
    exit();
}

// Get request method
$method = $_SERVER['REQUEST_METHOD'];

// Route requests
try {
    switch ($method) {
        case 'GET':
            // Get list of available report types
            $reportTypes = [
                'client_summary' => 'Client Summary Report',
                'policy_summary' => 'Policy Summary Report',
                'claims_summary' => 'Claims Summary Report',
                'revenue_report' => 'Revenue Report'
            ];
            echo json_encode(['report_types' => $reportTypes]);
            break;

        case 'POST':
            // Generate report
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['type']) || !isset($data['start_date']) || !isset($data['end_date'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Type, start_date, and end_date are required']);
                break;
            }

            // Generate report
            $result = $reportController->generateReport($data);
            
            if ($result['status'] === 'success') {
                http_response_code(200);
            } else {
                http_response_code(400);
            }
            
            echo json_encode($result);
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
