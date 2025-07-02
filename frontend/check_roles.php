<?php
session_start();

// Database connection
$host = 'localhost';
$dbname = 'zamsure_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all users and their roles
    $stmt = $pdo->query("SELECT id, email, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users and Their Roles</h2>";
    echo "<pre>";
    print_r($users);
    echo "</pre>";
    
    // Check current session
    if (isset($_SESSION['user'])) {
        echo "<h2>Current Session</h2>";
        echo "<pre>";
        print_r($_SESSION['user']);
        echo "</pre>";
    }
} catch(PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
