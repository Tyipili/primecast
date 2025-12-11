<?php
/**
 * PrimeCast Configuration File
 * Store this file OUTSIDE your web root for maximum security
 */

// Prevent direct access
if (!defined('PRIMECAST_ACCESS')) {
    die('Direct access not permitted');
}

// Environment (change to 'production' when live)
define('ENVIRONMENT', 'development'); // 'development' or 'production'

// Error Reporting
if (ENVIRONMENT === 'development') {
    ini_set('display_errors', 1);
    error_reporting(E_ALL);
} else {
    ini_set('display_errors', 0);
    error_reporting(0);
    ini_set('log_errors', 1);
}

// Security Settings
ini_set('session.cookie_httponly', 1);
ini_set('session.use_strict_mode', 1);
ini_set('session.cookie_samesite', 'Strict');

// Paths
define('ROOT_PATH', dirname(__DIR__));
define('STORAGE_PATH', ROOT_PATH . '/storage');
define('LOG_PATH', STORAGE_PATH . '/logs');

// Create necessary directories
if (!is_dir(STORAGE_PATH)) {
    mkdir(STORAGE_PATH, 0750, true);
}
if (!is_dir(LOG_PATH)) {
    mkdir(LOG_PATH, 0750, true);
}

// Security Constants
define('SESSION_TIMEOUT', 1800); // 30 minutes
define('MAX_LOGIN_ATTEMPTS', 5);
define('LOCKOUT_TIME', 900); // 15 minutes
define('RATE_LIMIT_REQUESTS', 5);
define('RATE_LIMIT_WINDOW', 300); // 5 minutes
define('MAX_POST_SIZE', 1048576); // 1MB

// File Permissions
define('DIR_PERMISSIONS', 0750);
define('FILE_PERMISSIONS', 0640);

// Email Configuration
define('EMAIL_FROM', 'info@primecast.world');
define('EMAIL_FROM_NAME', 'PrimeCast');

// PayPal Configuration
// IMPORTANT: Get these from https://developer.paypal.com
define('PAYPAL_CLIENT_ID', getenv('PAYPAL_CLIENT_ID') ?: 'YOUR_PAYPAL_CLIENT_ID_HERE');
define('PAYPAL_SECRET', getenv('PAYPAL_SECRET') ?: 'YOUR_PAYPAL_SECRET_HERE');
define('PAYPAL_MODE', ENVIRONMENT === 'production' ? 'live' : 'sandbox');

// PayPal API URLs
if (PAYPAL_MODE === 'live') {
    define('PAYPAL_API_URL', 'https://api-m.paypal.com');
} else {
    define('PAYPAL_API_URL', 'https://api-m.sandbox.paypal.com');
}

// Allowed Plans (whitelist)
define('ALLOWED_PLANS', ['basic', 'standard', 'premium']);

// Plan Prices (for verification)
define('PLAN_PRICES', [
    'basic' => 19.99,
    'standard' => 29.99,
    'premium' => 39.99
]);

/**
 * Get price for a plan
 */
function getPlanPrice($plan) {
    $plan = strtolower($plan);
    return PLAN_PRICES[$plan] ?? 0;
}

/**
 * Check if HTTPS is being used
 */
function isSecure() {
    return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');
}

// Force HTTPS in production
if (ENVIRONMENT === 'production' && !isSecure() && php_sapi_name() !== 'cli') {
    header('Location: https://' . $_SERVER['HTTP_HOST'] . $_SERVER['REQUEST_URI'], true, 301);
    exit;
}
