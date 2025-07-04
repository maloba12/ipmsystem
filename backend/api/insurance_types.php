<?php
session_start();
require_once '../auth/middleware.php';
require_once '../config/config.php';

// Check if user is logged in and has appropriate role
AuthMiddleware::requireLogin();

// Get the action from query parameters
$action = $_GET['action'] ?? 'list';

header('Content-Type: application/json');

try {
    $db = getDBConnection();
    
    switch ($action) {
        case 'list':
            // Get all insurance types with their services
            $stmt = $db->query("
                SELECT 
                    it.id as type_id,
                    it.name as type_name,
                    it.description as type_description,
                    GROUP_CONCAT(s.name) as services
                FROM insurance_types it
                LEFT JOIN services s ON it.id = s.insurance_type_id
                GROUP BY it.id
                ORDER BY it.name
            ");
            
            $insuranceTypes = $stmt->fetchAll(PDO::FETCH_ASSOC);
            echo json_encode([
                'success' => true,
                'data' => $insuranceTypes
            ]);
            break;
            
        case 'add':
            // Add new insurance type
            $stmt = $db->prepare("INSERT INTO insurance_types (name, description) VALUES (?, ?)");
            $stmt->execute([
                $_POST['name'],
                $_POST['description']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Insurance type added successfully'
            ]);
            break;
            
        case 'update':
            // Update insurance type
            $stmt = $db->prepare("UPDATE insurance_types SET name = ?, description = ? WHERE id = ?");
            $stmt->execute([
                $_POST['name'],
                $_POST['description'],
                $_POST['id']
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Insurance type updated successfully'
            ]);
            break;
            
        case 'delete':
            // Delete insurance type
            $stmt = $db->prepare("DELETE FROM insurance_types WHERE id = ?");
            $stmt->execute([$_POST['id']]);
            
            echo json_encode([
                'success' => true,
                'message' => 'Insurance type deleted successfully'
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
