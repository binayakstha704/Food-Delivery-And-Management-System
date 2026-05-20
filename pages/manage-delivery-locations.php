<?php
// manage-delivery-locations.php — Chef & Staff manage delivery locations
require_once '../includes/auth.php';
start_session();
session_security_check();
require_once '../config/db.php';

require_any_role(['chef', 'staff']);

$role      = $_SESSION['role'];
$user_name = $_SESSION['full_name'] ?? ucfirst($role);

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
$csrf = $_SESSION['csrf_token'];

function h(string $v): string {
    return htmlspecialchars($v, ENT_QUOTES, 'UTF-8');
}

// Canonical block list — the only pre-defined blocks shown in the dropdown
$known_blocks = ['HCK Block', 'ING Block', 'Library Block', 'WLV Block'];

$errors = [];

// ── Resolve block name from POST (handles "Other…" custom text input) ─────────
function resolve_block_name(array $known_blocks, array &$errors): string
{
    // When JS swaps names, the text input becomes "block_name"; the select becomes "_block_name_sel".
    $selected = trim($_POST['block_name'] ?? '');

    // Fallback for no-JS path where sentinel may still be present
    if ($selected === '__other__') {
        $selected = trim($_POST['block_name_other'] ?? '');
    }

    if ($selected === '') {
        $errors[] = 'Block name is required.';
        return '';
    }
    if (mb_strlen($selected) > 100) {
        $errors[] = 'Block name too long (max 100 chars).';
        return '';
    }

    // If it came from the dropdown and exactly matches a known block, allow it
    if (in_array($selected, $known_blocks, true)) {
        return $selected;
    }

    $needle = mb_strtolower($selected);

    foreach ($known_blocks as $kb) {
        $hay = mb_strtolower($kb);

        // 1. Exact match (case-insensitive)
        if ($needle === $hay) {
            $errors[] = 'The block "' . $kb . '" already exists. Please select it from the dropdown.';
            return '';
        }

        // 2. User input is contained within the known block name
        //    e.g. "library" inside "Library Resource", "wlv" inside "WLV Block",
        //         "hck" inside "HCK Block", "ing" inside "ING Block"
        if (mb_strpos($hay, $needle) !== false) {
            $errors[] = 'The block "' . $kb . '" already exists. Please select it from the dropdown.';
            return '';
        }

        // 3. Known block name is contained within the user input
        //    e.g. "library resource centre" typed when "Library Resource" exists
        if (mb_strpos($needle, $hay) !== false) {
            $errors[] = 'The block "' . $kb . '" already exists. Please select it from the dropdown.';
            return '';
        }

        // 4. Levenshtein distance — catch close typos like "libary", "wlb", "hcm"
        //    Compare against each word of the known block name too
        $kb_words = explode(' ', $hay);
        $close = false;

        // Check against full block name
        $full_dist = levenshtein($needle, $hay);
        $threshold = max(1, (int) floor(mb_strlen($hay) * 0.35)); // 35% of length
        if ($full_dist <= $threshold) {
            $close = true;
        }

        // Check against each significant word in the known block name (skip "Block")
        if (!$close) {
            foreach ($kb_words as $word) {
                if (mb_strlen($word) < 3 || $word === 'block' || $word === 'resource') continue;
                $word_dist = levenshtein($needle, $word);
                $word_threshold = max(1, (int) floor(mb_strlen($word) * 0.35));
                if ($word_dist <= $word_threshold) {
                    $close = true;
                    break;
                }
            }
        }

        if ($close) {
            $errors[] = 'The block "' . $kb . '" already exists. Please select it from the dropdown.';
            return '';
        }
    }

    return $selected; // Genuinely new block — allow
}

