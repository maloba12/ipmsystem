<?php
/**
 * Process Reports Cron Job
 * 
 * This script processes scheduled reports that are due for execution.
 * It should be run by a cron job at regular intervals (e.g., every minute).
 */

// Load required files
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../reports/classes/ReportGenerator.php';
require_once __DIR__ . '/../reports/classes/ReportDataCollector.php';
require_once __DIR__ . '/../reports/classes/ReportScheduler.php';

// Initialize database connection
try {
    $db = getDBConnection();
} catch (Exception $e) {
    error_log("Failed to connect to database: " . $e->getMessage());
    exit(1);
}

// Initialize report components
try {
    $report_generator = new ReportGenerator($db);
    $data_collector = new ReportDataCollector($db);
    $scheduler = new ReportScheduler($db, $report_generator, $data_collector);
} catch (Exception $e) {
    error_log("Failed to initialize report components: " . $e->getMessage());
    exit(1);
}

// Process scheduled reports
try {
    $scheduler->processScheduledReports();
} catch (Exception $e) {
    error_log("Failed to process scheduled reports: " . $e->getMessage());
    exit(1);
}

// Log successful execution
error_log("Successfully processed scheduled reports at " . date('Y-m-d H:i:s'));
exit(0); 