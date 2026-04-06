<?php 
session_start();
include('db1.php');

// --- CHEF ACCESS ONLY ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'chef') {
    header("Location: login.php");
    exit();
}

// --- UPDATE ORDER STATUS ---
if(isset($_POST['update_status'])) {
    $order_id = (int)$_POST['order_id'];
    $new_status = mysqli_real_escape_string($conn, $_POST['status']);
    $allowed = ['Pending', 'Preparing', 'Ready', 'Delivered', 'Cancelled'];
    if(in_array($new_status, $allowed)) {
        mysqli_query($conn, "UPDATE orders SET status='$new_status' WHERE id=$order_id");
    }
    header("Location: chef-orders.php");
    exit();
}

// --- FILTER LOGIC ---
$status_filter = isset($_GET['status']) ? mysqli_real_escape_string($conn, $_GET['status']) : '';
$date_filter = isset($_GET['date']) ? mysqli_real_escape_string($conn, $_GET['date']) : '';

$query = "SELECT * FROM orders WHERE 1=1";
if(!empty($status_filter)) $query .= " AND status='$status_filter'";
if(!empty($date_filter)) $query .= " AND DATE(created_at)='$date_filter'";
$query .= " ORDER BY id DESC";

$ordersRes = mysqli_query($conn, $query);
$totalOrders = $ordersRes ? mysqli_num_rows($ordersRes) : 0;

