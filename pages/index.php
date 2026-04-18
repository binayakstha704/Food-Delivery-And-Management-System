<?php
session_start();
require_once '../config/db.php';

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
    'burger' => 'burger.jpg', 'chicken' => 'burger.jpg',
    'coke' => 'drinks.jpg', 'drink' => 'drinks.jpg', 'juice' => 'drinks.jpg',
    'momo' => 'momo.jpg', 'noodle' => 'noodles.jpg', 'rice' => 'rice.jpg',
    'pasta' => 'pasta.jpg', 'salad' => 'salad.jpg', 'sandwich' => 'sandwich.jpg',
    'kebab' => 'kebabs.jpg', 'roll' => 'rolls.jpg', 'seafood' => 'seafood.jpg',
    'dessert' => 'deserts.jpg', 'waffle' => 'waffles.jpg', 'tiramisu' => 'deserts.jpg',
    'soup' => 'soup.jpg', 'steak' => 'steak.jpg', 'sushi' => 'sushi.jpg',
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
        ['name'=>'Momo (8 pcs)',     'category_name'=>'Nepali',   'description'=>'Steamed dumplings with chutney', 'price'=>90,  'rating'=>4.9,'image_url'=>'momo.jpg'],
        ['name'=>'Dal Bhat Tarkari', 'category_name'=>'Nepali',   'description'=>'Classic rice and lentil set',    'price'=>120, 'rating'=>4.8,'image_url'=>'rice.jpg'],
        ['name'=>'Chicken Burger',   'category_name'=>'Burgers',  'description'=>'Crispy chicken with veggies',    'price'=>320, 'rating'=>4.7,'image_url'=>'burger.jpg'],
        ['name'=>'Fresh Juice',      'category_name'=>'Beverages','description'=>'Cold seasonal juice',            'price'=>120, 'rating'=>4.6,'image_url'=>'drinks.jpg'],
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
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --green:      #4db848;
            --green-dark: #3a9236;
            --dark:       #141414;
            --dark2:      #1c1c1c;
            --white:      #ffffff;
            --muted:      #666666;
            --font-body:  'DM Sans', sans-serif;
            --font-head:  'Syne', sans-serif;
        }

        html, body {
            height: 100%;
            overflow: hidden;
            background: var(--dark);
            color: var(--white);
            font-family: var(--font-body);
        }

        .lp {
            height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            padding: 20px 24px;
            position: relative;
            overflow: hidden;
        }
        .lp::before {
            content: '';
            position: absolute;
            top: -120px; left: 50%;
            transform: translateX(-50%);
            width: 600px; height: 300px;
            background: radial-gradient(ellipse, rgba(77,184,72,0.12) 0%, transparent 70%);
            pointer-events: none;
        }
        .lp::after {
            content: '';
            position: absolute;
            inset: 0;
            background-image:
                linear-gradient(rgba(255,255,255,0.013) 1px, transparent 1px),
                linear-gradient(90deg, rgba(255,255,255,0.013) 1px, transparent 1px);
            background-size: 56px 56px;
            pointer-events: none;
        }
        .lp > * { position: relative; z-index: 1; }

        .toast {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 9999;
            background: #1e1e1e;
            border: 1.5px solid var(--green);
            border-radius: 12px;
            padding: 13px 18px;
            display: flex;
            align-items: center;
            gap: 11px;
            min-width: 280px;
            max-width: 340px;
            box-shadow: 0 8px 32px rgba(0,0,0,0.5), 0 0 0 1px rgba(77,184,72,0.15);
            transform: translateX(120%);
            opacity: 0;
            transition: transform 0.4s cubic-bezier(0.34,1.56,0.64,1), opacity 0.3s ease;
            pointer-events: none;
        }
        .toast.show {
            transform: translateX(0);
            opacity: 1;
        }
        .toast-icon {
            font-size: 1.4rem;
            flex-shrink: 0;
            line-height: 1;
        }
        .toast-body {
            display: flex;
            flex-direction: column;
            gap: 2px;
        }
        .toast-title {
            font-family: var(--font-head);
            font-size: 0.82rem;
            font-weight: 700;
            color: var(--white);
        }
        .toast-sub {
            font-size: 0.7rem;
            color: var(--muted);
        }
        .toast-bar {
            position: absolute;
            bottom: 0; left: 0;
            height: 3px;
            background: var(--green);
            border-radius: 0 0 12px 12px;
            width: 100%;
            transform-origin: left;
            animation: none;
        }
        .toast.show .toast-bar {
            animation: drain 1.6s linear forwards;
        }
        @keyframes drain {
            from { transform: scaleX(1); }
            to   { transform: scaleX(0); }
        }

        .hero {
            text-align: center;
            margin-bottom: 18px;
        }
        .hero-logo {
            height: 42px;
            margin: 0 auto 10px;
            filter: drop-shadow(0 0 12px rgba(77,184,72,0.35));
        }
        .hero-college {
            font-size: 0.62rem;
            color: #444;
            text-transform: uppercase;
            letter-spacing: 0.18em;
            margin-bottom: 4px;
        }
        .hero-title {
            font-family: var(--font-head);
            font-size: 2.4rem;
            font-weight: 800;
            color: var(--white);
            line-height: 1.1;
            margin-bottom: 4px;
        }
        .hero-title span { color: var(--green); }
        .hero-tagline { font-size: 0.8rem; color: var(--muted); }

        .flash {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 0.8rem;
            margin-bottom: 14px;
            text-align: center;
        }
        .flash-error   { background: #3a1a1a; color: #f88; border: 1px solid #622; }
        .flash-success { background: #1a2e1a; color: #8f8; border: 1px solid #262; }

        .menu-label {
            width: 100%;
            max-width: 800px;
            font-size: 0.6rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.14em;
            color: #3a3a3a;
            display: flex;
            align-items: center;
            gap: 10px;
            margin-bottom: 12px;
        }
        .menu-label::after { content: ''; flex: 1; height: 1px; background: #222; }

        .cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            width: 100%;
            max-width: 800px;
            margin-bottom: 22px;
        }

        .card {
            background: var(--dark2);
            border: 1.5px solid #252525;
            border-radius: 14px;
            overflow: hidden;
            display: flex;
            flex-direction: column;
            cursor: pointer;
            transition:
                transform 0.32s cubic-bezier(0.34,1.56,0.64,1),
                border-color 0.25s ease,
                box-shadow 0.32s ease;
        }
        .card:hover {
            transform: translateY(-8px) scale(1.03);
            border-color: var(--green);
            box-shadow: 0 18px 44px rgba(77,184,72,0.22), 0 0 0 1px rgba(77,184,72,0.1);
        }
        .card:active { transform: translateY(-2px) scale(0.99); }

        .card-img {
            position: relative;
            height: 96px;
            background: #111;
            overflow: hidden;
            display: flex;
            align-items: center;
            justify-content: center;
            flex-shrink: 0;
        }
        .card-img img {
            width: 100%;
            height: 100%;
            object-fit: cover;
            display: block;
            transition: transform 0.4s ease;
        }
        .card:hover .card-img img { transform: scale(1.08); }

        .card-img-overlay {
            position: absolute;
            inset: 0;
            background: rgba(77,184,72,0.0);
            display: flex;
            align-items: center;
            justify-content: center;
            transition: background 0.25s ease;
        }
        .card:hover .card-img-overlay { background: rgba(77,184,72,0.18); }
        .card-img-overlay-text {
            font-size: 0.65rem;
            font-weight: 700;
            color: #fff;
            background: rgba(0,0,0,0.55);
            padding: 4px 10px;
            border-radius: 20px;
            letter-spacing: 0.06em;
            text-transform: uppercase;
            opacity: 0;
            transform: translateY(4px);
            transition: opacity 0.2s ease, transform 0.2s ease;
            backdrop-filter: blur(4px);
        }
        .card:hover .card-img-overlay-text {
            opacity: 1;
            transform: translateY(0);
        }

        .card-emoji {
            font-size: 2.6rem;
            line-height: 1;
            transition: transform 0.32s cubic-bezier(0.34,1.56,0.64,1);
        }
        .card:hover .card-emoji { transform: scale(1.18) rotate(-6deg); }

        .card-img::after {
            content: '';
            position: absolute;
            bottom: 0; left: 0; right: 0;
            height: 2px;
            background: var(--green);
            transform: scaleX(0);
            transform-origin: left;
            transition: transform 0.3s ease;
        }
        .card:hover .card-img::after { transform: scaleX(1); }

        .card-rating {
            position: absolute;
            top: 7px; right: 7px;
            background: rgba(0,0,0,0.72);
            color: #f9c74f;
            font-size: 0.6rem;
            font-weight: 700;
            padding: 2px 7px;
            border-radius: 20px;
            backdrop-filter: blur(4px);
            z-index: 2;
        }

        .card-body {
            padding: 10px 12px 12px;
            display: flex;
            flex-direction: column;
            gap: 3px;
        }
        .card-cat {
            font-size: 0.58rem;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 0.1em;
            color: var(--green);
        }
        .card-name {
            font-family: var(--font-head);
            font-size: 0.82rem;
            font-weight: 700;
            color: #ffffff;
            line-height: 1.2;
        }
        .card-price {
            font-size: 0.85rem;
            font-weight: 800;
            color: var(--green);
            margin-top: 2px;
        }

        .cta {
            display: flex;
            justify-content: center;
            width: 100%;
            margin-bottom: 18px;
        }
        .cta-btn {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            background: var(--green);
            color: #fff;
            font-family: var(--font-head);
            font-size: 0.95rem;
            font-weight: 700;
            padding: 13px 46px;
            border-radius: 50px;
            text-decoration: none;
            letter-spacing: 0.04em;
            position: relative;
            overflow: hidden;
            box-shadow: 0 4px 22px rgba(77,184,72,0.38);
            transition:
                background 0.25s ease,
                transform 0.32s cubic-bezier(0.34,1.56,0.64,1),
                box-shadow 0.3s ease;
        }
        .cta-btn::before {
            content: '';
            position: absolute;
            top: 0; left: -100%;
            width: 55%; height: 100%;
            background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
            transition: left 0.5s ease;
        }
        .cta-btn:hover::before { left: 150%; }
        .cta-btn:hover {
            background: var(--green-dark);
            transform: translateY(-3px) scale(1.04);
            box-shadow: 0 10px 34px rgba(77,184,72,0.52);
            color: #fff;
            text-decoration: none;
        }
        .cta-btn:active { transform: translateY(-1px) scale(1); }

        .footer {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            opacity: 0.25;
            font-size: 0.6rem;
            color: #888;
        }
        .footer img { height: 15px; }

        @media (max-width: 660px) {
            html, body { overflow-y: auto; }
            .lp { height: auto; min-height: 100vh; padding: 30px 16px; }
            .cards { grid-template-columns: repeat(2, 1fr); gap: 10px; }
            .hero-title { font-size: 1.9rem; }
            .toast { min-width: 240px; right: 12px; top: 12px; }
        }
    </style>
</head>
<body>

<div class="toast" id="toast">
    <div class="toast-icon">🔐</div>
    <div class="toast-body">
        <div class="toast-title">Login required</div>
        <div class="toast-sub">You must log in to order food.</div>
    </div>
    <div class="toast-bar"></div>
</div>

<div class="lp">

    <div class="hero">
        <img src="../assets/images/Canteen.PNG" alt="Herald Canteen" class="hero-logo">
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
        <div class="card" onclick="showToastAndGo()">
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
        <a href="portal-login.php" class="cta-btn">🔑 &nbsp;Login / Sign Up</a>
    </div>

    <div class="footer">
        <img src="../assets/images/Canteen.PNG" alt="">
        <span>Herald Canteen &nbsp;·&nbsp; Herald College Kathmandu</span>
    </div>

</div>

<script>
    function showToastAndGo() {
        var toast = document.getElementById('toast');
        toast.classList.add('show');
        setTimeout(function () {
            window.location.href = 'portal-login.php';
        }, 1700);
    }
</script>

</body>
</html>