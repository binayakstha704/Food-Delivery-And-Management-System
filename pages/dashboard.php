<?php
require_once "../includes/auth.php";
configure_secure_session();
session_start();
session_security_check();
require '../config/db.php';

// Helper function for sanitization
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

$is_logged_in = isset($_SESSION['user_id']);
$field_errors = []; // For inline validation errors

if ($is_logged_in) {
    $user_id = $_SESSION['user_id'];
    $name = $_SESSION['full_name'] ?? $_SESSION['username'] ?? 'User';
} else {
    $user_id = null;
    $name    = 'Guest';
}

$added_item_name = null;

// Handle Add to Cart with validation
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {
    $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
    
    // Validate item_id
    if ($item_id === false || $item_id <= 0) {
        $field_errors['general'] = 'Invalid item selected.';
    } else {
        // Fetch item name for the notification (using prepared statement)
        $stmt = $conn->prepare("SELECT name, price FROM menu_items WHERE item_id = ? AND is_available = 1");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item_data = $stmt->get_result()->fetch_assoc();
        
        if (!$item_data) {
            $field_errors['general'] = 'Item not available.';
        } else {
            $item_name = $item_data['name'];
            $item_price = $item_data['price'];

            // Check if item already in cart
            $stmt = $conn->prepare("SELECT cart_id, quantity FROM cart WHERE user_id = ? AND item_id = ?");
            $stmt->bind_param("ii", $user_id, $item_id);
            $stmt->execute();
            $existing = $stmt->get_result()->fetch_assoc();

            if ($existing) {
                $new_qty = $existing['quantity'] + 1;
                $new_total = $new_qty * $item_price;
                $stmt = $conn->prepare("UPDATE cart SET quantity = ?, total_price = ? WHERE cart_id = ?");
                $stmt->bind_param("idi", $new_qty, $new_total, $existing['cart_id']);
                $stmt->execute();
            } else {
                $total_price = $item_price;
                $stmt = $conn->prepare("INSERT INTO cart (user_id, item_id, quantity, total_price) VALUES (?, ?, 1, ?)");
                $stmt->bind_param("iid", $user_id, $item_id, $total_price);
                $stmt->execute();
            }

            // Redirect to avoid resubmission on refresh
            $cat_id_redirect = filter_var($_GET['cat_id'] ?? 0, FILTER_VALIDATE_INT);
            $search_redirect = isset($_GET['search']) ? sanitize_output($_GET['search']) : '';
            $redirect_url = 'dashboard.php?cat_id=' . $cat_id_redirect . '&added=1&item_name=' . urlencode($item_name);
            if ($search_redirect !== '') {
                $redirect_url .= '&search=' . urlencode($search_redirect);
            }
            header('Location: ' . $redirect_url);
            exit;
        }
    }
}

// Pick up toast data from redirect
if (isset($_GET['added']) && $_GET['added'] === '1' && isset($_GET['item_name'])) {
    $added_item_name = htmlspecialchars($_GET['item_name'], ENT_QUOTES, 'UTF-8');
}

// Search validation with whitespace check
$search_raw = $_GET['search'] ?? '';
$search = trim($search_raw);

// Validate search - reject whitespace-only
if ($search_raw !== '' && $search === '') {
    $field_errors['search'] = 'Search cannot consist of whitespace characters only.';
    $search = ''; // Reset search to empty
}

// Validate search length (max 100 characters)
if ($search !== '' && strlen($search) > 100) {
    $field_errors['search'] = 'Search term cannot exceed 100 characters.';
    $search = '';
}

$popup_category = null;
$popup_items    = [];

// Validate and process cat_id
$cat_id_raw = $_GET['cat_id'] ?? 0;
$cat_id = filter_var($cat_id_raw, FILTER_VALIDATE_INT);

