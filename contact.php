<?php
header('Content-Type: application/json');
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: POST');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['success' => false, 'message' => 'Method not allowed']);
    exit;
}

// Get form data
$name = isset($_POST['name']) ? htmlspecialchars(trim($_POST['name'])) : '';
$email = isset($_POST['email']) ? filter_var(trim($_POST['email']), FILTER_SANITIZE_EMAIL) : '';
$subject = isset($_POST['subject']) ? htmlspecialchars(trim($_POST['subject'])) : '';
$message = isset($_POST['message']) ? htmlspecialchars(trim($_POST['message'])) : '';

// Validate inputs
if (empty($name) || empty($email) || empty($subject) || empty($message)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'All fields are required']);
    exit;
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    echo json_encode(['success' => false, 'message' => 'Invalid email address']);
    exit;
}

// Prepare email to admin
$to = "info@primecast.world";
$emailSubject = "PrimeCast Contact Form: " . $subject;
$emailMessage = "
New contact form submission from PrimeCast website:

Name: $name
Email: $email
Subject: $subject

Message:
$message

---
Sent from PrimeCast Contact Form
" . date('Y-m-d H:i:s');

$headers = "From: $name <$email>\r\n";
$headers .= "Reply-To: $email\r\n";
$headers .= "X-Mailer: PHP/" . phpversion();

// Send email
$emailSent = mail($to, $emailSubject, $emailMessage, $headers);

// Send auto-reply to customer
if ($emailSent) {
    $autoReplySubject = "Thank you for contacting PrimeCast";
    $autoReplyMessage = "
Hello $name,

Thank you for reaching out to PrimeCast!

We have received your message and will respond as soon as possible, typically within 4 hours.

Your message:
---
$message
---

If you need immediate assistance, you can also email us directly at info@primecast.world

Best regards,
The PrimeCast Team
";
    
    $autoReplyHeaders = "From: PrimeCast <info@primecast.world>\r\n";
    $autoReplyHeaders .= "Reply-To: info@primecast.world\r\n";
    $autoReplyHeaders .= "X-Mailer: PHP/" . phpversion();
    
    mail($email, $autoReplySubject, $autoReplyMessage, $autoReplyHeaders);
}

if ($emailSent) {
    echo json_encode(['success' => true, 'message' => 'Message sent successfully']);
} else {
    http_response_code(500);
    echo json_encode(['success' => false, 'message' => 'Failed to send message. Please try again.']);
}
?>