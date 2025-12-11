<?php
/**
 * PrimeCast Payment Activation Handler
 * Handles PayPal payment verification and customer notifications
 */

require_once __DIR__ . '/functions.php';

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

// CORS headers (adjust origin in production)
if (ENVIRONMENT === 'development') {
    header('Access-Control-Allow-Origin: *');
} else {
    header('Access-Control-Allow-Origin: https://yourdomain.com');
}
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(405, 'Method not allowed');
}

// Check POST size
checkPostSize();

// Rate limiting (5 requests per 5 minutes per IP)
checkRateLimit($_SERVER['REMOTE_ADDR'], 5, 300);

// Validate content type
if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') === false) {
    sendError(400, 'Invalid content type');
}

// Get and parse JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    sendError(400, 'Invalid JSON payload');
}

// Extract and validate data
$email = isset($data['email']) ? sanitizeInput($data['email'], 'email') : null;
$plan = isset($data['plan']) ? strtolower(trim($data['plan'])) : '';
$reference = isset($data['reference']) ? sanitizeInput($data['reference']) : '';
$transaction_id = isset($data['transaction_id']) ? trim($data['transaction_id']) : '';
$amountRaw = isset($data['amount']) ? $data['amount'] : '';
$payment_method = isset($data['payment_method']) ? strtolower(trim($data['payment_method'])) : 'unknown';

// Validate email
if (!$email) {
    sendError(400, 'Invalid email address', "Invalid email from IP: {$_SERVER['REMOTE_ADDR']}");
}

// Validate plan
if (!in_array($plan, ALLOWED_PLANS, true)) {
    sendError(400, 'Invalid plan selected', "Invalid plan '$plan' from IP: {$_SERVER['REMOTE_ADDR']}");
}

// Validate reference
if ($reference !== '' && !preg_match('/^[A-Za-z0-9._-]{1,64}$/', $reference)) {
    sendError(400, 'Invalid reference format');
}

// Validate transaction ID
if ($transaction_id === '' || !preg_match('/^[A-Za-z0-9]{6,64}$/', $transaction_id)) {
    sendError(400, 'Invalid transaction ID', "Missing/invalid transaction ID from IP: {$_SERVER['REMOTE_ADDR']}");
}

// Validate amount
$amount = preg_replace('/[^0-9.]/', '', (string) $amountRaw);
$amount = $amount === '' ? '0' : number_format((float) $amount, 2, '.', '');
if ((float) $amount <= 0) {
    sendError(400, 'Invalid amount');
}

// Validate payment method
if ($payment_method !== 'paypal') {
    sendError(400, 'Unsupported payment method');
}

/**
 * Verify PayPal Order
 */
function verifyPayPalOrder($orderID) {
    // Get PayPal access token
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . "/v1/oauth2/token");
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_USERPWD, PAYPAL_CLIENT_ID . ":" . PAYPAL_SECRET);
    curl_setopt($ch, CURLOPT_POSTFIELDS, "grant_type=client_credentials");
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("PayPal token request failed: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("PayPal token request failed: HTTP $httpCode");
        return false;
    }
    
    $json = json_decode($result, true);
    if (!isset($json['access_token'])) {
        error_log("PayPal access token not found in response");
        return false;
    }
    
    $accessToken = $json['access_token'];
    
    // Verify the order
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, PAYPAL_API_URL . "/v2/checkout/orders/" . urlencode($orderID));
    curl_setopt($ch, CURLOPT_HEADER, false);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_HTTPHEADER, [
        "Content-Type: application/json",
        "Authorization: Bearer " . $accessToken
    ]);
    
    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    
    if (curl_errno($ch)) {
        error_log("PayPal order verification failed: " . curl_error($ch));
        curl_close($ch);
        return false;
    }
    
    curl_close($ch);
    
    if ($httpCode !== 200) {
        error_log("PayPal order verification failed: HTTP $httpCode - Response: $result");
        return false;
    }
    
    $orderData = json_decode($result, true);
    
    // Verify order status
    if (!isset($orderData['status']) || $orderData['status'] !== 'COMPLETED') {
        error_log("PayPal order not completed. Status: " . ($orderData['status'] ?? 'unknown'));
        return false;
    }
    
    return $orderData;
}

// CRITICAL: Verify PayPal payment BEFORE logging
logSecurityEvent('PAYMENT_ATTEMPT', [
    'email' => $email,
    'plan' => $plan,
    'transaction_id' => $transaction_id,
    'amount' => $amount
]);

