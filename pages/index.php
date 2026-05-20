<?php
require_once '../includes/auth.php';
start_session();
session_security_check();
require_once '../config/db.php';

// Staff and chef should never land on the customer index — send them home
if (isset($_SESSION['user_id'], $_SESSION['role'])) {
    if ($_SESSION['role'] === 'staff') {
        header('Location: staff-control.php');
        exit;
    }
    if ($_SESSION['role'] === 'chef') {
        header('Location: chef-control.php');
        exit;
    }
}

$msg = $_GET['msg'] ?? '';
$flash_map = [
    'login_required' => ['type' => 'error',  'text' => 'Please log in to access that page.'],
    'logged_out'     => ['type' => 'success', 'text' => 'You have been logged out successfully.'],
];
$flash = $flash_map[$msg] ?? null;

// Pull 4 available items from DB ordered by rating
$items = [];
$result = $conn->query(
    "SELECT m.name, m.description, m.price, m.image_url, m.rating,
            c.name AS category_name
     FROM   menu_items m
     JOIN   categories c ON m.category_id = c.category_id
     WHERE  m.is_available = 1
     ORDER  BY m.rating DESC
     LIMIT  4"
);
if ($result && $result->num_rows > 0) {
    while ($row = $result->fetch_assoc()) {
        $items[] = $row;
    }
}

// Keyword → actual image file mapping
$img_map = [
    'pizza' => 'pizza.jpg', 'pepperoni' => 'pizza.jpg', 'margherita' => 'pizza.jpg',
    // momo/steam must come BEFORE 'chicken' so "Chicken Steam Momo" matches momo.jpg first
    'momo' => 'momo.jpg', 'steam' => 'momo.jpg', 'dumpling' => 'momo.jpg',
    'burger' => 'burger.jpg', 'chicken' => 'burger.jpg',
    'coke' => 'drinks.jpg', 'drink' => 'drinks.jpg', 'juice' => 'drinks.jpg',
    'noodle' => 'noodles.jpg', 'rice' => 'rice.jpg',
    'pasta' => 'pasta.jpg', 'salad' => 'salad.jpg', 'sandwich' => 'sandwich.jpg',
    'kebab' => 'kebabs.jpg', 'roll' => 'rolls.jpg', 'seafood' => 'seafood.jpg',
    'dessert' => 'deserts.jpg', 'waffle' => 'waffles.jpg', 'tiramisu' => 'deserts.jpg',
    'soup' => 'soup.jpg', 'steak' => 'steak.jpg',
    'sushi' => 'sushi.jpg', 'nigiri' => 'sushi.jpg', 'salmon' => 'sushi.jpg',
    'taco' => 'tacos.jpg', 'shawarma' => 'shawarma.jpg', 'snack' => 'snacks.jpg',
    'crab' => 'seafood.jpg', 'butter' => 'seafood.jpg'
];
$emoji_map = [
    'pizza'=>'🍕','burger'=>'🍔','chicken'=>'🍗','coke'=>'🥤','drink'=>'🥤',
    'juice'=>'🥤','momo'=>'🥟','noodle'=>'🍜','rice'=>'🍚','pasta'=>'🍝',
    'salad'=>'🥗','sandwich'=>'🥪','kebab'=>'🍢','roll'=>'🌯','seafood'=>'🦐',
    'dessert'=>'🍮','waffle'=>'🧇','soup'=>'🍲','steak'=>'🥩','sushi'=>'🍣',
    'taco'=>'🌮','shawarma'=>'🌯','dal'=>'🍛','curry'=>'🍛','tea'=>'☕',
    'coffee'=>'☕','lassi'=>'🥛','tiramisu'=>'🍰','crab'=>'🦀'
];

function get_img(string $name, string $db_img): string {
    global $img_map;
    $base = __DIR__ . '/../assets/images/';
    
    // If database has an image URL
    if (!empty($db_img)) {
        $filename = basename($db_img);
        if (file_exists($base . $filename)) {
            return '../assets/images/' . $filename;
        }
    }
    
    // Map item names to your actual image files
    $lower = strtolower($name);
    
    // Desserts - use deserts.jpg (your actual filename)
    if (str_contains($lower, 'tiramisu') || str_contains($lower, 'cheesecake') || str_contains($lower, 'lava') || str_contains($lower, 'gulab') || str_contains($lower, 'brownie')) {
        if (file_exists($base . 'deserts.jpg')) {
            return '../assets/images/deserts.jpg';
        }
    }
    
    // Seafood for crab, shrimp, fish
    if (str_contains($lower, 'crab') || str_contains($lower, 'shrimp') || str_contains($lower, 'fish') || str_contains($lower, 'seafood')) {
        if (file_exists($base . 'seafood.jpg')) {
            return '../assets/images/seafood.jpg';
        }
    }
    
    // Use your existing mapping
    foreach ($img_map as $key => $file) {
        if (str_contains($lower, $key) && file_exists($base . $file)) {
            return '../assets/images/' . $file;
        }
    }
    
    return '';
}

