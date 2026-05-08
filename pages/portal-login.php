<?php
session_start();
require_once "../config/db.php";
require_once "../includes/functions.php";

$field_errors = [];

// Rate limiting - prevent brute force attacks
$ip_address = $_SERVER['REMOTE_ADDR'];
$attempt_key = 'login_attempts_' . $ip_address;
$lockout_key = 'login_lockout_' . $ip_address;

if (!isset($_SESSION[$attempt_key])) {
    $_SESSION[$attempt_key] = 0;
}

// Check if account is locked out (15 minute lockout after 5 failed attempts)
if (isset($_SESSION[$lockout_key]) && $_SESSION[$lockout_key] > time()) {
    $remaining_lockout = ceil(($_SESSION[$lockout_key] - time()) / 60);
    $field_errors['general'] = "Too many failed login attempts. Please try again in {$remaining_lockout} minutes.";
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !isset($field_errors['general'])) {
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    // Email validation with whitespace check
    if ($email === '') {
        $field_errors['email'] = 'Email address is required.';
    } elseif (trim($email) === '') {
        $field_errors['email'] = 'Email address cannot consist of whitespace characters only.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['email'] = 'Please enter a valid email address.';
    }

    // Password validation with whitespace-only check (fixes OFDS-1 requirement)
    $trimmed_password = trim($password);
    if ($password === '') {
        $field_errors['password'] = 'Password is required.';
    } elseif ($trimmed_password === '') {
        $field_errors['password'] = 'Password cannot consist of whitespace characters only.';
    }

    // Only check database if basic validation passed
    if (empty($field_errors)) {
        $user = find_user_by_email($conn, $email);

        if (!$user) {
            $field_errors['email'] = 'No account found with this email.';
            // Increment failed attempt counter
            $_SESSION[$attempt_key]++;
            
            // Lock out after 5 failed attempts
            if ($_SESSION[$attempt_key] >= 5) {
                $_SESSION[$lockout_key] = time() + (15 * 60); // 15 minute lockout
                $field_errors['general'] = 'Too many failed attempts. Account locked for 15 minutes.';
            }
        } elseif ((int)$user['is_active'] !== 1) {
            $field_errors['general'] = 'This account is inactive.';
        } elseif (!in_array($user['role'], ['customer', 'chef', 'staff'], true)) {
            $field_errors['general'] = 'This account role is not allowed to log in.';
        } elseif (!password_verify($password, $user['password'])) {
            $field_errors['password'] = 'Incorrect password.';
            // Increment failed attempt counter
            $_SESSION[$attempt_key]++;
            
            // Lock out after 5 failed attempts
            if ($_SESSION[$attempt_key] >= 5) {
                $_SESSION[$lockout_key] = time() + (15 * 60); // 15 minute lockout
                $field_errors['general'] = 'Too many failed attempts. Account locked for 15 minutes.';
            }
        } else {
            // Successful login - reset rate limiting counters
            unset($_SESSION[$attempt_key]);
            unset($_SESSION[$lockout_key]);
            
            login_user($user);
            redirect_user_by_role($user['role']);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Portal Login</title>
    <link rel="stylesheet" href="../assets/css/login.css">
    <style>
        .field-error {
            display: block;
            color: #c0392b;
            font-size: 13px;
            margin-top: 5px;
            padding-left: 4px;
        }
        .input-group.has-error .input-wrapper input {
            border: 1.5px solid #c0392b;
            background: #242424;
        }
        .alert {
            padding: 12px;
            border-radius: 6px;
            margin-bottom: 20px;
        }
        .alert.success {
            background: #2e7d32;
            color: white;
        }
        .alert.error {
            background: #c0392b;
            color: white;
        }
        .alert.error div {
            margin: 3px 0;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-box">
        <div class="logo">
            <h1>Herald <span>Canteen</span></h1>
            <p>Login to Your Account</p>
        </div>

        <?php if (isset($_GET['registered']) && $_GET['registered'] == '1'): ?>
            <div class="alert success">
                ✅ Registration successful. Please log in.
            </div>
        <?php endif; ?>

        <?php if (!empty($field_errors['general'])): ?>
            <div class="alert error">
                ⚠️ <?php echo htmlspecialchars($field_errors['general']); ?>
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
                        value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>"
                        required
                    >
                </div>
                <?php if (isset($field_errors['email'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['email']); ?></span>
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
                    >
                </div>
                <?php if (isset($field_errors['password'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['password']); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>
</div>
</body>
</html>