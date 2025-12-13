<?php
/**
 * PrimeCast Common Functions
 */

// Environment configuration
define('ENVIRONMENT', getenv('APP_ENV') ?: 'production');

/**
 * Start session safely
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
    
    if (!isset($_SESSION['csrf_token'])) {
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
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES, 'UTF-8');
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
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $timestamp = date('Y-m-d H:i:s');
    
    $detailsStr = '';
    foreach ($details as $key => $value) {
        $detailsStr .= " | $key: $value";
    }
    
    $entry = sprintf("[%s] %s | IP: %s%s\n", $timestamp, $event, $ip, $detailsStr);
    file_put_contents($logFile, $entry, FILE_APPEND | LOCK_EX);
}
