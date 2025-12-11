<?php
/**
 * PrimeCast Admin Dashboard
 * Secure admin panel for viewing payments and statistics
 */

require_once __DIR__ . '/../php/functions.php';

// Configure secure session cookies
$isSecure = isSecure();

$cookieOptions = [
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $isSecure,
    'httponly' => true,
    'samesite' => 'Strict'
];

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieOptions);
} else {
    $path = $cookieOptions['path'] . '; samesite=' . $cookieOptions['samesite'];
    session_set_cookie_params(
        $cookieOptions['lifetime'],
        $path,
        $cookieOptions['domain'],
        $cookieOptions['secure'],
        $cookieOptions['httponly']
    );
}

session_start();

// Require admin authentication (includes timeout check)
requireAdmin();

// Handle logout
if (isset($_GET['logout'])) {
    logSecurityEvent('ADMIN_LOGOUT', [
        'username' => $_SESSION['admin_username'] ?? 'unknown',
        'session_duration' => isset($_SESSION['login_time']) ? (time() - strtotime($_SESSION['login_time'])) : 0
    ]);
    
    $_SESSION = [];
    
    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();
        
        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $params['secure'] ?? $isSecure,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Strict'
            ]);
        } else {
            $path = ($params['path'] ?? '/') . '; samesite=' . ($params['samesite'] ?? 'Strict');
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $path,
                $params['domain'] ?? '',
                $params['secure'] ?? $isSecure,
                $params['httponly'] ?? true
            );
        }
    }
    
    session_destroy();
    header('Location: login.php');
    exit;
}

// Read payment log
$logFile = STORAGE_PATH . '/payment_log.txt';
$payments = [];
$logError = '';

