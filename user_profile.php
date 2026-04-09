<?php
// user_profile.php — Herald Canteen
// Displays logged-in user info. Allows inline editing of name and phone.

session_start();
require_once 'db.php';
require_once 'session_mock.php'; // Sprint 1 demo — remove before Sprint 2

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $error = 'Invalid request token. Please try again.';

    } else {
        $new_name  = trim($_POST['name']  ?? '');
        $new_phone = trim($_POST['phone'] ?? '');

        if ($new_name === '') {
            $error = 'Name cannot be empty.';
        } elseif (strlen($new_name) > 100) {
            $error = 'Name is too long (max 100 characters).';
        } elseif ($new_phone !== '' && !preg_match('/^[0-9\+\-\s]{7,20}$/', $new_phone)) {
            $error = 'Please enter a valid phone number.';
        } else {
            $pdo->prepare("UPDATE users SET name = ?, phone = ? WHERE user_id = ?")
                ->execute([$new_name, $new_phone ?: null, $user_id]);
            $_SESSION['name'] = $new_name;
            $success = 'Profile updated successfully!';
        }
    }
}

$stmt = $pdo->prepare("SELECT user_id, name, email, role, phone, created_at FROM users WHERE user_id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) { session_destroy(); header('Location: index.php'); exit; }

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_orders = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = ?");
$stmt->execute([$user_id]);
$total_spent = (float) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?");
$stmt->execute([$user_id]);
$cart_items = (int) $stmt->fetchColumn();

$stmt = $pdo->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND order_status = 'delivered'");
$stmt->execute([$user_id]);
$delivered = (int) $stmt->fetchColumn();

$name_parts = explode(' ', $user['name']);
$initials   = strtoupper($name_parts[0][0] . (count($name_parts) > 1 ? end($name_parts)[0] : ''));

$role_labels  = ['student' => 'Student', 'staff' => 'Staff / Faculty', 'chef' => 'Chef'];
$role_display = $role_labels[$user['role']] ?? ucfirst($user['role']);
$member_since = date('F Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Herald Canteen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="index.php">
        <img src="Canteen.PNG" alt="Herald Canteen" class="navbar-logo">
        <div class="navbar-title">Herald Canteen <span>Herald College Kathmandu</span></div>
    </a>
    <ul class="navbar-nav">
        <li><a href="menu.php">Menu</a></li>
        <li><a href="my_cart.php">🛒 Cart</a></li>
        <li><a href="my_orders.php">My Orders</a></li>
        <li><a href="user_profile.php" class="active">Profile</a></li>
    </ul>
    <div class="navbar-user">
        <div class="navbar-avatar"><?= htmlspecialchars($initials) ?></div>
        <span class="navbar-username"><?= htmlspecialchars($user['name']) ?></span>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>
</nav>

<div class="page-wrapper">

    <div class="page-heading">
        <h1>My Profile</h1>
        <p>Manage your account details and view your activity.</p>
    </div>

    <?php if ($success): ?>
        <div class="alert alert-success">✔ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
        <div class="alert alert-error">⚠ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-val"><?= $total_orders ?></div>
            <div class="stat-lbl">Total Orders</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $delivered ?></div>
            <div class="stat-lbl">Delivered</div>
        </div>
        <div class="stat-card">
            <div class="stat-val"><?= $cart_items ?></div>
            <div class="stat-lbl">Cart Items</div>
        </div>
        <div class="stat-card">
            <div class="stat-val">Rs <?= number_format($total_spent, 0) ?></div>
            <div class="stat-lbl">Total Spent</div>
        </div>
    </div>

    <div class="card">
        <div class="profile-header">
            <div class="profile-avatar-lg"><?= htmlspecialchars($initials) ?></div>
            <div class="profile-info">
                <h2><?= htmlspecialchars($user['name']) ?></h2>
                <p><?= htmlspecialchars($user['email']) ?></p>
                <span class="role-badge"><?= htmlspecialchars($role_display) ?></span>
            </div>
            <div class="profile-edit-btn">
                <button type="button" class="btn btn-outline btn-sm" id="editToggleBtn">✏️ Edit Profile</button>
            </div>
        </div>

        <div id="viewMode">
            <div class="profile-detail-grid">
                <div>
                    <p class="profile-field-label">Full Name</p>
                    <p class="profile-field-value"><?= htmlspecialchars($user['name']) ?></p>
                </div>
                <div>
                    <p class="profile-field-label">Email</p>
                    <p class="profile-field-value"><?= htmlspecialchars($user['email']) ?></p>
                </div>
                <div>
                    <p class="profile-field-label">Phone</p>
                    <p class="profile-field-value">
                        <?php if ($user['phone']): ?>
                            <?= htmlspecialchars($user['phone']) ?>
                        <?php else: ?>
                            <span class="profile-field-empty">Not set</span>
                        <?php endif; ?>
                    </p>
                </div>
                <div>
                    <p class="profile-field-label">Member Since</p>
                    <p class="profile-field-value"><?= htmlspecialchars($member_since) ?></p>
                </div>
            </div>
        </div>

        <div id="editMode" hidden>
            <form method="POST" action="user_profile.php" novalidate>
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="update_profile" value="1">

                <div class="form-row">
                    <div class="form-group">
                        <label class="form-label" for="name">Full Name</label>
                        <input type="text" id="name" name="name" class="form-control"
                               value="<?= htmlspecialchars($user['name']) ?>" required maxlength="100">
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="email_disp">Email</label>
                        <input type="email" id="email_disp" class="form-control"
                               value="<?= htmlspecialchars($user['email']) ?>" disabled>
                        <p class="form-hint">Email cannot be changed.</p>
                    </div>
                    <div class="form-group">
                        <label class="form-label" for="phone">Phone Number</label>
                        <input type="tel" id="phone" name="phone" class="form-control"
                               value="<?= htmlspecialchars($user['phone'] ?? '') ?>"
                               placeholder="98XXXXXXXX" maxlength="20">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Role</label>
                        <input type="text" class="form-control"
                               value="<?= htmlspecialchars($role_display) ?>" disabled>
                        <p class="form-hint">Role is assigned by admin.</p>
                    </div>
                </div>

                <div class="quick-actions">
                    <button type="submit" class="btn btn-primary">💾 Save Changes</button>
                    <button type="button" class="btn btn-outline" id="cancelEditBtn">Cancel</button>
                </div>
            </form>
        </div>
    </div>

    <div class="card">
        <div class="card-title">Quick Actions</div>
        <div class="quick-actions">
            <a href="my_orders.php" class="btn btn-secondary">📋 View My Orders</a>
            <a href="my_cart.php"   class="btn btn-outline">🛒 Go to Cart</a>
            <a href="menu.php"      class="btn btn-outline">🍱 Browse Menu</a>
        </div>
    </div>

</div>

<script>
var editToggleBtn = document.getElementById('editToggleBtn');
var cancelEditBtn = document.getElementById('cancelEditBtn');
var viewMode      = document.getElementById('viewMode');
var editMode      = document.getElementById('editMode');

editToggleBtn.addEventListener('click', function () {
    viewMode.hidden = true;
    editMode.hidden = false;
    editToggleBtn.style.display = 'none';
});
cancelEditBtn.addEventListener('click', function () {
    editMode.hidden = true;
    viewMode.hidden = false;
    editToggleBtn.style.display = '';
});
<?php if ($error): ?>
viewMode.hidden = true;
editMode.hidden = false;
editToggleBtn.style.display = 'none';
<?php endif; ?>
</script>

</body>
</html>
