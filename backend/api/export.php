<?php
session_start();
require_once '../auth/middleware.php';
require_once '../utils/Export.php';

// Check if user is logged in
AuthMiddleware::requireLogin();

// Get parameters
$type = $_GET['type'] ?? 'excel'; // excel or pdf
$report = $_GET['report'] ?? 'insurance_types'; // insurance_types, users, etc.

header('Content-Type: application/json');

try {
    // Get data based on report type
    $data = [];
    $headers = [];
    $title = '';
    
    switch ($report) {
        case 'insurance_types':
            $title = 'Insurance Types Report';
            $headers = ['ID', 'Type Name', 'Description', 'Services'];
            
            $db = getDBConnection();
            $stmt = $db->query("
                SELECT 
                    it.id,
                    it.name as type_name,
                    it.description,
                    GROUP_CONCAT(s.name) as services
                FROM insurance_types it
                LEFT JOIN services s ON it.id = s.insurance_type_id
                GROUP BY it.id
                ORDER BY it.name
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    $row['id'],
                    $row['type_name'],
                    $row['description'],
                    $row['services'] ?? 'No services'
                ];
            }
            break;
            
        case 'users':
            $title = 'Users Report';
            $headers = ['ID', 'Name', 'Email', 'Role', 'Created At'];
            
            $db = getDBConnection();
            $stmt = $db->query("
                SELECT 
                    id,
                    name,
                    email,
                    role,
                    created_at
                FROM users
                ORDER BY created_at DESC
            ");
            
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                $data[] = [
                    $row['id'],
                    $row['name'],
                    $row['email'],
                    $row['role'],
                    $row['created_at']
                ];
            }
            break;
            
        default:
            throw new Exception('Invalid report type');
    }

    // Create export instance
    $export = new Export($title, $headers, $data);
    
    // Generate appropriate format
    if ($type === 'excel') {
        $export->toExcel();
    } elseif ($type === 'pdf') {
        $export->toPDF();
    } else {
        throw new Exception('Invalid export type');
    }

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'message' => $e->getMessage()
    ]);
}
