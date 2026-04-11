<?php
session_start();
require '../config/db.php';

$is_logged_in = isset($_SESSION['user_id']);

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $name    = $_SESSION['full_name'];
} else {
    $user_id = null;
    $name    = 'Guest';
}

if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = (int)$_POST['item_id'];

    $stmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND item_id = ?");
    $stmt->bind_param("ii", $user_id, $item_id);
    $stmt->execute();
    $existing = $stmt->get_result()->fetch_assoc();

    if ($existing) {
        $new_qty = $existing['quantity'] + 1;
        $stmt    = $conn->prepare("UPDATE cart SET quantity = ? WHERE cart_id = ?");
        $stmt->bind_param("ii", $new_qty, $existing['cart_id']);
        $stmt->execute();
    } else {
        $stmt = $conn->prepare("INSERT INTO cart (user_id, item_id, quantity) VALUES (?, ?, 1)");
        $stmt->bind_param("ii", $user_id, $item_id);
        $stmt->execute();
    }
    $added = true;
}

$search         = trim($_GET['search'] ?? '');
$popup_category = null;
$popup_items    = [];

if (isset($_GET['cat_id'])) {
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
    $like              = '%' . $conn->real_escape_string($search) . '%';
    $categories_result = $conn->query("SELECT * FROM categories WHERE name LIKE '$like' AND is_available = 1");
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
</head>
<script>
function requireLogin() {
    alert("You must login first to access this feature.");
    window.location.href = "login.php";
}
</script>
<body>

<div class="layout">

    <div class="sidebar">
        <h3>Herald Canteen</h3>
        <nav>
            <a href="dashboard.php" class="active">Home</a>

            <a href="<?php echo $is_logged_in ? 'cart.php' : 'login.php'; ?>">
                My Cart
                <?php if ($cart_count > 0): ?>
                    <span class="badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo $is_logged_in ? 'orders.php' : 'login.php'; ?>">
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

            <?php if ($is_logged_in): ?>
            <a href="notifications.php" class="notif-wrap">
                <span>🔔</span>
                <?php if ($notif_count > 0): ?>
                    <span class="notif-badge"><?php echo $notif_count; ?></span>
                <?php endif; ?>
            </a>
            <?php endif; ?>
        </div>

        <div class="content">

            <?php if (isset($added)): ?>
                <div class="alert">Item added to cart!</div>
            <?php endif; ?>

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
                        <a href="<?php echo $is_logged_in ? 'dashboard.php?cat_id=' . $cat['category_id'] : 'javascript:requireLogin()'; ?>" class="category-card">
                            <div class="cat-img-wrap">
                                <img src="<?php echo htmlspecialchars($cat['image_url'] ?: '../assets/images/default.jpg'); ?>" alt="<?php echo htmlspecialchars($cat['name']); ?>">
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

        <img class="popup-banner"
            src="<?php echo htmlspecialchars($popup_category['image_url'] ?: '../assets/images/default.jpg'); ?>"
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
                        <form method="POST" action="dashboard.php?cat_id=<?php echo $popup_category['category_id']; ?>">
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

</body>
</html>