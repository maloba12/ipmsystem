<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class ReportController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
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
