<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['user'])) {
    header('Location: login.php');
    exit();
}

// Configuration
$uploadDir = __DIR__ . '/uploads/';
$allowedTypes = ['application/pdf', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'application/vnd.ms-excel'];
$maxFileSize = 5 * 1024 * 1024; // 5MB

// Create uploads directory if it doesn't exist
if (!file_exists($uploadDir)) {
    mkdir($uploadDir, 0777, true);
}

// Handle file upload
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['file'])) {
    $file = $_FILES['file'];
    $documentType = $_POST['documentType'] ?? '';
    $description = $_POST['description'] ?? '';

    // Validate file
    if ($file['error'] !== UPLOAD_ERR_OK) {
        $error = 'Error uploading file: ' . $file['error'];
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode($error));
        exit();
    }

    if (!in_array($file['type'], $allowedTypes)) {
        $error = 'Invalid file type. Allowed types: PDF, Excel';
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode($error));
        exit();
    }

    if ($file['size'] > $maxFileSize) {
        $error = 'File size too large. Maximum allowed: 5MB';
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode($error));
        exit();
    }

    // Generate unique filename
    $ext = pathinfo($file['name'], PATHINFO_EXTENSION);
    $newFilename = uniqid() . '_' . $_SESSION['user']['id'] . '.' . $ext;
    $targetPath = $uploadDir . $newFilename;

    // Move uploaded file
    if (move_uploaded_file($file['tmp_name'], $targetPath)) {
        // Store file information in database
        try {
            $pdo = new PDO("mysql:host=localhost;dbname=zamsure_db", "root", "");
            $stmt = $pdo->prepare("INSERT INTO uploaded_files (user_id, filename, document_type, description, uploaded_at) VALUES (?, ?, ?, ?, NOW())");
            $stmt->execute([
                $_SESSION['user']['id'],
                $newFilename,
                $documentType,
                $description
            ]);
            
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?success=File uploaded successfully');
            exit();
        } catch (PDOException $e) {
            $error = 'Database error: ' . $e->getMessage();
            header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode($error));
            exit();
        }
    } else {
        $error = 'Failed to move uploaded file';
        header('Location: ' . $_SERVER['HTTP_REFERER'] . '?error=' . urlencode($error));
        exit();
    }
}
?>
