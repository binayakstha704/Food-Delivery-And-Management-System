<?php
// ============================================================
// debug_login.php — Herald Canteen Login Debugger
// DROP THIS FILE in: herald-canteen-server/pages/debug_login.php
// Then visit: http://localhost/herald-canteen-server/pages/debug_login.php
// DELETE THIS FILE after fixing the issue!
// ============================================================

$host    = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'herald_canteen';

echo "<style>body{font-family:monospace;padding:20px;background:#111;color:#eee;}
.ok{color:#4ade80;} .fail{color:#f87171;} .warn{color:#facc15;}
h2{color:#38bdf8;} pre{background:#1e293b;padding:12px;border-radius:6px;}
</style>";

echo "<h2>🔍 Herald Canteen — Login Debug</h2>";

// ── 1. DB Connection ────────────────────────────────────────
echo "<h3>Step 1: Database Connection</h3>";
mysqli_report(MYSQLI_REPORT_OFF); // manual error handling here
$conn = new mysqli($host, $db_user, $db_pass, $db_name);
if ($conn->connect_error) {
    echo "<p class='fail'>❌ Connection FAILED: " . htmlspecialchars($conn->connect_error) . "</p>";
    echo "<p class='warn'>👉 Fix: Make sure MySQL is running and database '<b>$db_name</b>' exists in phpMyAdmin.</p>";
    exit;
} else {
    echo "<p class='ok'>✅ Connected to MySQL as '$db_user' — database '$db_name' found.</p>";
}

// ── 2. Does users table exist? ──────────────────────────────
echo "<h3>Step 2: Users Table</h3>";
$r = $conn->query("SHOW TABLES LIKE 'users'");
if ($r && $r->num_rows > 0) {
    echo "<p class='ok'>✅ 'users' table exists.</p>";
} else {
    echo "<p class='fail'>❌ 'users' table NOT FOUND! You need to import herald_canteen.sql first.</p>";
    exit;
}

// ── 3. Show columns ─────────────────────────────────────────
echo "<h3>Step 3: Users Table Columns</h3><pre>";
$cols = $conn->query("SHOW COLUMNS FROM users");
$col_names = [];
while ($col = $cols->fetch_assoc()) {
    $col_names[] = $col['Field'];
    echo $col['Field'] . " — " . $col['Type'] . "\n";
}
echo "</pre>";

$missing = [];
foreach (['email_verified_at', 'mfa_enabled'] as $required) {
    if (!in_array($required, $col_names)) {
        $missing[] = $required;
    }
}
if ($missing) {
    echo "<p class='fail'>❌ Missing columns: <b>" . implode(', ', $missing) . "</b></p>";
    echo "<p class='warn'>👉 Fix: Import <b>otp_migration.sql</b> in phpMyAdmin (select herald_canteen DB → Import → choose file).</p>";
} else {
    echo "<p class='ok'>✅ All required columns present (email_verified_at, mfa_enabled).</p>";
}

// ── 4. How many users? ──────────────────────────────────────
echo "<h3>Step 4: Users in Database</h3>";
$r = $conn->query("SELECT COUNT(*) as total FROM users");
$row = $r->fetch_assoc();
echo "<p>Total users: <b>{$row['total']}</b></p>";

if ((int)$row['total'] === 0) {
    echo "<p class='fail'>❌ No users found! The INSERT data from herald_canteen.sql was not imported.</p>";
    exit;
}

// ── 5. Show all users (safe — no passwords) ─────────────────
echo "<h3>Step 5: All Users (no passwords shown)</h3><pre>";
$r = $conn->query("SELECT user_id, full_name, email, role, is_active FROM users");
while ($u = $r->fetch_assoc()) {
    $active = $u['is_active'] ? '✅ active' : '❌ inactive';
    echo "ID {$u['user_id']} | {$u['email']} | role: {$u['role']} | $active\n";
}
echo "</pre>";

// ── 6. Test email_verified_at values ────────────────────────
if (!$missing) {
    echo "<h3>Step 6: Email Verification Status</h3><pre>";
    $r = $conn->query("SELECT email, email_verified_at FROM users");
    while ($u = $r->fetch_assoc()) {
        $v = $u['email_verified_at'] ? "✅ verified at " . $u['email_verified_at'] : "❌ NOT verified (login will be blocked!)";
        echo $u['email'] . " → $v\n";
    }
    echo "</pre>";

    // Auto-fix: set email_verified_at for unverified users
    echo "<h3>Step 7: Auto-Fix email_verified_at</h3>";
    $fix = $conn->query("UPDATE users SET email_verified_at = created_at WHERE email_verified_at IS NULL");
    $affected = $conn->affected_rows;
    if ($affected > 0) {
        echo "<p class='ok'>✅ Fixed $affected user(s) — set email_verified_at = created_at.</p>";
    } else {
        echo "<p class='ok'>✅ No fix needed — all users already have email_verified_at set.</p>";
    }
}

// ── 7. Test the exact query login uses ──────────────────────
echo "<h3>Step 8: Simulate Login Query for customer@heraldcanteen.com</h3>";
$test_email = 'customer@heraldcanteen.com';
$sql = "SELECT user_id, full_name, email, password, role, is_active,
               COALESCE(mfa_enabled, 0) AS mfa_enabled,
               email_verified_at
        FROM users WHERE email = ? LIMIT 1";
$stmt = $conn->prepare($sql);
if (!$stmt) {
    echo "<p class='fail'>❌ prepare() FAILED: " . htmlspecialchars($conn->error) . "</p>";
    echo "<p class='warn'>👉 This is why login silently says 'no account' — the query fails because a column is missing. Import all migration SQLs!</p>";
} else {
    $stmt->bind_param("s", $test_email);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();
    if ($user) {
        echo "<p class='ok'>✅ User found! Login query works.</p><pre>";
        unset($user['password']); // don't show hash
        print_r($user);
        echo "</pre>";
        echo "<p class='ok'>✅ <b>Password to use: <code>password</code></b></p>";
    } else {
        echo "<p class='fail'>❌ Query ran but returned no user for '$test_email'.</p>";
        echo "<p class='warn'>👉 The email doesn't exist in the DB. Check Step 4/5 above.</p>";
    }
}

echo "<hr><p class='warn'>⚠️ DELETE this file after you're done debugging!</p>";