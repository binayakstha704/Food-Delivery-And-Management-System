<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth.php";
require_once "../includes/functions.php";

require_role('staff');

$field_errors = [];
$message = '';

// Helper function for consistent sanitization
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------
   HANDLE DELIVERY STATUS UPDATE
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_status'])) {
    // Validate order_id with proper filtering
    $order_id_raw = $_POST['order_id'] ?? 0;
    $order_id = filter_var($order_id_raw, FILTER_VALIDATE_INT);
    
    if ($order_id === false || $order_id <= 0) {
        $field_errors['order_id'] = 'Valid order ID is required.';
    } elseif ($order_id > 9999999) {
        $field_errors['order_id'] = 'Invalid order ID value.';
    }
    
    $new_status = clean_input($_POST['new_status'] ?? '');

    // Trim and validate new_status with whitespace check
    $trimmed_status = trim($new_status);
    if ($new_status === '') {
        $field_errors['new_status'] = 'Delivery status is required.';
    } elseif ($trimmed_status === '') {
        $field_errors['new_status'] = 'Delivery status cannot consist of whitespace characters only.';
    } elseif (strlen($trimmed_status) > 50) {
        $field_errors['new_status'] = 'Delivery status value is too long.';
    } elseif (!in_array($trimmed_status, ['out_for_delivery', 'delivered'], true)) {
        $field_errors['new_status'] = 'Invalid delivery status value.';
    } else {
        $new_status = $trimmed_status;
    }

    if (empty($field_errors)) {
        $stmt = $conn->prepare("
            SELECT 
                o.status,
                o.payment_method,
                COALESCE(p.payment_status, 'missing') AS payment_status
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.order_id
            WHERE o.order_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $current_order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current_order) {
            $current_status = $current_order['status'];
            $payment_status = $current_order['payment_status'];

            $allowed_transition =
                ($current_status === 'ready' && $new_status === 'out_for_delivery') ||
                ($current_status === 'out_for_delivery' && $new_status === 'delivered');

            if (!$allowed_transition) {
                $field_errors['general'] = "Invalid delivery status transition for staff.";
            } elseif ($new_status === 'delivered' && $payment_status !== 'successful') {
                $field_errors['general'] = "Order cannot be marked as delivered until payment is successful.";
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $stmt->bind_param("si", $new_status, $order_id);

                if ($stmt->execute()) {
                    $message = "Delivery status updated successfully.";
                } else {
                    $field_errors['general'] = "Failed to update delivery status.";
                }

                $stmt->close();
            }
        } else {
            $field_errors['order_id'] = "Order not found.";
        }
    }
}

/* ---------------------------
   HANDLE COD PAYMENT CONFIRMATION
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['confirm_cod_payment'])) {
    // Validate order_id with proper filtering
    $order_id_raw = $_POST['order_id'] ?? 0;
    $order_id = filter_var($order_id_raw, FILTER_VALIDATE_INT);

    if ($order_id === false || $order_id <= 0) {
        $field_errors['order_id'] = "Valid order ID is required.";
    } elseif ($order_id > 9999999) {
        $field_errors['order_id'] = "Invalid order ID value.";
    }

    if (empty($field_errors)) {
        $stmt = $conn->prepare("
            SELECT 
                o.status,
                o.payment_method,
                o.total_amount,
                p.payment_id,
                p.payment_status
            FROM orders o
            LEFT JOIN payments p ON p.order_id = o.order_id
            WHERE o.order_id = ?
            LIMIT 1
        ");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $payment_row = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if (!$payment_row) {
            $field_errors['order_id'] = "Order not found.";
        } elseif ($payment_row['payment_method'] !== 'cod') {
            $field_errors['general'] = "Manual payment confirmation is only allowed for COD orders.";
        } elseif ($payment_row['status'] !== 'out_for_delivery') {
            $field_errors['general'] = "COD payment can only be confirmed when the order is out for delivery.";
        } elseif (!empty($payment_row['payment_id']) && $payment_row['payment_status'] === 'successful') {
            $field_errors['general'] = "COD payment is already confirmed.";
        } else {
            if (empty($payment_row['payment_id'])) {
                $payment_method = 'cod';
                $payment_status = 'successful';
                $amount = (float)$payment_row['total_amount'];
                
                // Validate amount
                if ($amount <= 0) {
                    $field_errors['general'] = "Invalid order amount.";
                } else {
                    $stmt = $conn->prepare("
                        INSERT INTO payments (order_id, payment_method, payment_status, amount, paid_at)
                        VALUES (?, ?, ?, ?, NOW())
                    ");
                    $stmt->bind_param("issd", $order_id, $payment_method, $payment_status, $amount);

                    if ($stmt->execute()) {
                        $message = "COD payment recorded successfully.";
                    } else {
                        $field_errors['general'] = "Failed to record COD payment.";
                    }

                    $stmt->close();
                }
            } else {
                $payment_status = 'successful';

                $stmt = $conn->prepare("
                    UPDATE payments
                    SET payment_status = ?, paid_at = NOW()
                    WHERE order_id = ?
                ");
                $stmt->bind_param("si", $payment_status, $order_id);

                if ($stmt->execute()) {
                    $message = "COD payment confirmed successfully.";
                } else {
                    $field_errors['general'] = "Failed to confirm COD payment.";
                }

                $stmt->close();
            }
        }
    }
}

/* ---------------------------
   FETCH ORDER SUMMARY COUNTS
---------------------------- */
$summary = [
    'ready' => 0,
    'out_for_delivery' => 0,
    'delivered' => 0,
    'pending_payments' => 0
];

