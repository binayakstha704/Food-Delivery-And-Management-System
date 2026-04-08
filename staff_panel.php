<?php
session_start();
require_once __DIR__ . '/config/db2.php';

// AUTH CHECK
if (!isset($_SESSION['staff_id']) || $_SESSION['staff_role'] !== 'staff') {
    header("Location: staff.php");
    exit;
}

$success = "";
$error = "";

// LOGOUT
if (isset($_GET['logout'])) {
    session_destroy();
    header("Location: staff.php");
    exit;
}

// UPDATE DELIVERY
if (isset($_POST['update_delivery'])) {
    $id = $_POST['order_id'];

    $stmt = $pdo->prepare("UPDATE orderss SET delivery_status='Delivered' WHERE id=?");
    $stmt->execute([$id]);

    $success = "Order #$id marked Delivered";
}

// UPDATE PAYMENT
if (isset($_POST['update_payment'])) {
    $id = $_POST['order_id'];

    $stmt = $pdo->prepare("UPDATE orderss SET payment_status='Successful' WHERE id=?");
    $stmt->execute([$id]);

    $success = "Order #$id marked Paid";
}

// FETCH ORDERS
$orders = $pdo->query("SELECT * FROM orderss ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);

// STATS
$total = count($orders);
$delivered = 0;
$pending = 0;

foreach ($orders as $o) {
    if ($o['delivery_status'] == 'Delivered') $delivered++;
    if ($o['payment_status'] == 'Pending') $pending++;
}
?>

<!DOCTYPE html>
<html>
<head>
<title>Staff Panel</title>
<link rel="stylesheet" href="panel.css">
</head>
<body>

<header class="top">
    <h2>🍽️ Staff Panel</h2>
    <div>
        👤 <?= $_SESSION['staff_name'] ?>
        <a href="?logout=1" class="logout">Logout</a>
    </div>
</header>

<div class="container">

    <?php if($success): ?>
        <div class="msg ok">✅ <?= $success ?></div>
    <?php endif; ?>

    <?php if($error): ?>
        <div class="msg err">❌ <?= $error ?></div>
    <?php endif; ?>

    <!-- STATS -->
    <div class="stats">
        <div class="card total">📦<br><b><?= $total ?></b><span>Total Orders</span></div>
        <div class="card delivered">✅<br><b><?= $delivered ?></b><span>Delivered</span></div>
        <div class="card pending">💳<br><b><?= $pending ?></b><span>Pending</span></div>
    </div>

    <!-- TABLE -->
    <table>
        <tr>
            <th>ID</th>
            <th>Name</th>
            <th>Item</th>
            <th>Qty</th>
            <th>Amount</th>
            <th>Delivery</th>
            <th>Payment</th>
            <th>Action</th>
        </tr>

        <?php foreach($orders as $o): ?>
        <tr>
            <td>#<?= $o['id'] ?></td>
            <td><?= $o['customer_name'] ?></td>
            <td><?= $o['item_name'] ?></td>
            <td><?= $o['quantity'] ?></td>
            <td class="price">Rs <?= $o['total_amount'] ?></td>

            <!-- DELIVERY STATUS -->
            <td>
                <?php if($o['delivery_status']=='Delivered'): ?>
                    <span class="badge green">Delivered</span>
                <?php else: ?>
                    <span class="badge yellow">On Delivery</span>
                <?php endif; ?>
            </td>

            <!-- PAYMENT STATUS -->
            <td>
                <?php if($o['payment_status']=='Successful'): ?>
                    <span class="badge blue">Paid</span>
                <?php else: ?>
                    <span class="badge red">Pending</span>
                <?php endif; ?>
            </td>

            <!-- ACTION BUTTONS -->
            <td>
                <div class="actions">

                <?php if($o['delivery_status']!='Delivered'): ?>
                <form method="POST" onsubmit="return confirm('Mark as Delivered?')">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <button class="btn deliver" name="update_delivery">
                        🚴 Deliver
                    </button>
                </form>
                <?php else: ?>
                    <span class="done">✔</span>
                <?php endif; ?>

                <?php if($o['payment_status']!='Successful'): ?>
                <form method="POST" onsubmit="return confirm('Mark as Paid?')">
                    <input type="hidden" name="order_id" value="<?= $o['id'] ?>">
                    <button class="btn pay" name="update_payment">
                        💳 Paid
                    </button>
                </form>
                <?php else: ?>
                    <span class="done">✔</span>
                <?php endif; ?>

                </div>
            </td>
        </tr>
        <?php endforeach; ?>
    </table>

</div>

</body>
</html>