<?php
// Database connection (replace with your actual database credentials)
$host = 'localhost';
$dbname = 'zamsure_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Query all users and their roles
    $stmt = $pdo->query("SELECT id, username, role FROM users");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    echo "<h2>Users and Roles in Database</h2>";
    echo "<table border='1'>";
    echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
    
    foreach ($users as $user) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($user['id']) . "</td>";
        echo "<td>" . htmlspecialchars($user['username']) . "</td>";
        echo "<td>" . htmlspecialchars($user['role']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Check if any roles don't match expected values
    $expected_roles = ['Admin', 'Insurance Agent', 'Client'];
    $invalid_roles = array_filter($users, function($user) use ($expected_roles) {
        return !in_array($user['role'], $expected_roles);
    });
    
    if (!empty($invalid_roles)) {
        echo "<h3>Warning: Invalid Role Values Found</h3>";
        echo "<p>The following users have roles that don't match the expected values ('Admin', 'Insurance Agent', 'Client'):</p>";
        echo "<table border='1'>";
        echo "<tr><th>ID</th><th>Username</th><th>Role</th></tr>";
        
        foreach ($invalid_roles as $user) {
            echo "<tr>";
            echo "<td>" . htmlspecialchars($user['id']) . "</td>";
            echo "<td>" . htmlspecialchars($user['username']) . "</td>";
            echo "<td>" . htmlspecialchars($user['role']) . "</td>";
            echo "</tr>";
        }
        
        echo "</table>";
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
