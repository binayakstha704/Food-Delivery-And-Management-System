<?php
// my_cart.php — Herald Canteen
// DB-driven cart. PRG pattern prevents double-submit on refresh.
// Free delivery on orders >= Rs 500.

session_start();
require_once 'db.php';
require_once 'session_mock.php'; // Sprint 1 demo — remove before Sprint 2

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

define('FREE_DELIVERY_THRESHOLD', 500);
define('DELIVERY_FEE', 30);

// ------------------------------------------------------------------
// POST — handle cart actions, then redirect (PRG pattern)
// ------------------------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        header('Location: my_cart.php');
        exit;
    }

    $action  = $_POST['action']  ?? '';
    $cart_id = (int) ($_POST['cart_id'] ?? 0);

    if ($action === 'increase' && $cart_id) {
        // Fetch current price from menu separately to avoid subquery-on-same-table bug
        $price_stmt = $pdo->prepare(
            "SELECT m.price FROM cart c
             JOIN menu m ON c.item_id = m.item_id
             WHERE c.cart_id = ? AND c.user_id = ?"
        );
        $price_stmt->execute([$cart_id, $user_id]);
        $row = $price_stmt->fetch();
        if ($row) {
            $pdo->prepare(
                "UPDATE cart SET quantity = quantity + 1,
                 total_price = (quantity + 1) * ?
                 WHERE cart_id = ? AND user_id = ? AND quantity < 20"
            )->execute([$row['price'], $cart_id, $user_id]);
        }

    } elseif ($action === 'decrease' && $cart_id) {
        $stmt = $pdo->prepare(
            "SELECT c.quantity, m.price FROM cart c
             JOIN menu m ON c.item_id = m.item_id
             WHERE c.cart_id = ? AND c.user_id = ?"
        );
        $stmt->execute([$cart_id, $user_id]);
        $row = $stmt->fetch();
        if ($row) {
            if ((int) $row['quantity'] <= 1) {
                $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?")->execute([$cart_id, $user_id]);
            } else {
                $new_qty = (int) $row['quantity'] - 1;
                $pdo->prepare(
                    "UPDATE cart SET quantity = ?, total_price = ? WHERE cart_id = ? AND user_id = ?"
                )->execute([$new_qty, $new_qty * $row['price'], $cart_id, $user_id]);
            }
        }

    } elseif ($action === 'remove' && $cart_id) {
        $pdo->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?")->execute([$cart_id, $user_id]);

    } elseif ($action === 'clear_all') {
        $pdo->prepare("DELETE FROM cart WHERE user_id = ?")->execute([$user_id]);

    } elseif ($action === 'checkout') {
        header('Location: payment.php');
        exit;
    }

    header('Location: my_cart.php');
    exit;
}

// ------------------------------------------------------------------
// GET — fetch cart from DB
// ------------------------------------------------------------------
$stmt = $pdo->prepare(
    "SELECT c.cart_id, c.quantity, c.total_price,
            m.item_id, m.item_name, m.cuisine, m.price, m.availability
     FROM   cart c
     JOIN   menu m ON c.item_id = m.item_id
     WHERE  c.user_id = ?
     ORDER  BY c.added_at DESC"
);
$stmt->execute([$user_id]);
$cart_items = $stmt->fetchAll();

$subtotal    = 0;
foreach ($cart_items as $item) {
    $subtotal += (float) $item['total_price'];
}
$delivery    = ($subtotal > 0 && $subtotal < FREE_DELIVERY_THRESHOLD) ? DELIVERY_FEE : 0;
$grand_total = $subtotal + $delivery;
$progress    = $subtotal >= FREE_DELIVERY_THRESHOLD ? 100 : ($subtotal > 0 ? min(99, ($subtotal / FREE_DELIVERY_THRESHOLD) * 100) : 0);
$amount_away = FREE_DELIVERY_THRESHOLD - $subtotal;

$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

// Nepali food emoji map
$food_emojis = [
    'momo' => '🥟', 'dal bhat' => '🍛', 'chowmein' => '🍜',
    'thukpa' => '🍜', 'samosa' => '🥙', 'sel roti' => '🍩',
    'chicken' => '🍗', 'buff' => '🥩', 'rice' => '🍚',
    'tea' => '☕', 'lassi' => '🥛', 'aloo' => '🥔',
    'pani puri' => '🫙', 'chilli' => '🌶',
];
function get_food_emoji(string $name): string {
    $lower = strtolower($name);
    global $food_emojis;
    foreach ($food_emojis as $key => $emoji) {
        if (str_contains($lower, $key)) return $emoji;
    }
    return '🍱';
}

