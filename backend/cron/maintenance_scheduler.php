<?php
/**
 * System Maintenance Scheduler
 * 
 * This script schedules and manages system maintenance tasks:
 * - Daily database checks
 * - Weekly report cleanup
 * - Monthly system resource monitoring
 * - Error log analysis
 */

require_once __DIR__ . '/../maintenance/system_check.php';

class MaintenanceScheduler {
    private $db;
    private $maintenance;
    private $log_file;

    public function __construct() {
        $this->log_file = __DIR__ . '/../logs/maintenance_scheduler.log';
        $this->initializeComponents();
    }

    private function initializeComponents() {
        try {
            $this->db = getDBConnection();
            $this->maintenance = new SystemMaintenance();
        } catch (Exception $e) {
            $this->logError('Initialization Error: ' . $e->getMessage());
        }
    }

    public function scheduleMaintenance() {
        $this->checkDailyTasks();
        $this->checkWeeklyTasks();
        $this->checkMonthlyTasks();
    }

    private function checkDailyTasks() {
        $last_run = $this->getLastRunTime('daily');
        if ($this->shouldRunTask($last_run, 'daily')) {
            try {
                // Run daily database checks
                $this->maintenance->checkDatabase();
                $this->updateLastRunTime('daily');
                $this->logSuccess('Daily maintenance completed successfully');
            } catch (Exception $e) {
                $this->logError('Daily maintenance failed: ' . $e->getMessage());
            }
        }
    }

    private function checkWeeklyTasks() {
        $last_run = $this->getLastRunTime('weekly');
        if ($this->shouldRunTask($last_run, 'weekly')) {
            try {
                // Run weekly report cleanup
                $this->maintenance->cleanupReports();
                $this->updateLastRunTime('weekly');
                $this->logSuccess('Weekly maintenance completed successfully');
            } catch (Exception $e) {
                $this->logError('Weekly maintenance failed: ' . $e->getMessage());
            }
        }
    }

    private function checkMonthlyTasks() {
        $last_run = $this->getLastRunTime('monthly');
        if ($this->shouldRunTask($last_run, 'monthly')) {
            try {
                // Run monthly system checks
                $this->maintenance->monitorResources();
                $this->maintenance->analyzeErrorLogs();
                $this->updateLastRunTime('monthly');
                $this->logSuccess('Monthly maintenance completed successfully');
            } catch (Exception $e) {
                $this->logError('Monthly maintenance failed: ' . $e->getMessage());
            }
        }
    }

    private function getLastRunTime($task_type) {
        try {
            $stmt = $this->db->prepare("
                SELECT last_run 
                FROM maintenance_schedule 
                WHERE task_type = ?
            ");
            $stmt->execute([$task_type]);
            $result = $stmt->fetch(PDO::FETCH_ASSOC);
            return $result ? $result['last_run'] : null;
        } catch (Exception $e) {
            $this->logError('Error getting last run time: ' . $e->getMessage());
            return null;
        }
    }

    private function updateLastRunTime($task_type) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO maintenance_schedule (task_type, last_run)
                VALUES (?, NOW())
                ON DUPLICATE KEY UPDATE last_run = NOW()
            ");
            $stmt->execute([$task_type]);
        } catch (Exception $e) {
            $this->logError('Error updating last run time: ' . $e->getMessage());
        }
    }

    private function shouldRunTask($last_run, $task_type) {
        if (!$last_run) {
            return true;
        }

        $last_run_time = strtotime($last_run);
        $current_time = time();
        $time_diff = $current_time - $last_run_time;

        switch ($task_type) {
            case 'daily':
                return $time_diff >= 86400; // 24 hours
            case 'weekly':
                return $time_diff >= 604800; // 7 days
            case 'monthly':
                return $time_diff >= 2592000; // 30 days
            default:
                return false;
        }
    }

    private function logError($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] ERROR: $message\n";
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }

    private function logSuccess($message) {
        $timestamp = date('Y-m-d H:i:s');
        $log_message = "[$timestamp] SUCCESS: $message\n";
        file_put_contents($this->log_file, $log_message, FILE_APPEND);
    }
}

// Create maintenance_schedule table if it doesn't exist
try {
    $db = getDBConnection();
    $db->exec("
        CREATE TABLE IF NOT EXISTS maintenance_schedule (
            task_type VARCHAR(20) PRIMARY KEY,
            last_run DATETIME NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        )
    ");
} catch (Exception $e) {
    error_log('Error creating maintenance_schedule table: ' . $e->getMessage());
}

// Run scheduler if script is executed directly
if (php_sapi_name() === 'cli') {
    $scheduler = new MaintenanceScheduler();
    $scheduler->scheduleMaintenance();
} 