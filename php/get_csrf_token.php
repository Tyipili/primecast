<?php
/**
 * PrimeCast CSRF Token Generator
 * Provides CSRF tokens for frontend forms
 */

require_once __DIR__ . '/functions.php';

header('Content-Type: application/json');
header('X-Content-Type-Options: nosniff');

// CORS headers
if (ENVIRONMENT === 'development') {
    header('Access-Control-Allow-Origin: *');
} else {
    header('Access-Control-Allow-Origin: https://yourdomain.com');
}

header('Access-Control-Allow-Methods: GET');
header('Access-Control-Allow-Headers: Content-Type');

if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError(405, 'Method not allowed');
}

$token = getCSRFToken();

echo json_encode([
    'success' => true,
    'token' => $token
]);