// Using prepared statement for consistency
$count_stmt = $conn->prepare("
    SELECT status, COUNT(*) AS total
    FROM orders
    WHERE status IN ('ready', 'out_for_delivery', 'delivered')
    GROUP BY status
");
$count_stmt->execute();
$count_result = $count_stmt->get_result();

if ($count_result) {
    while ($row = $count_result->fetch_assoc()) {
        $summary[$row['status']] = (int)$row['total'];
    }
}
$count_stmt->close();

$pending_stmt = $conn->prepare("
    SELECT COUNT(*) AS total
    FROM payments
    WHERE payment_status = 'pending'
");
$pending_stmt->execute();
$pending_payment_result = $pending_stmt->get_result();

if ($pending_payment_result) {
    $summary['pending_payments'] = (int)($pending_payment_result->fetch_assoc()['total'] ?? 0);
}
$pending_stmt->close();

/* ---------------------------
   FETCH STAFF ORDERS
---------------------------- */
$orders_stmt = $conn->prepare("
    SELECT
        o.order_id,
        u.full_name,
        o.total_amount,
        o.status,
        o.payment_method,
        o.created_at,
        COALESCE(MAX(p.payment_status), 'missing') AS payment_status,
        GROUP_CONCAT(
            CONCAT(mi.name, ' x', oi.quantity)
            ORDER BY mi.name SEPARATOR ', '
        ) AS items_summary
    FROM orders o
    INNER JOIN users u
        ON o.user_id = u.user_id
    LEFT JOIN payments p
        ON p.order_id = o.order_id
    LEFT JOIN order_items oi
        ON oi.order_id = o.order_id
    LEFT JOIN menu_items mi
        ON mi.item_id = oi.item_id
    WHERE o.status IN ('ready', 'out_for_delivery', 'delivered')
    GROUP BY
        o.order_id,
        u.full_name,
        o.total_amount,
        o.status,
        o.payment_method,
        o.created_at
    ORDER BY o.order_id DESC
");
$orders_stmt->execute();
$orders = $orders_stmt->get_result();
$orders_stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Staff Control Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">

    <style>
        :root {
            --staff-primary: #1565c0;
            --staff-primary-dark: #0d47a1;
            --staff-soft: #e3f2fd;
        }

        body {
            color: #222;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--staff-primary), var(--staff-primary-dark));
        }

        .navbar-title,
        .navbar-title span {
            color: #fff;
        }

        .sidebar nav a {
            color: #f5f5f5;
        }

        .sidebar nav a.active,
        .sidebar nav a:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        .topbar {
            border-bottom: 2px solid var(--staff-soft);
        }

        .topbar div {
            color: var(--staff-primary) !important;
        }

        .section-title h2 {
            color: var(--staff-primary) !important;
        }

        .section-title p {
            color: #555 !important;
        }

        .panel-card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .panel-card,
        .panel-card * {
            color: #222;
        }

        .panel-card h3 {
            margin-bottom: 18px;
            color: var(--staff-primary) !important;
        }

        .summary-grid {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 18px;
            margin-bottom: 24px;
        }

        .summary-card {
            background: #fff;
            border-radius: 16px;
            padding: 18px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .summary-card h4 {
            margin-bottom: 8px;
            color: var(--staff-primary) !important;
            font-size: 15px;
        }

        .summary-card .count {
            font-size: 28px;
            font-weight: 700;
            color: #222 !important;
        }

        .staff-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
        }

        .staff-table th,
        .staff-table td {
            border: 1px solid #eee;
            padding: 12px;
            text-align: left;
            vertical-align: top;
            color: #222 !important;
        }

        .staff-table th {
            background: var(--staff-soft);
            color: var(--staff-primary-dark) !important;
        }

        .staff-btn {
            background: var(--staff-primary);
            color: #fff !important;
            border: none;
            padding: 10px 14px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .staff-btn:hover {
            background: var(--staff-primary-dark);
        }

        .staff-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }

        .action-stack {
            display: flex;
            flex-direction: column;
            gap: 10px;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: white !important;
            text-transform: capitalize;
        }

        .status-ready { background: #2e7d32; }
        .status-out_for_delivery { background: #ef6c00; }
        .status-delivered { background: #6d4c41; }

        .payment-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: white !important;
            text-transform: capitalize;
        }

        .payment-pending { background: #d97706; }
        .payment-successful { background: #2e7d32; }
        .payment-failed { background: #c62828; }
        .payment-missing { background: #757575; }

        .alert-success {
            background: #e8f5e9;
            color: #1b5e20 !important;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .alert-error {
            background: #ffebee;
            color: #b71c1c !important;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .muted-text {
            color: #666 !important;
            font-size: 13px;
        }

        .field-error {
            display: block;
            color: #c0392b;
            font-size: 13px;
            margin-top: 5px;
        }

        @media (max-width: 1100px) {
            .summary-grid {
                grid-template-columns: 1fr 1fr;
            }
        }

        @media (max-width: 700px) {
            .summary-grid {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>

<div class="layout">

    <div class="sidebar">
        <div class="navbar-title">
            Herald Canteen
            <span>Staff Portal</span>
        </div>
        <nav>
            <a href="staff-control.php" class="active">🧾 Staff Home</a>
            <a href="#orders-section">📦 Delivery & Payments</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </div>

    <div class="main">

        <div class="topbar">
            <div style="font-weight:600;">
                Welcome, <?php echo sanitize_output($_SESSION['full_name']); ?>
            </div>
        </div>

        <div class="content">

            <div class="section-title">
                <h2>Staff Control Panel</h2>
                <p>Manage delivery progress and payment completion.</p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert-success">✓ <?php echo sanitize_output($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($field_errors['general'])): ?>
                <div class="alert-error">⚠️ <?php echo sanitize_output($field_errors['general']); ?></div>
            <?php endif; ?>

            <div class="summary-grid">
                <div class="summary-card">
                    <h4>Ready Orders</h4>
                    <div class="count"><?php echo $summary['ready']; ?></div>
                </div>
                <div class="summary-card">
                    <h4>On Delivery</h4>
                    <div class="count"><?php echo $summary['out_for_delivery']; ?></div>
                </div>
                <div class="summary-card">
                    <h4>Delivered</h4>
                    <div class="count"><?php echo $summary['delivered']; ?></div>
                </div>
                <div class="summary-card">
                    <h4>Pending Payments</h4>
                    <div class="count"><?php echo $summary['pending_payments']; ?></div>
                </div>
            </div>

            <div class="panel-card" id="orders-section">
                <h3>Delivery & Payment Orders</h3>

                <?php if (!empty($field_errors['order_id'])): ?>
                    <div class="alert-error">⚠️ <?php echo sanitize_output($field_errors['order_id']); ?></div>
                <?php endif; ?>

                <?php if (!empty($field_errors['new_status'])): ?>
                    <div class="alert-error">⚠️ <?php echo sanitize_output($field_errors['new_status']); ?></div>
                <?php endif; ?>

                <table class="staff-table">
                    <thead>
                        <tr>
                            <th>ID</th>
                            <th>Customer</th>
                            <th>Items</th>
                            <th>Total Amount</th>
                            <th>Delivery Status</th>
                            <th>Payment Method</th>
                            <th>Payment Status</th>
                            <th>Created At</th>
                            <th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ($orders && $orders->num_rows > 0): ?>
                            <?php $counter = 1; ?>
                            <?php while ($order = $orders->fetch_assoc()): ?>
                                <tr>
                                    <td><?php echo $counter++; ?></td>
                                    <td><?php echo sanitize_output($order['full_name']); ?></td>
                                    <td><?php echo sanitize_output($order['items_summary'] ?? 'No items found'); ?></td>
                                    <td>Rs. <?php echo number_format((float)$order['total_amount'], 2); ?></td>
                                    <td>
                                        <span class="status-badge status-<?php echo sanitize_output($order['status']); ?>">
                                            <?php echo sanitize_output(str_replace('_', ' ', $order['status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo sanitize_output(strtoupper($order['payment_method'])); ?></td>
                                    <td>
                                        <span class="payment-badge payment-<?php echo sanitize_output($order['payment_status']); ?>">
                                            <?php echo sanitize_output(str_replace('_', ' ', $order['payment_status'])); ?>
                                        </span>
                                    </td>
                                    <td><?php echo sanitize_output($order['created_at']); ?></td>
                                    <td>
                                        <div class="action-stack">

                                            <?php if ($order['status'] === 'ready'): ?>
                                                <form method="POST">
                                                    <input type="hidden" name="order_id" value="<?php echo sanitize_output($order['order_id']); ?>">
                                                    <input type="hidden" name="new_status" value="out_for_delivery">
                                                    <button type="submit" name="update_delivery_status" class="staff-btn">
                                                        Mark On Delivery
                                                    </button>
                                                </form>

                                            <?php elseif ($order['status'] === 'out_for_delivery'): ?>
                                                <?php if ($order['payment_status'] === 'successful'): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="order_id" value="<?php echo sanitize_output($order['order_id']); ?>">
                                                        <input type="hidden" name="new_status" value="delivered">
                                                        <button type="submit" name="update_delivery_status" class="staff-btn">
                                                            Mark Delivered
                                                        </button>
                                                    </form>
                                                <?php else: ?>
                                                    <button type="button" class="staff-btn" disabled>
                                                        Mark Delivered
                                                    </button>
                                                    <span class="muted-text">Confirm payment first</span>
                                                <?php endif; ?>

                                            <?php else: ?>
                                                <span class="muted-text">Delivery complete</span>
                                            <?php endif; ?>

                                            <?php if ($order['payment_method'] === 'cod'): ?>
                                                <?php if ($order['status'] === 'out_for_delivery' && in_array($order['payment_status'], ['pending', 'missing'], true)): ?>
                                                    <form method="POST">
                                                        <input type="hidden" name="order_id" value="<?php echo sanitize_output($order['order_id']); ?>">
                                                        <button type="submit" name="confirm_cod_payment" class="staff-btn">
                                                            Confirm COD Payment
                                                        </button>
                                                    </form>
                                                <?php elseif ($order['payment_status'] === 'successful'): ?>
                                                    <span class="muted-text">COD payment confirmed</span>
                                                <?php else: ?>
                                                    <span class="muted-text">COD payment pending</span>
                                                <?php endif; ?>

                                            <?php else: ?>
                                                <?php if ($order['payment_status'] === 'successful'): ?>
                                                    <span class="muted-text">Online payment completed</span>
                                                <?php elseif ($order['payment_status'] === 'pending'): ?>
                                                    <span class="muted-text">Waiting for online payment</span>
                                                <?php elseif ($order['payment_status'] === 'missing'): ?>
                                                    <span class="muted-text">No payment record</span>
                                                <?php else: ?>
                                                    <span class="muted-text">Payment not updatable</span>
                                                <?php endif; ?>
                                            <?php endif; ?>

                                        </div>
                                    </td>
                                </tr>
                            <?php endwhile; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="9">No staff-side orders found.</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

        </div>
    </div>
</div>

</body>
</html>