$is_ajax = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) && strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    if (!hash_equals($csrf, $_POST['csrf_token'] ?? '')) {
        $errors[] = 'Invalid CSRF token. Please try again.';
        if ($is_ajax) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => implode(' ', $errors)]);
            exit;
        }
    } else {
        $action = trim($_POST['action'] ?? '');

        if ($action === 'add_location') {
            $loc_name   = trim(strip_tags($_POST['location_name'] ?? ''));
            $blk_name   = resolve_block_name($known_blocks, $errors);
            $sort_order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
            $is_active  = isset($_POST['is_active']) ? 1 : 0;

            if ($loc_name === '')                           $errors[] = 'Location name is required.';
            elseif (mb_strlen($loc_name) > 150)            $errors[] = 'Location name too long (max 150 chars).';
            if ($sort_order === false || $sort_order < 0)  $errors[] = 'Sort order must be a non-negative integer.';

            if (empty($errors)) {
                $dup = $conn->prepare('SELECT location_id FROM delivery_locations WHERE location_name=? AND block_name=? LIMIT 1');
                $dup->bind_param('ss', $loc_name, $blk_name);
                $dup->execute();
                $dup->store_result();
                if ($dup->num_rows > 0) {
                    $errors[] = 'A location with that name already exists in that block.';
                } else {
                    $ins = $conn->prepare('INSERT INTO delivery_locations (location_name, block_name, sort_order, is_active) VALUES (?,?,?,?)');
                    $ins->bind_param('ssii', $loc_name, $blk_name, $sort_order, $is_active);
                    $ins->execute();
                    $ins->close();
                    $_SESSION['_toast'] = ['text' => 'Location added successfully.', 'type' => 'success'];
                    session_write_close();
                    header('Location: manage-delivery-locations.php');
                    exit;
                }
                $dup->close();
            }
        }

        elseif ($action === 'edit_location') {
            $loc_id     = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT);
            $loc_name   = trim(strip_tags($_POST['location_name'] ?? ''));
            $blk_name   = resolve_block_name($known_blocks, $errors);
            $sort_order = filter_var($_POST['sort_order'] ?? 0, FILTER_VALIDATE_INT);
            $is_active  = isset($_POST['is_active']) ? 1 : 0;

            if (!$loc_id || $loc_id <= 0)                  $errors[] = 'Invalid location ID.';
            if ($loc_name === '')                           $errors[] = 'Location name is required.';
            elseif (mb_strlen($loc_name) > 150)            $errors[] = 'Location name too long (max 150 chars).';
            if ($sort_order === false || $sort_order < 0)  $errors[] = 'Sort order must be a non-negative integer.';

            if (empty($errors)) {
                $upd = $conn->prepare('UPDATE delivery_locations SET location_name=?, block_name=?, sort_order=?, is_active=? WHERE location_id=?');
                $upd->bind_param('ssiii', $loc_name, $blk_name, $sort_order, $is_active, $loc_id);
                $upd->execute();
                $upd->close();
                $_SESSION['_toast'] = ['text' => 'Location updated successfully.', 'type' => 'success'];
                session_write_close();
                header('Location: manage-delivery-locations.php');
                exit;
            }
        }

        elseif ($action === 'toggle_active') {
            $loc_id = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($loc_id && $loc_id > 0) {
                $conn->query("UPDATE delivery_locations SET is_active = 1 - is_active WHERE location_id = " . (int)$loc_id);
                // Fetch new status
                $rs = $conn->query("SELECT is_active FROM delivery_locations WHERE location_id = " . (int)$loc_id);
                $new_status = $rs ? (int)$rs->fetch_assoc()['is_active'] : -1;
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => true, 'is_active' => $new_status, 'message' => 'Location status updated.']);
                    exit;
                }
                $_SESSION['_toast'] = ['text' => 'Location status updated.', 'type' => 'success'];
            } else {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Invalid location ID.']);
                    exit;
                }
            }
            session_write_close();
            header('Location: manage-delivery-locations.php');
            exit;
        }

        elseif ($action === 'delete_location') {
            $loc_id = filter_var($_POST['location_id'] ?? 0, FILTER_VALIDATE_INT);
            if ($loc_id && $loc_id > 0) {
                $del = $conn->prepare('DELETE FROM delivery_locations WHERE location_id = ?');
                $del->bind_param('i', $loc_id);
                $del->execute();
                $affected = $del->affected_rows;
                $del->close();
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    if ($affected > 0) {
                        echo json_encode(['ok' => true, 'message' => 'Location deleted successfully.']);
                    } else {
                        echo json_encode(['ok' => false, 'error' => 'Location not found or already deleted.']);
                    }
                    exit;
                }
                $_SESSION['_toast'] = $affected > 0
                    ? ['text' => 'Location deleted successfully.', 'type' => 'success']
                    : ['text' => 'Location not found or already deleted.', 'type' => 'danger'];
            } else {
                if ($is_ajax) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Invalid location ID.']);
                    exit;
                }
                $_SESSION['_toast'] = ['text' => 'Invalid location ID.', 'type' => 'danger'];
            }
            session_write_close();
            header('Location: manage-delivery-locations.php');
            exit;
        }
    }
}

