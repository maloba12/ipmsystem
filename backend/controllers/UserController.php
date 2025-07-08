<?php
namespace IPMS\Controllers;

use IPMS\Utils\UserValidation;

class UserController extends BaseController {
    private $db;
    private $validator;
    
    public function __construct($db) {
        $this->db = $db;
        $this->validator = new UserValidation($db);
    }
    
    public function createUser($userData) {
        try {
            // Validate all user data
            $this->validator->validateUsername($userData['username']);
            $this->validator->validateEmail($userData['email']);
            $this->validator->validatePassword($userData['password']);
            $this->validator->validateRole($userData['role']);
            }

            // Sanitize inputs
            $sanitizedData = $this->sanitizeInput($userData);

            // Check if email already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
            $stmt->execute([$sanitizedData['email']]);
            if ($stmt->fetch()) {
                return $this->handleError('Email already exists');
            }

            // Check if username already exists
            $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
            $stmt->execute([$sanitizedData['username']]);
            if ($stmt->fetch()) {
                return $this->handleError('Username already exists');
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

            // Get created user without password
            $stmt = $this->db->prepare("
                SELECT 
                    id,
                    username,
                    email,
                    first_name,
                    last_name,
                    role,
                    status,
                    DATE_FORMAT(created_at, '%Y-%m-%d %H:%i:%s') as created_at,
                    DATE_FORMAT(updated_at, '%Y-%m-%d %H:%i:%s') as updated_at,
                    DATE_FORMAT(last_login, '%Y-%m-%d %H:%i:%s') as last_login,
                    (
                        SELECT COUNT(*) 
                        FROM policies 
                        WHERE user_id = users.id
                    ) as policy_count
                FROM users 
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(PDO::FETCH_ASSOC);

            return $this->handleSuccess($user);

        } catch (Exception $e) {
            error_log("Error creating user: " . $e->getMessage());
            return $this->handleError('An error occurred while creating the user', 500);
        }
    }

    public function getUsers() {
        try {
            $stmt = $this->db->query("
                SELECT 
                    id, 
                    username, 
                    email, 
                    role, 
                    first_name, 
                    last_name, 
                    created_at, 
                    status, 
                    last_login,
                    (SELECT COUNT(*) FROM policies WHERE policies.user_id = users.id) as policy_count
                FROM users
                ORDER BY created_at DESC
            ");

            return [
                'success' => true,
                'users' => $stmt->fetchAll(\PDO::FETCH_ASSOC)
            ];

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function getUser($userId) {
        try {
            $stmt = $this->db->prepare("
                SELECT id, username, email, role, first_name, last_name, created_at, status, last_login
                FROM users
                WHERE id = ?
            ");
            $stmt->execute([$userId]);
            $user = $stmt->fetch(\PDO::FETCH_ASSOC);

            if (!$user) {
                throw new \Exception('User not found');
            }

            // Format timestamps
            $user['created_at'] = date('Y-m-d H:i:s', strtotime($user['created_at']));
            $user['last_login'] = $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : null;

            return [
                'success' => true,
                'user' => $user
            ];

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function updateUser($userId, $userData) {
        try {
            // Validate update permissions
            if (!isset($_SESSION['user']) || $_SESSION['user']['role'] !== 'Admin') {
                throw new \Exception('Unauthorized to update user');
            }

            // Validate user data
            $this->validator->validateUserUpdate($userId, $userData);

            // Prepare update statement
            $updateFields = [];
            $params = [];
            
            // Validate and sanitize each field
            if (isset($userData['username'])) {
                if (!preg_match('/^[a-zA-Z0-9_]+$/', $userData['username'])) {
                    throw new \Exception('Username can only contain letters, numbers, and underscores');
                }
                if (strlen($userData['username']) < 3 || strlen($userData['username']) > 50) {
                    throw new \Exception('Username must be between 3 and 50 characters');
                }
                $updateFields[] = 'username = ?';
                $params[] = $userData['username'];
            }
            
            if (isset($userData['email'])) {
                if (!filter_var($userData['email'], FILTER_VALIDATE_EMAIL)) {
                    throw new \Exception('Invalid email address');
                }
                $updateFields[] = 'email = ?';
                $params[] = $userData['email'];
            }
            
            if (isset($userData['password'])) {
                if (strlen($userData['password']) < 8) {
                    throw new \Exception('Password must be at least 8 characters');
                }
                $saltedPassword = $userData['password'] . 'your-secret-pepper-string';
                $updateFields[] = 'password = ?';
                $params[] = password_hash($saltedPassword, PASSWORD_DEFAULT);
            }
            
            if (isset($userData['role'])) {
                $validRoles = ['Admin', 'Insurance Agent', 'Client'];
                if (!in_array($userData['role'], $validRoles)) {
                    throw new \Exception('Invalid user role');
                }
                $updateFields[] = 'role = ?';
                $params[] = $userData['role'];
            }
            
            if (isset($userData['first_name'])) {
                if (strlen($userData['first_name']) < 2 || strlen($userData['first_name']) > 50) {
                    throw new \Exception('First name must be between 2 and 50 characters');
                }
                $updateFields[] = 'first_name = ?';
                $params[] = $userData['first_name'];
            }
            
            if (isset($userData['last_name'])) {
                if (strlen($userData['last_name']) < 2 || strlen($userData['last_name']) > 50) {
                    throw new \Exception('Last name must be between 2 and 50 characters');
                }
                $updateFields[] = 'last_name = ?';
                $params[] = $userData['last_name'];
            }

            if (empty($updateFields)) {
                throw new \Exception('No fields to update');
            }

            // Check if username is changing and if new username exists
            if (isset($userData['username']) && $userData['username'] !== $currentUser['user']['username']) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE username = ?");
                $stmt->execute([$userData['username']]);
                if ($stmt->fetch()) {
                    throw new \Exception('Username already exists');
                }
            }

            // Check if email is changing and if new email exists
            if (isset($userData['email']) && $userData['email'] !== $currentUser['user']['email']) {
                $stmt = $this->db->prepare("SELECT id FROM users WHERE email = ?");
                $stmt->execute([$userData['email']]);
                if ($stmt->fetch()) {
                    throw new \Exception('Email address already exists');
                }
            }

            $params[] = $userId;
            
            $sql = "UPDATE users SET " . implode(', ', $updateFields) . " WHERE id = ?";
            $stmt = $this->db->prepare($sql);
            $stmt->execute($params);

            // Log the update
            $this->logActivity('User updated', "User ID: $userId updated by admin");

            return [
                'success' => true,
                'message' => 'User updated successfully'
            ];

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }

    public function deleteUser($userId) {
        try {
            // Check if user exists
            $stmt = $this->db->prepare("SELECT id, username FROM users WHERE id = ?");
            $stmt->execute([$userId]);
            $user = $stmt->fetch();
            
            if (!$user) {
                throw new \Exception('User not found');
            }

            // Soft delete - mark as inactive instead of deleting
            $stmt = $this->db->prepare("UPDATE users SET status = 'deleted', deleted_at = NOW() WHERE id = ?");
            $stmt->execute([$userId]);

            // Log the deletion
            $this->logActivity('User deleted', "User {$user['username']} (ID: $userId) deleted");

            // Send notification email
            $this->sendDeletionNotification($user['email']);

            return [
                'success' => true,
                'message' => 'User deleted successfully',
                'user' => [
                    'id' => $userId,
                    'username' => $user['username']
                ]
            ];

        } catch (\Exception $e) {
            throw new \Exception($e->getMessage());
        }
    }
}
