<?php
/**
 * Common admin session hardening helpers.
 */

function configure_admin_session(array $options = []): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }

    $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
        || (isset($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
        || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower($_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https');

    $cookieOptions = array_merge([
        'lifetime' => $options['cookie_lifetime'] ?? 3600,
        'path' => '/',
        'httponly' => true,
        'samesite' => 'Strict',
        'secure' => $isSecure,
    ], $options);

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
}

function logout_admin_session(): void
{
    $_SESSION = [];

    if (ini_get('session.use_cookies')) {
        $params = session_get_cookie_params();

        if (PHP_VERSION_ID >= 70300) {
            setcookie(session_name(), '', [
                'expires' => time() - 42000,
                'path' => $params['path'] ?? '/',
                'domain' => $params['domain'] ?? '',
                'secure' => $params['secure'] ?? false,
                'httponly' => $params['httponly'] ?? true,
                'samesite' => $params['samesite'] ?? 'Strict',
            ]);
        } else {
            $path = ($params['path'] ?? '/') . '; samesite=' . ($params['samesite'] ?? 'Strict');
            setcookie(
                session_name(),
                '',
                time() - 42000,
                $path,
                '',
                $params['secure'] ?? false,
                $params['httponly'] ?? true
            );
        }
    }

    session_destroy();
}

function enforce_admin_session_lifetime(array $options = []): void
{
    $now = time();
    $absoluteLifetime = $options['absolute_lifetime'] ?? 7200; // 2 hours
    $idleTimeout = $options['idle_timeout'] ?? 1800; // 30 minutes
    $regenerateInterval = $options['regenerate_interval'] ?? 900; // 15 minutes

    if (!isset($_SESSION['created_at'])) {
        $_SESSION['created_at'] = $now;
    } elseif ($now - $_SESSION['created_at'] > $absoluteLifetime) {
        logout_admin_session();
        header('Location: login.php?reason=expired');
        exit;
    }

    if (!isset($_SESSION['last_activity'])) {
        $_SESSION['last_activity'] = $now;
    } elseif ($now - $_SESSION['last_activity'] > $idleTimeout) {
        logout_admin_session();
        header('Location: login.php?reason=idle');
        exit;
    }

    $_SESSION['last_activity'] = $now;

    $lastRegenerate = $_SESSION['last_regenerate'] ?? 0;
    if ($now - $lastRegenerate > $regenerateInterval) {
        session_regenerate_id(true);
        $_SESSION['last_regenerate'] = $now;
    }
}
