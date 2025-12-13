<?php
/**
 * PrimeCast Order Submission Handler
 * Simple e-transfer order processing with CSRF protection
 */

require_once __DIR__ . '/functions.php';

// Set security and CORS headers
setSecurityHeaders();
setCORSHeaders('POST');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    sendError(405, 'Method not allowed');
}

// Validate request size
validateRequestSize();

// Start session for CSRF
startSession();

// Rate limiting
$rateLimitResult = checkRateLimit($_SERVER['REMOTE_ADDR']);
if ($rateLimitResult !== true) {
    sendError(429, $rateLimitResult);
}

// Get and parse JSON input
$input = file_get_contents('php://input');
$data = json_decode($input, true);

if (!is_array($data)) {
    sendError(400, 'Invalid JSON payload');
}

// Extract data
$name = $data['name'] ?? '';
$email = $data['email'] ?? '';
$plan = $data['plan'] ?? '';
$amount = $data['amount'] ?? '';
$orderRef = $data['order_reference'] ?? '';
$etransferRef = $data['etransfer_reference'] ?? '';
$csrfToken = $data['csrf_token'] ?? '';

// Validate CSRF token
if (!validateCSRFToken($csrfToken)) {
    logSecurityEvent('CSRF_FAILED', ['form' => 'order_submission', 'order_ref' => $orderRef]);
    sendError(403, 'Invalid security token. Please refresh the page and try again.');
}

// Validate name
if (empty($name)) {
    sendError(400, 'Name is required');
}

$name = sanitizeString($name);

if (strlen($name) < 2) {
    sendError(400, 'Name must be at least 2 characters');
}

// Validate email
$email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
if (!$email) {
    sendError(400, 'Valid email address is required');
}

// Validate plan
$plan = sanitizeString($plan);
if (!validatePlan($plan)) {
    logSecurityEvent('INVALID_PLAN', ['plan' => $plan, 'email' => $email]);
    sendError(400, 'Invalid plan selected');
}

// Validate amount
$amount = sanitizeString($amount);
if (!is_numeric($amount) || floatval($amount) <= 0) {
    sendError(400, 'Invalid amount');
}

// Validate order reference format
$orderRef = sanitizeString($orderRef);
if (!validateOrderReference($orderRef)) {
    logSecurityEvent('INVALID_ORDER_REF', ['ref' => $orderRef, 'email' => $email]);
    sendError(400, 'Invalid order reference format');
}

// Validate e-transfer reference
$etransferRef = sanitizeString($etransferRef);
if (empty($etransferRef) || strlen($etransferRef) < 3) {
    sendError(400, 'E-transfer reference is required');
}

// Ensure storage directories exist
ensureStorageDirectory();
ensureStorageDirectory('logs');

// Check for duplicate order reference
$ordersFile = __DIR__ . '/../storage/orders.txt';
if (file_exists($ordersFile)) {
    $existingOrders = file_get_contents($ordersFile);
    if (strpos($existingOrders, $orderRef) !== false) {
        logSecurityEvent('DUPLICATE_ORDER', ['ref' => $orderRef, 'email' => $email]);
        sendError(400, 'This order reference has already been submitted');
    }
}

// Log order to file
$timestamp = date('Y-m-d H:i:s');
$orderEntry = sprintf(
    "%s | %s | %s | %s | %s | pending\n",
    $timestamp,
    $email,
    $orderRef,
    $plan,
    $etransferRef
);

if (file_put_contents($ordersFile, $orderEntry, FILE_APPEND | LOCK_EX) === false) {
    error_log("Failed to write to orders file");
    logSecurityEvent('ORDER_WRITE_FAILED', ['ref' => $orderRef, 'email' => $email]);
    sendError(500, 'Failed to save order. Please contact support at ' . ADMIN_EMAIL);
}

// Log security event
logSecurityEvent('ORDER_SUBMITTED', [
    'email' => $email,
    'ref' => $orderRef,
    'plan' => $plan,
    'amount' => $amount
]);

// Send confirmation email to customer
$customerSubject = "Order Received - PrimeCast [$orderRef]";
$customerMessage = "Hello $name,

Thank you for your order with PrimeCast!

Order Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Plan: $plan
Amount: $$amount
Order Reference: $orderRef
E-transfer Reference: $etransferRef
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

We have received your order and will verify your e-transfer payment shortly.

Your login credentials will be sent to this email address within 1-4 hours after we confirm your payment.

What happens next:
1. Our team will verify your e-transfer payment
2. We'll create your account
3. You'll receive login credentials via email
4. Start enjoying premium IPTV streaming!

If you have any questions or don't receive your credentials within 4 hours, please contact us at " . ADMIN_EMAIL . "

Best regards,
The PrimeCast Team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
This is an automated confirmation.
Please save this email for your records.
";

sendEmail($email, $customerSubject, $customerMessage);

// Send alert email to admin
$adminSubject = "🔔 New PrimeCast Order - $plan";
$adminMessage = "New order received and requires payment verification:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Customer Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Name: $name
Email: $email

Order Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Plan: $plan
Amount to Verify: $$amount
Order Reference: $orderRef
E-transfer Reference: $etransferRef

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Action Required:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
1. Check your bank for e-transfer with reference: $orderRef
2. Verify amount matches: $$amount
3. Verify e-transfer reference: $etransferRef
4. Create customer account with plan: $plan
5. Send login credentials to: $email

View all orders in admin dashboard:
" . SITE_URL . "/admin/dashboard.php

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Submission Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IP Address: " . $_SERVER['REMOTE_ADDR'] . "
Timestamp: $timestamp
";

sendEmail(ADMIN_EMAIL, $adminSubject, $adminMessage);

// Return success
sendSuccess([
    'message' => 'Order submitted successfully',
    'order_reference' => $orderRef
]);