$name_parts = explode(' ', $_SESSION['name']);
$initials   = strtoupper($name_parts[0][0] . (count($name_parts) > 1 ? end($name_parts)[0] : ''));
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Cart — Herald Canteen</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<nav class="navbar">
    <a class="navbar-brand" href="chef_login.php">
        <img src="Canteen.PNG" alt="Herald Canteen" class="navbar-logo">
        <div class="navbar-title">
            Herald Canteen
            <span>Herald College Kathmandu</span>
        </div>
    </a>
    <ul class="navbar-nav">
        <li><a href="my_cart.php" class="active">🛒 Cart</a></li>
        <li><a href="my_orders.php">My Orders</a></li>
        <li><a href="user_profile.php">Profile</a></li>
    </ul>
    <div class="navbar-user">
        <div class="navbar-avatar"><?= htmlspecialchars($initials) ?></div>
        <span class="navbar-username"><?= htmlspecialchars($_SESSION['name']) ?></span>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>
</nav>

<div class="page-wrapper">

    <div class="page-heading">
        <h1>My Cart 🛒</h1>
        <p>Review your selected items before placing your order.</p>
    </div>

    <?php if ($flash_error): ?>
        <div class="alert alert-error">⚠ <?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>

        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>You haven't added anything yet. Head to the menu and grab something!</p>
                <a href="my_orders.php" class="btn btn-primary">View My Orders</a>
            </div>
        </div>

    <?php else: ?>

        <?php if ($subtotal < FREE_DELIVERY_THRESHOLD): ?>
            <div class="card delivery-progress-card">
                <div class="delivery-progress-header">
                    <span>🚀 Add <strong>Rs <?= number_format($amount_away, 0) ?></strong> more for <strong>FREE delivery!</strong></span>
                    <span><?= number_format($progress, 0) ?>%</span>
                </div>
                <div class="delivery-bar-track">
                    <div class="delivery-bar-fill" style="width:<?= $progress ?>%"></div>
                </div>
            </div>
        <?php else: ?>
            <div class="alert alert-success">🎉 You qualify for <strong>FREE delivery!</strong></div>
        <?php endif; ?>

        <div class="card card-no-pad">
            <div class="table-wrap">
                <table>
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Unit Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th>Remove</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($cart_items as $item): ?>
                        <tr>
                            <td>
                                <div class="item-cell">
                                    <span class="item-emoji"><?= get_food_emoji($item['item_name']) ?></span>
                                    <div>
                                        <div class="item-name"><?= htmlspecialchars($item['item_name']) ?></div>
                                        <div class="item-cuisine"><?= htmlspecialchars($item['cuisine']) ?></div>
                                        <?php if ($item['availability'] === 'out_of_stock'): ?>
                                            <span class="badge badge-red">Out of Stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td>Rs <?= number_format($item['price'], 0) ?></td>
                            <td>
                                <div class="qty-stepper">
                                    <form method="POST" action="my_cart.php" style="display:contents;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="cart_id"   value="<?= $item['cart_id'] ?>">
                                        <input type="hidden" name="action"    value="decrease">
                                        <button type="submit">−</button>
                                    </form>
                                    <span><?= (int) $item['quantity'] ?></span>
                                    <form method="POST" action="my_cart.php" style="display:contents;">
                                        <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                        <input type="hidden" name="cart_id"   value="<?= $item['cart_id'] ?>">
                                        <input type="hidden" name="action"    value="increase">
                                        <button type="submit">+</button>
                                    </form>
                                </div>
                            </td>
                            <td class="item-price-strong">Rs <?= number_format($item['total_price'], 0) ?></td>
                            <td>
                                <form method="POST" action="my_cart.php" onsubmit="return confirm('Remove this item?')">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                                    <input type="hidden" name="cart_id"   value="<?= $item['cart_id'] ?>">
                                    <input type="hidden" name="action"    value="remove">
                                    <button type="submit" class="btn btn-danger btn-sm">🗑</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        </div>

        <div class="cart-actions">
            <form method="POST" action="my_cart.php" onsubmit="return confirm('Clear your entire cart?')">
                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn btn-danger-outline btn-sm">🗑 Clear Cart</button>
            </form>
        </div>

        <div class="summary-strip">
            <div class="summary-left">
                <p class="total-label">Order Summary</p>
                <div class="summary-line">
                    <span>Subtotal</span>
                    <span>Rs <?= number_format($subtotal, 0) ?></span>
                </div>
                <div class="summary-line">
                    <span>Delivery</span>
                    <span>
                        <?php if ($delivery === 0): ?>
                            <span class="free-delivery">FREE</span>
                        <?php else: ?>
                            Rs <?= DELIVERY_FEE ?>
                        <?php endif; ?>
                    </span>
                </div>
                <p class="delivery-note">
                    <?php if ($delivery === 0): ?>
                        ✨ Free delivery applied — order above Rs <?= FREE_DELIVERY_THRESHOLD ?>
                    <?php else: ?>
                        Free delivery on orders above Rs <?= FREE_DELIVERY_THRESHOLD ?>
                    <?php endif; ?>
                </p>
            </div>
            <div class="summary-right">
                <p class="total-label">Grand Total</p>
                <p class="total-amount">Rs <?= number_format($grand_total, 0) ?></p>
                <form method="POST" action="my_cart.php">
                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
                    <input type="hidden" name="action" value="checkout">
                    <button type="submit" class="btn btn-primary">Proceed to Checkout →</button>
                </form>
            </div>
        </div>

    <?php endif; ?>

</div>

</body>
</html>
