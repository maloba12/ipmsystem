<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

/**
 * Report Controller
 * 
 * This controller handles all report-related API endpoints, including
 * report generation, scheduling, and management.
 */
class ReportController {
    private $db;
    private $report_generator;
    private $data_collector;
    private $scheduler;

    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
        $this->report_generator = new ReportGenerator($db);
        $this->data_collector = new ReportDataCollector($db);
        $this->scheduler = new ReportScheduler($db, $this->report_generator, $this->data_collector);
    }

    /**
     * Generate a report
     * 
     * @param array $request Request data
     * @return array Response data
     */
    public function generateReport($request) {
        try {
            // Validate request
            $this->validateReportRequest($request);

            // Set date range
            $this->data_collector->setDateRange(
                $request['start_date'],
                $request['end_date']
            );

            // Collect data based on report type
            $data = $this->collectReportData($request['report_type'], $request['parameters'] ?? []);

            // Generate report
            $this->report_generator->setFormat($request['format'] ?? 'pdf');
            $report_file = $this->report_generator->generate($data);

            return [
                'status' => 'success',
                'message' => 'Report generated successfully',
                'data' => [
                    'report_file' => $report_file
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Schedule a report
     * 
     * @param array $request Request data
     * @return array Response data
     */
    public function scheduleReport($request) {
        try {
            // Validate request
            $this->validateScheduleRequest($request);

            // Schedule report
            $report_id = $this->scheduler->scheduleReport($request);

            return [
                'status' => 'success',
                'message' => 'Report scheduled successfully',
                'data' => [
                    'report_id' => $report_id
                ]
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Update a scheduled report
     * 
     * @param array $request Request data
     * @return array Response data
     */
    public function updateScheduledReport($request) {
        try {
            // Validate request
            if (empty($request['report_id'])) {
                throw new Exception('Report ID is required');
            }
            $this->validateScheduleRequest($request);

            // Update report
            $success = $this->scheduler->updateScheduledReport(
                $request['report_id'],
                $request
            );

            if (!$success) {
                throw new Exception('Failed to update scheduled report');
            }

            return [
                'status' => 'success',
                'message' => 'Scheduled report updated successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Delete a scheduled report
     * 
     * @param array $request Request data
     * @return array Response data
     */
    public function deleteScheduledReport($request) {
        try {
            // Validate request
            if (empty($request['report_id'])) {
                throw new Exception('Report ID is required');
            }

            // Delete report
            $success = $this->scheduler->deleteScheduledReport($request['report_id']);

            if (!$success) {
                throw new Exception('Failed to delete scheduled report');
            }

            return [
                'status' => 'success',
                'message' => 'Scheduled report deleted successfully'
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get scheduled reports
     * 
     * @param array $request Request data
     * @return array Response data
     */
    public function getScheduledReports($request) {
        try {
            $filters = [];
            
            if (!empty($request['status'])) {
                $filters['status'] = $request['status'];
            }
            
            if (!empty($request['report_type'])) {
                $filters['report_type'] = $request['report_type'];
            }
            
            if (!empty($request['frequency'])) {
                $filters['frequency'] = $request['frequency'];
            }

            $reports = $this->scheduler->getScheduledReports($filters);

            return [
                'status' => 'success',
                'data' => $reports
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get report templates
     * 
     * @return array Response data
     */
    public function getReportTemplates() {
        try {
            $sql = "SELECT * FROM report_templates WHERE is_active = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => $templates
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get report recipients
     * 
     * @return array Response data
     */
    public function getReportRecipients() {
        try {
            $sql = "SELECT * FROM report_recipients WHERE is_active = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $recipients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => $recipients
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Get report recipient groups
     * 
     * @return array Response data
     */
    public function getReportRecipientGroups() {
        try {
            $sql = "SELECT * FROM report_recipient_groups WHERE is_active = TRUE";
            $stmt = $this->db->prepare($sql);
            $stmt->execute();
            $groups = $stmt->fetchAll(PDO::FETCH_ASSOC);

            return [
                'status' => 'success',
                'data' => $groups
            ];
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }

    /**
     * Validate report generation request
     * 
     * @param array $request Request data
     * @throws Exception If validation fails
     */
    private function validateReportRequest($request) {
        if (empty($request['report_type'])) {
            throw new Exception('Report type is required');
        }

        if (empty($request['start_date'])) {
            throw new Exception('Start date is required');
        }

        if (empty($request['end_date'])) {
            throw new Exception('End date is required');
        }

        if (strtotime($request['start_date']) > strtotime($request['end_date'])) {
            throw new Exception('Start date must be before end date');
        }
    }

    /**
     * Validate report scheduling request
     * 
     * @param array $request Request data
     * @throws Exception If validation fails
     */
    private function validateScheduleRequest($request) {
        $this->validateReportRequest($request);

        if (empty($request['frequency'])) {
            throw new Exception('Frequency is required');
        }

        if (!in_array($request['frequency'], ['daily', 'weekly', 'monthly', 'quarterly', 'yearly'])) {
            throw new Exception('Invalid frequency');
        }

        if (empty($request['recipients'])) {
            throw new Exception('Recipients are required');
        }

        if (!is_array($request['recipients'])) {
            throw new Exception('Recipients must be an array');
        }
    }

    /**
     * Collect report data
     * 
     * @param string $report_type Report type
     * @param array $parameters Report parameters
     * @return array Report data
     * @throws Exception If report type is invalid
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
                if (empty($parameters['client_id'])) {
                    throw new Exception('Client ID is required for client portfolio report');
                }
                return $this->data_collector->collectClientPortfolio($parameters['client_id']);
            
            default:
                throw new Exception("Unknown report type: {$report_type}");
        }
    }

    public function getFinancialReport() {
        AuthMiddleware::requireAnyRole(['admin', 'accountant']);
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        try {
            // Get premium income
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_policies,
                    SUM(amount) as total_premium_income,
                    AVG(amount) as average_premium
                FROM payments
                WHERE policy_id IS NOT NULL
                AND payment_date BETWEEN ? AND ?
                AND status = 'completed'
            ");
            $stmt->execute([$start_date, $end_date]);
            $premium_income = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get claim payments
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_claims,
                    SUM(amount) as total_claim_payments,
                    AVG(amount) as average_claim
                FROM payments
                WHERE claim_id IS NOT NULL
                AND payment_date BETWEEN ? AND ?
                AND status = 'completed'
            ");
            $stmt->execute([$start_date, $end_date]);
            $claim_payments = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get monthly trends
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(payment_date, '%Y-%m') as month,
                    SUM(CASE WHEN policy_id IS NOT NULL THEN amount ELSE 0 END) as premium_income,
                    SUM(CASE WHEN claim_id IS NOT NULL THEN amount ELSE 0 END) as claim_payments
                FROM payments
                WHERE payment_date BETWEEN ? AND ?
                AND status = 'completed'
                GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$start_date, $end_date]);
            $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'premium_income' => $premium_income,
                'claim_payments' => $claim_payments,
                'monthly_trends' => $monthly_trends,
                'net_income' => $premium_income['total_premium_income'] - $claim_payments['total_claim_payments']
            ]);

        } catch (PDOException $e) {
            error_log("Error generating financial report: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to generate financial report'], 500);
        }
    }

    public function getPolicyReport() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        try {
            // Get policy statistics
            $stmt = $this->db->prepare("
                SELECT 
                    policy_type,
                    COUNT(*) as total_policies,
                    SUM(coverage_amount) as total_coverage,
                    AVG(coverage_amount) as average_coverage,
                    SUM(premium_amount) as total_premium,
                    AVG(premium_amount) as average_premium
                FROM policies
                WHERE created_at BETWEEN ? AND ?
                AND status != 'deleted'
                GROUP BY policy_type
            ");
            $stmt->execute([$start_date, $end_date]);
            $policy_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get policy status distribution
            $stmt = $this->db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count
                FROM policies
                WHERE created_at BETWEEN ? AND ?
                GROUP BY status
            ");
            $stmt->execute([$start_date, $end_date]);
            $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get monthly policy creation trends
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_policies,
                    SUM(premium_amount) as total_premium
                FROM policies
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$start_date, $end_date]);
            $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'policy_statistics' => $policy_stats,
                'status_distribution' => $status_distribution,
                'monthly_trends' => $monthly_trends
            ]);

        } catch (PDOException $e) {
            error_log("Error generating policy report: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to generate policy report'], 500);
        }
    }

    public function getClaimReport() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster']);
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        try {
            // Get claim statistics
            $stmt = $this->db->prepare("
                SELECT 
                    claim_type,
                    COUNT(*) as total_claims,
                    SUM(claim_amount) as total_claim_amount,
                    AVG(claim_amount) as average_claim_amount
                FROM claims
                WHERE created_at BETWEEN ? AND ?
                AND status != 'deleted'
                GROUP BY claim_type
            ");
            $stmt->execute([$start_date, $end_date]);
            $claim_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get claim status distribution
            $stmt = $this->db->prepare("
                SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(claim_amount) as total_amount
                FROM claims
                WHERE created_at BETWEEN ? AND ?
                GROUP BY status
            ");
            $stmt->execute([$start_date, $end_date]);
            $status_distribution = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get monthly claim trends
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_claims,
                    SUM(claim_amount) as total_claim_amount
                FROM claims
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$start_date, $end_date]);
            $monthly_trends = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'claim_statistics' => $claim_stats,
                'status_distribution' => $status_distribution,
                'monthly_trends' => $monthly_trends
            ]);

        } catch (PDOException $e) {
            error_log("Error generating claim report: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to generate claim report'], 500);
        }
    }

    public function getClientReport() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        try {
            // Get client statistics
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_clients,
                    COUNT(CASE WHEN status = 'active' THEN 1 END) as active_clients,
                    COUNT(CASE WHEN status = 'inactive' THEN 1 END) as inactive_clients
                FROM clients
                WHERE created_at BETWEEN ? AND ?
            ");
            $stmt->execute([$start_date, $end_date]);
            $client_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get client policy distribution
            $stmt = $this->db->prepare("
                SELECT 
                    c.id,
                    c.first_name,
                    c.last_name,
                    COUNT(p.id) as total_policies,
                    SUM(p.coverage_amount) as total_coverage,
                    SUM(p.premium_amount) as total_premium
                FROM clients c
                LEFT JOIN policies p ON c.id = p.client_id
                WHERE c.created_at BETWEEN ? AND ?
                GROUP BY c.id
                ORDER BY total_policies DESC
                LIMIT 10
            ");
            $stmt->execute([$start_date, $end_date]);
            $top_clients = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get monthly client acquisition
            $stmt = $this->db->prepare("
                SELECT 
                    DATE_FORMAT(created_at, '%Y-%m') as month,
                    COUNT(*) as new_clients
                FROM clients
                WHERE created_at BETWEEN ? AND ?
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month
            ");
            $stmt->execute([$start_date, $end_date]);
            $monthly_acquisition = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'client_statistics' => $client_stats,
                'top_clients' => $top_clients,
                'monthly_acquisition' => $monthly_acquisition
            ]);

        } catch (PDOException $e) {
            error_log("Error generating client report: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to generate client report'], 500);
        }
    }

    public function getAgentReport() {
        AuthMiddleware::requireRole('admin');
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        try {
            // Get agent performance statistics
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    COUNT(DISTINCT p.id) as total_policies,
                    SUM(p.premium_amount) as total_premium,
                    COUNT(DISTINCT c.id) as total_clients,
                    COUNT(DISTINCT cl.id) as total_claims
                FROM users u
                LEFT JOIN policies p ON u.id = p.created_by
                LEFT JOIN clients c ON u.id = c.created_by
                LEFT JOIN claims cl ON u.id = cl.created_by
                WHERE u.role = 'agent'
                AND u.created_at BETWEEN ? AND ?
                GROUP BY u.id
                ORDER BY total_premium DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $agent_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Get monthly agent performance
            $stmt = $this->db->prepare("
                SELECT 
                    u.id,
                    u.first_name,
                    u.last_name,
                    DATE_FORMAT(p.created_at, '%Y-%m') as month,
                    COUNT(p.id) as new_policies,
                    SUM(p.premium_amount) as premium_amount
                FROM users u
                LEFT JOIN policies p ON u.id = p.created_by
                WHERE u.role = 'agent'
                AND p.created_at BETWEEN ? AND ?
                GROUP BY u.id, DATE_FORMAT(p.created_at, '%Y-%m')
                ORDER BY month, premium_amount DESC
            ");
            $stmt->execute([$start_date, $end_date]);
            $monthly_performance = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'agent_statistics' => $agent_stats,
                'monthly_performance' => $monthly_performance
            ]);

        } catch (PDOException $e) {
            error_log("Error generating agent report: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to generate agent report'], 500);
        }
    }
}
