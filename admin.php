<?php include('db.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    
    <title>Swaad Unlimited - Admin</title>
</head>
<body>
    <div class="sidebar">
        <div class="logo-section">
            <img src="logo.png.png" alt="Logo">
            <div>

                <span style="font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--peach);">Swaad</span><span style="font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--bright-orange);">Unlimited</span></span>
                <small>Admin Pane</small>
            </div>
        </div>
         <ul class="nav-links">
            <li ><a href="admin.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
            <li ><a href="manage-foods.php"><i class="fas fa-utensils"></i> Manage Foods</a></li>
            <li ><a href="user-logs.php"><i class="fas fa-clipboard-list"></i> User Logs</a></li>
        </ul>
    </div>

    <div class="main-content">
        <div class="top-nav">
    <div class="left-section">
        <img src="logo.png.png" alt="Logo" class="nav-logo">
        <h2 class="nav-title">Admin Profile</h2>
    </div>

    <div class="right-section">
        <div class="notification-icon">
            
            <i class="far fa-bell"></i>
        </div>
        
        <div class="admin-profile-btn">
            
            <span>Admin</span>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
        <div class="header">
            <h2>Dashboard Overview</h2>
            <div class="admin-profile">Admin Profile</div>
        </div>

        <div class="card" style="background: white; padding: 20px; border-radius: 15px; width: 200px;">
            <p>Menu Items</p>
            <h1>7</h1>
        </div>

        <h3>Recent Orders</h3>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>#1234</td>
                    <td>Ram Sharma</td>
                    <td>Dal Bhat, Momo</td>
                    <td><span class="badge available">Delivered</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>

</html>