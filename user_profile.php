<?php
session_start();

// ── Sample session-based user data (replace with DB fetch later)
// Example with DB:
// $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
// $stmt->execute([$_SESSION['user_id']]);
// $user = $stmt->fetch(PDO::FETCH_ASSOC);

if (!isset($_SESSION['user'])) {
    $_SESSION['user'] = [
        'first_name' => 'Joe',
        'last_name'  => 'Bart',
        'email'      => 'Joe@swaad.com',
        'phone'      => '+977 98XXXXXXXX',
        'dob'        => '2003-01-01',
        'gender'     => 'male',
        'address'    => 'Kathmandu, Bagmati Province, Nepal',
    ];
}

$success_msg = '';
$error_msg   = '';

// ── Handle profile update POST
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['save_profile'])) {

    // CSRF check
    if (!isset($_POST['csrf_token']) || $_POST['csrf_token'] !== $_SESSION['csrf_token']) {
        $error_msg = 'Invalid request. Please try again.';
    } else {
        $first_name = htmlspecialchars(trim($_POST['first_name'] ?? ''), ENT_QUOTES, 'UTF-8');
        $last_name  = htmlspecialchars(trim($_POST['last_name']  ?? ''), ENT_QUOTES, 'UTF-8');
        $email      = htmlspecialchars(trim($_POST['email']      ?? ''), ENT_QUOTES, 'UTF-8');
        $phone      = htmlspecialchars(trim($_POST['phone']      ?? ''), ENT_QUOTES, 'UTF-8');
        $dob        = htmlspecialchars(trim($_POST['dob']        ?? ''), ENT_QUOTES, 'UTF-8');
        $gender     = htmlspecialchars(trim($_POST['gender']     ?? ''), ENT_QUOTES, 'UTF-8');
        $address    = htmlspecialchars(trim($_POST['address']    ?? ''), ENT_QUOTES, 'UTF-8');

        if (empty($first_name) || empty($last_name)) {
            $error_msg = 'First and last name are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error_msg = 'Please enter a valid email address.';
        } elseif (empty($phone)) {
            $error_msg = 'Phone number is required.';
        } else {
            // Save to session (replace with DB UPDATE in production)
            $_SESSION['user'] = compact('first_name','last_name','email','phone','dob','gender','address');
            $success_msg = 'Profile updated successfully!';
        }
    }
}

// CSRF token
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$u = $_SESSION['user'];
$initials = strtoupper(substr($u['first_name'],0,1) . substr($u['last_name'],0,1));
$full_name = htmlspecialchars($u['first_name'] . ' ' . $u['last_name'], ENT_QUOTES, 'UTF-8');

