<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class LogController {
    private $db;
    private $log_path;

    public function __construct() {
        $this->db = getDBConnection();
        $this->log_path = __DIR__ . '/../logs/';
    }

    public function getActivityLogs() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 50;
            $offset = ($page - 1) * $limit;
            
            $filters = [
                'user_id' => $_GET['user_id'] ?? null,
                'activity_type' => $_GET['activity_type'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];

            $where_clauses = [];
            $params = [];

            if ($filters['user_id']) {
                $where_clauses[] = "user_id = ?";
                $params[] = $filters['user_id'];
            }
            if ($filters['activity_type']) {
                $where_clauses[] = "activity_type = ?";
                $params[] = $filters['activity_type'];
            }
            if ($filters['start_date']) {
                $where_clauses[] = "created_at >= ?";
                $params[] = $filters['start_date'];
            }
            if ($filters['end_date']) {
                $where_clauses[] = "created_at <= ?";
                $params[] = $filters['end_date'];
            }

            $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM activity_logs {$where_sql}";
            $stmt = $this->db->prepare($count_sql);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get logs with user information
            $sql = "
                SELECT 
                    al.*,
                    u.username,
                    u.email
                FROM activity_logs al
                LEFT JOIN users u ON al.user_id = u.id
                {$where_sql}
                ORDER BY al.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'logs' => $logs,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching activity logs: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch activity logs'], 500);
        }
    }

    public function getErrorLogs() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 50;
            $offset = ($page - 1) * $limit;
            
            $filters = [
                'error_level' => $_GET['error_level'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];

            $where_clauses = [];
            $params = [];

            if ($filters['error_level']) {
                $where_clauses[] = "error_level = ?";
                $params[] = $filters['error_level'];
            }
            if ($filters['start_date']) {
                $where_clauses[] = "created_at >= ?";
                $params[] = $filters['start_date'];
            }
            if ($filters['end_date']) {
                $where_clauses[] = "created_at <= ?";
                $params[] = $filters['end_date'];
            }

            $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM error_logs {$where_sql}";
            $stmt = $this->db->prepare($count_sql);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get error logs
            $sql = "
                SELECT * FROM error_logs
                {$where_sql}
                ORDER BY created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'logs' => $logs,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching error logs: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch error logs'], 500);
        }
    }

    public function getAuditTrail() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $page = $_GET['page'] ?? 1;
            $limit = $_GET['limit'] ?? 50;
            $offset = ($page - 1) * $limit;
            
            $filters = [
                'entity_type' => $_GET['entity_type'] ?? null,
                'entity_id' => $_GET['entity_id'] ?? null,
                'action' => $_GET['action'] ?? null,
                'start_date' => $_GET['start_date'] ?? null,
                'end_date' => $_GET['end_date'] ?? null
            ];

            $where_clauses = [];
            $params = [];

            if ($filters['entity_type']) {
                $where_clauses[] = "entity_type = ?";
                $params[] = $filters['entity_type'];
            }
            if ($filters['entity_id']) {
                $where_clauses[] = "entity_id = ?";
                $params[] = $filters['entity_id'];
            }
            if ($filters['action']) {
                $where_clauses[] = "action = ?";
                $params[] = $filters['action'];
            }
            if ($filters['start_date']) {
                $where_clauses[] = "created_at >= ?";
                $params[] = $filters['start_date'];
            }
            if ($filters['end_date']) {
                $where_clauses[] = "created_at <= ?";
                $params[] = $filters['end_date'];
            }

            $where_sql = !empty($where_clauses) ? "WHERE " . implode(" AND ", $where_clauses) : "";

            // Get total count
            $count_sql = "SELECT COUNT(*) as total FROM audit_trail {$where_sql}";
            $stmt = $this->db->prepare($count_sql);
            $stmt->execute($params);
            $total = $stmt->fetch(PDO::FETCH_ASSOC)['total'];

            // Get audit trail with user information
            $sql = "
                SELECT 
                    at.*,
                    u.username,
                    u.email
                FROM audit_trail at
                LEFT JOIN users u ON at.user_id = u.id
                {$where_sql}
                ORDER BY at.created_at DESC
                LIMIT ? OFFSET ?
            ";
            
            $params[] = $limit;
            $params[] = $offset;
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);
            $trail = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'trail' => $trail,
                'pagination' => [
                    'total' => $total,
                    'page' => $page,
                    'limit' => $limit,
                    'total_pages' => ceil($total / $limit)
                ]
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching audit trail: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch audit trail'], 500);
        }
    }

    public function rotateLogs() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $retention_days = $_POST['retention_days'] ?? 30;
            
            if (!is_numeric($retention_days) || $retention_days < 1) {
                sendJsonResponse(['error' => 'Invalid retention days'], 400);
                return;
            }

            $this->db->beginTransaction();

            try {
                // Archive old logs
                $archive_date = date('Y-m-d H:i:s', strtotime("-{$retention_days} days"));
                
                // Archive activity logs
                $stmt = $this->db->prepare("
                    INSERT INTO archived_activity_logs 
                    SELECT * FROM activity_logs 
                    WHERE created_at < ?
                ");
                $stmt->execute([$archive_date]);

                $stmt = $this->db->prepare("
                    DELETE FROM activity_logs 
                    WHERE created_at < ?
                ");
                $stmt->execute([$archive_date]);

                // Archive error logs
                $stmt = $this->db->prepare("
                    INSERT INTO archived_error_logs 
                    SELECT * FROM error_logs 
                    WHERE created_at < ?
                ");
                $stmt->execute([$archive_date]);

                $stmt = $this->db->prepare("
                    DELETE FROM error_logs 
                    WHERE created_at < ?
                ");
                $stmt->execute([$archive_date]);

                // Archive audit trail
                $stmt = $this->db->prepare("
                    INSERT INTO archived_audit_trail 
                    SELECT * FROM audit_trail 
                    WHERE created_at < ?
                ");
                $stmt->execute([$archive_date]);

                $stmt = $this->db->prepare("
                    DELETE FROM audit_trail 
                    WHERE created_at < ?
                ");
                $stmt->execute([$archive_date]);

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'logs_rotated',
                    "Rotated logs older than {$retention_days} days"
                );

                sendJsonResponse(['message' => 'Logs rotated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error rotating logs: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to rotate logs'], 500);
        }
    }

    public function getLogAnalytics() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $start_date = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
            $end_date = $_GET['end_date'] ?? date('Y-m-d');

            // Activity log analytics
            $stmt = $this->db->prepare("
                SELECT 
                    activity_type,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM activity_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY activity_type, DATE(created_at)
                ORDER BY date, activity_type
            ");
            $stmt->execute([$start_date, $end_date]);
            $activity_analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Error log analytics
            $stmt = $this->db->prepare("
                SELECT 
                    error_level,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM error_logs
                WHERE created_at BETWEEN ? AND ?
                GROUP BY error_level, DATE(created_at)
                ORDER BY date, error_level
            ");
            $stmt->execute([$start_date, $end_date]);
            $error_analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Audit trail analytics
            $stmt = $this->db->prepare("
                SELECT 
                    entity_type,
                    action,
                    COUNT(*) as count,
                    DATE(created_at) as date
                FROM audit_trail
                WHERE created_at BETWEEN ? AND ?
                GROUP BY entity_type, action, DATE(created_at)
                ORDER BY date, entity_type, action
            ");
            $stmt->execute([$start_date, $end_date]);
            $audit_analytics = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'activity_analytics' => $activity_analytics,
                'error_analytics' => $error_analytics,
                'audit_analytics' => $audit_analytics
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching log analytics: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch log analytics'], 500);
        }
    }

    public function exportLogs() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $log_type = $_GET['type'] ?? null;
            $start_date = $_GET['start_date'] ?? null;
            $end_date = $_GET['end_date'] ?? null;

            if (!$log_type || !$start_date || !$end_date) {
                sendJsonResponse(['error' => 'Log type, start date, and end date are required'], 400);
                return;
            }

            $table = '';
            switch ($log_type) {
                case 'activity':
                    $table = 'activity_logs';
                    break;
                case 'error':
                    $table = 'error_logs';
                    break;
                case 'audit':
                    $table = 'audit_trail';
                    break;
                default:
                    sendJsonResponse(['error' => 'Invalid log type'], 400);
                    return;
            }

            $stmt = $this->db->prepare("
                SELECT * FROM {$table}
                WHERE created_at BETWEEN ? AND ?
                ORDER BY created_at DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $logs = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Generate CSV
            $filename = "{$log_type}_logs_{$start_date}_to_{$end_date}.csv";
            $filepath = $this->log_path . $filename;

            $fp = fopen($filepath, 'w');
            
            // Write headers
            if (!empty($logs)) {
                fputcsv($fp, array_keys($logs[0]));
            }

            // Write data
            foreach ($logs as $log) {
                fputcsv($fp, $log);
            }

            fclose($fp);

            logActivity(
                $_SESSION['user_id'],
                'logs_exported',
                "Exported {$log_type} logs from {$start_date} to {$end_date}"
            );

            sendJsonResponse([
                'message' => 'Logs exported successfully',
                'file' => $filename
            ]);

        } catch (PDOException $e) {
            error_log("Error exporting logs: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to export logs'], 500);
        }
    }

    public function clearLogs() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $log_type = $_POST['type'] ?? null;
            $before_date = $_POST['before_date'] ?? null;

            if (!$log_type || !$before_date) {
                sendJsonResponse(['error' => 'Log type and before date are required'], 400);
                return;
            }

            $table = '';
            switch ($log_type) {
                case 'activity':
                    $table = 'activity_logs';
                    break;
                case 'error':
                    $table = 'error_logs';
                    break;
                case 'audit':
                    $table = 'audit_trail';
                    break;
                default:
                    sendJsonResponse(['error' => 'Invalid log type'], 400);
                    return;
            }

            $this->db->beginTransaction();

            try {
                // Archive logs before deleting
                $archive_table = "archived_{$table}";
                $stmt = $this->db->prepare("
                    INSERT INTO {$archive_table}
                    SELECT * FROM {$table}
                    WHERE created_at < ?
                ");
                $stmt->execute([$before_date]);

                // Delete logs
                $stmt = $this->db->prepare("
                    DELETE FROM {$table}
                    WHERE created_at < ?
                ");
                $stmt->execute([$before_date]);

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'logs_cleared',
                    "Cleared {$log_type} logs before {$before_date}"
                );

                sendJsonResponse(['message' => 'Logs cleared successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error clearing logs: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to clear logs'], 500);
        }
    }
} 