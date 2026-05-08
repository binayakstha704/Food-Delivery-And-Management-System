<?php
session_start();
require_once "../config/db.php";
require_once "../includes/functions.php";

$field_errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name        = clean_input($_POST['full_name'] ?? '');
    $email            = clean_input($_POST['email'] ?? '');
    $password         = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    // Full name - ADDED whitespace-only check
    if ($full_name === '') {
        $field_errors['full_name'] = 'Full name is required.';
    } elseif (trim($full_name) === '') {
        $field_errors['full_name'] = 'Full name cannot consist of whitespace characters only.';
    }

    // Email - ADDED whitespace-only check
    if ($email === '') {
        $field_errors['email'] = 'Email address is required.';
    } elseif (trim($email) === '') {
        $field_errors['email'] = 'Email address cannot consist of whitespace characters only.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $field_errors['email'] = 'Please enter a valid email address.';
    }

    // Password — whitespace-only check (bug OFDS-1) - UPDATED to trim before validation
    $trimmed_password = trim($password);
    if ($password === '') {
        $field_errors['password'] = 'Password is required.';
    } elseif ($trimmed_password === '') {
        $field_errors['password'] = 'Password cannot consist of whitespace characters only.';
    } elseif (strlen($password) < 8) {
        $field_errors['password'] = 'Password must be at least 8 characters.';
    }

    // Confirm password - UPDATED to use trimmed values for comparison
    if (!isset($field_errors['password'])) {
        $trimmed_confirm = trim($confirm_password);
        if ($confirm_password === '') {
            $field_errors['confirm_password'] = 'Please confirm your password.';
        } elseif ($trimmed_password !== $trimmed_confirm) {
            $field_errors['confirm_password'] = 'Passwords do not match.';
        }
    }

    if (empty($field_errors) && email_exists($conn, $email)) {
        $field_errors['email'] = 'This email is already registered.';
    }

    if (empty($field_errors)) {
        if (register_user($conn, $full_name, $email, $password)) {
            header("Location: portal-login.php?registered=1");
            exit;
        } else {
            $field_errors['general'] = 'Registration failed. Please try again.';
        }
    }
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Register</title>
    <link rel="stylesheet" href="../assets/css/style.css">
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
            background:  #242424;
        }
    </style>
</head>
<body>
<div class="container">
    <div class="login-box">
        <div class="logo">
            <h1>Herald <span>Canteen</span></h1>
            <p>Create Your Account</p>
        </div>

        <?php if (!empty($field_errors['general'])): ?>
            <div class="alert error">
                ⚠️ <?php echo htmlspecialchars($field_errors['general']); ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">

            <div class="input-group <?php echo isset($field_errors['full_name']) ? 'has-error' : ''; ?>">
                <label>Full Name</label>
                <div class="input-wrapper">
                    <span class="input-icon">👤</span>
                    <input
                        type="text"
                        name="full_name"
                        placeholder="Enter your full name"
                        value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>"
                        required
                    >
                </div>
                <?php if (isset($field_errors['full_name'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['full_name']); ?></span>
                <?php endif; ?>
            </div>

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

            <div class="input-group <?php echo isset($field_errors['confirm_password']) ? 'has-error' : ''; ?>">
                <label>Confirm Password</label>
                <div class="input-wrapper">
                    <span class="input-icon">🔐</span>
                    <input
                        type="password"
                        name="confirm_password"
                        placeholder="Confirm your password"
                        required
                    >
                </div>
                <?php if (isset($field_errors['confirm_password'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['confirm_password']); ?></span>
                <?php endif; ?>
            </div>

            <button type="submit" class="login-btn">Register</button>
        </form>

        <div class="register-link">
            <p>Already have an account? <a href="portal-login.php">Login here</a></p>
        </div>
    </div>
</div>
</body>
</html>