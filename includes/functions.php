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

    $sql  = "INSERT INTO users (full_name, email, password, role, is_active, mfa_enabled, email_verified_at) VALUES (?, ?, ?, 'customer', 1, 0, NOW())";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss", $full_name, $email, $hashed_password);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function cleanup_pending_registrations(mysqli $conn): void
{
    $stmt = $conn->prepare("DELETE FROM pending_registrations WHERE expires_at < NOW()");

    if ($stmt) {
        $stmt->execute();
        $stmt->close();
    }
}

function save_pending_registration(mysqli $conn, string $full_name, string $email, string $password, ?string $phone = null): bool
{
    cleanup_pending_registrations($conn);

    $password_hash = password_hash($password, PASSWORD_DEFAULT);
    $expires_at = date('Y-m-d H:i:s', time() + 30 * 60);

    $sql = "INSERT INTO pending_registrations
                (full_name, email, password_hash, phone, role, expires_at)
            VALUES (?, ?, ?, ?, 'customer', ?)
            ON DUPLICATE KEY UPDATE
                full_name = VALUES(full_name),
                password_hash = VALUES(password_hash),
                phone = VALUES(phone),
                expires_at = VALUES(expires_at),
                created_at = CURRENT_TIMESTAMP";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sssss", $full_name, $email, $password_hash, $phone, $expires_at);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

