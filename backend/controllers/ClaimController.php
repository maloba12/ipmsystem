<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class ClaimController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function index() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
        
        try {
            $stmt = $this->db->query("
                SELECT cl.*, 
                       p.policy_number,
                       c.first_name as client_first_name,
                       c.last_name as client_last_name,
                       c.email as client_email,
                       u.first_name as adjuster_first_name,
                       u.last_name as adjuster_last_name
                FROM claims cl
                JOIN policies p ON cl.policy_id = p.id
                JOIN clients c ON p.client_id = c.id
                LEFT JOIN users u ON cl.assigned_to = u.id
                WHERE cl.status != 'deleted'
                ORDER BY cl.created_at DESC
            ");
            $claims = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse(['claims' => $claims]);
        } catch (PDOException $e) {
            error_log("Error fetching claims: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch claims'], 500);
        }
    }

    public function show($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT cl.*, 
                       p.policy_number,
                       p.policy_type,
                       p.coverage_amount,
                       c.first_name as client_first_name,
                       c.last_name as client_last_name,
                       c.email as client_email,
                       c.phone as client_phone,
                       u.first_name as adjuster_first_name,
                       u.last_name as adjuster_last_name
                FROM claims cl
                JOIN policies p ON cl.policy_id = p.id
                JOIN clients c ON p.client_id = c.id
                LEFT JOIN users u ON cl.assigned_to = u.id
                WHERE cl.id = ?
            ");
            $stmt->execute([$id]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                sendJsonResponse(['error' => 'Claim not found'], 404);
            }

            // Get claim documents
            $stmt = $this->db->prepare("
                SELECT * FROM claim_documents 
                WHERE claim_id = ? 
                ORDER BY created_at DESC
            ");
            $stmt->execute([$id]);
            $claim['documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get claim history
            $stmt = $this->db->prepare("
                SELECT ch.*, 
                       u.first_name as user_first_name,
                       u.last_name as user_last_name
                FROM claim_history ch
                LEFT JOIN users u ON ch.user_id = u.id
                WHERE ch.claim_id = ?
                ORDER BY ch.created_at DESC
            ");
            $stmt->execute([$id]);
            $claim['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['claim' => $claim]);
        } catch (PDOException $e) {
            error_log("Error fetching claim: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch claim details'], 500);
        }
    }

    public function store() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['policy_id', 'claim_type', 'claim_amount', 'incident_date', 'description'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                sendJsonResponse(['error' => "Field {$field} is required"], 400);
            }
        }

        try {
            // Check if policy exists and is active
            $stmt = $this->db->prepare("
                SELECT id, status, end_date 
                FROM policies 
                WHERE id = ? AND status = 'active'
            ");
            $stmt->execute([$data['policy_id']]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                sendJsonResponse(['error' => 'Invalid or inactive policy'], 400);
            }

            // Check if incident date is within policy period
            if (strtotime($data['incident_date']) > strtotime($policy['end_date'])) {
                sendJsonResponse(['error' => 'Incident date is outside policy period'], 400);
            }

            // Generate claim number
            $claim_number = $this->generateClaimNumber();

            // Insert new claim
            $stmt = $this->db->prepare("
                INSERT INTO claims (
                    claim_number, policy_id, claim_type, claim_amount,
                    incident_date, description, status, created_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'pending', ?, NOW())
            ");

            $stmt->execute([
                $claim_number,
                $data['policy_id'],
                $data['claim_type'],
                $data['claim_amount'],
                $data['incident_date'],
                $data['description'],
                $_SESSION['user_id']
            ]);

            $claimId = $this->db->lastInsertId();
            
            // Add to claim history
            $this->addToHistory($claimId, 'created', 'Claim created');

            // Log activity
            logActivity($_SESSION['user_id'], 'create_claim', "Created new claim: {$claim_number}");

            sendJsonResponse([
                'message' => 'Claim created successfully',
                'claim_id' => $claimId,
                'claim_number' => $claim_number
            ], 201);

        } catch (PDOException $e) {
            error_log("Error creating claim: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create claim'], 500);
        }
    }

    public function update($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // Check if claim exists
            $stmt = $this->db->prepare("SELECT id, status FROM claims WHERE id = ?");
            $stmt->execute([$id]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                sendJsonResponse(['error' => 'Claim not found'], 404);
            }

            // Build update query dynamically based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = ['claim_amount', 'description', 'status', 'assigned_to', 'adjuster_notes'];
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

            $sql = "UPDATE claims SET " . implode(', ', $updates) . ", 
                    updated_by = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Add to claim history
            $this->addToHistory($id, 'updated', 'Claim updated');

            // Log activity
            logActivity($_SESSION['user_id'], 'update_claim', "Updated claim ID: {$id}");

            sendJsonResponse(['message' => 'Claim updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating claim: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update claim'], 500);
        }
    }

    public function assignAdjuster($id) {
        AuthMiddleware::requireRole('admin');
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        if (empty($data['adjuster_id'])) {
            sendJsonResponse(['error' => 'Adjuster ID is required'], 400);
        }

        try {
            // Check if adjuster exists and has correct role
            $stmt = $this->db->prepare("
                SELECT id FROM users 
                WHERE id = ? AND role = 'adjuster' AND status = 'active'
            ");
            $stmt->execute([$data['adjuster_id']]);
            if (!$stmt->fetch()) {
                sendJsonResponse(['error' => 'Invalid adjuster'], 400);
            }

            // Update claim
            $stmt = $this->db->prepare("
                UPDATE claims 
                SET assigned_to = ?,
                    status = 'assigned',
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $data['adjuster_id'],
                $_SESSION['user_id'],
                $id
            ]);

            // Add to claim history
            $this->addToHistory($id, 'assigned', "Claim assigned to adjuster ID: {$data['adjuster_id']}");

            // Log activity
            logActivity($_SESSION['user_id'], 'assign_claim', "Assigned claim ID: {$id} to adjuster ID: {$data['adjuster_id']}");

            sendJsonResponse(['message' => 'Claim assigned successfully']);

        } catch (PDOException $e) {
            error_log("Error assigning claim: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to assign claim'], 500);
        }
    }

    public function uploadDocument($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
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
            // Check if claim exists
            $stmt = $this->db->prepare("SELECT id FROM claims WHERE id = ?");
            $stmt->execute([$id]);
            if (!$stmt->fetch()) {
                sendJsonResponse(['error' => 'Claim not found'], 404);
            }

            // Generate unique filename
            $filename = uniqid() . '_' . basename($file['name']);
            $upload_path = APP_ROOT . '/uploads/claim_documents/' . $filename;

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
                INSERT INTO claim_documents (
                    claim_id, filename, original_name, file_type,
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

            // Add to claim history
            $this->addToHistory($id, 'document_uploaded', "Document uploaded: {$file['name']}");

            // Log activity
            logActivity($_SESSION['user_id'], 'upload_claim_document', "Uploaded document for claim ID: {$id}");

            sendJsonResponse([
                'message' => 'Document uploaded successfully',
                'filename' => $filename
            ]);

        } catch (Exception $e) {
            error_log("Error uploading document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to upload document'], 500);
        }
    }

    private function generateClaimNumber() {
        $prefix = 'CLM';
        $timestamp = time();
        $random = rand(1000, 9999);
        return "{$prefix}-{$timestamp}-{$random}";
    }

    private function addToHistory($claim_id, $action, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO claim_history (
                    claim_id, action, details, user_id, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $claim_id,
                $action,
                $details,
                $_SESSION['user_id']
            ]);
        } catch (PDOException $e) {
            error_log("Error adding to claim history: " . $e->getMessage());
        }
    }
}
