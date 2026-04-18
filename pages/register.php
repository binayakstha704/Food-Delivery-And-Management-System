<?php
session_start();
require_once "../config/db.php";
require_once "../includes/functions.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = clean_input($_POST['full_name'] ?? '');
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';

    $errors = validate_registration_data($full_name, $email, $password, $confirm_password);

    if (empty($errors) && email_exists($conn, $email)) {
        $errors[] = 'This email is already registered.';
    }

    if (empty($errors)) {
        if (register_user($conn, $full_name, $email, $password)) {
            header("Location: portal-login.php?registered=1");
            exit;
        } else {
            $errors[] = 'Registration failed. Please try again.';
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
    <link rel="stylesheet" href="../assets/css/login.css">
</head>
<body>
<div class="container">
    <div class="login-box">
        <div class="logo">
            <h1>Herald <span>Canteen</span></h1>
            <p>Create Your Account</p>
        </div>

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <div>⚠️ <?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
            <div class="input-group">
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
            </div>

            <div class="input-group">
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
            </div>

            <div class="input-group">
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
            </div>

            <div class="input-group">
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
            </div>

            <button type="submit" class="login-btn">Register</button>
        </form>

        <div class="register-link">
            <p>Already have an account? <a href="user-login.php">Login here</a></p>
        </div>
    </div>
</div>
</body>
</html>