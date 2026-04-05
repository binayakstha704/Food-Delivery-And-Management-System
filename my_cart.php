<?php
session_start();

// ── Sample cart in session (replace with DB later)
// Example with DB:
// $stmt = $pdo->prepare("SELECT ci.*, f.name, f.price, f.cuisine FROM cart_items ci JOIN food_items f ON ci.food_id = f.id WHERE ci.user_id = ?");
// $stmt->execute([$_SESSION['user_id']]);
// $cart = $stmt->fetchAll(PDO::FETCH_ASSOC);

if (!isset($_SESSION['cart'])) {
    $_SESSION['cart'] = [
        ['id'=>1,'name'=>'Margherita Pizza','cuisine'=>'Italian','emoji'=>'🍕','price'=>850,'qty'=>2,'rating'=>4.8,'reviews'=>234],
        ['id'=>2,'name'=>'Chicken Burger',  'cuisine'=>'American','emoji'=>'🍔','price'=>550,'qty'=>1,'rating'=>4.6,'reviews'=>189],
        ['id'=>3,'name'=>'Pad Thai',         'cuisine'=>'Thai',    'emoji'=>'🍜','price'=>680,'qty'=>1,'rating'=>4.7,'reviews'=>156],
    ];
}

$DELIVERY_FEE = 60;
$FREE_DELIVERY = 500;

$PROMO_CODES = ['SWAAD10'=>10, 'WELCOME'=>15, 'SAVE20'=>20];

if (!isset($_SESSION['discount'])) $_SESSION['discount'] = 0;
if (!isset($_SESSION['promo_msg'])) $_SESSION['promo_msg'] = '';
if (!isset($_SESSION['promo_msg_type'])) $_SESSION['promo_msg_type'] = '';

$action_msg = '';

// ── Handle actions via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // Update quantity
    if ($action === 'update_qty') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $delta   = (int)($_POST['delta']   ?? 0);
        foreach ($_SESSION['cart'] as &$item) {
            if ($item['id'] === $item_id) {
                $item['qty'] = max(1, $item['qty'] + $delta);
                break;
            }
        }
        unset($item);
    }

    // Remove item
    if ($action === 'remove_item') {
        $item_id = (int)($_POST['item_id'] ?? 0);
        $_SESSION['cart'] = array_values(array_filter($_SESSION['cart'], fn($i) => $i['id'] !== $item_id));
        $_SESSION['discount'] = 0;
        $_SESSION['promo_msg'] = '';
    }

    // Clear cart
    if ($action === 'clear_cart') {
        $_SESSION['cart'] = [];
        $_SESSION['discount'] = 0;
        $_SESSION['promo_msg'] = '';
    }

    // Redirect back (PRG pattern prevents form resubmission on refresh)
    header('Location: my_cart.php');
    exit();
}

