<?php
/**
 * PrimeCast Common Functions
 * Shared utilities for all PHP scripts
 */

// Environment configuration
define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');
define('SITE_URL', getenv('SITE_URL') ?: 'https://primecast.ct.ws');
define('ADMIN_EMAIL', getenv('ADMIN_EMAIL') ?: 'istvan.szekely@gmail.com');

/**
 * Start session safely with security settings
 */
function startSession() {
    if (session_status() === PHP_SESSION_NONE) {
        ini_set('session.cookie_httponly', 1);
        ini_set('session.cookie_secure', 1);
        ini_set('session.cookie_samesite', 'Strict');
        session_start();
    }
}

/**
 * Get or generate CSRF token
 */
function getCSRFToken() {
    startSession();
    
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }
    
    return $_SESSION['csrf_token'];
}

/**
 * Validate CSRF token
 */
function validateCSRFToken($token) {
    startSession();
    
    if (!isset($_SESSION['csrf_token']) || empty($token)) {
        return false;
    }
    
    return hash_equals($_SESSION['csrf_token'], $token);
}

/**
 * Send JSON error response
 */
function sendError($code, $message) {
    http_response_code($code);
    echo json_encode([
        'success' => false,
        'error' => $message
    ]);
    exit;
}

/**
 * Send JSON success response
 */
function sendSuccess($data = []) {
    echo json_encode(array_merge(['success' => true], $data));
    exit;
}

/**
 * Sanitize string input
 */
function sanitizeString($input) {
    $sanitized = htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
    // Remove potential email header injection characters
    return str_replace(["\r", "\n", "%0a", "%0d"], '', $sanitized);
}

/**
 * Log security event
 */
function logSecurityEvent($event, $details = []) {
    $logDir = __DIR__ . '/../storage/logs';
    if (!is_dir($logDir)) {
        mkdir($logDir, 0750, true);
    }
    
    $logFile = $logDir . '/security.log';
    rotateLogIfNeeded($logFile);
    
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $detailsStr = '';
    foreach ($details as $key => $value) {
        $detailsStr .= " | $key: $value";
    }
    
    $entry = sprintf("[%s] %s | IP: %s%s\n", $timestamp, $event, $ip, $detailsStr);
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}

/**
 * Ensure storage directory exists with proper permissions
 */
function ensureStorageDirectory($path = '') {
    $fullPath = __DIR__ . '/../storage/' . $path;
    
    if (!is_dir($fullPath)) {
        if (!mkdir($fullPath, 0750, true)) {
            error_log("Failed to create directory: $fullPath");
            return false;
        }
    }
    
    // Ensure .htaccess exists to protect storage
    $htaccess = __DIR__ . '/../storage/.htaccess';
    if (!file_exists($htaccess)) {
        $content = "# Deny all access to storage directory\n";
        $content .= "Require all denied\n";
        file_put_contents($htaccess, $content);
    }
    
    return true;
}

/**
 * Check rate limit for IP address
 * @return bool|string True if allowed, error message if blocked
 */
function checkRateLimit($identifier, $maxRequests = 5, $timeWindow = 300) {
    ensureStorageDirectory();
    
    $storageDir = __DIR__ . '/../storage';
    $rateFile = $storageDir . '/rate_limit_' . md5($identifier) . '.json';
    $now = time();
    
    // Randomly clean old rate limit files (1% chance)
    if (rand(1, 100) === 1) {
        cleanRateLimitFiles();
    }
    
    if (file_exists($rateFile)) {
        $data = json_decode(file_get_contents($rateFile), true);
        if ($data && isset($data['attempts'])) {
            // Filter attempts within time window
            $attempts = array_filter($data['attempts'], function($timestamp) use ($now, $timeWindow) {
                return ($now - $timestamp) < $timeWindow;
            });
            
            if (count($attempts) >= $maxRequests) {
                $waitTime = ceil($timeWindow / 60);
                return "Too many requests. Please try again in $waitTime minutes.";
            }
            
            $attempts[] = $now;
            file_put_contents($rateFile, json_encode(['attempts' => array_values($attempts)]), LOCK_EX);
            return true;
        }
    }
    
    file_put_contents($rateFile, json_encode(['attempts' => [$now]]), LOCK_EX);
    return true;
}

/**
 * Clean old rate limit files (run periodically)
 */
function cleanRateLimitFiles() {
    $storageDir = __DIR__ . '/../storage';
    $files = glob($storageDir . '/rate_limit_*.json');
    $now = time();
    $maxAge = 3600; // 1 hour
    
    foreach ($files as $file) {
        if (filemtime($file) < ($now - $maxAge)) {
            @unlink($file);
        }
    }
}

