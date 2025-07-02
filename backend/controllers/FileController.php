<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class FileController {
    private $db;
    private $uploadDir;
    private $allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'];
    private $maxFileSize = 10 * 1024 * 1024; // 10MB

    public function __construct($db) {
        $this->db = $db;
        $this->uploadDir = __DIR__ . '/../../uploads/';
        if (!file_exists($this->uploadDir)) {
            mkdir($this->uploadDir, 0777, true);
        }
    }

    public function uploadFile() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'client']);
        
        try {
            if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
                throw new Exception('No file uploaded or upload error occurred');
            }

            $file = $_FILES['file'];
            $fileType = mime_content_type($file['tmp_name']);
            $fileName = basename($file['name']);
            $fileSize = $file['size'];
            $fileExtension = strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
            
            // Validate file type
            if (!in_array($fileType, $this->allowedTypes)) {
                throw new Exception('Invalid file type. Only PDF and Excel files are allowed.');
            }
            
            // Validate file size
            if ($fileSize > $this->maxFileSize) {
                throw new Exception('File size exceeds the maximum limit of 10MB.');
            }

            // Generate unique filename
            $uniqueFileName = uniqid() . '.' . $fileExtension;
            $targetPath = $this->uploadDir . $uniqueFileName;

            // Move uploaded file
            if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
                throw new Exception('Failed to move uploaded file.');
            }

            // Get user ID
            $userId = $_SESSION['user_id'];
            $userRole = $_SESSION['user']['role'];

            // Insert into database
            $stmt = $this->db->prepare("
                INSERT INTO files (user_id, user_role, file_name, file_path, file_type, file_size, uploaded_at)
                VALUES (?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $userId,
                $userRole,
                $fileName,
                $uniqueFileName,
                $fileType,
                $fileSize
            ]);

            $fileId = $this->db->lastInsertId();

            sendJsonResponse([
                'success' => true,
                'message' => 'File uploaded successfully',
                'file_id' => $fileId,
                'file_name' => $fileName,
                'file_path' => $uniqueFileName
            ]);

        } catch (Exception $e) {
            error_log("File upload error: " . $e->getMessage());
            sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function downloadFile($fileId) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'client']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM files WHERE id = ?
            ");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                throw new Exception('File not found');
            }

            // Check if user has permission to download
            $userId = $_SESSION['user_id'];
            $userRole = $_SESSION['user']['role'];

            if ($userRole !== 'Admin' && ($file['user_id'] !== $userId || $file['user_role'] !== $userRole)) {
                throw new Exception('You do not have permission to download this file');
            }

            $filePath = $this->uploadDir . $file['file_path'];

            if (!file_exists($filePath)) {
                throw new Exception('File not found on server');
            }

            // Set headers
            header('Content-Description: File Transfer');
            header('Content-Type: ' . $file['file_type']);
            header('Content-Disposition: attachment; filename="' . $file['file_name'] . '"');
            header('Content-Length: ' . $file['file_size']);

            // Read and output file
            readfile($filePath);
            exit();

        } catch (Exception $e) {
            error_log("File download error: " . $e->getMessage());
            sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function listFiles() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'client']);
        
        try {
            $userId = $_SESSION['user_id'];
            $userRole = $_SESSION['user']['role'];

            $query = "SELECT * FROM files WHERE 1 = 1";
            $params = [];

            if ($userRole !== 'Admin') {
                $query .= " AND user_id = ? AND user_role = ?";
                $params = [$userId, $userRole];
            }

            $stmt = $this->db->prepare($query);
            $stmt->execute($params);
            $files = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'success' => true,
                'files' => $files
            ]);

        } catch (Exception $e) {
            error_log("File listing error: " . $e->getMessage());
            sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }

    public function deleteFile($fileId) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'client']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM files WHERE id = ?
            ");
            $stmt->execute([$fileId]);
            $file = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$file) {
                throw new Exception('File not found');
            }

            $userId = $_SESSION['user_id'];
            $userRole = $_SESSION['user']['role'];

            if ($userRole !== 'Admin' && ($file['user_id'] !== $userId || $file['user_role'] !== $userRole)) {
                throw new Exception('You do not have permission to delete this file');
            }

            $filePath = $this->uploadDir . $file['file_path'];

            if (file_exists($filePath)) {
                unlink($filePath);
            }

            $stmt = $this->db->prepare("
                DELETE FROM files WHERE id = ?
            ");
            $stmt->execute([$fileId]);

            sendJsonResponse([
                'success' => true,
                'message' => 'File deleted successfully'
            ]);

        } catch (Exception $e) {
            error_log("File deletion error: " . $e->getMessage());
            sendJsonResponse([
                'success' => false,
                'message' => $e->getMessage()
            ], 400);
        }
    }
}
