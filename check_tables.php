<?php
require_once __DIR__ . '/backend/config/config.php';

try {
    $pdo = getDBConnection();
    
    // Check required tables
    $requiredTables = ['clients', 'policies', 'claims', 'reports'];
    $existingTables = [];
    
    // Get all tables
    $stmt = $pdo->query("SHOW TABLES");
    while ($row = $stmt->fetch(PDO::FETCH_NUM)) {
        $existingTables[] = $row[0];
    }
    
    echo "<h2>Database Tables Check</h2>";
    echo "<p>Database: " . DB_NAME . "</p>";
    echo "<h3>Existing Tables:</h3>";
    echo "<ul>";
    
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "<li style='color: green;'>✓ $table</li>";
        } else {
            echo "<li style='color: red;'>✗ $table</li>";
        }
    }
    
    echo "</ul>";
    
    // Check table structures
    foreach ($requiredTables as $table) {
        if (in_array($table, $existingTables)) {
            echo "<h3>Structure of $table:</h3>";
            $stmt = $pdo->query("DESCRIBE $table");
            echo "<table border='1'>";
            echo "<tr><th>Field</th><th>Type</th><th>Null</th><th>Key</th><th>Default</th><th>Extra</th></tr>";
            while ($row = $stmt->fetch(PDO::FETCH_ASSOC)) {
                echo "<tr>";
                echo "<td>" . htmlspecialchars($row['Field'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['Type'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['Null'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['Key'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['Default'] ?? '') . "</td>";
                echo "<td>" . htmlspecialchars($row['Extra'] ?? '') . "</td>";
                echo "</tr>";
            }
            echo "</table>";
        }
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
