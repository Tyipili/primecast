<?php
/**
 * PrimeCast Order Submission Handler
 * Simple e-transfer order processing
 */

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed', 'success' => false]);
    exit;
}

// Check POST size (1MB limit)
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 1048576) {
    http_response_code(413);
    echo json_encode(['error' => 'Payload too large', 'success' => false]);
    exit;
}

// Rate limiting (5 requests per 5 minutes per IP)
session_start();
$ip = $_SERVER['REMOTE_ADDR'];
$rateFile = __DIR__ . '/../storage/rate_limit_' . md5($ip) . '.json';
$now = time();
$maxRequests = 5;
$timeWindow = 300;

if (file_exists($rateFile)) {
    $data = json_decode(file_get_contents($rateFile), true);
    if ($data && isset($data['attempts'])) {
        $attempts = array_filter($data['attempts'], function($timestamp) use ($now, $timeWindow) {
            return ($now - $timestamp) < $timeWindow;
        });
        
        if (count($attempts) >= $maxRequests) {
            http_response_code(429);
            echo json_encode(['error' => 'Too many requests. Please try again later.', 'success' => false]);
            exit;
        }
        
        $attempts[] = $now;
        file_put_contents($rateFile, json_encode(['attempts' => $attempts]), LOCK_EX);
    }
} else {
    file_put_contents($rateFile, json_encode(['attempts' => [$now]]), LOCK_EX);
}

// Get and parse JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    http_response_code(400);
    echo json_encode(['error' => 'Invalid JSON payload', 'success' => false]);
    exit;
}

// Extract and validate data
$name = isset($data['name']) ? htmlspecialchars(strip_tags(trim($data['name'])), ENT_QUOTES, 'UTF-8') : '';
$email = isset($data['email']) ? filter_var(trim($data['email']), FILTER_VALIDATE_EMAIL) : false;
$plan = isset($data['plan']) ? htmlspecialchars(strip_tags(trim($data['plan'])), ENT_QUOTES, 'UTF-8') : '';
$amount = isset($data['amount']) ? htmlspecialchars(strip_tags(trim($data['amount'])), ENT_QUOTES, 'UTF-8') : '';
$orderRef = isset($data['order_reference']) ? htmlspecialchars(strip_tags(trim($data['order_reference'])), ENT_QUOTES, 'UTF-8') : '';
$etransferRef = isset($data['etransfer_reference']) ? htmlspecialchars(strip_tags(trim($data['etransfer_reference'])), ENT_QUOTES, 'UTF-8') : '';

// Validate inputs
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['error' => 'Name is required', 'success' => false]);
    exit;
}

if (!$email) {
    http_response_code(400);
    echo json_encode(['error' => 'Valid email is required', 'success' => false]);
    exit;
}

if (empty($plan)) {
    http_response_code(400);
    echo json_encode(['error' => 'Plan is required', 'success' => false]);
    exit;
}

if (empty($orderRef)) {
    http_response_code(400);
    echo json_encode(['error' => 'Order reference is required', 'success' => false]);
    exit;
}

if (empty($etransferRef)) {
    http_response_code(400);
    echo json_encode(['error' => 'E-transfer reference is required', 'success' => false]);
    exit;
}

// Log security event
$securityLog = __DIR__ . '/../storage/logs/security.log';
if (!is_dir(__DIR__ . '/../storage/logs')) {
    mkdir(__DIR__ . '/../storage/logs', 0750, true);
}

$securityEntry = sprintf(
    "[%s] ORDER_SUBMITTED | IP: %s | Email: %s | Reference: %s\n",
    date('Y-m-d H:i:s'),
    $ip,
    $email,
    $orderRef
);
file_put_contents($securityLog, $securityEntry, FILE_APPEND | LOCK_EX);

// Log order to file
$ordersFile = __DIR__ . '/../storage/orders.txt';
$timestamp = date('Y-m-d H:i:s');
$orderEntry = sprintf(
    "%s | %s | %s | %s | %s\n",
    $timestamp,
    $email,
    $orderRef,
    $plan,
    $etransferRef
);

if (file_put_contents($ordersFile, $orderEntry, FILE_APPEND | LOCK_EX) === false) {
    error_log("Failed to write to orders log");
    http_response_code(500);
    echo json_encode(['error' => 'Failed to save order. Please contact support.', 'success' => false]);
    exit;
}

// Send confirmation email to customer
$customerSubject = "Order Received - PrimeCast";
$customerMessage = "Hello $name,

Thank you for your order with PrimeCast!

Order Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Plan: $plan
Amount: $$amount
Order Reference: $orderRef
E-transfer Reference: $etransferRef
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

We have received your order and will verify your e-transfer payment shortly.

Your login credentials will be sent to this email address within 1-4 hours after we confirm your payment.

If you have any questions, please contact us at info@primecast.world

Best regards,
The PrimeCast Team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
This is an automated confirmation.
";

$customerHeaders = "MIME-Version: 1.0\r\n";
$customerHeaders .= "Content-type: text/plain; charset=UTF-8\r\n";
$customerHeaders .= "From: PrimeCast <info@primecast.world>\r\n";
$customerHeaders .= "Reply-To: info@primecast.world\r\n";

mail($email, $customerSubject, $customerMessage, $customerHeaders);

// Send alert email to admin
$adminSubject = "New PrimeCast Order - $plan";
$adminMessage = "New order received:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Customer Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Name: $name
Email: $email

Order Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Plan: $plan
Amount to Verify: $$amount
Order Reference: $orderRef
E-transfer Reference: $etransferRef

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Action Required:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Check your bank for e-transfer with reference: $orderRef
2. Verify amount: $$amount
3. Create customer account
4. Send login credentials to: $email

View all orders in admin dashboard:
https://yourdomain.com/admin/dashboard.php
";

$adminHeaders = "MIME-Version: 1.0\r\n";
$adminHeaders .= "Content-type: text/plain; charset=UTF-8\r\n";
$adminHeaders .= "From: PrimeCast System <info@primecast.world>\r\n";

mail('info@primecast.world', $adminSubject, $adminMessage, $adminHeaders);

// Return success
echo json_encode([
    'success' => true,
    'message' => 'Order submitted successfully',
    'order_reference' => $orderRef
]);