// Sample order history (replace with DB query later)
$orders = [
    ['icon'=>'🍕','name'=>'Margherita Pizza × 2','date'=>'March 24, 2025 · 7:30 PM','price'=>'Rs 1,700','status'=>'Delivered','status_class'=>'status-delivered'],
    ['icon'=>'🍔','name'=>'Chicken Burger × 1',  'date'=>'March 20, 2025 · 1:15 PM','price'=>'Rs 550',  'status'=>'Delivered','status_class'=>'status-delivered'],
    ['icon'=>'🍜','name'=>'Pad Thai × 1',         'date'=>'March 15, 2025 · 8:00 PM','price'=>'Rs 680',  'status'=>'Cancelled','status_class'=>'status-cancelled'],
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8"/>
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>My Profile — Swaad Unlimited</title>
  <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    :root {
      --deep-brown:   #2C1503;
      --mid-brown:    #7B3F00;
      --warm-brown:   #A0522D;
      --near-black:   #1A0A00;
      --burnt-orange: #CC5500;
      --bright-orange:#FF6B1A;
      --peach:        #FFD5B0;
      --peach-light:  #FFF0E0;
      --peach-card:   #FDE8D0;
      --white:        #FFFFFF;
    }
    body { min-height:100vh; font-family:'Poppins',sans-serif; background:var(--peach-light); color:var(--near-black); }

    nav {
      background:var(--deep-brown); padding:0 32px;
      display:flex; align-items:center; justify-content:space-between;
      height:62px; position:sticky; top:0; z-index:100;
      box-shadow:0 2px 12px rgba(0,0,0,0.3);
    }
    .nav-brand { display:flex; align-items:center; gap:10px; text-decoration:none; }
    .nav-logo {
      width:50px; height:50px; background:var(--white); border-radius:50%;
      display:flex; align-items:center; justify-content:center; overflow:hidden;
    }
    .nav-logo img { width:50px; height:50px; object-fit:cover; border-radius:50%; }
    .nav-brand-name { font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--peach); }
    .nav-brand-name span { color:var(--bright-orange); }
    .nav-search { flex:1; max-width:460px; margin:0 28px; position:relative; }
    .nav-search input {
      width:100%; padding:8px 16px 8px 38px; border-radius:24px; border:none;
      background:rgba(255,255,255,0.12); color:var(--white);
      font-family:'Poppins',sans-serif; font-size:0.84rem; outline:none;
    }
    .nav-search input::placeholder { color:rgba(255,255,255,0.5); }
    .nav-search .si { position:absolute; left:12px; top:50%; transform:translateY(-50%); color:rgba(255,255,255,0.5); }
    .nav-links { display:flex; align-items:center; gap:6px; list-style:none; }
    .nav-links a {
      color:var(--peach); text-decoration:none; font-size:0.85rem;
      padding:6px 12px; border-radius:6px; transition:background 0.2s;
      display:flex; align-items:center; gap:6px;
    }
    .nav-links a:hover { background:rgba(255,255,255,0.08); }

    .tab-bar { background:var(--white); border-bottom:1px solid var(--peach); padding:0 32px; display:flex; }
    .tab-bar a {
      padding:14px 20px; font-size:0.88rem; color:var(--mid-brown);
      text-decoration:none; display:flex; align-items:center; gap:7px;
      border-bottom:2px solid transparent; transition:color 0.2s,border-color 0.2s;
    }
    .tab-bar a:hover { color:var(--burnt-orange); }
    .tab-bar a.active { color:var(--burnt-orange); border-bottom-color:var(--burnt-orange); font-weight:600; }

    .page-wrap { max-width:980px; margin:32px auto; padding:0 20px 48px; }
    .page-title { font-family:'Playfair Display',serif; font-size:1.6rem; color:var(--near-black); margin-bottom:4px; }
    .page-subtitle { font-size:0.82rem; color:var(--warm-brown); margin-bottom:28px; }

    .profile-grid { display:grid; grid-template-columns:280px 1fr; gap:24px; align-items:start; }

    .avatar-card {
      background:var(--white); border-radius:16px; padding:32px 24px;
      text-align:center; box-shadow:0 4px 20px rgba(44,21,3,0.10);
      animation:fadeIn 0.4s ease both;
    }
    @keyframes fadeIn { from{opacity:0;transform:translateY(16px);}to{opacity:1;transform:none;} }

    .avatar-wrap { position:relative; display:inline-block; margin-bottom:16px; }
    .avatar-circle {
      width:110px; height:110px; border-radius:50%;
      background:linear-gradient(135deg,var(--peach),var(--burnt-orange));
      display:flex; align-items:center; justify-content:center;
      font-size:2.8rem; color:var(--white);
      font-family:'Playfair Display',serif; font-weight:700;
      border:4px solid var(--white);
      box-shadow:0 4px 18px rgba(204,85,0,0.25);
      overflow:hidden;
    }
    .avatar-circle img { width:100%; height:100%; object-fit:cover; }
    .avatar-edit-btn {
      position:absolute; bottom:4px; right:4px;
      width:30px; height:30px;
      background:var(--burnt-orange); border:2px solid var(--white); border-radius:50%;
      display:flex; align-items:center; justify-content:center;
      font-size:0.75rem; cursor:pointer; transition:background 0.2s;
    }
    .avatar-edit-btn:hover { background:#b34a00; }

    .profile-name { font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--near-black); margin-bottom:3px; }
    .profile-handle { font-size:0.78rem; color:var(--warm-brown); margin-bottom:16px; }

    .stats-row {
      display:flex; justify-content:center; gap:16px; margin-bottom:20px;
      padding:12px 0; border-top:1px solid var(--peach); border-bottom:1px solid var(--peach);
    }
    .stat-item { text-align:center; }
    .stat-item .val { font-size:1.1rem; font-weight:700; color:var(--burnt-orange); }
    .stat-item .lbl { font-size:0.68rem; color:var(--warm-brown); }

    .side-menu { list-style:none; text-align:left; }
    .side-menu li a {
      display:flex; align-items:center; gap:10px;
      padding:9px 12px; border-radius:8px;
      text-decoration:none; font-size:0.84rem; color:var(--mid-brown);
      transition:background 0.2s,color 0.2s;
    }
    .side-menu li a:hover { background:var(--peach-light); color:var(--burnt-orange); }
    .side-menu li a.active { background:var(--peach); color:var(--burnt-orange); font-weight:600; }
    .side-menu .menu-icon { font-size:1rem; width:20px; text-align:center; }

    .btn-logout {
      margin-top:16px; width:100%; padding:9px;
      background:none; border:1.5px solid #e2c9b0;
      border-radius:8px; color:var(--warm-brown);
      font-family:'Poppins',sans-serif; font-size:0.82rem; cursor:pointer;
      transition:background 0.2s,color 0.2s;
    }
    .btn-logout:hover { background:#fff0f0; color:#cc0000; border-color:#ffcccc; }

    .info-card {
      background:var(--white); border-radius:16px; padding:32px;
      box-shadow:0 4px 20px rgba(44,21,3,0.10);
      animation:fadeIn 0.4s 0.1s ease both;
    }
    .section-title {
      font-family:'Playfair Display',serif; font-size:1.1rem; color:var(--near-black);
      margin-bottom:20px; padding-bottom:10px;
      border-bottom:2px solid var(--peach);
      display:flex; align-items:center; justify-content:space-between;
    }
    .edit-toggle-btn {
      background:var(--burnt-orange); color:var(--white); border:none;
      padding:5px 14px; border-radius:6px;
      font-family:'Poppins',sans-serif; font-size:0.75rem; font-weight:600;
      cursor:pointer; transition:background 0.2s;
    }
    .edit-toggle-btn:hover { background:#b34a00; }

    .form-row { display:grid; grid-template-columns:1fr 1fr; gap:18px; margin-bottom:18px; }
    .form-row.full { grid-template-columns:1fr; }
    .form-group label { display:block; font-size:0.78rem; font-weight:500; color:var(--mid-brown); margin-bottom:5px; }
    .form-group input,.form-group select,.form-group textarea {
      width:100%; padding:9px 13px;
      border:1.5px solid #e2c9b0; border-radius:8px;
      font-family:'Poppins',sans-serif; font-size:0.85rem;
      color:var(--near-black); background:#fffaf6; outline:none;
      transition:border-color 0.2s;
    }
    .form-group input:focus,.form-group select:focus,.form-group textarea:focus {
      border-color:var(--burnt-orange);
      box-shadow:0 0 0 3px rgba(204,85,0,0.08);
    }
    .form-group input:disabled,.form-group select:disabled,.form-group textarea:disabled {
      background:#f5f0eb; color:var(--warm-brown); cursor:not-allowed;
    }
    .form-group textarea { resize:vertical; min-height:70px; }

    .save-row { display:flex; gap:12px; justify-content:flex-end; margin-top:4px; }
    .btn-save {
      padding:9px 28px; background:var(--burnt-orange); color:var(--white);
      border:none; border-radius:8px;
      font-family:'Poppins',sans-serif; font-size:0.88rem; font-weight:600;
      cursor:pointer; transition:background 0.2s;
      box-shadow:0 3px 10px rgba(204,85,0,0.2);
    }
    .btn-save:hover { background:#b34a00; }
    .btn-cancel {
      padding:9px 20px; background:none; color:var(--warm-brown);
      border:1.5px solid #e2c9b0; border-radius:8px;
      font-family:'Poppins',sans-serif; font-size:0.88rem; cursor:pointer;
      transition:background 0.2s;
    }
    .btn-cancel:hover { background:var(--peach-light); }

    .flash-msg {
      padding:10px 14px; border-radius:8px;
      font-size:0.82rem; font-weight:600; margin-bottom:18px;
    }
    .flash-msg.success { background:#f0fff4; color:#2e7d32; border:1px solid #b2dfdb; }
    .flash-msg.error   { background:#fff0f0; color:#cc0000; border:1px solid #ffcccc; }

    .order-card {
      background:var(--white); border-radius:16px; padding:28px 32px;
      box-shadow:0 4px 20px rgba(44,21,3,0.10); margin-top:24px;
      animation:fadeIn 0.4s 0.2s ease both;
    }
    .order-row { display:flex; align-items:center; gap:16px; padding:13px 0; border-bottom:1px solid var(--peach); }
    .order-row:last-child { border-bottom:none; }
    .order-icon { width:44px; height:44px; background:var(--peach-card); border-radius:10px; display:flex; align-items:center; justify-content:center; font-size:1.3rem; flex-shrink:0; }
    .order-info { flex:1; }
    .order-info .order-name { font-size:0.9rem; font-weight:600; color:var(--near-black); }
    .order-info .order-date { font-size:0.74rem; color:var(--warm-brown); }
    .order-price { font-weight:700; color:var(--burnt-orange); font-size:0.92rem; }
    .order-status { font-size:0.72rem; padding:3px 10px; border-radius:20px; font-weight:600; }
    .status-delivered { background:#e8f5e9; color:#2e7d32; }
    .status-cancelled { background:#fff3e0; color:#e65100; }

    .toast {
      position:fixed; bottom:28px; right:28px;
      background:var(--deep-brown); color:var(--peach);
      padding:12px 22px; border-radius:10px;
      font-size:0.84rem; font-weight:500;
      box-shadow:0 6px 24px rgba(0,0,0,0.25);
      opacity:0; transform:translateY(16px);
      transition:all 0.3s; z-index:1000; pointer-events:none;
    }
    .toast.show { opacity:1; transform:translateY(0); }

    @media(max-width:720px){
      .profile-grid{grid-template-columns:1fr;}
      .form-row{grid-template-columns:1fr;}
    }
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
  <ul class="nav-links">
    <li><a href="my_cart.php">🛒 Cart <span style="background:var(--burnt-orange);color:#fff;border-radius:50%;width:18px;height:18px;display:inline-flex;align-items:center;justify-content:center;font-size:0.65rem;">3</span></a></li>
  </ul>
</nav>

<div class="tab-bar">
<<<<<<< HEAD
  <a href="index.php"> Home</a>
  <a href="my_orders.php"> My Orders</a>
=======
  <a href="index.php">🏠 Home</a>
  <a href="my_orders.php">📦 My Orders</a>
>>>>>>> cb72e933b77b3a24b02f699e47ba769dd7907e3b
  <a href="#">🔔 Notifications</a>
  <a href="user_profile.php" class="active">👤 Profile</a>
</div>

<div class="page-wrap">
  <h2 class="page-title">My Profile</h2>
  <p class="page-subtitle">Manage your account details and preferences</p>

  <?php if (!empty($success_msg)): ?>
    <div class="flash-msg success"><?php echo $success_msg; ?></div>
  <?php elseif (!empty($error_msg)): ?>
    <div class="flash-msg error"><?php echo $error_msg; ?></div>
  <?php endif; ?>

  <div class="profile-grid">
    <!-- LEFT -->
    <div>
      <div class="avatar-card">
        <div class="avatar-wrap">
          <div class="avatar-circle" id="avatarCircle">
            <span id="avatarInitials"><?php echo $initials; ?></span>
          </div>
          <div class="avatar-edit-btn" onclick="document.getElementById('avatarInput').click()" title="Change photo">✏️</div>
          <input type="file" id="avatarInput" accept="image/*" style="display:none" onchange="previewAvatar(event)"/>
        </div>
        <div class="profile-name" id="displayName"><?php echo $full_name; ?></div>
        <div class="profile-handle" id="displayEmail"><?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?></div>

        <div class="stats-row">
          <div class="stat-item"><div class="val">12</div><div class="lbl">Orders</div></div>
          <div class="stat-item"><div class="val">4.8⭐</div><div class="lbl">Rating</div></div>
          <div class="stat-item"><div class="val">Rs 4,200</div><div class="lbl">Spent</div></div>
        </div>

        <ul class="side-menu">
          <li><a href="#" class="active"><span class="menu-icon">👤</span> Personal Info</a></li>
          <li><a href="my_orders.php"><span class="menu-icon">📦</span> Order History</a></li>
          <li><a href="#"><span class="menu-icon">📍</span> Addresses</a></li>
<<<<<<< HEAD
          <li><a href="#"><span class="menu-icon"></span> Payment Methods</a></li>
          <li><a href="#"><span class="menu-icon"></span> Notifications</a></li>
          <li><a href="#"><span class="menu-icon"></span> Change Password</a></li>
=======
          <li><a href="#"><span class="menu-icon">💳</span> Payment Methods</a></li>
          <li><a href="#"><span class="menu-icon">🔔</span> Notifications</a></li>
          <li><a href="#"><span class="menu-icon">🔒</span> Change Password</a></li>
>>>>>>> cb72e933b77b3a24b02f699e47ba769dd7907e3b
        </ul>

        <form method="POST" action="logout.php">
          <button type="submit" class="btn-logout">Logout</button>
        </form>
      </div>
    </div>

    <!-- RIGHT -->
    <div>
      <div class="info-card">
        <div class="section-title">
          Personal Information
          <button class="edit-toggle-btn" onclick="toggleEdit()" id="editBtn">✏️ Edit</button>
        </div>

        <form method="POST" action="user_profile.php" id="profileForm">
          <input type="hidden" name="csrf_token" value="<?php echo $_SESSION['csrf_token']; ?>"/>
          <input type="hidden" name="save_profile" value="1"/>

          <div class="form-row">
            <div class="form-group">
              <label>First Name</label>
              <input type="text" id="firstName" name="first_name"
                     value="<?php echo htmlspecialchars($u['first_name'], ENT_QUOTES, 'UTF-8'); ?>" disabled/>
            </div>
            <div class="form-group">
              <label>Last Name</label>
              <input type="text" id="lastName" name="last_name"
                     value="<?php echo htmlspecialchars($u['last_name'], ENT_QUOTES, 'UTF-8'); ?>" disabled/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Email Address</label>
              <input type="email" id="email" name="email"
                     value="<?php echo htmlspecialchars($u['email'], ENT_QUOTES, 'UTF-8'); ?>" disabled/>
            </div>
            <div class="form-group">
              <label>Phone Number</label>
              <input type="tel" id="phone" name="phone"
                     value="<?php echo htmlspecialchars($u['phone'], ENT_QUOTES, 'UTF-8'); ?>" disabled/>
            </div>
          </div>
          <div class="form-row">
            <div class="form-group">
              <label>Date of Birth</label>
              <input type="date" id="dob" name="dob"
                     value="<?php echo htmlspecialchars($u['dob'], ENT_QUOTES, 'UTF-8'); ?>" disabled/>
            </div>
            <div class="form-group">
              <label>Gender</label>
              <select id="gender" name="gender" disabled>
                <option value="male"   <?php echo $u['gender']==='male'  ?'selected':''; ?>>Male</option>
                <option value="female" <?php echo $u['gender']==='female'?'selected':''; ?>>Female</option>
                <option value="other"  <?php echo $u['gender']==='other' ?'selected':''; ?>>Prefer not to say</option>
              </select>
            </div>
          </div>
          <div class="form-row full">
            <div class="form-group">
              <label>Delivery Address</label>
              <textarea id="address" name="address" disabled><?php echo htmlspecialchars($u['address'], ENT_QUOTES, 'UTF-8'); ?></textarea>
            </div>
          </div>

          <div class="save-row" id="saveRow" style="display:none;">
            <button type="button" class="btn-cancel" onclick="cancelEdit()">Cancel</button>
            <button type="submit" class="btn-save">Save Changes</button>
          </div>
        </form>
      </div>

      <!-- Order history -->
      <div class="order-card">
        <div class="section-title">Recent Orders</div>
        <?php foreach ($orders as $order): ?>
        <div class="order-row">
          <div class="order-icon"><?php echo $order['icon']; ?></div>
          <div class="order-info">
            <div class="order-name"><?php echo htmlspecialchars($order['name'], ENT_QUOTES, 'UTF-8'); ?></div>
            <div class="order-date"><?php echo htmlspecialchars($order['date'], ENT_QUOTES, 'UTF-8'); ?></div>
          </div>
          <div class="order-price"><?php echo htmlspecialchars($order['price'], ENT_QUOTES, 'UTF-8'); ?></div>
          <span class="order-status <?php echo $order['status_class']; ?>"><?php echo $order['status']; ?></span>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </div>
</div>

<div class="toast" id="toast"></div>

<script>
  let isEditing = false;
  const fields = ['firstName','lastName','email','phone','dob','gender','address'];
  let origVals = {};

  function toggleEdit() {
    if (!isEditing) {
      fields.forEach(id => { origVals[id] = document.getElementById(id).value; document.getElementById(id).disabled = false; });
      document.getElementById('editBtn').textContent = '✕ Cancel';
      document.getElementById('saveRow').style.display = 'flex';
      isEditing = true;
    } else { cancelEdit(); }
  }

  function cancelEdit() {
    fields.forEach(id => { document.getElementById(id).value = origVals[id]; document.getElementById(id).disabled = true; });
    document.getElementById('editBtn').textContent = '✏️ Edit';
    document.getElementById('saveRow').style.display = 'none';
    isEditing = false;
  }

  function previewAvatar(event) {
    const file = event.target.files[0];
    if (!file) return;
    const reader = new FileReader();
    reader.onload = e => {
      document.getElementById('avatarCircle').innerHTML = `<img src="${e.target.result}" alt="Avatar"/>`;
    };
    reader.readAsDataURL(file);
  }

  function showToast(msg) {
    const t = document.getElementById('toast');
    t.textContent = msg; t.classList.add('show');
    setTimeout(() => t.classList.remove('show'), 3000);
  }

  <?php if (!empty($success_msg)): ?>
    window.addEventListener('load', () => showToast('<?php echo addslashes($success_msg); ?>'));
  <?php endif; ?>
</script>
</body>
</html>
