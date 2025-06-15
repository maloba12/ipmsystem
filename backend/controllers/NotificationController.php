<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class NotificationController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function getNotifications() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $user_id = $_SESSION['user_id'];
            $role = $_SESSION['role'];
            
            // Get unread notifications
            $notifications = $this->getUnreadNotifications($user_id, $role);
            
            // Get notification counts by type
            $counts = $this->getNotificationCounts($user_id, $role);
            
            sendJsonResponse([
                'notifications' => $notifications,
                'counts' => $counts
            ]);

        } catch (PDOException $e) {
            error_log("Error fetching notifications: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch notifications'], 500);
        }
    }

    public function markAsRead() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $notification_id = $_POST['notification_id'] ?? null;
            $user_id = $_SESSION['user_id'];
            
            if (!$notification_id) {
                sendJsonResponse(['error' => 'Notification ID is required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse(['message' => 'Notification marked as read']);
            } else {
                sendJsonResponse(['error' => 'Notification not found'], 404);
            }

        } catch (PDOException $e) {
            error_log("Error marking notification as read: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to mark notification as read'], 500);
        }
    }

    public function markAllAsRead() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $user_id = $_SESSION['user_id'];
            
            $stmt = $this->db->prepare("
                UPDATE notifications 
                SET is_read = 1, read_at = NOW()
                WHERE user_id = ? AND is_read = 0
            ");
            $stmt->execute([$user_id]);

            sendJsonResponse(['message' => 'All notifications marked as read']);

        } catch (PDOException $e) {
            error_log("Error marking all notifications as read: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to mark all notifications as read'], 500);
        }
    }

    private function getUnreadNotifications($user_id, $role) {
        $stmt = $this->db->prepare("
            SELECT 
                n.*,
                CASE 
                    WHEN n.type = 'policy_renewal' THEN p.policy_number
                    WHEN n.type = 'claim_update' THEN cl.claim_number
                    WHEN n.type = 'payment_reminder' THEN p.policy_number
                    WHEN n.type = 'document_expiry' THEN d.document_name
                    ELSE NULL
                END as reference_number
            FROM notifications n
            LEFT JOIN policies p ON n.policy_id = p.id
            LEFT JOIN claims cl ON n.claim_id = cl.id
            LEFT JOIN documents d ON n.document_id = d.id
            WHERE n.user_id = ?
            AND n.is_read = 0
            ORDER BY n.created_at DESC
            LIMIT 50
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    private function getNotificationCounts($user_id, $role) {
        $stmt = $this->db->prepare("
            SELECT 
                type,
                COUNT(*) as count
            FROM notifications
            WHERE user_id = ?
            AND is_read = 0
            GROUP BY type
        ");
        $stmt->execute([$user_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function createPolicyRenewalNotification($policy_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.id as client_id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    u.id as agent_id
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$policy_id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                throw new Exception("Policy not found");
            }

            // Create notification for client
            $this->createNotification([
                'user_id' => $policy['client_id'],
                'type' => 'policy_renewal',
                'title' => 'Policy Renewal Reminder',
                'message' => "Your policy {$policy['policy_number']} is due for renewal on " . date('Y-m-d', strtotime($policy['end_date'])),
                'policy_id' => $policy_id,
                'priority' => 'high'
            ]);

            // Create notification for agent
            $this->createNotification([
                'user_id' => $policy['agent_id'],
                'type' => 'policy_renewal',
                'title' => 'Policy Renewal Follow-up Required',
                'message' => "Policy {$policy['policy_number']} for {$policy['first_name']} {$policy['last_name']} is due for renewal",
                'policy_id' => $policy_id,
                'priority' => 'medium'
            ]);

        } catch (Exception $e) {
            error_log("Error creating policy renewal notification: " . $e->getMessage());
        }
    }

    public function createClaimUpdateNotification($claim_id, $status, $message) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    cl.*,
                    p.policy_number,
                    c.id as client_id,
                    c.first_name,
                    c.last_name,
                    u.id as agent_id,
                    a.id as adjuster_id
                FROM claims cl
                JOIN policies p ON cl.policy_id = p.id
                JOIN clients c ON p.client_id = c.id
                JOIN users u ON p.created_by = u.id
                LEFT JOIN users a ON cl.assigned_to = a.id
                WHERE cl.id = ?
            ");
            $stmt->execute([$claim_id]);
            $claim = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$claim) {
                throw new Exception("Claim not found");
            }

            // Create notification for client
            $this->createNotification([
                'user_id' => $claim['client_id'],
                'type' => 'claim_update',
                'title' => 'Claim Status Update',
                'message' => "Your claim {$claim['claim_number']} has been {$status}: {$message}",
                'claim_id' => $claim_id,
                'priority' => 'high'
            ]);

            // Create notification for agent
            $this->createNotification([
                'user_id' => $claim['agent_id'],
                'type' => 'claim_update',
                'title' => 'Claim Status Update',
                'message' => "Claim {$claim['claim_number']} for {$claim['first_name']} {$claim['last_name']} has been {$status}",
                'claim_id' => $claim_id,
                'priority' => 'medium'
            ]);

            // Create notification for adjuster if assigned
            if ($claim['adjuster_id']) {
                $this->createNotification([
                    'user_id' => $claim['adjuster_id'],
                    'type' => 'claim_update',
                    'title' => 'Claim Status Update',
                    'message' => "Claim {$claim['claim_number']} has been {$status}",
                    'claim_id' => $claim_id,
                    'priority' => 'medium'
                ]);
            }

        } catch (Exception $e) {
            error_log("Error creating claim update notification: " . $e->getMessage());
        }
    }

    public function createPaymentReminderNotification($policy_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    p.*,
                    c.id as client_id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    u.id as agent_id
                FROM policies p
                JOIN clients c ON p.client_id = c.id
                JOIN users u ON p.created_by = u.id
                WHERE p.id = ?
            ");
            $stmt->execute([$policy_id]);
            $policy = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$policy) {
                throw new Exception("Policy not found");
            }

            // Create notification for client
            $this->createNotification([
                'user_id' => $policy['client_id'],
                'type' => 'payment_reminder',
                'title' => 'Premium Payment Reminder',
                'message' => "Premium payment of {$policy['premium_amount']} for policy {$policy['policy_number']} is due",
                'policy_id' => $policy_id,
                'priority' => 'high'
            ]);

            // Create notification for agent
            $this->createNotification([
                'user_id' => $policy['agent_id'],
                'type' => 'payment_reminder',
                'title' => 'Premium Payment Follow-up Required',
                'message' => "Premium payment for policy {$policy['policy_number']} is due",
                'policy_id' => $policy_id,
                'priority' => 'medium'
            ]);

        } catch (Exception $e) {
            error_log("Error creating payment reminder notification: " . $e->getMessage());
        }
    }

    public function createDocumentExpiryNotification($document_id) {
        try {
            $stmt = $this->db->prepare("
                SELECT 
                    d.*,
                    c.id as client_id,
                    c.first_name,
                    c.last_name,
                    c.email,
                    u.id as agent_id
                FROM documents d
                JOIN clients c ON d.client_id = c.id
                JOIN users u ON d.uploaded_by = u.id
                WHERE d.id = ?
            ");
            $stmt->execute([$document_id]);
            $document = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$document) {
                throw new Exception("Document not found");
            }

            // Create notification for client
            $this->createNotification([
                'user_id' => $document['client_id'],
                'type' => 'document_expiry',
                'title' => 'Document Expiry Reminder',
                'message' => "Your document {$document['document_name']} will expire on " . date('Y-m-d', strtotime($document['expiry_date'])),
                'document_id' => $document_id,
                'priority' => 'high'
            ]);

            // Create notification for agent
            $this->createNotification([
                'user_id' => $document['agent_id'],
                'type' => 'document_expiry',
                'title' => 'Document Expiry Follow-up Required',
                'message' => "Document {$document['document_name']} for {$document['first_name']} {$document['last_name']} will expire soon",
                'document_id' => $document_id,
                'priority' => 'medium'
            ]);

        } catch (Exception $e) {
            error_log("Error creating document expiry notification: " . $e->getMessage());
        }
    }

    private function createNotification($data) {
        try {
            $stmt = $this->db->prepare("
                INSERT INTO notifications (
                    user_id, type, title, message, 
                    policy_id, claim_id, document_id,
                    priority, created_at
                ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
            ");
            
            $stmt->execute([
                $data['user_id'],
                $data['type'],
                $data['title'],
                $data['message'],
                $data['policy_id'] ?? null,
                $data['claim_id'] ?? null,
                $data['document_id'] ?? null,
                $data['priority']
            ]);

            // Log the notification creation
            logActivity(
                $data['user_id'],
                'notification_created',
                "Created {$data['type']} notification: {$data['title']}"
            );

        } catch (PDOException $e) {
            error_log("Error creating notification: " . $e->getMessage());
        }
    }

    public function deleteNotification() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $notification_id = $_POST['notification_id'] ?? null;
            $user_id = $_SESSION['user_id'];
            
            if (!$notification_id) {
                sendJsonResponse(['error' => 'Notification ID is required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                DELETE FROM notifications 
                WHERE id = ? AND user_id = ?
            ");
            $stmt->execute([$notification_id, $user_id]);

            if ($stmt->rowCount() > 0) {
                sendJsonResponse(['message' => 'Notification deleted successfully']);
            } else {
                sendJsonResponse(['error' => 'Notification not found'], 404);
            }

        } catch (PDOException $e) {
            error_log("Error deleting notification: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to delete notification'], 500);
        }
    }

    public function getNotificationSettings() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $user_id = $_SESSION['user_id'];
            
            $stmt = $this->db->prepare("
                SELECT 
                    notification_type,
                    email_notifications,
                    push_notifications,
                    sms_notifications
                FROM notification_settings
                WHERE user_id = ?
            ");
            $stmt->execute([$user_id]);
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['settings' => $settings]);

        } catch (PDOException $e) {
            error_log("Error fetching notification settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch notification settings'], 500);
        }
    }

    public function updateNotificationSettings() {
        AuthMiddleware::requireAnyRole(['admin', 'agent', 'adjuster', 'accountant']);
        
        try {
            $user_id = $_SESSION['user_id'];
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                sendJsonResponse(['error' => 'Settings are required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                INSERT INTO notification_settings (
                    user_id, notification_type,
                    email_notifications, push_notifications, sms_notifications
                ) VALUES (?, ?, ?, ?, ?)
                ON DUPLICATE KEY UPDATE
                    email_notifications = VALUES(email_notifications),
                    push_notifications = VALUES(push_notifications),
                    sms_notifications = VALUES(sms_notifications)
            ");

            foreach ($settings as $setting) {
                $stmt->execute([
                    $user_id,
                    $setting['notification_type'],
                    $setting['email_notifications'],
                    $setting['push_notifications'],
                    $setting['sms_notifications']
                ]);
            }

            sendJsonResponse(['message' => 'Notification settings updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating notification settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update notification settings'], 500);
        }
    }
} 