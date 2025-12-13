<?php
/**
 * PrimeCast CSRF Token Generator
 * Provides CSRF tokens for frontend forms
 */

require_once __DIR__ . '/functions.php';

// Set security and CORS headers
setSecurityHeaders();
setCORSHeaders('GET');

// Check request method
if ($_SERVER['REQUEST_METHOD'] !== 'GET') {
    sendError(405, 'Method not allowed');
}

// Get or generate CSRF token
$token = getCSRFToken();

// Return token
sendSuccess(['token' => $token]);
