<?php
/**
 * PrimeCast Admin Login
 * Secure admin authentication with brute force protection
 */

require_once __DIR__ . '/../php/functions.php';

// Detect secure connection
$isSecure = isSecure();

// Configure secure session cookies
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

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

// Storage locations
$credentialsFile = STORAGE_PATH . '/admin_credentials.json';

$error = '';
$timeout = isset($_GET['timeout']) ? true : false;
$credentials = null;

// Environment variables for initial setup
$envUser = getenv('ADMIN_USERNAME');
$envPass = getenv('ADMIN_PASSWORD');

// Ensure storage directory exists
if (!is_dir(STORAGE_PATH)) {
    if (!mkdir(STORAGE_PATH, DIR_PERMISSIONS, true) && !is_dir(STORAGE_PATH)) {
        $error = 'System configuration error. Please contact administrator.';
        error_log("Failed to create storage directory: " . STORAGE_PATH);
    }
}

// Load credentials from secure location or bootstrap from env vars
if (file_exists($credentialsFile) && is_readable($credentialsFile)) {
    $contents = file_get_contents($credentialsFile);
    
    if ($contents === false) {
        $error = 'System configuration error. Please contact administrator.';
        error_log("Unable to read admin credentials file: $credentialsFile");
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
            $error = 'System configuration error. Please contact administrator.';
            error_log("Admin credentials file is invalid");
        }
    }
} elseif ($envUser && $envPass && trim($envUser) !== '' && trim($envPass) !== '') {
    // Bootstrap from environment variables
    $credentials = [
        'username' => trim($envUser),
        'password' => password_hash(trim($envPass), PASSWORD_DEFAULT)
    ];
    
    if (file_put_contents($credentialsFile, json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES), LOCK_EX) === false) {
        $error = 'System configuration error. Please contact administrator.';
        error_log("Failed to write admin credentials file");
    } else {
        chmod($credentialsFile, FILE_PERMISSIONS);
        error_log("Admin credentials file created successfully");
    }
} else {
    $error = 'System not configured. Please set ADMIN_USERNAME and ADMIN_PASSWORD environment variables.';
    error_log("Admin credentials not configured");
}

// Check for brute force attempts
$attemptCheck = checkLoginAttempts($_SERVER['REMOTE_ADDR']);
if ($attemptCheck !== true) {
    $error = $attemptCheck;
}

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $credentials && $attemptCheck === true) {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Basic input validation
    if (empty($username) || empty($password)) {
        $error = 'Username and password are required';
        recordFailedLogin($_SERVER['REMOTE_ADDR'], $username);
    } elseif ($username === $credentials['username']) {
        $storedPassword = $credentials['password'];
        
        // Check if password is already hashed or plain text
        $passwordIsHash = password_get_info($storedPassword)['algo'] !== 0;
        $verified = $passwordIsHash 
            ? password_verify($password, $storedPassword) 
            : hash_equals($storedPassword, $password);
        
        if ($verified) {
            // Upgrade plain text password to hash if needed
            if (!$passwordIsHash) {
                $credentials['password'] = password_hash($storedPassword, PASSWORD_DEFAULT);
                file_put_contents(
                    $credentialsFile,
                    json_encode($credentials, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES),
                    LOCK_EX
                );
                chmod($credentialsFile, FILE_PERMISSIONS);
                error_log("Admin password upgraded to hash for user: $username");
            }
            
            // Successful login
            clearLoginAttempts($_SERVER['REMOTE_ADDR']);
            
            // Regenerate session ID to prevent fixation
            session_regenerate_id(true);
            
            // Set session variables
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            $_SESSION['last_activity'] = time();
            $_SESSION['session_token'] = generateToken(32);
            $_SESSION['login_ip'] = $_SERVER['REMOTE_ADDR'];
            $_SESSION['login_time'] = date('Y-m-d H:i:s');
            
            logSecurityEvent('ADMIN_LOGIN_SUCCESS', [
                'username' => $username,
                'ip' => $_SERVER['REMOTE_ADDR']
            ]);
            
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
            recordFailedLogin($_SERVER['REMOTE_ADDR'], $username);
        }
    } else {
        $error = 'Invalid username or password';
        recordFailedLogin($_SERVER['REMOTE_ADDR'], $username);
    }
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && !$credentials && !$error) {
    $error = 'System configuration error. Please contact administrator.';
}

