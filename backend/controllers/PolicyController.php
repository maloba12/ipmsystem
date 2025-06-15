<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class PolicyController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function index() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       c.first_name as client_first_name,
                       c.last_name as client_last_name,
                       c.email as client_email
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                WHERE p.status != 'deleted'
                ORDER BY p.created_at DESC
            ");
            $policies = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse(['policies' => $policies]);
        } catch (PDOException $e) {
            error_log("Error fetching policies: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch policies'], 500);
        }
    }

    public function show($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       c.first_name as client_first_name,
                       c.last_name as client_last_name,
                       c.email as client_email,
                       c.phone as client_phone,
                       COUNT(cl.id) as total_claims,
                       SUM(cl.claim_amount) as total_claims_amount
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                LEFT JOIN claims cl ON p.id = cl.policy_id
                WHERE p.id = ?
                GROUP BY p.id
            ");
            $stmt->execute([$id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                sendJsonResponse(['error' => 'Policy not found'], 404);
            }

            // Get policy documents
            $stmt = $this->db->prepare("
                SELECT * FROM policy_documents 
                WHERE policy_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$id]);
            $policy['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['policy' => $policy]);
        } catch (PDOException $e) {
            error_log("Error fetching policy: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch policy details'], 500);
        }
    }

    public function store() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['client_id', 'policy_type', 'coverage_amount', 'start_date', 'end_date'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                sendJsonResponse(['error' => "Field {$field} is required"], 400);
            }
        }

        try {
            // Check if client exists and is active
            $stmt = $this->db->prepare("SELECT id FROM clients WHERE id = ? AND status = 'active'");
            $stmt->execute([$data['client_id']]);
            if (!$stmt->fetch()) {
                sendJsonResponse(['error' => 'Invalid or inactive client'], 400);
            }

            // Calculate premium based on policy type and coverage
            $premium = $this->calculatePremium($data['policy_type'], $data['coverage_amount']);

            // Generate policy number
            $policy_number = $this->generatePolicyNumber($data['policy_type']);

            // Insert new policy
            $stmt = $this->db->prepare("
                INSERT INTO policies (
                    policy_number, client_id, policy_type, coverage_amount,
                    premium_amount, start_date, end_date, status,
                    created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, NOW())
            ");

            $stmt->execute([
                $policy_number,
                $data['client_id'],
                $data['policy_type'],
                $data['coverage_amount'],
                $premium,
                $data['start_date'],
                $data['end_date'],
                $_SESSION['user_id']
            ]);

            $policyId = $this->db->lastInsertId();
            
            // Log activity
            logActivity($_SESSION['user_id'], 'create_policy', "Created new policy: {$policy_number}");

            sendJsonResponse([
                'message' => 'Policy created successfully',
                'policy_id' => $policyId,
                'policy_number' => $policy_number
            ], 201);

        } catch (PDOException $e) {
            error_log("Error creating policy: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create policy'], 500);
        }
    }

    public function update($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // Check if policy exists
            $stmt = $this->db->prepare("SELECT id, status FROM policies WHERE id = ?");
            $stmt->execute([$id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                sendJsonResponse(['error' => 'Policy not found'], 404);
            }

            // Only allow updates to active policies
            if ($policy['status'] !== 'active') {
                sendJsonResponse(['error' => 'Cannot update inactive policy'], 400);
            }

            // Build update query dynamically based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = ['coverage_amount', 'premium_amount', 'end_date', 'status'];
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

            $sql = "UPDATE policies SET " . implode(', ', $updates) . ", 
                    updated_by = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Log activity
            logActivity($_SESSION['user_id'], 'update_policy', "Updated policy ID: {$id}");

            sendJsonResponse(['message' => 'Policy updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating policy: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update policy'], 500);
        }
    }

    public function renew($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        try {
            // Get current policy
            $stmt = $this->db->prepare("
                SELECT * FROM policies 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                sendJsonResponse(['error' => 'Active policy not found'], 404);
            }

            // Calculate new dates
            $start_date = date('Y-m-d');
            $end_date = date('Y-m-d', strtotime('+1 year'));

            // Create new policy record
            $stmt = $this->db->prepare("
                INSERT INTO policies (
                    policy_number, client_id, policy_type, coverage_amount,
                    premium_amount, start_date, end_date, status,
                    previous_policy_id, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, 'active', ?, ?, NOW())
            ");

            $new_policy_number = $this->generatePolicyNumber($policy['policy_type']);
            
            $stmt->execute([
                $new_policy_number,
                $policy['client_id'],
                $policy['policy_type'],
                $policy['coverage_amount'],
                $policy['premium_amount'],
                $start_date,
                $end_date,
                $id,
                $_SESSION['user_id']
            ]);

            $new_policy_id = $this->db->lastInsertId();

            // Update old policy status
            $stmt = $this->db->prepare("
                UPDATE policies 
                SET status = 'renewed', 
                    updated_by = ?, 
                    updated_at = NOW() 
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $id]);

            // Log activity
            logActivity($_SESSION['user_id'], 'renew_policy', "Renewed policy ID: {$id} to new policy ID: {$new_policy_id}");

            sendJsonResponse([
                'message' => 'Policy renewed successfully',
                'new_policy_id' => $new_policy_id,
                'new_policy_number' => $new_policy_number
            ]);

        } catch (PDOException $e) {
            error_log("Error renewing policy: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to renew policy'], 500);
        }
    }

    public function uploadDocument($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        if (!isset($_FILES['document'])) {
            sendJsonResponse(['error' => 'No document uploaded'], 400);
        }

        $file = $_FILES['document'];
        $allowed_types = ['application/pdf', 'image/jpeg', 'image/png'];
        
        if (!in_array($file['type'], $allowed_types)) {
            sendJsonResponse(['error' => 'Invalid file type'], 400);
        }

        try {
            // Check if policy exists
            $stmt = $this->db->prepare("SELECT id FROM policies WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                sendJsonResponse(['error' => 'Policy not found'], 404);
            }

            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $upload_path = APP_ROOT . '/uploads/policy_documents/' . $filename;

            // Create directory if it doesn't exist
            if (!file_exists(dirname($upload_path))) {
                mkdir(dirname($upload_path), 0777, true);
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $upload_path)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Save document record
            $stmt = $this->db->prepare("
                INSERT INTO policy_documents (
                    policy_id, filename, original_name, file_type,
                    file_size, uploaded_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $id,
                $filename,
                $file['name'],
                $file['type'],
                $file['size'],
                $_SESSION['user_id']
            ]);

            // Log activity
            logActivity($_SESSION['user_id'], 'upload_document', "Uploaded document for policy ID: {$id}");

            sendJsonResponse([
                'message' => 'Document uploaded successfully',
                'filename' => $filename
            ]);

        } catch (Exception $e) {
            error_log("Error uploading document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to upload document'], 500);
        }
    }

    private function calculatePremium($policy_type, $coverage_amount) {
        // Basic premium calculation logic
        $base_rate = 0;
        switch ($policy_type) {
            case 'auto':
                $base_rate = 0.05;
                break;
            case 'home':
                $base_rate = 0.03;
                break;
            case 'life':
                $base_rate = 0.02;
                break;
            default:
                $base_rate = 0.04;
        }
        
        return $coverage_amount * $base_rate;
    }

    private function generatePolicyNumber($policy_type) {
        $prefix = strtoupper(substr($policy_type, 0, 3));
        $timestamp = time();
        $random = rand(1000, 9999);
        return "{$prefix}-{$timestamp}-{$random}";
    }
}
