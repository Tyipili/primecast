<?php
// Harden session cookies on the dashboard as well
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => $isSecure
]);

session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, [
            'path' => $params['path'] ?? '/',
            'domain' => $params['domain'] ?? '',
            'secure' => $params['secure'],
            'httponly' => $params['httponly'],
            'samesite' => $params['samesite'] ?? 'Strict'
        ]);
    }

    session_destroy();
    header('Location: login.php');
    exit;
}

// Read payment log
$logFile = dirname(__DIR__) . '/storage/payment_log.txt';
$payments = [];
$logError = '';

if (!file_exists($logFile)) {
    $logError = 'Payment log not found. Purchases will appear here once recorded.';
} elseif (!is_readable($logFile)) {
    $logError = 'Payment log exists but is not readable. Check file permissions.';
} elseif (filesize($logFile) > 0) {
    $logContent = file_get_contents($logFile);

    if ($logContent === false) {
        $logError = 'Unable to read payment log contents.';
    } else {
        $lines = explode("\n", trim($logContent));

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            // Parse log entry with anchored pattern to reduce malformed matches
            preg_match('/^\[(.*?)\]\s+Email:\s+(.*?)\s+\|\s+Plan:\s+(.*?)\s+\|\s+Reference:\s+(.*?)\s+\|\s+Transaction:\s+(.*?)\s+\|\s+Amount:\s+\$(.*?)\s+\|\s+Method:\s+(.*)$/', $line, $matches);

            if (count($matches) === 8 && is_numeric($matches[6])) {
                $payments[] = [
                    'timestamp' => $matches[1],
                    'email' => $matches[2],
                    'plan' => $matches[3],
                    'reference' => $matches[4],
                    'transaction' => $matches[5],
                    'amount' => (float) $matches[6],
                    'method' => $matches[7]
                ];
            }
        }

        // Reverse to show newest first
        $payments = array_reverse($payments);
    }
}

// Calculate statistics
$totalPayments = count($payments);
$totalRevenue = array_sum(array_column($payments, 'amount'));
$todayPayments = 0;
$todayRevenue = 0;
$today = date('Y-m-d');

foreach ($payments as $payment) {
    if (strpos($payment['timestamp'], $today) === 0) {
        $todayPayments++;
        $todayRevenue += floatval($payment['amount']);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - PrimeCast</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        .stats-grid {
            display: grid;
@@ -122,55 +160,59 @@ foreach ($payments as $payment) {
        <div class="stats-grid">
            <div class="stat-card">
                <div class="stat-label">Total Payments</div>
                <div class="stat-value"><?php echo $totalPayments; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Total Revenue</div>
                <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today's Payments</div>
                <div class="stat-value"><?php echo $todayPayments; ?></div>
            </div>
            <div class="stat-card">
                <div class="stat-label">Today's Revenue</div>
                <div class="stat-value">$<?php echo number_format($todayRevenue, 2); ?></div>
            </div>
        </div>

        <!-- Payments Table -->
        <div class="payments-table">
            <div style="padding: 20px; background: rgba(196, 155, 42, 0.1); border-bottom: 1px solid rgba(255, 255, 255, 0.1);">
                <h2 style="color: var(--gold); margin: 0;">Recent Payments</h2>
            </div>
            
            <?php if ($logError): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                    <p><?php echo htmlspecialchars($logError); ?></p>
                </div>
            <?php elseif (empty($payments)): ?>
                <div style="padding: 40px; text-align: center; color: var(--text-gray);">
                    <p>No payments recorded yet.</p>
                </div>
            <?php else: ?>
                <table>
                    <thead>
                        <tr>
                            <th>Date & Time</th>
                            <th>Email</th>
                            <th>Plan</th>
                            <th>Reference</th>
                            <th>Transaction ID</th>
                            <th>Amount</th>
                            <th>Method</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($payments as $payment): ?>
                            <tr>
                                <td><?php echo htmlspecialchars($payment['timestamp']); ?></td>
                                <td><?php echo htmlspecialchars($payment['email']); ?></td>
                                <td><?php echo htmlspecialchars($payment['plan']); ?></td>
                                <td style="font-family: monospace; color: var(--neon-blue);">
                                    <?php echo htmlspecialchars($payment['reference']); ?>
                                </td>
                                <td><?php echo htmlspecialchars($payment['transaction']); ?></td>
                                <td style="color: var(--gold); font-weight: 600;">
                                    $<?php echo htmlspecialchars($payment['amount']); ?>
                                </td>
