<?php
session_start();

require_once __DIR__ . '/config/db2.php';

if (!isset($pdo)) {
    die("❌ Database not connected. Fix db.php");
}

$error = "";

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $inputUser = trim($_POST['username'] ?? '');
    $inputPass = $_POST['password'] ?? '';

    if (empty($inputUser) || empty($inputPass)) {
        $error = "Please enter both username and password.";
    } else {
        $stmt = $pdo->prepare("SELECT * FROM userss WHERE username = ? AND role = 'staff'");
        $stmt->execute([$inputUser]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($user && password_verify($inputPass, $user['password'])) {
            $_SESSION['staff_id']   = $user['id'];
            $_SESSION['staff_name'] = $user['username'];
            $_SESSION['staff_role'] = $user['role'];
            header("Location: staff_panel.php");
            exit;
        } else {
            $error = "Invalid username or password.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Herald Canteen — Staff Login</title>
<link rel="stylesheet" href="staaff.css">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700;900&family=Outfit:wght@300;400;500;600&display=swap" rel="stylesheet">
</head>
<body class="login-body">
  


<div class="orb orb-1"></div>
<div class="orb orb-2"></div>
<div class="orb orb-3"></div>

<div class="container">
    <div class="login-box">

        <!-- Corner decorations -->
        <div class="corner corner-tl"></div>
        <div class="corner corner-br"></div>

        <!-- Logo mark -->
        <!-- Replace this: -->


<!-- With this: -->
<div class="logo-mark">
    <img src="Canteen.PNG" alt="Herald Canteen" style="height:48px;width:auto;border-radius:8px;">
</div>

        <!-- Brand -->
        <div class="brand-name">Herald Canteen</div>
        <div class="brand-tagline">Staff Portal</div>

        <div class="divider"><span>Secure Access</span></div>

        <?php if ($error): ?>
            <div class="error">⚠ <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-box">
                <span class="icon">👤</span>
                <input type="text" name="username" placeholder="Username" required>
            </div>

            <div class="input-box">
                <span class="icon">🔒</span>
                <input type="password" name="password" id="pwField" placeholder="Password" required>
                <button type="button" class="toggle-pw" onclick="togglePw()">👁️</button>
            </div>

            <button class="btn" type="submit"><span>Sign In</span></button>
        </form>

        <div class="demo">
            Demo: <b>staff1</b> / <b>staff123</b>
        </div>
    </div>
</div>

<script>
function togglePw() {
    const pw = document.getElementById('pwField');
    pw.type = pw.type === 'password' ? 'text' : 'password';
}
</script>
</body>
</html>