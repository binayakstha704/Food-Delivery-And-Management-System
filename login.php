<?php
session_start();
require 'db.php';

$error = "";
if (isset($_POST["username"]))  {
    $username = trim($_POST["username"]);
    $password = trim($_POST["password"]); 
    $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
    $stmt->execute([$username]);
    $user = $stmt->fetch();
    if (!$user) {
        $error = "Account doesn't exist.";
    } elseif (!password_verify($password, $user["password"])) {
        $error = "Wrong username or password.";
    } else {
        $_SESSION["user_id"] = $user["id"];
        $_SESSION["username"] = $user["username"];
        header("Location: dashboard.php");
        exit();
    }
}
?>
<!doctype html>
<html lang="en">
  <head>
    <meta charset="UTF-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <link
      href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@700&family=Lato:wght@400&display=swap"
      rel="stylesheet"
    />
    <link rel="stylesheet" href="login.css" />
    <title>Swaad Unlimited</title>
  </head>
  <body>
    <img src="logo.png" id="logo" alt="Swaad Unlimited Logo">
    <h1 id="main-title">Swaad Unlimited</h1>
    <p id="second-title">Delicious Food Will Light Up Your Mood</p>

    <div id="login-form">
      <h2 id="welcome-text">Welcome Back</h2>
      <form action="login.php" method="POST">
        <div class="user-input">
          <label for="username">Username</label>
          <input
            type="text"
            id="username"
            name="username"
            placeholder="Enter your username"
            required
          />
        </div>

        <div class="user-input">
          <label for="password">Password</label>
          <input
            type="password"
            id="password"
            name="password"
            placeholder="Enter your password"
            required
          />
        </div>

        <a href="forgotpassword.php" id="forgot-password">Forgot password?</a>
        <button type="submit" id="login-btn">Login</button>

        <div id="divider">
          <hr />
          <span>OR</span>
          <hr />
        </div>

        <a href="register.html">
          <button type="button" id="create-account-btn">
            Create New Account
          </button>
        </a>

        <?php if (!empty($error)): ?>
        <p id="error-message"><?php echo htmlspecialchars($error); ?></p>
        <?php endif; ?>
      </form>
    </div>
  </body>
</html>
