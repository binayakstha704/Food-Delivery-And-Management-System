<?php
session_start();

$msg = $_GET['msg'] ?? '';
$flash_map = [
    'login_required' => ['type' => 'error',   'text' => 'Please log in to access that page.'],
    'logged_out'     => ['type' => 'success',  'text' => 'You have been logged out successfully.'],
];
$flash = $flash_map[$msg] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herald Canteen — College Food Ordering System</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<div class="landing-page">
<div class="landing-inner">

    <div class="landing-hero">
        <img src="Canteen.PNG" alt="Herald Canteen" class="landing-logo">
        <p class="landing-college">Herald College Kathmandu</p>
        <h1 class="landing-title">Herald <span>Canteen</span></h1>
        <p class="landing-tagline">College Food Order &amp; Management System</p>
    </div>

    <?php if ($flash): ?>
        <div class="alert alert-<?= $flash['type'] ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>

    <div class="sprint-banner">
        <span class="sprint-badge">Sprint 1</span>
        <span>Demo mode.</span>
    </div>

    <p class="section-title">My Pages &nbsp;·&nbsp; SCRUM SR</p>

    <div class="pages-grid">
        <a href="chef_login.php" class="page-card">
            <div class="page-card-icon">👨‍🍳</div>
            <div class="page-card-name">Chef Login</div>
            <div class="page-card-desc"></div>
            <div class="page-card-file">chef_login.php</div>
        </a>
        <a href="user_profile.php" class="page-card">
            <div class="page-card-icon">👤</div>
            <div class="page-card-name">User Profile</div>
            <div class="page-card-desc">View &amp; edit profile info, live stats from database</div>
            <div class="page-card-file">user_profile.php</div>
        </a>
        <a href="my_cart.php" class="page-card">
            <div class="page-card-icon">🛒</div>
            <div class="page-card-name">My Cart</div>
            <div class="page-card-desc">DB-driven cart, qty controls</div>
            <div class="page-card-file">my_cart.php</div>
        </a>
        <a href="my_orders.php" class="page-card">
            <div class="page-card-icon">📋</div>
            <div class="page-card-name">My Orders</div>
            <div class="page-card-desc">4-status order tracker, filter tabs &amp; live DB data</div>
            <div class="page-card-file">my_orders.php</div>
        </a>
    </div>

    <p class="section-title">Login Portals</p>

    <div class="login-grid">
        <a href="user_login.php" class="login-card">
            <div class="login-card-icon">🎓</div>
            <div class="login-card-name">Student / Staff Login</div>
            <div class="login-card-desc"></div>
        </a>
        <a href="chef_login.php" class="login-card">
            <div class="login-card-icon">👨‍🍳</div>
            <div class="login-card-name">Chef Login</div>
            <div class="login-card-desc"></div>
        </a>
    </div>

    <div class="college-footer">
        <img src="Canteen.PNG" alt="">
        <span>Herald Canteen &nbsp;·&nbsp; Herald College Kathmandu</span>
    </div>

</div>
</div>

</body>
</html>
