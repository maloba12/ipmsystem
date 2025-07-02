<?php
require_once __DIR__ . '/backend/config/config.php';

// Function to execute SQL and return success status
function executeSQL($pdo, $sql) {
    try {
        $pdo->exec($sql);
        return true;
    } catch (PDOException $e) {
        return false;
    }
}

// Function to create table if it doesn't exist
function createTableIfNotExists($pdo, $tableName, $sql) {
    global $tableCreated;
    
    try {
        // Check if table exists
        $stmt = $pdo->prepare("SHOW TABLES LIKE ?");
        $stmt->execute([$tableName]);
        
        if ($stmt->rowCount() == 0) {
            if (executeSQL($pdo, $sql)) {
                echo "<p style='color: green;'>✓ Table $tableName created successfully</p>";
                $tableCreated = true;
            } else {
                echo "<p style='color: red;'>✗ Failed to create table $tableName</p>";
            }
        } else {
            echo "<p style='color: blue;'>• Table $tableName already exists</p>";
        }
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Error checking table $tableName: " . $e->getMessage() . "</p>";
    }
}

try {
    $pdo = getDBConnection();
    echo "<h2>IPM System Database Setup</h2>";
    echo "<p>Database: " . DB_NAME . "</p>";
    
    // Create clients table
    $clientsSQL = "CREATE TABLE IF NOT EXISTS clients (
        id INT PRIMARY KEY AUTO_INCREMENT,
        first_name VARCHAR(50) NOT NULL,
        last_name VARCHAR(50) NOT NULL,
        email VARCHAR(100) UNIQUE NOT NULL,
        phone VARCHAR(20),
        address TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    createTableIfNotExists($pdo, 'clients', $clientsSQL);
    
    // Create policies table
    $policiesSQL = "CREATE TABLE IF NOT EXISTS policies (
        id INT PRIMARY KEY AUTO_INCREMENT,
        policy_number VARCHAR(50) UNIQUE NOT NULL,
        client_id INT,
        policy_type VARCHAR(50) NOT NULL,
        coverage_amount DECIMAL(15,2) NOT NULL,
        premium DECIMAL(15,2) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        status VARCHAR(20) DEFAULT 'active',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (client_id) REFERENCES clients(id)
    )";
    
    createTableIfNotExists($pdo, 'policies', $policiesSQL);
    
    // Create claims table
    $claimsSQL = "CREATE TABLE IF NOT EXISTS claims (
        id INT PRIMARY KEY AUTO_INCREMENT,
        policy_id INT,
        amount DECIMAL(15,2) NOT NULL,
        description TEXT NOT NULL,
        status VARCHAR(20) DEFAULT 'pending',
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        FOREIGN KEY (policy_id) REFERENCES policies(id)
    )";
    
    createTableIfNotExists($pdo, 'claims', $claimsSQL);
    
    // Create reports table
    $reportsSQL = "CREATE TABLE IF NOT EXISTS reports (
        id INT PRIMARY KEY AUTO_INCREMENT,
        type VARCHAR(50) NOT NULL,
        start_date DATE NOT NULL,
        end_date DATE NOT NULL,
        data JSON,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    )";
    
    createTableIfNotExists($pdo, 'reports', $reportsSQL);
    
    if (isset($tableCreated) && $tableCreated) {
        echo "<h3>Database setup completed successfully!</h3>";
        echo "<p>You can now access the dashboard at <a href='dashboard.html'>dashboard.html</a></p>";
    } else {
        echo "<h3>No tables were created (all already existed)</h3>";
    }
    
} catch (PDOException $e) {
    die("Error: " . $e->getMessage());
}
?>
