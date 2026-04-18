<?php
session_start();
require_once "../config/db.php";
require_once "../includes/functions.php";

$errors = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = clean_input($_POST['email'] ?? '');
    $password = $_POST['password'] ?? '';

    $errors = validate_login_data($email, $password);

    if (empty($errors)) {
        $user = find_user_by_email($conn, $email);

        if (!$user) {
            $errors[] = 'No account found with this email.';
        } elseif ((int)$user['is_active'] !== 1) {
            $errors[] = 'This account is inactive.';
        } elseif (!in_array($user['role'], ['customer', 'chef', 'staff'], true)) {
            $errors[] = 'This account role is not allowed to log in.';
        } elseif (!password_verify($password, $user['password'])) {
            $errors[] = 'Incorrect password.';
        } else {
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

        <?php if (!empty($errors)): ?>
            <div class="alert error">
                <?php foreach ($errors as $error): ?>
                    <div>⚠️ <?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            </div>
        <?php endif; ?>

        <form method="POST" class="login-form">
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

            <button type="submit" class="login-btn">Login</button>
        </form>

        <div class="register-link">
            <p>Don't have an account? <a href="register.php">Create Account</a></p>
        </div>
    </div>
</div>
</body>
</html>