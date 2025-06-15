<?php
/**
 * System Maintenance Script
 * 
 * This script performs various system checks and maintenance tasks:
 * - Database connection verification
 * - File system checks
 * - Report cleanup
 * - System resource monitoring
 * - Error log analysis
 */

require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../reports/classes/ReportGenerator.php';

class SystemMaintenance {
    private $db;
    private $report_generator;
    private $log_file;
    private $check_results = [];

    public function __construct() {
        $this->log_file = __DIR__ . '/../logs/system_maintenance.log';
        $this->initializeComponents();
    }

    private function initializeComponents() {
        try {
            $this->db = getDBConnection();
            $this->report_generator = new ReportGenerator($this->db, 
                __DIR__ . '/../reports/templates',
                __DIR__ . '/../reports/output'
            );
        } catch (Exception $e) {
            $this->logError('Initialization Error: ' . $e->getMessage());
        }
    }

    public function runMaintenance() {
        $this->checkDatabase();
        $this->checkFileSystem();
        $this->cleanupReports();
        $this->monitorResources();
        $this->analyzeErrorLogs();
        $this->generateReport();
    }

    private function checkDatabase() {
        try {
            // Check database connection
            $this->db->query('SELECT 1');
            $this->check_results['database']['connection'] = 'OK';

            // Check table integrity
            $tables = ['policies', 'claims', 'clients', 'transactions', 'scheduled_reports'];
            foreach ($tables as $table) {
                $this->db->query("SELECT COUNT(*) FROM $table");
                $this->check_results['database']['tables'][$table] = 'OK';
            }
        } catch (Exception $e) {
            $this->check_results['database']['error'] = $e->getMessage();
            $this->logError('Database Check Error: ' . $e->getMessage());
        }
    }

    private function checkFileSystem() {
        $directories = [
            '../reports/templates',
            '../reports/output',
            '../logs',
            '../uploads'
        ];

        foreach ($directories as $dir) {
            $path = __DIR__ . '/' . $dir;
            if (is_dir($path) && is_writable($path)) {
                $this->check_results['filesystem'][$dir] = 'OK';
            } else {
                $this->check_results['filesystem'][$dir] = 'ERROR';
                $this->logError("Directory Check Error: $dir is not accessible or writable");
            }
        }
    }

    private function cleanupReports() {
        try {
            // Clean up reports older than 30 days
            $this->report_generator->cleanupOldReports(30);
            $this->check_results['cleanup']['reports'] = 'OK';
        } catch (Exception $e) {
            $this->check_results['cleanup']['error'] = $e->getMessage();
            $this->logError('Cleanup Error: ' . $e->getMessage());
        }
    }

    private function monitorResources() {
        // Check memory usage
        $memory_usage = memory_get_usage(true);
        $memory_limit = ini_get('memory_limit');
        $this->check_results['resources']['memory'] = [
            'usage' => $this->formatBytes($memory_usage),
            'limit' => $memory_limit
        ];

        // Check disk space
        $disk_free = disk_free_space(__DIR__);
        $disk_total = disk_total_space(__DIR__);
        $this->check_results['resources']['disk'] = [
            'free' => $this->formatBytes($disk_free),
            'total' => $this->formatBytes($disk_total)
        ];
    }

    private function analyzeErrorLogs() {
        $error_log = __DIR__ . '/../logs/error.log';
        if (file_exists($error_log)) {
            $errors = file($error_log);
            $error_count = count($errors);
            $recent_errors = array_slice($errors, -10); // Last 10 errors

            $this->check_results['errors'] = [
                'total' => $error_count,
                'recent' => $recent_errors
            ];
        }
    }

    private function generateReport() {
        $report = "System Maintenance Report\n";
        $report .= "Generated: " . date('Y-m-d H:i:s') . "\n\n";

        foreach ($this->check_results as $category => $results) {
            $report .= strtoupper($category) . "\n";
            $report .= str_repeat('-', strlen($category)) . "\n";
            
            if (is_array($results)) {
                foreach ($results as $key => $value) {
                    if (is_array($value)) {
                        $report .= "$key:\n";
                        foreach ($value as $subkey => $subvalue) {
                            $report .= "  $subkey: $subvalue\n";
                        }
                    } else {
                        $report .= "$key: $value\n";
                    }
                }
            } else {
                $report .= "$results\n";
            }
            $report .= "\n";
        }

        file_put_contents($this->log_file, $report);
    }

    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] $message\n";
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }

    private function formatBytes($bytes) {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);
        return round($bytes, 2) . ' ' . $units[$pow];
    }
}

// Run maintenance if script is executed directly
if (php_sapi_name() === 'cli') {
    $maintenance = new SystemMaintenance();
    $maintenance->runMaintenance();
} 