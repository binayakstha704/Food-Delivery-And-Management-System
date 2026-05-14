<?php
require_once "../includes/auth.php";
start_session();
session_security_check();
require_once "../config/db.php";
require_once "../includes/functions.php";

// ── Access control: only chef and staff can view logs ─────────────────────────
require_login();

$current_role = $_SESSION['role'] ?? '';
if (!in_array($current_role, ['chef', 'staff'], true)) {
    // Log the access denial before redirecting
    log_user_event(
        $conn,
        'access_denied',
        $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0',
        "Unauthorised access attempt to user-logs.php by user_id " . ($_SESSION['user_id'] ?? 'unknown'),
        isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : null
    );
    header('Location: dashboard.php');
    exit;
}

$viewer_name = htmlspecialchars($_SESSION['full_name'] ?? 'User', ENT_QUOTES, 'UTF-8');
$ip          = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

// ── Which tab is active? ──────────────────────────────────────────────────────
// 'events' shows the user_logs table (login/logout/access events)
// 'orders' shows the orders table (order activity)
$active_tab = ($_GET['tab'] ?? 'events') === 'orders' ? 'orders' : 'events';

/* ============================================================
   TAB 1: USER EVENT LOGS (user_logs table)
   Filters: event_type, date, search (name or email via JOIN)
   ============================================================ */
$ev_type   = trim($_GET['event_type'] ?? '');
$ev_date   = trim($_GET['ev_date']    ?? '');
$ev_search = trim($_GET['ev_search']  ?? '');

$ev_where  = ['1=1'];
$ev_params = [];
$ev_types  = '';

// Filter by event type
$allowed_events = ['login_success', 'login_failed', 'logout', 'access_denied'];
if ($ev_type !== '' && in_array($ev_type, $allowed_events, true)) {
    $ev_where[]  = 'ul.event_type = ?';
    $ev_params[] = $ev_type;
    $ev_types   .= 's';
}

// Filter by date
if ($ev_date !== '') {
    $ev_where[]  = 'DATE(ul.created_at) = ?';
    $ev_params[] = $ev_date;
    $ev_types   .= 's';
}

// Filter by user name or email (LEFT JOIN because user_id can be NULL)
if ($ev_search !== '') {
    $ev_where[]  = '(u.full_name LIKE ? OR u.email LIKE ? OR ul.description LIKE ?)';
    $like        = '%' . $ev_search . '%';
    $ev_params[] = $like;
    $ev_params[] = $like;
    $ev_params[] = $like;
    $ev_types   .= 'sss';
}

$ev_where_sql = implode(' AND ', $ev_where);

$ev_sql = "SELECT
               ul.log_id,
               ul.event_type,
               ul.ip_address,
               ul.description,
               ul.created_at,
               u.full_name,
               u.email,
               u.role
           FROM user_logs ul
           LEFT JOIN users u ON u.user_id = ul.user_id
           WHERE {$ev_where_sql}
           ORDER BY ul.created_at DESC
           LIMIT 300";

$ev_stmt = $conn->prepare($ev_sql);
if (!empty($ev_params)) {
    $ev_stmt->bind_param($ev_types, ...$ev_params);
}
$ev_stmt->execute();
$event_logs = $ev_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ev_stmt->close();

