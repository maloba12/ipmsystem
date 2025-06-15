<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class ClientController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function index() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $stmt = $this->db->query("
                SELECT id, first_name, last_name, email, phone, status, created_at 
                FROM clients 
                ORDER BY created_at DESC
            ");
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse(['clients' => $clients]);
        } catch (PDOException $e) {
            error_log("Error fetching clients: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch clients'], 500);
        }
    }

    public function show($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT c.*, 
                       COUNT(p.id) as total_policies,
                       SUM(p.premium_amount) as total_premium
                FROM clients c
                LEFT JOIN policies p ON c.id = p.client_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                sendJsonResponse(['error' => 'Client not found'], 404);
            }

            sendJsonResponse(['client' => $client]);
        } catch (PDOException $e) {
            error_log("Error fetching client: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch client details'], 500);
        }
    }

    public function store() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['first_name', 'last_name', 'email', 'phone'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                sendJsonResponse(['error' => "Field {$field} is required"], 400);
            }
        }

        // Validate email
        if (!isValidEmail($data['email'])) {
            sendJsonResponse(['error' => 'Invalid email format'], 400);
        }

        try {
            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                sendJsonResponse(['error' => 'Email already registered'], 400);
            }

            // Insert new client
            $stmt = $this->db->prepare("
                INSERT INTO clients (
                    first_name, last_name, email, phone, address, 
                    date_of_birth, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");

            $stmt->execute([
                $data['first_name'],
                $data['last_name'],
                $data['email'],
                $data['phone'],
                $data['address'] ?? null,
                $data['date_of_birth'] ?? null,
                $_SESSION['user_id']
            ]);

            $clientId = $this->db->lastInsertId();
            
            // Log activity
            logActivity($_SESSION['user_id'], 'create_client', "Created new client: {$data['email']}");

            sendJsonResponse([
                'message' => 'Client created successfully',
                'client_id' => $clientId
            ], 201);

        } catch (PDOException $e) {
            error_log("Error creating client: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create client'], 500);
        }
    }

    public function update($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // Check if client exists
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                sendJsonResponse(['error' => 'Client not found'], 404);
            }

            // Build update query dynamically based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = ['first_name', 'last_name', 'phone', 'address', 'date_of_birth', 'status'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
            }

            $params[] = $_SESSION['user_id']; // updated_by
            $params[] = $id; // WHERE id = ?

            $sql = "UPDATE clients SET " . implode(', ', $updates) . ", 
                    updated_by = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Log activity
            logActivity($_SESSION['user_id'], 'update_client', "Updated client ID: {$id}");

            sendJsonResponse(['message' => 'Client updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating client: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update client'], 500);
        }
    }

    public function delete($id) {
        AuthMiddleware::requireRole('admin');
        AuthMiddleware::validateCSRF();

        try {
            // Check if client has active policies
            $stmt = $this->db->prepare("
                SELECT COUNT(*) as policy_count 
                FROM policies 
                WHERE client_id = ? AND status = 'active'
            ");
            $stmt->execute([$id]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);

            if ($result['policy_count'] > 0) {
                sendJsonResponse(['error' => 'Cannot delete client with active policies'], 400);
            }

            // Soft delete the client
            $stmt = $this->db->prepare("
                UPDATE clients 
                SET status = 'deleted', 
                    deleted_by = ?, 
                    deleted_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $id]);

            // Log activity
            logActivity($_SESSION['user_id'], 'delete_client', "Deleted client ID: {$id}");

            sendJsonResponse(['message' => 'Client deleted successfully']);

        } catch (PDOException $e) {
            error_log("Error deleting client: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete client'], 500);
        }
    }

    public function search() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        $query = sanitize($_GET['query'] ?? '');
        
        if (empty($query)) {
            sendJsonResponse(['error' => 'Search query is required'], 400);
        }

        try {
            $searchTerm = "%{$query}%";
            $stmt = $this->db->prepare("
                SELECT id, first_name, last_name, email, phone, status
                FROM clients
                WHERE (first_name LIKE ? OR last_name LIKE ? OR email LIKE ? OR phone LIKE ?)
                AND status != 'deleted'
                ORDER BY created_at DESC
                LIMIT 10
            ");
            
            $stmt->execute([$searchTerm, $searchTerm, $searchTerm, $searchTerm]);
            $results = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['results' => $results]);

        } catch (PDOException $e) {
            error_log("Error searching clients: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to search clients'], 500);
        }
    }
}