// Notification count
$notifCount = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status='Pending'"))[0] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <title>Swaad Unlimited - Order Status</title>
    <style>
        .notif-badge { background: #e74c3c; color: white; border-radius: 50%; padding: 1px 6px; font-size: 11px; margin-left: 4px; }
        .status-form { display: inline-flex; gap: 6px; align-items: center; }
        .status-form select { padding: 5px 8px; border-radius: 6px; border: 1px solid #ddd; font-size: 13px; font-family: 'Poppins', sans-serif; }
        .update-btn { padding: 5px 12px; background: var(--bright-orange, #e67e22); color: white; border: none; border-radius: 6px; cursor: pointer; font-size: 13px; }
        .update-btn:hover { opacity: 0.85; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-section">
            <img src="logo.png.png" alt="Logo">
            <div>
                <span style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--peach);">Swaad</span><span style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--bright-orange);">Unlimited</span>
                <small>Chef Panel</small>
            </div>
        </div>
        <ul class="nav-links">
            <li><a href="chef.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li><a href="chef-foods.php"><i class="fas fa-utensils"></i> Manage Menu</a></li>
            <li><a href="chef-orders.php"><i class="fas fa-receipt"></i> Order Status</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-nav">
            <div class="left-section">
                <img src="logo.png.png" alt="Logo" class="nav-logo">
                <h2 class="nav-title">Chef Panel</h2>
            </div>
            <div class="right-section">
                <div class="notification-icon" onclick="toggleNotifDropdown()" style="cursor:pointer;position:relative;">
                    <i class="far fa-bell"></i>
                    <?php if($notifCount > 0): ?>
                        <span class="notif-badge"><?php echo $notifCount; ?></span>
                    <?php endif; ?>
                    <div id="notifDropdown" style="display:none;position:absolute;right:0;top:38px;background:white;border-radius:10px;box-shadow:0 4px 20px rgba(0,0,0,0.13);min-width:220px;z-index:999;padding:12px;">
                        <p style="margin:0 0 8px;font-weight:600;font-size:13px;color:#333;">Pending Orders</p>
                        <?php
                        $notifOrders = mysqli_query($conn, "SELECT id, customer_name FROM orders WHERE status='Pending' ORDER BY id DESC LIMIT 5");
                        if($notifOrders && mysqli_num_rows($notifOrders) > 0):
                            while($n = mysqli_fetch_assoc($notifOrders)):
                        ?>
                        <div style="padding:6px 0;border-bottom:1px solid #f0f0f0;font-size:13px;">
                            <i class="fas fa-circle" style="color:#e67e22;font-size:8px;margin-right:6px;"></i>
                            Order #<?php echo $n['id']; ?> — <?php echo htmlspecialchars($n['customer_name'] ?? 'Customer'); ?>
                        </div>
                        <?php endwhile; else: ?>
                        <p style="color:#aaa;font-size:13px;margin:0;">No pending orders 🎉</p>
                        <?php endif; ?>
                        <a href="chef-orders.php" style="display:block;margin-top:10px;text-align:center;color:var(--bright-orange,#e67e22);font-size:13px;text-decoration:none;font-weight:600;">View All Orders →</a>
                    </div>
                </div>
                <div class="admin-profile-btn">
                    <i class="fas fa-hat-chef"></i>
                    <span>Chef</span>
                </div>
                <a href="logout.php" class="logout-btn"><i class="fas fa-sign-out-alt"></i></a>
            </div>
        </div>

        <div class="header-container">
            <h2 class="page-title">Order Status</h2>
            <button class="btn-export" onclick="window.print()">
                <i class="fas fa-download"></i> Export
            </button>
        </div>

        <!-- FILTERS -->
        <form method="GET" action="chef-orders.php" class="filter-bar">
            <div class="filter-group">
                <div class="input-wrapper">
                    <i class="fas fa-filter"></i>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="Pending"    <?php if($status_filter=='Pending')    echo 'selected'; ?>>Pending</option>
                        <option value="Preparing"  <?php if($status_filter=='Preparing')  echo 'selected'; ?>>Preparing</option>
                        <option value="Ready"      <?php if($status_filter=='Ready')      echo 'selected'; ?>>Ready</option>
                        <option value="Delivered"  <?php if($status_filter=='Delivered')  echo 'selected'; ?>>Delivered</option>
                        <option value="Cancelled"  <?php if($status_filter=='Cancelled')  echo 'selected'; ?>>Cancelled</option>
                    </select>
                </div>
                <div class="input-wrapper">
                    <i class="fas fa-calendar-alt"></i>
                    <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                </div>
                <?php if(!empty($status_filter) || !empty($date_filter)): ?>
                    <a href="chef-orders.php" style="color:#d35400;text-decoration:none;font-size:13px;align-self:center;">Clear Filters</a>
                <?php endif; ?>
            </div>
            <div class="stats-badge">Total Orders: <?php echo $totalOrders; ?></div>
        </form>

        <!-- ORDERS TABLE -->
        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Items</th>
                        <th>Date</th>
                        <th>Current Status</th>
                        <th>Update Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if($ordersRes && mysqli_num_rows($ordersRes) > 0):
                        while($order = mysqli_fetch_assoc($ordersRes)):
                            $sc = strtolower(str_replace(' ', '-', $order['status']));
                    ?>
                    <tr>
                        <td>#<?php echo $order['id']; ?></td>
                        <td><strong><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></strong></td>
                        <td><?php echo htmlspecialchars($order['items'] ?? 'N/A'); ?></td>
                        <td class="timestamp"><?php echo $order['created_at'] ?? '—'; ?></td>
                        <td><span class="badge <?php echo $sc; ?>"><?php echo $order['status']; ?></span></td>
                        <td>
                            <form method="POST" action="chef-orders.php" class="status-form">
                                <input type="hidden" name="order_id" value="<?php echo $order['id']; ?>">
                                <select name="status">
                                    <option value="Pending"   <?php if($order['status']=='Pending')   echo 'selected'; ?>>Pending</option>
                                    <option value="Preparing" <?php if($order['status']=='Preparing') echo 'selected'; ?>>Preparing</option>
                                    <option value="Ready"     <?php if($order['status']=='Ready')     echo 'selected'; ?>>Ready</option>
                                    <option value="Delivered" <?php if($order['status']=='Delivered') echo 'selected'; ?>>Delivered</option>
                                    <option value="Cancelled" <?php if($order['status']=='Cancelled') echo 'selected'; ?>>Cancelled</option>
                                </select>
                                <button type="submit" name="update_status" class="update-btn">
                                    <i class="fas fa-sync-alt"></i> Update
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; else: ?>
                    <tr><td colspan="6" class="no-data">No orders found.</td></tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>

    <script>
        function toggleNotifDropdown() {
            const d = document.getElementById('notifDropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        }
        document.addEventListener('click', function(e) {
            const icon = document.querySelector('.notification-icon');
            if (icon && !icon.contains(e.target)) {
                document.getElementById('notifDropdown').style.display = 'none';
            }
        });
    </script>
</body>
</html>