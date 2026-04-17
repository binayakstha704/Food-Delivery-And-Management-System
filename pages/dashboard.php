<?php
session_start();
require '../config/originaldb.php';

$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
} else {
    $user_id = null;
    $name    = 'Guest';
}

$added_item_name = null;

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = (int)$_POST['item_id'];

    // Fetch item name for the notification
    $stmt = $conn->prepare("SELECT name FROM menu_items WHERE item_id = ?");
    $stmt->bind_param("i", $item_id);
    $stmt->execute();
    $item_row  = $stmt->get_result()->fetch_assoc();
    $item_name = $item_row['name'] ?? 'Item';

    $stmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND item_id = ?");
    $stmt->bind_param("ii", $user_id, $item_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $new_qty = $existing['quantity'] + 1;
        $stmt = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
        $stmt->bind_param("ii", $new_qty, $existing['cart_id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, item_id, quantity) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $user_id, $item_id);
        $stmt->execute();
    }

    // Redirect to avoid resubmission on refresh
    $cat_id_redirect = (int)($_GET['cat_id'] ?? 0);
    $redirect_url = 'dashboard.php?cat_id=' . $cat_id_redirect . '&added=1&item_name=' . urlencode($item_name);
    header('Location: ' . $redirect_url);
    exit;
}

// Pick up toast data from redirect
if (isset($_GET['added']) && $_GET['added'] === '1' && isset($_GET['item_name'])) {
    $added_item_name = htmlspecialchars($_GET['item_name']);
}

$search         = trim($_GET['search'] ?? '');
$popup_category = null;
$popup_items    = [];

if (isset($_GET['cat_id']) && (int)$_GET['cat_id'] > 0) {
    $cat_id = (int)$_GET['cat_id'];

    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $popup_category = $stmt->get_result()->fetch_assoc();

    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category_id = ? AND is_available = 1");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $res = $stmt->get_result();
    while ($row = $res->fetch_assoc()) {
        $popup_items[] = $row;
    }
}

if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE name LIKE ? AND is_available = 1");
    $like = '%' . $search . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $categories_result = $stmt->get_result();
} else {
    $categories_result = $conn->query("SELECT * FROM categories WHERE is_available = 1");
}

$cart_count = 0;
if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

