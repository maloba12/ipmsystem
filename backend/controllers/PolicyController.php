<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class PolicyController {
    private $db;

    public function __construct($db) {
        $this->db = $db;
    }

    public function getAll() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    p.*,
                    c.name as client_name,
                    c.email as client_email,
                    COUNT(cl.id) as total_claims
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                LEFT JOIN claims cl ON p.id = cl.policy_id
                GROUP BY p.id
                ORDER BY p.created_at DESC
            ");
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($policies);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while fetching policies']);
        }
    }

    public function getById($id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.name as client_name,
                    c.email as client_email,
                    c.phone as client_phone,
                    c.address as client_address
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                http_response_code(404);
                echo json_encode(['message' => 'Policy not found']);
                return;
            }

            // Get policy's claims
            $stmt = $this->db->prepare("
                SELECT * FROM claims 
                WHERE policy_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$id]);
            $policy['claims'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            echo json_encode($policy);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while fetching policy details']);
        }
    }

    public function create() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['client_id']) || !isset($data['type']) || !isset($data['premium']) || 
                !isset($data['start_date']) || !isset($data['end_date'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Client ID, type, premium, start date, and end date are required']);
                return;
            }

            // Check if client exists
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE id = ?");
            $stmt->execute([$data['client_id']]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['message' => 'Client not found']);
                return;
            }

            // Generate policy number
            $policyNumber = $this->generatePolicyNumber($data['type']);

            // Insert new policy
            $stmt = $this->db->prepare("
                INSERT INTO policies (
                    policy_number, client_id, type, premium, 
                    start_date, end_date, status
                ) VALUES (?, ?, ?, ?, ?, ?, 'active')
            ");
            $stmt->execute([
                $policyNumber,
                $data['client_id'],
                $data['type'],
                $data['premium'],
                $data['start_date'],
                $data['end_date']
            ]);

            $policyId = $this->db->lastInsertId();
            
            // Get created policy
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.name as client_name,
                    c.email as client_email
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$policyId]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($policy);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while creating policy']);
        }
    }

    public function update($id) {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['premium']) || !isset($data['start_date']) || !isset($data['end_date'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Premium, start date, and end date are required']);
                return;
            }

            // Check if policy exists
            $stmt = $this->db->prepare("SELECT id FROM policies WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['message' => 'Policy not found']);
                return;
            }

            // Update policy
            $stmt = $this->db->prepare("
                UPDATE policies 
                SET premium = ?, start_date = ?, end_date = ?, status = ?
                WHERE id = ?
            ");
            $stmt->execute([
                $data['premium'],
                $data['start_date'],
                $data['end_date'],
                $data['status'] ?? 'active',
                $id
            ]);

            // Get updated policy
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.name as client_name,
                    c.email as client_email
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            echo json_encode($policy);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while updating policy']);
        }
    }

    public function delete($id) {
        try {
            // Check if policy exists
            $stmt = $this->db->prepare("SELECT id FROM policies WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                http_response_code(404);
                echo json_encode(['message' => 'Policy not found']);
                return;
            }

            // Check if policy has claims
            $stmt = $this->db->prepare("SELECT COUNT(*) FROM claims WHERE policy_id = ?");
            $stmt->execute([$id]);
            if ($stmt->fetchColumn() > 0) {
                http_response_code(400);
                echo json_encode(['message' => 'Cannot delete policy with existing claims']);
                return;
            }

            // Delete policy
            $stmt = $this->db->prepare("DELETE FROM policies WHERE id = ?");
            $stmt->execute([$id]);

            echo json_encode(['message' => 'Policy deleted successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while deleting policy']);
        }
    }

    private function generatePolicyNumber($type) {
        $prefix = strtoupper(substr($type, 0, 1));
        $timestamp = time();
        $random = rand(1000, 9999);
        return "{$prefix}{$timestamp}{$random}";
    }
}
