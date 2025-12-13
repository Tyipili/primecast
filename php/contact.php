<?php
require_once __DIR__ . '/functions.php';

// Security headers
header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Access-Control-Allow-Origin: *');

// Start session safely
startSession();

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Check POST size (1MB limit)
if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > 1048576) {
    http_response_code(413);
    echo json_encode(['success' => false, 'message' => 'Payload too large']);
    exit;
}

// Rate limiting (5 requests per 5 minutes per IP)
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
            echo json_encode(['success' => false, 'message' => 'Too many requests. Please try again later.']);
            exit;
        }
        
        $attempts[] = $now;
        file_put_contents($rateFile, json_encode(['attempts' => $attempts]), LOCK_EX);
    }
} else {
    file_put_contents($rateFile, json_encode(['attempts' => [$now]]), LOCK_EX);
}

// Get form data
$name = isset($_POST['name']) ? trim($_POST['name']) : '';
$email = isset($_POST['email']) ? trim($_POST['email']) : '';
$subject = isset($_POST['subject']) ? trim($_POST['subject']) : '';
$message = isset($_POST['message']) ? trim($_POST['message']) : '';
$csrfToken = isset($_POST['csrf_token']) ? $_POST['csrf_token'] : '';

// Validate CSRF token
if (!isset($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrfToken)) {
    http_response_code(403);
    echo json_encode(['success' => false, 'message' => 'Invalid security token. Please refresh the page and try again.']);
    
    // Log security event
    $securityLog = __DIR__ . '/../storage/logs/security.log';
    if (!is_dir(dirname($securityLog))) {
        mkdir(dirname($securityLog), 0750, true);
    }
    $entry = sprintf("[%s] CSRF_FAILED | IP: %s | Contact Form\n", date('Y-m-d H:i:s'), $ip);
    file_put_contents($securityLog, $entry, FILE_APPEND | LOCK_EX);
    
    exit;
}

// Validate inputs
if (empty($name)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name is required']);
    exit;
}

if (strlen($name) > 100) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Name is too long (maximum 100 characters)']);
    exit;
}

if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Valid email address is required']);
    exit;
}

if (empty($subject)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Subject is required']);
    exit;
}

if (strlen($subject) > 200) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Subject is too long (maximum 200 characters)']);
    exit;
}

if (empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is required']);
    exit;
}

if (strlen($message) > 5000) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Message is too long (maximum 5000 characters)']);
    exit;
}

// Sanitize inputs (prevent header injection)
$name = htmlspecialchars(strip_tags($name), ENT_QUOTES, 'UTF-8');
$name = str_replace(["\r", "\n", "%0a", "%0d"], '', $name);
$email = filter_var($email, FILTER_SANITIZE_EMAIL);
$email = str_replace(["\r", "\n", "%0a", "%0d"], '', $email);
$subject = htmlspecialchars(strip_tags($subject), ENT_QUOTES, 'UTF-8');
$subject = str_replace(["\r", "\n", "%0a", "%0d"], '', $subject);
$message = htmlspecialchars(strip_tags($message), ENT_QUOTES, 'UTF-8');

// Basic spam detection
$spamKeywords = ['viagra', 'cialis', 'casino', 'lottery', 'bitcoin', 'forex'];
$messageContent = strtolower($message . ' ' . $subject);

foreach ($spamKeywords as $keyword) {
    if (strpos($messageContent, $keyword) !== false) {
        // Log spam attempt
        $securityLog = __DIR__ . '/../storage/logs/security.log';
        $entry = sprintf("[%s] SPAM_DETECTED | IP: %s | Keyword: %s\n", date('Y-m-d H:i:s'), $ip, $keyword);
        file_put_contents($securityLog, $entry, FILE_APPEND | LOCK_EX);
        
        // Return success anyway (don't tell spammer)
        echo json_encode(['success' => true, 'message' => 'Message received. We will respond soon.']);
        exit;
    }
}

// Prepare email to admin
$emailSubject = "PrimeCast Contact: " . $subject;
$emailMessage = "New contact form submission from PrimeCast website:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Contact Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Name: $name
Email: $email
Subject: $subject

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Message:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$message

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Submission Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IP Address: " . $ip . "
User Agent: " . substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100) . "
Date/Time: " . date('Y-m-d H:i:s T') . "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Please respond to: $email
";

$headers = "MIME-Version: 1.0\r\n";
$headers .= "Content-type: text/plain; charset=UTF-8\r\n";
$headers .= "From: PrimeCast <info@primecast.world>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send email to admin
$emailSent = mail('info@primecast.world', $emailSubject, $emailMessage, $headers);

if (!$emailSent) {
    error_log("Failed to send contact form to admin. Name: $name, Email: $email");
    
    // Log failed email
    $securityLog = __DIR__ . '/../storage/logs/security.log';
    $entry = sprintf("[%s] EMAIL_FAILED | IP: %s | Contact Form\n", date('Y-m-d H:i:s'), $ip);
    file_put_contents($securityLog, $entry, FILE_APPEND | LOCK_EX);
    
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again or email us directly at info@primecast.world']);
    exit;
}

// Send auto-reply to customer
$autoReplySubject = "Thank you for contacting PrimeCast";
$autoReplyMessage = "Hello $name,

Thank you for reaching out to PrimeCast!

We have received your message and will respond as soon as possible, typically within 4 hours during business hours (Monday-Friday, 9 AM - 6 PM EST).

Your Message:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Subject: $subject

$message
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If you need immediate assistance, you can also reach us directly:
📧 Email: info@primecast.world
🌐 Website: https://yourdomain.com

We appreciate your interest in PrimeCast and look forward to assisting you!

Best regards,
The PrimeCast Team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
This is an automated response. Please do not reply to this email.
If you need to add information to your inquiry, please submit a new contact form.
";

$autoReplyHeaders = "MIME-Version: 1.0\r\n";
$autoReplyHeaders .= "Content-type: text/plain; charset=UTF-8\r\n";
$autoReplyHeaders .= "From: PrimeCast <info@primecast.world>\r\n";
$autoReplyHeaders .= "Reply-To: info@primecast.world\r\n";
$autoReplyHeaders .= "X-Mailer: PHP/" . phpversion();

mail($email, $autoReplySubject, $autoReplyMessage, $autoReplyHeaders);

// Log successful contact
$securityLog = __DIR__ . '/../storage/logs/security.log';
$entry = sprintf("[%s] CONTACT_FORM | IP: %s | Email: %s\n", date('Y-m-d H:i:s'), $ip, $email);
file_put_contents($securityLog, $entry, FILE_APPEND | LOCK_EX);

// Return success
echo json_encode(['success' => true, 'message' => 'Thank you for your message! We will respond within 4 hours.']);

