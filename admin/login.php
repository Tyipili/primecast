<?php
// Helper to detect secure requests across direct and proxied deployments
$isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

// Harden session cookies with compatibility for PHP < 7.3
$cookieOptions = [
    'lifetime' => 0,
    'path' => '/',
    'httponly' => true,
    'samesite' => 'Strict',
    'secure' => $isSecure
];

if (PHP_VERSION_ID >= 70300) {
    session_set_cookie_params($cookieOptions);
} else {
    $path = $cookieOptions['path'] . '; samesite=' . $cookieOptions['samesite'];
    session_set_cookie_params(
        $cookieOptions['lifetime'],
        $path,
        '',
        $cookieOptions['secure'],
        $cookieOptions['httponly']
    );
}
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Storage locations
$storageDir = dirname(__DIR__) . '/storage';
$credentialsFile = $storageDir . '/admin_credentials.json';

$error = '';
$credentials = null;
$envUser = getenv('ADMIN_USERNAME');
$envPass = getenv('ADMIN_PASSWORD');

// Ensure storage directory exists
if (!is_dir($storageDir)) {
    if (!mkdir($storageDir, 0750, true) && !is_dir($storageDir)) {
        $error = 'Unable to create storage directory.';
    }
}

// Load credentials from secure location or bootstrap from env vars
if (is_readable($credentialsFile)) {
    $contents = file_get_contents($credentialsFile);

    if ($contents === false) {
        $error = 'Unable to read admin credentials file.';
    } else {
        $decoded = json_decode($contents, true);

        if (
            is_array($decoded) &&
            isset($decoded['username'], $decoded['password']) &&
            trim($decoded['username']) !== '' &&
            trim($decoded['password']) !== '' &&
            json_last_error() === JSON_ERROR_NONE
        ) {
            $credentials = [
                'username' => trim($decoded['username']),
                'password' => $decoded['password']
            ];
        } else {
            $error = 'Admin credentials file is invalid. Please recreate it with ADMIN_USERNAME and ADMIN_PASSWORD.';
        }
    }
} elseif ($envUser && $envPass && trim($envUser) !== '' && trim($envPass) !== '') {
    $credentials = [
        'username' => trim($envUser),
        'password' => password_hash(trim($envPass), PASSWORD_DEFAULT)
    ];

    if (file_put_contents($credentialsFile, json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        $error = 'Failed to write admin credentials file. Check directory permissions.';
    } else {
        chmod($credentialsFile, 0640);
    }
} else {
    $error = 'Admin credentials are not configured. Set ADMIN_USERNAME and ADMIN_PASSWORD in the environment.';
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $credentials) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';

    if ($credentials && $username === $credentials['username']) {
        $storedPassword = $credentials['password'];

        $passwordIsHash = password_get_info($storedPassword)['algo'] !== 0;
        $verified = $passwordIsHash ? password_verify($password, $storedPassword) : hash_equals($storedPassword, $password);

        if ($verified) {
            if (!$passwordIsHash) {
                $credentials['password'] = password_hash($storedPassword, PASSWORD_DEFAULT);
                file_put_contents(
                    $credentialsFile,
                    json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    LOCK_EX
                );
                chmod($credentialsFile, 0640);
            }

            session_regenerate_id(true);
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        $error = 'Invalid username or password';
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$credentials && !$error) {
    $error = 'Admin credentials are not available. Please contact the server administrator.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - PrimeCast</title>
    <link rel="stylesheet" href="../css/style.css">
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div style="text-align: center; margin-bottom: 30px;">
				<img src="/images/logo.png" alt="PrimeCast Logo" style="height: 45px; width: auto;">
            </div>
            <h2>Admin Login</h2>
            
            <?php if ($error): ?>
                <div class="error-message"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            
            <form method="POST" action="">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input type="text" id="username" name="username" required autofocus>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input type="password" id="password" name="password" required>
                </div>
                
                <button type="submit" class="btn btn-primary" style="width: 100%;">Login</button>
            </form>
            
            <p style="text-align: center; margin-top: 20px; font-size: 14px; color: var(--text-gray);">
                Configure admin credentials via environment variables.<br>
                Contact your server administrator if you need access.
            </p>
        </div>
    </div>
</body>
</html>
