<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Get the POST data
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!$data) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid data']);
    exit;
}

// Extract data
$email = isset($data['email']) ? filter_var($data['email'], FILTER_SANITIZE_EMAIL) : '';
$plan = isset($data['plan']) ? htmlspecialchars($data['plan']) : '';
$reference = isset($data['reference']) ? htmlspecialchars($data['reference']) : '';
$transaction_id = isset($data['transaction_id']) ? htmlspecialchars($data['transaction_id']) : 'N/A';
$amount = isset($data['amount']) ? htmlspecialchars($data['amount']) : '0';
$payment_method = isset($data['payment_method']) ? htmlspecialchars($data['payment_method']) : 'unknown';

// Validate email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid email']);
    exit;
}

// Create payment log entry
$timestamp = date('Y-m-d H:i:s');
$logEntry = sprintf(
    "[%s] Email: %s | Plan: %s | Reference: %s | Transaction: %s | Amount: $%s | Method: %s\n",
    $timestamp,
    $email,
    $plan,
    $reference,
    $transaction_id,
    $amount,
    $payment_method
);

// Log to file
$logFile = 'payment_log.txt';
if (file_put_contents($logFile, $logEntry, FILE_APPEND | LOCK_EX) === false) {
    error_log("Failed to write to payment log");
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

$headers = "From: PrimeCast <info@primecast.world>\r\n";
$headers .= "Reply-To: info@primecast.world\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send email
$emailSent = mail($to, $subject, $message, $headers);

if (!$emailSent) {
    error_log("Failed to send confirmation email to: $email");
    // Still return success since payment was logged
}

// Return success response
echo json_encode([
    'status' => 'success',
    'message' => 'Payment logged and confirmation email sent',
    'email_sent' => $emailSent
]);
exit;
?>
