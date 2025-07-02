<?php
// Create database and import SQL

$host = 'localhost';
$username = 'root';
$password = '';

try {
    // Create database
    $pdo = new PDO("mysql:host=$host", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec("CREATE DATABASE IF NOT EXISTS ipmsystem");
    echo "Database created successfully!\n";

    // Select the database
    $pdo->exec("USE ipmsystem");

    // Import SQL files one by one
    $sqlFiles = [
        'database/db_connect.sql',
        'database/ipms_dump.sql',
        'database/insert_sample_data.sql',
        'database/joins_and_reports.sql'
    ];

    foreach ($sqlFiles as $file) {
        if (file_exists($file)) {
            $sql = file_get_contents($file);
            $statements = explode(';', $sql);
            
            foreach ($statements as $statement) {
                if (trim($statement)) {
                    try {
                        $pdo->exec(trim($statement));
                    } catch (PDOException $e) {
                        echo "Warning: Failed to execute statement from $file: " . $e->getMessage() . "\n";
                    }
                }
            }
            echo "Successfully processed $file\n";
        } else {
            echo "Warning: $file not found\n";
        }
    }

} catch(PDOException $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
