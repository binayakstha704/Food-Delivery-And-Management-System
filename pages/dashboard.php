<?php
require_once "../includes/auth.php";
start_session();
session_security_check();
require '../config/db.php';

// Helper function for sanitization
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

// RBAC: Block Chef and Staff from accessing the customer dashboard — portal-login (panel-level page)
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'chef' || $_SESSION['role'] === 'staff') {
        header('Location: portal-login.php');
        exit;
    }
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

// Generate CSRF token for Add to Cart form (CSRF fix — Bug 1)
if ($is_logged_in && empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

// Detect AJAX request (sent by our JS fetch)
$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
           strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

// Handle Add to Cart with validation
if ($is_logged_in && $_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['item_id'])) {

    // CSRF validation — Bug 1 fix: prevent cross-site cart manipulation
    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'] ?? '', $_POST['csrf_token'])) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid request token. Please refresh the page.']);
            exit;
        }
        $field_errors['general'] = 'Invalid request token. Please refresh the page.';
        goto skip_cart_action;
    }

    $item_id = filter_var($_POST['item_id'], FILTER_VALIDATE_INT);
    
    // Validate item_id
    if ($item_id === false || $item_id <= 0) {
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid item selected.']);
            exit;
        }
        $field_errors['general'] = 'Invalid item selected.';
    } else {
        // Fetch item name for the notification (using prepared statement)
        $stmt = $conn->prepare("SELECT name, price FROM menu_items WHERE item_id = ? AND is_available = 1");
        $stmt->bind_param("i", $item_id);
        $stmt->execute();
        $item_data = $stmt->get_result()->fetch_assoc();
        
        if (!$item_data) {
            if ($is_ajax) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Item not available.']);
                exit;
            }
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

            // For AJAX: return JSON with updated cart count and item name
            if ($is_ajax) {
                $ct_stmt = $conn->prepare("SELECT SUM(quantity) AS n FROM cart WHERE user_id = ?");
                $ct_uid  = (int)$user_id;
                $ct_stmt->bind_param('i', $ct_uid);
                $ct_stmt->execute();
                $ct_row   = $ct_stmt->get_result()->fetch_assoc();
                $ct_stmt->close();
                $bag_count = (int)($ct_row['n'] ?? 0);
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'item_name' => $item_name, 'bag_count' => $bag_count]);
                exit;
            }

            // Non-AJAX fallback: redirect to avoid resubmission on refresh
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

skip_cart_action: // jump here if CSRF fails (non-AJAX fallback)

// ── AJAX search endpoint ──────────────────────────────────────────────────
if ($is_ajax && isset($_GET['search_ajax'])) {
    $q = trim($_GET['search_ajax']);
    $error = null;
    if ($q !== '' && strlen($q) > 100) {
        $error = 'Search term cannot exceed 100 characters.';
    }
    $cats = [];
    if (!$error) {
        if ($q !== '') {
            $like = '%' . $conn->real_escape_string($q) . '%';
            $res = $conn->query("SELECT * FROM categories WHERE name LIKE '$like' AND is_available = 1");
        } else {
            $res = $conn->query("SELECT * FROM categories WHERE is_available = 1 ORDER BY name");
        }
        if ($res) {
            while ($row = $res->fetch_assoc()) $cats[] = $row;
        }
    }
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'error' => $error, 'categories' => $cats, 'is_logged_in' => (bool)$is_logged_in]);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────

