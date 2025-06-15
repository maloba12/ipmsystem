<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class DashboardController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function getOverview() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            // Get quick statistics
            $stats = $this->getQuickStats();
            
            // Get recent activities
            $activities = $this->getRecentActivities();
            
            // Get pending tasks
            $tasks = $this->getPendingTasks();
            
            // Get performance metrics
            $metrics = $this->getPerformanceMetrics();
            
            // Get upcoming renewals
            $renewals = $this->getUpcomingRenewals();
            
            // Get recent claims
            $claims = $this->getRecentClaims();

            sendJsonResponse([
                'quick_stats' => $stats,
                'recent_activities' => $activities,
                'pending_tasks' => $tasks,
                'performance_metrics' => $metrics,
                'upcoming_renewals' => $renewals,
                'recent_claims' => $claims
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching dashboard data: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch dashboard data'], 500);
        }
    }

    private function getQuickStats() {
        $stats = [];
        
        // Total active policies
        $stmt = $this->db->query("
            SELECT COUNT(*) as count, SUM(premium_amount) as total_premium
            FROM policies 
            WHERE status = 'active'
        ");
        $stats['active_policies'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Total active clients
        $stmt = $this->db->query("
            SELECT COUNT(*) as count
            FROM clients 
            WHERE status = 'active'
        ");
        $stats['active_clients'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Pending claims
        $stmt = $this->db->query("
            SELECT COUNT(*) as count, SUM(claim_amount) as total_amount
            FROM claims 
            WHERE status = 'pending'
        ");
        $stats['pending_claims'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Today's premium payments
        $stmt = $this->db->query("
            SELECT COUNT(*) as count, SUM(amount) as total_amount
            FROM payments 
            WHERE policy_id IS NOT NULL 
            AND payment_date = CURDATE()
            AND status = 'completed'
        ");
        $stats['today_premiums'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }

    private function getRecentActivities() {
        $stmt = $this->db->query("
            SELECT 
                a.*,
                u.first_name as user_first_name,
                u.last_name as user_last_name
            FROM activity_log a
            LEFT JOIN users u ON a.user_id = u.id
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getPendingTasks() {
        $tasks = [];
        
        // Pending claim approvals
        $stmt = $this->db->query("
            SELECT 
                cl.*,
                p.policy_number,
                c.first_name as client_first_name,
                c.last_name as client_last_name
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            JOIN clients c ON p.client_id = c.id
            WHERE cl.status = 'pending'
            ORDER BY cl.created_at ASC
            LIMIT 5
        ");
        $tasks['pending_claims'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pending policy renewals
        $stmt = $this->db->query("
            SELECT 
                p.*,
                c.first_name as client_first_name,
                c.last_name as client_last_name
            FROM policies p
            JOIN clients c ON p.client_id = c.id
            WHERE p.status = 'active'
            AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY p.end_date ASC
            LIMIT 5
        ");
        $tasks['pending_renewals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Pending document uploads
        $stmt = $this->db->query("
            SELECT 
                cl.*,
                p.policy_number,
                c.first_name as client_first_name,
                c.last_name as client_last_name
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            JOIN clients c ON p.client_id = c.id
            WHERE cl.status = 'document_required'
            ORDER BY cl.created_at ASC
            LIMIT 5
        ");
        $tasks['pending_documents'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $tasks;
    }

    private function getPerformanceMetrics() {
        $metrics = [];
        
        // Monthly premium collection
        $stmt = $this->db->query("
            SELECT 
                DATE_FORMAT(payment_date, '%Y-%m') as month,
                SUM(amount) as total_amount
            FROM payments
            WHERE policy_id IS NOT NULL
            AND payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            AND status = 'completed'
            GROUP BY DATE_FORMAT(payment_date, '%Y-%m')
            ORDER BY month
        ");
        $metrics['premium_collection'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Claim settlement ratio
        $stmt = $this->db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_claims,
                SUM(CASE WHEN status = 'approved' THEN 1 ELSE 0 END) as approved_claims
            FROM claims
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $metrics['claim_settlement'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Client retention rate
        $stmt = $this->db->query("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_clients,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_clients
            FROM clients
            WHERE created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $metrics['client_retention'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $metrics;
    }

    private function getUpcomingRenewals() {
        $stmt = $this->db->query("
            SELECT 
                p.*,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                c.email as client_email,
                c.phone as client_phone
            FROM policies p
            JOIN clients c ON p.client_id = c.id
            WHERE p.status = 'active'
            AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY p.end_date ASC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getRecentClaims() {
        $stmt = $this->db->query("
            SELECT 
                cl.*,
                p.policy_number,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                u.first_name as adjuster_first_name,
                u.last_name as adjuster_last_name
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            JOIN clients c ON p.client_id = c.id
            LEFT JOIN users u ON cl.assigned_to = u.id
            WHERE cl.status != 'deleted'
            ORDER BY cl.created_at DESC
            LIMIT 10
        ");
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getAgentDashboard() {
        AuthMiddleware::requireRole('agent');
        
        try {
            $agent_id = $_SESSION['user_id'];
            
            // Get agent's quick statistics
            $stats = $this->getAgentStats($agent_id);
            
            // Get agent's recent activities
            $activities = $this->getAgentActivities($agent_id);
            
            // Get agent's pending tasks
            $tasks = $this->getAgentTasks($agent_id);
            
            // Get agent's performance metrics
            $metrics = $this->getAgentMetrics($agent_id);
            
            // Get agent's upcoming renewals
            $renewals = $this->getAgentRenewals($agent_id);
            
            // Get agent's recent claims
            $claims = $this->getAgentClaims($agent_id);

            sendJsonResponse([
                'quick_stats' => $stats,
                'recent_activities' => $activities,
                'pending_tasks' => $tasks,
                'performance_metrics' => $metrics,
                'upcoming_renewals' => $renewals,
                'recent_claims' => $claims
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching agent dashboard data: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch agent dashboard data'], 500);
        }
    }

    private function getAgentStats($agent_id) {
        $stats = [];
        
        // Agent's active policies
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, SUM(premium_amount) as total_premium
            FROM policies 
            WHERE created_by = ? AND status = 'active'
        ");
        $stmt->execute([$agent_id]);
        $stats['active_policies'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Agent's active clients
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count
            FROM clients 
            WHERE created_by = ? AND status = 'active'
        ");
        $stmt->execute([$agent_id]);
        $stats['active_clients'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Agent's pending claims
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, SUM(claim_amount) as total_amount
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            WHERE p.created_by = ? AND cl.status = 'pending'
        ");
        $stmt->execute([$agent_id]);
        $stats['pending_claims'] = $stmt->fetch(PDO::FETCH_ASSOC);

        // Agent's today's premium payments
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count, SUM(amount) as total_amount
            FROM payments py
            JOIN policies p ON py.policy_id = p.id
            WHERE p.created_by = ?
            AND py.payment_date = CURDATE()
            AND py.status = 'completed'
        ");
        $stmt->execute([$agent_id]);
        $stats['today_premiums'] = $stmt->fetch(PDO::FETCH_ASSOC);

        return $stats;
    }

    private function getAgentActivities($agent_id) {
        $stmt = $this->db->prepare("
            SELECT 
                a.*,
                u.first_name as user_first_name,
                u.last_name as user_last_name
            FROM activity_log a
            LEFT JOIN users u ON a.user_id = u.id
            WHERE a.user_id = ?
            ORDER BY a.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAgentTasks($agent_id) {
        $tasks = [];
        
        // Agent's pending claim approvals
        $stmt = $this->db->prepare("
            SELECT 
                cl.*,
                p.policy_number,
                c.first_name as client_first_name,
                c.last_name as client_last_name
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            JOIN clients c ON p.client_id = c.id
            WHERE p.created_by = ? AND cl.status = 'pending'
            ORDER BY cl.created_at ASC
            LIMIT 5
        ");
        $stmt->execute([$agent_id]);
        $tasks['pending_claims'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agent's pending policy renewals
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                c.first_name as client_first_name,
                c.last_name as client_last_name
            FROM policies p
            JOIN clients c ON p.client_id = c.id
            WHERE p.created_by = ? AND p.status = 'active'
            AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY p.end_date ASC
            LIMIT 5
        ");
        $stmt->execute([$agent_id]);
        $tasks['pending_renewals'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $tasks;
    }

    private function getAgentMetrics($agent_id) {
        $metrics = [];
        
        // Agent's monthly premium collection
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(py.payment_date, '%Y-%m') as month,
                SUM(py.amount) as total_amount
            FROM payments py
            JOIN policies p ON py.policy_id = p.id
            WHERE p.created_by = ?
            AND py.payment_date >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            AND py.status = 'completed'
            GROUP BY DATE_FORMAT(py.payment_date, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$agent_id]);
        $metrics['premium_collection'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agent's claim settlement ratio
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(cl.created_at, '%Y-%m') as month,
                COUNT(*) as total_claims,
                SUM(CASE WHEN cl.status = 'approved' THEN 1 ELSE 0 END) as approved_claims
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            WHERE p.created_by = ?
            AND cl.created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(cl.created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$agent_id]);
        $metrics['claim_settlement'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        // Agent's client retention rate
        $stmt = $this->db->prepare("
            SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as total_clients,
                COUNT(CASE WHEN status = 'active' THEN 1 END) as active_clients
            FROM clients
            WHERE created_by = ?
            AND created_at >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
            GROUP BY DATE_FORMAT(created_at, '%Y-%m')
            ORDER BY month
        ");
        $stmt->execute([$agent_id]);
        $metrics['client_retention'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        return $metrics;
    }

    private function getAgentRenewals($agent_id) {
        $stmt = $this->db->prepare("
            SELECT 
                p.*,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                c.email as client_email,
                c.phone as client_phone
            FROM policies p
            JOIN clients c ON p.client_id = c.id
            WHERE p.created_by = ? AND p.status = 'active'
            AND p.end_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 30 DAY)
            ORDER BY p.end_date ASC
            LIMIT 10
        ");
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getAgentClaims($agent_id) {
        $stmt = $this->db->prepare("
            SELECT 
                cl.*,
                p.policy_number,
                c.first_name as client_first_name,
                c.last_name as client_last_name,
                u.first_name as adjuster_first_name,
                u.last_name as adjuster_last_name
            FROM claims cl
            JOIN policies p ON cl.policy_id = p.id
            JOIN clients c ON p.client_id = c.id
            LEFT JOIN users u ON cl.assigned_to = u.id
            WHERE p.created_by = ? AND cl.status != 'deleted'
            ORDER BY cl.created_at DESC
            LIMIT 10
        ");
        $stmt->execute([$agent_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }
} 