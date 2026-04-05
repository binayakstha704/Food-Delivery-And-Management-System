<?php 
include('db1.php'); 

// 1. Get Filter Values
$status_filter = isset($_GET['status']) ? $_GET['status'] : '';
$date_filter = isset($_GET['date']) ? $_GET['date'] : '';

// 2. Build Dynamic Query
$query = "SELECT * FROM activity_logs WHERE 1=1";

if (!empty($status_filter)) {
    $safe_status = mysqli_real_escape_string($conn, $status_filter);
    $query .= " AND status = '$safe_status'";
}

if (!empty($date_filter)) {
    $safe_date = mysqli_real_escape_string($conn, $date_filter);
    $query .= " AND DATE(timestamp) = '$safe_date'";
}

$query .= " ORDER BY id DESC";
$logRes = mysqli_query($conn, $query);

// 3. Get Total Count for the badge
$totalLogsCount = mysqli_num_rows($logRes);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Swaad Unlimited - User Logs</title>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
</head>
<body>
    <div class="sidebar">
        <div class="logo-section">
            <img src="logo.png.png" alt="Logo">
            <div>
                <span style="font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--peach);">Swaad</span><span style="font-family:'Playfair Display',serif; font-size:1.2rem; color:var(--bright-orange);">Unlimited</span></span>
                <small>Admin Panel</small>
            </div>
        </div>
        <ul class="nav-links">
            <li ><a href="index.php"><i class="fas fa-th-large"></i> Dashboard</a></li>
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
            <i class="far fa-user"></i>
            <span>Admin</span>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
        <div class="header-container">
            <h2 class="page-title">User Activity Logs</h2>
            <button class="btn-export" onclick="window.print()">
                <i class="fas fa-download"></i> Export Logs
            </button>
        </div>
        
        <form method="GET" action="user-logs.php" class="filter-bar">
            <div class="filter-group">
                <div class="input-wrapper">
                    <i class="fas fa-filter"></i>
                    <select name="status" onchange="this.form.submit()">
                        <option value="">All Status</option>
                        <option value="success" <?php if($status_filter == 'success') echo 'selected'; ?>>Success</option>
                        <option value="warning" <?php if($status_filter == 'warning') echo 'selected'; ?>>Warning</option>
                        <option value="failed" <?php if($status_filter == 'failed') echo 'selected'; ?>>Failed</option>
                    </select>
                </div>
                <div class="input-wrapper">
                    <i class="fas fa-calendar-alt"></i>
                    <input type="date" name="date" value="<?php echo $date_filter; ?>" onchange="this.form.submit()">
                </div>
                <?php if(!empty($status_filter) || !empty($date_filter)): ?>
                    <a href="user-logs.php" style="color: #d35400; text-decoration: none; font-size: 13px; align-self: center;">Clear Filters</a>
                <?php endif; ?>
            </div>
            
            <div class="stats-badge">
                Total Logs: <?php echo $totalLogsCount; ?>
            </div>
        </form>

        <div class="table-container">
            <table>
                <thead>
                    <tr>
                        <th>ID</th>
                        <th>User</th>
                        <th>Action</th>
                        <th>Timestamp</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>
                    <?php
                    if ($logRes && mysqli_num_rows($logRes) > 0) {
                        while($log = mysqli_fetch_assoc($logRes)) {
                            $statusClass = strtolower($log['status']); 
                            echo "<tr>
                                    <td>#{$log['id']}</td>
                                    <td><strong>{$log['username']}</strong></td>
                                    <td>{$log['action']}</td>
                                    <td class='timestamp'>{$log['timestamp']}</td>
                                    <td><span class='badge {$statusClass}'>{$log['status']}</span></td>
                                  </tr>";
                        }
                    } else {
                        echo "<tr><td colspan='5' class='no-data'>No results matching your filters.</td></tr>";
                    }
                    ?>
                </tbody>
            </table>
        </div>
    </div>
</body>
</html>
