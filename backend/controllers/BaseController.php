<?php
namespace IPMS\Controllers;

class BaseController {
    protected $db;
    protected $errorMessages = [];
    protected $validationRules = [];

    public function __construct($db) {
        $this->db = $db;
    }

    protected function validateInput($data, $rules) {
        $errors = [];
        
        foreach ($rules as $field => $rule) {
            if (!isset($data[$field])) {
                $errors[$field] = "{$field} is required";
                continue;
            }

            $value = $data[$field];
            
            // Check type
            if (isset($rule['type'])) {
                $type = $rule['type'];
                if ($type === 'email' && !filter_var($value, FILTER_VALIDATE_EMAIL)) {
                    $errors[$field] = "{$field} must be a valid email address";
                } elseif ($type === 'password' && !$this->validatePassword($value)) {
                    $errors[$field] = "{$field} must be at least 8 characters long and contain uppercase, lowercase, numbers, and special characters";
                } elseif ($type === 'role' && !$this->validateRole($value)) {
                    $errors[$field] = "{$field} must be one of: Admin, Insurance Agent, Client";
                }
            }

            // Check min length
            if (isset($rule['min_length']) && strlen($value) < $rule['min_length']) {
                $errors[$field] = "{$field} must be at least {$rule['min_length']} characters long";
            }

            // Check max length
            if (isset($rule['max_length']) && strlen($value) > $rule['max_length']) {
                $errors[$field] = "{$field} must be no more than {$rule['max_length']} characters long";
            }

            // Check regex pattern
            if (isset($rule['pattern']) && !preg_match($rule['pattern'], $value)) {
                $errors[$field] = "{$field} has an invalid format";
            }
        }

        if (!empty($errors)) {
            $this->errorMessages = array_merge($this->errorMessages, $errors);
            return false;
        }

        return true;
    }

    protected function validatePassword($password) {
        return preg_match('/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[@$!%*?&])[A-Za-z\d@$!%*?&]{8,}$/', $password);
    }

    protected function validateRole($role) {
        return in_array($role, ['Admin', 'Insurance Agent', 'Client']);
    }

    protected function sanitizeInput($data) {
        $sanitized = [];
        foreach ($data as $key => $value) {
            if (is_string($value)) {
                $sanitized[$key] = filter_var($value, FILTER_SANITIZE_STRING);
            } else {
                $sanitized[$key] = $value;
            }
        }
        return $sanitized;
    }

    protected function generateCSRFToken() {
        if (!isset($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
        return $_SESSION['csrf_token'];
    }

    protected function validateCSRFToken($token) {
        return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
    }

    protected function handleError($message, $code = 400) {
        http_response_code($code);
        return [
            'success' => false,
            'error' => $message,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }

    protected function handleSuccess($data = null) {
        return [
            'success' => true,
            'data' => $data,
            'timestamp' => date('Y-m-d H:i:s')
        ];
    }
}
