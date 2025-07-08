<?php

use IPMS\Utils\UserValidation;

class AuthController extends BaseController {
    private $db;
    private $jwt;
    private $validator;

    public function __construct($db) {
        $this->db = $db;
        $this->jwt = new JWT();
        $this->validator = new UserValidation($db);
    }

    public function login() {
        try {
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $rules = [
                'email' => ['type' => 'email'],
                'password' => ['type' => 'password']
            ];
            
            if (!$this->validateInput($data, $rules)) {
                return $this->handleError(implode(', ', $this->errorMessages));
            }

            // Sanitize inputs
            $sanitizedData = $this->sanitizeInput($data);

            // Check if user exists
            $stmt = $this->db->prepare('SELECT * FROM users WHERE email = ?');
            $stmt->execute([$sanitizedData['email']]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            if (!$user || !password_verify($sanitizedData['password'], $user['password'])) {
                return $this->handleError('Invalid email or password', 401);
            }

            if ($user['status'] !== 'active') {
                return $this->handleError('Account is not active', 403);
            }

            // Update last login
            $stmt = $this->db->prepare('UPDATE users SET last_login = NOW() WHERE id = ?');
            $stmt->execute([$user['id']]);

            // Generate JWT token
            $token = $this->jwt->generate([
                'user_id' => $user['id'],
                'email' => $user['email'],
                'role' => $user['role']
            ]);

            // Remove sensitive data
            unset($user['password']);
            unset($user['created_at']);
            unset($user['updated_at']);
            unset($user['status']);
            unset($user['last_login']);

            return $this->handleSuccess([
                'user' => $user,
                'token' => $token
            ]);

        } catch (Exception $e) {
            error_log("Login error: " . $e->getMessage());
            return $this->handleError('An error occurred during login', 500);
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
            $data = json_decode(file_get_contents('php://input'), true);
            
            // Validate input
            $rules = [
                'username' => ['type' => 'string', 'min_length' => 3, 'max_length' => 50],
                'email' => ['type' => 'email'],
                'password' => ['type' => 'password', 'min_length' => 8],
                'first_name' => ['type' => 'string', 'min_length' => 2, 'max_length' => 50],
                'last_name' => ['type' => 'string', 'min_length' => 2, 'max_length' => 50],
                'role' => ['type' => 'role']
            ];
            
            if (!$this->validateInput($data, $rules)) {
                return $this->handleError(implode(', ', $this->errorMessages));
            }

            // Sanitize inputs
            $sanitizedData = $this->sanitizeInput($data);

            // Check if email already exists
            $stmt = $this->db->prepare('SELECT id FROM users WHERE email = ?');
            $stmt->execute([$sanitizedData['email']]);
            if ($stmt->fetch()) {
                return $this->handleError('Email already registered');
            }

            // Check if username already exists
            $stmt = $this->db->prepare('SELECT id FROM users WHERE username = ?');
            $stmt->execute([$sanitizedData['username']]);
            if ($stmt->fetch()) {
                return $this->handleError('Username already taken');
            }

            // Hash password
            $hashedPassword = password_hash($sanitizedData['password'], PASSWORD_DEFAULT);

            // Insert new user
            $stmt = $this->db->prepare("
                INSERT INTO users 
                (username, email, password, first_name, last_name, role, status, created_at)
                VALUES (?, ?, ?, ?, ?, ?, 'active', NOW())
            ");
            $stmt->execute([
                $sanitizedData['username'],
                $sanitizedData['email'],
                $hashedPassword,
                $sanitizedData['first_name'],
                $sanitizedData['last_name'],
                $sanitizedData['role']
            ]);

            $userId = $this->db->lastInsertId();

            // Generate JWT token
            $token = $this->jwt->generate([
                'user_id' => $userId,
                'email' => $sanitizedData['email'],
                'role' => $sanitizedData['role']
            ]);

            // Get user data without password
            $stmt = $this->db->prepare('SELECT id, username, email, first_name, last_name, role FROM users WHERE id = ?');
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->handleSuccess([
                'user' => $user,
                'token' => $token
            ]);

        } catch (Exception $e) {
            error_log("Registration error: " . $e->getMessage());
            return $this->handleError('An error occurred during registration', 500);
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