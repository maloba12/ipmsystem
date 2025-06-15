<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class IntegrationController {
    private $db;
    private $integrations = [
        'payment_gateway' => [
            'required_fields' => ['api_key', 'secret_key', 'webhook_secret'],
            'test_endpoint' => 'https://api.payment-gateway.com/test',
            'webhook_events' => ['payment.success', 'payment.failed', 'refund.processed']
        ],
        'sms_provider' => [
            'required_fields' => ['api_key', 'sender_id'],
            'test_endpoint' => 'https://api.sms-provider.com/test',
            'webhook_events' => ['sms.delivered', 'sms.failed']
        ],
        'email_service' => [
            'required_fields' => ['api_key', 'from_email', 'reply_to'],
            'test_endpoint' => 'https://api.email-service.com/test',
            'webhook_events' => ['email.delivered', 'email.bounced', 'email.opened']
        ],
        'document_storage' => [
            'required_fields' => ['access_key', 'secret_key', 'bucket_name'],
            'test_endpoint' => 'https://api.storage.com/test',
            'webhook_events' => ['file.uploaded', 'file.deleted']
        ]
    ];

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function getIntegrations() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM integrations 
                ORDER BY name ASC
            ");
            $integrations = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mask sensitive data
            foreach ($integrations as &$integration) {
                $integration['config'] = $this->maskSensitiveData(
                    json_decode($integration['config'], true)
                );
            }

            sendJsonResponse(['integrations' => $integrations]);

        } catch (Exception $e) {
            error_log("Error fetching integrations: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch integrations'], 500);
        }
    }

    public function getIntegration($id) {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->prepare("
                SELECT * FROM integrations 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$integration) {
                sendJsonResponse(['error' => 'Integration not found'], 404);
                return;
            }

            // Mask sensitive data
            $integration['config'] = $this->maskSensitiveData(
                json_decode($integration['config'], true)
            );

            sendJsonResponse(['integration' => $integration]);

        } catch (Exception $e) {
            error_log("Error fetching integration: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch integration'], 500);
        }
    }

    public function createIntegration() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $name = $_POST['name'] ?? '';
            $type = $_POST['type'] ?? '';
            $config = $_POST['config'] ?? [];

            // Validate required fields
            if (empty($name) || empty($type)) {
                sendJsonResponse(['error' => 'Name and type are required'], 400);
                return;
            }

            // Validate integration type
            if (!isset($this->integrations[$type])) {
                sendJsonResponse(['error' => 'Invalid integration type'], 400);
                return;
            }

            // Validate required configuration fields
            $required_fields = $this->integrations[$type]['required_fields'];
            foreach ($required_fields as $field) {
                if (!isset($config[$field]) || empty($config[$field])) {
                    sendJsonResponse(['error' => "Missing required field: {$field}"], 400);
                    return;
                }
            }

            // Test integration
            $test_result = $this->testIntegration($type, $config);
            if (!$test_result['success']) {
                sendJsonResponse(['error' => 'Integration test failed: ' . $test_result['message']], 400);
                return;
            }

            // Insert integration
            $stmt = $this->db->prepare("
                INSERT INTO integrations (name, type, config, status, created_at, updated_at)
                VALUES (?, ?, ?, 'active', NOW(), NOW())
            ");
            $stmt->execute([
                $name,
                $type,
                json_encode($config)
            ]);

            $integration_id = $this->db->lastInsertId();

            logActivity(
                $_SESSION['user_id'],
                'integration_created',
                "Created integration: {$name} ({$type})"
            );

            sendJsonResponse([
                'message' => 'Integration created successfully',
                'integration_id' => $integration_id
            ]);

        } catch (Exception $e) {
            error_log("Error creating integration: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to create integration'], 500);
        }
    }

    public function updateIntegration($id) {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $name = $_POST['name'] ?? '';
            $config = $_POST['config'] ?? [];
            $status = $_POST['status'] ?? '';

            // Validate required fields
            if (empty($name)) {
                sendJsonResponse(['error' => 'Name is required'], 400);
                return;
            }

            // Get current integration
            $stmt = $this->db->prepare("
                SELECT * FROM integrations 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$integration) {
                sendJsonResponse(['error' => 'Integration not found'], 404);
                return;
            }

            // Validate required configuration fields
            $required_fields = $this->integrations[$integration['type']]['required_fields'];
            foreach ($required_fields as $field) {
                if (!isset($config[$field]) || empty($config[$field])) {
                    sendJsonResponse(['error' => "Missing required field: {$field}"], 400);
                    return;
                }
            }

            // Test integration if config changed
            if ($config !== json_decode($integration['config'], true)) {
                $test_result = $this->testIntegration($integration['type'], $config);
                if (!$test_result['success']) {
                    sendJsonResponse(['error' => 'Integration test failed: ' . $test_result['message']], 400);
                    return;
                }
            }

            // Update integration
            $stmt = $this->db->prepare("
                UPDATE integrations 
                SET name = ?, config = ?, status = ?, updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([
                $name,
                json_encode($config),
                $status ?: $integration['status'],
                $id
            ]);

            logActivity(
                $_SESSION['user_id'],
                'integration_updated',
                "Updated integration: {$name}"
            );

            sendJsonResponse(['message' => 'Integration updated successfully']);

        } catch (Exception $e) {
            error_log("Error updating integration: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update integration'], 500);
        }
    }

    public function deleteIntegration($id) {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            // Check if integration exists
            $stmt = $this->db->prepare("
                SELECT name FROM integrations 
                WHERE id = ?
            ");
            $stmt->execute([$id]);
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$integration) {
                sendJsonResponse(['error' => 'Integration not found'], 404);
                return;
            }

            // Delete integration
            $stmt = $this->db->prepare("
                DELETE FROM integrations 
                WHERE id = ?
            ");
            $stmt->execute([$id]);

            logActivity(
                $_SESSION['user_id'],
                'integration_deleted',
                "Deleted integration: {$integration['name']}"
            );

            sendJsonResponse(['message' => 'Integration deleted successfully']);

        } catch (Exception $e) {
            error_log("Error deleting integration: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete integration'], 500);
        }
    }

    public function testIntegration($id = null) {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            if ($id) {
                // Test existing integration
                $stmt = $this->db->prepare("
                    SELECT * FROM integrations 
                    WHERE id = ?
                ");
                $stmt->execute([$id]);
                $integration = $stmt->fetch(PDO::FETCH_ASSOC);

                if (!$integration) {
                    sendJsonResponse(['error' => 'Integration not found'], 404);
                    return;
                }

                $type = $integration['type'];
                $config = json_decode($integration['config'], true);
            } else {
                // Test new integration
                $type = $_POST['type'] ?? '';
                $config = $_POST['config'] ?? [];

                if (empty($type) || empty($config)) {
                    sendJsonResponse(['error' => 'Type and config are required'], 400);
                    return;
                }
            }

            $result = $this->testIntegrationConnection($type, $config);
            sendJsonResponse($result);

        } catch (Exception $e) {
            error_log("Error testing integration: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to test integration'], 500);
        }
    }

    public function handleWebhook($type) {
        try {
            // Get integration
            $stmt = $this->db->prepare("
                SELECT * FROM integrations 
                WHERE type = ? AND status = 'active'
            ");
            $stmt->execute([$type]);
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$integration) {
                sendJsonResponse(['error' => 'Integration not found'], 404);
                return;
            }

            // Verify webhook signature
            $config = json_decode($integration['config'], true);
            $signature = $_SERVER['HTTP_X_WEBHOOK_SIGNATURE'] ?? '';
            $payload = file_get_contents('php://input');

            if (!$this->verifyWebhookSignature($signature, $payload, $config['webhook_secret'])) {
                sendJsonResponse(['error' => 'Invalid webhook signature'], 401);
                return;
            }

            // Process webhook
            $event = json_decode($payload, true);
            $this->processWebhookEvent($type, $event);

            sendJsonResponse(['message' => 'Webhook processed successfully']);

        } catch (Exception $e) {
            error_log("Error processing webhook: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to process webhook'], 500);
        }
    }

    private function testIntegrationConnection($type, $config) {
        if (!isset($this->integrations[$type])) {
            return [
                'success' => false,
                'message' => 'Invalid integration type'
            ];
        }

        $endpoint = $this->integrations[$type]['test_endpoint'];

        try {
            $ch = curl_init($endpoint);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_POST, true);
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($config));
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $config['api_key']
            ]);

            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code === 200) {
                return [
                    'success' => true,
                    'message' => 'Integration test successful'
                ];
            }

            return [
                'success' => false,
                'message' => 'Integration test failed: ' . $response
            ];

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Integration test failed: ' . $e->getMessage()
            ];
        }
    }

    private function verifyWebhookSignature($signature, $payload, $secret) {
        $expected = hash_hmac('sha256', $payload, $secret);
        return hash_equals($expected, $signature);
    }

    private function processWebhookEvent($type, $event) {
        switch ($type) {
            case 'payment_gateway':
                $this->processPaymentWebhook($event);
                break;
            case 'sms_provider':
                $this->processSMSWebhook($event);
                break;
            case 'email_service':
                $this->processEmailWebhook($event);
                break;
            case 'document_storage':
                $this->processDocumentWebhook($event);
                break;
            default:
                throw new Exception('Unknown webhook type');
        }
    }

    private function processPaymentWebhook($event) {
        // Process payment gateway webhook
        $event_type = $event['type'] ?? '';
        $payment_id = $event['payment_id'] ?? '';

        switch ($event_type) {
            case 'payment.success':
                // Update payment status
                $stmt = $this->db->prepare("
                    UPDATE payments 
                    SET status = 'completed', 
                        updated_at = NOW() 
                    WHERE payment_reference = ?
                ");
                $stmt->execute([$payment_id]);
                break;

            case 'payment.failed':
                // Update payment status
                $stmt = $this->db->prepare("
                    UPDATE payments 
                    SET status = 'failed', 
                        updated_at = NOW() 
                    WHERE payment_reference = ?
                ");
                $stmt->execute([$payment_id]);
                break;

            case 'refund.processed':
                // Process refund
                $stmt = $this->db->prepare("
                    UPDATE payments 
                    SET status = 'refunded', 
                        updated_at = NOW() 
                    WHERE payment_reference = ?
                ");
                $stmt->execute([$payment_id]);
                break;
        }
    }

    private function processSMSWebhook($event) {
        // Process SMS provider webhook
        $event_type = $event['type'] ?? '';
        $message_id = $event['message_id'] ?? '';

        switch ($event_type) {
            case 'sms.delivered':
                // Update SMS status
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET status = 'delivered', 
                        updated_at = NOW() 
                    WHERE reference_id = ?
                ");
                $stmt->execute([$message_id]);
                break;

            case 'sms.failed':
                // Update SMS status
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET status = 'failed', 
                        updated_at = NOW() 
                    WHERE reference_id = ?
                ");
                $stmt->execute([$message_id]);
                break;
        }
    }

    private function processEmailWebhook($event) {
        // Process email service webhook
        $event_type = $event['type'] ?? '';
        $email_id = $event['email_id'] ?? '';

        switch ($event_type) {
            case 'email.delivered':
                // Update email status
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET status = 'delivered', 
                        updated_at = NOW() 
                    WHERE reference_id = ?
                ");
                $stmt->execute([$email_id]);
                break;

            case 'email.bounced':
                // Update email status
                $stmt = $this->db->prepare("
                    UPDATE notifications 
                    SET status = 'bounced', 
                        updated_at = NOW() 
                    WHERE reference_id = ?
                ");
                $stmt->execute([$email_id]);
                break;

            case 'email.opened':
                // Log email open
                $stmt = $this->db->prepare("
                    INSERT INTO notification_events 
                    (notification_id, event_type, created_at)
                    SELECT id, 'opened', NOW()
                    FROM notifications 
                    WHERE reference_id = ?
                ");
                $stmt->execute([$email_id]);
                break;
        }
    }

    private function processDocumentWebhook($event) {
        // Process document storage webhook
        $event_type = $event['type'] ?? '';
        $file_id = $event['file_id'] ?? '';

        switch ($event_type) {
            case 'file.uploaded':
                // Update document status
                $stmt = $this->db->prepare("
                    UPDATE documents 
                    SET status = 'uploaded', 
                        updated_at = NOW() 
                    WHERE file_id = ?
                ");
                $stmt->execute([$file_id]);
                break;

            case 'file.deleted':
                // Update document status
                $stmt = $this->db->prepare("
                    UPDATE documents 
                    SET status = 'deleted', 
                        updated_at = NOW() 
                    WHERE file_id = ?
                ");
                $stmt->execute([$file_id]);
                break;
        }
    }

    private function maskSensitiveData($config) {
        if (!is_array($config)) {
            return $config;
        }

        $sensitive_fields = ['api_key', 'secret_key', 'webhook_secret', 'access_key'];
        foreach ($sensitive_fields as $field) {
            if (isset($config[$field])) {
                $config[$field] = '********';
            }
        }

        return $config;
    }
} 