<?php

class AuthController {
    private $db;
    private $jwt;

    public function __construct($db) {
        $this->db = $db;
        $this->jwt = new JWT();
    }

    public function login() {
        try {
            // Get request data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['email']) || !isset($data['password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Email and password are required']);
                return;
            }

            // Get user from database
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$data['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['password'], $user['password'])) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid email or password']);
                return;
            }

            // Generate JWT token
            $token = $this->jwt->generate([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            // Remove sensitive data
            unset($user['password']);

            // Return user data and token
            echo json_encode([
                'user' => $user,
                'token' => $token
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during login']);
        }
    }

    public function validate() {
        try {
            // Get authorization header
            $headers = getallheaders();
            if (!isset($headers['Authorization'])) {
                http_response_code(401);
                echo json_encode(['message' => 'No token provided']);
                return;
            }

            // Extract token
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            
            // Validate token
            $payload = $this->jwt->validate($token);
            if (!$payload) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid token']);
                return;
            }

            // Get user from database
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user) {
                http_response_code(401);
                echo json_encode(['message' => 'User not found']);
                return;
            }

            // Remove sensitive data
            unset($user['password']);

            // Return user data
            echo json_encode(['user' => $user]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during token validation']);
        }
    }

    public function register() {
        try {
            // Get request data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['email']) || !isset($data['password']) || !isset($data['name'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Email, password, and name are required']);
                return;
            }

            // Check if email already exists
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$data['email']]);
            if ($stmt->fetch()) {
                http_response_code(400);
                echo json_encode(['message' => 'Email already exists']);
                return;
            }

            // Hash password
            $hashedPassword = password_hash($data['password'], PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->db->prepare('INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)');
            $stmt->execute([
                $data['name'],
                $data['email'],
                $hashedPassword,
                $data['role'] ?? 'user'
            ]);

            // Get created user
            $userId = $this->db->lastInsertId();
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            // Generate JWT token
            $token = $this->jwt->generate([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            // Remove sensitive data
            unset($user['password']);

            // Return user data and token
            echo json_encode([
                'user' => $user,
                'token' => $token
            ]);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred during registration']);
        }
    }

    public function changePassword() {
        try {
            // Get request data
            $data = json_decode(file_get_contents('php://input'), true);
            
            if (!isset($data['current_password']) || !isset($data['new_password'])) {
                http_response_code(400);
                echo json_encode(['message' => 'Current password and new password are required']);
                return;
            }

            // Get user from token
            $headers = getallheaders();
            $token = str_replace('Bearer ', '', $headers['Authorization']);
            $payload = $this->jwt->validate($token);
            
            if (!$payload) {
                http_response_code(401);
                echo json_encode(['message' => 'Invalid token']);
                return;
            }

            // Get user from database
            $stmt = $this->db->prepare('SELECT * FROM users WHERE id = ?');
            $stmt->execute([$payload['user_id']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($data['current_password'], $user['password'])) {
                http_response_code(401);
                echo json_encode(['message' => 'Current password is incorrect']);
                return;
            }

            // Hash new password
            $hashedPassword = password_hash($data['new_password'], PASSWORD_DEFAULT);

            // Update password
            $stmt = $this->db->prepare('UPDATE users SET password = ? WHERE id = ?');
            $stmt->execute([$hashedPassword, $user['id']]);

            echo json_encode(['message' => 'Password updated successfully']);
        } catch (Exception $e) {
            http_response_code(500);
            echo json_encode(['message' => 'An error occurred while changing password']);
        }
    }
} 