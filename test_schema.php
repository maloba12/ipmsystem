<?php
// Database connection
$host = 'localhost';
$dbname = 'zamsure_db';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Get table structure
    $stmt = $pdo->query("DESCRIBE users");
    echo "<h2>Users Table Structure</h2>";
    echo "<table border='1'>";
    echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
    
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        echo "<td>" . htmlspecialchars($row['Field']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Type']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Null']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Key']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Default']) . "</td>";
        echo "<td>" . htmlspecialchars($row['Extra']) . "</td>";
        echo "</tr>";
    }
    
    echo "</table>";
    
    // Get all users with their roles
    echo "<h2>Users in Database</h2>";
    $stmt = $pdo->query("SELECT * FROM users");
    echo "<table border='1'>";
    $columns = $stmt->columnCount();
    $columnNames = [];
    
    // Get column names
    for ($i = 0; $i < $columns; $i++) {
        $column = $stmt->getColumnMeta($i);
        $columnNames[] = $column['name'];
    }
    
    // Create header row
    echo "<tr>";
    foreach ($columnNames as $name) {
        echo "<th>" . htmlspecialchars($name) . "</th>";
    }
    echo "</tr>";
    
    // Display data
    while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
        echo "<tr>";
        foreach ($columnNames as $name) {
            echo "<td>" . htmlspecialchars($row[$name]) . "</td>";
        }
        echo "</tr>";
    }
    
    echo "</table>";
    
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
