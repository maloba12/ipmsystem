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

// Handle dashboard data requests
if (isset($_GET['action'])) {
    $action = $_GET['action'];

    try {
        switch ($action) {
            case 'revenue':
                // Get revenue data for the last 6 months
                $stmt = $db->prepare("
                    SELECT 
                        DATE_FORMAT(p.created_at, '%Y-%m') as month,
                        SUM(p.premium) as total_revenue
                    FROM policies p
                    WHERE p.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
                    GROUP BY month
                    ORDER BY month
                ");
                $stmt->execute();
                $revenueData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Format data for chart
                $labels = array_map(function($row) {
                    return date('M', strtotime($row['month']));
                }, $revenueData);

                $values = array_column($revenueData, 'total_revenue');

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'labels' => $labels,
                        'values' => $values
                    ]
                ]);
                break;

            case 'users':
                // Get user distribution
                $stmt = $db->prepare("
                    SELECT 
                        CASE 
                            WHEN role = 'Admin' THEN 'Admins'
                            WHEN role = 'Agent' THEN 'Agents'
                            ELSE 'Clients'
                        END as user_type,
                        COUNT(*) as count
                    FROM users
                    GROUP BY user_type
                ");
                $stmt->execute();
                $userData = $stmt->fetchAll(PDO::FETCH_ASSOC);

                // Format data for chart
                $labels = array_column($userData, 'user_type');
                $values = array_column($userData, 'count');

                echo json_encode([
                    'success' => true,
                    'data' => [
                        'labels' => $labels,
                        'values' => $values
                    ]
                ]);
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
}
