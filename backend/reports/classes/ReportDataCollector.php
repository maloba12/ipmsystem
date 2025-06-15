<?php
/**
 * Report Data Collector Class
 * 
 * This class handles the collection and preparation of data for various reports.
 * It provides methods for gathering data from different sources and formatting it
 * for report generation.
 */
class ReportDataCollector {
    private $db;
    private $date_range;

    /**
     * Constructor
     * 
     * @param PDO $db Database connection
     */
    public function __construct($db) {
        $this->db = $db;
    }

    /**
     * Set the date range for data collection
     * 
     * @param string $start Start date
     * @param string $end End date
     * @return ReportDataCollector
     */
    public function setDateRange($start, $end) {
        $this->date_range = [
            'start' => $start,
            'end' => $end
        ];
        return $this;
    }

    /**
     * Collect financial summary data
     * 
     * @return array Financial summary data
     */
    public function collectFinancialSummary() {
        $data = [
            'metadata' => [
                'report_type' => 'Financial Summary'
            ],
            'totals' => $this->getFinancialTotals(),
            'payment_methods' => $this->getPaymentMethodDistribution(),
            'premium_income' => $this->getPremiumIncomeByPeriod(),
            'claim_payments' => $this->getClaimPaymentsByPeriod()
        ];

        return $data;
    }

    /**
     * Collect financial transactions data
     * 
     * @return array Financial transactions data
     */
    public function collectFinancialTransactions() {
        $data = [
            'metadata' => [
                'report_type' => 'Financial Transactions'
            ],
            'premium_payments' => $this->getPremiumPayments(),
            'claim_payments' => $this->getClaimPayments(),
            'summary' => $this->getTransactionSummary()
        ];

        return $data;
    }

    /**
     * Collect policy performance data
     * 
     * @return array Policy performance data
     */
    public function collectPolicyPerformance() {
        $data = [
            'metadata' => [
                'report_type' => 'Policy Performance'
            ],
            'metrics' => $this->getPerformanceMetrics(),
            'policy_status' => $this->getPolicyStatusDistribution(),
            'product_performance' => $this->getProductPerformance(),
            'claims_analysis' => $this->getClaimsAnalysis(),
            'renewal_analysis' => $this->getRenewalAnalysis()
        ];

        return $data;
    }

    /**
     * Collect client portfolio data
     * 
     * @param int $client_id Client ID
     * @return array Client portfolio data
     */
    public function collectClientPortfolio($client_id) {
        $data = [
            'metadata' => [
                'report_type' => 'Client Portfolio'
            ],
            'client' => $this->getClientInfo($client_id),
            'policies' => $this->getClientPolicies($client_id),
            'payments' => $this->getClientPayments($client_id),
            'claims' => $this->getClientClaims($client_id),
            'risk_assessment' => $this->getClientRiskAssessment($client_id)
        ];

        return $data;
    }

    /**
     * Get financial totals
     * 
     * @return array Financial totals
     */
    private function getFinancialTotals() {
        $sql = "SELECT 
                    SUM(CASE WHEN transaction_type = 'premium' THEN amount ELSE 0 END) as premium_income,
                    SUM(CASE WHEN transaction_type = 'claim' THEN amount ELSE 0 END) as claim_payments
                FROM transactions
                WHERE transaction_date BETWEEN :start AND :end";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        $totals = $stmt->fetch(PDO::FETCH_ASSOC);
        $totals['net_income'] = $totals['premium_income'] - $totals['claim_payments'];

        return $totals;
    }

    /**
     * Get payment method distribution
     * 
     * @return array Payment method distribution
     */
    private function getPaymentMethodDistribution() {
        $sql = "SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM transactions
                WHERE transaction_date BETWEEN :start AND :end
                GROUP BY payment_method";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        $methods = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($methods, 'total_amount'));

        foreach ($methods as &$method) {
            $method['percentage'] = ($total > 0) ? round(($method['total_amount'] / $total) * 100, 1) : 0;
        }

        return $methods;
    }

