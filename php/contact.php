<?php
/**
 * PrimeCast Contact Form Handler
 * Secure contact form with CSRF protection and rate limiting
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

// Get form data
$name = $_POST['name'] ?? '';
$email = $_POST['email'] ?? '';
$subject = $_POST['subject'] ?? '';
$message = $_POST['message'] ?? '';
$csrfToken = $_POST['csrf_token'] ?? '';

// Validate CSRF token
if (!validateCSRFToken($csrfToken)) {
    logSecurityEvent('CSRF_FAILED', ['form' => 'contact']);
    sendError(403, 'Invalid security token. Please refresh the page and try again.');
}

// Validate inputs
if (empty($name)) {
    sendError(400, 'Name is required');
}

if (strlen($name) < 2) {
    sendError(400, 'Name must be at least 2 characters');
}

if (strlen($name) > 100) {
    sendError(400, 'Name is too long (maximum 100 characters)');
}

if (empty($email)) {
    sendError(400, 'Email address is required');
}

$email = filter_var(trim($email), FILTER_VALIDATE_EMAIL);
if (!$email) {
    sendError(400, 'Valid email address is required');
}

if (empty($subject)) {
    sendError(400, 'Subject is required');
}

if (strlen($subject) < 3) {
    sendError(400, 'Subject must be at least 3 characters');
}

if (strlen($subject) > 200) {
    sendError(400, 'Subject is too long (maximum 200 characters)');
}

if (empty($message)) {
    sendError(400, 'Message is required');
}

if (strlen($message) < 10) {
    sendError(400, 'Message must be at least 10 characters');
}

if (strlen($message) > 5000) {
    sendError(400, 'Message is too long (maximum 5000 characters)');
}

// Sanitize inputs
$name = sanitizeString($name);
$subject = sanitizeString($subject);
$message = sanitizeString($message);

// Basic spam detection
$spamKeywords = ['viagra', 'cialis', 'casino', 'lottery', 'bitcoin', 'forex', 'crypto investment'];
$messageContent = strtolower($message . ' ' . $subject);

foreach ($spamKeywords as $keyword) {
    if (strpos($messageContent, $keyword) !== false) {
        logSecurityEvent('SPAM_DETECTED', ['keyword' => $keyword, 'email' => $email]);
        // Return success anyway (don't tell spammer)
        sendSuccess(['message' => 'Thank you for your message! We will respond within 4 hours.']);
    }
}

// Prepare email to admin
$emailSubject = "PrimeCast Contact: " . $subject;
$emailMessage = "New contact form submission from PrimeCast website:

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Contact Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Name: $name
Email: $email
Subject: $subject

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Message:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$message

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Submission Details:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
IP Address: " . $_SERVER['REMOTE_ADDR'] . "
User Agent: " . substr($_SERVER['HTTP_USER_AGENT'] ?? 'Unknown', 0, 100) . "
Date/Time: " . date('Y-m-d H:i:s T') . "
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

Please respond to: $email
";

// Send email to admin
$emailSent = sendEmail(ADMIN_EMAIL, $emailSubject, $emailMessage, $email);

if (!$emailSent) {
    sendError(500, 'Failed to send message. Please try again or email us directly at ' . ADMIN_EMAIL);
}

// Send auto-reply to customer
$autoReplySubject = "Thank you for contacting PrimeCast";
$autoReplyMessage = "Hello $name,

Thank you for reaching out to PrimeCast!

We have received your message and will respond as soon as possible, typically within 4 hours during business hours (Monday-Friday, 9 AM - 6 PM EST).

Your Message:
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
Subject: $subject

$message
━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━

If you need immediate assistance, you can also reach us directly:
📧 Email: " . ADMIN_EMAIL . "
🌐 Website: " . SITE_URL . "

We appreciate your interest in PrimeCast and look forward to assisting you!

Best regards,
The PrimeCast Team

━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
This is an automated response. Please do not reply to this email.
If you need to add information to your inquiry, please submit a new contact form.
";

sendEmail($email, $autoReplySubject, $autoReplyMessage);

// Log successful contact
logSecurityEvent('CONTACT_FORM', ['email' => $email]);

// Return success
sendSuccess(['message' => 'Thank you for your message! We will respond within 4 hours.']);
