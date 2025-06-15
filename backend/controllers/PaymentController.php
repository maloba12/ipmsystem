<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class PaymentController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function index() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'accountant']);
        
        try {
            $stmt = $this->db->query("
                SELECT p.*, 
                       CASE 
                           WHEN p.policy_id IS NOT NULL THEN 'premium'
                           WHEN p.claim_id IS NOT NULL THEN 'claim'
                           ELSE 'other'
                       END as payment_type,
                       pol.policy_number,
                       cl.claim_number,
                       c.first_name as client_first_name,
                       c.last_name as client_last_name,
                       c.email as client_email
                FROM payments p
                LEFT JOIN policies pol ON p.policy_id = pol.id
                LEFT JOIN claims cl ON p.claim_id = cl.id
                LEFT JOIN clients c ON p.client_id = c.id
                WHERE p.status != 'deleted'
                ORDER BY p.created_at DESC
            ");
            $payments = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            sendJsonResponse(['payments' => $payments]);
        } catch (PDOException $e) {
            error_log("Error fetching payments: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch payments'], 500);
        }
    }

    public function show($id) {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'accountant']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT p.*, 
                       CASE 
                           WHEN p.policy_id IS NOT NULL THEN 'premium'
                           WHEN p.claim_id IS NOT NULL THEN 'claim'
                           ELSE 'other'
                       END as payment_type,
                       pol.policy_number,
                       pol.policy_type,
                       cl.claim_number,
                       c.first_name as client_first_name,
                       c.last_name as client_last_name,
                       c.email as client_email,
                       c.phone as client_phone,
                       u.first_name as processed_by_first_name,
                       u.last_name as processed_by_last_name
                FROM payments p
                LEFT JOIN policies pol ON p.policy_id = pol.id
                LEFT JOIN claims cl ON p.claim_id = cl.id
                LEFT JOIN clients c ON p.client_id = c.id
                LEFT JOIN users u ON p.processed_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                sendJsonResponse(['error' => 'Payment not found'], 404);
            }

            // Get payment history
            $stmt = $this->db->prepare("
                SELECT ph.*, 
                       u.first_name as user_first_name,
                       u.last_name as user_last_name
                FROM payment_history ph
                LEFT JOIN users u ON ph.user_id = u.id
                WHERE ph.payment_id = ?
                ORDER BY ph.created_at DESC
            ");
            $stmt->execute([$id]);
            $payment['history'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['payment' => $payment]);
        } catch (PDOException $e) {
            error_log("Error fetching payment: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch payment details'], 500);
        }
    }

    public function processPremiumPayment() {
        AuthMiddleware::requireAnyRole(['admin', 'agent']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['policy_id', 'amount', 'payment_method', 'payment_date'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                sendJsonResponse(['error' => "Field {$field} is required"], 400);
            }
        }

        try {
            // Check if policy exists and is active
            $stmt = $this->db->prepare("
                SELECT p.*, c.id as client_id
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                WHERE p.id = ? AND p.status = 'active'
            ");
            $stmt->execute([$data['policy_id']]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                sendJsonResponse(['error' => 'Invalid or inactive policy'], 400);
            }

            // Generate payment reference
            $payment_reference = $this->generatePaymentReference('PRE');

            // Insert payment record
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    payment_reference, policy_id, client_id, amount,
                    payment_method, payment_date, status, processed_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");

            $stmt->execute([
                $payment_reference,
                $data['policy_id'],
                $policy['client_id'],
                $data['amount'],
                $data['payment_method'],
                $data['payment_date'],
                $_SESSION['user_id']
            ]);

            $paymentId = $this->db->lastInsertId();
            
            // Add to payment history
            $this->addToHistory($paymentId, 'created', 'Premium payment processed');

            // Log activity
            logActivity($_SESSION['user_id'], 'process_premium_payment', "Processed premium payment: {$payment_reference}");

            sendJsonResponse([
                'message' => 'Premium payment processed successfully',
                'payment_id' => $paymentId,
                'payment_reference' => $payment_reference
            ], 201);

        } catch (PDOException $e) {
            error_log("Error processing premium payment: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to process premium payment'], 500);
        }
    }

    public function processClaimPayment() {
        AuthMiddleware::requireAnyRole(['admin', 'accountant']);
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        // Validate required fields
        $required_fields = ['claim_id', 'amount', 'payment_method', 'payment_date'];
        foreach ($required_fields as $field) {
            if (empty($data[$field])) {
                sendJsonResponse(['error' => "Field {$field} is required"], 400);
            }
        }

        try {
            // Check if claim exists and is approved
            $stmt = $this->db->prepare("
                SELECT cl.*, p.client_id
                FROM claims cl
                JOIN policies p ON cl.policy_id = p.id
                WHERE cl.id = ? AND cl.status = 'approved'
            ");
            $stmt->execute([$data['claim_id']]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                sendJsonResponse(['error' => 'Invalid or unapproved claim'], 400);
            }

            // Generate payment reference
            $payment_reference = $this->generatePaymentReference('CLM');

            // Insert payment record
            $stmt = $this->db->prepare("
                INSERT INTO payments (
                    payment_reference, claim_id, client_id, amount,
                    payment_method, payment_date, status, processed_by,
                    created_at
                ) VALUES (?, ?, ?, ?, ?, ?, 'completed', ?, NOW())
            ");

            $stmt->execute([
                $payment_reference,
                $data['claim_id'],
                $claim['client_id'],
                $data['amount'],
                $data['payment_method'],
                $data['payment_date'],
                $_SESSION['user_id']
            ]);

            $paymentId = $this->db->lastInsertId();
            
            // Update claim status
            $stmt = $this->db->prepare("
                UPDATE claims 
                SET status = 'paid',
                    updated_by = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$_SESSION['user_id'], $data['claim_id']]);

            // Add to payment history
            $this->addToHistory($paymentId, 'created', 'Claim payment processed');

            // Log activity
            logActivity($_SESSION['user_id'], 'process_claim_payment', "Processed claim payment: {$payment_reference}");

            sendJsonResponse([
                'message' => 'Claim payment processed successfully',
                'payment_id' => $paymentId,
                'payment_reference' => $payment_reference
            ], 201);

        } catch (PDOException $e) {
            error_log("Error processing claim payment: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to process claim payment'], 500);
        }
    }

    public function update($id) {
        AuthMiddleware::requireRole('admin');
        AuthMiddleware::validateCSRF();

        $data = json_decode(file_get_contents('php://input'), true);
        
        try {
            // Check if payment exists
            $stmt = $this->db->prepare("SELECT id, status FROM payments WHERE id = ?");
            $stmt->execute([$id]);
            $payment = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$payment) {
                sendJsonResponse(['error' => 'Payment not found'], 404);
            }

            // Build update query dynamically based on provided fields
            $updates = [];
            $params = [];
            
            $allowed_fields = ['amount', 'payment_method', 'payment_date', 'status', 'notes'];
            foreach ($allowed_fields as $field) {
                if (isset($data[$field])) {
                    $updates[] = "{$field} = ?";
                    $params[] = $data[$field];
                }
            }

            if (empty($updates)) {
                sendJsonResponse(['error' => 'No valid fields to update'], 400);
            }

            $params[] = $_SESSION['user_id']; // updated_by
            $params[] = $id; // WHERE id = ?

            $sql = "UPDATE payments SET " . implode(', ', $updates) . ", 
                    updated_by = ?, updated_at = NOW() 
                    WHERE id = ?";
            
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Add to payment history
            $this->addToHistory($id, 'updated', 'Payment updated');

            // Log activity
            logActivity($_SESSION['user_id'], 'update_payment', "Updated payment ID: {$id}");

            sendJsonResponse(['message' => 'Payment updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating payment: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update payment'], 500);
        }
    }

    public function getPaymentReport() {
        AuthMiddleware::requireAnyRole(['admin', 'accountant']);
        
        $start_date = $_GET['start_date'] ?? date('Y-m-01');
        $end_date = $_GET['end_date'] ?? date('Y-m-t');
        
        try {
            // Get premium payments
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_premium_payments,
                    SUM(amount) as total_premium_amount
                FROM payments
                WHERE policy_id IS NOT NULL
                AND payment_date BETWEEN ? AND ?
                AND status = 'completed'
            ");
            $stmt->execute([$start_date, $end_date]);
            $premium_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get claim payments
            $stmt = $this->db->prepare("
                SELECT 
                    COUNT(*) as total_claim_payments,
                    SUM(amount) as total_claim_amount
                FROM payments
                WHERE claim_id IS NOT NULL
                AND payment_date BETWEEN ? AND ?
                AND status = 'completed'
            ");
            $stmt->execute([$start_date, $end_date]);
            $claim_stats = $stmt->fetch(PDO::FETCH_ASSOC);

            // Get payment method distribution
            $stmt = $this->db->prepare("
                SELECT 
                    payment_method,
                    COUNT(*) as count,
                    SUM(amount) as total_amount
                FROM payments
                WHERE payment_date BETWEEN ? AND ?
                AND status = 'completed'
                GROUP BY payment_method
            ");
            $stmt->execute([$start_date, $end_date]);
            $method_stats = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse([
                'period' => [
                    'start_date' => $start_date,
                    'end_date' => $end_date
                ],
                'premium_payments' => $premium_stats,
                'claim_payments' => $claim_stats,
                'payment_methods' => $method_stats
            ]);

        } catch (PDOException $e) {
            error_log("Error generating payment report: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to generate payment report'], 500);
        }
    }

    private function generatePaymentReference($type) {
        $prefix = $type;
        $timestamp = time();
        $random = rand(1000, 9999);
        return "{$prefix}-{$timestamp}-{$random}";
    }

    private function addToHistory($payment_id, $action, $details) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO payment_history (
                    payment_id, action, details, user_id, created_at
                ) VALUES (?, ?, ?, ?, NOW())
            ");
            $stmt->execute([
                $payment_id,
                $action,
                $details,
                $_SESSION['user_id']
            ]);
        } catch (PDOException $e) {
            error_log("Error adding to payment history: " . $e->getMessage());
        }
    }
}