if (!file_exists($logFile)) {
    $logError = 'No payment log found. Payments will appear here once customers make purchases.';
} elseif (!is_readable($logFile)) {
    $logError = 'Payment log exists but cannot be read. Check file permissions.';
    error_log("Payment log not readable: $logFile");
} elseif (filesize($logFile) > 0) {
    $logContent = file_get_contents($logFile);
    
    if ($logContent === false) {
        $logError = 'Unable to read payment log contents.';
        error_log("Failed to read payment log: $logFile");
    } else {
        $lines = explode("\n", trim($logContent));
        
        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }
            
            // Parse log entry
            // Format: [timestamp] Email: xxx | Plan: xxx | Reference: xxx | Transaction: xxx | Amount: $xxx | Method: xxx | Status: xxx
            preg_match(
                '/^\[(.*?)\]\s+Email:\s+(.*?)\s+\|\s+Plan:\s+(.*?)\s+\|\s+Reference:\s+(.*?)\s+\|\s+Transaction:\s+(.*?)\s+\|\s+Amount:\s+\$(.*?)\s+\|\s+Method:\s+(.*?)(?:\s+\|\s+Status:\s+(.*))?$/',
                $line,
                $matches
            );
            
            if (count($matches) >= 8) {
                $payments[] = [
                    'timestamp' => $matches[1],
                    'email' => $matches[2],
                    'plan' => $matches[3],
                    'reference' => $matches[4],
                    'transaction' => $matches[5],
                    'amount' => (float) $matches[6],
                    'method' => $matches[7],
                    'status' => $matches[8] ?? 'completed'
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

// Plan breakdown
$planCounts = ['basic' => 0, 'standard' => 0, 'premium' => 0];
foreach ($payments as $payment) {
    $plan = strtolower($payment['plan']);
    if (isset($planCounts[$plan])) {
        $planCounts[$plan]++;
    }
}

// Session info for display
$sessionDuration = isset($_SESSION['last_activity']) 
    ? (time() - $_SESSION['last_activity']) 
    : 0;
$remainingTime = SESSION_TIMEOUT - $sessionDuration;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Dashboard - PrimeCast</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        :root {
            --card-surface: rgba(26, 26, 26, 0.9);
            --border-soft: rgba(0, 229, 255, 0.2);
            --gold: #C49B2A;
            --neon-blue: #00E5FF;
            --dark-bg: #0a0a0a;
            --text-gray: #b0b0b0;
        }
        
        body {
            background: radial-gradient(circle at 20% 20%, rgba(0, 229, 255, 0.08), transparent 35%),
                radial-gradient(circle at 80% 0%, rgba(196, 155, 42, 0.1), transparent 30%),
                var(--dark-bg);
            color: #fff;
            margin: 0;
            padding: 0;
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
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
            max-width: 1400px;
            margin: 0 auto;
            padding: 40px 20px 80px;
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
            flex-wrap: wrap;
        }
        
        .admin-header h1 {
            color: var(--gold);
            margin: 0;
            font-size: 2rem;
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
            font-size: 0.9rem;
        }
        
        .actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        
        .btn {
            padding: 10px 20px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s ease;
            font-size: 0.9rem;
        }
        
        .refresh-btn {
            background: var(--neon-blue);
            color: var(--dark-bg);
        }
        
        .refresh-btn:hover {
            background: var(--gold);
        }
        
        .logout-btn {
            background: rgba(255, 0, 0, 0.2);
            color: #ff6b6b;
            border: 1px solid rgba(255, 0, 0, 0.3);
        }
        
        .logout-btn:hover {
            background: rgba(255, 0, 0, 0.3);
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
            transition: transform 0.3s ease;
        }
        
        .stat-card:hover {
            transform: translateY(-4px);
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
        
        .payments-table {
            background: var(--card-surface);
            border-radius: 16px;
            border: 1px solid var(--border-soft);
            overflow: hidden;
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
        
        .table-message {
            padding: 40px;
            text-align: center;
            color: var(--text-gray);
        }
        
        .table-warning {
            background: rgba(255, 165, 0, 0.08);
            color: #ffb347;
        }
        
        table {
            width: 100%;
            border-collapse: collapse;
        }
        
        th {
            background: rgba(196, 155, 42, 0.2);
            color: var(--gold);
            padding: 14px 16px;
            text-align: left;
            font-weight: 600;
            font-size: 0.9rem;
        }
        
        td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.06);
            color: var(--text-gray);
            font-size: 0.9rem;
        }
        
        tr:hover {
            background: rgba(0, 229, 255, 0.03);
        }
        
        .mono {
            font-family: 'Courier New', monospace;
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
        
        .status-badge {
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 0.8rem;
            font-weight: 600;
        }
        
        .status-verified {
            background: rgba(0, 255, 0, 0.2);
            color: #00ff00;
        }
        
        .session-timer {
            font-size: 0.85rem;
            color: var(--text-gray);
            margin-left: 10px;
        }
        
        @media (max-width: 768px) {
            .admin-header {
                flex-direction: column;
                align-items: flex-start;
            }
            
            table {
                font-size: 0.8rem;
            }
            
            th, td {
                padding: 10px 8px;
            }
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
                <p class="welcome">
                    Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?>
                    <span class="session-timer" id="session-timer"></span>
                </p>
            </div>
            <div class="actions">
                <button onclick="location.reload()" class="btn refresh-btn" aria-label="Refresh dashboard">
                    🔄 Refresh
                </button>
                <button onclick="if(confirm('Are you sure you want to logout?')) window.location.href='?logout=1'" class="btn logout-btn" aria-label="Log out">
                    🚪 Logout
                </button>
            </div>
        </header>

        <main role="main">
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
                    <div>
                        <span class="badge">🔒 Secure Area</span>
                    </div>
                </div>

                <?php if ($logError): ?>
                    <div class="table-message table-warning">
                        <p><?php echo htmlspecialchars($logError); ?></p>
                    </div>
                <?php elseif (empty($payments)): ?>
                    <div class="table-message">
                        <p>No payments recorded yet. New orders will appear here.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
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
                                    <th scope="col">Status</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($payments as $payment): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($payment['timestamp']); ?></td>
                                        <td><?php echo htmlspecialchars($payment['email']); ?></td>
                                        <td><?php echo htmlspecialchars(ucfirst($payment['plan'])); ?></td>
                                        <td class="mono highlight">
                                            <?php echo htmlspecialchars($payment['reference']); ?>
                                        </td>
                                        <td class="mono"><?php echo htmlspecialchars($payment['transaction']); ?></td>
                                        <td class="amount">$<?php echo number_format($payment['amount'], 2); ?></td>
                                        <td class="method"><?php echo htmlspecialchars($payment['method']); ?></td>
                                        <td>
                                            <span class="status-badge status-verified">
                                                <?php echo htmlspecialchars(ucfirst($payment['status'])); ?>
                                            </span>
                                        </td>
                                    </tr>
                                <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                <?php endif; ?>
            </section>
        </main>
    </div>

    <script>
        // Session timeout timer
        const sessionTimeout = <?php echo SESSION_TIMEOUT; ?>;
        const startTime = Date.now();
        
        function updateTimer() {
            const elapsed = Math.floor((Date.now() - startTime) / 1000);
            const remaining = sessionTimeout - elapsed;
            
            if (remaining <= 0) {
                window.location.href = 'login.php?timeout=1';
                return;
            }
            
            const minutes = Math.floor(remaining / 60);
            const seconds = remaining % 60;
            
            const timerElement = document.getElementById('session-timer');
            if (timerElement) {
                timerElement.textContent = `(Session expires in ${minutes}:${seconds.toString().padStart(2, '0')})`;
                
                if (remaining < 300) { // 5 minutes
                    timerElement.style.color = '#ff6b6b';
                }
            }
        }
        
        setInterval(updateTimer, 1000);
        updateTimer();
    </script>
</body>
</html>
