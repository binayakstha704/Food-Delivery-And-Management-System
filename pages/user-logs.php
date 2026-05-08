<?php
require_once "../includes/auth.php";
configure_secure_session();
session_start();
session_security_check();
require_once "../config/db.php";

// Only admin/chef/staff can view logs
require_login();
$allowed_roles = ['chef', 'staff'];
if (!in_array($_SESSION['role'] ?? '', $allowed_roles, true)) {
    header('Location: dashboard.php');
    exit;
}

// ── Filters ──────────────────────────────────────────────────
$status_filter = trim($_GET['status'] ?? '');
$date_filter   = trim($_GET['date']   ?? '');
$search_filter = trim($_GET['search'] ?? '');

// ── Build query from orders + users (activity logs) ──────────
// We display login/order activity derived from the existing schema.
// orders table gives us real user activity.
$where = ['1=1'];
$params = [];
$types  = '';

if ($status_filter !== '') {
    $allowed_statuses = ['pending','preparing','ready','out_for_delivery','delivered','cancelled'];
    if (in_array($status_filter, $allowed_statuses, true)) {
        $where[] = 'o.status = ?';
        $params[] = $status_filter;
        $types .= 's';
    }
}
if ($date_filter !== '') {
    $where[] = 'DATE(o.created_at) = ?';
    $params[] = $date_filter;
    $types .= 's';
}
if ($search_filter !== '') {
    $where[] = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like = '%' . $search_filter . '%';
    $params[] = $like;
    $params[] = $like;
    $types .= 'ss';
}

$where_sql = implode(' AND ', $where);

$sql = "SELECT o.order_id, u.full_name, u.email, o.total_amount,
               o.status, o.payment_method, o.created_at
        FROM orders o
        JOIN users u ON u.user_id = o.user_id
        WHERE $where_sql
        ORDER BY o.created_at DESC
        LIMIT 200";

$stmt = $conn->prepare($sql);
if (!empty($params)) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$logs = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$total_count = count($logs);

