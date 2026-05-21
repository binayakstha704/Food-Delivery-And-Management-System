<?php
// cod_confirm.php — handles Cash on Delivery orders
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_payment'])) {
    header('Location: dashboard.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: payment.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pending = $_SESSION['pending_payment'];

$received_uuid = $_POST['transaction_uuid'] ?? '';
if ($received_uuid !== $pending['transaction_uuid']) {
    header('Location: payment.php?status=failed');
    exit;
}

$conn->begin_transaction();

try {
    $total = $pending['total'];

    // 1. Create order with pending status
    $stmt = $conn->prepare("INSERT INTO orders (user_id, total_amount, status) VALUES (?, ?, 'pending')");
    $stmt->bind_param("id", $user_id, $total);
    $stmt->execute();
    $order_id = $conn->insert_id;

    // 2. Insert order items
    $stmt = $conn->prepare("INSERT INTO order_items (order_id, item_id, quantity, price) VALUES (?, ?, ?, ?)");
    foreach ($pending['cart_items'] as $item) {
        $stmt->bind_param("iiid", $order_id, $item['item_id'], $item['quantity'], $item['price']);
        $stmt->execute();
    }

    // 3. Clear cart
    $stmt = $conn->prepare("DELETE FROM cart WHERE user_id = ?");
    $stmt->bind_param("i", $user_id);
    $stmt->execute();

    // 4. Notification
    $title   = "Order Placed — Cash on Delivery";
    $message = "Your order #$order_id has been placed. Please pay Rs. " . number_format($total, 2) . " on delivery.";
    $stmt    = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();

    $conn->commit();

    unset($_SESSION['pending_payment']);

    header("Location: orders.php?payment=cod_success&order_id=$order_id");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("COD order save failed: " . $e->getMessage());
    header('Location: payment.php?status=failed');
    exit;
}