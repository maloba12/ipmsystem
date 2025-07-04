<?php
require_once __DIR__ . '/../config/database.php';
require_once __DIR__ . '/../auth/middleware.php';
require_once __DIR__ . '/../utils/Validation.php';

header('Content-Type: application/json');

// Check CSRF token
if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
    http_response_code(403);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'INVALID_CSRF_TOKEN',
            'message' => 'Invalid CSRF token'
        ]
    ]);
    exit;
}

// Validate inputs
$validation = new Validation();
$validation->addRules([
    'policyNumber' => ['required', 'string', 'min:3', 'max:20'],
    'amount' => ['required', 'numeric', 'min:0.01'],
    'paymentDate' => ['required', 'date', 'before:tomorrow'],
    'paymentMethod' => ['required', 'in:cash,check,credit_card,bank_transfer']
]);

$errors = $validation->validate($_POST);
if (!empty($errors)) {
    http_response_code(400);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'VALIDATION_ERROR',
            'message' => 'Validation failed',
            'details' => $errors
        ]
    ]);
    exit;
}

try {
    $pdo = Database::getInstance();
    
    // Check if policy exists
    $stmt = $pdo->prepare('SELECT id FROM policies WHERE policy_number = ?');
    $stmt->execute([$_POST['policyNumber']]);
    $policy = $stmt->fetch();
    
    if (!$policy) {
        http_response_code(404);
        echo json_encode([
            'success' => false,
            'error' => [
                'code' => 'POLICY_NOT_FOUND',
                'message' => 'Policy number not found'
            ]
        ]);
        exit;
    }
    
    // Generate transaction number
    $transactionNumber = 'TXN-' . date('Ymd') . '-' . uniqid();
    
    // Insert payment
    $stmt = $pdo->prepare('INSERT INTO payments (
        policy_id,
        transaction_number,
        amount,
        payment_date,
        payment_method,
        notes,
        created_by
    ) VALUES (?, ?, ?, ?, ?, ?, ?)');
    
    $stmt->execute([
        $policy['id'],
        $transactionNumber,
        $_POST['amount'],
        $_POST['paymentDate'],
        $_POST['paymentMethod'],
        $_POST['notes'] ?? '',
        $_SESSION['user_id'] ?? null
    ]);
    
    echo json_encode([
        'success' => true,
        'data' => [
            'transaction_number' => $transactionNumber
        ]
    ]);
    
} catch (PDOException $e) {
    error_log('Payment recording error: ' . $e->getMessage());
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => [
            'code' => 'DATABASE_ERROR',
            'message' => 'Error recording payment'
        ]
    ]);
}
