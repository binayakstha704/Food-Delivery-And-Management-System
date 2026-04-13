<?php
// my_orders.php — Herald Canteen
// Shows all orders for logged-in user with clickable stat cards and 4-status progress tracker.

session_start();
require_once 'db.php';
require_once 'session_mock.php'; // Sprint 1 demo — remove before Sprint 2

$user_id = (int) $_SESSION['user_id'];

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$allowed_filters = ['all', 'active', 'in_process', 'ready_for_delivery', 'on_delivery', 'delivered'];
$filter = in_array($_GET['filter'] ?? '', $allowed_filters) ? $_GET['filter'] : 'all';

if ($filter === 'all') {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM orders 
        WHERE user_id = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
} elseif ($filter === 'active') {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM orders 
        WHERE user_id = ? 
          AND order_status IN ('in_process', 'ready_for_delivery', 'on_delivery')
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id]);
} else {
    $stmt = $pdo->prepare("
        SELECT * 
        FROM orders 
        WHERE user_id = ? 
          AND order_status = ? 
        ORDER BY created_at DESC
    ");
    $stmt->execute([$user_id, $filter]);
}
$orders = $stmt->fetchAll();

$count_stmt = $pdo->prepare("
    SELECT order_status, COUNT(*) as cnt 
    FROM orders 
    WHERE user_id = ? 
    GROUP BY order_status
");
$count_stmt->execute([$user_id]);

$counts = [
    'all' => 0,
    'active' => 0,
    'in_process' => 0,
    'ready_for_delivery' => 0,
    'on_delivery' => 0,
    'delivered' => 0
];

foreach ($count_stmt->fetchAll() as $row) {
    $status = $row['order_status'];
    $cnt = (int) $row['cnt'];

    if (isset($counts[$status])) {
        $counts[$status] = $cnt;
    }

    $counts['all'] += $cnt;
}

$counts['active'] = $counts['in_process'] + $counts['ready_for_delivery'] + $counts['on_delivery'];

$order_ids   = array_column($orders, 'order_id');
$details_map = [];

if (!empty($order_ids)) {
    $placeholders = implode(',', array_fill(0, count($order_ids), '?'));
    $stmt = $pdo->prepare("
        SELECT od.order_id, od.quantity, od.price, m.item_name
        FROM order_details od
        JOIN menu m ON od.item_id = m.item_id
        WHERE od.order_id IN ($placeholders)
    ");
    $stmt->execute($order_ids);

    foreach ($stmt->fetchAll() as $d) {
        $details_map[$d['order_id']][] = $d;
    }
}

$spent_stmt = $pdo->prepare("
    SELECT COALESCE(SUM(total_amount), 0)
    FROM orders
    WHERE user_id = ?
      AND order_status = 'delivered'
");
$spent_stmt->execute([$user_id]);
$total_spent = (float) $spent_stmt->fetchColumn();

$status_steps = [
    'in_process' => ['label' => 'In Process', 'icon' => '👨‍🍳', 'step' => 1],
    'ready_for_delivery' => ['label' => 'Ready', 'icon' => '✅', 'step' => 2],
    'on_delivery' => ['label' => 'On the Way', 'icon' => '🛵', 'step' => 3],
    'delivered' => ['label' => 'Delivered', 'icon' => '🏠', 'step' => 4],
];

$all_steps = ['in_process', 'ready_for_delivery', 'on_delivery', 'delivered'];

function get_badge_class(string $s): string {
    return match($s) {
        'in_process'         => 'badge-yellow',
        'ready_for_delivery' => 'badge-blue',
        'on_delivery'        => 'badge-green',
        'delivered'          => 'badge-gray',
        default              => 'badge-gray',
    };
}

function get_status_label(string $s): string {
    return match($s) {
        'all'                => 'All Orders',
        'active'             => 'Active Orders',
        'in_process'         => 'In Process',
        'ready_for_delivery' => 'Ready for Delivery',
        'on_delivery'        => 'On Delivery',
        'delivered'          => 'Delivered',
        default              => ucfirst(str_replace('_', ' ', $s)),
    };
}

$initials = strtoupper(substr($_SESSION['name'], 0, 1));

$tab_labels = [
    'all'                => 'All',
    'active'             => '🔥 Active',
    'in_process'         => '👨‍🍳 In Process',
    'ready_for_delivery' => '✅ Ready',
    'on_delivery'        => '🛵 On Delivery',
    'delivered'          => '🏠 Delivered',
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders — Herald Canteen</title>
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
        <li><a href="my_orders.php" class="active">My Orders</a></li>
        <li><a href="user_profile.php">Profile</a></li>
    </ul>

    <div class="navbar-user">
        <div class="navbar-avatar"><?= htmlspecialchars($initials) ?></div>
        <form method="POST" action="logout.php">
            <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($_SESSION['csrf_token']) ?>">
            <button type="submit" class="btn-logout">Logout</button>
        </form>
    </div>
</nav>

<div class="page-wrapper">

    <div class="page-heading">
        <h1>My Orders</h1>
        <p>Track all your food orders and their live status.</p>
    </div>

    <div class="stats-grid">
        <a href="my_orders.php?filter=all" class="stat-card-link">
            <div class="stat-card <?= $filter === 'all' ? 'active-stat' : '' ?>">
                <div class="stat-val"><?= $counts['all'] ?></div>
                <div class="stat-lbl">Total Orders</div>
            </div>
        </a>

        <a href="my_orders.php?filter=delivered" class="stat-card-link">
            <div class="stat-card <?= $filter === 'delivered' ? 'active-stat' : '' ?>">
                <div class="stat-val"><?= $counts['delivered'] ?></div>
                <div class="stat-lbl">Delivered</div>
            </div>
        </a>

        <a href="my_orders.php?filter=active" class="stat-card-link">
            <div class="stat-card <?= $filter === 'active' ? 'active-stat' : '' ?>">
                <div class="stat-val"><?= $counts['active'] ?></div>
                <div class="stat-lbl">Active</div>
            </div>
        </a>

        <a href="my_orders.php?filter=delivered" class="stat-card-link">
            <div class="stat-card <?= $filter === 'delivered' ? 'active-stat' : '' ?>">
                <div class="stat-val">Rs <?= number_format($total_spent, 0) ?></div>
                <div class="stat-lbl">Total Spent</div>
            </div>
        </a>
    </div>

    <div class="tabs">
        <?php foreach ($tab_labels as $key => $label): ?>
            <a href="my_orders.php?filter=<?= urlencode($key) ?>"
               class="tab-btn <?= $filter === $key ? 'active' : '' ?>">
                <?= $label ?>
                <?php if (!empty($counts[$key]) && $counts[$key] > 0): ?>
                    <span class="tab-count"><?= $counts[$key] ?></span>
                <?php endif; ?>
            </a>
        <?php endforeach; ?>
    </div>

    <?php if (empty($orders)): ?>

        <div class="card">
            <div class="empty-state">
                <div class="empty-icon"></div>
                <h3>No orders found</h3>
                <p>
                    <?php if ($filter !== 'all'): ?>
                        No orders found for "<strong><?= htmlspecialchars(get_status_label($filter)) ?></strong>".
                        <a href="my_orders.php">View all orders</a>
                    <?php else: ?>
                        You haven't placed any orders yet.
                    <?php endif; ?>
                </p>
                <a href="menu.php" class="btn btn-primary">Browse Menu</a>
            </div>
        </div>

    <?php else: ?>

        <?php foreach ($orders as $order):
            $current_step = $status_steps[$order['order_status']]['step'] ?? 1;
            $order_items  = $details_map[$order['order_id']] ?? [];
        ?>
            <div class="card order-card">

                <div class="order-header">
                    <div>
                        <p class="order-id-label">Order #<?= str_pad($order['order_id'], 4, '0', STR_PAD_LEFT) ?></p>
                        <p class="order-date"><?= date('D, d M Y · g:i A', strtotime($order['created_at'])) ?></p>
                    </div>

                    <div class="order-header-right">
                        <span class="badge <?= get_badge_class($order['order_status']) ?>">
                            <?= $status_steps[$order['order_status']]['icon'] ?? '' ?>
                            <?= get_status_label($order['order_status']) ?>
                        </span>
                        <span class="order-total">Rs <?= number_format($order['total_amount'], 0) ?></span>
                    </div>
                </div>

                <div class="status-track">
                    <?php foreach ($all_steps as $step_key):
                        $step_num = $status_steps[$step_key]['step'];
                        $is_done = $step_num < $current_step;
                        $is_active = $step_num === $current_step;
                        $css_class = $is_done ? 'done' : ($is_active ? 'active' : '');
                    ?>
                        <div class="status-step <?= $css_class ?>">
                            <div class="status-dot">
                                <?= $is_done ? '✓' : ($is_active ? '●' : '○') ?>
                            </div>
                            <div class="status-label">
                                <?= $status_steps[$step_key]['icon'] ?><br>
                                <?= $status_steps[$step_key]['label'] ?>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>

                <?php if (!empty($order_items)): ?>
                    <div class="order-items-box">
                        <p class="order-items-label">Items Ordered</p>

                        <?php foreach ($order_items as $di): ?>
                            <div class="order-item-row">
                                <span>
                                    <?= htmlspecialchars($di['item_name']) ?>
                                    <span class="order-item-qty">× <?= (int) $di['quantity'] ?></span>
                                </span>
                                <span class="order-item-price">
                                    Rs <?= number_format($di['price'] * $di['quantity'], 0) ?>
                                </span>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>

                <div class="order-footer">
                    <?php if ($order['order_status'] === 'delivered'): ?>
                        <a href="menu.php" class="btn btn-outline btn-sm">🔄 Reorder</a>
                    <?php elseif ($order['order_status'] === 'on_delivery'): ?>
                        <span class="order-onway-note">On its way to you!</span>
                    <?php else: ?>
                        <span class="order-pending-note">⏳ Your order is being prepared...</span>
                    <?php endif; ?>
                </div>

            </div>
        <?php endforeach; ?>

    <?php endif; ?>

    <div>
        <a href="menu.php" class="btn btn-secondary">Order More Food</a>
    </div>

</div>

</body>
</html>