$orderData = verifyPayPalOrder($transaction_id);

if ($orderData === false) {
    logSecurityEvent('PAYMENT_VERIFICATION_FAILED', [
        'email' => $email,
        'transaction_id' => $transaction_id,
        'ip' => $_SERVER['REMOTE_ADDR']
    ]);
    
    sendError(403, 'Payment verification failed. Please contact support if you believe this is an error.');
}

// Verify amount matches
$paidAmount = floatval($orderData['purchase_units'][0]['amount']['value'] ?? 0);
$expectedAmount = getPlanPrice($plan);

if (abs($paidAmount - floatval($amount)) > 0.01) {
    logSecurityEvent('PAYMENT_AMOUNT_MISMATCH', [
        'email' => $email,
        'expected' => $amount,
        'received' => $paidAmount,
        'transaction_id' => $transaction_id
    ]);
    
    sendError(403, 'Payment amount verification failed');
}

// Optional: Verify amount matches plan price
if (abs($paidAmount - $expectedAmount) > 0.01) {
    logSecurityEvent('PAYMENT_PLAN_MISMATCH', [
        'email' => $email,
        'plan' => $plan,
        'expected_price' => $expectedAmount,
        'paid_amount' => $paidAmount
    ]);
    
    // You might want to accept this but flag for manual review
    error_log("WARNING: Plan price mismatch for $email. Plan: $plan, Expected: $expectedAmount, Paid: $paidAmount");
}

// Verify email matches (optional but recommended)
$paypalEmail = $orderData['payer']['email_address'] ?? '';
if (strtolower($paypalEmail) !== strtolower($email)) {
    logSecurityEvent('PAYMENT_EMAIL_MISMATCH', [
        'form_email' => $email,
        'paypal_email' => $paypalEmail,
        'transaction_id' => $transaction_id
    ]);
    
    // Log but don't reject - customer might use different emails
    error_log("WARNING: Email mismatch. Form: $email, PayPal: $paypalEmail");
}

// Payment verified successfully! Log it
$paymentData = [
    'email' => $email,
    'plan' => $plan,
    'reference' => $reference,
    'transaction_id' => $transaction_id,
    'amount' => $paidAmount, // Use verified amount from PayPal
    'payment_method' => $payment_method,
    'status' => 'verified'
];

if (!logPayment($paymentData)) {
    error_log("WARNING: Payment verified but failed to write to log file");
}

logSecurityEvent('PAYMENT_SUCCESS', [
    'email' => $email,
    'plan' => $plan,
    'amount' => $paidAmount,
    'transaction_id' => $transaction_id
]);

// Send confirmation email to customer
$subject = "Welcome to PrimeCast - Payment Confirmed";
$message = "Hello,

Welcome to PrimeCast!

Your payment has been successfully received and verified.

Order Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Plan: " . ucfirst($plan) . "
Amount Paid: $" . number_format($paidAmount, 2) . "
Order Reference: $reference
Transaction ID: $transaction_id
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Our activation team will prepare your login credentials and send them to your email shortly (typically within 1-4 hours during business hours).

You will receive a separate email with:
- Your account username
- Your secure password
- Server connection details
- Setup instructions

Thank you for choosing PrimeCast! We're excited to have you as a customer.

If you have any questions or need assistance, please don't hesitate to contact us:
📧 Email: " . EMAIL_FROM . "

Best regards,
The PrimeCast Team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
This is an automated confirmation. Please do not reply to this email.
";

sendEmail($email, $subject, $message);

// Send notification to admin
$adminSubject = "New PrimeCast Order - " . ucfirst($plan) . " Plan";
$adminMessage = "New order received and verified:

Customer Email: $email
Plan: " . ucfirst($plan) . "
Amount: $" . number_format($paidAmount, 2) . "
Reference: $reference
Transaction ID: $transaction_id

Action Required:
1. Create customer account
2. Send login credentials to: $email

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
View all orders: " . (ENVIRONMENT === 'production' ? 'https://yourdomain.com' : 'http://localhost') . "/admin/dashboard.php
";

sendEmail(EMAIL_FROM, $adminSubject, $adminMessage);

// Return success
sendSuccess([
    'reference' => $reference,
    'transaction_id' => $transaction_id,
    'plan' => $plan,
    'amount' => number_format($paidAmount, 2)
], 'Payment verified successfully');
