<?php
/**
 * PrimeCast Admin Login
 * Simple admin authentication with security features
 */

session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$storageDir = dirname(__DIR__) . '/storage';
$credentialsFile = $storageDir . '/admin_credentials.json';
$error = '';
$timeout = isset($_GET['timeout']) ? true : false;

// Ensure storage directory exists
if (!is_dir($storageDir)) {
    mkdir($storageDir, 0750, true);
}

// Load or create credentials
$credentials = null;
$envUser = getenv('ADMIN_USERNAME');
$envPass = getenv('ADMIN_PASSWORD');

if (file_exists($credentialsFile)) {
    $contents = file_get_contents($credentialsFile);
    if ($contents) {
        $decoded = json_decode($contents, true);
        if (is_array($decoded) && isset($decoded['username'], $decoded['password'])) {
            $credentials = $decoded;
        }
    }
} elseif ($envUser && $envPass) {
    $credentials = [
        'username' => trim($envUser),
        'password' => password_hash(trim($envPass), PASSWORD_DEFAULT)
    ];
    file_put_contents($credentialsFile, json_encode($credentials, JSON_PRETTY_PRINT), LOCK_EX);
    chmod($credentialsFile, 0640);
}

if (!$credentials) {
    $error = 'Admin credentials not configured. Set ADMIN_USERNAME and ADMIN_PASSWORD environment variables.';
}

// Brute force protection
function checkLoginAttempts($ip) {
    $attemptsFile = dirname(__DIR__) . '/storage/login_attempts_' . md5($ip) . '.json';
    $maxAttempts = 5;
    $lockoutTime = 900;
    
    if (!file_exists($attemptsFile)) return true;
    
    $data = json_decode(file_get_contents($attemptsFile), true);
    if (!$data) return true;
    
    $attempts = $data['attempts'] ?? 0;
    $lastAttempt = $data['timestamp'] ?? 0;
    $now = time();
    
    if ($now - $lastAttempt > $lockoutTime) {
        unlink($attemptsFile);
        return true;
    }
    
    if ($attempts >= $maxAttempts) {
        $remaining = ceil(($lockoutTime - ($now - $lastAttempt)) / 60);
        return "Too many failed attempts. Try again in $remaining minutes.";
    }
    
    return true;
}

function recordFailedLogin($ip) {
    $attemptsFile = dirname(__DIR__) . '/storage/login_attempts_' . md5($ip) . '.json';
    $data = ['attempts' => 1, 'timestamp' => time()];
    
    if (file_exists($attemptsFile)) {
        $existing = json_decode(file_get_contents($attemptsFile), true);
        if ($existing) {
            $data['attempts'] = ($existing['attempts'] ?? 0) + 1;
        }
    }
    
    file_put_contents($attemptsFile, json_encode($data), LOCK_EX);
    
    // Log security event
    $securityLog = dirname(__DIR__) . '/storage/logs/security.log';
    if (!is_dir(dirname($securityLog))) {
        mkdir(dirname($securityLog), 0750, true);
    }
    $entry = sprintf("[%s] FAILED_LOGIN | IP: %s | Attempts: %d\n", date('Y-m-d H:i:s'), $ip, $data['attempts']);
    file_put_contents($securityLog, $entry, FILE_APPEND | LOCK_EX);
}

function clearLoginAttempts($ip) {
    $attemptsFile = dirname(__DIR__) . '/storage/login_attempts_' . md5($ip) . '.json';
    if (file_exists($attemptsFile)) {
        unlink($attemptsFile);
    }
}

$attemptCheck = checkLoginAttempts($_SERVER['REMOTE_ADDR']);
if ($attemptCheck !== true) {
    $error = $attemptCheck;
}

// Handle login
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $credentials && $attemptCheck === true) {
    $username = trim($_POST['username'] ?? '');
    $password = trim($_POST['password'] ?? '');
    
    if ($username === $credentials['username'] && password_verify($password, $credentials['password'])) {
        clearLoginAttempts($_SERVER['REMOTE_ADDR']);
        session_regenerate_id(true);
        
        $_SESSION['admin_logged_in'] = true;
        $_SESSION['admin_username'] = $username;
        $_SESSION['last_activity'] = time();
        
        // Log successful login
        $securityLog = dirname(__DIR__) . '/storage/logs/security.log';
        $entry = sprintf("[%s] LOGIN_SUCCESS | IP: %s | User: %s\n", date('Y-m-d H:i:s'), $_SERVER['REMOTE_ADDR'], $username);
        file_put_contents($securityLog, $entry, FILE_APPEND | LOCK_EX);
        
        header('Location: dashboard.php');
        exit;
    } else {
        recordFailedLogin($_SERVER['REMOTE_ADDR']);
        $error = 'Invalid username or password';
    }
}

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
                        <?php echo $attemptCheck !== true ? 'disabled' : ''; ?>>
                </div>
                
                <div class="form-group">
                    <label for="password">Password</label>
                    <input 
                        type="password" 
                        id="password" 
                        name="password" 
                        required
                        <?php echo $attemptCheck !== true ? 'disabled' : ''; ?>>
                </div>
                
                <button 
                    type="submit" 
                    class="btn-primary"
                    <?php echo $attemptCheck !== true ? 'disabled' : ''; ?>>
                    <?php echo $attemptCheck !== true ? 'Locked' : 'Login'; ?>
                </button>
            </form>
            
            <div class="security-notice">
                🔒 All access attempts are logged
            </div>
        </div>
    </div>
</body>
</html>
