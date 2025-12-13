<?php
require_once __DIR__ . '/functions.php';

echo "<h2>Testing SMTP Email</h2>";

$testResult = sendEmail(
    'your-test-email@gmail.com',
    'Test Email from PrimeCast',
    'This is a test email to verify SMTP is working correctly.',
    'info@primecast.world'
);

if ($testResult) {
    echo "✅ Email sent successfully!";
} else {
    echo "❌ Email failed. Check error logs.";
}
?>
