<?php
session_start();
require_once __DIR__ . '/../config/config.php';
require_once __DIR__ . '/../helpers/functions.php';

header('Content-Type: application/json');

// Rate limiting
$ip = $_SERVER['REMOTE_ADDR'];
$attempts_file = sys_get_temp_dir() . '/login_attempts_' . md5($ip) . '.json';

function checkRateLimit($ip) {
    global $attempts_file;
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true);
        if ($attempts['count'] >= 5 && (time() - $attempts['time']) < 300) {
            sendJsonResponse(['error' => 'Too many login attempts. Please try again in 5 minutes.'], 429);
        }
        if ((time() - $attempts['time']) >= 300) {
            unlink($attempts_file);
        }
    }
}

function updateRateLimit($ip) {
    global $attempts_file;
    
    if (file_exists($attempts_file)) {
        $attempts = json_decode(file_get_contents($attempts_file), true);
        $attempts['count']++;
    } else {
        $attempts = ['count' => 1, 'time' => time()];
    }
    file_put_contents($attempts_file, json_encode($attempts));
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendJsonResponse(['error' => 'Invalid request method'], 405);
}

checkRateLimit($ip);

// Get and sanitize input
$email = sanitize($_POST['email'] ?? '');
$password = $_POST['password'] ?? '';

// Validate input
if (empty($email) || empty($password)) {
    updateRateLimit($ip);
    sendJsonResponse(['error' => 'Email and password are required'], 400);
}

if (!isValidEmail($email)) {
    updateRateLimit($ip);
    sendJsonResponse(['error' => 'Invalid email format'], 400);
}

try {
    $db = getDBConnection();
    
    // Get user from database
    $stmt = $db->prepare("SELECT id, email, password, role, status, failed_attempts, last_failed_login FROM users WHERE email = ?");
    $stmt->execute([$email]);
    $user = $stmt->fetch(PDO::FETCH_ASSOC);

    if (!$user) {
        updateRateLimit($ip);
        sendJsonResponse(['error' => 'Invalid credentials'], 401);
    }

    // Check if account is locked
    if ($user['failed_attempts'] >= 5 && (time() - strtotime($user['last_failed_login'])) < 300) {
        sendJsonResponse(['error' => 'Account is temporarily locked. Please try again in 5 minutes.'], 403);
    }

    // Verify password
    if (!password_verify($password, $user['password'])) {
        // Update failed attempts
        $stmt = $db->prepare("UPDATE users SET failed_attempts = failed_attempts + 1, last_failed_login = NOW() WHERE id = ?");
        $stmt->execute([$user['id']]);
        
        updateRateLimit($ip);
        sendJsonResponse(['error' => 'Invalid credentials'], 401);
    }

    // Check if user is active
    if ($user['status'] !== 'active') {
        sendJsonResponse(['error' => 'Account is not active'], 403);
    }

    // Reset failed attempts on successful login
    $stmt = $db->prepare("UPDATE users SET failed_attempts = 0, last_login = NOW() WHERE id = ?");
    $stmt->execute([$user['id']]);

    // Generate new session ID to prevent session fixation
    session_regenerate_id(true);

    // Set session variables
    $_SESSION['user_id'] = $user['id'];
    $_SESSION['user_email'] = $user['email'];
    $_SESSION['user_role'] = $user['role'];
    $_SESSION['last_activity'] = time();
    $_SESSION['csrf_token'] = generateToken();

    // Set secure session cookie
    setcookie(session_name(), session_id(), [
        'expires' => time() + SESSION_LIFETIME,
        'path' => '/',
        'secure' => true,
        'httponly' => true,
        'samesite' => 'Strict'
    ]);

    // Log successful login
    logActivity($user['id'], 'login', 'User logged in successfully');

    // Remove rate limit file on successful login
    if (file_exists($attempts_file)) {
        unlink($attempts_file);
    }

    // Send success response
    sendJsonResponse([
        'message' => 'Login successful',
        'user' => [
            'id' => $user['id'],
            'email' => $user['email'],
            'role' => $user['role']
        ],
        'csrf_token' => $_SESSION['csrf_token']
    ]);

} catch (PDOException $e) {
    error_log("Login error: " . $e->getMessage());
    sendJsonResponse(['error' => 'An error occurred during login'], 500);
}
