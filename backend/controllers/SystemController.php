<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class SystemController {
    private $db;
    private $cache_path;
    private $temp_path;

    public function __construct() {
        $this->db = getDBConnection();
        $this->cache_path = __DIR__ . '/../cache/';
        $this->temp_path = __DIR__ . '/../temp/';
    }

    public function getSystemHealth() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $health = [
                'database' => $this->checkDatabaseHealth(),
                'disk_space' => $this->checkDiskSpace(),
                'memory_usage' => $this->checkMemoryUsage(),
                'php_status' => $this->checkPHPStatus(),
                'services' => $this->checkServices(),
                'last_backup' => $this->getLastBackupStatus(),
                'error_logs' => $this->getRecentErrors()
            ];

            sendJsonResponse(['health' => $health]);

        } catch (Exception $e) {
            error_log("Error checking system health: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to check system health'], 500);
        }
    }

    private function checkDatabaseHealth() {
        try {
            // Check database connection
            $stmt = $this->db->query("SELECT 1");
            $connection = $stmt->fetch(PDO::FETCH_ASSOC) ? true : false;

            // Check database size
            $stmt = $this->db->query("
                SELECT 
                    table_schema as 'database',
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as 'size_mb'
                FROM information_schema.tables
                WHERE table_schema = ?
                GROUP BY table_schema
            ");
            $stmt->execute([DB_NAME]);
            $size = $stmt->fetch(PDO::FETCH_ASSOC);

            // Check slow queries
            $stmt = $this->db->query("
                SHOW GLOBAL STATUS LIKE 'Slow_queries'
            ");
            $slow_queries = $stmt->fetch(PDO::FETCH_ASSOC);

            return [
                'status' => 'healthy',
                'connection' => $connection,
                'size_mb' => $size['size_mb'] ?? 0,
                'slow_queries' => $slow_queries['Value'] ?? 0
            ];

        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkDiskSpace() {
        try {
            $total_space = disk_total_space(__DIR__);
            $free_space = disk_free_space(__DIR__);
            $used_space = $total_space - $free_space;
            $usage_percent = ($used_space / $total_space) * 100;

            return [
                'status' => $usage_percent > 90 ? 'warning' : 'healthy',
                'total_gb' => round($total_space / 1024 / 1024 / 1024, 2),
                'free_gb' => round($free_space / 1024 / 1024 / 1024, 2),
                'used_gb' => round($used_space / 1024 / 1024 / 1024, 2),
                'usage_percent' => round($usage_percent, 2)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkMemoryUsage() {
        try {
            $memory_usage = memory_get_usage(true);
            $memory_limit = ini_get('memory_limit');
            $memory_limit_bytes = $this->returnBytes($memory_limit);
            $usage_percent = ($memory_usage / $memory_limit_bytes) * 100;

            return [
                'status' => $usage_percent > 80 ? 'warning' : 'healthy',
                'used_mb' => round($memory_usage / 1024 / 1024, 2),
                'limit_mb' => round($memory_limit_bytes / 1024 / 1024, 2),
                'usage_percent' => round($usage_percent, 2)
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkPHPStatus() {
        try {
            return [
                'status' => 'healthy',
                'version' => PHP_VERSION,
                'extensions' => get_loaded_extensions(),
                'max_execution_time' => ini_get('max_execution_time'),
                'upload_max_filesize' => ini_get('upload_max_filesize'),
                'post_max_size' => ini_get('post_max_size')
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkServices() {
        try {
            $services = [
                'mysql' => $this->checkService('mysql'),
                'apache' => $this->checkService('apache2'),
                'php-fpm' => $this->checkService('php-fpm')
            ];

            return [
                'status' => in_array('error', array_column($services, 'status')) ? 'error' : 'healthy',
                'services' => $services
            ];

        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function checkService($service) {
        try {
            exec("systemctl is-active {$service}", $output, $return_var);
            return [
                'status' => $return_var === 0 ? 'running' : 'stopped',
                'message' => implode("\n", $output)
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function getLastBackupStatus() {
        try {
            $stmt = $this->db->query("
                SELECT * FROM backups 
                ORDER BY created_at DESC 
                LIMIT 1
            ");
            $backup = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$backup) {
                return [
                    'status' => 'warning',
                    'message' => 'No backups found'
                ];
            }

            $days_since_backup = (time() - strtotime($backup['created_at'])) / (60 * 60 * 24);

            return [
                'status' => $days_since_backup > 7 ? 'warning' : 'healthy',
                'last_backup' => $backup['created_at'],
                'days_since_backup' => round($days_since_backup, 1),
                'backup_type' => $backup['backup_type']
            ];

        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    private function getRecentErrors() {
        try {
            $stmt = $this->db->query("
                SELECT COUNT(*) as count 
                FROM error_logs 
                WHERE created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
            ");
            $error_count = $stmt->fetch(PDO::FETCH_ASSOC)['count'];

            return [
                'status' => $error_count > 100 ? 'warning' : 'healthy',
                'errors_last_24h' => $error_count
            ];

        } catch (PDOException $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    public function getPerformanceMetrics() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $metrics = [
                'response_time' => $this->getResponseTimeMetrics(),
                'database_performance' => $this->getDatabasePerformanceMetrics(),
                'memory_usage' => $this->getMemoryUsageMetrics(),
                'disk_io' => $this->getDiskIOMetrics(),
                'php_performance' => $this->getPHPPerformanceMetrics()
            ];

            sendJsonResponse(['metrics' => $metrics]);

        } catch (Exception $e) {
            error_log("Error fetching performance metrics: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch performance metrics'], 500);
        }
    }

    private function getResponseTimeMetrics() {
        try {
            $start_time = microtime(true);
            
            // Simulate a database query
            $this->db->query("SELECT 1");
            
            $end_time = microtime(true);
            $response_time = ($end_time - $start_time) * 1000; // Convert to milliseconds

            return [
                'current' => round($response_time, 2),
                'unit' => 'ms'
            ];

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDatabasePerformanceMetrics() {
        try {
            $metrics = [];

            // Get query statistics
            $stmt = $this->db->query("SHOW GLOBAL STATUS");
            $status = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);

            $metrics['queries_per_second'] = $status['Questions'] ?? 0;
            $metrics['slow_queries'] = $status['Slow_queries'] ?? 0;
            $metrics['connections'] = $status['Threads_connected'] ?? 0;

            return $metrics;

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    private function getMemoryUsageMetrics() {
        try {
            $metrics = [];
            $metrics['current'] = memory_get_usage(true);
            $metrics['peak'] = memory_get_peak_usage(true);
            $metrics['limit'] = $this->returnBytes(ini_get('memory_limit'));

            return $metrics;

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    private function getDiskIOMetrics() {
        try {
            $metrics = [];
            
            // Get disk I/O statistics
            exec('iostat -d -x 1 1', $output);
            
            if (!empty($output)) {
                $metrics['read_ops'] = $output[3] ?? 0;
                $metrics['write_ops'] = $output[4] ?? 0;
            }

            return $metrics;

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    private function getPHPPerformanceMetrics() {
        try {
            $metrics = [];
            
            $metrics['opcache_enabled'] = function_exists('opcache_get_status');
            $metrics['opcache_memory_usage'] = $metrics['opcache_enabled'] ? opcache_get_status()['memory_usage'] : null;
            $metrics['max_execution_time'] = ini_get('max_execution_time');
            $metrics['max_input_time'] = ini_get('max_input_time');

            return $metrics;

        } catch (Exception $e) {
            return [
                'error' => $e->getMessage()
            ];
        }
    }

    public function clearCache() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $cache_type = $_POST['type'] ?? 'all';
            
            if (!in_array($cache_type, ['all', 'system', 'database', 'template'])) {
                sendJsonResponse(['error' => 'Invalid cache type'], 400);
                return;
            }

            $cleared = [];

            // Clear system cache
            if ($cache_type === 'all' || $cache_type === 'system') {
                $this->clearDirectory($this->cache_path);
                $cleared[] = 'system';
            }

            // Clear database cache
            if ($cache_type === 'all' || $cache_type === 'database') {
                $this->db->query("FLUSH QUERY CACHE");
                $cleared[] = 'database';
            }

            // Clear template cache
            if ($cache_type === 'all' || $cache_type === 'template') {
                $template_cache = __DIR__ . '/../cache/templates/';
                $this->clearDirectory($template_cache);
                $cleared[] = 'template';
            }

            logActivity(
                $_SESSION['user_id'],
                'cache_cleared',
                "Cleared cache: " . implode(', ', $cleared)
            );

            sendJsonResponse([
                'message' => 'Cache cleared successfully',
                'cleared' => $cleared
            ]);

        } catch (Exception $e) {
            error_log("Error clearing cache: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to clear cache'], 500);
        }
    }

    private function clearDirectory($dir) {
        if (!file_exists($dir)) {
            return;
        }

        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            if (is_dir($path)) {
                $this->clearDirectory($path);
                rmdir($path);
            } else {
                unlink($path);
            }
        }
    }

    public function optimizeDatabase() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $this->db->beginTransaction();

            try {
                // Get all tables
                $stmt = $this->db->query("SHOW TABLES");
                $tables = $stmt->fetchAll(PDO::FETCH_COLUMN);

                $optimized = [];

                foreach ($tables as $table) {
                    // Optimize table
                    $this->db->query("OPTIMIZE TABLE {$table}");
                    $optimized[] = $table;

                    // Analyze table
                    $this->db->query("ANALYZE TABLE {$table}");
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'database_optimized',
                    "Optimized tables: " . implode(', ', $optimized)
                );

                sendJsonResponse([
                    'message' => 'Database optimized successfully',
                    'optimized_tables' => $optimized
                ]);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (Exception $e) {
            error_log("Error optimizing database: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to optimize database'], 500);
        }
    }

    public function getSystemStatus() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $status = [
                'system' => [
                    'os' => PHP_OS,
                    'php_version' => PHP_VERSION,
                    'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
                    'server_name' => $_SERVER['SERVER_NAME'] ?? 'Unknown',
                    'document_root' => $_SERVER['DOCUMENT_ROOT'] ?? 'Unknown'
                ],
                'database' => [
                    'version' => $this->db->query('SELECT VERSION()')->fetchColumn(),
                    'tables' => $this->db->query('SHOW TABLES')->rowCount(),
                    'size' => $this->getDatabaseSize()
                ],
                'php' => [
                    'extensions' => get_loaded_extensions(),
                    'memory_limit' => ini_get('memory_limit'),
                    'max_execution_time' => ini_get('max_execution_time'),
                    'upload_max_filesize' => ini_get('upload_max_filesize'),
                    'post_max_size' => ini_get('post_max_size')
                ],
                'disk' => [
                    'total' => disk_total_space(__DIR__),
                    'free' => disk_free_space(__DIR__),
                    'used' => disk_total_space(__DIR__) - disk_free_space(__DIR__)
                ],
                'memory' => [
                    'usage' => memory_get_usage(true),
                    'peak' => memory_get_peak_usage(true)
                ]
            ];

            sendJsonResponse(['status' => $status]);

        } catch (Exception $e) {
            error_log("Error fetching system status: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch system status'], 500);
        }
    }

    private function getDatabaseSize() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb
                FROM information_schema.tables
                WHERE table_schema = ?
            ");
            $stmt->execute([DB_NAME]);
            return $stmt->fetch(PDO::FETCH_ASSOC)['size_mb'];
        } catch (Exception $e) {
            return 0;
        }
    }

    private function returnBytes($val) {
        $val = trim($val);
        $last = strtolower($val[strlen($val)-1]);
        $val = substr($val, 0, -1);
        
        switch($last) {
            case 'g':
                $val *= 1024;
            case 'm':
                $val *= 1024;
            case 'k':
                $val *= 1024;
        }
        
        return $val;
    }
} 