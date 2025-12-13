<?php
/**
 * PrimeCast Admin Dashboard
 * Simple order viewing interface
 */

// Simple session management
session_start();

// Check if user is logged in
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// Session timeout (30 minutes)
if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity']) > 1800) {
    session_destroy();
    header('Location: login.php?timeout=1');
    exit;
}
$_SESSION['last_activity'] = time();

// Handle logout
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: login.php');
    exit;
}

// Read orders file
$ordersFile = dirname(__DIR__) . '/storage/orders.txt';
$orders = [];
$error = '';

// Check if orders file exists
if (!file_exists($ordersFile)) {
    // This is normal - file is created when first order is submitted
    $error = 'No orders yet. Orders will appear here once customers submit their information.';
} elseif (!is_readable($ordersFile)) {
    $error = 'Orders file exists but cannot be read. Check file permissions.';
} elseif (filesize($ordersFile) > 0) {
    $content = @file_get_contents($ordersFile);
    if ($content === false) {
        $error = 'Unable to read orders file.';
    } else {
        $lines = explode("\n", trim($content));
        foreach ($lines as $line) {
            if (empty($line)) continue;
            
            // Parse: timestamp | email | order_ref | plan | etransfer_ref
            $parts = array_map('trim', explode('|', $line));
            if (count($parts) === 5) {
                $orders[] = [
                    'timestamp' => $parts[0],
                    'email' => $parts[1],
                    'order_ref' => $parts[2],
                    'plan' => $parts[3],
                    'etransfer_ref' => $parts[4]
                ];
            }
        }
        // Reverse to show newest first
        $orders = array_reverse($orders);
    }
}

// Calculate statistics
$totalOrders = count($orders);
$todayOrders = 0;
$today = date('Y-m-d');

foreach ($orders as $order) {
    if (strpos($order['timestamp'], $today) === 0) {
        $todayOrders++;
    }
}
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
        
        .admin-container {
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
        
        .orders-table {
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
        
        .table-info {
            background: rgba(0, 229, 255, 0.05);
            border: 1px solid rgba(0, 229, 255, 0.2);
            padding: 30px;
            margin: 20px;
            border-radius: 8px;
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
    <div class="admin-container">
        <header class="admin-header">
            <div>
                <p class="eyebrow">PrimeCast Control Panel</p>
                <h1>Admin Dashboard</h1>
                <p class="welcome">
                    Welcome, <?php echo htmlspecialchars($_SESSION['admin_username'] ?? 'Admin'); ?>
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
            <section class="stats-grid" aria-label="Order statistics">
                <article class="stat-card">
                    <div class="stat-label">Total Orders</div>
                    <div class="stat-value"><?php echo $totalOrders; ?></div>
                </article>
                <article class="stat-card">
                    <div class="stat-label">Today's Orders</div>
                    <div class="stat-value"><?php echo $todayOrders; ?></div>
                </article>
            </section>

            <!-- Orders Table -->
            <section class="orders-table" aria-label="Recent orders">
                <div class="table-heading">
                    <div>
                        <p class="eyebrow">Order Management</p>
                        <h2>Recent Orders</h2>
                    </div>
                    <div>
                        <span class="badge">🔒 Secure Area</span>
                    </div>
                </div>

                <?php if ($error): ?>
                    <div class="table-info">
                        <p style="margin: 0;"><?php echo htmlspecialchars($error); ?></p>
                        <p style="margin-top: 15px; font-size: 0.9rem;">
                            When customers complete the checkout form, their orders will appear here automatically.
                        </p>
                    </div>
                <?php elseif (empty($orders)): ?>
                    <div class="table-message">
                        <p>No orders yet. New orders will appear here.</p>
                    </div>
                <?php else: ?>
                    <div style="overflow-x: auto;">
                        <table>
                            <thead>
                                <tr>
                                    <th scope="col">Date &amp; Time</th>
                                    <th scope="col">Email</th>
                                    <th scope="col">Order Reference</th>
                                    <th scope="col">Plan</th>
                                    <th scope="col">E-transfer Reference</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php foreach ($orders as $order): ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars($order['timestamp']); ?></td>
                                        <td><?php echo htmlspecialchars($order['email']); ?></td>
                                        <td class="mono highlight">
                                            <?php echo htmlspecialchars($order['order_ref']); ?>
                                        </td>
                                        <td><?php echo htmlspecialchars($order['plan']); ?></td>
                                        <td class="mono">
                                            <?php echo htmlspecialchars($order['etransfer_ref']); ?>
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
</body>
</html>
