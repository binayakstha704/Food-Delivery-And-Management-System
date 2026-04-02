<?php
session_start();

// ── If already logged in, redirect to dashboard
if (isset($_SESSION['admin_logged_in']) && $_SESSION['admin_logged_in'] === true) {
    header('Location: admin_dashboard.php');
    exit();
}

// ── Security helpers
function sanitize_input($val) {
    $val = trim($val);
    $val = stripslashes($val);
    $val = htmlspecialchars($val, ENT_QUOTES, 'UTF-8');
    return $val;
}

function has_sql_injection($val) {
    $patterns = "/('|--|;|\/\*|\*\/|xp_|UNION|SELECT|INSERT|DROP|DELETE|UPDATE|EXEC|CAST|CONVERT|ALTER|CREATE|TRUNCATE)/i";
    return preg_match($patterns, $val);
}

function has_xss($val) {
    $patterns = "/<script|onerror|onload|javascript:|<img|<svg|alert\(/i";
    return preg_match($patterns, $val);
}

// ── Rate limiting via session
if (!isset($_SESSION['login_attempts']))  $_SESSION['login_attempts'] = 0;
if (!isset($_SESSION['locked_until']))    $_SESSION['locked_until']   = 0;

$error_msg   = '';
$success_msg = '';
$warn_msg    = '';

// ── Handle POST submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Check lockout
    if (time() < $_SESSION['locked_until']) {
        $remaining = $_SESSION['locked_until'] - time();
        $warn_msg  = "Too many failed attempts. Try again in {$remaining} seconds.";
    } else {

        $raw_user = $_POST['adminUser'] ?? '';
        $raw_pass = $_POST['adminPass'] ?? '';

        // Required fields
        if (empty(trim($raw_user)) || empty(trim($raw_pass))) {
            $error_msg = 'Username and password are required.';

        // Injection checks
        } elseif (has_sql_injection($raw_user) || has_sql_injection($raw_pass)) {
            $error_msg = 'Suspicious input detected. This attempt has been logged.';
            error_log('[SECURITY] SQL injection attempt at ' . date('Y-m-d H:i:s'));

        } elseif (has_xss($raw_user) || has_xss($raw_pass)) {
            $error_msg = 'Invalid input detected. This attempt has been logged.';
            error_log('[SECURITY] XSS attempt at ' . date('Y-m-d H:i:s'));

        } else {
            $username = sanitize_input($raw_user);
            $password = sanitize_input($raw_pass);

            // Length validation
            if (strlen($username) < 3) {
                $error_msg = 'Username must be at least 3 characters.';
            } elseif (strlen($password) < 6) {
                $error_msg = 'Password must be at least 6 characters.';
            } else {

                $DEMO_USER = 'admin';
                $DEMO_PASS = 'admin123';
                $valid = ($username === $DEMO_USER && $password === $DEMO_PASS);

                if ($valid) {
                    // Reset attempts and create session
                    $_SESSION['login_attempts']  = 0;
                    $_SESSION['locked_until']    = 0;
                    $_SESSION['admin_logged_in'] = true;
                    $_SESSION['admin_user']      = $username;
                    $_SESSION['login_time']      = time();

                    // Regenerate session ID to prevent fixation
                    session_regenerate_id(true);

                    header('Location: admin_dashboard.php');
                    exit();
                } else {
                    $_SESSION['login_attempts']++;
                    $remaining_attempts = 5 - $_SESSION['login_attempts'];

                    if ($_SESSION['login_attempts'] >= 5) {
                        $_SESSION['locked_until'] = time() + 30; // 30s demo lockout
                        $warn_msg = 'Too many failed attempts. Account locked for 30 seconds.';
                        error_log('[SECURITY] Max login attempts reached at ' . date('Y-m-d H:i:s'));
                    } else {
                        $error_msg = "Invalid username or password. {$remaining_attempts} attempt(s) remaining.";
                    }
                }
            }
        }
    }
}