// ── AJAX category items endpoint ─────────────────────────────────────────
if ($is_ajax && isset($_GET['cat_ajax'])) {
    if (!$is_logged_in) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Not logged in.']);
        exit;
    }
    $cat_id_req = filter_var($_GET['cat_ajax'], FILTER_VALIDATE_INT);
    if (!$cat_id_req || $cat_id_req <= 0) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Invalid category.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ? AND is_available = 1");
    $stmt->bind_param("i", $cat_id_req);
    $stmt->execute();
    $cat_row = $stmt->get_result()->fetch_assoc();
    $stmt->close();
    if (!$cat_row) {
        header('Content-Type: application/json');
        echo json_encode(['ok' => false, 'error' => 'Category not found.']);
        exit;
    }
    $stmt = $conn->prepare("SELECT * FROM menu_items WHERE category_id = ? AND is_available = 1 ORDER BY name");
    $stmt->bind_param("i", $cat_id_req);
    $stmt->execute();
    $res = $stmt->get_result();
    $items_arr = [];
    while ($row = $res->fetch_assoc()) $items_arr[] = $row;
    $stmt->close();
    header('Content-Type: application/json');
    echo json_encode(['ok' => true, 'category' => $cat_row, 'items' => $items_arr]);
    exit;
}
// ─────────────────────────────────────────────────────────────────────────


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
    <script src="../assets/js/modal.js"></script>