/**
 * Rotate log file if too large
 */
function rotateLogIfNeeded($logFile, $maxSize = 5242880) { // 5MB
    if (!file_exists($logFile)) {
        return;
    }
    
    if (filesize($logFile) > $maxSize) {
        $archiveName = $logFile . '.' . date('Y-m-d-His');
        rename($logFile, $archiveName);
        
        // Keep only last 10 archived logs
        $dir = dirname($logFile);
        $base = basename($logFile);
        $archives = glob($dir . '/' . $base . '.*');
        
        if (count($archives) > 10) {
            usort($archives, function($a, $b) {
                return filemtime($a) - filemtime($b);
            });
            @unlink($archives[0]);
        }
    }
}

/**
 * Send email using SMTP (PHPMailer)
 */
function sendEmail($to, $subject, $message, $replyTo = null) {
    // Load PHPMailer
    require_once __DIR__ . '/PHPMailer/PHPMailer.php';
    require_once __DIR__ . '/PHPMailer/SMTP.php';
    require_once __DIR__ . '/PHPMailer/Exception.php';
    
    $mail = new PHPMailer\PHPMailer\PHPMailer(true);
    
    try {
        // SMTP Configuration
        $mail->isSMTP();
        $mail->Host       = getenv('SMTP_HOST') ?: 'smtp.gmail.com'; // Change this
        $mail->SMTPAuth   = true;
        $mail->Username   = getenv('SMTP_USERNAME') ?: 'your-email@gmail.com'; // Change this
        $mail->Password   = getenv('SMTP_PASSWORD') ?: 'your-app-password'; // Change this
        $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
        $mail->Port       = getenv('SMTP_PORT') ?: 587;
        $mail->CharSet    = 'UTF-8';
        
        // Sender
        $mail->setFrom(ADMIN_EMAIL, 'PrimeCast');
        
        // Recipient
        $mail->addAddress($to);
        
        // Reply-To
        if ($replyTo) {
            $mail->addReplyTo($replyTo);
        } else {
            $mail->addReplyTo(ADMIN_EMAIL);
        }
        
        // Content
        $mail->isHTML(false); // Plain text
        $mail->Subject = $subject;
        $mail->Body    = $message;
        
        // Send
        $mail->send();
        return true;
        
    } catch (Exception $e) {
        error_log("Email failed to send. To: $to, Error: {$mail->ErrorInfo}");
        logSecurityEvent('EMAIL_FAILED', [
            'to' => $to, 
            'subject' => $subject,
            'error' => $mail->ErrorInfo
        ]);
        return false;
    }
}
/**
 * Validate order reference format
 */
function validateOrderReference($ref) {
    return preg_match('/^PC-\d{13}-\d{4}$/', $ref);
}

/**
 * Validate plan name
 */
function validatePlan($plan) {
    $validPlans = [
        'Basic Plan (1 Month)',
        'Standard Plan (3 Months)',
        'Premium Plan (12 Months)'
    ];
    return in_array($plan, $validPlans);
}

/**
 * Get allowed CORS origin
 */
function getAllowedCORSOrigin() {
    $allowedOrigins = [
        SITE_URL,
        'https://primecast.ct.ws',
        'http://localhost:3000' // For development
    ];
    
    $origin = $_SERVER['HTTP_ORIGIN'] ?? '';
    
    if (ENVIRONMENT === 'development') {
        return $origin ?: SITE_URL;
    }
    
    return in_array($origin, $allowedOrigins) ? $origin : SITE_URL;
}

/**
 * Set security headers
 */
function setSecurityHeaders() {
    header('Content-Type: application/json');
    header('X-Content-Type-Options: nosniff');
    header('X-Frame-Options: DENY');
    header('X-XSS-Protection: 1; mode=block');
    header('Referrer-Policy: strict-origin-when-cross-origin');
}

/**
 * Set CORS headers
 */
function setCORSHeaders($methods = 'GET, POST') {
    $origin = getAllowedCORSOrigin();
    header("Access-Control-Allow-Origin: $origin");
    header("Access-Control-Allow-Methods: $methods");
    header("Access-Control-Allow-Headers: Content-Type");
    header("Access-Control-Allow-Credentials: true");
}

/**
 * Validate request size
 */
function validateRequestSize($maxSize = 1048576) { // 1MB
    if (isset($_SERVER['CONTENT_LENGTH']) && $_SERVER['CONTENT_LENGTH'] > $maxSize) {
        sendError(413, 'Payload too large');
    }
}
