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
            preg_match(
                '/^\[(.*?)\]\s+Email:\s+(.*?)\s+\|\s+Plan:\s+(.*?)\s+\|\s+Reference:\s+(.*?)\s+\|\s+Transaction:\s+(.*?)\s+\|\s+Amount:\s+\$(.*?)\s+\|\s+Method:\s+(.*)$/',
                $line,
                $matches
            );

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
        :root {
            --card-surface: rgba(26, 26, 26, 0.9);
            --border-soft: rgba(0, 229, 255, 0.2);
        }
        body {
            background: radial-gradient(circle at 20% 20%, rgba(0, 229, 255, 0.08), transparent 35%),
                radial-gradient(circle at 80% 0%, rgba(196, 155, 42, 0.1), transparent 30%),
                var(--dark-bg);
            color: #fff;
        }
        .admin-page-bg {
            position: fixed;
            inset: 0;
            background: linear-gradient(120deg, rgba(0, 229, 255, 0.05), rgba(196, 155, 42, 0.05));
            filter: blur(60px);
            z-index: 0;
        }
        .admin-container {
            position: relative;
            z-index: 1;
            max-width: 1200px;
            margin: 0 auto;
            padding: 40px 20px 80px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: var(--card-surface);
            border: 1px solid var(--border-soft);
            border-radius: 12px;
            padding: 25px;
            text-align: center;
        }
        .stat-value {
            font-size: 2.5rem;
            color: var(--gold);
            font-weight: 700;
            margin: 10px 0;
        }
        .stat-label {
            color: var(--text-gray);
            font-size: 1rem;
        }
        .eyebrow {
            text-transform: uppercase;
            letter-spacing: 0.08em;
            font-size: 0.85rem;
            color: var(--neon-blue);
            margin-bottom: 8px;
        }
        .welcome {
            color: var(--text-gray);
            margin-top: 6px;
        }
        .admin-header {
            background: var(--card-surface);
            padding: 30px;
            border: 1px solid var(--border-soft);
            border-radius: 16px;
            margin-bottom: 30px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            gap: 20px;
        }
        .admin-header h1 {
            color: var(--gold);
            margin: 0;
        }
        .admin-layout {
            display: flex;
            flex-direction: column;
            gap: 30px;
        }
        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .refresh-btn {
            background: var(--neon-blue);
            color: var(--dark-bg);
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
        }
        .refresh-btn:hover {
            background: var(--gold);
        }
        .table-heading {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.08);
        }
        .table-heading h2 {
            color: var(--gold);
            margin: 4px 0 0;
        }
        .table-meta {
            display: flex;
            gap: 10px;
        }
        .badge {
            display: inline-flex;
            align-items: center;
            padding: 6px 12px;
            border-radius: 20px;
            background: rgba(0, 229, 255, 0.12);
            color: var(--neon-blue);
            font-weight: 600;
            font-size: 0.9rem;
            border: 1px solid rgba(0, 229, 255, 0.25);
        }
        .payments-table {
            background: var(--card-surface);
            border-radius: 16px;
            border: 1px solid var(--border-soft);
            overflow: hidden;
        }
        .table-message {
            padding: 32px;
            text-align: center;
            color: var(--text-gray);
        }
        .table-warning {
            background: rgba(255, 165, 0, 0.08);
            color: #ffb347;
        }
        .payments-table table {
            width: 100%;
            border-collapse: collapse;
        }
        .payments-table th {
            background: rgba(196, 155, 42, 0.2);
            color: var(--gold);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
        }
        .payments-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            color: var(--text-gray);
        }
        .payments-table tr:hover {
            background: rgba(0, 229, 255, 0.03);
        }
        .mono {
            font-family: monospace;
        }
        .highlight {
            color: var(--neon-blue);
        }
        .amount {
            color: var(--gold);
            font-weight: 700;
        }
        .method {
            text-transform: uppercase;
        }
    </style>
</head>
<body>
    <div class="admin-page-bg"></div>
    <div class="admin-container">
        <header class="admin-header">
            <div>
                <p class="eyebrow">PrimeCast Control Panel</p>
                <h1>Admin Dashboard</h1>
                <p class="welcome">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
            </div>
            <div class="actions">
                <button onclick="location.reload()" class="refresh-btn" aria-label="Refresh dashboard">Refresh</button>
                <button onclick="window.location.href='?logout=1'" class="logout-btn" aria-label="Log out">Logout</button>
            </div>
        </header>

        <main class="admin-layout" role="main">
            <!-- Statistics Cards -->
            <section class="stats-grid" aria-label="Payment statistics">
                <article class="stat-card">
                    <div class="stat-label">Total Payments</div>
                    <div class="stat-value"><?php echo $totalPayments; ?></div>
                </article>
                <article class="stat-card">
                    <div class="stat-label">Total Revenue</div>
                    <div class="stat-value">$<?php echo number_format($totalRevenue, 2); ?></div>
                </article>
                <article class="stat-card">
                    <div class="stat-label">Today's Payments</div>
                    <div class="stat-value"><?php echo $todayPayments; ?></div>
                </article>
                <article class="stat-card">
                    <div class="stat-label">Today's Revenue</div>
                    <div class="stat-value">$<?php echo number_format($todayRevenue, 2); ?></div>
                </article>
            </section>

            <!-- Payments Table -->
            <section class="payments-table" aria-label="Recent payments">
                <div class="table-heading">
                    <div>
                        <p class="eyebrow">Transactions</p>
                        <h2>Recent Payments</h2>
                    </div>
                    <div class="table-meta">
                        <span class="badge">Secure Area</span>
                    </div>
                </div>

                <?php if ($logError): ?>
                    <div class="table-message table-warning">
                        <p><?php echo htmlspecialchars($logError); ?></p>
                    </div>
                <?php elseif (empty($payments)): ?>
                    <div class="table-message">
                        <p>No payments recorded yet.</p>
                    </div>
                <?php else: ?>
                    <table>
                        <thead>
                            <tr>
                                <th scope="col">Date &amp; Time</th>
                                <th scope="col">Email</th>
                                <th scope="col">Plan</th>
                                <th scope="col">Reference</th>
                                <th scope="col">Transaction ID</th>
                                <th scope="col">Amount</th>
                                <th scope="col">Method</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($payments as $payment): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($payment['timestamp']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['email']); ?></td>
                                    <td><?php echo htmlspecialchars($payment['plan']); ?></td>
                                    <td class="mono highlight">
                                        <?php echo htmlspecialchars($payment['reference']); ?>
                                    </td>
                                    <td><?php echo htmlspecialchars($payment['transaction']); ?></td>
                                    <td class="amount">$<?php echo htmlspecialchars($payment['amount']); ?></td>
                                    <td class="method"><?php echo htmlspecialchars($payment['method']); ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </section>
        </main>
    </div>
</body>
</html>
