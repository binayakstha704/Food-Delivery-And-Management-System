<?php

/* ============================================================
   INPUT HELPERS
   ============================================================ */

function clean_input(string $data): string
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/* ============================================================
   REGISTRATION HELPERS
   ============================================================ */

function validate_registration_data(string $full_name, string $email, string $password, string $confirm_password): array
{
    $errors = [];

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($full_name) < 2) {
        $errors[] = 'Full name must be at least 2 characters long.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 8) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($confirm_password === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    return $errors;
}

function email_exists(mysqli $conn, string $email): bool
{
    $sql  = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function register_user(mysqli $conn, string $full_name, string $email, string $password): bool
{
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql  = "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss", $full_name, $email, $hashed_password);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

/* ============================================================
   LOGIN HELPERS
   ============================================================ */

function find_user_by_email(mysqli $conn, string $email): ?array
{
    $sql = "SELECT user_id, full_name, email, password, role, is_active
            FROM users
            WHERE email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user   = $result->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function validate_login_data(string $email, string $password): array
{
    $errors = [];

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    return $errors;
}

function login_user(array $user): void
{
    // Regenerate session ID on login to prevent session fixation attacks
    session_regenerate_id(true);

    $_SESSION['user_id']   = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email']     = $user['email'];
    $_SESSION['role']      = $user['role'];

    // Re-initialise security metadata after regeneration
    $now = time();
    $_SESSION['_fingerprint']   = hash('sha256',
        ($_SERVER['HTTP_USER_AGENT']      ?? '') .
        ($_SERVER['HTTP_ACCEPT_LANGUAGE'] ?? '') .
        ($_SERVER['HTTP_ACCEPT_ENCODING'] ?? '')
    );
    $_SESSION['_created']       = $now;
    $_SESSION['_last_activity'] = $now;
    $_SESSION['_last_regen']    = $now;
}

function redirect_user_by_role(string $role): void
{
    if ($role === 'chef') {
        header("Location: chef-control.php");
        exit;
    }

    if ($role === 'staff') {
        header("Location: staff-control.php");
        exit;
    }

    if ($role === 'customer') {
        header("Location: dashboard.php");
        exit;
    }

    header("Location: user-login.php");
    exit;
}

/* ============================================================
   RATE LIMITING — SCRUM-53
   ─────────────────────────────────────────────────────────────
   All three functions below work together to implement
   database-backed rate limiting on the login page.

   Why database-backed instead of session-based?
   - Session counters reset when a user clears cookies or opens
     a new browser/incognito tab — completely bypassing the lock.
   - Storing attempts in the login_attempts table means the
     block is enforced by IP address regardless of session state.

   Constants:
     RATE_LIMIT_MAX      — max failed attempts before lockout (5)
     RATE_LIMIT_WINDOW   — rolling time window in seconds (15 min)
   ============================================================ */

define('RATE_LIMIT_MAX',    5);
define('RATE_LIMIT_WINDOW', 15 * 60); // 15 minutes in seconds

/**
 * is_rate_limited()
 *
 * Checks whether the given IP address has exceeded the allowed
 * number of failed login attempts within the rolling time window.
 *
 * It also purges expired attempts for this IP on every call so
 * the table stays clean without needing a cron job.
 *
 * Returns an array with:
 *   'blocked'   => bool   — true if the IP is currently locked out
 *   'remaining' => int    — minutes left on the lockout (0 if not blocked)
 *   'attempts'  => int    — current failed attempt count in the window
 */
function is_rate_limited(mysqli $conn, string $ip): array
{
    // 1. Delete attempts older than the rolling window for this IP.
    //    This is the automatic expiry — no cron needed.
    //    Note: bind_param() requires variables (passed by reference),
    //    so we copy the constant into a variable before binding.
    $window = RATE_LIMIT_WINDOW;
    $purge = $conn->prepare(
        "DELETE FROM login_attempts
         WHERE ip_address = ?
           AND attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $purge->bind_param("si", $ip, $window);
    $purge->execute();
    $purge->close();

    // 2. Count how many valid (within-window) attempts remain for this IP.
    $count_stmt = $conn->prepare(
        "SELECT COUNT(*) AS attempt_count,
                MIN(attempted_at) AS oldest_attempt
         FROM login_attempts
         WHERE ip_address = ?"
    );
    $count_stmt->bind_param("s", $ip);
    $count_stmt->execute();
    $row = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();

    $attempt_count  = (int)($row['attempt_count'] ?? 0);
    $oldest_attempt = $row['oldest_attempt'] ?? null;

    // 3. Calculate how many minutes remain on the lockout window.
    $remaining_minutes = 0;
    if ($attempt_count >= RATE_LIMIT_MAX && $oldest_attempt !== null) {
        $unlock_time       = strtotime($oldest_attempt) + RATE_LIMIT_WINDOW;
        $remaining_seconds = $unlock_time - time();
        $remaining_minutes = (int)ceil($remaining_seconds / 60);
        if ($remaining_minutes < 1) {
            $remaining_minutes = 1;
        }
    }

    return [
        'blocked'   => $attempt_count >= RATE_LIMIT_MAX,
        'remaining' => $remaining_minutes,
        'attempts'  => $attempt_count,
    ];
}

/**
 * log_failed_attempt()
 *
 * Insertsone row into login_attempts for the given IP address.
 * Called every time a login attempt fails for any reason
 * (wrong email, wrong password, inactive account, etc.).
 */
function log_failed_attempt(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare(
        "INSERT INTO login_attempts (ip_address) VALUES (?)"
    );
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * clear_failed_attempts()
 *
 * Deletes ALL login_attempts rows for the given IP address.
 * Called immediately after a successful login so the user
 * starts fresh on their next visit
 */
function clear_failed_attempts(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare(
        "DELETE FROM login_attempts WHERE ip_address = ?"
    );
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}
