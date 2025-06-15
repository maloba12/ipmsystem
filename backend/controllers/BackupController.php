<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class BackupController {
    private $db;
    private $backup_path;
    private $db_config;

    public function __construct() {
        $this->db = getDBConnection();
        $this->backup_path = __DIR__ . '/../backups/';
        $this->db_config = [
            'host' => DB_HOST,
            'name' => DB_NAME,
            'user' => DB_USER,
            'pass' => DB_PASS
        ];
    }

    public function createBackup() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $backup_type = $_POST['type'] ?? 'full';
            $description = $_POST['description'] ?? '';
            
            if (!in_array($backup_type, ['full', 'database', 'files'])) {
                sendJsonResponse(['error' => 'Invalid backup type'], 400);
                return;
            }

            $timestamp = date('Y-m-d_H-i-s');
            $backup_dir = $this->backup_path . $timestamp;
            
            if (!file_exists($backup_dir)) {
                mkdir($backup_dir, 0755, true);
            }

            $backup_files = [];

            // Database backup
            if ($backup_type === 'full' || $backup_type === 'database') {
                $db_backup = $this->backupDatabase($backup_dir);
                if (!$db_backup['success']) {
                    throw new Exception($db_backup['error']);
                }
                $backup_files[] = $db_backup['file'];
            }

            // File system backup
            if ($backup_type === 'full' || $backup_type === 'files') {
                $files_backup = $this->backupFiles($backup_dir);
                if (!$files_backup['success']) {
                    throw new Exception($files_backup['error']);
                }
                $backup_files = array_merge($backup_files, $files_backup['files']);
            }

            // Create backup record
            $stmt = $this->db->prepare("
                INSERT INTO backups (
                    backup_type,
                    description,
                    backup_path,
                    file_count,
                    total_size,
                    created_by
                ) VALUES (?, ?, ?, ?, ?, ?)
            ");

            $total_size = 0;
            foreach ($backup_files as $file) {
                $total_size += filesize($file);
            }

            $stmt->execute([
                $backup_type,
                $description,
                $backup_dir,
                count($backup_files),
                $total_size,
                $_SESSION['user_id']
            ]);

            $backup_id = $this->db->lastInsertId();

            logActivity(
                $_SESSION['user_id'],
                'backup_created',
                "Created {$backup_type} backup ID: {$backup_id}"
            );

            sendJsonResponse([
                'message' => 'Backup created successfully',
                'backup_id' => $backup_id,
                'files' => $backup_files
            ]);

        } catch (Exception $e) {
            error_log("Error creating backup: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create backup'], 500);
        }
    }

    private function backupDatabase($backup_dir) {
        try {
            $filename = "database_{$this->db_config['name']}_" . date('Y-m-d_H-i-s') . ".sql";
            $filepath = $backup_dir . '/' . $filename;

            // Create mysqldump command
            $command = sprintf(
                'mysqldump --host=%s --user=%s --password=%s %s > %s',
                escapeshellarg($this->db_config['host']),
                escapeshellarg($this->db_config['user']),
                escapeshellarg($this->db_config['pass']),
                escapeshellarg($this->db_config['name']),
                escapeshellarg($filepath)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database backup failed');
            }

            return [
                'success' => true,
                'file' => $filepath
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function backupFiles($backup_dir) {
        try {
            $files = [];
            $exclude_dirs = ['backups', 'logs', 'temp', 'cache'];
            $root_dir = __DIR__ . '/../';

            // Create files backup
            $filename = "files_" . date('Y-m-d_H-i-s') . ".zip";
            $filepath = $backup_dir . '/' . $filename;

            $zip = new ZipArchive();
            if ($zip->open($filepath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                throw new Exception('Failed to create zip file');
            }

            $iterator = new RecursiveIteratorIterator(
                new RecursiveDirectoryIterator($root_dir),
                RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($iterator as $file) {
                if ($file->isFile()) {
                    $file_path = $file->getRealPath();
                    $relative_path = substr($file_path, strlen($root_dir));

                    // Skip excluded directories
                    $skip = false;
                    foreach ($exclude_dirs as $exclude_dir) {
                        if (strpos($relative_path, $exclude_dir . '/') === 0) {
                            $skip = true;
                            break;
                        }
                    }

                    if (!$skip) {
                        $zip->addFile($file_path, $relative_path);
                        $files[] = $file_path;
                    }
                }
            }

            $zip->close();

            return [
                'success' => true,
                'files' => [$filepath]
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function restoreBackup() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $backup_id = $_POST['backup_id'] ?? null;
            
            if (!$backup_id) {
                sendJsonResponse(['error' => 'Backup ID is required'], 400);
                return;
            }

            // Get backup details
            $stmt = $this->db->prepare("
                SELECT * FROM backups 
                WHERE id = ?
            ");
            $stmt->execute([$backup_id]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                sendJsonResponse(['error' => 'Backup not found'], 404);
                return;
            }

            $this->db->beginTransaction();

            try {
                // Restore database if needed
                if ($backup['backup_type'] === 'full' || $backup['backup_type'] === 'database') {
                    $db_files = glob($backup['backup_path'] . '/database_*.sql');
                    if (!empty($db_files)) {
                        $result = $this->restoreDatabase($db_files[0]);
                        if (!$result['success']) {
                            throw new Exception($result['error']);
                        }
                    }
                }

                // Restore files if needed
                if ($backup['backup_type'] === 'full' || $backup['backup_type'] === 'files') {
                    $file_backups = glob($backup['backup_path'] . '/files_*.zip');
                    if (!empty($file_backups)) {
                        $result = $this->restoreFiles($file_backups[0]);
                        if (!$result['success']) {
                            throw new Exception($result['error']);
                        }
                    }
                }

                // Update backup record
                $stmt = $this->db->prepare("
                    UPDATE backups 
                    SET last_restored_at = NOW(),
                        restored_by = ?
                    WHERE id = ?
                ");
                $stmt->execute([$_SESSION['user_id'], $backup_id]);

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'backup_restored',
                    "Restored backup ID: {$backup_id}"
                );

                sendJsonResponse(['message' => 'Backup restored successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error restoring backup: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to restore backup'], 500);
        }
    }

    private function restoreDatabase($backup_file) {
        try {
            // Create restore command
            $command = sprintf(
                'mysql --host=%s --user=%s --password=%s %s < %s',
                escapeshellarg($this->db_config['host']),
                escapeshellarg($this->db_config['user']),
                escapeshellarg($this->db_config['pass']),
                escapeshellarg($this->db_config['name']),
                escapeshellarg($backup_file)
            );

            exec($command, $output, $return_var);

            if ($return_var !== 0) {
                throw new Exception('Database restore failed');
            }

            return ['success' => true];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function restoreFiles($backup_file) {
        try {
            $root_dir = __DIR__ . '/../';
            $zip = new ZipArchive();

            if ($zip->open($backup_file) !== true) {
                throw new Exception('Failed to open zip file');
            }

            $zip->extractTo($root_dir);
            $zip->close();

            return ['success' => true];

        } catch (Exception $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    public function getBackups() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 10;
            $offset = ($page - 1) * $limit;

            // Get total count
            $stmt = $this->db->query("SELECT COUNT(*) as total FROM backups");
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get backups with user information
            $stmt = $this->db->prepare("
                SELECT 
                    b.*,
                    u.username as created_by_username,
                    r.username as restored_by_username
                FROM backups b
                LEFT JOIN users u ON b.created_by = u.id
                LEFT JOIN users r ON b.restored_by = r.id
                ORDER BY b.created_at DESC
                LIMIT ? OFFSET ?
            ");
            $stmt->execute([$limit, $offset]);
            $backups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'backups' => $backups,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching backups: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch backups'], 500);
        }
    }

    public function deleteBackup() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $backup_id = $_POST['backup_id'] ?? null;
            
            if (!$backup_id) {
                sendJsonResponse(['error' => 'Backup ID is required'], 400);
                return;
            }

            // Get backup details
            $stmt = $this->db->prepare("
                SELECT * FROM backups 
                WHERE id = ?
            ");
            $stmt->execute([$backup_id]);
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                sendJsonResponse(['error' => 'Backup not found'], 404);
                return;
            }

            $this->db->beginTransaction();

            try {
                // Delete backup files
                if (file_exists($backup['backup_path'])) {
                    $this->deleteDirectory($backup['backup_path']);
                }

                // Delete backup record
                $stmt = $this->db->prepare("
                    DELETE FROM backups 
                    WHERE id = ?
                ");
                $stmt->execute([$backup_id]);

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'backup_deleted',
                    "Deleted backup ID: {$backup_id}"
                );

                sendJsonResponse(['message' => 'Backup deleted successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error deleting backup: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete backup'], 500);
        }
    }

    private function deleteDirectory($dir) {
        if (!file_exists($dir)) {
            return true;
        }

        if (!is_dir($dir)) {
            return unlink($dir);
        }

        foreach (scandir($dir) as $item) {
            if ($item == '.' || $item == '..') {
                continue;
            }

            if (!$this->deleteDirectory($dir . DIRECTORY_SEPARATOR . $item)) {
                return false;
            }
        }

        return rmdir($dir);
    }

    public function scheduleBackup() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $backup_type = $_POST['type'] ?? null;
            $schedule = $_POST['schedule'] ?? null;
            $description = $_POST['description'] ?? '';
            
            if (!$backup_type || !$schedule) {
                sendJsonResponse(['error' => 'Backup type and schedule are required'], 400);
                return;
            }

            if (!in_array($backup_type, ['full', 'database', 'files'])) {
                sendJsonResponse(['error' => 'Invalid backup type'], 400);
                return;
            }

            if (!in_array($schedule, ['daily', 'weekly', 'monthly'])) {
                sendJsonResponse(['error' => 'Invalid schedule'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO backup_schedules (
                    backup_type,
                    schedule,
                    description,
                    is_active,
                    created_by
                ) VALUES (?, ?, ?, true, ?)
            ");
            $stmt->execute([
                $backup_type,
                $schedule,
                $description,
                $_SESSION['user_id']
            ]);

            $schedule_id = $this->db->lastInsertId();

            logActivity(
                $_SESSION['user_id'],
                'backup_scheduled',
                "Scheduled {$schedule} {$backup_type} backup"
            );

            sendJsonResponse([
                'message' => 'Backup scheduled successfully',
                'schedule_id' => $schedule_id
            ]);

        } catch (PDOException $e) {
            error_log("Error scheduling backup: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to schedule backup'], 500);
        }
    }

    public function getBackupSchedules() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    bs.*,
                    u.username as created_by_username
                FROM backup_schedules bs
                LEFT JOIN users u ON bs.created_by = u.id
                ORDER BY bs.created_at DESC
            ");
            $stmt->execute();
            $schedules = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['schedules' => $schedules]);

        } catch (PDOException $e) {
            error_log("Error fetching backup schedules: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch backup schedules'], 500);
        }
    }

    public function updateBackupSchedule() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $schedule_id = $_POST['schedule_id'] ?? null;
            $is_active = $_POST['is_active'] ?? null;
            
            if (!$schedule_id || !isset($is_active)) {
                sendJsonResponse(['error' => 'Schedule ID and active status are required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE backup_schedules 
                SET is_active = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$is_active, $schedule_id]);

            logActivity(
                $_SESSION['user_id'],
                'backup_schedule_updated',
                "Updated backup schedule ID: {$schedule_id}"
            );

            sendJsonResponse(['message' => 'Backup schedule updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating backup schedule: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update backup schedule'], 500);
        }
    }

    public function deleteBackupSchedule() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $schedule_id = $_POST['schedule_id'] ?? null;
            
            if (!$schedule_id) {
                sendJsonResponse(['error' => 'Schedule ID is required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                DELETE FROM backup_schedules 
                WHERE id = ?
            ");
            $stmt->execute([$schedule_id]);

            logActivity(
                $_SESSION['user_id'],
                'backup_schedule_deleted',
                "Deleted backup schedule ID: {$schedule_id}"
            );

            sendJsonResponse(['message' => 'Backup schedule deleted successfully']);

        } catch (PDOException $e) {
            error_log("Error deleting backup schedule: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete backup schedule'], 500);
        }
    }
}