<?php
require_once "../includes/auth.php";
start_session();
session_security_check();
require_once "../config/db.php";
require_once "../includes/functions.php";

require_role('staff');

$field_errors = [];

// Pick up flash toast from POST-redirect-GET pattern
$staff_toast = null;
if (isset($_SESSION['_toast'])) {
    $staff_toast = $_SESSION['_toast'];
    unset($_SESSION['_toast']);
}

// Helper function for consistent sanitization
function sanitize_output($data) {
    return htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
}

/* ---------------------------
   HANDLE DELIVERY STATUS UPDATE
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_delivery_status'])) {
    $is_ajax_staff = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

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
                if (!empty($is_ajax_staff)) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Invalid delivery status transition.']); exit; }
            } elseif ($new_status === 'delivered' && $payment_status !== 'successful') {
                $field_errors['general'] = "Order cannot be marked as delivered until payment is successful.";
                if (!empty($is_ajax_staff)) { header('Content-Type: application/json'); echo json_encode(['ok' => false, 'error' => 'Payment must be confirmed before marking delivered.']); exit; }
            } else {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $stmt->bind_param("si", $new_status, $order_id);

                if ($stmt->execute()) {
                    $stmt->close();
                    if (!empty($is_ajax_staff)) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => true, 'new_status' => $new_status, 'order_id' => $order_id]);
                        exit;
                    }
                    $_SESSION['_toast'] = ['text' => 'Delivery status updated successfully.', 'type' => 'success'];
                    session_write_close();
                    header('Location: staff-control.php');
                    exit;
                } else {
                    $field_errors['general'] = "Failed to update delivery status.";
                    $stmt->close();
                    if (!empty($is_ajax_staff)) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'error' => 'Failed to update delivery status.']);
                        exit;
                    }
                }
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
    $is_ajax_staff = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                     strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

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
        } elseif (!in_array($payment_row['status'], ['out_for_delivery', 'ready'], true)) {
            $field_errors['general'] = "COD payment can only be confirmed when the order is Ready or Out for Delivery.";
        } elseif (!empty($payment_row['payment_id']) && $payment_row['payment_status'] === 'successful') {
            $field_errors['general'] = "COD payment is already confirmed.";
        } else {
            $conn->begin_transaction();
            try {
                $amount = (float)$payment_row['total_amount'];

                if (empty($payment_row['payment_id'])) {
                    // Insert payment row
                    $stmt = $conn->prepare(
                        "INSERT INTO payments (order_id, payment_method, payment_status, amount, paid_at)
                         VALUES (?, 'cod', 'successful', ?, NOW())"
                    );
                    $stmt->bind_param("id", $order_id, $amount);
                    $stmt->execute();
                    $stmt->close();
                } else {
                    // Update existing payment row
                    $stmt = $conn->prepare(
                        "UPDATE payments SET payment_status = 'successful', paid_at = NOW() WHERE order_id = ?"
                    );
                    $stmt->bind_param("i", $order_id);
                    $stmt->execute();
                    $stmt->close();
                }

                // Mark invoice as paid
                $stmt = $conn->prepare(
                    "UPDATE kot_invoices SET is_paid = 1, paid_at = NOW() WHERE order_id = ?"
                );
                $stmt->bind_param("i", $order_id);
                $stmt->execute();
                $stmt->close();

                $conn->commit();
                if (!empty($is_ajax_staff)) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true, 'order_id' => $order_id]);
                    exit;
                }
                $_SESSION['_toast'] = ['text' => 'COD payment confirmed successfully. Invoice is now available to customer.', 'type' => 'success'];
                session_write_close();
                header('Location: staff-control.php');
                exit;

            } catch (Exception $e) {
                $conn->rollback();
                $field_errors['general'] = "Failed to confirm COD payment. Please try again.";
                error_log("COD confirm failed: " . $e->getMessage());
                if (!empty($is_ajax_staff)) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Failed to confirm COD payment. Please try again.']);
                    exit;
                }
            }
        } // end else (valid payment row checks)
    } // end if (empty($field_errors))
} // end if POST confirm_cod_payment

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
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">

   
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
            <a href="user-logs.php">📋 User Logs</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </div>

    <div class="main">

        <div class="topbar">
            <div class="topbar-welcome">
                Welcome, <?php echo sanitize_output($_SESSION['full_name']); ?>
            </div>
            <!-- Theme Toggle -->
            <label class="theme-toggle" title="Toggle light/dark mode">
              <input type="checkbox" class="theme-checkbox">
              <span class="theme-slider"></span>
            </label>
        </div>

        <div class="content">

            <div class="section-title">
                <h2>Staff Control Panel</h2>
                <p>Manage delivery progress and payment completion.</p>
            </div>

            <?php if ($staff_toast): ?>
                <div class="alert-success">✓ <?php echo htmlspecialchars($staff_toast['text'], ENT_QUOTES, 'UTF-8'); ?></div>
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
                                        <div class="action-stack" id="action-<?php echo (int)$order['order_id']; ?>">

                                            <?php if ($order['status'] === 'ready'): ?>
                                                <button type="button" class="staff-btn"
                                                    onclick="staffAction('delivery', <?php echo (int)$order['order_id']; ?>, 'out_for_delivery', this)">
                                                    Mark On Delivery
                                                </button>

                                            <?php elseif ($order['status'] === 'out_for_delivery'): ?>
                                                <?php if ($order['payment_status'] === 'successful'): ?>
                                                    <button type="button" class="staff-btn"
                                                        onclick="staffAction('delivery', <?php echo (int)$order['order_id']; ?>, 'delivered', this)">
                                                        Mark Delivered
                                                    </button>
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
                                                    <button type="button" class="staff-btn"
                                                        onclick="staffAction('cod', <?php echo (int)$order['order_id']; ?>, null, this)">
                                                        Confirm COD Payment
                                                    </button>
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

<!-- Staff Toast -->
<div class="toast" id="staffToast" style="min-width:280px;display:none;">
    <div class="toast-icon">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" id="staffToastIcon">
            <circle cx="9" cy="9" r="9" fill="#4db848"/>
            <path d="M5 9.5L7.5 12L13 6.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
    </div>
    <div class="toast-body">
        <span class="toast-title" id="staffToastTitle"></span>
        <span class="toast-sub" id="staffToastSub"></span>
    </div>
    <button class="toast-close" onclick="this.closest('.toast').classList.add('toast-hide')">&#x2715;</button>
</div>

<script>
function showStaffToast(text, type) {
    var toast = document.getElementById('staffToast');
    var title = document.getElementById('staffToastTitle');
    var sub   = document.getElementById('staffToastSub');
    if (!toast) return;
    title.textContent = text;
    sub.textContent   = type === 'success' ? 'Updated successfully \u2713' : 'Action completed.';
    toast.classList.remove('toast-hide', 'toast-danger');
    if (type === 'danger') toast.classList.add('toast-danger');
    toast.style.display = '';
    clearTimeout(toast._t);
    toast._t = setTimeout(function() { toast.classList.add('toast-hide'); }, 3500);
}

function updateSummaryCount(status, delta) {
    var map = {
        'out_for_delivery': 1,   // "On Delivery" card index
        'delivered': 2,           // "Delivered" card index
        'ready': 0                // "Ready Orders" card index
    };
    var cards = document.querySelectorAll('.summary-card .count');
    if (cards[map[status]] !== undefined) {
        var cur = parseInt(cards[map[status]].textContent) || 0;
        cards[map[status]].textContent = Math.max(0, cur + delta);
    }
}

function staffAction(type, orderId, newStatus, btn) {
    btn.disabled = true;
    var origText = btn.textContent;
    btn.textContent = '...';

    var body = type === 'delivery'
        ? 'update_delivery_status=1&order_id=' + orderId + '&new_status=' + encodeURIComponent(newStatus)
        : 'confirm_cod_payment=1&order_id=' + orderId;

    fetch('staff-control.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (!data.ok) {
            btn.disabled = false;
            btn.textContent = origText;
            alert(data.error || 'Action failed. Please try again.');
            return;
        }

        var row = btn.closest('tr');
        var actionDiv = document.getElementById('action-' + orderId);

        if (type === 'delivery') {
            // Update status badge in the row
            var badge = row ? row.querySelector('.status-badge') : null;
            if (badge) {
                badge.className = 'status-badge status-' + data.new_status;
                badge.textContent = data.new_status.replace(/_/g, ' ');
            }
            // Update summary counts
            if (data.new_status === 'out_for_delivery') {
                updateSummaryCount('ready', -1);
                updateSummaryCount('out_for_delivery', 1);
                // Update action buttons
                if (actionDiv) {
                    // Check payment status badge in row
                    var payBadge = row ? row.querySelector('.payment-badge') : null;
                    var payStatus = payBadge ? payBadge.textContent.trim() : '';
                    var isPaid = payStatus === 'successful';
                    actionDiv.innerHTML = isPaid
                        ? '<button type="button" class="staff-btn" onclick="staffAction(\'delivery\',' + orderId + ',\'delivered\',this)">Mark Delivered</button>'
                        : '<button type="button" class="staff-btn" disabled>Mark Delivered</button><span class="muted-text">Confirm payment first</span>';
                    // Keep COD button if applicable - just rebuild the existing non-delivery part
                    // (COD confirm button stays until payment confirmed; it was already there)
                }
            } else if (data.new_status === 'delivered') {
                updateSummaryCount('out_for_delivery', -1);
                updateSummaryCount('delivered', 1);
                if (actionDiv) {
                    // Clear all buttons, show complete text
                    var codPart = actionDiv.innerHTML.includes('Confirm COD')
                        ? '<span class="muted-text">COD payment pending</span>' : '';
                    actionDiv.innerHTML = '<span class="muted-text">Delivery complete</span>' + codPart;
                }
            }
            showStaffToast('Delivery status updated successfully.', 'success');

        } else if (type === 'cod') {
            // Payment confirmed — update payment badge and unlock "Mark Delivered"
            var payBadge = row ? row.querySelector('.payment-badge') : null;
            if (payBadge) {
                payBadge.className = 'payment-badge payment-successful';
                payBadge.textContent = 'successful';
            }
            // Update pending payments count
            var cards = document.querySelectorAll('.summary-card .count');
            if (cards[3]) {
                var cur = parseInt(cards[3].textContent) || 0;
                cards[3].textContent = Math.max(0, cur - 1);
            }
            // Replace COD button and unlock Mark Delivered
            if (actionDiv) {
                // Replace COD button with confirmed text
                var codBtn = actionDiv.querySelector('button[onclick*="staffAction(\'cod\'"]');
                if (codBtn) {
                    codBtn.replaceWith(Object.assign(document.createElement('span'), { className: 'muted-text', textContent: 'COD payment confirmed' }));
                }
                // Unlock the Mark Delivered button if it exists and is disabled
                var deliverBtn = actionDiv.querySelector('button.staff-btn[disabled]');
                if (deliverBtn && deliverBtn.textContent.trim() === 'Mark Delivered') {
                    deliverBtn.disabled = false;
                    deliverBtn.setAttribute('onclick', "staffAction('delivery'," + orderId + ",'delivered',this)");
                    // Remove the "Confirm payment first" hint
                    var hint = actionDiv.querySelector('.muted-text');
                    if (hint && hint.textContent.trim() === 'Confirm payment first') hint.remove();
                }
            }
            showStaffToast('COD payment confirmed successfully.', 'success');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = origText;
        alert('Network error. Please try again.');
    });
}
</script>

</body>
</html>