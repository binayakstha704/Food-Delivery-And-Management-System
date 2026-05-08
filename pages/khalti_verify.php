<?php
// khalti_verify.php — Khalti return URL handler
session_start();
require '../config/db.php';

if (!isset($_SESSION['user_id']) || !isset($_SESSION['pending_payment'])) {
    header('Location: dashboard.php');
    exit;
}

$user_id = $_SESSION['user_id'];
$pending = $_SESSION['pending_payment'];

// Khalti returns pidx and status in GET params
$pidx   = $_GET['pidx']   ?? '';
$status = $_GET['status'] ?? '';

if ($status !== 'Completed' || empty($pidx)) {
    header('Location: payment.php?status=failed');
    exit;
}

// ── Verify with Khalti lookup API ─────────────────────────────────────────────
$khalti_secret_key  = "test_secret_key_f59e8b7d18b4499ca40f68195a846e9b";
$khalti_lookup_url  = "https://a.khalti.com/api/v2/epayment/lookup/";

$ch = curl_init($khalti_lookup_url);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST           => true,
    CURLOPT_POSTFIELDS     => json_encode(['pidx' => $pidx]),
    CURLOPT_HTTPHEADER     => [
        "Authorization: Key $khalti_secret_key",
        "Content-Type: application/json",
    ],
]);
$response  = curl_exec($ch);
$http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
curl_close($ch);

$result = json_decode($response, true);

if ($http_code !== 200 || ($result['status'] ?? '') !== 'Completed') {
    error_log("Khalti lookup failed: " . $response);
    header('Location: payment.php?status=failed');
    exit;
}

// ── Validate amount ───────────────────────────────────────────────────────────
$expected_paisa = (int)($pending['total'] * 100);
$received_paisa = (int)($result['total_amount'] ?? 0);

if ($received_paisa !== $expected_paisa) {
    error_log("Khalti amount mismatch: expected $expected_paisa got $received_paisa");
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

    // 4. Notification
    $title   = "Order Placed Successfully";
    $message = "Your order #$order_id has been placed via Khalti. Total: Rs. " . number_format($total, 2);
    $stmt    = $conn->prepare("INSERT INTO notifications (user_id, title, message, type) VALUES (?, ?, ?, 'order')");
    $stmt->bind_param("iss", $user_id, $title, $message);
    $stmt->execute();

    $conn->commit();

    unset($_SESSION['pending_payment']);

    header("Location: my_orders.php?payment=success&order_id=$order_id");
    exit;

} catch (Exception $e) {
    $conn->rollback();
    error_log("Khalti order save failed: " . $e->getMessage());
    header('Location: payment.php?status=failed');
    exit;
}