$attempts_left = max(0, 5 - $_SESSION['login_attempts']);
$is_locked     = time() < $_SESSION['locked_until'];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Admin Login — Swaad Unlimited</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    :root {
      --deep-brown:   #2C1503;
      --mid-brown:    #7B3F00;
      --warm-brown:   #A0522D;
      --near-black:   #1A0A00;
      --burnt-orange: #CC5500;
      --bright-orange:#FF6B1A;
      --peach:        #FFD5B0;
      --peach-light:  #FFF0E0;
      --white:        #FFFFFF;
      --error:        #CC0000;
      --success:      #2e7d32;
    }

    body {
      min-height: 100vh;
      font-family: 'Poppins', sans-serif;
      background: var(--peach-light);
      display: flex;
      align-items: center;
      justify-content: center;
      overflow: hidden;
      position: relative;
    }

    body::before {
      content: '';
      position: fixed;
      inset: 0;
      background: linear-gradient(135deg, var(--peach) 50%, var(--deep-brown) 50%);
      z-index: 0;
    }

    .logo-wrap {
      position: fixed;
      top: 18px; right: 24px;
      width: 72px; height: 72px;
      background: var(--white);
      border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      box-shadow: 0 4px 16px rgba(0,0,0,0.18);
      z-index: 10;
      overflow: hidden;
    }
    .logo-wrap img {
      width: 72px; height: 72px;
      object-fit: cover;
      border-radius: 50%;
    }

    .brand-header {
      position: fixed;
      top: 0; left: 0; right: 0;
      text-align: center;
      padding: 22px 0 0;
      z-index: 5;
      pointer-events: none;
    }
    .brand-header h1 {
      font-family: 'Playfair Display', serif;
      font-size: 2rem;
      color: var(--burnt-orange);
      letter-spacing: 0.5px;
    }
    .brand-header p {
      font-size: 0.82rem;
      color: var(--mid-brown);
      margin-top: 2px;
      font-weight: 300;
    }

    .card {
      position: relative;
      z-index: 2;
      background: var(--white);
      border-radius: 16px;
      padding: 40px 44px 36px;
      width: 100%;
      max-width: 420px;
      box-shadow: 0 12px 48px rgba(44,21,3,0.22);
      margin-top: 60px;
      animation: slideUp 0.5s cubic-bezier(.23,1,.32,1) both;
    }

    @keyframes slideUp {
      from { opacity:0; transform: translateY(30px); }
      to   { opacity:1; transform: translateY(0); }
    }

    .card-title {
      font-family: 'Playfair Display', serif;
      font-size: 1.6rem;
      color: var(--near-black);
      text-align: center;
      margin-bottom: 6px;
    }
    .card-subtitle {
      text-align: center;
      font-size: 0.78rem;
      color: var(--warm-brown);
      margin-bottom: 28px;
      display: flex;
      align-items: center;
      justify-content: center;
      gap: 6px;
    }
    .card-subtitle span {
      background: var(--burnt-orange);
      color: var(--white);
      font-size: 0.65rem;
      padding: 2px 8px;
      border-radius: 20px;
      font-weight: 600;
      letter-spacing: 0.5px;
    }

    .form-group { margin-bottom: 18px; }
    .form-group label {
      display: block;
      font-size: 0.82rem;
      font-weight: 500;
      color: var(--mid-brown);
      margin-bottom: 6px;
    }

    .input-wrap { position: relative; }
    .input-wrap .icon {
      position: absolute;
      left: 12px; top: 50%;
      transform: translateY(-50%);
      font-size: 1rem;
      pointer-events: none;
      color: var(--warm-brown);
    }
    .input-wrap input {
      width: 100%;
      padding: 10px 40px 10px 38px;
      border: 1.5px solid #e2c9b0;
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.88rem;
      color: var(--near-black);
      background: #fffaf6;
      outline: none;
      transition: border-color 0.2s, box-shadow 0.2s;
    }
    .input-wrap input:focus {
      border-color: var(--burnt-orange);
      box-shadow: 0 0 0 3px rgba(204,85,0,0.10);
      background: #fff;
    }
    .input-wrap input.error-input { border-color: var(--error); }

    .toggle-pw {
      position: absolute;
      right: 12px; top: 50%;
      transform: translateY(-50%);
      background: none; border: none;
      cursor: pointer; font-size: 1rem;
      color: var(--warm-brown); padding: 0;
    }

    .forgot { text-align: right; margin-top: -10px; margin-bottom: 18px; }
    .forgot a {
      font-size: 0.76rem;
      color: var(--burnt-orange);
      text-decoration: none;
      font-weight: 500;
    }
    .forgot a:hover { text-decoration: underline; }

    .btn-login {
      width: 100%; padding: 12px;
      background: var(--burnt-orange);
      color: var(--white); border: none;
      border-radius: 8px;
      font-family: 'Poppins', sans-serif;
      font-size: 0.95rem; font-weight: 600;
      cursor: pointer; letter-spacing: 0.3px;
      transition: background 0.2s, transform 0.1s, box-shadow 0.2s;
      box-shadow: 0 4px 14px rgba(204,85,0,0.25);
    }
    .btn-login:hover { background: #b34a00; box-shadow: 0 6px 18px rgba(204,85,0,0.35); }
    .btn-login:active { transform: scale(0.98); }
    .btn-login:disabled { background: #ccc; cursor: not-allowed; box-shadow: none; }

    .msg {
      text-align: center;
      font-size: 0.8rem;
      font-weight: 600;
      margin-top: 12px;
      border-radius: 6px;
      padding: 8px 10px;
    }
    .msg.error   { color: var(--error);   background: #fff0f0; border: 1px solid #ffcccc; }
    .msg.success { color: var(--success); background: #f0fff4; border: 1px solid #b2dfdb; }
    .msg.warn    { color: #8a6000;        background: #fffbe6; border: 1px solid #ffe082; }

    .security-note {
      display: flex; align-items: center; justify-content: center;
      gap: 6px; margin-top: 18px;
      font-size: 0.72rem; color: var(--warm-brown);
    }

    .spinner {
      display: inline-block;
      width: 16px; height: 16px;
      border: 2px solid rgba(255,255,255,0.4);
      border-top-color: #fff;
      border-radius: 50%;
      animation: spin 0.7s linear infinite;
      vertical-align: middle;
      margin-right: 6px;
    }
    @keyframes spin { to { transform: rotate(360deg); } }
  </style>
</head>
<body>

  <div class="logo-wrap">
    <img src="logo.jfif" alt="Swaad Unlimited Logo"
         onerror="this.style.display='none';this.parentElement.innerHTML='🍽️'"/>
  </div>

  <div class="brand-header">
    <h1>Swaad Unlimited</h1>
    <p>Delicious Food Will Light Up Your Mood</p>
  </div>

  <div class="card" role="main">
    <h2 class="card-title">Admin Portal</h2>
    <div class="card-subtitle">
    </div>

    <form method="POST" action="admin_login.php" novalidate>
      <!-- CSRF token (basic protection) -->
      <?php
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
        }
      ?>
      <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"/>

      <div class="form-group">
        <label for="adminUser">Username</label>
        <div class="input-wrap">
          <span class="icon"></span>
          <input type="text" id="adminUser" name="adminUser"
                 placeholder="Enter admin username"
                 autocomplete="username" maxlength="60"
                 spellcheck="false" autocorrect="off" autocapitalize="off"
                 value="<?php echo isset($_POST['adminUser']) ? htmlspecialchars($_POST['adminUser'], ENT_QUOTES, 'UTF-8') : ''; ?>"
                 <?php echo $is_locked ? 'disabled' : ''; ?>/>
        </div>
      </div>

      <div class="form-group">
        <label for="adminPass">Password</label>
        <div class="input-wrap">
          <span class="icon"></span>
          <input type="password" id="adminPass" name="adminPass"
                 placeholder="Enter your password"
                 autocomplete="current-password" maxlength="128"
                 <?php echo $is_locked ? 'disabled' : ''; ?>/>
          <button class="toggle-pw" type="button" id="togglePw" aria-label="Toggle password">👁️</button>
        </div>
      </div>
      <button class="btn-login" type="submit" id="loginBtn"
              <?php echo $is_locked ? 'disabled' : ''; ?>>
        Login
      </button>
    </form>

    <?php if (!empty($error_msg)): ?>
      <div class="msg error"><?php echo $error_msg; ?></div>
    <?php elseif (!empty($warn_msg)): ?>
      <div class="msg warn"><?php echo $warn_msg; ?></div>
    <?php elseif (!empty($success_msg)): ?>
      <div class="msg success"><?php echo $success_msg; ?></div>
    <?php endif; ?>

    <?php if ($is_locked): ?>
      <div class="msg warn" id="lockMsg">
        🔒 Account locked. Refreshing in <span id="lockCount"><?php echo $_SESSION['locked_until'] - time(); ?></span>s...
      </div>
    <?php endif; ?>

  <script>
    // Toggle password visibility
    document.getElementById('togglePw').addEventListener('click', function () {
      const inp = document.getElementById('adminPass');
      const hidden = inp.type === 'password';
      inp.type = hidden ? 'text' : 'password';
      this.textContent = hidden ? '🙈' : '👁️';
    });

    // Client-side lockout countdown (PHP already enforces it server-side)
    const lockEl = document.getElementById('lockCount');
    if (lockEl) {
      let secs = parseInt(lockEl.textContent);
      const t = setInterval(() => {
        secs--;
        if (secs <= 0) { clearInterval(t); location.reload(); }
        else lockEl.textContent = secs;
      }, 1000);
    }

    // Show spinner on submit
    const form = document.querySelector('form');
    const btn  = document.getElementById('loginBtn');
    if (form && btn) {
      form.addEventListener('submit', function () {
        btn.innerHTML = '<span class="spinner"></span>Verifying...';
        btn.disabled = true;
      });
    }
  </script>
</body>
</html>