function get_emoji(string $name): string {
    global $emoji_map;
    $lower = strtolower($name);
    foreach ($emoji_map as $key => $e) {
        if (str_contains($lower, $key)) return $e;
    }
    return '🍱';
}

// Hardcoded fallback if DB is empty
if (empty($items)) {
    $items = [
        ['name'=>'Momo (8 pcs)',          'category_name'=>'Nepali',   'description'=>'Steamed dumplings with chutney', 'price'=>90,  'rating'=>4.9,'image_url'=>'momo.jpg'],
        ['name'=>'Dal Bhat Tarkari',       'category_name'=>'Nepali',   'description'=>'Classic rice and lentil set',    'price'=>120, 'rating'=>4.8,'image_url'=>'rice.jpg'],
        ['name'=>'Chicken Steam Momo',     'category_name'=>'Momo',     'description'=>'Juicy chicken steamed momos',   'price'=>160, 'rating'=>4.9,'image_url'=>'momo.jpg'],
        ['name'=>'Fresh Juice',            'category_name'=>'Beverages','description'=>'Cold seasonal juice',            'price'=>120, 'rating'=>4.6,'image_url'=>'drinks.jpg'],
    ];
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Herald Canteen</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600;700&family=Syne:wght@700;800&display=swap" rel="stylesheet">
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
</head>
<body>

<div class="lp">

    <!-- Toast: inside .lp so .lp .toast CSS rules apply (display:none by default, flex on .show) -->
    <div class="toast" id="toast">
        <div class="toast-icon">🔐</div>
        <div class="toast-body">
            <div class="toast-title">Login required</div>
            <div class="toast-sub">You must log in to order food.</div>
        </div>
        <button class="toast-close" onclick="dismissToast()" title="Dismiss">&times;</button>
        <div class="toast-bar"></div>
    </div>
    <!-- Theme Toggle -->
    <div style="position:fixed;top:18px;right:18px;z-index:999;">
      <label class="theme-toggle" title="Toggle light/dark mode">
        <input type="checkbox" class="theme-checkbox">
        <span class="theme-slider"></span>
      </label>
    </div>

    <div class="hero">
        <img src="../assets/images/Logo.PNG" alt="Herald Canteen" class="hero-logo">
        <p class="hero-college">Herald College Kathmandu</p>
        <h1 class="hero-title">Herald <span>Canteen</span></h1>
        <p class="hero-tagline">Fresh food, fast delivery — right inside campus.</p>
    </div>

    <?php if ($flash): ?>
        <div class="flash flash-<?= $flash['type'] ?>">
            <?= htmlspecialchars($flash['text']) ?>
        </div>
    <?php endif; ?>

    <p class="menu-label">What's on the Menu</p>

    <div class="cards">
        <?php foreach ($items as $item):
            $img   = get_img($item['name'], $item['image_url'] ?? '');
            $emoji = get_emoji($item['name']);
        ?>
        <div class="card" onclick="handleCardClick()">
            <div class="card-img">
                <?php if ($img): ?>
                    <img src="<?= htmlspecialchars($img) ?>" alt="<?= htmlspecialchars($item['name']) ?>">
                <?php else: ?>
                    <span class="card-emoji"><?= $emoji ?></span>
                <?php endif; ?>
                <div class="card-img-overlay">
                    <span class="card-img-overlay-text">Tap to Order</span>
                </div>
                <span class="card-rating">⭐ <?= number_format((float)$item['rating'], 1) ?></span>
            </div>
            <div class="card-body">
                <div class="card-cat"><?= htmlspecialchars($item['category_name']) ?></div>
                <div class="card-name"><?= htmlspecialchars($item['name']) ?></div>
                <div class="card-price">Rs <?= number_format((float)$item['price'], 0) ?></div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>

    <div class="cta">
        <?php if (isset($_SESSION['user_id'])): ?>
            <a href="dashboard.php" class="cta-btn">🍽️ &nbsp;Go to Menu</a>
        <?php else: ?>
            <a href="portal-login.php" class="cta-btn">🔑 &nbsp;Login / Sign Up</a>
        <?php endif; ?>
    </div>

    <div class="footer">
        <img src="../assets/images/Logo.PNG" alt="">
        <span>Herald Canteen &nbsp;·&nbsp; Herald College Kathmandu</span>
    </div>

</div>

<script>
    var _isLoggedIn = <?php echo isset($_SESSION['user_id']) ? 'true' : 'false'; ?>;
    var _toastTimer = null;

    function handleCardClick() {
        if (_isLoggedIn) {
            window.location.href = 'dashboard.php';
        } else {
            showLoginToast();
        }
    }

    function showLoginToast() {
        var toast = document.getElementById('toast');
        if (_toastTimer) { clearTimeout(_toastTimer); _toastTimer = null; }
        toast.classList.add('show');
        _toastTimer = setTimeout(function () {
            toast.classList.remove('show');
            _toastTimer = null;
        }, 4000);
    }

    function dismissToast() {
        var toast = document.getElementById('toast');
        if (_toastTimer) { clearTimeout(_toastTimer); _toastTimer = null; }
        toast.classList.remove('show');
    }
</script>

</body>
</html>
