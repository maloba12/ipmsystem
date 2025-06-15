<?php
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

class SettingsController {
    private $db;

    public function __construct() {
        $this->db = getDBConnection();
    }

    public function getSystemSettings() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM system_settings 
                ORDER BY category, setting_key
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Group settings by category
            $grouped_settings = [];
            foreach ($settings as $setting) {
                $grouped_settings[$setting['category']][] = $setting;
            }

            sendJsonResponse(['settings' => $grouped_settings]);

        } catch (PDOException $e) {
            error_log("Error fetching system settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch system settings'], 500);
        }
    }

    public function updateSystemSettings() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                sendJsonResponse(['error' => 'No settings provided'], 400);
                return;
            }

            $this->db->beginTransaction();

            try {
                $stmt = $this->db->prepare("
                    UPDATE system_settings 
                    SET setting_value = ?,
                        updated_at = NOW()
                    WHERE setting_key = ?
                ");

                foreach ($settings as $key => $value) {
                    $stmt->execute([$value, $key]);
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'settings_updated',
                    'Updated system settings'
                );

                sendJsonResponse(['message' => 'Settings updated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error updating system settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update system settings'], 500);
        }
    }

    public function getEmailTemplates() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM email_templates 
                ORDER BY template_name
            ");
            $templates = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['templates' => $templates]);

        } catch (PDOException $e) {
            error_log("Error fetching email templates: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch email templates'], 500);
        }
    }

    public function updateEmailTemplate() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $template_id = $_POST['template_id'] ?? null;
            $subject = $_POST['subject'] ?? null;
            $body = $_POST['body'] ?? null;
            $variables = $_POST['variables'] ?? null;

            if (!$template_id || !$subject || !$body) {
                sendJsonResponse(['error' => 'Template ID, subject, and body are required'], 400);
                return;
            }

            $stmt = $this->db->prepare("
                UPDATE email_templates 
                SET subject = ?,
                    body = ?,
                    variables = ?,
                    updated_at = NOW()
                WHERE id = ?
            ");
            $stmt->execute([$subject, $body, $variables, $template_id]);

            logActivity(
                $_SESSION['user_id'],
                'email_template_updated',
                "Updated email template ID: {$template_id}"
            );

            sendJsonResponse(['message' => 'Email template updated successfully']);

        } catch (PDOException $e) {
            error_log("Error updating email template: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update email template'], 500);
        }
    }

    public function getNotificationSettings() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM notification_settings 
                ORDER BY notification_type
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['settings' => $settings]);

        } catch (PDOException $e) {
            error_log("Error fetching notification settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch notification settings'], 500);
        }
    }

    public function updateNotificationSettings() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $settings = $_POST['settings'] ?? [];
            
            if (empty($settings)) {
                sendJsonResponse(['error' => 'No settings provided'], 400);
                return;
            }

            $this->db->beginTransaction();

            try {
                $stmt = $this->db->prepare("
                    UPDATE notification_settings 
                    SET email_enabled = ?,
                        sms_enabled = ?,
                        push_enabled = ?,
                        updated_at = NOW()
                    WHERE notification_type = ?
                ");

                foreach ($settings as $type => $config) {
                    $stmt->execute([
                        $config['email_enabled'] ?? false,
                        $config['sms_enabled'] ?? false,
                        $config['push_enabled'] ?? false,
                        $type
                    ]);
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'notification_settings_updated',
                    'Updated notification settings'
                );

                sendJsonResponse(['message' => 'Notification settings updated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error updating notification settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update notification settings'], 500);
        }
    }

    public function getSystemPreferences() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM system_preferences 
                ORDER BY preference_key
            ");
            $preferences = $stmt->fetchAll(PDO::FETCH_ASSOC);

            sendJsonResponse(['preferences' => $preferences]);

        } catch (PDOException $e) {
            error_log("Error fetching system preferences: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch system preferences'], 500);
        }
    }

    public function updateSystemPreferences() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $preferences = $_POST['preferences'] ?? [];
            
            if (empty($preferences)) {
                sendJsonResponse(['error' => 'No preferences provided'], 400);
                return;
            }

            $this->db->beginTransaction();

            try {
                $stmt = $this->db->prepare("
                    UPDATE system_preferences 
                    SET preference_value = ?,
                        updated_at = NOW()
                    WHERE preference_key = ?
                ");

                foreach ($preferences as $key => $value) {
                    $stmt->execute([$value, $key]);
                }

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'preferences_updated',
                    'Updated system preferences'
                );

                sendJsonResponse(['message' => 'System preferences updated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error updating system preferences: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update system preferences'], 500);
        }
    }

    public function getIntegrationSettings() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $stmt = $this->db->query("
                SELECT * FROM integration_settings 
                ORDER BY integration_name
            ");
            $settings = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // Mask sensitive data
            foreach ($settings as &$setting) {
                if (isset($setting['api_key'])) {
                    $setting['api_key'] = substr($setting['api_key'], 0, 4) . '****';
                }
                if (isset($setting['secret_key'])) {
                    $setting['secret_key'] = '****';
                }
            }

            sendJsonResponse(['settings' => $settings]);

        } catch (PDOException $e) {
            error_log("Error fetching integration settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to fetch integration settings'], 500);
        }
    }

    public function updateIntegrationSettings() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $integration_id = $_POST['integration_id'] ?? null;
            $settings = $_POST['settings'] ?? [];
            
            if (!$integration_id || empty($settings)) {
                sendJsonResponse(['error' => 'Integration ID and settings are required'], 400);
                return;
            }

            // Validate required fields
            $required_fields = ['api_key', 'secret_key', 'endpoint_url'];
            foreach ($required_fields as $field) {
                if (!isset($settings[$field]) || empty($settings[$field])) {
                    sendJsonResponse(['error' => "{$field} is required"], 400);
                    return;
                }
            }

            $this->db->beginTransaction();

            try {
                $stmt = $this->db->prepare("
                    UPDATE integration_settings 
                    SET api_key = ?,
                        secret_key = ?,
                        endpoint_url = ?,
                        is_active = ?,
                        updated_at = NOW()
                    WHERE id = ?
                ");
                $stmt->execute([
                    $settings['api_key'],
                    $settings['secret_key'],
                    $settings['endpoint_url'],
                    $settings['is_active'] ?? false,
                    $integration_id
                ]);

                $this->db->commit();

                logActivity(
                    $_SESSION['user_id'],
                    'integration_settings_updated',
                    "Updated integration settings ID: {$integration_id}"
                );

                sendJsonResponse(['message' => 'Integration settings updated successfully']);

            } catch (Exception $e) {
                $this->db->rollBack();
                throw $e;
            }

        } catch (PDOException $e) {
            error_log("Error updating integration settings: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to update integration settings'], 500);
        }
    }

    public function testIntegration() {
        AuthMiddleware::requireAnyRole(['admin']);
        
        try {
            $integration_id = $_POST['integration_id'] ?? null;
            
            if (!$integration_id) {
                sendJsonResponse(['error' => 'Integration ID is required'], 400);
                return;
            }

            // Get integration settings
            $stmt = $this->db->prepare("
                SELECT * FROM integration_settings 
                WHERE id = ?
            ");
            $stmt->execute([$integration_id]);
            $integration = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$integration) {
                sendJsonResponse(['error' => 'Integration not found'], 404);
                return;
            }

            // Test the integration based on its type
            $result = $this->testIntegrationConnection($integration);

            if ($result['success']) {
                logActivity(
                    $_SESSION['user_id'],
                    'integration_tested',
                    "Tested integration: {$integration['integration_name']}"
                );
            }

            sendJsonResponse($result);

        } catch (PDOException $e) {
            error_log("Error testing integration: " . $e->getMessage());
            sendJsonResponse(['error' => 'Failed to test integration'], 500);
        }
    }

    private function testIntegrationConnection($integration) {
        // Implement specific integration testing logic here
        // This is a placeholder that should be customized based on the integration type
        try {
            // Example: Test API connection
            $ch = curl_init($integration['endpoint_url']);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HTTPHEADER, [
                "Authorization: Bearer {$integration['api_key']}",
                "Content-Type: application/json"
            ]);
            
            $response = curl_exec($ch);
            $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            curl_close($ch);

            if ($http_code >= 200 && $http_code < 300) {
                return [
                    'success' => true,
                    'message' => 'Integration test successful'
                ];
            } else {
                return [
                    'success' => false,
                    'message' => 'Integration test failed',
                    'error' => "HTTP Code: {$http_code}"
                ];
            }

        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => 'Integration test failed',
                'error' => $e->getMessage()
            ];
        }
    }
} 