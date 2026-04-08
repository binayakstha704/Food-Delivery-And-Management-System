<?php
session_start();

require_once __DIR__ . '/config/db2.php';

// check DB connection
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

        // ✅ FIXED HERE
        if ($user && password_verify($inputPass, $user['password'])) {

            $_SESSION['staff_id'] = $user['id'];
            $_SESSION['staff_name'] = $user['username']; // FIXED
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
<title>Staff Login</title>

<link rel="stylesheet" href="login.css">
<link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;600&display=swap" rel="stylesheet">

</head>
<body>

<div class="container">

    <div class="login-box">
        <h2>Staff Login</h2>
        <p class="subtitle">Secure access only</p>

        <?php if ($error): ?>
            <div class="error"><?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST">
            <div class="input-box">
                <span>👤</span>
                <input type="text" name="username" placeholder="Username" required>
            </div>

            <div class="input-box">
                <span>🔒</span>
                <input type="password" name="password" id="pwField" placeholder="Password" required>
                <button type="button" onclick="togglePw()">👁️</button>
            </div>

            <button class="btn">Login</button>
        </form>

        <div class="demo">
            Demo: <b>staff1 / staff123</b>
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