$toast = null;
if (isset($_SESSION['_toast'])) {
    $toast = $_SESSION['_toast'];
    unset($_SESSION['_toast']);
}

$edit_loc = null;
$edit_id  = filter_var($_GET['edit_id'] ?? 0, FILTER_VALIDATE_INT);
if ($edit_id && $edit_id > 0) {
    $es = $conn->prepare('SELECT * FROM delivery_locations WHERE location_id=? LIMIT 1');
    $es->bind_param('i', $edit_id);
    $es->execute();
    $edit_loc = $es->get_result()->fetch_assoc();
    $es->close();
}

$all_locs_res = $conn->query('SELECT * FROM delivery_locations ORDER BY block_name, sort_order, location_name');
$blocks = [];
while ($row = $all_locs_res->fetch_assoc()) {
    $blocks[$row['block_name']][] = $row;
}

$cur_block = $edit_loc['block_name'] ?? ($_POST['block_name'] ?? $known_blocks[0]);
if ($cur_block === '__other__') $cur_block = trim($_POST['block_name_other'] ?? '');
$is_other  = !in_array($cur_block, $known_blocks, true) && $cur_block !== '';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Delivery Locations — Herald Canteen</title>
    <script src="../assets/js/modal.js"></script>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        .btn-loc-sm { border:none; padding:5px 12px; border-radius:6px; font-size:12px; font-weight:600; cursor:pointer; text-decoration:none; display:inline-flex; align-items:center; gap:4px; }
        .btn-loc-toggle-off { background:rgba(255,100,100,0.12); color:rgba(255,120,120,0.9); border:1px solid rgba(255,100,100,0.2); }
        .btn-loc-toggle-on  { background:rgba(77,184,72,0.12);   color:#4db848;               border:1px solid rgba(77,184,72,0.25); }
        .block-section      { margin-bottom:26px; }
        .block-heading      { font-size:13px; font-weight:700; color:#4db848; letter-spacing:.5px; text-transform:uppercase; margin-bottom:10px; padding-bottom:6px; border-bottom:1px solid rgba(77,184,72,0.2); }
        .location-status-badge { display:inline-block; padding:2px 10px; border-radius:20px; font-size:11px; font-weight:700; letter-spacing:.4px; text-transform:uppercase; }
        .location-status-badge.active   { background:rgba(77,184,72,0.15);  color:#4db848;               border:1px solid rgba(77,184,72,0.3); }
        .location-status-badge.inactive { background:rgba(255,255,255,0.07); color:rgba(255,255,255,0.4); border:1px solid rgba(255,255,255,0.1); }

        /* Checkbox row — matches other form field heights */
        .chef-checkbox-row {
            display: flex;
            align-items: center;
            gap: 10px;
            height: 42px; /* matches text/number inputs */
            padding: 0 12px;
            border: 1px solid rgba(255,255,255,0.12);
            border-radius: 8px;
            background: rgba(255,255,255,0.04);
            cursor: pointer;
        }
        .chef-checkbox-row input[type="checkbox"] {
            width: 17px;
            height: 17px;
            accent-color: #4db848;
            cursor: pointer;
            flex-shrink: 0;
            margin: 0;
        }
        .chef-checkbox-label {
            font-size: 14px;
            font-weight: 500;
            color: rgba(255,255,255,0.8);
            cursor: pointer;
            margin: 0;
            user-select: none;
        }
        html[data-theme="light"] .chef-checkbox-row {
            border-color: rgba(0,0,0,0.15);
            background: rgba(0,0,0,0.02);
        }
        html[data-theme="light"] .chef-checkbox-label { color: #333; }
    </style>
</head>
<body class="chef-page ops-page">
<div class="layout">

    <div class="sidebar">
        <div class="navbar-title">
            Herald Canteen
            <span><?php echo $role === 'chef' ? 'Chef Portal' : 'Staff Portal'; ?></span>
        </div>
        <nav>
            <?php if ($role === 'chef'): ?>
                <a href="chef-control.php">👨‍🍳 Chef Dashboard</a>
                <a href="chef-categories.php">🖼️ Categories</a>
                <a href="chef-menu.php">🍽️ Manage Menu</a>
                <a href="manage-delivery-locations.php" class="active">📍 Delivery Locations</a>
                <a href="logout.php">🚪 Logout</a>
            <?php else: ?>
                <a href="staff-control.php">🧾 Staff Home</a>
                <a href="staff-control.php#orders-section">📦 Active Orders</a>
                <a href="staff-order-history.php">🕘 Paid History</a>
                <a href="user-logs.php">📋 User Logs</a>
                <a href="logout.php">🚪 Logout</a>
            <?php endif; ?>
        </nav>
    </div>

    <div class="main">
        <div class="topbar">
            <div class="topbar-welcome">Welcome, <?php echo h($user_name); ?></div>
            <label class="theme-toggle" title="Toggle light/dark mode">
                <input type="checkbox" class="theme-checkbox">
                <span class="theme-slider"></span>
            </label>
        </div>

        <div class="content">

            <section class="ops-hero ops-hero-chef">
                <div>
                    <p class="ops-eyebrow">Location Management</p>
                    <h1>Delivery Locations</h1>
                    <p>Manage where orders can be delivered on campus.</p>
                </div>
                <div class="ops-hero-side">
                    <span class="ops-role-pill"><?php echo $role === 'chef' ? '👨‍🍳' : '🧾'; ?> <?php echo h($user_name); ?></span>
                    <a href="<?php echo $role === 'chef' ? 'chef-control.php' : 'staff-control.php'; ?>" class="ops-link-btn">Back to Dashboard</a>
                </div>
            </section>

            <?php if ($toast): ?>
                <div class="<?php echo ($toast['type'] ?? '') === 'danger' ? 'alert-error' : 'alert-success'; ?>">
                    <?php echo h($toast['text'] ?? ''); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($errors)): ?>
                <div class="alert-error">
                    <strong>Please fix the following:</strong>
                    <ul style="margin:6px 0 0 18px;">
                        <?php foreach ($errors as $e): ?><li><?php echo h($e); ?></li><?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <!-- ADD / EDIT FORM -->
            <section class="ops-panel">
                <div class="ops-panel-heading">
                    <div>
                        <p class="ops-eyebrow"><?php echo $edit_loc ? 'Update Location' : 'New Location'; ?></p>
                        <h2><?php echo $edit_loc ? 'Edit Delivery Location' : 'Add Delivery Location'; ?></h2>
                        <p>Locations appear in the checkout dropdown when customers choose delivery.</p>
                    </div>
                </div>

                <form method="POST" class="chef-form-grid"
                      action="manage-delivery-locations.php<?php echo $edit_loc ? '?edit_id=' . (int)$edit_loc['location_id'] : ''; ?>">
                    <input type="hidden" name="csrf_token" value="<?php echo h($csrf); ?>">
                    <input type="hidden" name="action"     value="<?php echo $edit_loc ? 'edit_location' : 'add_location'; ?>">
                    <?php if ($edit_loc): ?>
                        <input type="hidden" name="location_id" value="<?php echo (int)$edit_loc['location_id']; ?>">
                    <?php endif; ?>

                    <div>
                        <label>Location Name *</label>
                        <input type="text" name="location_name" maxlength="150" required
                               placeholder="e.g. Library Entrance"
                               value="<?php echo h($edit_loc['location_name'] ?? ($_POST['location_name'] ?? '')); ?>">
                    </div>

                    <div>
                        <label>Block Name *</label>
                        <select name="block_name" id="block_select" onchange="toggleOtherBlock(this)">
                            <?php foreach ($known_blocks as $kb): ?>
                                <option value="<?php echo h($kb); ?>" <?php echo (!$is_other && $cur_block === $kb) ? 'selected' : ''; ?>>
                                    <?php echo h($kb); ?>
                                </option>
                            <?php endforeach; ?>
                            <option value="__other__" <?php echo $is_other ? 'selected' : ''; ?>>Other…</option>
                        </select>
                        <input type="text" name="block_name_other" id="block_other_input"
                               maxlength="100" placeholder="Enter a new block name (must not match existing blocks)"
                               value="<?php echo $is_other ? h($cur_block) : ''; ?>"
                               style="margin-top:8px;display:<?php echo $is_other ? 'block' : 'none'; ?>;">
                        <small id="block_other_hint" style="display:<?php echo $is_other ? 'block' : 'none'; ?>;color:rgba(255,255,255,0.4);font-size:11px;margin-top:4px;">
                            Only use this for a block that isn't listed above. Entering an existing block name will result in an error.
                        </small>
                    </div>

                    <div>
                        <label>Sort Order</label>
                        <input type="number" name="sort_order" min="0"
                               value="<?php echo (int)($edit_loc['sort_order'] ?? ($_POST['sort_order'] ?? 0)); ?>">
                    </div>

                    <div>
                        <label>Active (visible to customers)</label>
                        <div class="chef-checkbox-row">
                            <input type="checkbox" name="is_active" value="1" id="is_active_chk"
                                <?php echo ((int)($edit_loc['is_active'] ?? ($_POST['is_active'] ?? 1))) ? 'checked' : ''; ?>>
                            <label for="is_active_chk" class="chef-checkbox-label">Visible to customers on checkout</label>
                        </div>
                    </div>

                    <div class="full">
                        <button type="submit" class="ops-btn ops-btn-primary">
                            <?php echo $edit_loc ? '💾 Save Changes' : '➕ Add Location'; ?>
                        </button>
                        <?php if ($edit_loc): ?>
                            <a href="manage-delivery-locations.php" class="ops-btn ops-btn-ghost">Cancel</a>
                        <?php endif; ?>
                    </div>
                </form>
            </section>

            <!-- LOCATIONS LIST -->
            <section class="ops-panel">
                <div class="ops-panel-heading">
                    <div>
                        <p class="ops-eyebrow">All Locations</p>
                        <h2>Configured Delivery Locations</h2>
                        <p>Grouped by block. Edit, toggle active status, or delete individual locations.</p>
                    </div>
                </div>

                <?php if (empty($blocks)): ?>
                    <div class="ops-empty-state">
                        <div>📍</div>
                        <h3>No delivery locations yet</h3>
                        <p>Add your first location using the form above.</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($blocks as $block_name => $locs): ?>
                    <div class="block-section">
                        <div class="block-heading">
                            <?php echo h($block_name); ?>
                            <span style="opacity:.5;font-weight:400;">(<?php echo count($locs); ?>)</span>
                        </div>
                        <div class="ops-table-wrap">
                            <table class="chef-table">
                                <tr>
                                    <th>Location Name</th>
                                    <th>Sort</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                                <?php foreach ($locs as $loc): ?>
                                <tr>
                                    <td><?php echo h($loc['location_name']); ?></td>
                                    <td style="color:rgba(255,255,255,0.45);"><?php echo (int)$loc['sort_order']; ?></td>
                                    <td>
                                        <?php if ($loc['is_active']): ?>
                                            <span class="location-status-badge active">Active</span>
                                        <?php else: ?>
                                            <span class="location-status-badge inactive">Inactive</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <div class="action-links">
                                            <a class="ops-btn ops-btn-ghost btn-loc-sm"
                                               href="manage-delivery-locations.php?edit_id=<?php echo (int)$loc['location_id']; ?>">
                                                ✏️ Edit
                                            </a>
                                            <button type="button"
                                                class="ops-btn btn-loc-sm <?php echo $loc['is_active'] ? 'ops-btn-warning btn-loc-toggle-off' : 'ops-btn-ghost btn-loc-toggle-on'; ?> btn-toggle-ajax"
                                                data-loc-id="<?php echo (int)$loc['location_id']; ?>"
                                                data-csrf="<?php echo h($csrf); ?>"
                                                data-is-active="<?php echo (int)$loc['is_active']; ?>">
                                                <?php echo $loc['is_active'] ? '⏸ Deactivate' : '▶ Activate'; ?>
                                            </button>
                                            <button type="button"
                                                class="ops-btn ops-btn-warning btn-loc-sm btn-loc-toggle-off btn-delete-ajax"
                                                data-loc-id="<?php echo (int)$loc['location_id']; ?>"
                                                data-loc-name="<?php echo h($loc['location_name']); ?>"
                                                data-csrf="<?php echo h($csrf); ?>">
                                                🗑️ Delete
                                            </button>
                                        </div>
                                    </td>
                                </tr>
                                <?php endforeach; ?>
                            </table>
                        </div>
                    </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </section>

        </div><!-- /.content -->
    </div><!-- /.main -->
</div><!-- /.layout -->

<script>
function toggleOtherBlock(sel) {
    var inp  = document.getElementById('block_other_input');
    var hint = document.getElementById('block_other_hint');
    if (sel.value === '__other__') {
        inp.style.display  = 'block';
        hint.style.display = 'block';
        inp.required = true;
        inp.name     = 'block_name';
        sel.name     = '_block_name_sel';
        inp.focus();
    } else {
        inp.style.display  = 'none';
        hint.style.display = 'none';
        inp.required = false;
        inp.name     = 'block_name_other';
        sel.name     = 'block_name';
    }
}
(function () {
    var sel = document.getElementById('block_select');
    if (sel) toggleOtherBlock(sel);
})();

// ── AJAX helpers ─────────────────────────────────────────────────
function postAjax(data) {
    var body = new URLSearchParams(data);
    return fetch('manage-delivery-locations.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: body.toString()
    }).then(function(r) { return r.json(); });
}

// ── Toggle Active ────────────────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-toggle-ajax');
    if (!btn) return;
    e.preventDefault();

    var locId    = btn.dataset.locId;
    var csrf     = btn.dataset.csrf;
    var isActive = btn.dataset.isActive === '1';
    var action   = isActive ? 'Deactivate' : 'Activate';

    hcConfirm(action + ' this location?', {
        icon: isActive ? '⏸' : '▶',
        type: isActive ? 'warning' : 'info',
        okText: action
    }).then(function(confirmed) {
        if (!confirmed) return;
        btn.disabled = true;
        postAjax({ action: 'toggle_active', location_id: locId, csrf_token: csrf })
            .then(function(data) {
                if (data.ok) {
                    var nowActive = data.is_active === 1;
                    btn.dataset.isActive = nowActive ? '1' : '0';
                    if (nowActive) {
                        btn.textContent = '⏸ Deactivate';
                        btn.classList.remove('ops-btn-ghost', 'btn-loc-toggle-on');
                        btn.classList.add('ops-btn-warning', 'btn-loc-toggle-off');
                    } else {
                        btn.textContent = '▶ Activate';
                        btn.classList.remove('ops-btn-warning', 'btn-loc-toggle-off');
                        btn.classList.add('ops-btn-ghost', 'btn-loc-toggle-on');
                    }
                    // Update badge in the same row
                    var row = btn.closest('tr');
                    if (row) {
                        var badge = row.querySelector('.location-status-badge');
                        if (badge) {
                            badge.textContent = nowActive ? 'Active' : 'Inactive';
                            badge.className = 'location-status-badge ' + (nowActive ? 'active' : 'inactive');
                        }
                    }
                    hcAlert(data.message || 'Status updated.', { icon: '✅', type: 'success' });
                } else {
                    hcAlert(data.error || 'Could not update status.', { icon: '❌', type: 'danger' });
                }
            })
            .catch(function() { hcAlert('Network error. Please try again.', { icon: '📡', type: 'warning' }); })
            .finally(function() { btn.disabled = false; });
    });
});