if ($cat_id !== false && $cat_id > 0) {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ? AND is_available = 1");
    $stmt->bind_param("i", $cat_id);
    $stmt->execute();
    $popup_category = $stmt->get_result()->fetch_assoc();

    if ($popup_category) {
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category_id = ? AND is_available = 1 ORDER BY name");
        $stmt->bind_param("i", $cat_id);
        $stmt->execute();
        $res = $stmt->get_result();
        while ($row = $res->fetch_assoc()) {
            $popup_items[] = $row;
        }
    }
}

// Fetch categories with validation
if ($search !== '') {
    $stmt = $conn->prepare("SELECT * FROM categories WHERE name LIKE ? AND is_available = 1");
    $like = '%' . $search . '%';
    $stmt->bind_param("s", $like);
    $stmt->execute();
    $categories_result = $stmt->get_result();
} else {
    $categories_result = $conn->query("SELECT * FROM categories WHERE is_available = 1 ORDER BY name");
}

// Get cart count
$cart_count = 0;
if ($is_logged_in) {
    $stmt = $conn->prepare("SELECT SUM(quantity) as total FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $cart_count = $stmt->get_result()->fetch_assoc()['total'] ?? 0;
}

// Get notification count
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
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herald Canteen - Dashboard</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <script>
    function requireLogin() {
        alert("You must login first to access this feature.");
        window.location.href = "login.php";
    }
    
    function dismissToast() {
        const toast = document.getElementById('cartToast');
        if (toast) toast.classList.add('toast-hide');
    }
    </script>
</head>
<body>

<div class="layout">

    <!-- Sidebar -->
    <div class="sidebar">
        <div class="navbar-title">
            Herald Canteen
            <span>Herald College Kathmandu</span>
        </div>
        <nav>
            <a href="dashboard.php" class="active">🏠 Home</a>

            <a href="<?php echo $is_logged_in ? 'my_cart.php' : 'portal-login.php'; ?>">
                🛒 My Cart
                <?php if ($cart_count > 0): ?>
                    <span class="badge"><?php echo $cart_count; ?></span>
                <?php endif; ?>
            </a>

            <a href="<?php echo $is_logged_in ? 'my_orders.php' : 'portal-login.php'; ?>">
                📦 My Orders
            </a>

            <?php if ($is_logged_in): ?>
                <a href="logout.php">🚪 Logout</a>
            <?php else: ?>
                <a href="portal-login.php">🔑 Login / Signup</a>
            <?php endif; ?>
        </nav>
    </div>

    <!-- Main Content -->
    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <form method="GET" action="dashboard.php" class="search-form <?php echo isset($field_errors['search']) ? 'has-error' : ''; ?>">
                <input type="text" name="search" placeholder="🔍 Search categories..."
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">Search</button>
                <?php if (isset($field_errors['search'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['search'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </form>

            <div class="topbar-right">

                <!-- My Profile -->
                <?php if ($is_logged_in): ?>
                    <a href="user_profile.php" class="profile-link">
                        👤 My Profile
                    </a>
                <?php else: ?>
                    <a href="portal-login.php" class="profile-link">
                        👤 My Profile
                    </a>
                <?php endif; ?>

                <!-- Notification -->
                <?php if ($is_logged_in): ?>
                <a href="notifications.php" class="notif-wrap">
                    🔔
                    <?php if ($notif_count > 0): ?>
                        <span class="notif-badge"><?php echo $notif_count; ?></span>
                    <?php endif; ?>
                </a>
                <?php endif; ?>

            </div>
        </div>

        <!-- Content Area -->
        <div class="content">

            <div class="section-title">
                <h2>Our Specialties</h2>
                <p>
                    <?php if ($is_logged_in): ?>
                        Hello <?php echo htmlspecialchars($name, ENT_QUOTES, 'UTF-8'); ?>, what are you craving today?
                    <?php else: ?>
                        Browse our menu — login to order delicious food!
                    <?php endif; ?>
                </p>
            </div>

            <?php if (!empty($field_errors['general'])): ?>
                <div class="alert-error">
                    ⚠️ <?php echo htmlspecialchars($field_errors['general'], ENT_QUOTES, 'UTF-8'); ?>
                </div>
            <?php endif; ?>

            <!-- Category Grid -->
            <div class="category-grid">
                <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                    <?php while ($cat = $categories_result->fetch_assoc()): ?>
                        <?php if ($is_logged_in): ?>
                            <a href="dashboard.php?cat_id=<?php echo htmlspecialchars($cat['category_id'], ENT_QUOTES, 'UTF-8'); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>" class="category-card">
                        <?php else: ?>
                            <a href="javascript:void(0)" onclick="requireLogin()" class="category-card">
                        <?php endif; ?>
                            <div class="cat-img-wrap">
                                <img src="<?php echo htmlspecialchars($cat['image_url'] ?: '../assets/images/default.jpg', ENT_QUOTES, 'UTF-8'); ?>" alt="<?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?>">
                            </div>
                            <div class="cat-label">
                                <h4><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p><?php echo htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8'); ?></p>
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

<!-- Category Popup Modal -->
<?php if ($popup_category): ?>
<div class="popup-overlay" id="categoryPopup">
    <div class="popup">

        <a href="dashboard.php<?php echo $search ? '?search=' . urlencode($search) : ''; ?>" class="close-btn">✕</a>

        <!-- Category Banner Image -->
        <div class="popup-banner-wrapper">
            <img class="popup-banner" src="<?php echo htmlspecialchars($popup_category['image_url'] ?: '../assets/images/default-category.jpg', ENT_QUOTES, 'UTF-8'); ?>"
                alt="<?php echo htmlspecialchars($popup_category['name'], ENT_QUOTES, 'UTF-8'); ?>">
        </div>

        <div class="popup-header-text">
            <h2><?php echo htmlspecialchars($popup_category['name'], ENT_QUOTES, 'UTF-8'); ?></h2>
            <p><?php echo htmlspecialchars($popup_category['description'], ENT_QUOTES, 'UTF-8'); ?></p>
        </div>

        <!-- Menu Items List -->
        <div class="popup-items">
            <h3>🍽️ Choose Your Variety</h3>
            <?php if (count($popup_items) > 0): ?>
                <div class="items-grid">
                    <?php foreach ($popup_items as $item): ?>
                        <div class="popup-item">
                            <div class="popup-item-info">
                                <h4><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></h4>
                                <p><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></p>
                                <div class="item-footer">
                                    <span class="price">Rs. <?php echo number_format($item['price'], 2); ?></span>
                                    <?php if ($item['rating'] > 0): ?>
                                        <span class="rating">⭐ <?php echo number_format($item['rating'], 1); ?></span>
                                    <?php endif; ?>
                                </div>
                            </div>

                            <?php if ($is_logged_in): ?>
                            <form method="POST" action="dashboard.php?cat_id=<?php echo htmlspecialchars($popup_category['category_id'], ENT_QUOTES, 'UTF-8'); ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?>">
                                <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['item_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                <button type="submit" class="add-btn">+ Add to Bag</button>
                            </form>
                            <?php else: ?>
                                <a href="login.php" class="add-btn">+ Add to Bag</a>
                            <?php endif; ?>

                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <p class="no-result">No items available in this category at the moment.</p>
            <?php endif; ?>
        </div>

    </div>
</div>
<?php endif; ?>

<!-- Toast Notification -->
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
        <span class="toast-sub">Added to your bag successfully ✓</span>
    </div>
    <button class="toast-close" onclick="dismissToast()">✕</button>
</div>
<script>
    const toast = document.getElementById('cartToast');
    if (toast) {
        setTimeout(() => {
            toast.classList.add('toast-hide');
        }, 3000);
    }
</script>
<?php endif; ?>

</body>
</html>