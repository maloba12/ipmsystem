<?php
// Prevent any output before headers
ob_start();
session_start();

// Debug logging function
function debugLog($message) {
    $logFile = __DIR__ . '/login_debug.log';
    $logMessage = '[' . date('Y-m-d H:i:s') . '] ' . $message . "\n";
    error_log($logMessage, 3, $logFile);
}

// Database connection (replace with your actual database credentials)
$host = 'localhost';
$dbname = 'zamsure_db';
$username = 'root';
$password = '';

debugLog("Starting login process");
debugLog("Database connection: host=$host, dbname=$dbname");

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    debugLog("Database connection successful");
} catch(PDOException $e) {
    debugLog("Database connection failed: " . $e->getMessage());
    die("Connection failed: " . $e->getMessage());
}

// Function to hash password
function hashPassword($password) {
    return password_hash($password, PASSWORD_DEFAULT);
}

// Function to verify password
function verifyPassword($password, $hash) {
    return password_verify($password, $hash);
}

// Function to check if session is active
function isSessionActive() {
    // Check if session is started
    if (session_status() === PHP_SESSION_NONE) {
        debugLog("Session is not started, starting now");
        session_start();
    }

    // Check for user_id and user_role in session
    if (isset($_SESSION['user_id']) && isset($_SESSION['user_role'])) {
        debugLog("Session is active. User ID: " . $_SESSION['user_id']);
        debugLog("Session role: " . $_SESSION['user_role']);
        return true;
    }
    
    debugLog("Session is not active or incomplete");
    return false;
}

// Handle login form submission
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username = $_POST['username'] ?? '';
    $password = $_POST['password'] ?? '';
    $redirect_to = $_POST['redirect_to'] ?? null;
    debugLog("Login attempt: username=$username, redirect_to=" . ($redirect_to ?? 'null'));

    if (empty($username) || empty($password)) {
        debugLog("Error: Empty credentials");
        $_SESSION['error'] = "Username and password are required";
        header("Location: login.php");
        exit();
    }

    try {
        // Prepare and execute the query
        // Debug: Log the email being used for login
        debugLog("Attempting login with email: " . $username);
        
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        // Debug: Log query results
        if ($user) {
            debugLog("User found in database:");
            debugLog("Email: " . $user['email']);
            debugLog("Role: " . $user['role']);
            debugLog("Status: " . $user['status']);
        } else {
            debugLog("No user found with email: " . $username);
        }

        if ($user) {
            debugLog("User found in database. Role: " . $user['role']);
            
            // Debug: Log password verification
            debugLog("Attempting password verification");
            debugLog("Provided password: " . $password);
            debugLog("Stored password hash: " . $user['password']);
            
            if (verifyPassword($password, $user['password'])) {
                debugLog("Password verified successfully");
                
                // Set session variables to match middleware expectations
                $_SESSION['user_id'] = $user['id'];
                $_SESSION['user_role'] = strtolower($user['role']);  // Store role in lowercase for case-insensitive comparison
                $_SESSION['user_email'] = $user['email'];
                $_SESSION['user_name'] = $user['first_name'] . ' ' . $user['last_name'];
                
                // Also set the user array for compatibility
                $_SESSION['user'] = [
                    'id' => $user['id'],
                    'email' => $user['email'],
                    'first_name' => $user['first_name'],
                    'last_name' => $user['last_name'],
                    'role' => $user['role']
                ];
                debugLog("Session set: " . json_encode($_SESSION['user']));

                // Redirect based on user role or to the requested page
                debugLog("User role: " . $user['role']);
                
                // Always redirect to the requested page after login
                if ($redirect_to) {
                    debugLog("Redirecting to requested page: " . $redirect_to);
                    header("Location: " . $redirect_to);
                    exit();
                }
                
                // If no redirect_to was provided, redirect to default dashboard based on role
                if ($user['role'] === 'Admin') {
                    debugLog("Redirecting to admin dashboard");
                    header("Location: /ipmsystem/frontend/admin_dashboard.php");
                } elseif ($user['role'] === 'Insurance Agent') {
                    debugLog("Redirecting to agent dashboard");
                    header("Location: /ipmsystem/frontend/agent_dashboard.php");
                } elseif ($user['role'] === 'Client') {
                    debugLog("Redirecting to client dashboard");
                    header("Location: /ipmsystem/frontend/client_dashboard.php");
                } else {
                    debugLog("Error: Invalid user role: " . $user['role']);
                    $_SESSION['error'] = "Invalid user role";
                    header("Location: login.php");
                }
                exit();
            } else {
                debugLog("Error: Password verification failed");
                $_SESSION['error'] = "Invalid username or password";
                header("Location: login.php");
                exit();
            }
        } else {
            debugLog("Error: User not found");
            $_SESSION['error'] = "Invalid username or password";
            header("Location: login.php");
            exit();
        }
    } catch(PDOException $e) {
        debugLog("Database error: " . $e->getMessage());
        $_SESSION['error'] = "An error occurred. Please try again later.";
        header("Location: login.php");
        exit();
    }
} else {
    // Check if already logged in
    if (isSessionActive()) {
        debugLog("Already logged in, redirecting to appropriate dashboard");
        
        // If redirect_to is set and user has permission to access it
        if (isset($_GET['redirect_to'])) {
            debugLog("Redirecting to requested page: " . $_GET['redirect_to']);
            header("Location: " . $_GET['redirect_to']);
            exit();
        }
        
        // Get user role from session
        $user_role = $_SESSION['user_role'] ?? 'Client';
        
        switch ($user_role) {
            case 'admin':
                debugLog("Redirecting to admin dashboard");
                header("Location: /ipmsystem/frontend/admin_dashboard.php");
                break;
            case 'insurance agent':
                debugLog("Redirecting to agent dashboard");
                header("Location: /ipmsystem/frontend/agent_dashboard.php");
                break;
            case 'Client':
                debugLog("Redirecting to client dashboard");
                header("Location: /ipmsystem/frontend/client_dashboard.php");
                break;
            default:
                debugLog("Error: Invalid session role");
                session_destroy();
                header("Location: login.php");
        }
        exit();
    }
}

// Clear any output buffer and redirect to login if no POST request
ob_end_clean();
header("Location: login.php");
debugLog("Redirecting to login page");
exit();
