<?php
session_start();
require '../config/db.php';

// Must be logged in
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}

$user_id   = $_SESSION['user_id'];
$full_name = $_SESSION['full_name'];

// ── Check cart is not empty ──────────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT c.cart_id, c.quantity, m.item_id, m.name, m.price
    FROM cart c
    JOIN menu_items m ON c.item_id = m.item_id
    WHERE c.user_id = ?
");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$cart_result = $stmt->get_result();
$cart_items  = [];
while ($row = $cart_result->fetch_assoc()) {
    $cart_items[] = $row;
}

if (empty($cart_items)) {
    // Redirect with error if cart is empty
    header('Location: cart.php?error=empty_cart');
    exit;
}

// ── Calculate total ───────────────────────────────────────────────────────────
$total = 0;
foreach ($cart_items as $item) {
    $total += $item['price'] * $item['quantity'];
}
$total_paisa = (int)($total * 100); // for Khalti (paisa)

// ── eSewa config (sandbox) ───────────────────────────────────────────────────
$esewa_merchant_code = "EPAYTEST"; // sandbox merchant code
$esewa_url           = "https://rc-epay.esewa.com.np/api/epay/main/v2/form";
$esewa_secret_key    = "8gBm/:&EnhH.1/q"; // sandbox secret key

// ── Khalti config (sandbox) ──────────────────────────────────────────────────
$khalti_public_key   = "test_public_key_dc74e0fd57cb46cd93832aee0a390234"; // sandbox key
$khalti_secret_key   = "test_secret_key_f59e8b7d18b4499ca40f68195a846e9b"; // sandbox key

// ── Generate a unique transaction/purchase order ID ──────────────────────────
$transaction_uuid = uniqid('hc_', true);

// Store in session so callbacks can reference it
$_SESSION['pending_payment'] = [
    'transaction_uuid' => $transaction_uuid,
    'total'            => $total,
    'cart_items'       => $cart_items,
];

// ── eSewa HMAC signature ──────────────────────────────────────────────────────
// Format: total_amount,transaction_uuid,product_code
$esewa_signed_field_names = "total_amount,transaction_uuid,product_code";
$esewa_sign_string = "total_amount={$total},transaction_uuid={$transaction_uuid},product_code={$esewa_merchant_code}";
$esewa_signature   = base64_encode(hash_hmac('sha256', $esewa_sign_string, $esewa_secret_key, true));

