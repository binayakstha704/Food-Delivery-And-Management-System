<?php 
session_start();
include('../config/db1.php');

// --- CHEF ACCESS ONLY ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'chef') {
    header("Location: pages/login.php");
    exit();
} 

// --- FETCH STATS ---
$menuCount = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM foods"))[0];
$pendingOrders = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status='Pending'"))[0] ?? 0;
$preparingOrders = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status='Preparing'"))[0] ?? 0;
$completedToday = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status='Delivered' AND DATE(updated_at)=CURDATE()"))[0] ?? 0;

// --- FETCH RECENT ORDERS ---
$ordersRes = mysqli_query($conn, "SELECT * FROM orders ORDER BY id DESC LIMIT 10");

// --- FETCH NOTIFICATION COUNT ---
$notifCount = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status='Pending'"))[0] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../assests/Heraldcanteen.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <title>Herald Canteen - Chef Panel</title>
</head>
<body>
    <div class="sidebar">
        <div class="logo-section">
            <img src="../Canteen.PNG" alt="Logo">
            <div>
               <span class="brand-text">
  HERALD <span>CANTEEN</span>
</span>
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
                <img src="../Canteen.PNG" alt="Logo" class="nav-logo">
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

        <div class="header">
            <h2>Chef Dashboard</h2>
        </div>

        <div class="stats-grid">
            <div class="stat-card">
                <p><i class="fas fa-utensils" style="margin-right:6px;color:#e67e22;"></i>Menu Items</p>
                <h1><?php echo $menuCount; ?></h1>
            </div>
            <div class="stat-card">
                <p><i class="fas fa-hourglass-half" style="margin-right:6px;color:#e74c3c;"></i>Pending Orders</p>
                <h1><?php echo $pendingOrders; ?></h1>
            </div>
            <div class="stat-card">
                <p><i class="fas fa-fire" style="margin-right:6px;color:#f39c12;"></i>Preparing</p>
                <h1><?php echo $preparingOrders; ?></h1>
            </div>
            <div class="stat-card">
                <p><i class="fas fa-check-circle" style="margin-right:6px;color:#27ae60;"></i>Delivered Today</p>
                <h1><?php echo $completedToday; ?></h1>
            </div>
        </div>

        <h3>Recent Orders</h3>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Status</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if($ordersRes && mysqli_num_rows($ordersRes) > 0):
                    while($order = mysqli_fetch_assoc($ordersRes)): 
                        $statusClass = strtolower(str_replace(' ', '-', $order['status']));
                ?>
                <tr>
                    <td>#<?php echo $order['id']; ?></td>
                    <td><?php echo htmlspecialchars($order['customer_name'] ?? 'N/A'); ?></td>
                    <td><?php echo htmlspecialchars($order['items'] ?? 'N/A'); ?></td>
                    <td><span class="badge <?php echo $statusClass; ?>"><?php echo $order['status']; ?></span></td>
                    <td>
                        <a href="chef-orders.php?update_id=<?php echo $order['id']; ?>" class="edit-btn" title="Update Status">
                            <i class="fas fa-sync-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="no-data">No orders found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <script>
        function toggleNotifDropdown() {
            const d = document.getElementById('notifDropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        }
        // Close dropdown when clicking outside
        document.addEventListener('click', function(e) {
            const icon = document.querySelector('.notification-icon');
            if (icon && !icon.contains(e.target)) {
                document.getElementById('notifDropdown').style.display = 'none';
            }
        });
    </script>
</body>
</html>