// ── Calculate totals
$cart     = $_SESSION['cart'];
$subtotal = array_sum(array_map(fn($i) => $i['price'] * $i['qty'], $cart));
$total_qty= array_sum(array_map(fn($i) => $i['qty'], $cart));
$delivery = ($subtotal >= $FREE_DELIVERY && $subtotal > 0) ? 0 : ($subtotal > 0 ? $DELIVERY_FEE : 0);
$discount_pct = $_SESSION['discount'];
$discount_amt = (int)round($subtotal * ($discount_pct / 100));
$total = $subtotal + $delivery - $discount_amt;
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Cart — Swaad Unlimited</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
    :root{
      --deep-brown:#2C1503;--mid-brown:#7B3F00;--warm-brown:#A0522D;
      --near-black:#1A0A00;--burnt-orange:#CC5500;--bright-orange:#FF6B1A;
      --peach:#FFD5B0;--peach-light:#FFF0E0;--peach-card:#FDE8D0;--white:#FFFFFF;
    }
    body{min-height:100vh;font-family:'Poppins',sans-serif;background:var(--peach-light);color:var(--near-black);}

    nav{background:var(--deep-brown);padding:0 32px;display:flex;align-items:center;justify-content:space-between;height:62px;position:sticky;top:0;z-index:100;box-shadow:0 2px 12px rgba(0,0,0,0.3);}
    .nav-brand{display:flex;align-items:center;gap:10px;text-decoration:none;}
    .nav-logo{width:50px;height:50px;background:var(--white);border-radius:50%;display:flex;align-items:center;justify-content:center;overflow:hidden;}
    .nav-logo img{width:50px;height:50px;object-fit:cover;border-radius:50%;}
    .nav-brand-name{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--peach);}
    .nav-brand-name span{color:var(--bright-orange);}
    .nav-search{flex:1;max-width:460px;margin:0 28px;position:relative;}
    .nav-search input{width:100%;padding:8px 16px 8px 38px;border-radius:24px;border:none;background:rgba(255,255,255,0.12);color:var(--white);font-family:'Poppins',sans-serif;font-size:0.84rem;outline:none;}
    .nav-search input::placeholder{color:rgba(255,255,255,0.5);}
    .nav-search .si{position:absolute;left:12px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,0.5);}
    .nav-cart-badge{background:var(--burnt-orange);color:#fff;padding:6px 18px;border-radius:20px;font-size:0.84rem;font-weight:600;display:flex;align-items:center;gap:7px;text-decoration:none;}
    .cart-count{background:var(--bright-orange);color:#fff;border-radius:50%;width:20px;height:20px;display:flex;align-items:center;justify-content:center;font-size:0.7rem;font-weight:700;}

    .tab-bar{background:var(--white);border-bottom:1px solid var(--peach);padding:0 32px;display:flex;}
    .tab-bar a{padding:14px 20px;font-size:0.88rem;color:var(--mid-brown);text-decoration:none;display:flex;align-items:center;gap:7px;border-bottom:2px solid transparent;transition:color 0.2s,border-color 0.2s;}
    .tab-bar a:hover{color:var(--burnt-orange);}
    .tab-bar a.active{color:var(--burnt-orange);border-bottom-color:var(--burnt-orange);font-weight:600;}

    .page-wrap{max-width:1060px;margin:32px auto;padding:0 20px 48px;}
    .page-title{font-family:'Playfair Display',serif;font-size:1.6rem;color:var(--near-black);margin-bottom:4px;}
    .page-subtitle{font-size:0.82rem;color:var(--warm-brown);margin-bottom:28px;}

    .cart-layout{display:grid;grid-template-columns:1fr 340px;gap:24px;align-items:start;}

    .cart-panel{background:var(--white);border-radius:16px;padding:28px;box-shadow:0 4px 20px rgba(44,21,3,0.10);animation:fadeIn 0.4s ease both;}
    @keyframes fadeIn{from{opacity:0;transform:translateY(14px);}to{opacity:1;transform:none;}}

    .panel-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:20px;padding-bottom:14px;border-bottom:2px solid var(--peach);}
    .panel-header h3{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--near-black);}

    .clear-btn{background:none;border:1px solid #e2c9b0;color:var(--warm-brown);font-family:'Poppins',sans-serif;font-size:0.75rem;padding:5px 13px;border-radius:6px;cursor:pointer;transition:background 0.2s,color 0.2s;}
    .clear-btn:hover{background:#fff0f0;color:#cc0000;border-color:#ffcccc;}

    .cart-item{display:flex;align-items:center;gap:16px;padding:16px 0;border-bottom:1px solid var(--peach);}
    .cart-item:last-child{border-bottom:none;}
    .item-img{width:72px;height:72px;border-radius:12px;background:var(--peach-card);display:flex;align-items:center;justify-content:center;font-size:2rem;flex-shrink:0;}
    .item-details{flex:1;}
    .item-name{font-size:0.92rem;font-weight:600;color:var(--near-black);margin-bottom:2px;}
    .item-cuisine{font-size:0.74rem;color:var(--warm-brown);margin-bottom:5px;}
    .item-meta{display:flex;align-items:center;gap:10px;}
    .item-rating{font-size:0.74rem;color:var(--warm-brown);}
    .item-rating span{color:var(--burnt-orange);}
    .avail-badge{font-size:0.66rem;padding:2px 8px;border-radius:20px;font-weight:600;background:#e8f5e9;color:#2e7d32;}
    .item-price-col{text-align:right;}
    .item-unit-price{font-size:0.75rem;color:var(--warm-brown);margin-bottom:6px;}

    .qty-control{display:flex;align-items:center;border:1.5px solid var(--peach);border-radius:8px;overflow:hidden;margin-bottom:8px;}
    .qty-btn{width:30px;height:30px;background:var(--peach-card);border:none;cursor:pointer;font-size:1rem;font-weight:700;color:var(--burnt-orange);transition:background 0.15s;display:flex;align-items:center;justify-content:center;}
    .qty-btn:hover{background:var(--peach);}
    .qty-num{width:36px;text-align:center;font-size:0.88rem;font-weight:600;color:var(--near-black);background:var(--white);padding:4px 0;}

    .item-subtotal{font-weight:700;color:var(--burnt-orange);font-size:0.92rem;}
    .remove-btn{background:none;border:none;cursor:pointer;color:#ccc;font-size:1.1rem;transition:color 0.2s;padding:4px;margin-left:4px;}
    .remove-btn:hover{color:#cc0000;}

    .empty-cart{text-align:center;padding:48px 0;}
    .empty-cart .empty-icon{font-size:3.5rem;margin-bottom:12px;}
    .empty-cart h4{font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--near-black);margin-bottom:6px;}
    .empty-cart p{font-size:0.82rem;color:var(--warm-brown);margin-bottom:20px;}
    .btn-browse{display:inline-block;padding:10px 28px;background:var(--burnt-orange);color:var(--white);border-radius:8px;text-decoration:none;font-weight:600;font-size:0.88rem;transition:background 0.2s;}
    .btn-browse:hover{background:#b34a00;}

    .delivery-note{margin-top:16px;background:var(--peach-light);border:1px dashed var(--peach);border-radius:10px;padding:12px 16px;display:flex;align-items:center;gap:10px;font-size:0.78rem;color:var(--mid-brown);}

    .summary-panel{background:var(--white);border-radius:16px;padding:28px;box-shadow:0 4px 20px rgba(44,21,3,0.10);position:sticky;top:80px;animation:fadeIn 0.4s 0.1s ease both;}
    .summary-panel h3{font-family:'Playfair Display',serif;font-size:1.1rem;color:var(--near-black);margin-bottom:20px;padding-bottom:12px;border-bottom:2px solid var(--peach);}

    .summary-row{display:flex;justify-content:space-between;font-size:0.84rem;color:var(--mid-brown);margin-bottom:12px;}
    .summary-row.total{font-weight:700;color:var(--near-black);font-size:1rem;padding-top:12px;border-top:2px solid var(--peach);margin-top:6px;}
    .summary-row .val{color:var(--near-black);font-weight:500;}
    .summary-row.total .val{color:var(--burnt-orange);font-size:1.1rem;}

    .addr-select-wrap{margin-bottom:18px;}
    .addr-select-wrap label{font-size:0.78rem;font-weight:500;color:var(--mid-brown);display:block;margin-bottom:5px;}
    .addr-select-wrap select{width:100%;padding:9px 13px;border:1.5px solid #e2c9b0;border-radius:8px;font-family:'Poppins',sans-serif;font-size:0.82rem;color:var(--near-black);background:#fffaf6;outline:none;}

    .btn-checkout{width:100%;padding:13px;background:var(--burnt-orange);color:var(--white);border:none;border-radius:10px;font-family:'Poppins',sans-serif;font-size:0.95rem;font-weight:700;cursor:pointer;transition:background 0.2s,transform 0.1s;box-shadow:0 4px 16px rgba(204,85,0,0.28);letter-spacing:0.3px;}
    .btn-checkout:hover{background:#b34a00;}
    .btn-checkout:active{transform:scale(0.98);}
    .secure-note{display:flex;align-items:center;justify-content:center;gap:5px;margin-top:10px;font-size:0.72rem;color:var(--warm-brown);}

    /* Hidden form helper */
    .action-form{display:none;}

    @media(max-width:760px){.cart-layout{grid-template-columns:1fr;}.summary-panel{position:static;}}
  </style>
</head>
<body>

<nav>
  <a class="nav-brand" href="index.php">
    <div class="nav-logo">
      <img src="logo.jfif" alt="Logo" onerror="this.style.display='none';this.parentElement.innerHTML='🍽️'"/>
    </div>
    <span class="nav-brand-name">Swaad <span>Unlimited</span></span>
  </a>
  <div class="nav-search">
    <span class="si">🔍</span>
    <input type="text" placeholder="Search for food, cuisine..."/>
  </div>
  <a class="nav-cart-badge" href="my_cart.php">
    🛒 Cart <span class="cart-count"><?php echo $total_qty; ?></span>
  </a>
</nav>

<div class="tab-bar">
<<<<<<< HEAD
  <a href="index.php"> Home</a>
  <a href="my_cart.php" class="active">🛒 My Cart</a>
  <a href="my_orders.php"> My Orders</a>
=======
  <a href="index.php">🏠 Home</a>
  <a href="my_cart.php" class="active">🛒 My Cart</a>
  <a href="my_orders.php">📦 My Orders</a>
>>>>>>> cb72e933b77b3a24b02f699e47ba769dd7907e3b
  <a href="user_profile.php">👤 Profile</a>
</div>

<div class="page-wrap">
  <h2 class="page-title">My Cart</h2>
  <p class="page-subtitle">Review your items and place your order</p>

  <div class="cart-layout">

    <!-- CART ITEMS -->
    <div class="cart-panel">
      <div class="panel-header">
        <h3>🛒 Cart Items (<?php echo $total_qty; ?>)</h3>
        <?php if (!empty($cart)): ?>
        <form method="POST" action="my_cart.php">
          <input type="hidden" name="action" value="clear_cart"/>
          <button type="submit" class="clear-btn"
                  onclick="return confirm('Remove all items from cart?')">Clear All</button>
        </form>
        <?php endif; ?>
      </div>

      <?php if (empty($cart)): ?>
        <div class="empty-cart">
          <div class="empty-icon">🛒</div>
          <h4>Your cart is empty</h4>
          <p>Looks like you haven't added anything yet.<br>Explore our menu and add some delicious items!</p>
          <a href="index.php" class="btn-browse">Browse Menu</a>
        </div>
      <?php else: ?>
        <?php foreach ($cart as $item): ?>
        <div class="cart-item">
          <div class="item-img"><?php echo htmlspecialchars($item['emoji'], ENT_QUOTES, 'UTF-8'); ?></div>
          <div class="item-details">
            <div class="item-name"><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="item-cuisine"><?php echo htmlspecialchars($item['cuisine'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="item-meta">
              <span class="item-rating"><span>⭐</span> <?php echo $item['rating']; ?> (<?php echo $item['reviews']; ?> reviews)</span>
              <span class="avail-badge">Available</span>
            </div>
          </div>
          <div class="item-price-col">
            <div class="item-unit-price">Rs <?php echo number_format($item['price']); ?> each</div>
            <!-- Quantity controls -->
            <div class="qty-control">
              <form method="POST" action="my_cart.php" style="display:contents;">
                <input type="hidden" name="action"  value="update_qty"/>
                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>"/>
                <input type="hidden" name="delta"   value="-1"/>
                <button type="submit" class="qty-btn">−</button>
              </form>
              <div class="qty-num"><?php echo $item['qty']; ?></div>
              <form method="POST" action="my_cart.php" style="display:contents;">
                <input type="hidden" name="action"  value="update_qty"/>
                <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>"/>
                <input type="hidden" name="delta"   value="1"/>
                <button type="submit" class="qty-btn">+</button>
              </form>
            </div>
            <div class="item-subtotal">Rs <?php echo number_format($item['price'] * $item['qty']); ?></div>
          </div>
          <!-- Remove -->
          <form method="POST" action="my_cart.php">
            <input type="hidden" name="action"  value="remove_item"/>
            <input type="hidden" name="item_id" value="<?php echo $item['id']; ?>"/>
            <button type="submit" class="remove-btn" title="Remove item">✕</button>
          </form>
        </div>
        <?php endforeach; ?>

        <div class="delivery-note">
          <span>🛵</span>
          <span>Estimated delivery: <strong>25–35 minutes</strong> ·
            <?php echo $delivery === 0 ? '🎉 Free delivery on this order!' : 'Free delivery on orders above Rs 500'; ?>
          </span>
        </div>
      <?php endif; ?>
    </div>

    <!-- ORDER SUMMARY -->
    <div class="summary-panel">
      <h3>Order Summary</h3>

      <div class="summary-row">
        <span>Subtotal (<?php echo $total_qty; ?> items)</span>
        <span class="val">Rs <?php echo number_format($subtotal); ?></span>
      </div>
      <div class="summary-row">
        <span>Delivery Fee</span>
        <span class="val"><?php echo $delivery === 0 ? '🎉 Free' : 'Rs ' . number_format($delivery); ?></span>
      </div>
      <div class="summary-row">
        <span>Discount</span>
        <span class="val" style="color:#2e7d32;">- Rs <?php echo number_format($discount_amt); ?></span>
      </div>
      <div class="summary-row total">
        <span>Total</span>
        <span class="val">Rs <?php echo number_format($total); ?></span>
      </div>

      <!-- Delivery address -->
      <div class="addr-select-wrap">
        <label>Delivery Address</label>
        <select>
          <option>📍 Kathmandu, Bagmati Province</option>
          <option>📍 Lalitpur, Patan</option>
          <option>+ Add new address</option>
        </select>
      </div>

      <?php if (!empty($cart)): ?>
        <form method="POST" action="payment_page.php">
          <button type="submit" class="btn-checkout">Proceed to Checkout →</button>
        </form>
      <?php else: ?>
        <button class="btn-checkout" disabled style="background:#ccc;cursor:not-allowed;">Cart is Empty</button>
      <?php endif; ?>
    </div>

  </div>
</div>

</body>
</html>