// Recent registrations for the summary cards
$new_users_today = $conn->query("SELECT COUNT(*) FROM users WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$orders_today    = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$pending_orders  = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0] ?? 0;

// Status badge classes
function status_class(string $s): string {
    return match($s) {
        'pending'          => 'badge-warning',
        'preparing'        => 'badge-info',
        'ready'            => 'badge-success',
        'out_for_delivery' => 'badge-primary',
        'delivered'        => 'badge-delivered',
        'cancelled'        => 'badge-danger',
        default            => 'badge-secondary',
    };
}
function status_label(string $s): string {
    return ucwords(str_replace('_', ' ', $s));
}

$role = $_SESSION['role'];
$chef_name = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Logs – Herald Canteen</title>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        body { background: #0f0f0f; color: #fff; font-family: 'Inter', sans-serif; }
        .logs-shell { display: flex; min-height: 100vh; }

        /* Sidebar */
        .logs-sidebar {
            width: 220px;
            background: #111;
            border-right: 1px solid rgba(255,255,255,0.07);
            display: flex;
            flex-direction: column;
            padding: 24px 0;
            flex-shrink: 0;
        }
        .logs-sidebar .brand {
            padding: 0 20px 24px;
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .logs-sidebar .brand h2 { font-size: 18px; font-weight: 800; color: #fff; }
        .logs-sidebar .brand span { color: #4db848; }
        .logs-sidebar .brand small { display: block; font-size: 11px; color: rgba(255,255,255,0.3); text-transform: uppercase; letter-spacing: 1px; margin-top: 2px; }
        .sidebar-nav { padding: 16px 0; flex: 1; }
        .sidebar-nav a {
            display: flex;
            align-items: center;
            gap: 10px;
            padding: 11px 20px;
            color: rgba(255,255,255,0.55);
            text-decoration: none;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.15s;
        }
        .sidebar-nav a:hover, .sidebar-nav a.active {
            background: rgba(77,184,72,0.1);
            color: #4db848;
            border-right: 3px solid #4db848;
        }
        .sidebar-nav a .nav-icon { font-size: 16px; }
        .sidebar-logout {
            padding: 16px 20px;
            border-top: 1px solid rgba(255,255,255,0.07);
        }
        .sidebar-logout a {
            display: flex;
            align-items: center;
            gap: 8px;
            color: rgba(255,100,100,0.7);
            text-decoration: none;
            font-size: 13px;
            transition: color 0.15s;
        }
        .sidebar-logout a:hover { color: #e53935; }

        /* Main */
        .logs-main { flex: 1; padding: 32px; overflow-x: auto; }
        .logs-topbar {
            display: flex;
            align-items: center;
            justify-content: space-between;
            margin-bottom: 28px;
        }
        .logs-topbar h1 { font-size: 24px; font-weight: 700; }
        .logs-topbar .chef-tag {
            font-size: 13px;
            color: rgba(255,255,255,0.4);
        }

        /* Summary cards */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 16px;
            margin-bottom: 28px;
        }
        .summary-card {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            padding: 20px 22px;
        }
        .summary-card .sc-label { font-size: 12px; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.8px; }
        .summary-card .sc-value { font-size: 32px; font-weight: 800; margin-top: 6px; }
        .summary-card.green .sc-value { color: #4db848; }
        .summary-card.orange .sc-value { color: #ff9800; }
        .summary-card.blue .sc-value { color: #42a5f5; }

        /* Filter bar */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 12px;
            flex-wrap: wrap;
            margin-bottom: 20px;
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 14px 18px;
        }
        .filter-bar input,
        .filter-bar select {
            background: #111;
            border: 1px solid rgba(255,255,255,0.12);
            color: #fff;
            padding: 9px 14px;
            border-radius: 999px;
            font-size: 13px;
            outline: none;
        }
        .filter-bar input:focus,
        .filter-bar select:focus { border-color: #4db848; }
        .filter-bar .filter-label { font-size: 12px; color: rgba(255,255,255,0.4); margin-right: 4px; }
        .btn-filter {
            background: #1f6f43;
            color: #fff;
            border: none;
            padding: 9px 20px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .btn-filter:hover { background: #155233; }
        .btn-reset {
            background: transparent;
            color: rgba(255,255,255,0.4);
            border: 1px solid rgba(255,255,255,0.15);
            padding: 9px 18px;
            border-radius: 999px;
            font-size: 13px;
            cursor: pointer;
            text-decoration: none;
            transition: all 0.2s;
        }
        .btn-reset:hover { border-color: #e53935; color: #e53935; }
        .count-tag {
            margin-left: auto;
            font-size: 13px;
            color: rgba(255,255,255,0.35);
        }
        .count-tag strong { color: #4db848; }

        /* Table */
        .logs-table-wrap {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 16px;
            overflow: hidden;
        }
        .logs-table {
            width: 100%;
            border-collapse: collapse;
        }
        .logs-table th {
            background: #141414;
            color: rgba(255,255,255,0.4);
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.8px;
            padding: 14px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            font-weight: 600;
        }
        .logs-table td {
            padding: 14px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 13px;
            color: rgba(255,255,255,0.8);
            vertical-align: middle;
        }
        .logs-table tr:last-child td { border-bottom: none; }
        .logs-table tr:hover td { background: rgba(77,184,72,0.04); }
        .user-cell .uname { font-weight: 600; color: #fff; }
        .user-cell .uemail { font-size: 11px; color: rgba(255,255,255,0.3); margin-top: 2px; }
        .amt-cell { font-weight: 700; color: #4db848; }
        .timestamp-cell { font-size: 12px; color: rgba(255,255,255,0.35); }

        /* Badges */
        .badge { display: inline-block; padding: 4px 12px; border-radius: 999px; font-size: 11px; font-weight: 700; text-transform: capitalize; }
        .badge-warning  { background: rgba(255,152,0,0.15); color: #ff9800; }
        .badge-info     { background: rgba(66,165,245,0.15); color: #42a5f5; }
        .badge-success  { background: rgba(77,184,72,0.15); color: #4db848; }
        .badge-primary  { background: rgba(103,58,183,0.15); color: #9c27b0; }
        .badge-delivered{ background: rgba(38,166,154,0.15); color: #26a69a; }
        .badge-danger   { background: rgba(229,57,53,0.15); color: #e53935; }
        .badge-secondary{ background: rgba(255,255,255,0.07); color: rgba(255,255,255,0.5); }

        .payment-pill {
            display: inline-block;
            background: rgba(255,255,255,0.06);
            border-radius: 999px;
            padding: 3px 10px;
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 0.5px;
            color: rgba(255,255,255,0.5);
        }

        .no-data { text-align: center; padding: 48px; color: rgba(255,255,255,0.25); font-size: 15px; }
        .export-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.6);
            padding: 9px 18px;
            border-radius: 999px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .export-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

        @media (max-width: 900px) {
            .summary-cards { grid-template-columns: 1fr 1fr; }
            .logs-sidebar { display: none; }
        }
    </style>
</head>
<body>
<div class="logs-shell">

    <!-- Sidebar -->
    <aside class="logs-sidebar">
        <div class="brand">
            <h2>Herald <span>Canteen</span></h2>
            <small><?php echo strtoupper($role); ?> PORTAL</small>
        </div>
        <nav class="sidebar-nav">
            <?php if ($role === 'chef'): ?>
            <a href="chef-control.php"><span class="nav-icon">👨‍🍳</span> Chef Home</a>
            <?php else: ?>
            <a href="staff-control.php"><span class="nav-icon">🧾</span> Staff Home</a>
            <?php endif; ?>
            <a href="user-logs.php" class="active"><span class="nav-icon">📋</span> Order Logs</a>
        </nav>
        <div class="sidebar-logout">
            <a href="logout.php">🚪 Logout</a>
        </div>
    </aside>

    <!-- Main -->
    <main class="logs-main">
        <div class="logs-topbar">
            <div>
                <h1>📋 Order Activity Logs</h1>
                <div class="chef-tag">Welcome, <?php echo $chef_name; ?></div>
            </div>
            <button class="export-btn" onclick="window.print()">⬇ Export</button>
        </div>

        <!-- Summary cards -->
        <div class="summary-cards">
            <div class="summary-card green">
                <div class="sc-label">New Users Today</div>
                <div class="sc-value"><?php echo $new_users_today; ?></div>
            </div>
            <div class="summary-card orange">
                <div class="sc-label">Orders Today</div>
                <div class="sc-value"><?php echo $orders_today; ?></div>
            </div>
            <div class="summary-card blue">
                <div class="sc-label">Pending Orders</div>
                <div class="sc-value"><?php echo $pending_orders; ?></div>
            </div>
        </div>

        <!-- Filters -->
        <form method="GET" class="filter-bar">
            <span class="filter-label">Status</span>
            <select name="status" onchange="this.form.submit()">
                <option value="">All</option>
                <?php foreach (['pending','preparing','ready','out_for_delivery','delivered','cancelled'] as $s): ?>
                <option value="<?php echo $s; ?>" <?php echo $status_filter === $s ? 'selected' : ''; ?>>
                    <?php echo status_label($s); ?>
                </option>
                <?php endforeach; ?>
            </select>

            <span class="filter-label">Date</span>
            <input type="date" name="date" value="<?php echo htmlspecialchars($date_filter, ENT_QUOTES, 'UTF-8'); ?>" onchange="this.form.submit()">

            <input type="text" name="search" placeholder="Search user…" value="<?php echo htmlspecialchars($search_filter, ENT_QUOTES, 'UTF-8'); ?>">
            <button type="submit" class="btn-filter">Search</button>

            <?php if ($status_filter || $date_filter || $search_filter): ?>
            <a href="user-logs.php" class="btn-reset">Clear</a>
            <?php endif; ?>

            <span class="count-tag">Showing <strong><?php echo $total_count; ?></strong> records</span>
        </form>

        <!-- Table -->
        <div class="logs-table-wrap">
            <table class="logs-table">
                <thead>
                    <tr>
                        <th>Order #</th>
                        <th>Customer</th>
                        <th>Amount</th>
                        <th>Payment</th>
                        <th>Status</th>
                        <th>Time</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if (count($logs) === 0): ?>
                    <tr><td colspan="6" class="no-data">No records found for your filters.</td></tr>
                    <?php else: foreach ($logs as $log): ?>
                    <tr>
                        <td>#<?php echo $log['order_id']; ?></td>
                        <td class="user-cell">
                            <div class="uname"><?php echo htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8'); ?></div>
                            <div class="uemail"><?php echo htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8'); ?></div>
                        </td>
                        <td class="amt-cell">Rs <?php echo number_format((float)$log['total_amount'], 0); ?></td>
                        <td><span class="payment-pill"><?php echo htmlspecialchars($log['payment_method'], ENT_QUOTES, 'UTF-8'); ?></span></td>
                        <td><span class="badge <?php echo status_class($log['status']); ?>"><?php echo status_label($log['status']); ?></span></td>
                        <td class="timestamp-cell"><?php echo htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                    </tr>
                    <?php endforeach; endif; ?>
                </tbody>
            </table>
        </div>
    </main>
</div>
</body>
</html>
