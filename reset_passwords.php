<?php
// Database connection
$host = 'localhost';
$dbname = 'zamsure_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Reset passwords
    $passwords = [
        'admin@zamsure.com' => 'admin123',
        'agent@zamsure.com' => 'agent123',
        'malobampundu5@gmail.com' => 'client123'
    ];
    
    foreach ($passwords as $email => $pass) {
        $hashedPassword = password_hash($pass, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE email = ?");
        $stmt->execute([$hashedPassword, $email]);
        echo "Password reset for $email to $pass\n";
    }
    
    echo "\nPassword reset complete. Try logging in again with:\n";
    foreach ($passwords as $email => $pass) {
        echo "$email / $pass\n";
    }
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
