<?php
session_start();

// Check if already logged in
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: dashboard.php');
    exit;
}

$error = '';

// Handle login form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = isset($_POST['username']) ? trim($_POST['username']) : '';
    $password = isset($_POST['password']) ? trim($_POST['password']) : '';
    
    // Load credentials from encrypted file
    $credentialsFile = '.htpasswd';
    
    if (file_exists($credentialsFile)) {
        $credentials = json_decode(file_get_contents($credentialsFile), true);
        
        if ($credentials && 
            $username === $credentials['username'] && 
            password_verify($password, $credentials['password'])) {
            
            $_SESSION['admin_logged_in'] = true;
            $_SESSION['admin_username'] = $username;
            header('Location: dashboard.php');
            exit;
        } else {
            $error = 'Invalid username or password';
        }
    } else {
        // Create default credentials if file doesn't exist
        $defaultCredentials = [
            'username' => 'admin',
            'password' => password_hash('primecast2024', PASSWORD_DEFAULT)
        ];
        file_put_contents($credentialsFile, json_encode($defaultCredentials));
        $error = 'Default credentials created. Username: admin, Password: primecast2024';
    }
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
                <svg width="60" height="60" viewBox="0 0 50 50" style="margin: 0 auto;">
                    <defs>
                        <linearGradient id="loginLogoGradient" x1="0%" y1="0%" x2="100%" y2="100%">
                            <stop offset="0%" style="stop-color:#00E5FF;stop-opacity:1" />
                            <stop offset="100%" style="stop-color:#0091EA;stop-opacity:1" />
                        </linearGradient>
                    </defs>
                    <path d="M 10 10 L 10 40 L 15 40 L 15 15 L 40 15 L 40 10 Z" fill="url(#loginLogoGradient)" opacity="0.3"/>
                    <path d="M 15 15 L 15 45 L 45 45 L 45 15 Z" fill="none" stroke="url(#loginLogoGradient)" stroke-width="2"/>
                    <path d="M 22 22 L 22 38 L 36 30 Z" fill="url(#loginLogoGradient)"/>
                </svg>
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
                Default credentials: admin / primecast2024
            </p>
        </div>
    </div>
</body>
</html>