// Summary counts for the event log cards
$logins_today  = $conn->query("SELECT COUNT(*) FROM user_logs WHERE event_type = 'login_success' AND DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$failures_today= $conn->query("SELECT COUNT(*) FROM user_logs WHERE event_type = 'login_failed'  AND DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$logouts_today = $conn->query("SELECT COUNT(*) FROM user_logs WHERE event_type = 'logout'        AND DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$denied_today  = $conn->query("SELECT COUNT(*) FROM user_logs WHERE event_type = 'access_denied' AND DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;

/* ============================================================
   TAB 2: ORDER ACTIVITY LOGS (orders table)
   Filters: status, date, search (name or email)
   ============================================================ */
$ord_status = trim($_GET['status'] ?? '');
$ord_date   = trim($_GET['date']   ?? '');
$ord_search = trim($_GET['search'] ?? '');

$ord_where  = ['1=1'];
$ord_params = [];
$ord_types  = '';

$allowed_statuses = ['pending','preparing','ready','out_for_delivery','delivered','cancelled'];
if ($ord_status !== '' && in_array($ord_status, $allowed_statuses, true)) {
    $ord_where[]  = 'o.status = ?';
    $ord_params[] = $ord_status;
    $ord_types   .= 's';
}
if ($ord_date !== '') {
    $ord_where[]  = 'DATE(o.created_at) = ?';
    $ord_params[] = $ord_date;
    $ord_types   .= 's';
}
if ($ord_search !== '') {
    $ord_where[]  = '(u.full_name LIKE ? OR u.email LIKE ?)';
    $like         = '%' . $ord_search . '%';
    $ord_params[] = $like;
    $ord_params[] = $like;
    $ord_types   .= 'ss';
}

$ord_where_sql = implode(' AND ', $ord_where);

$ord_sql = "SELECT o.order_id, u.full_name, u.email, o.total_amount,
                   o.status, o.payment_method, o.created_at
            FROM orders o
            JOIN users u ON u.user_id = o.user_id
            WHERE {$ord_where_sql}
            ORDER BY o.created_at DESC
            LIMIT 200";

$ord_stmt = $conn->prepare($ord_sql);
if (!empty($ord_params)) {
    $ord_stmt->bind_param($ord_types, ...$ord_params);
}
$ord_stmt->execute();
$order_logs = $ord_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$ord_stmt->close();

// Summary counts for the order cards
$new_users_today = $conn->query("SELECT COUNT(*) FROM users  WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$orders_today    = $conn->query("SELECT COUNT(*) FROM orders WHERE DATE(created_at) = CURDATE()")->fetch_row()[0] ?? 0;
$pending_orders  = $conn->query("SELECT COUNT(*) FROM orders WHERE status = 'pending'")->fetch_row()[0] ?? 0;

/* ============================================================
   DISPLAY HELPERS
   ============================================================ */
function event_badge_class(string $type): string
{
    return match($type) {
        'login_success' => 'badge-success',
        'login_failed'  => 'badge-danger',
        'logout'        => 'badge-info',
        'access_denied' => 'badge-warning',
        default         => 'badge-secondary',
    };
}

function event_icon(string $type): string
{
    return match($type) {
        'login_success' => '✅',
        'login_failed'  => '❌',
        'logout'        => '🚪',
        'access_denied' => '🚫',
        default         => '📋',
    };
}

function event_label(string $type): string
{
    return match($type) {
        'login_success' => 'Login Success',
        'login_failed'  => 'Login Failed',
        'logout'        => 'Logout',
        'access_denied' => 'Access Denied',
        default         => ucwords(str_replace('_', ' ', $type)),
    };
}

function order_status_class(string $s): string
{
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

function order_status_label(string $s): string
{
    return ucwords(str_replace('_', ' ', $s));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>User Logs – Herald Canteen</title>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Tab buttons ─────────────────────────────────────── */
        .tab-bar {
            display: flex;
            gap: 8px;
            margin-bottom: 24px;
        }
        .tab-btn {
            padding: 10px 24px;
            border-radius: 999px;
            font-size: 13px;
            font-weight: 600;
            cursor: pointer;
            border: 1px solid rgba(255,255,255,0.12);
            background: rgba(255,255,255,0.05);
            color: rgba(255,255,255,0.5);
            text-decoration: none;
            transition: all 0.2s;
        }
        .tab-btn.active,
        .tab-btn:hover {
            background: #1f6f43;
            border-color: #1f6f43;
            color: #fff;
        }

        /* ── Summary cards ───────────────────────────────────── */
        .summary-cards {
            display: grid;
            grid-template-columns: repeat(4, 1fr);
            gap: 14px;
            margin-bottom: 24px;
        }
        .summary-cards.three-col {
            grid-template-columns: repeat(3, 1fr);
        }
        .summary-card {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
            padding: 18px 20px;
        }
        .sc-label {
            font-size: 11px;
            color: rgba(255,255,255,0.4);
            text-transform: uppercase;
            letter-spacing: 0.8px;
        }
        .sc-value {
            font-size: 30px;
            font-weight: 800;
            margin-top: 6px;
        }
        .sc-green  .sc-value { color: #4db848; }
        .sc-red    .sc-value { color: #e53935; }
        .sc-blue   .sc-value { color: #42a5f5; }
        .sc-orange .sc-value { color: #ff9800; }
        .sc-purple .sc-value { color: #ab47bc; }

        /* ── Filter bar ──────────────────────────────────────── */
        .filter-bar {
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
            margin-bottom: 18px;
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
            padding: 8px 14px;
            border-radius: 999px;
            font-size: 13px;
            outline: none;
        }
        .filter-bar input:focus,
        .filter-bar select:focus { border-color: #4db848; }
        .filter-label {
            font-size: 12px;
            color: rgba(255,255,255,0.4);
        }
        .btn-filter {
            background: #1f6f43;
            color: #fff;
            border: none;
            padding: 8px 18px;
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
            padding: 8px 16px;
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

        /* ── Table ───────────────────────────────────────────── */
        .logs-table-wrap {
            background: #1a1a1a;
            border: 1px solid rgba(255,255,255,0.08);
            border-radius: 14px;
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
            padding: 13px 16px;
            text-align: left;
            border-bottom: 1px solid rgba(255,255,255,0.07);
            font-weight: 600;
        }
        .logs-table td {
            padding: 13px 16px;
            border-bottom: 1px solid rgba(255,255,255,0.05);
            font-size: 13px;
            color: rgba(255,255,255,0.8);
            vertical-align: middle;
        }
        .logs-table tr:last-child td { border-bottom: none; }
        .logs-table tr:hover td { background: rgba(77,184,72,0.03); }

        .user-cell .uname  { font-weight: 600; color: #fff; }
        .user-cell .uemail { font-size: 11px; color: rgba(255,255,255,0.3); margin-top: 2px; }
        .anon-cell         { font-size: 12px; color: rgba(255,255,255,0.25); font-style: italic; }
        .desc-cell         { font-size: 12px; color: rgba(255,255,255,0.45); max-width: 260px; word-break: break-word; }
        .ip-cell           { font-size: 12px; color: rgba(255,255,255,0.3); font-family: monospace; }
        .time-cell         { font-size: 12px; color: rgba(255,255,255,0.35); }
        .amt-cell          { font-weight: 700; color: #4db848; }

        /* ── Badges ──────────────────────────────────────────── */
        .badge {
            display: inline-flex;
            align-items: center;
            gap: 4px;
            padding: 4px 11px;
            border-radius: 999px;
            font-size: 11px;
            font-weight: 700;
            text-transform: capitalize;
            white-space: nowrap;
        }
        .badge-success   { background: rgba(77,184,72,0.15);    color: #4db848; }
        .badge-danger    { background: rgba(229,57,53,0.15);    color: #e53935; }
        .badge-info      { background: rgba(66,165,245,0.15);   color: #42a5f5; }
        .badge-warning   { background: rgba(255,152,0,0.15);    color: #ff9800; }
        .badge-primary   { background: rgba(103,58,183,0.15);   color: #9c27b0; }
        .badge-delivered { background: rgba(38,166,154,0.15);   color: #26a69a; }
        .badge-secondary { background: rgba(255,255,255,0.07);  color: rgba(255,255,255,0.5); }

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

        .no-data {
            text-align: center;
            padding: 48px;
            color: rgba(255,255,255,0.25);
            font-size: 15px;
        }
        .export-btn {
            background: rgba(255,255,255,0.06);
            border: 1px solid rgba(255,255,255,0.12);
            color: rgba(255,255,255,0.6);
            padding: 8px 16px;
            border-radius: 999px;
            font-size: 13px;
            cursor: pointer;
            transition: all 0.2s;
        }
        .export-btn:hover { background: rgba(255,255,255,0.1); color: #fff; }

        @media (max-width: 1000px) {
            .summary-cards,
            .summary-cards.three-col { grid-template-columns: 1fr 1fr; }
        }
        @media (max-width: 640px) {
            .summary-cards,
            .summary-cards.three-col { grid-template-columns: 1fr; }
        }
    </style>
</head>
<body class="<?= $current_role === 'chef' ? 'chef-page' : 'staff-page' ?>">
<div class="layout">

    <!-- ── Sidebar — matches chef-control.php / staff-control.php exactly ── -->
    <div class="sidebar">
        <div class="navbar-title">
            Herald Canteen
            <span><?= strtoupper($current_role) ?> PORTAL</span>
        </div>
        <nav>
            <?php if ($current_role === 'chef'): ?>
                <a href="chef-control.php">👨‍🍳 Chef Home</a>
            <?php else: ?>
                <a href="staff-control.php">🧾 Staff Home</a>
            <?php endif; ?>
            <a href="user-logs.php" class="active">📋 User Logs</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </div>

    <!-- ── Main content ───────────────────────────────────────────────────── -->
    <div class="main">

        <!-- Topbar -->
        <div class="topbar">
            <div class="topbar-welcome">
                Welcome, <?= $viewer_name ?>
            </div>
            <div style="display:flex;align-items:center;gap:12px;">
                <label class="theme-toggle" title="Toggle light/dark mode">
                    <input type="checkbox" class="theme-checkbox">
                    <span class="theme-slider"></span>
                </label>
                <button class="export-btn" onclick="window.print()">⬇ Export</button>
            </div>
        </div>

        <div class="content">

            <div class="section-title">
                <h2>📋 User Logs</h2>
                <p>Monitor login events, session activity, and order history across the system.</p>
            </div>

            <!-- Tab navigation -->
            <div class="tab-bar">
                <a href="user-logs.php?tab=events"
                   class="tab-btn <?= $active_tab === 'events' ? 'active' : '' ?>">
                    🔐 Login &amp; Session Events
                </a>
                <a href="user-logs.php?tab=orders"
                   class="tab-btn <?= $active_tab === 'orders' ? 'active' : '' ?>">
                    📦 Order Activity
                </a>
            </div>

            <?php if ($active_tab === 'events'): ?>
            <!-- ══════════════════════════════════════════════════════
                 TAB 1 — LOGIN & SESSION EVENTS
                 ══════════════════════════════════════════════════ -->

            <!-- Summary cards -->
            <div class="summary-cards">
                <div class="summary-card sc-green">
                    <div class="sc-label">Logins Today</div>
                    <div class="sc-value"><?= $logins_today ?></div>
                </div>
                <div class="summary-card sc-red">
                    <div class="sc-label">Failed Attempts Today</div>
                    <div class="sc-value"><?= $failures_today ?></div>
                </div>
                <div class="summary-card sc-blue">
                    <div class="sc-label">Logouts Today</div>
                    <div class="sc-value"><?= $logouts_today ?></div>
                </div>
                <div class="summary-card sc-orange">
                    <div class="sc-label">Access Denied Today</div>
                    <div class="sc-value"><?= $denied_today ?></div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="events">

                <span class="filter-label">Event</span>
                <select name="event_type" onchange="this.form.submit()">
                    <option value="">All Events</option>
                    <?php foreach ($allowed_events as $et): ?>
                    <option value="<?= $et ?>" <?= $ev_type === $et ? 'selected' : '' ?>>
                        <?= event_label($et) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <span class="filter-label">Date</span>
                <input type="date" name="ev_date"
                       value="<?= htmlspecialchars($ev_date, ENT_QUOTES, 'UTF-8') ?>"
                       onchange="this.form.submit()">

                <input type="text" name="ev_search"
                       placeholder="Search name, email, description…"
                       value="<?= htmlspecialchars($ev_search, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-filter">Search</button>

                <?php if ($ev_type || $ev_date || $ev_search): ?>
                    <a href="user-logs.php?tab=events" class="btn-reset">Clear</a>
                <?php endif; ?>

                <span class="count-tag">
                    Showing <strong><?= count($event_logs) ?></strong> records
                </span>
            </form>

            <!-- Event log table -->
            <div class="logs-table-wrap">
                <table class="logs-table">
                    <thead>
                        <tr>
                            <th>#</th>
                            <th>Event</th>
                            <th>User</th>
                            <th>IP Address</th>
                            <th>Description</th>
                            <th>Time</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php if (count($event_logs) === 0): ?>
                        <tr>
                            <td colspan="6" class="no-data">No events found for your filters.</td>
                        </tr>
                    <?php else: foreach ($event_logs as $ev): ?>
                        <tr>
                            <td><?= (int)$ev['log_id'] ?></td>
                            <td>
                                <span class="badge <?= event_badge_class($ev['event_type']) ?>">
                                    <?= event_icon($ev['event_type']) ?>
                                    <?= event_label($ev['event_type']) ?>
                                </span>
                            </td>
                            <td>
                                <?php if ($ev['full_name']): ?>
                                <div class="user-cell">
                                    <div class="uname"><?= htmlspecialchars($ev['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                    <div class="uemail"><?= htmlspecialchars($ev['email'] ?? '', ENT_QUOTES, 'UTF-8') ?></div>
                                </div>
                                <?php else: ?>
                                <span class="anon-cell">Unknown / Anonymous</span>
                                <?php endif; ?>
                            </td>
                            <td class="ip-cell"><?= htmlspecialchars($ev['ip_address'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="desc-cell"><?= htmlspecialchars($ev['description'], ENT_QUOTES, 'UTF-8') ?></td>
                            <td class="time-cell"><?= htmlspecialchars($ev['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php else: ?>
            <!-- ══════════════════════════════════════════════════════
                 TAB 2 — ORDER ACTIVITY
                 ══════════════════════════════════════════════════ -->

            <!-- Summary cards -->
            <div class="summary-cards three-col">
                <div class="summary-card sc-green">
                    <div class="sc-label">New Users Today</div>
                    <div class="sc-value"><?= $new_users_today ?></div>
                </div>
                <div class="summary-card sc-orange">
                    <div class="sc-label">Orders Today</div>
                    <div class="sc-value"><?= $orders_today ?></div>
                </div>
                <div class="summary-card sc-blue">
                    <div class="sc-label">Pending Orders</div>
                    <div class="sc-value"><?= $pending_orders ?></div>
                </div>
            </div>

            <!-- Filters -->
            <form method="GET" class="filter-bar">
                <input type="hidden" name="tab" value="orders">

                <span class="filter-label">Status</span>
                <select name="status" onchange="this.form.submit()">
                    <option value="">All</option>
                    <?php foreach ($allowed_statuses as $s): ?>
                    <option value="<?= $s ?>" <?= $ord_status === $s ? 'selected' : '' ?>>
                        <?= order_status_label($s) ?>
                    </option>
                    <?php endforeach; ?>
                </select>

                <span class="filter-label">Date</span>
                <input type="date" name="date"
                       value="<?= htmlspecialchars($ord_date, ENT_QUOTES, 'UTF-8') ?>"
                       onchange="this.form.submit()">

                <input type="text" name="search"
                       placeholder="Search user…"
                       value="<?= htmlspecialchars($ord_search, ENT_QUOTES, 'UTF-8') ?>">
                <button type="submit" class="btn-filter">Search</button>

                <?php if ($ord_status || $ord_date || $ord_search): ?>
                    <a href="user-logs.php?tab=orders" class="btn-reset">Clear</a>
                <?php endif; ?>

                <span class="count-tag">
                    Showing <strong><?= count($order_logs) ?></strong> records
                </span>
            </form>

            <!-- Order activity table -->
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
                    <?php if (count($order_logs) === 0): ?>
                        <tr>
                            <td colspan="6" class="no-data">No orders found for your filters.</td>
                        </tr>
                    <?php else: foreach ($order_logs as $log): ?>
                        <tr>
                            <td>#<?= (int)$log['order_id'] ?></td>
                            <td class="user-cell">
                                <div class="uname"><?= htmlspecialchars($log['full_name'], ENT_QUOTES, 'UTF-8') ?></div>
                                <div class="uemail"><?= htmlspecialchars($log['email'], ENT_QUOTES, 'UTF-8') ?></div>
                            </td>
                            <td class="amt-cell">Rs <?= number_format((float)$log['total_amount'], 0) ?></td>
                            <td><span class="payment-pill"><?= htmlspecialchars($log['payment_method'], ENT_QUOTES, 'UTF-8') ?></span></td>
                            <td>
                                <span class="badge <?= order_status_class($log['status']) ?>">
                                    <?= order_status_label($log['status']) ?>
                                </span>
                            </td>
                            <td class="time-cell"><?= htmlspecialchars($log['created_at'], ENT_QUOTES, 'UTF-8') ?></td>
                        </tr>
                    <?php endforeach; endif; ?>
                    </tbody>
                </table>
            </div>

            <?php endif; ?>

        </div><!-- /.content -->
    </div><!-- /.main -->
</div><!-- /.layout -->
</body>
</html>