<script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">

    <script>
    function requireLogin() {
        hcAlert("You must login first to access this feature.", {icon:"🔒", type:"warning"});
        window.location.href = "portal-login.php";
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
            <form method="GET" action="dashboard.php" id="search-form" class="search-form <?php echo isset($field_errors['search']) ? 'has-error' : ''; ?>">
                <input type="text" name="search" placeholder="🔍 Search categories..."
                    value="<?php echo htmlspecialchars($search, ENT_QUOTES, 'UTF-8'); ?>">
                <button type="submit">Search</button>
                <?php if (isset($field_errors['search'])): ?>
                    <span class="field-error">⚠️ <?php echo htmlspecialchars($field_errors['search'], ENT_QUOTES, 'UTF-8'); ?></span>
                <?php endif; ?>
            </form>

            <div class="topbar-right">
                <!-- Theme Toggle -->
                <label class="theme-toggle" title="Toggle light/dark mode">
                  <input type="checkbox" class="theme-checkbox">
                  <span class="theme-slider"></span>
                </label>


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
            <div class="category-grid" id="category-grid">
                <?php if ($categories_result && $categories_result->num_rows > 0): ?>
                    <?php while ($cat = $categories_result->fetch_assoc()): ?>
                        <?php if ($is_logged_in): ?>
                            <a href="javascript:void(0)" onclick="openCategoryModal(<?php echo (int)$cat['category_id']; ?>)" class="category-card">
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

<!-- Category Popup Modal (JS-driven, no page reload) -->
<div class="popup-overlay" id="categoryPopup" style="display:none;">
    <div class="popup">
        <button type="button" class="close-btn" onclick="closeCategoryModal()">✕</button>
        <div class="popup-banner-wrapper">
            <img class="popup-banner" id="popupBannerImg" src="" alt="">
        </div>
        <div class="popup-header-text">
            <h2 id="popupCategoryName"></h2>
            <p id="popupCategoryDesc"></p>
        </div>
        <div class="popup-items">
            <h3>🍽️ Choose Your Variety</h3>
            <div class="items-grid" id="popupItemsGrid">
                <p class="no-result">Loading…</p>
            </div>
        </div>
    </div>
</div>

<!-- Toast Notification (AJAX-driven — no page reload) -->
<!-- Hidden toast container for AJAX-triggered notifications -->
<div class="toast" id="cartToast" style="min-width:280px;display:none;">
    <div class="toast-icon">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="9" cy="9" r="9" fill="#4db848"/>
            <path d="M5 9.5L7.5 12L13 6.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="toast-body">
        <span class="toast-title" id="toastTitle"></span>
        <span class="toast-sub" id="toastSub"></span>
    </div>
    <button class="toast-close" onclick="dismissToast()">&#x2715;</button>
</div>

<script>
function dismissToast() {
    var toast = document.getElementById('cartToast');
    if (toast) toast.classList.add('toast-hide');
}

function showToast(itemName, bagCount) {
    var toast = document.getElementById('cartToast');
    var title = document.getElementById('toastTitle');
    var sub   = document.getElementById('toastSub');
    if (!toast) return;
    title.textContent = itemName;
    sub.textContent   = 'Added to bag \u2713 \u00b7 ' + bagCount + ' item' + (bagCount !== 1 ? 's' : '') + ' in bag';
    toast.classList.remove('toast-hide');
    toast.style.display = '';
    clearTimeout(toast._hideTimer);
    toast._hideTimer = setTimeout(function() { toast.classList.add('toast-hide'); }, 3500);
}

function updateCartBadge(count) {
    var cartLinks = document.querySelectorAll('a[href*="my_cart"]');
    cartLinks.forEach(function(link) {
        var badge = link.querySelector('.badge');
        if (count > 0) {
            if (!badge) {
                badge = document.createElement('span');
                badge.className = 'badge';
                link.appendChild(badge);
            }
            badge.textContent = count;
            badge.style.display = '';
        } else if (badge) {
            badge.style.display = 'none';
        }
    });
}

// CSRF token for Add to Cart AJAX (Bug 1 fix)
const DASHBOARD_CSRF = <?php echo json_encode($_SESSION['csrf_token'] ?? ''); ?>;
const IS_LOGGED_IN_DASH = <?php echo $is_logged_in ? 'true' : 'false'; ?>;

/* ── Category Modal (no page reload) ─────────────────────────────────────── */
function openCategoryModal(catId) {
    var overlay = document.getElementById('categoryPopup');
    var grid    = document.getElementById('popupItemsGrid');
    var title   = document.getElementById('popupCategoryName');
    var desc    = document.getElementById('popupCategoryDesc');
    var banner  = document.getElementById('popupBannerImg');

    // Show overlay immediately with loading state
    grid.innerHTML = '<p class="no-result">Loading…</p>';
    title.textContent = '';
    desc.textContent  = '';
    banner.src = '';
    overlay.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    fetch('dashboard.php?cat_ajax=' + encodeURIComponent(catId), {
        headers: { 'X-Requested-With': 'XMLHttpRequest' }
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) {
            grid.innerHTML = '<p class="no-result">⚠️ ' + escHtmlModal(data.error || 'Failed to load.') + '</p>';
            return;
        }
        var cat = data.category;
        title.textContent = cat.name || '';
        desc.textContent  = cat.description || '';
        if (cat.image_url) {
            banner.src = cat.image_url;
            banner.alt = cat.name;
            banner.parentElement.style.display = '';
        } else {
            banner.parentElement.style.display = 'none';
        }

        if (!data.items || data.items.length === 0) {
            grid.innerHTML = '<p class="no-result">No items available in this category at the moment.</p>';
            return;
        }

        var html = '';
        data.items.forEach(function(item) {
            var rating = parseFloat(item.rating) > 0
                ? '<span class="rating">⭐ ' + parseFloat(item.rating).toFixed(1) + '</span>'
                : '';
            html += '<div class="popup-item">'
                  + '<div class="popup-item-info">'
                  + '<h4>' + escHtmlModal(item.name) + '</h4>'
                  + '<p>' + escHtmlModal(item.description || '') + '</p>'
                  + '<div class="item-footer">'
                  + '<span class="price">Rs. ' + parseFloat(item.price).toFixed(2) + '</span>'
                  + rating
                  + '</div></div>'
                  + (IS_LOGGED_IN_DASH
                      ? '<button type="button" class="add-btn" onclick="addToCart(' + parseInt(item.item_id) + ', this)">+ Add to Bag</button>'
                      : '<a href="portal-login.php" class="add-btn">+ Add to Bag</a>')
                  + '</div>';
        });
        grid.innerHTML = html;
    })
    .catch(function() {
        grid.innerHTML = '<p class="no-result">Network error. Please try again.</p>';
    });
}

function closeCategoryModal() {
    var overlay = document.getElementById('categoryPopup');
    overlay.style.display = 'none';
    document.body.style.overflow = '';
}

// Close on overlay backdrop click
document.addEventListener('DOMContentLoaded', function() {
    var overlay = document.getElementById('categoryPopup');
    overlay.addEventListener('click', function(e) {
        if (e.target === overlay) closeCategoryModal();
    });
});