// Show timeout message
if ($timeout) {
    $error = 'Your session has expired. Please log in again.';
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="robots" content="noindex, nofollow">
    <title>Admin Login - PrimeCast</title>
    <link rel="stylesheet" href="../css/style.css">
    <style>
        body {
            margin: 0;
            padding: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            background: linear-gradient(135deg, #0a0a0a 0%, #1a1a1a 100%);
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Oxygen, Ubuntu, Cantarell, sans-serif;
        }
        
        .login-container {
            width: 100%;
            max-width: 400px;
            padding: 20px;
        }
        
        .login-box {
            background: rgba(26, 26, 26, 0.95);
            border: 1px solid rgba(0, 229, 255, 0.2);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.5);
        }
        
        .login-box h2 {
            color: #C49B2A;
            margin: 0 0 10px 0;
            font-size: 1.8rem;
            text-align: center;
        }
        
        .login-subtitle {
            color: #888;
            text-align: center;
            margin-bottom: 30px;
            font-size: 0.9rem;
        }
        
        .error-message {
            background: rgba(255, 0, 0, 0.1);
            border: 1px solid rgba(255, 0, 0, 0.3);
            color: #ff6b6b;
            padding: 12px;
            border-radius: 8px;
            margin-bottom: 20px;
            font-size: 0.9rem;
        }
        
        .form-group {
            margin-bottom: 20px;
        }
        
        .form-group label {
            display: block;
            color: #00E5FF;
            margin-bottom: 8px;
            font-weight: 500;
            font-size: 0.9rem;
        }
        
        .form-group input {
            width: 100%;
            padding: 12px;
            background: rgba(10, 10, 10, 0.8);
            border: 1px solid rgba(0, 229, 255, 0.3);
            border-radius: 8px;
            color: #fff;
            font-size: 1rem;
            box-sizing: border-box;
            transition: all 0.3s ease;
        }
        
        .form-group input:focus {
            outline: none;
            border-color: #00E5FF;
            box-shadow: 0 0 0 3px rgba(0, 229, 255, 0.1);
        }
        
        .btn-primary {
            width: 100%;
            padding: 14px;
            background: linear-gradient(135deg, #00E5FF 0%, #0091EA 100%);
            color: #0a0a0a;
            border: none;
            border-radius: 8px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 229, 255, 0.4);
        }
        
        .btn-primary:active {
            transform: translateY(0);
        }
        
        .btn-primary:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        
        .security-notice {
            margin-top: 20px;
            padding: 12px;
            background: rgba(0, 229, 255, 0.05);
            border: 1px solid rgba(0, 229, 255, 0.2);
            border-radius: 8px;
            color: #888;
            font-size: 0.85rem;
            text-align: center;
        }
        
        .logo-container {
            text-align: center;
            margin-bottom: 30px;
        }
        
        .logo-container img {
            height: 50px;
            width: auto;
        }
    </style>
</head>
<body>
    <div class="login-container">
        <div class="login-box">
            <div class="logo-container">
                <img src="../images/logo.png" alt="PrimeCast Logo" onerror="this.style.display='none'">
            </div>
            
            <h2>Admin Login</h2>
            <p class="login-subtitle">Secure Access Portal</p>
            
            <?php if ($error): ?>
                <div class="error-message" role="alert">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <form method="POST" action="" autocomplete="off">
                <div class="form-group">
                    <label for="username">Username</label>
                    <input 
                        type="text" 
                        id="username" 
                        name="username" 
                        required 
                        autofocus
                        autocomplete="username"
                        <?php echo $attemptCheck !== true ? 'disabled' : ''; ?>
                    >
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        autocomplete="current-password"
                        <?php echo $attemptCheck !== true ? 'disabled' : ''; ?>
                    >
                </div>
                
                <button 
                    type="submit" 
                    class="btn-primary"
                    <?php echo $attemptCheck !== true ? 'disabled' : ''; ?>
                >
                    <?php echo $attemptCheck !== true ? 'Locked' : 'Login'; ?>
                </button>
            </form>
            
            <div class="security-notice">
                🔒 This is a secure area. All access attempts are logged.
            </div>
        </div>
    </div>
</body>
</html>