    /**
     * Get premium income by period
     * 
     * @return array Premium income by period
     */
    private function getPremiumIncomeByPeriod() {
        $sql = "SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') as period,
                    COUNT(*) as transaction_count,
                    SUM(amount) as total_amount
                FROM transactions
                WHERE transaction_type = 'premium'
                AND transaction_date BETWEEN :start AND :end
                GROUP BY period
                ORDER BY period";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get claim payments by period
     * 
     * @return array Claim payments by period
     */
    private function getClaimPaymentsByPeriod() {
        $sql = "SELECT 
                    DATE_FORMAT(transaction_date, '%Y-%m') as period,
                    COUNT(*) as claim_count,
                    SUM(amount) as total_amount
                FROM transactions
                WHERE transaction_type = 'claim'
                AND transaction_date BETWEEN :start AND :end
                GROUP BY period
                ORDER BY period";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get premium payments
     * 
     * @return array Premium payments
     */
    private function getPremiumPayments() {
        $sql = "SELECT 
                    t.*,
                    c.name as client_name,
                    p.policy_number
                FROM transactions t
                JOIN clients c ON t.client_id = c.id
                JOIN policies p ON t.policy_id = p.id
                WHERE t.transaction_type = 'premium'
                AND t.transaction_date BETWEEN :start AND :end
                ORDER BY t.transaction_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get claim payments
     * 
     * @return array Claim payments
     */
    private function getClaimPayments() {
        $sql = "SELECT 
                    t.*,
                    c.name as client_name,
                    p.policy_number,
                    cl.claim_number,
                    cl.type as claim_type
                FROM transactions t
                JOIN clients c ON t.client_id = c.id
                JOIN policies p ON t.policy_id = p.id
                JOIN claims cl ON t.claim_id = cl.id
                WHERE t.transaction_type = 'claim'
                AND t.transaction_date BETWEEN :start AND :end
                ORDER BY t.transaction_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get transaction summary
     * 
     * @return array Transaction summary
     */
    private function getTransactionSummary() {
        $sql = "SELECT 
                    COUNT(CASE WHEN transaction_type = 'premium' THEN 1 END) as premium_count,
                    SUM(CASE WHEN transaction_type = 'premium' THEN amount ELSE 0 END) as premium_total,
                    COUNT(CASE WHEN transaction_type = 'claim' THEN 1 END) as claim_count,
                    SUM(CASE WHEN transaction_type = 'claim' THEN amount ELSE 0 END) as claim_total
                FROM transactions
                WHERE transaction_date BETWEEN :start AND :end";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        $summary = $stmt->fetch(PDO::FETCH_ASSOC);
        $summary['total_count'] = $summary['premium_count'] + $summary['claim_count'];
        $summary['net_total'] = $summary['premium_total'] - $summary['claim_total'];

        return $summary;
    }

    /**
     * Get performance metrics
     * 
     * @return array Performance metrics
     */
    private function getPerformanceMetrics() {
        // Get current period metrics
        $current = $this->getCurrentPeriodMetrics();
        
        // Get previous period metrics
        $previous = $this->getPreviousPeriodMetrics();

        // Calculate growth rates
        $metrics = [
            'total_policies' => $current['total_policies'],
            'policy_growth' => $this->calculateGrowthRate($current['total_policies'], $previous['total_policies']),
            'collection_rate' => $current['collection_rate'],
            'collection_change' => $this->calculateGrowthRate($current['collection_rate'], $previous['collection_rate']),
            'claims_ratio' => $current['claims_ratio'],
            'claims_ratio_change' => $this->calculateGrowthRate($current['claims_ratio'], $previous['claims_ratio'])
        ];

        return $metrics;
    }

    /**
     * Get current period metrics
     * 
     * @return array Current period metrics
     */
    private function getCurrentPeriodMetrics() {
        $sql = "SELECT 
                    COUNT(DISTINCT p.id) as total_policies,
                    (COUNT(CASE WHEN t.status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as collection_rate,
                    (SUM(CASE WHEN t.transaction_type = 'claim' THEN t.amount ELSE 0 END) * 100.0 / 
                     SUM(CASE WHEN t.transaction_type = 'premium' THEN t.amount ELSE 0 END)) as claims_ratio
                FROM policies p
                LEFT JOIN transactions t ON p.id = t.policy_id
                WHERE t.transaction_date BETWEEN :start AND :end";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get previous period metrics
     * 
     * @return array Previous period metrics
     */
    private function getPreviousPeriodMetrics() {
        $interval = strtotime($this->date_range['end']) - strtotime($this->date_range['start']);
        $previous_start = date('Y-m-d', strtotime($this->date_range['start']) - $interval);
        $previous_end = date('Y-m-d', strtotime($this->date_range['start']) - 1);

        $sql = "SELECT 
                    COUNT(DISTINCT p.id) as total_policies,
                    (COUNT(CASE WHEN t.status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as collection_rate,
                    (SUM(CASE WHEN t.transaction_type = 'claim' THEN t.amount ELSE 0 END) * 100.0 / 
                     SUM(CASE WHEN t.transaction_type = 'premium' THEN t.amount ELSE 0 END)) as claims_ratio
                FROM policies p
                LEFT JOIN transactions t ON p.id = t.policy_id
                WHERE t.transaction_date BETWEEN :start AND :end";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $previous_start,
            ':end' => $previous_end
        ]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Calculate growth rate
     * 
     * @param float $current Current value
     * @param float $previous Previous value
     * @return float Growth rate
     */
    private function calculateGrowthRate($current, $previous) {
        if ($previous == 0) {
            return 0;
        }
        return round((($current - $previous) / $previous) * 100, 1);
    }

    /**
     * Get policy status distribution
     * 
     * @return array Policy status distribution
     */
    private function getPolicyStatusDistribution() {
        $sql = "SELECT 
                    status,
                    COUNT(*) as count,
                    SUM(premium) as total_premium
                FROM policies
                WHERE created_at <= :end
                GROUP BY status";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':end' => $this->date_range['end']
        ]);

        $statuses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total = array_sum(array_column($statuses, 'count'));

        foreach ($statuses as &$status) {
            $status['percentage'] = ($total > 0) ? round(($status['count'] / $total) * 100, 1) : 0;
        }

        return $statuses;
    }

    /**
     * Get product performance
     * 
     * @return array Product performance
     */
    private function getProductPerformance() {
        $sql = "SELECT 
                    p.product_type,
                    COUNT(DISTINCT p.id) as active_policies,
                    SUM(p.premium) as total_premium,
                    AVG(p.premium) as average_premium,
                    (COUNT(CASE WHEN t.status = 'completed' THEN 1 END) * 100.0 / COUNT(*)) as collection_rate
                FROM policies p
                LEFT JOIN transactions t ON p.id = t.policy_id
                WHERE p.status = 'active'
                AND t.transaction_date BETWEEN :start AND :end
                GROUP BY p.product_type";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get claims analysis
     * 
     * @return array Claims analysis
     */
    private function getClaimsAnalysis() {
        $sql = "SELECT 
                    p.product_type,
                    COUNT(DISTINCT c.id) as total_claims,
                    SUM(t.amount) as total_amount,
                    AVG(t.amount) as average_claim,
                    (SUM(t.amount) * 100.0 / SUM(p.premium)) as claims_ratio
                FROM policies p
                JOIN claims c ON p.id = c.policy_id
                JOIN transactions t ON c.id = t.claim_id
                WHERE t.transaction_date BETWEEN :start AND :end
                GROUP BY p.product_type";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get renewal analysis
     * 
     * @return array Renewal analysis
     */
    private function getRenewalAnalysis() {
        $sql = "SELECT 
                    DATE_FORMAT(expiry_date, '%Y-%m') as month,
                    COUNT(*) as expiring_policies,
                    COUNT(CASE WHEN renewal_status = 'renewed' THEN 1 END) as renewed,
                    (COUNT(CASE WHEN renewal_status = 'renewed' THEN 1 END) * 100.0 / COUNT(*)) as renewal_rate,
                    SUM(CASE WHEN renewal_status = 'lapsed' THEN premium ELSE 0 END) as lost_premium
                FROM policies
                WHERE expiry_date BETWEEN :start AND :end
                GROUP BY month
                ORDER BY month";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([
            ':start' => $this->date_range['start'],
            ':end' => $this->date_range['end']
        ]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client information
     * 
     * @param int $client_id Client ID
     * @return array Client information
     */
    private function getClientInfo($client_id) {
        $sql = "SELECT 
                    c.*,
                    COUNT(DISTINCT p.id) as total_policies
                FROM clients c
                LEFT JOIN policies p ON c.id = p.client_id
                WHERE c.id = :client_id
                GROUP BY c.id";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);

        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get client policies
     * 
     * @param int $client_id Client ID
     * @return array Client policies
     */
    private function getClientPolicies($client_id) {
        $sql = "SELECT 
                    p.*,
                    pt.name as product_type
                FROM policies p
                JOIN product_types pt ON p.product_type_id = pt.id
                WHERE p.client_id = :client_id
                ORDER BY p.created_at DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client payments
     * 
     * @param int $client_id Client ID
     * @return array Client payments
     */
    private function getClientPayments($client_id) {
        $sql = "SELECT 
                    t.*,
                    p.policy_number
                FROM transactions t
                JOIN policies p ON t.policy_id = p.id
                WHERE t.client_id = :client_id
                AND t.transaction_type = 'premium'
                ORDER BY t.transaction_date DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client claims
     * 
     * @param int $client_id Client ID
     * @return array Client claims
     */
    private function getClientClaims($client_id) {
        $sql = "SELECT 
                    c.*,
                    p.policy_number,
                    t.amount,
                    t.status as payment_status
                FROM claims c
                JOIN policies p ON c.policy_id = p.id
                LEFT JOIN transactions t ON c.id = t.claim_id
                WHERE p.client_id = :client_id
                ORDER BY c.date_filed DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);

        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get client risk assessment
     * 
     * @param int $client_id Client ID
     * @return array Client risk assessment
     */
    private function getClientRiskAssessment($client_id) {
        $sql = "SELECT 
                    factor,
                    score,
                    weight,
                    (score * weight) as weighted_score,
                    notes
                FROM client_risk_assessments
                WHERE client_id = :client_id
                ORDER BY weight DESC";

        $stmt = $this->db->prepare($sql);
        $stmt->execute([':client_id' => $client_id]);

        $factors = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $total_score = array_sum(array_column($factors, 'weighted_score'));

        return [
            'factors' => $factors,
            'total_score' => $total_score,
            'risk_level' => $this->determineRiskLevel($total_score)
        ];
    }

    /**
     * Determine risk level based on total score
     * 
     * @param float $total_score Total risk score
     * @return string Risk level
     */
    private function determineRiskLevel($total_score) {
        if ($total_score <= 30) {
            return 'Low';
        } elseif ($total_score <= 70) {
            return 'Medium';
        } else {
            return 'High';
        }
    }
} 