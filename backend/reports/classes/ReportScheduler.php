<?php
/**
 * Report Scheduler Class
 * 
 * This class handles the scheduling and automation of report generation.
 * It provides methods for scheduling reports, managing report jobs, and
 * handling report delivery.
 */
class ReportScheduler {
    private $db;
    private $report_generator;
    private $data_collector;

    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     * @param ReportGenerator $report_generator Report generator instance
     * @param ReportDataCollector $data_collector Data collector instance
     */
    public function __construct($db, $report_generator, $data_collector) {
        $this->db = $db;
        $this->report_generator = $report_generator;
        $this->data_collector = $data_collector;
    }

    /**
     * Schedule a new report
     * 
     * @param array $config Report configuration
     * @return int Scheduled report ID
     */
    public function scheduleReport($config) {
        $sql = "INSERT INTO scheduled_reports (
                    report_type,
                    frequency,
                    start_date,
                    end_date,
                    recipients,
                    format,
                    parameters,
                    status,
                    created_at
                ) VALUES (
                    :report_type,
                    :frequency,
                    :start_date,
                    :end_date,
                    :recipients,
                    :format,
                    :parameters,
                    'pending',
                    NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':report_type' => $config['report_type'],
            ':frequency' => $config['frequency'],
            ':start_date' => $config['start_date'],
            ':end_date' => $config['end_date'],
            ':recipients' => json_encode($config['recipients']),
            ':format' => $config['format'],
            ':parameters' => json_encode($config['parameters'])
        ]);

        return $this->db->lastInsertId();
    }

    /**
     * Update a scheduled report
     * 
     * @param int $report_id Report ID
     * @param array $config Updated configuration
     * @return bool Success status
     */
    public function updateScheduledReport($report_id, $config) {
        $sql = "UPDATE scheduled_reports SET
                    report_type = :report_type,
                    frequency = :frequency,
                    start_date = :start_date,
                    end_date = :end_date,
                    recipients = :recipients,
                    format = :format,
                    parameters = :parameters,
                    updated_at = NOW()
                WHERE id = :report_id";

        $stmt = $this->db->prepare($sql);
        return $stmt->execute([
            ':report_id' => $report_id,
            ':report_type' => $config['report_type'],
            ':frequency' => $config['frequency'],
            ':start_date' => $config['start_date'],
            ':end_date' => $config['end_date'],
            ':recipients' => json_encode($config['recipients']),
            ':format' => $config['format'],
            ':parameters' => json_encode($config['parameters'])
        ]);
    }

    /**
     * Delete a scheduled report
     * 
     * @param int $report_id Report ID
     * @return bool Success status
     */
    public function deleteScheduledReport($report_id) {
        $sql = "DELETE FROM scheduled_reports WHERE id = :report_id";
        $stmt = $this->db->prepare($sql);
        return $stmt->execute([':report_id' => $report_id]);
    }

    /**
     * Get scheduled reports
     * 
     * @param array $filters Optional filters
     * @return array Scheduled reports
     */
    public function getScheduledReports($filters = []) {
        $sql = "SELECT * FROM scheduled_reports WHERE 1=1";
        $params = [];

        if (!empty($filters['status'])) {
            $sql .= " AND status = :status";
            $params[':status'] = $filters['status'];
        }

        if (!empty($filters['report_type'])) {
            $sql .= " AND report_type = :report_type";
            $params[':report_type'] = $filters['report_type'];
        }

        if (!empty($filters['frequency'])) {
            $sql .= " AND frequency = :frequency";
            $params[':frequency'] = $filters['frequency'];
        }

        $sql .= " ORDER BY created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute($params);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Process scheduled reports
     * 
     * This method should be called by a cron job to process pending reports
     */
    public function processScheduledReports() {
        $sql = "SELECT * FROM scheduled_reports 
                WHERE status = 'pending' 
                AND next_run <= NOW()";

        $stmt = $this->db->prepare($sql);
        $stmt->execute();
        $reports = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($reports as $report) {
            try {
                $this->processReport($report);
                $this->updateNextRun($report['id']);
            } catch (Exception $e) {
                $this->logError($report['id'], $e->getMessage());
            }
        }
    }

    /**
     * Process a single report
     * 
     * @param array $report Report configuration
     */
    private function processReport($report) {
        // Set date range
        $this->data_collector->setDateRange(
            $report['start_date'],
            $report['end_date']
        );

        // Collect data based on report type
        $data = $this->collectReportData($report['report_type'], $report['parameters']);

        // Generate report
        $this->report_generator->setFormat($report['format']);
        $report_file = $this->report_generator->generate($data);

        // Send report to recipients
        $this->deliverReport($report_file, $report);

        // Update report status
        $this->updateReportStatus($report['id'], 'completed');
    }

    /**
     * Collect report data
     * 
     * @param string $report_type Report type
     * @param array $parameters Report parameters
     * @return array Report data
     */
    private function collectReportData($report_type, $parameters) {
        switch ($report_type) {
            case 'financial_summary':
                return $this->data_collector->collectFinancialSummary();
            
            case 'financial_transactions':
                return $this->data_collector->collectFinancialTransactions();
            
            case 'policy_performance':
                return $this->data_collector->collectPolicyPerformance();
            
            case 'client_portfolio':
                return $this->data_collector->collectClientPortfolio($parameters['client_id']);
            
            default:
                throw new Exception("Unknown report type: {$report_type}");
        }
    }

    /**
     * Update next run time
     * 
     * @param int $report_id Report ID
     */
    private function updateNextRun($report_id) {
        $sql = "UPDATE scheduled_reports SET
                    next_run = CASE frequency
                        WHEN 'daily' THEN DATE_ADD(NOW(), INTERVAL 1 DAY)
                        WHEN 'weekly' THEN DATE_ADD(NOW(), INTERVAL 1 WEEK)
                        WHEN 'monthly' THEN DATE_ADD(NOW(), INTERVAL 1 MONTH)
                        WHEN 'quarterly' THEN DATE_ADD(NOW(), INTERVAL 3 MONTH)
                        WHEN 'yearly' THEN DATE_ADD(NOW(), INTERVAL 1 YEAR)
                    END
                WHERE id = :report_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':report_id' => $report_id]);
    }

    /**
     * Update report status
     * 
     * @param int $report_id Report ID
     * @param string $status New status
     */
    private function updateReportStatus($report_id, $status) {
        $sql = "UPDATE scheduled_reports SET
                    status = :status,
                    last_run = NOW()
                WHERE id = :report_id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':report_id' => $report_id,
            ':status' => $status
        ]);
    }

    /**
     * Log error
     * 
     * @param int $report_id Report ID
     * @param string $error Error message
     */
    private function logError($report_id, $error) {
        $sql = "INSERT INTO report_errors (
                    report_id,
                    error_message,
                    created_at
                ) VALUES (
                    :report_id,
                    :error_message,
                    NOW()
                )";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':report_id' => $report_id,
            ':error_message' => $error
        ]);

        $this->updateReportStatus($report_id, 'failed');
    }

    /**
     * Deliver report to recipients
     * 
     * @param string $report_file Report file path
     * @param array $report Report configuration
     */
    private function deliverReport($report_file, $report) {
        $recipients = json_decode($report['recipients'], true);
        $subject = "Scheduled Report: {$report['report_type']}";
        $body = "Please find attached the scheduled report.\n\n";
        $body .= "Report Type: {$report['report_type']}\n";
        $body .= "Period: {$report['start_date']} to {$report['end_date']}\n";
        $body .= "Format: {$report['format']}\n";

        foreach ($recipients as $recipient) {
            $this->sendEmail($recipient, $subject, $body, $report_file);
        }
    }

    /**
     * Send email with report attachment
     * 
     * @param string $recipient Recipient email
     * @param string $subject Email subject
     * @param string $body Email body
     * @param string $attachment Report file path
     */
    private function sendEmail($recipient, $subject, $body, $attachment) {
        // Implement email sending logic here
        // This could use PHPMailer, SwiftMailer, or other email libraries
    }
} 