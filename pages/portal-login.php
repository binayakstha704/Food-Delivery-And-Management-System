<?php
require_once "../includes/auth.php";
configure_secure_session();
session_start();
session_security_check();
require_once "../config/db.php";
require_once "../includes/functions.php";

/* ============================================================
   RATE LIMIT CHECK 
   ─────────────────────────────────────────────────────────────
   We check the database-backed rate limit BEFORE processing
   any POST data so a locked-out IP never reaches the
   password_verify() call.
   ============================================================ */
$ip_address  = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
$rate_status = is_rate_limited($conn, $ip_address);

$field_errors = [];

// If this IP is currently locked out, set a general error right away.
// The form will still render but the POST block below will be skipped.
if ($rate_status['blocked']) {
    $field_errors['general'] = "Too many failed login attempts. "
        . "Please try again in {$rate_status['remaining']} minute(s).";
}

/* ============================================================
   PROCESS LOGIN FORM
   Only runs when: method is POST AND the IP is not locked out.
   ============================================================ */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && empty($field_errors['general'])) {

    $email    = clean_input($_POST['email']    ?? '');
    $password =             $_POST['password'] ?? '';

    // ── Field-level validation ────────────────────────────────

    // Email
    if ($email === '') {
        $field_errors['email'] = 'Email address is required.';
    } elseif (trim($email) === '') {
        $field_errors['email'] = 'Email address cannot consist of whitespace only.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['email'] = 'Please enter a valid email address.';
    }

    // Password — whitespace-only check fixes OFDS-1
    $trimmed_password = trim($password);
    if ($password === '') {
        $field_errors['password'] = 'Password is required.';
    } elseif ($trimmed_password === '') {
        $field_errors['password'] = 'Password cannot consist of whitespace only.';
    }

    // ── Database check (only if field validation passed) ──────
    if (empty($field_errors)) {

        $user = find_user_by_email($conn, $email);

        if (!$user) {
            // Unknown email — log the failed attempt
            log_failed_attempt($conn, $ip_address);
            $field_errors['email'] = 'No account found with this email.';

        } elseif ((int)$user['is_active'] !== 1) {
            // Inactive accounts do NOT count as a brute-force attempt
            // because the attacker already knows the email is valid.
            // We log it anyway to keep an audit trail.
            log_failed_attempt($conn, $ip_address);
            $field_errors['general'] = 'This account has been deactivated. Please contact support.';

        } elseif (!in_array($user['role'], ['customer', 'chef', 'staff'], true)) {
            // Invalid role — should not normally happen
            log_failed_attempt($conn, $ip_address);
            $field_errors['general'] = 'This account is not authorised to log in.';

        } elseif (!password_verify($password, $user['password'])) {
            // Wrong password — log the failed attempt
            log_failed_attempt($conn, $ip_address);
            $field_errors['password'] = 'Incorrect password.';

        } else {
            // ── Successful login ──────────────────────────────
            // Clear all previous failed attempts for this IP.
            clear_failed_attempts($conn, $ip_address);

            login_user($user);
            redirect_user_by_role($user['role']);
        }

        // After logging a failed attempt, re-check whether the IP
        // has now hit the limit so we show the lockout message
        // immediately on this same page load.
        if (!empty($field_errors)) {
            $rate_status = is_rate_limited($conn, $ip_address);
            if ($rate_status['blocked']) {
                $field_errors = []; // clear individual errors
                $field_errors['general'] = "Too many failed login attempts. "
                    . "Please try again in {$rate_status['remaining']} minute(s).";
            }
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Login – Herald Canteen</title>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>
<div class="container">
    <div class="login-box">

        <div class="logo">
            <h1>Herald <span>Canteen</span></h1>
            <p>Login to Your Account</p>
        </div>

        <?php if (isset($_GET['timeout'])): ?>
            <div class="alert error">
                ⏳ Your session has expired. Please log in again.
            </div>
        <?php endif; ?>

        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
            <div class="alert success">
                ✅ Registration successful. Please log in.
            </div>
        <?php endif; ?>

        <?php if (!empty($field_errors['general'])): ?>
            <div class="alert error">
                🔒 <?php echo htmlspecialchars($field_errors['general'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">

            <div class="input-group <?php echo isset($field_errors['email']) ? 'has-error' : ''; ?>">
                <label>Email Address</label>
                <div class="input-wrapper">
                    <span class="input-icon">📧</span>
                    <input
                        type="email"
                        name="email"
                        placeholder="Enter your email"
                        value="<?php echo htmlspecialchars($_POST['email'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                        required
                        <?php echo $rate_status['blocked'] ? 'disabled' : ''; ?>
                    >
                </div>
                <?php if (isset($field_errors['email'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['email'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <div class="input-group <?php echo isset($field_errors['password']) ? 'has-error' : ''; ?>">
                <label>Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔒</span>
                    <input
                        type="password"
                        name="password"
                        placeholder="Enter your password"
                        required
                        <?php echo $rate_status['blocked'] ? 'disabled' : ''; ?>
                    >
                </div>
                <?php if (isset($field_errors['password'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['password'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </div>

            <button
                type="submit"
                class="login-btn"
                <?php echo $rate_status['blocked'] ? 'disabled' : ''; ?>
            >
                <?php echo $rate_status['blocked'] ? '🔒 Account Locked' : 'Login'; ?>
            </button>

        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>

    </div>
</div>
</body>
</html>