function get_pending_registration_by_email(mysqli $conn, string $email): ?array
{
    cleanup_pending_registrations($conn);

    $stmt = $conn->prepare(
        "SELECT pending_id, full_name, email, password_hash, phone, role, expires_at, created_at
         FROM pending_registrations
         WHERE email = ? AND expires_at >= NOW()
         LIMIT 1"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $row = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $row ?: null;
}

function delete_pending_registration(mysqli $conn, string $email): void
{
    $stmt = $conn->prepare("DELETE FROM pending_registrations WHERE email = ?");

    if ($stmt) {
        $stmt->bind_param("s", $email);
        $stmt->execute();
        $stmt->close();
    }
}

function create_user_from_pending_registration(mysqli $conn, array $pending): ?int
{
    $role = $pending['role'] ?? 'customer';
    if ($role !== 'customer') {
        return null;
    }

    $phone = $pending['phone'] ?? null;

    $stmt = $conn->prepare(
        "INSERT INTO users
            (full_name, email, password, role, phone, is_active, mfa_enabled, email_verified_at)
         VALUES (?, ?, ?, 'customer', ?, 1, 0, NOW())"
    );

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param(
        "ssss",
        $pending['full_name'],
        $pending['email'],
        $pending['password_hash'],
        $phone
    );

    $success = $stmt->execute();
    $new_user_id = $success ? (int)$stmt->insert_id : null;
    $stmt->close();

    return $new_user_id ?: null;
}

/* ============================================================
   LOGIN HELPERS
   ============================================================ */

function find_user_by_email(mysqli $conn, string $email): ?array
{
    $sql = "SELECT user_id, full_name, email, password, role, is_active,
                   COALESCE(mfa_enabled, 0)              AS mfa_enabled,
                   email_verified_at
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

function find_user_by_id(mysqli $conn, int $user_id): ?array
{
    $sql = "SELECT user_id, full_name, email, password, role, is_active,
                   COALESCE(mfa_enabled, 0) AS mfa_enabled,
                   email_verified_at
            FROM users
            WHERE user_id = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);
    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    $stmt->close();

    return $user ?: null;
}

function login_user(array $user): void
{
    $flash = $_SESSION;
    session_regenerate_id();
    $_SESSION = $flash;

    $_SESSION['user_id']        = $user['user_id'];
    $_SESSION['full_name']      = $user['full_name'];
    $_SESSION['email']          = $user['email'];
    $_SESSION['role']           = $user['role'];

    $now = time();
    $_SESSION['_last_activity'] = $now;
    $_SESSION['_last_regen']    = $now;

    session_write_close();
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

    header("Location: portal-login.php");
    exit;
}

/* ============================================================
   DELIVERY MODE HELPERS
   ============================================================ */

function delivery_mode_label(string $mode): string
{
    return match($mode) {
        'delivery' => 'Delivery 🚚',
        'takeaway' => 'Takeaway 🥡',
        'dine_in'  => 'Dine-in 🍽️',
        default    => ucfirst(str_replace('_', ' ', $mode)),
    };
}

/* ============================================================
   RATE LIMITING — SCRUM-53
   ─────────────────────────────────────────────────────────────
   Constants:
     RATE_LIMIT_MAX    — max failed attempts before lockout (5)
     RATE_LIMIT_WINDOW — rolling time window in seconds (5 min)
   ============================================================ */

define('RATE_LIMIT_MAX',    5);
define('RATE_LIMIT_WINDOW', 5 * 60);

/**
 * is_rate_limited()
 * Checks if IP has exceeded failed login attempts within the window.
 * Also purges expired attempts on every call — no cron needed.
 */
function is_rate_limited(mysqli $conn, string $ip): array
{
    $window = RATE_LIMIT_WINDOW;

    $purge = $conn->prepare(
        "DELETE FROM login_attempts
         WHERE ip_address = ?
           AND attempted_at < DATE_SUB(NOW(), INTERVAL ? SECOND)"
    );
    $purge->bind_param("si", $ip, $window);
    $purge->execute();
    $purge->close();

    $count_stmt = $conn->prepare(
        "SELECT
             COUNT(*) AS attempt_count,
             GREATEST(0, ? - TIMESTAMPDIFF(SECOND, MIN(attempted_at), NOW())) AS remaining_seconds
         FROM login_attempts
         WHERE ip_address = ?"
    );
    $count_stmt->bind_param("is", $window, $ip);
    $count_stmt->execute();
    $row = $count_stmt->get_result()->fetch_assoc();
    $count_stmt->close();

    $attempt_count     = (int)($row['attempt_count'] ?? 0);
    $remaining_seconds = (int)($row['remaining_seconds'] ?? 0);
    $is_blocked        = $attempt_count >= RATE_LIMIT_MAX;

    $remaining_minutes = ($is_blocked && $remaining_seconds > 0)
                         ? max(1, (int)ceil($remaining_seconds / 60))
                         : 0;

    return [
        'blocked'   => $is_blocked,
        'remaining' => $remaining_minutes,
        'attempts'  => $attempt_count,
    ];
}

/**
 * log_failed_attempt()
 * Records one failed login attempt for this IP in login_attempts.
 */
function log_failed_attempt(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

/**
 * clear_failed_attempts()
 * Removes all failed attempt records for this IP after successful login.
 */
function clear_failed_attempts(mysqli $conn, string $ip): void
{
    $stmt = $conn->prepare("DELETE FROM login_attempts WHERE ip_address = ?");
    $stmt->bind_param("s", $ip);
    $stmt->execute();
    $stmt->close();
}

/* ============================================================
   USER LOGS 
   ─────────────────────────────────────────────────────────────
   log_user_event() is the single function all pages call to
   write an audit entry to the user_logs table.

   Supported event types (must match the ENUM in user_logs):
     'login_success'  — user authenticated and session started
     'login_failed'   — wrong credentials or blocked account
     'logout'         — user ended their session
     'access_denied'  — user tried to reach a restricted page

   $user_id is nullable — failed logins may not have a known user.

   The function fails silently on DB error so a logging failure
   never crashes the page the user is trying to visit.
   ============================================================ */

/**
 * log_user_event()
 *
 * @param mysqli   $conn        Active database connection
 * @param string   $event_type  One of: login_success, login_failed, logout, access_denied
 * @param string   $ip          Visitor IP address
 * @param string   $description Short human-readable note (max 255 chars)
 * @param int|null $user_id     User ID if known, null for anonymous failed logins
 */
function log_user_event(
    mysqli $conn,
    string $event_type,
    string $ip,
    string $description = '',
    ?int   $user_id     = null
): void {
    // Guard: only write recognised event types to avoid DB ENUM errors
    $allowed = ['login_success', 'login_failed', 'logout', 'access_denied'];
    if (!in_array($event_type, $allowed, true)) {
        return;
    }

    // Clamp description to the DB column length
    $description = mb_substr($description, 0, 255);

    $stmt = $conn->prepare(
        "INSERT INTO user_logs (user_id, event_type, ip_address, description)
         VALUES (?, ?, ?, ?)"
    );

    // Fail silently if the table doesn't exist yet or prepare fails
    if (!$stmt) {
        return;
    }

    // bind 'i' for user_id — MySQLi correctly sends NULL when the PHP var is null
    $stmt->bind_param("isss", $user_id, $event_type, $ip, $description);
    $stmt->execute();
    $stmt->close();
}
