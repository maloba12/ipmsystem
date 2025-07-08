<?php
namespace IPMS\Utils;

class UserValidation {
    private $db;
    private $passwordRequirements = [
        'min_length' => 8,
        'max_length' => 100,
        'require_uppercase' => true,
        'require_lowercase' => true,
        'require_number' => true,
        'require_special_char' => true
    ];

    public function __construct($db) {
        $this->db = $db;
    }

    public function validatePassword($password) {
        if (strlen($password) < $this->passwordRequirements['min_length']) {
            throw new \Exception("Password must be at least {$this->passwordRequirements['min_length']} characters long");
        }

        if (strlen($password) > $this->passwordRequirements['max_length']) {
            throw new \Exception("Password cannot exceed {$this->passwordRequirements['max_length']} characters");
        }

        if ($this->passwordRequirements['require_uppercase'] && !preg_match('/[A-Z]/', $password)) {
            throw new \Exception("Password must contain at least one uppercase letter");
        }

        if ($this->passwordRequirements['require_lowercase'] && !preg_match('/[a-z]/', $password)) {
            throw new \Exception("Password must contain at least one lowercase letter");
        }

        if ($this->passwordRequirements['require_number'] && !preg_match('/[0-9]/', $password)) {
            throw new \Exception("Password must contain at least one number");
        }

        if ($this->passwordRequirements['require_special_char'] && !preg_match('/[!@#$%^&*(),.?":{}|<>]/', $password)) {
            throw new \Exception("Password must contain at least one special character");
        }

        return true;
    }

    public function validateEmail($email) {
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            throw new \Exception("Invalid email format");
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute([$email]);
        if ($stmt->fetch()) {
            throw new \Exception("Email address is already registered");
        }

        return true;
    }

    public function validateUsername($username) {
        if (!preg_match('/^[a-zA-Z0-9_]+$/', $username)) {
            throw new \Exception("Username can only contain letters, numbers, and underscores");
        }

        if (strlen($username) < 3 || strlen($username) > 50) {
            throw new \Exception("Username must be between 3 and 50 characters long");
        }

        $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            throw new \Exception("Username is already taken");
        }

        return true;
    }

    public function validateRole($role) {
        $allowedRoles = ['Admin', 'Insurance Agent', 'Client'];
        if (!in_array($role, $allowedRoles)) {
            throw new \Exception("Invalid role. Allowed roles are: " . implode(', ', $allowedRoles));
        }
        return true;
    }

    public function validateUserUpdate($userId, $data) {
        // Check if user exists
        $stmt = $this->db->prepare("SELECT id FROM users WHERE id = ?");
        $stmt->execute([$userId]);
        if (!$stmt->fetch()) {
            throw new \Exception("User not found");
        }

        // Validate each field if provided
        if (isset($data['email'])) {
            $this->validateEmail($data['email']);
        }

        if (isset($data['username'])) {
            $this->validateUsername($data['username']);
        }

        if (isset($data['password'])) {
            $this->validatePassword($data['password']);
        }

        if (isset($data['role'])) {
            $this->validateRole($data['role']);
        }

        return true;
    }

    public function validateResetPassword($token, $newPassword) {
        // Check if token exists and hasn't expired
        $stmt = $this->db->prepare("
            SELECT u.id, u.email 
            FROM password_resets pr 
            JOIN users u ON pr.user_id = u.id 
            WHERE pr.token = ? AND pr.expires_at > NOW()
        ");
        $stmt->execute([$token]);
        $user = $stmt->fetch();

        if (!$user) {
            throw new \Exception("Invalid or expired password reset token");
        }

        // Validate new password
        $this->validatePassword($newPassword);

        return $user;
    }
}
