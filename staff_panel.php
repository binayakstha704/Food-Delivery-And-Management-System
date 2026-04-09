<?php
session_start();
require_once __DIR__ . '/config/db2.php';

if (!isset($_SESSION['staff_id']) || $_SESSION['staff_role'] !== 'staff') {
    header("Location: staff.php");
    exit;
}

$success = "";
$error   = "";

if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: staff.php");
    exit;
}

if (isset($_POST['update_delivery'])) {
    $id   = $_POST['order_id'];
    $stmt = $pdo->prepare("UPDATE orderss SET delivery_status='Delivered' WHERE id=?");
    $stmt->execute([$id]);
    $success = "Order #$id marked as Delivered";
}

if (isset($_POST['update_payment'])) {
    $id   = $_POST['order_id'];
    $stmt = $pdo->prepare("UPDATE orderss SET payment_status='Successful' WHERE id=?");
    $stmt->execute([$id]);
    $success = "Order #$id marked as Paid";
}

$orders    = $pdo->query("SELECT * FROM orderss ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
$total     = count($orders);
$delivered = 0;
$pending   = 0;

foreach ($orders as $o) {
    if ($o['delivery_status'] == 'Delivered')   $delivered++;
    if ($o['payment_status']  == 'Pending')     $pending++;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Herald Canteen — Staff Panel</title>
<link rel="stylesheet" href="panel.css">
<link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;600;700&family=Outfit:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>

<header class="top">
    <h2>🌿 Herald Canteen</h2>
    <div>
        <span>👤 <?= htmlspecialchars($_SESSION['staff_name']) ?></span>
        <a href="?logout=1" class="logout">⬡ Logout</a>
    </div>
</header>

<div class="container">

    <?php if($success): ?>
        <div class="msg ok">✅ <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="msg err">❌ <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats">
        <div class="card total">
            <span class="card-icon">📦</span>
            <b><?= $total ?></b>
            <span>Total Orders</span>
        </div>
        <div class="card delivered">
            <span class="card-icon">✅</span>
            <b><?= $delivered ?></b>
            <span>Delivered</span>
        </div>
        <div class="card pending">
            <span class="card-icon">💳</span>
            <b><?= $pending ?></b>
            <span>Payment Pending</span>
        </div>
    </div>

    <!-- TABLE -->
    <div class="table-wrapper">
        <div class="table-header">
            <h3>📋 Order Management</h3>
        </div>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Item</th>
                    <th>Qty</th>
                    <th>Amount</th>
                    <th>Delivery</th>
                    <th>Payment</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($orders as $o): ?>
                <tr>
                    <td><span style="color:var(--text-dim);font-size:12px;">#</span><?= $o['id'] ?></td>
                    <td><?= htmlspecialchars($o['customer_name']) ?></td>
                    <td><?= htmlspecialchars($o['item_name']) ?></td>
                    <td><?= $o['quantity'] ?></td>
                    <td class="price">Rs <?= number_format($o['total_amount']) ?></td>

                    <td>
                        <?php if($o['delivery_status']=='Delivered'): ?>
                            <span class="badge green">Delivered</span>
                        <?php else: ?>
                            <span class="badge yellow">On Delivery</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <?php if($o['payment_status']=='Successful'): ?>
                            <span class="badge blue">Paid</span>
                        <?php else: ?>
                            <span class="badge red">Pending</span>
                        <?php endif; ?>
                    </td>

                    <td>
                        <div class="actions">
                            <?php if($o['delivery_status'] != 'Delivered'): ?>
                            <form method="POST" onsubmit="return confirm('Mark Order #<?= $o['id'] ?> as Delivered?')">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button class="btn deliver" name="update_delivery">🚴 Deliver</button>
                            </form>
                            <?php else: ?>
                                <span class="done">✔</span>
                            <?php endif; ?>

                            <?php if($o['payment_status'] != 'Successful'): ?>
                            <form method="POST" onsubmit="return confirm('Mark Order #<?= $o['id'] ?> as Paid?')">
                                <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                                <button class="btn pay" name="update_payment">💳 Paid</button>
                            </form>
                            <?php else: ?>
                                <span class="done">✔</span>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>

                <?php if(empty($orders)): ?>
                <tr><td colspan="8" style="text-align:center;padding:40px;color:var(--text-muted);">No orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

</div>
</body>
</html>