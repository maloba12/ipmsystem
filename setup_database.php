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
    
    // Create system_settings table
    $systemSettingsSQL = "CREATE TABLE IF NOT EXISTS system_settings (
        id INT PRIMARY KEY AUTO_INCREMENT,
        category VARCHAR(50) NOT NULL,
        setting_key VARCHAR(100) NOT NULL,
        value TEXT,
        description TEXT,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY unique_setting (category, setting_key)
    )";

    createTableIfNotExists($pdo, 'system_settings', $systemSettingsSQL);

    // Insert default system settings
    try {
        $stmt = $pdo->prepare("INSERT INTO system_settings (category, setting_key, value, description) VALUES (?, ?, ?, ?)");
        
        // General Settings
        $stmt->execute(['general', 'app_name', 'IPM System', 'Name of the insurance processing system']);
        $stmt->execute(['general', 'version', '1.0.0', 'System version number']);
        
        // Email Settings
        $stmt->execute(['email', 'smtp_host', '', 'SMTP server host']);
        $stmt->execute(['email', 'smtp_port', '587', 'SMTP server port']);
        $stmt->execute(['email', 'smtp_user', '', 'SMTP username']);
        $stmt->execute(['email', 'smtp_pass', '', 'SMTP password']);
        
        // Security Settings
        $stmt->execute(['security', 'password_min_length', '8', 'Minimum password length']);
        $stmt->execute(['security', 'password_expiration_days', '90', 'Number of days before password expires']);
        $stmt->execute(['security', 'login_attempts', '5', 'Number of allowed login attempts before lockout']);
        
        // Display Settings
        $stmt->execute(['display', 'theme', 'light', 'System theme (light/dark)']);
        $stmt->execute(['display', 'language', 'en', 'Default language']);
        
        echo "<p style='color: green;'>✓ Default system settings inserted successfully</p>";
    } catch (PDOException $e) {
        echo "<p style='color: red;'>✗ Failed to insert default settings: " . $e->getMessage() . "</p>";
    }

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
