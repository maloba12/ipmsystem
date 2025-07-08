<?php
namespace IPMS\Utils;

class RateLimiter {
    private $db;
    private $limits = [];

    public function __construct($db) {
        $this->db = $db;
    }

    public function setLimits(array $limits) {
        foreach ($limits as $action => $config) {
            if (!isset($config['limit']) || !isset($config['window'])) {
                throw new Exception("Invalid rate limit configuration for action: $action");
            }
            $this->limits[$action] = [
                'limit' => (int)$config['limit'],
                'window' => (int)$config['window']
            ];
        }
    }

    public function checkLimit($action, $userId) {
        if (!isset($this->limits[$action])) {
            return; // No limit set for this action
        }

        $config = $this->limits[$action];
        $windowStart = time() - $config['window'];

        // Check if rate limit table exists
        $this->ensureRateLimitTableExists();

        // Get request count for this user and action in the last window
        $stmt = $this->db->prepare("
            SELECT COUNT(*) as count 
            FROM rate_limits 
            WHERE user_id = ? 
            AND action = ? 
            AND created_at > FROM_UNIXTIME(?)
        ");
        $stmt->execute([$userId, $action, $windowStart]);
        $result = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($result['count'] >= $config['limit']) {
            throw new Exception("Rate limit exceeded: Maximum {$config['limit']} requests allowed per hour");
        }

        // Record this request
        $stmt = $this->db->prepare("
            INSERT INTO rate_limits (user_id, action, created_at) 
            VALUES (?, ?, NOW())
        ");
        $stmt->execute([$userId, $action]);
    }

    private function ensureRateLimitTableExists() {
        $stmt = $this->db->prepare("
            CREATE TABLE IF NOT EXISTS rate_limits (
                id INT AUTO_INCREMENT PRIMARY KEY,
                user_id INT NOT NULL,
                action VARCHAR(50) NOT NULL,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
                INDEX idx_user_action (user_id, action)
            )
        ");
        $stmt->execute();
    }

    public function cleanupOldEntries() {
        $stmt = $this->db->prepare("
            DELETE FROM rate_limits 
            WHERE created_at < DATE_SUB(NOW(), INTERVAL 24 HOUR)
        ");
        $stmt->execute();
    }
}
