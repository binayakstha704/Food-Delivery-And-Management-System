<?php

/* ============================================================
   SESSION SECURITY CONFIGURATION
   ============================================================ */

function configure_secure_session(): void
{
    ini_set('session.cookie_httponly', '1');
    if (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') {
        ini_set('session.cookie_secure', '1');
    }
    ini_set('session.cookie_samesite', 'Strict');
    ini_set('session.use_only_cookies', '1');
    ini_set('session.use_trans_sid', '0');
}

/* ============================================================
   SESSION FINGERPRINTING & FIXATION PROTECTION
   ============================================================ */

function session_security_check(): void
{
    $now = time();

    $fingerprint = hash('sha256',
        ($_SERVER['HTTP_USER_AGENT'] ?? '') .
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
        ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')
    );

    if (!isset($_SESSION['_fingerprint'])) {
        $_SESSION['_fingerprint']   = $fingerprint;
        $_SESSION['_created']       = $now;
        $_SESSION['_last_activity'] = $now;
        $_SESSION['_last_regen']    = $now;
    } else {
        if (!hash_equals($_SESSION['_fingerprint'], $fingerprint)) {
            session_destroy();
            session_start();
            configure_secure_session();
            return;
        }
    }

    // Idle timeout: 30 minutes
    $idle_timeout = 30 * 60;
    if ($now - ($_SESSION['_last_activity'] ?? $now) > $idle_timeout) {
        $was_logged_in = isset($_SESSION['user_id']);
        session_unset();
        session_destroy();
        session_start();
        configure_secure_session();
        if ($was_logged_in) {
            header('Location: portal-login.php?timeout=1');
            exit;
        }
        return;
    }
    $_SESSION['_last_activity'] = $now;

    // Regenerate session ID every 15 minutes
    $regen_interval = 15 * 60;
    if ($now - ($_SESSION['_last_regen'] ?? $now) > $regen_interval) {
        session_regenerate_id(true);
        $_SESSION['_last_regen'] = $now;
    }
}

/* ============================================================
   ROLE-BASED ACCESS GUARDS
   ============================================================ */

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header('Location: portal-login.php');
        exit;
    }
}

function require_role(string $required_role): void
{
    require_login();
    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header('Location: portal-login.php');
        exit;
    }
}

/* ============================================================
   LOGOUT HELPER
   ============================================================ */

function logout_user(): void
{
    session_unset();
    session_destroy();
    session_start();
    configure_secure_session();
}
?>
