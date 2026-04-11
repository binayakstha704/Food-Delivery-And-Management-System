<?php
// esewa_verify.php — eSewa v2 callback handler
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_payment'])) {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pending = $_SESSION['pending_payment'];

// ── eSewa sends encoded response as GET param ─────────────────────────────────
if (!isset($_GET['data'])) {
    header('Location: payment.php?status=failed');
    exit;
}

$decoded = json_decode(base64_decode($_GET['data']), true);

if (!$decoded) {
    header('Location: payment.php?status=failed');
    exit;
}

// ── Verify signature from eSewa ───────────────────────────────────────────────
$esewa_secret_key    = "8gBm/:&EnhH.1/q"; // sandbox
$esewa_merchant_code = "EPAYTEST";

$signed_field_names = $decoded['signed_field_names'] ?? '';
$fields             = explode(',', $signed_field_names);
$sign_parts         = [];
foreach ($fields as $field) {
    $field = trim($field);
    if ($field !== 'signature') {
        $sign_parts[] = "{$field}={$decoded[$field]}";
    }
}
$sign_string      = implode(',', $sign_parts);
$expected_sig     = base64_encode(hash_hmac('sha256', $sign_string, $esewa_secret_key, true));
$received_sig     = $decoded['signature'] ?? '';

if (!hash_equals($expected_sig, $received_sig)) {
    // Signature mismatch — possible tamper
    header('Location: payment.php?status=failed');
    exit;
}

// ── Check status from eSewa ───────────────────────────────────────────────────
$status           = $decoded['status'] ?? '';
$transaction_uuid = $decoded['transaction_uuid'] ?? '';
$total_amount     = $decoded['total_amount'] ?? 0;

if ($status !== 'COMPLETE') {
    header('Location: payment.php?status=failed');
    exit;
}

// ── Validate UUID matches pending session ─────────────────────────────────────
if ($transaction_uuid !== $pending['transaction_uuid']) {
    header('Location: payment.php?status=failed');
    exit;
}

// ── Insert order into DB ──────────────────────────────────────────────────────
$conn->begin_transaction();

try {
    $total = $pending['total'];

    // 1. Create order
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

    // 4. Add success notification
    $title   = "Order Placed Successfully";
    $message = "Your order #$order_id has been placed via eSewa. Total: Rs. " . number_format($total, 2);
    $stmt    = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();

    $conn->commit();

    unset($_SESSION['pending_payment']);

    // Redirect to orders with success
    header("Location: orders.php?payment=success&order_id=$order_id");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("eSewa order save failed: " . $e->getMessage());
    header('Location: payment.php?status=failed');
    exit;
}