<?php
// my_cart.php — Herald Canteen

session_start();

if (file_exists(__DIR__ . '/../config/db.php')) {
    require_once __DIR__ . '/../config/db.php';
} else {
    die('Database config not found.');
}

if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

define('FREE_DELIVERY_THRESHOLD', 500);
define('DELIVERY_FEE', 30);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (empty($_POST['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $_POST['csrf_token'])) {
        $_SESSION['flash_error'] = 'Invalid request. Please try again.';
        header('Location: my_cart.php');
        exit;
    }

    $action  = $_POST['action'] ?? '';
    $cart_id = (int) ($_POST['cart_id'] ?? 0);

    if ($action === 'increase' && $cart_id) {
        // Get current quantity and price
        $stmt = $conn->prepare("SELECT c.quantity, m.price FROM cart c JOIN menu_items m ON c.item_id = m.item_id WHERE c.cart_id = ? AND c.user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $row = $result->fetch_assoc();
        
        if ($row && $row['quantity'] < 20) {
            $new_qty = $row['quantity'] + 1;
            $new_total = $new_qty * $row['price'];
            $stmt = $conn->prepare("UPDATE cart SET quantity = ?, total_price = ? WHERE cart_id = ? AND user_id = ?");
            $stmt->bind_param("idii", $new_qty, $new_total, $cart_id, $user_id);
            $stmt->execute();
        }

    } elseif ($action === 'decrease' && $cart_id) {
        $stmt = $conn->prepare("SELECT c.quantity, m.price FROM cart c JOIN menu_items m ON c.item_id = m.item_id WHERE c.cart_id = ? AND c.user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();

        if ($row) {
            if ((int)$row['quantity'] <= 1) {
                $stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
                $stmt->bind_param("ii", $cart_id, $user_id);
                $stmt->execute();
            } else {
                $new_qty = (int)$row['quantity'] - 1;
                $new_total = $new_qty * $row['price'];
                $stmt = $conn->prepare("UPDATE cart SET quantity = ?, total_price = ? WHERE cart_id = ? AND user_id = ?");
                $stmt->bind_param("idii", $new_qty, $new_total, $cart_id, $user_id);
                $stmt->execute();
            }
        }

    } elseif ($action === 'remove' && $cart_id) {
        $stmt = $conn->prepare("DELETE FROM cart WHERE cart_id = ? AND user_id = ?");
        $stmt->bind_param("ii", $cart_id, $user_id);
        $stmt->execute();

    } elseif ($action === 'clear_all') {
        $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
        $stmt->bind_param("i", $user_id);
        $stmt->execute();

    } elseif ($action === 'checkout') {
        header('Location: payment.php');
        exit;
    }

    header('Location: my_cart.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, c.total_price as cart_total_price,
           m.item_id, m.name AS item_name, m.description, m.price, m.is_available
    FROM cart c
    JOIN menu_items m ON c.item_id = m.item_id
    WHERE c.user_id = ?
    ORDER BY c.added_at DESC
");

$stmt->bind_param("i", $user_id);
$stmt->execute();
$result = $stmt->get_result();

$cart_items = [];
while ($row = $result->fetch_assoc()) {
    $row['total_price'] = $row['quantity'] * $row['price'];
    $cart_items[] = $row;
}

$subtotal = 0;
foreach ($cart_items as $item) {
    $subtotal += $item['total_price'];
}

$delivery = ($subtotal > 0 && $subtotal < FREE_DELIVERY_THRESHOLD) ? DELIVERY_FEE : 0;
$grand_total = $subtotal + $delivery;

$flash_error = $_SESSION['flash_error'] ?? '';
unset($_SESSION['flash_error']);

function get_food_emoji(string $name): string {
    $map = [
        'momo'=>'🥟','burger'=>'🍔','pizza'=>'🍕','rice'=>'🍚',
        'noodle'=>'🍜','chicken'=>'🍗','tea'=>'☕','coffee'=>'☕',
        'cake'=>'🎂','soup'=>'🍲','pasta'=>'🍝','sandwich'=>'🥪',
        'roll'=>'🌯','drink'=>'🥤','coffee'=>'☕','dessert'=>'🍰'
    ];
    $lower = strtolower($name);
    foreach ($map as $k => $e) {
        if (str_contains($lower, $k)) return $e;
    }
    return '🍱';
}

$full_name = $_SESSION['full_name'] ?? 'User';
$name_parts = explode(' ', $full_name);
$initials = strtoupper($name_parts[0][0] . ($name_parts[1][0] ?? ''));
?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>My Cart — Herald Canteen</title>
<link rel="stylesheet" href="../assets/css/style.css">
<style>
.item-cell {
    display: flex;
    align-items: center;
    gap: 12px;
}
.item-emoji {
    font-size: 32px;
}
.item-name {
    font-weight: 600;
    margin-bottom: 4px;
}
.item-cuisine {
    font-size: 12px;
    color: #666;
}
.qty-stepper {
    display: flex;
    align-items: center;
    gap: 12px;
}
.qty-stepper button {
    background: #f0f0f0;
    border: none;
    width: 32px;
    height: 32px;
    border-radius: 8px;
    cursor: pointer;
    font-size: 18px;
    font-weight: bold;
}
.qty-stepper button:hover {
    background: #e0e0e0;
}
.item-price-strong {
    font-weight: 600;
    color: #e67e22;
}
.btn-danger {
    background: #fee;
    color: #e74c3c;
    border: none;
    padding: 8px 12px;
    border-radius: 8px;
    cursor: pointer;
}
.btn-danger:hover {
    background: #fdd;
}
</style>
</head>
<body>

<nav class="navbar">
    <div class="navbar-title">
        Herald Canteen
        <span>Herald College Kathmandu</span>
    </div>

    <ul class="navbar-nav">
        <li><a href="dashboard.php">Home</a></li>
        <li><a class="active" href="my_cart.php">Cart</a></li>
        <li><a href="my_orders.php">Orders</a></li>
    </ul>

    <div class="navbar-user">
        <div class="navbar-avatar"><?= htmlspecialchars($initials) ?></div>
        <span class="navbar-username"><?= htmlspecialchars($full_name) ?></span>

        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
            <button class="btn-logout">Logout</button>
        </form>
    </div>
</nav>

<div class="page-wrapper">

    <div class="page-heading">
        <h1>My Cart 🛒</h1>
        <p>Review items before checkout</p>
    </div>

    <?php if ($flash_error): ?>
        <div class="alert alert-error"><?= htmlspecialchars($flash_error) ?></div>
    <?php endif; ?>

    <?php if (empty($cart_items)): ?>

        <div class="card">
            <div class="empty-state">
                <div class="empty-icon">🛒</div>
                <h3>Your cart is empty</h3>
                <p>Add items from menu</p>
                <a href="dashboard.php" class="btn btn-primary">Browse Menu</a>
            </div>
        </div>

    <?php else: ?>

        <div class="card">
            <table class="cart-table">
                <thead>
                    <tr>
                        <th>Item</th>
                        <th>Price</th>
                        <th>Qty</th>
                        <th>Total</th>
                        <th></th>
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
                                    <div class="item-cuisine"><?= htmlspecialchars($item['description']) ?></div>
                                </div>
                            </div>
                        </td>

                        <td>Rs <?= number_format($item['price']) ?></td>

                        <td>
                            <div class="qty-stepper">
                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="decrease">
                                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                    <button type="submit">−</button>
                                </form>

                                <span><?= $item['quantity'] ?></span>

                                <form method="POST">
                                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                    <input type="hidden" name="action" value="increase">
                                    <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                    <button type="submit">+</button>
                                </form>
                            </div>
                        </td>

                        <td class="item-price-strong">
                            Rs <?= number_format($item['total_price']) ?>
                        </td>

                        <td>
                            <form method="POST">
                                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                                <input type="hidden" name="action" value="remove">
                                <input type="hidden" name="cart_id" value="<?= $item['cart_id'] ?>">
                                <button type="submit" class="btn-danger">🗑 Remove</button>
                            </form>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <div class="summary-strip">
            <div class="summary-left">
                <div class="summary-line">
                    <span>Subtotal</span>
                    <span>Rs <?= number_format($subtotal) ?></span>
                </div>

                <div class="summary-line">
                    <span>Delivery</span>
                    <span><?= $delivery ? "Rs $delivery" : "FREE" ?></span>
                </div>
            </div>

            <div class="summary-right">
                <div class="total-label">Total</div>
                <div class="total-amount">Rs <?= number_format($grand_total) ?></div>

                <form method="POST">
                    <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                    <input type="hidden" name="action" value="checkout">
                    <button type="submit" class="btn btn-primary">Proceed to Checkout</button>
                </form>
            </div>
        </div>

        <div style="margin-top: 20px; text-align: center;">
            <form method="POST">
                <input type="hidden" name="csrf_token" value="<?= $_SESSION['csrf_token'] ?>">
                <input type="hidden" name="action" value="clear_all">
                <button type="submit" class="btn-danger" style="padding: 10px 20px;" onclick="return confirm('Clear entire cart?')">Clear Cart</button>
            </form>
        </div>

    <?php endif; ?>

</div>

</body>
</html>