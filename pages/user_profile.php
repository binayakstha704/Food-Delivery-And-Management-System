<?php
// user_profile.php — Herald Canteen

session_start();

require_once '../config/originaldb.php'; // your real DB

// 🔐 LOGIN CHECK (your requirement)
if (!isset($_SESSION['user_id'])) {
    echo "<script>
        alert('You must login first to access your profile.');
        window.location.href = 'login.php';
    </script>";
    exit;
}


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

        $new_name  = trim($_POST['name'] ?? '');
        $new_phone = trim($_POST['phone'] ?? '');

        if ($new_name === '') {
            $error = 'Name cannot be empty.';
        } elseif (strlen($new_name) > 100) {
            $error = 'Name is too long (max 100 characters).';
        } elseif ($new_phone !== '' && !preg_match('/^[0-9\+\-\s]{7,20}$/', $new_phone)) {
            $error = 'Please enter a valid phone number.';
        } else {

            // ✅ FIX 1: full_name instead of name
            $stmt = $conn->prepare("UPDATE users SET full_name = ?, phone = ? WHERE user_id = ?");
            $stmt->bind_param("ssi", $new_name, $new_phone ?: null, $user_id);
            $stmt->execute();

            // ✅ FIX 2: session consistency
            $_SESSION['full_name'] = $new_name;

            $success = 'Profile updated successfully!';
        }
    }
}

// ✅ FIX 1: full_name here too
$stmt = $conn->prepare("SELECT user_id, full_name, email, role, phone, created_at FROM users WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();

if (!$user) {
    session_destroy();
    header('Location: index.php');
    exit;
}

// stats
$stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_orders = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COALESCE(SUM(total_amount),0) FROM orders WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$total_spent = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COALESCE(SUM(quantity),0) FROM cart WHERE user_id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_items = $stmt->get_result()->fetch_row()[0];

$stmt = $conn->prepare("SELECT COUNT(*) FROM orders WHERE user_id = ? AND status = 'delivered'");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$delivered = $stmt->get_result()->fetch_row()[0];

// ✅ FIX 6: safe initials
$name_parts = explode(' ', $user['full_name']);
$initials = strtoupper(
    substr($name_parts[0] ?? 'U', 0, 1) .
    substr(end($name_parts) ?: '', 0, 1)
);

// ✅ FIX 7: role mapping corrected
$role_labels = [
    'user'  => 'Student',
    'staff' => 'Staff / Faculty',
    'chef'  => 'Chef'
];

$role_display = $role_labels[$user['role']] ?? ucfirst($user['role']);
$member_since = date('F Y', strtotime($user['created_at']));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Herald Canteen</title>

    <!-- ✅ FIX 3: assets path -->
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="index.php">
        <img src="../assets/images/Canteen.PNG" alt="Herald Canteen" class="navbar-logo">
        <div class="navbar-title">
            Herald Canteen
            <span>Herald College Kathmandu</span>
        </div>
    </a>

    <ul class="navbar-nav">
        <li><a href="dashboard.php">Menu</a></li>
        <li><a href="my_cart.php">🛒 Cart</a></li>
        <li><a href="my_orders.php">My Orders</a></li>
        <li><a href="user_profile.php" class="active">Profile</a></li>
    </ul>
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
                <h2><?= htmlspecialchars($user['full_name']) ?></h2>
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
                    <p class="profile-field-value"><?= htmlspecialchars($user['full_name']) ?></p>
                </div>

                <div>
                    <p class="profile-field-label">Email</p>
                    <p class="profile-field-value"><?= htmlspecialchars($user['email']) ?></p>
                </div>

                <div>
                    <p class="profile-field-label">Phone</p>
                    <p class="profile-field-value">
                        <?= $user['phone'] ? htmlspecialchars($user['phone']) : '<span class="profile-field-empty">Not set</span>' ?>
                    </p>
                </div>

                <div>
                    <p class="profile-field-label">Member Since</p>
                    <p class="profile-field-value"><?= htmlspecialchars($member_since) ?></p>
                </div>
            </div>
        </div>

        <div id="editMode" hidden>
            <form method="POST" action="user_profile.php">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="update_profile" value="1">

                <div class="form-group">
                    <label>Full Name</label>
                    <input type="text" name="name" value="<?= htmlspecialchars($user['full_name']) ?>" maxlength="100">
                </div>

                <div class="form-group">
                    <label>Email</label>
                    <input type="email" value="<?= htmlspecialchars($user['email']) ?>" disabled>
                </div>

                <div class="form-group">
                    <label>Phone</label>
                    <input type="tel" name="phone" value="<?= htmlspecialchars($user['phone'] ?? '') ?>">
                </div>

                <button type="submit">Save</button>
            </form>
        </div>

    </div>

</div>

<script>
const editBtn = document.getElementById('editToggleBtn');
const viewMode = document.getElementById('viewMode');
const editMode = document.getElementById('editMode');

editBtn.addEventListener('click', () => {
    viewMode.hidden = true;
    editMode.hidden = false;
    editBtn.style.display = 'none';
});
</script>

</body>
</html>