<?php
// Database connection
$host = 'localhost';
$dbname = 'zamsure_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get all users with their roles and passwords
    echo "<h2>Users and Password Hashes</h2>";
    $stmt = $pdo->query("SELECT email, password, role FROM users");
    echo "<table border='1'>";
    echo "<tr><th>Email</th><th>Password Hash</th><th>Role</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['email']) . "</td>";
        echo "<td>" . htmlspecialchars($row['password']) . "</td>";
        echo "<td>" . htmlspecialchars($row['role']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Test password verification
    echo "<h2>Password Verification Tests</h2>";
    
    // Test passwords
    $testPasswords = [
        'admin@zamsure.com' => 'admin123',
        'agent@zamsure.com' => 'agent123',
        'malobampundu5@gmail.com' => 'client123'
    ];
    
    foreach ($testPasswords as $email => $password) {
        $stmt = $pdo->prepare("SELECT password FROM users WHERE email = ?");
        $stmt->execute([$email]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($user) {
            $isVerified = password_verify($password, $user['password']);
            echo "<p>Testing: $email with password $password<br>";
            echo "Stored hash: " . htmlspecialchars($user['password']) . "<br>";
            echo "Verification result: " . ($isVerified ? "SUCCESS" : "FAILED") . "</p>";
        } else {
            echo "<p>User not found: $email</p>";
        }
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