$notif_count = 0;
if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT COUNT(*) as count FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $notif_count = $stmt->get_result()->fetch_assoc()['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>Dashboard</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">
    
    <script>
    function requireLogin() {
        alert("You must login first to access this feature.");
        window.location.href = "login.php";
    }
    </script>
</head>
<body>

<div class="layout">

    <div class="sidebar">
        <div class="navbar-title">
    Herald Canteen
    <span>Herald College Kathmandu</span>
</div>
        <nav>
            <a href="dashboard.php" class="active">Home</a>

            <a href="<?php echo $is_logged_in ? 'my_cart.php' : 'login.php'; ?>">
                My Cart
                <?php if ($cart_count > 0): ?>
                    <span class="badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo $is_logged_in ? 'my_orders.php' : 'login.php'; ?>">
                My Orders
            </a>

            <?php if ($is_logged_in): ?>
                <a href="logout.php">Logout</a>
            <?php else: ?>
                <a href="login.php">Login / Signup</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="main">

        <div class="topbar">
    <form method="GET" action="dashboard.php" class="search-form">
        <input type="text" name="search" placeholder="Search categories..."
            value="<?php echo htmlspecialchars($search); ?>">
        <button type="submit">Search</button>
    </form>

    <div style="display:flex; align-items:center; gap:18px;">

        <!-- My Profile -->
        <?php if ($is_logged_in): ?>
            <a href="user_profile.php" class="profile-link">
                👤 My Profile
            </a>
        <?php else: ?>
            <a href="login.php" class="profile-link">
                👤 My Profile
            </a>
        <?php endif; ?>

        <!-- Notification -->
        <?php if ($is_logged_in): ?>
        <a href="notifications.php" class="notif-wrap">
            <span>🔔</span>
            <?php if ($notif_count > 0): ?>
                <span class="notif-badge"><?php echo $notif_count; ?></span>
            <?php endif; ?>
        </a>
        <?php endif; ?>

    </div>
</div>

        <div class="content">

            <div class="section-title">
                <h2>Our Special</h2>
                <p>
                    <?php if ($is_logged_in): ?>
                        Hello <?php echo htmlspecialchars($name); ?>, what are you craving today?
                    <?php else: ?>
                        Browse our menu — login to order!
                    <?php endif; ?>
                </p>
            </div>

            <div class="category-grid">
                <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                    <?php while ($cat = $categories_result->fetch_assoc()): ?>
                        <?php if ($is_logged_in): ?>
                            <a href="dashboard.php?cat_id=<?php echo $cat['category_id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-card">
                        <?php else: ?>
                            <a href="javascript:void(0)" onclick="requireLogin()" class="category-card">
                        <?php endif; ?>
                            <div class="cat-img-wrap">
                                <img src="<?php echo '../' . htmlspecialchars($cat['image_url'] ?: 'assets/images/default.jpg'); ?>"> alt="<?php echo htmlspecialchars($cat['name']); ?>">
                            </div>
                            <div class="cat-label">
                                <h4><?php echo htmlspecialchars($cat['name']); ?></h4>
                                <p><?php echo htmlspecialchars($cat['description']); ?></p>
                            </div>
                        </a>
                    <?php endwhile; ?>
                <?php else: ?>
                    <p class="no-result">No categories found.</p>
                <?php endif; ?>
            </div>

        </div>
    </div>
</div>

<?php if ($popup_category): ?>
<div class="popup-overlay">
    <div class="popup">

        <a href="dashboard.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" class="close-btn">✕</a>

        <img class="popup-banner" src="<?php echo '../' . htmlspecialchars($popup_category['image_url'] ?: 'assets/images/default.jpg'); ?>"
            alt="<?php echo htmlspecialchars($popup_category['name']); ?>">

        <div class="popup-header-text">
            <h2><?php echo htmlspecialchars($popup_category['name']); ?></h2>
            <p><?php echo htmlspecialchars($popup_category['description']); ?></p>
        </div>

        <div class="popup-items">
            <h3>Choose Your Variety</h3>
            <?php if (count($popup_items) > 0): ?>
                <?php foreach ($popup_items as $item): ?>
                    <div class="popup-item">
                        <div class="popup-item-info">
                            <h4><?php echo htmlspecialchars($item['name']); ?></h4>
                            <p><?php echo htmlspecialchars($item['description']); ?></p>
                            <span class="price">Rs. <?php echo number_format($item['price'], 2); ?></span>
                        </div>

                        <?php if ($is_logged_in): ?>
                        <form method="POST" action="dashboard.php?cat_id=<?php echo $popup_category['category_id']; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                            <button type="submit" class="add-btn">Add to Bag</button>
                        </form>
                        <?php else: ?>
                            <a href="login.php" class="add-btn">Add to Bag</a>
                        <?php endif; ?>

                    </div>
                <?php endforeach; ?>
            <?php else: ?>
                <p class="no-result">No items available.</p>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php endif; ?>

<?php if ($added_item_name): ?>
<div class="toast" id="cartToast">
    <div class="toast-icon">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="9" cy="9" r="9" fill="#4db848"/>
            <path d="M5 9.5L7.5 12L13 6.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="toast-body">
        <span class="toast-title"><?php echo $added_item_name; ?></span>
        <span class="toast-sub">Added to your bag successfully</span>
    </div>
    <button class="toast-close" onclick="dismissToast()">✕</button>
</div>
<script>
    const toast = document.getElementById('cartToast');
    setTimeout(() => {
        if (toast) toast.classList.add('toast-hide');
    }, 9500);
    function dismissToast() {
        if (toast) toast.classList.add('toast-hide');
    }
</script>
<?php endif; ?>

</body>
</html>