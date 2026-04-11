<?php
// chef_login.php — Herald Canteen
// Authenticates CHEF role only.
// Security: CSRF token, bcrypt verify, rate limiting, session regeneration.

session_start();
require_once 'db.php';

if (isset($_GET['logout'])) {
    session_unset();
    session_destroy();
    header('Location: chef_login.php');
    exit;
}

$already_chef = isset($_SESSION['user_id']) && $_SESSION['role'] === 'chef';

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

function is_rate_limited(PDO $pdo, string $ip): bool {
    $pdo->prepare("DELETE FROM login_attempts WHERE attempted_at < DATE_SUB(NOW(), INTERVAL 10 MINUTE)")->execute();
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM login_attempts WHERE ip_address = ?");
    $stmt->execute([$ip]);
    return (int) $stmt->fetchColumn() >= 5;
}

function log_attempt(PDO $pdo, string $ip): void {
    $pdo->prepare("INSERT INTO login_attempts (ip_address) VALUES (?)")->execute([$ip]);
}

$error         = '';
$login_success = $already_chef;
$chef_name     = $_SESSION['name'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && !$already_chef) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request. Please refresh the page and try again.';

    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

        if (is_rate_limited($pdo, $ip)) {
            $error = 'Too many failed attempts. Please wait 10 minutes before trying again.';

        } else {
            $email    = trim($_POST['email']    ?? '');
            $password = trim($_POST['password'] ?? '');

            if ($email === '' || $password === '') {
                $error = 'Both email and password are required.';

            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Please enter a valid email address.';

            } else {
                $stmt = $pdo->prepare(
                    "SELECT user_id, name, email, password, role
                     FROM users WHERE email = ? AND role = 'chef' LIMIT 1"
                );
                $stmt->execute([$email]);
                $user = $stmt->fetch();

                if ($user && password_verify($password, $user['password'])) {
                    session_regenerate_id(true);
                    $_SESSION['user_id']    = $user['user_id'];
                    $_SESSION['name']       = $user['name'];
                    $_SESSION['email']      = $user['email'];
                    $_SESSION['role']       = 'chef';
                    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
                    $login_success          = true;
                    $chef_name              = $user['name'];

                } else {
                    log_attempt($pdo, $ip);
                    $error = 'Invalid email or password. Only chef accounts can log in here.';
                }
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
    <title>Chef Login — Herald Canteen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="login-page">

    <?php if ($login_success): ?>

        <div class="login-success-box">
            <div class="success-icon">👨‍🍳</div>
            <h2 class="success-title">Welcome, <?= htmlspecialchars($chef_name) ?>!</h2>
            <p class="success-subtitle">Authenticated as Chef ✓</p>

            <div class="checklist-box">
                <p class="checklist-label">Sprint 1 · Authentication Checklist</p>
                <div class="checklist-item">✅ &nbsp;CSRF token validated</div>
                <div class="checklist-item">✅ &nbsp;Bcrypt password verified</div>
                <div class="checklist-item">✅ &nbsp;Rate limiting active (5 attempts / 10 min)</div>
                <div class="checklist-item">✅ &nbsp;Session regenerated on login</div>
                <div class="checklist-item">✅ &nbsp;Chef role confirmed from database</div>
                <div class="checklist-item">✅ &nbsp;Non-chef accounts blocked</div>
                <div class="checklist-item-pending">⏳ &nbsp;Redirect → chef_control.php will connect in Sprint 2</div>
            </div>

            <p class="success-note">
                Chef dashboard (chef_control.php) is your teammate's module.<br>
                It will be linked during final Sprint 2 integration.
            </p>

            <div class="success-actions">
                <a href="index.php" class="btn btn-primary btn-full">← Back to Main Page</a>
                <a href="chef_login.php?logout=1" class="btn btn-ghost">Logout Chef Session</a>
            </div>
        </div>

    <?php else: ?>

        <div class="login-box">

            <div class="logo-wrap">
                <img src="Canteen.PNG" alt="Herald College Logo">
                <h2>Herald Canteen</h2>
                <p>Chef Portal</p>
            </div>

            <?php if ($error): ?>
                <div class="alert alert-error">
                    <span>&#9888;</span>
                    <span><?= htmlspecialchars($error) ?></span>
                </div>
            <?php endif; ?>

            <form method="POST" action="chef_login.php" novalidate id="loginForm">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">

                <div class="form-group">
                    <label class="form-label" for="email">Chef Email</label>
                    <input
                        type="email"
                        id="email"
                        name="email"
                        class="form-control <?= $error ? 'error' : '' ?>"
                        placeholder="chef@heraldcanteen.com"
                        value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                        autocomplete="username"
                        required
                    >
                </div>

                <div class="form-group">
                    <label class="form-label" for="password">Password</label>
                    <div class="pwd-wrapper">
                        <input
                            type="password"
                            id="password"
                            name="password"
                            class="form-control <?= $error ? 'error' : '' ?>"
                            placeholder="••••••••"
                            autocomplete="current-password"
                            required
                        >
                        <button type="button" class="pwd-toggle" id="togglePwd" aria-label="Toggle password">👁</button>
                    </div>
                </div>

                <button type="submit" class="btn btn-primary btn-full">
                    <span>🍳</span> Sign In as Chef
                </button>
            </form>

            <p class="login-footer-note">
                Not a chef? <a href="index.php">Go back to main page</a>
            </p>

            <p class="login-security-note">
                chef accounts only.<br>
                5 failed attempts triggers a 10-minute lockout.
            </p>

        </div>

    <?php endif; ?>

</div>

<script>
document.getElementById('togglePwd') && document.getElementById('togglePwd').addEventListener('click', function () {
    var pwd = document.getElementById('password');
    pwd.type = pwd.type === 'password' ? 'text' : 'password';
    this.textContent = pwd.type === 'password' ? '👁' : '🙈';
});
document.getElementById('loginForm') && document.getElementById('loginForm').addEventListener('submit', function (e) {
    var email = document.getElementById('email').value.trim();
    var pwd   = document.getElementById('password').value.trim();
    if (!email || !pwd) { e.preventDefault(); alert('Please fill in both fields.'); }
});
</script>

</body>
</html>
