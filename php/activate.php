<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid content type']);
    exit;
}

$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload']);
    exit;
}

// Extract and validate data
$email = isset($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_EMAIL) : '';
$plan = isset($data['plan']) ? trim($data['plan']) : '';
$reference = isset($data['reference']) ? trim($data['reference']) : '';
$transaction_id = isset($data['transaction_id']) ? trim($data['transaction_id']) : '';
$amountRaw = isset($data['amount']) ? $data['amount'] : '';
$payment_method = isset($data['payment_method']) ? trim($data['payment_method']) : 'unknown';

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

// Optional plan whitelisting to avoid arbitrary values from client
$allowedPlans = ['basic', 'standard', 'premium'];
if ($plan === '' || !in_array(strtolower($plan), $allowedPlans, true)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid plan']);
    exit;
}

if ($reference !== '' && !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $reference)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid reference']);
    exit;
}

if ($transaction_id === '' || !preg_match('/^[A-Za-z0-9]{6,64}$/', $transaction_id)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid transaction ID']);
    exit;
}

$amount = preg_replace('/[^0-9.]/', '', (string) $amountRaw);
$amount = $amount === '' ? '0' : number_format((float) $amount, 2, '.', '');
if ((float) $amount <= 0) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid amount']);
    exit;
}

if ($payment_method !== 'paypal') {
    http_response_code(400);
    echo json_encode(['error' => 'Unsupported payment method']);
    exit;
}

// TODO: Validate the PayPal order server-side using the PayPal Orders API
// before recording payment data. Example outline:
// 1. Exchange your client credentials for an access token.
// 2. Call GET /v2/checkout/orders/{transaction_id} with the token.
// 3. Verify status COMPLETED, amount/currency match $amount, and payee ID matches your account.
// 4. Reject the request if any checks fail.

// Create payment log entry
$timestamp = date('Y-m-d H:i:s');
$logEntry = sprintf(
    "[%s] Email: %s | Plan: %s | Reference: %s | Transaction: %s | Amount: $%s | Method: %s\n",
    $timestamp,
    $email,
    htmlspecialchars($plan, ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($reference, ENT_QUOTES, 'UTF-8'),
    htmlspecialchars($transaction_id, ENT_QUOTES, 'UTF-8'),
    $amount,
    htmlspecialchars($payment_method, ENT_QUOTES, 'UTF-8')
);

// Log to secured storage
$storageDir = dirname(__DIR__) . '/storage';
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
        http_response_code(500);
        echo json_encode(['error' => 'Unable to create storage directory']);
        exit;
    }
}

$logFile = $storageDir . '/payment_log.txt';
$isNewLog = !file_exists($logFile);

if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
    error_log("Failed to write to payment log");
} elseif ($isNewLog) {
    chmod($logFile, 0640);
}

// Send confirmation email
$to = $email;
$subject = "Welcome to PrimeCast - Payment Confirmed";
$message = "
Hello,

Welcome to PrimeCast!

Your payment has been received and your subscription is confirmed.

Order Details:
- Plan: $plan
- Reference: $reference
- Amount: $$amount

Our activation team will prepare your login information and send it to your email shortly (typically within 1-4 hours).

Thank you for choosing PrimeCast!

If you have any questions, please contact us at info@primecast.world

Best regards,
The PrimeCast Team
";