// Close on Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        var overlay = document.getElementById('categoryPopup');
        if (overlay && overlay.style.display !== 'none') closeCategoryModal();
    }
});

function escHtmlModal(s) {
    return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
/* ─────────────────────────────────────────────────────────────────────────── */

function addToCart(itemId, btn) {
    if (btn) { btn.disabled = true; btn.textContent = '...'; }

    fetch('dashboard.php', {
        method:  'POST',
        headers: {
            'Content-Type':     'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'csrf_token=' + encodeURIComponent(DASHBOARD_CSRF) + '&item_id=' + encodeURIComponent(itemId)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            showToast(data.item_name, data.bag_count);
            updateCartBadge(data.bag_count);
        } else {
            hcAlert(data.error || 'Could not add item. Please try again.', {icon:'❌', type:'danger'});
        }
    })
    .catch(function() { hcAlert('Network error. Please try again.', {icon:'📡', type:'warning'}); })
    .finally(function() {
        if (btn) { btn.disabled = false; btn.textContent = '+ Add to Bag'; }
    });
}

/* ── AJAX Live Search ──────────────────────────────────────────────────── */
(function () {
    var form      = document.getElementById('search-form');
    var input     = form ? form.querySelector('input[name="search"]') : null;
    var grid      = document.getElementById('category-grid');
    var isLoggedIn = <?php echo $is_logged_in ? 'true' : 'false'; ?>;
    var debounceTimer = null;

    if (!form || !input || !grid) return;

    // Build one category card's HTML from a data object
    function buildCard(cat) {
        var img  = cat.image_url ? cat.image_url : '../assets/images/default.jpg';
        var href = 'javascript:void(0)';
        var onclick = isLoggedIn
            ? ' onclick="openCategoryModal(' + parseInt(cat.category_id) + ')"'
            : ' onclick="requireLogin()"';
        return '<a href="' + href + '"' + onclick + ' class="category-card">'
             + '<div class="cat-img-wrap"><img src="' + escHtml(img) + '" alt="' + escHtml(cat.name) + '"></div>'
             + '<div class="cat-label"><h4>' + escHtml(cat.name) + '</h4><p>' + escHtml(cat.description || '') + '</p></div>'
             + '</a>';
    }

    function escHtml(s) {
        return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    function doSearch(q) {
        fetch('dashboard.php?search_ajax=' + encodeURIComponent(q), {
            headers: { 'X-Requested-With': 'XMLHttpRequest' }
        })
        .then(function(r) { return r.json(); })
        .then(function(data) {
            if (!data.ok) {
                grid.innerHTML = '<p class="no-result">⚠️ ' + escHtml(data.error || 'Search error') + '</p>';
                return;
            }
            if (data.categories.length === 0) {
                grid.innerHTML = '<p class="no-result">No categories found for "' + escHtml(q) + '".</p>';
            } else {
                grid.innerHTML = data.categories.map(buildCard).join('');
            }
            // Update the URL without reloading
            var newUrl = 'dashboard.php' + (q ? '?search=' + encodeURIComponent(q) : '');
            history.replaceState(null, '', newUrl);
        })
        .catch(function() {
            grid.innerHTML = '<p class="no-result">Network error. Please try again.</p>';
        });
    }

    // Live search on input (debounced 300ms)
    input.addEventListener('input', function () {
        clearTimeout(debounceTimer);
        debounceTimer = setTimeout(function () {
            doSearch(input.value.trim());
        }, 300);
    });

    // Prevent full page reload on form submit
    form.addEventListener('submit', function (e) {
        e.preventDefault();
        clearTimeout(debounceTimer);
        doSearch(input.value.trim());
    });
})();
/* ───────────────────────────────────────────────────────────────────────── */

/* ── Real-time notification polling (handled by shared notif_poller.js) ─── */
</script>
<script src="../assets/js/notif_poller.js"></script>

</body>
</html>