// ── Delete ───────────────────────────────────────────────────────
document.addEventListener('click', function(e) {
    var btn = e.target.closest('.btn-delete-ajax');
    if (!btn) return;
    e.preventDefault();

    var locId   = btn.dataset.locId;
    var locName = btn.dataset.locName;
    var csrf    = btn.dataset.csrf;

    hcConfirm("Delete '" + locName + "'? This cannot be undone.", {
        icon: '🗑️',
        type: 'danger',
        okText: 'Delete'
    }).then(function(confirmed) {
        if (!confirmed) return;
        btn.disabled = true;
        postAjax({ action: 'delete_location', location_id: locId, csrf_token: csrf })
            .then(function(data) {
                if (data.ok) {
                    var row = btn.closest('tr');
                    if (row) {
                        row.style.transition = 'opacity 0.3s';
                        row.style.opacity = '0';
                        setTimeout(function() {
                            var blockSection = row.closest('.block-section');
                            row.remove();
                            // If block is now empty, remove the whole block section
                            if (blockSection) {
                                var remaining = blockSection.querySelectorAll('tbody tr, table tr:not(:first-child)');
                                if (remaining.length === 0) blockSection.remove();
                            }
                        }, 300);
                    }
                    hcAlert(data.message || 'Location deleted.', { icon: '🗑️', type: 'success' });
                } else {
                    hcAlert(data.error || 'Could not delete location.', { icon: '❌', type: 'danger' });
                    btn.disabled = false;
                }
            })
            .catch(function() {
                hcAlert('Network error. Please try again.', { icon: '📡', type: 'warning' });
                btn.disabled = false;
            });
    });
});
</script>
</body>
</html>