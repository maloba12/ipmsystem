<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class ClientController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    c.*,
                    COUNT(DISTINCT p.id) as total_policies,
                    COUNT(DISTINCT cl.id) as total_claims
                FROM clients c
                LEFT JOIN policies p ON c.id = p.client_id
                LEFT JOIN claims cl ON p.id = cl.policy_id
                GROUP BY c.id
                ORDER BY c.created_at DESC
            ");
            $clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($clients);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while fetching clients']);
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    c.*,
                    COUNT(DISTINCT p.id) as total_policies,
                    COUNT(DISTINCT cl.id) as total_claims
                FROM clients c
                LEFT JOIN policies p ON c.id = p.client_id
                LEFT JOIN claims cl ON p.id = cl.policy_id
                WHERE c.id = ?
                GROUP BY c.id
            ");
            $stmt->execute([$id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$client) {
                http_response_code(404);
                echo json_encode(['message' => 'Client not found']);
                return;
            }

            // Get client's policies
            $stmt = $this->db->prepare("
                SELECT * FROM policies 
                WHERE client_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$id]);
            $client['policies'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($client);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while fetching client details']);
        }
    }

    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['phone']) || !isset($data['address'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Name, email, phone, and address are required']);
                return;
            }

            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE email = ?");
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['message' => 'Email already exists']);
                return;
            }

            // Insert new client
            $stmt = $this->db->prepare("
                INSERT INTO clients (name, email, phone, address)
                VALUES (?, ?, ?, ?)
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['address']
            ]);

            $clientId = $this->db->lastInsertId();
            
            // Get created client
            $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$clientId]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($client);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while creating client']);
        }
    }

    public function update($id) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['name']) || !isset($data['email']) || !isset($data['phone']) || !isset($data['address'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Name, email, phone, and address are required']);
                return;
            }

            // Check if client exists
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['message' => 'Client not found']);
                return;
            }

            // Check if email is already used by another client
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE email = ? AND id != ?");
            $stmt->execute([$data['email'], $id]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['message' => 'Email already exists']);
                return;
            }

            // Update client
            $stmt = $this->db->prepare("
                UPDATE clients 
                SET name = ?, email = ?, phone = ?, address = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['name'],
                $data['email'],
                $data['phone'],
                $data['address'],
                $id
            ]);

            // Get updated client
            $stmt = $this->db->prepare("SELECT * FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            $client = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($client);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while updating client']);
        }
    }

    public function delete($id) {
        try {
            // Check if client exists
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['message' => 'Client not found']);
                return;
            }

            // Delete client (cascade will handle related records)
            $stmt = $this->db->prepare("DELETE FROM clients WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['message' => 'Client deleted successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while deleting client']);
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