$success_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/esewa_verify.php";
$failure_url = "http://" . $_SERVER['HTTP_HOST'] . dirname($_SERVER['PHP_SELF']) . "/payment.php?status=failed";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Checkout — Herald Canteen</title>
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700&display=swap" rel="stylesheet">
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }

        body {
            font-family: "Segoe UI", sans-serif;
            background: #181818;
            color: #ffffff;
        }

        a { text-decoration: none; }

        .layout { display: flex; min-height: 100vh; }

        /* ── Sidebar ── */
        .sidebar {
            width: 220px;
            background: #2e2e2e;
            padding: 30px 20px;
            display: flex;
            flex-direction: column;
            gap: 30px;
            position: fixed;
            top: 0; left: 0;
            height: 100vh;
            z-index: 10;
            border-right: 1px solid rgba(77,184,72,0.1);
        }
        .sidebar h3 { font-size: 22px; color: #4db848; }
        .sidebar nav { display: flex; flex-direction: column; gap: 6px; }
        .sidebar nav a {
            color: rgba(255,255,255,0.45);
            font-size: 14px;
            padding: 10px 14px;
            border-radius: 8px;
            transition: all 0.2s;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .sidebar nav a:hover,
        .sidebar nav a.active {
            background: rgba(77,184,72,0.1);
            color: #4db848;
            border: 1px solid rgba(77,184,72,0.15);
        }

        /* ── Main ── */
        .main { margin-left: 220px; flex: 1; display: flex; flex-direction: column; }

        /* ── Topbar ── */
        .topbar {
            padding: 28px 30px;
            background: rgba(24,24,24,0.85);
            backdrop-filter: blur(12px);
            border-bottom: 1px solid rgba(77,184,72,0.1);
            position: sticky;
            top: 0;
            z-index: 5;
            display: flex;
            align-items: center;
            justify-content: space-between;
        }
        .topbar-left h2 {
            font-size: 28px;
            font-weight: 700;
            font-family: "Poppins", "Segoe UI", sans-serif;
            letter-spacing: 0.3px;
        }
        .topbar-left p {
            font-size: 15px;
            font-weight: 500;
            color: #4db848;
            margin-top: 4px;
            font-family: "Poppins", "Segoe UI", sans-serif;
        }
        .topbar-amount {
            text-align: right;
        }
        .topbar-amount .label {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 1px;
            font-weight: 600;
        }
        .topbar-amount .amount {
            font-size: 26px;
            font-weight: 700;
            color: #4db848;
            font-family: "Poppins", "Segoe UI", sans-serif;
            margin-top: 2px;
        }

        /* ── Content ── */
        .content { padding: 30px; }

        /* ── Section title ── */
        .section-title h2 {
            font-size: 30px;
            font-weight: 700;
            font-family: "Poppins", "Segoe UI", sans-serif;
            letter-spacing: 0.5px;
        }
        .section-title p {
            margin-top: 8px;
            margin-bottom: 24px;
            font-size: 15px;
            font-weight: 600;
            color: #4db848;
            font-family: "Poppins", "Segoe UI", sans-serif;
        }

        /* ── Alert ── */
        .alert {
            padding: 12px 16px;
            border-radius: 8px;
            margin-bottom: 16px;
            font-size: 14px;
        }
        .alert.error   { background: rgba(229,57,53,0.1);  border: 1px solid rgba(229,57,53,0.3);  color: #ef9a9a; }
        .alert.success { background: rgba(77,184,72,0.1);  border: 1px solid rgba(77,184,72,0.3);  color: #6dcc68; }

        /* ── Checkout grid ── */
        .checkout-grid {
            display: grid;
            grid-template-columns: 1fr 400px;
            gap: 20px;
            align-items: start;
        }

        /* ── Cards ── */
        .card {
            background: #2a2a2a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 24px;
            box-shadow: 0 2px 12px rgba(0,0,0,0.3);
        }
        .card-title {
            font-size: 11px;
            font-weight: 700;
            text-transform: uppercase;
            letter-spacing: 1px;
            color: rgba(77,184,72,0.7);
            margin-bottom: 16px;
        }

        /* ── Order items ── */
        .order-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 13px 0;
            border-bottom: 1px solid rgba(255,255,255,0.05);
        }
        .order-item:last-child { border-bottom: none; }
        .order-item-name { font-size: 14px; font-weight: 600; color: #ffffff; margin-bottom: 3px; }
        .order-item-qty  { font-size: 12px; color: rgba(255,255,255,0.4); }
        .order-item-price { font-size: 14px; font-weight: 700; color: #4db848; }

        .order-total {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-top: 16px;
            padding-top: 16px;
            border-top: 1px solid rgba(255,255,255,0.08);
        }
        .order-total span:first-child {
            font-size: 15px;
            font-weight: 700;
            font-family: "Poppins", "Segoe UI", sans-serif;
        }
        .total-amount {
            font-size: 18px;
            font-weight: 700;
            color: #4db848;
            font-family: "Poppins", "Segoe UI", sans-serif;
        }

        /* ── Payment methods ── */
        .method-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 10px;
            margin-bottom: 20px;
        }
        .method-card { position: relative; }
        .method-card input[type="radio"] {
            position: absolute;
            opacity: 0;
            pointer-events: none;
        }
        .method-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 8px;
            padding: 16px 10px;
            border: 1px solid rgba(255,255,255,0.06);
            border-radius: 12px;
            background: #212121;
            cursor: pointer;
            transition: all 0.2s;
        }
        .method-card input:checked + .method-label {
            border-color: rgba(77,184,72,0.5);
            background: rgba(77,184,72,0.08);
            box-shadow: 0 0 0 1px rgba(77,184,72,0.2);
        }
        .method-label:hover {
            border-color: rgba(77,184,72,0.25);
            background: rgba(77,184,72,0.05);
        }
        .method-icon {
            width: 42px; height: 42px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 1.3rem;
        }
        .icon-cod    { background: rgba(77,184,72,0.12); }
        .icon-esewa  { background: rgba(96,187,70,0.12); }
        .icon-khalti { background: rgba(92,45,145,0.15); }
        .icon-card   { background: rgba(100,160,230,0.12); }

        .method-name {
            font-size: 12px;
            font-weight: 700;
            font-family: "Poppins", "Segoe UI", sans-serif;
            color: #ffffff;
            text-align: center;
        }
        .method-sub { font-size: 11px; color: rgba(255,255,255,0.4); text-align: center; }

        /* ── Pay button ── */
        #pay-btn {
            width: 100%;
            padding: 12px 20px;
            background: #4db848;
            color: #ffffff;
            border: none;
            border-radius: 8px;
            font-size: 15px;
            font-weight: 700;
            font-family: "Poppins", "Segoe UI", sans-serif;
            cursor: pointer;
            transition: background 0.2s;
            box-shadow: 0 4px 12px rgba(77,184,72,0.25);
        }
        #pay-btn:hover { background: #3a9236; }
        #pay-btn:disabled { background: #3a3a3a; color: rgba(255,255,255,0.3); cursor: not-allowed; box-shadow: none; }

        /* ── Hidden forms ── */
        #esewa-form, #cod-form { display: none; }

        @media (max-width: 900px) {
            .checkout-grid { grid-template-columns: 1fr; }
            .sidebar { display: none; }
            .main { margin-left: 0; }
        }
    </style>
</head>
<body>
<div class="layout">

    <!-- Sidebar -->
    <div class="sidebar">
        <h3>Herald Canteen</h3>
        <nav>
            <a href="dashboard.php">Home</a>
            <a href="my_cart.php">My Cart</a>
            <a href="my_orders.php">My Orders</a>
            <a href="logout.php">Logout</a>
        </nav>
    </div>

    <!-- Main -->
    <div class="main">

        <div class="topbar">
            <div class="topbar-left">
                <h2>Checkout</h2>
                <p>👋 Hello, <?php echo htmlspecialchars($full_name); ?> — review your order below</p>
            </div>
            <div class="topbar-amount">
                <div class="label">Order Total</div>
                <div class="amount">Rs. <?php echo number_format($total, 2); ?></div>
            </div>
        </div>

        <div class="content">

        <?php if (isset($_GET['status']) && $_GET['status'] === 'failed'): ?>
        <div class="alert error">⚠️ Payment failed or was cancelled. Please try again.</div>
        <?php endif; ?>

        <div class="section-title">
            <h2>Payment</h2>
            <p>Choose how you'd like to pay</p>
        </div>

        <div class="checkout-grid">

            <!-- Left: Order Summary -->
            <div class="card">
                <div class="card-title">Order Summary</div>
                <?php foreach ($cart_items as $item): ?>
                <div class="order-item">
                    <div>
                        <div class="order-item-name"><?php echo htmlspecialchars($item['name']); ?></div>
                        <div class="order-item-qty">Qty: <?php echo $item['quantity']; ?></div>
                    </div>
                    <div class="order-item-price">
                        Rs. <?php echo number_format($item['price'] * $item['quantity'], 2); ?>
                    </div>
                </div>
                <?php endforeach; ?>
                <div class="order-total">
                    <span>Total</span>
                    <span class="total-amount">Rs. <?php echo number_format($total, 2); ?></span>

                </div>
            </div>

            <!-- Right: Payment Methods -->
            <div class="card">
                <div class="card-title">Payment Method</div>

                <div class="method-grid">
                    <!-- COD -->
                    <label class="method-card">
                        <input type="radio" name="payment_method" value="cod" checked>
                        <div class="method-label">
                            <div class="method-icon icon-cod">💵</div>
                            <div class="method-name">Cash on Delivery</div>
                            <div class="method-sub">Pay when received</div>
                        </div>
                    </label>

                    <!-- eSewa -->
                    <label class="method-card">
                        <input type="radio" name="payment_method" value="esewa">
                        <div class="method-label">
                            <div class="method-icon icon-esewa">
                                <img src="https://esewa.com.np/common/images/esewa_logo.png"
                                     alt="eSewa" style="width:30px;height:30px;object-fit:contain;">
                            </div>
                            <div class="method-name">eSewa</div>
                            <div class="method-sub">Digital wallet</div>
                        </div>
                    </label>

                    <!-- Khalti -->
                    <label class="method-card">
                        <input type="radio" name="payment_method" value="khalti">
                        <div class="method-label">
                            <div class="method-icon icon-khalti" style="font-family:'Poppins',sans-serif;font-size:18px;font-weight:800;color:#a855f7;">K</div>
                            <div class="method-name">Khalti</div>
                            <div class="method-sub">Digital wallet</div>
                        </div>
                    </label>

                    <!-- Card -->
                    <label class="method-card">
                        <input type="radio" name="payment_method" value="card">
                        <div class="method-label">
                            <div class="method-icon icon-card">💳</div>
                            <div class="method-name">Card</div>
                            <div class="method-sub">Via eSewa gateway</div>
                        </div>
                    </label>
                </div>

                <button id="pay-btn" onclick="handlePayment()">
                    Pay Rs. <?php echo number_format($total, 2); ?>
                </button>
            </div>

        </div><!-- /checkout-grid -->

        <!-- eSewa hidden form (v2 API) -->
        <form id="esewa-form" action="<?php echo $esewa_url; ?>" method="POST">
            <input type="hidden" name="amount"              value="<?php echo $total; ?>">
            <input type="hidden" name="tax_amount"          value="0">
            <input type="hidden" name="total_amount"        value="<?php echo $total; ?>">
            <input type="hidden" name="transaction_uuid"    value="<?php echo $transaction_uuid; ?>">
            <input type="hidden" name="product_code"        value="<?php echo $esewa_merchant_code; ?>">
            <input type="hidden" name="product_service_charge" value="0">
            <input type="hidden" name="product_delivery_charge" value="0">
            <input type="hidden" name="success_url"         value="<?php echo $success_url; ?>">
            <input type="hidden" name="failure_url"         value="<?php echo $failure_url; ?>">
            <input type="hidden" name="signed_field_names"  value="<?php echo $esewa_signed_field_names; ?>">
            <input type="hidden" name="signature"           value="<?php echo $esewa_signature; ?>">
        </form>

        <!-- COD hidden form -->
        <form id="cod-form" action="cod_confirm.php" method="POST">
            <input type="hidden" name="transaction_uuid" value="<?php echo $transaction_uuid; ?>">
        </form>

        </div><!-- /content -->
    </div><!-- /main -->
</div><!-- /layout -->

<script>
function handlePayment() {
    const method = document.querySelector('input[name="payment_method"]:checked').value;

    if (method === 'cod') {
        document.getElementById('cod-form').submit();
    } else if (method === 'esewa' || method === 'card') {
        document.getElementById('esewa-form').submit();
    } else if (method === 'khalti') {
        initiateKhalti();
    }
}

function initiateKhalti() {
    const btn = document.getElementById('pay-btn');
    btn.textContent = 'Connecting to Khalti…';
    btn.disabled = true;

    fetch('khalti_initiate.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ transaction_uuid: '<?php echo $transaction_uuid; ?>' })
    })
    .then(r => r.json())
    .then(data => {
        if (data.payment_url) {
            window.location.href = data.payment_url;
        } else {
            alert('Khalti error: ' + (data.error || 'Unknown error. Try again.'));
            btn.textContent = 'Pay Rs. <?php echo number_format($total, 2); ?>';
            btn.disabled = false;
        }
    })
    .catch(() => {
        alert('Could not connect to Khalti. Please try again.');
        btn.textContent = 'Pay Rs. <?php echo number_format($total, 2); ?>';
        btn.disabled = false;
    });
}
</script>
</body>
</html>