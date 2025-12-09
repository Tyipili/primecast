<?php
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Read payment log
$logFile = '../php/payment_log.txt';
$payments = [];

if (file_exists($logFile)) {
    $logContent = file_get_contents($logFile);
    $lines = explode("\n", trim($logContent));
    
    foreach ($lines as $line) {
        if (empty($line)) continue;
        
        // Parse log entry
        preg_match('/\[(.*?)\] Email: (.*?) \| Plan: (.*?) \| Reference: (.*?) \| Transaction: (.*?) \| Amount: \$(.*?) \| Method: (.*)/', $line, $matches);
        
        if (count($matches) === 8) {
            $payments[] = [
                'timestamp' => $matches[1],
                'email' => $matches[2],
                'plan' => $matches[3],
                'reference' => $matches[4],
                'transaction' => $matches[5],
                'amount' => $matches[6],
                'method' => $matches[7]
            ];
        }
    }
    
    // Reverse to show newest first
    $payments = array_reverse($payments);
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
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 40px;
        }
        .stat-card {
            background: rgba(26, 26, 26, 0.8);
            border: 1px solid rgba(0, 229, 255, 0.2);
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
    </style>
</head>
<body>
    <div class="admin-container">
        <div class="admin-header">
            <div>
                <h1>PrimeCast Admin Dashboard</h1>
                <p style="color: var(--text-gray);">Welcome, <?php echo htmlspecialchars($_SESSION['admin_username']); ?></p>
            </div>
            <div>
                <button onclick="location.reload()" class="refresh-btn">Refresh</button>
                <button onclick="window.location.href='?logout=1'" class="logout-btn">Logout</button>
            </div>
        </div>

        <!-- Statistics Cards -->
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
            
            <?php if (empty($payments)): ?>
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
                                <td style="text-transform: uppercase;">
                                    <?php echo htmlspecialchars($payment['method']); ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>
