<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class DocumentController {
    private $db;
    private $upload_dir;
    private $allowed_types;
    private $max_file_size;

    public function __construct() {
        $this->db = getDBConnection();
        $this->upload_dir = __DIR__ . '/../uploads/documents/';
        $this->allowed_types = [
            'application/pdf',
            'image/jpeg',
            'image/png',
            'application/msword',
            'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
        ];
        $this->max_file_size = 10 * 1024 * 1024; // 10MB
    }

    public function upload() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
        
        try {
            if (!isset($_FILES['document']) || $_FILES['document']['error'] !== UPLOAD_ERR_OK) {
                sendJsonResponse(['error' => 'No file uploaded or upload error'], 400);
                return;
            }

            $file = $_FILES['document'];
            $client_id = $_POST['client_id'] ?? null;
            $policy_id = $_POST['policy_id'] ?? null;
            $claim_id = $_POST['claim_id'] ?? null;
            $document_type = $_POST['document_type'] ?? null;
            $expiry_date = $_POST['expiry_date'] ?? null;

            // Validate inputs
            if (!$client_id || !$document_type) {
                sendJsonResponse(['error' => 'Client ID and document type are required'], 400);
                return;
            }

            // Validate file
            if (!in_array($file['type'], $this->allowed_types)) {
                sendJsonResponse(['error' => 'Invalid file type'], 400);
                return;
            }

            if ($file['size'] > $this->max_file_size) {
                sendJsonResponse(['error' => 'File size exceeds limit'], 400);
                return;
            }

            // Generate unique filename
            $extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = uniqid() . '_' . time() . '.' . $extension;
            $filepath = $this->upload_dir . $filename;

            // Create upload directory if it doesn't exist
            if (!file_exists($this->upload_dir)) {
                mkdir($this->upload_dir, 0777, true);
            }

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $filepath)) {
                throw new Exception('Failed to move uploaded file');
            }

            // Save document record
            $stmt = $this->db->prepare("
                INSERT INTO documents (
                    client_id, policy_id, claim_id, document_type,
                    document_name, file_path, file_type, file_size,
                    expiry_date, uploaded_by, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");

            $stmt->execute([
                $client_id,
                $policy_id,
                $claim_id,
                $document_type,
                $file['name'],
                $filename,
                $file['type'],
                $file['size'],
                $expiry_date,
                $_SESSION['user_id']
            ]);

            $document_id = $this->db->lastInsertId();

            // Log activity
            logActivity(
                $_SESSION['user_id'],
                'document_uploaded',
                "Uploaded document: {$file['name']} for client ID: {$client_id}"
            );

            // Create notification for document expiry if expiry date is set
            if ($expiry_date) {
                $notificationController = new NotificationController();
                $notificationController->createDocumentExpiryNotification($document_id);
            }

            sendJsonResponse([
                'message' => 'Document uploaded successfully',
                'document_id' => $document_id
            ]);

        } catch (Exception $e) {
            error_log("Error uploading document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to upload document'], 500);
        }
    }

    public function getDocuments() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $client_id = $_GET['client_id'] ?? null;
            $policy_id = $_GET['policy_id'] ?? null;
            $claim_id = $_GET['claim_id'] ?? null;
            $document_type = $_GET['document_type'] ?? null;

            $query = "
                SELECT 
                    d.*,
                    c.first_name as client_first_name,
                    c.last_name as client_last_name,
                    p.policy_number,
                    cl.claim_number,
                    u.first_name as uploader_first_name,
                    u.last_name as uploader_last_name
                FROM documents d
                JOIN clients c ON d.client_id = c.id
                LEFT JOIN policies p ON d.policy_id = p.id
                LEFT JOIN claims cl ON d.claim_id = cl.id
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE 1=1
            ";
            $params = [];

            if ($client_id) {
                $query .= " AND d.client_id = ?";
                $params[] = $client_id;
            }
            if ($policy_id) {
                $query .= " AND d.policy_id = ?";
                $params[] = $policy_id;
            }
            if ($claim_id) {
                $query .= " AND d.claim_id = ?";
                $params[] = $claim_id;
            }
            if ($document_type) {
                $query .= " AND d.document_type = ?";
                $params[] = $document_type;
            }

            $query .= " ORDER BY d.created_at DESC";

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['documents' => $documents]);

        } catch (PDOException $e) {
            error_log("Error fetching documents: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch documents'], 500);
        }
    }

    public function getDocument($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    c.first_name as client_first_name,
                    c.last_name as client_last_name,
                    p.policy_number,
                    cl.claim_number,
                    u.first_name as uploader_first_name,
                    u.last_name as uploader_last_name
                FROM documents d
                JOIN clients c ON d.client_id = c.id
                LEFT JOIN policies p ON d.policy_id = p.id
                LEFT JOIN claims cl ON d.claim_id = cl.id
                LEFT JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                sendJsonResponse(['error' => 'Document not found'], 404);
                return;
            }

            sendJsonResponse(['document' => $document]);

        } catch (PDOException $e) {
            error_log("Error fetching document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch document'], 500);
        }
    }

    public function download($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM documents WHERE id = ?
            ");
            $stmt->execute([$id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                sendJsonResponse(['error' => 'Document not found'], 404);
                return;
            }

            $filepath = $this->upload_dir . $document['file_path'];
            if (!file_exists($filepath)) {
                sendJsonResponse(['error' => 'File not found'], 404);
                return;
            }

            // Log download activity
            logActivity(
                $_SESSION['user_id'],
                'document_downloaded',
                "Downloaded document: {$document['document_name']}"
            );

            // Set headers for download
            header('Content-Type: ' . $document['file_type']);
            header('Content-Disposition: attachment; filename="' . $document['document_name'] . '"');
            header('Content-Length: ' . filesize($filepath));
            header('Cache-Control: private, max-age=0, must-revalidate');
            header('Pragma: public');

            readfile($filepath);
            exit;

        } catch (Exception $e) {
            error_log("Error downloading document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to download document'], 500);
        }
    }

    public function update() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $document_id = $_POST['document_id'] ?? null;
            $document_type = $_POST['document_type'] ?? null;
            $expiry_date = $_POST['expiry_date'] ?? null;

            if (!$document_id || !$document_type) {
                sendJsonResponse(['error' => 'Document ID and type are required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE documents 
                SET document_type = ?,
                    expiry_date = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$document_type, $expiry_date, $document_id]);

            if ($stmt->rowCount() > 0) {
                // Create notification for document expiry if expiry date is set
                if ($expiry_date) {
                    $notificationController = new NotificationController();
                    $notificationController->createDocumentExpiryNotification($document_id);
                }

                logActivity(
                    $_SESSION['user_id'],
                    'document_updated',
                    "Updated document ID: {$document_id}"
                );

                sendJsonResponse(['message' => 'Document updated successfully']);
            } else {
                sendJsonResponse(['error' => 'Document not found'], 404);
            }

        } catch (PDOException $e) {
            error_log("Error updating document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update document'], 500);
        }
    }

    public function delete() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $document_id = $_POST['document_id'] ?? null;
            
            if (!$document_id) {
                sendJsonResponse(['error' => 'Document ID is required'], 400);
                return;
            }

            // Get document details before deletion
            $stmt = $this->db->prepare("SELECT * FROM documents WHERE id = ?");
            $stmt->execute([$document_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                sendJsonResponse(['error' => 'Document not found'], 404);
                return;
            }

            // Delete file from storage
            $filepath = $this->upload_dir . $document['file_path'];
            if (file_exists($filepath)) {
                unlink($filepath);
            }

            // Delete from database
            $stmt = $this->db->prepare("DELETE FROM documents WHERE id = ?");
            $stmt->execute([$document_id]);

            logActivity(
                $_SESSION['user_id'],
                'document_deleted',
                "Deleted document: {$document['document_name']}"
            );

            sendJsonResponse(['message' => 'Document deleted successfully']);

        } catch (PDOException $e) {
            error_log("Error deleting document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete document'], 500);
        }
    }

    public function getDocumentTypes() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM document_types 
                ORDER BY type_name
            ");
            $types = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['document_types' => $types]);

        } catch (PDOException $e) {
            error_log("Error fetching document types: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch document types'], 500);
        }
    }

    public function getExpiringDocuments() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        try {
            $days_threshold = $_GET['days'] ?? 30;
            
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    c.first_name as client_first_name,
                    c.last_name as client_last_name,
                    p.policy_number,
                    DATEDIFF(d.expiry_date, CURDATE()) as days_until_expiry
                FROM documents d
                JOIN clients c ON d.client_id = c.id
                LEFT JOIN policies p ON d.policy_id = p.id
                WHERE d.expiry_date IS NOT NULL
                AND d.expiry_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL ? DAY)
                ORDER BY d.expiry_date ASC
            ");
            $stmt->execute([$days_threshold]);
            $documents = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['expiring_documents' => $documents]);

        } catch (PDOException $e) {
            error_log("Error fetching expiring documents: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch expiring documents'], 500);
        }
    }

    public function verifyDocument() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
        
        try {
            $document_id = $_POST['document_id'] ?? null;
            $verification_status = $_POST['verification_status'] ?? null;
            $verification_notes = $_POST['verification_notes'] ?? null;

            if (!$document_id || !$verification_status) {
                sendJsonResponse(['error' => 'Document ID and verification status are required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE documents 
                SET verification_status = ?,
                    verification_notes = ?,
                    verified_by = ?,
                    verified_at = NOW(),
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $verification_status,
                $verification_notes,
                $_SESSION['user_id'],
                $document_id
            ]);

            if ($stmt->rowCount() > 0) {
                logActivity(
                    $_SESSION['user_id'],
                    'document_verified',
                    "Verified document ID: {$document_id} with status: {$verification_status}"
                );

                sendJsonResponse(['message' => 'Document verification updated successfully']);
            } else {
                sendJsonResponse(['error' => 'Document not found'], 404);
            }

        } catch (PDOException $e) {
            error_log("Error verifying document: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to verify document'], 500);
        }
    }
} 