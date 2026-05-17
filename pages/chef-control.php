<?php
require_once "../includes/auth.php";
start_session();
session_security_check();
require_once "../config/db.php";

require_role('chef');

// Helper function for sanitizing input
function sanitize_input($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

// Helper function for validating and sanitizing text with max length
function validate_text($input, $field_name, $max_length, &$errors, $required = true) {
    $cleaned = trim($input);
    
    if ($required && empty($cleaned)) {
        $errors[$field_name] = ucfirst($field_name) . " is required.";
        return false;
    }
    
    if (!empty($cleaned) && strlen($cleaned) > $max_length) {
        $errors[$field_name] = ucfirst($field_name) . " cannot exceed " . $max_length . " characters.";
        return false;
    }
    
    // Sanitize XSS
    $sanitized = htmlspecialchars($cleaned, ENT_QUOTES, 'UTF-8');
    return $sanitized;
}

// Helper function for validating price
function validate_price($price, &$errors) {
    $cleaned = filter_var(trim($price), FILTER_VALIDATE_FLOAT);
    
    if ($cleaned === false || $cleaned <= 0) {
        $errors['price'] = "Price must be a positive number greater than 0.";
        return false;
    }
    
    if ($cleaned > 999999.99) {
        $errors['price'] = "Price cannot exceed 999,999.99.";
        return false;
    }
    
    return round($cleaned, 2);
}

// Helper function for validating rating
function validate_rating($rating, &$errors) {
    $cleaned = filter_var(trim($rating), FILTER_VALIDATE_FLOAT);
    
    if ($cleaned === false) {
        $errors['rating'] = "Rating must be a valid number.";
        return false;
    }
    
    if ($cleaned < 0 || $cleaned > 5) {
        $errors['rating'] = "Rating must be between 0 and 5.";
        return false;
    }
    
    return round($cleaned, 1);
}

function upload_category_image(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors['image'] = "Image upload failed.";
        return null;
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_extensions, true)) {
        $errors['image'] = "Only JPG, JPEG, PNG, and WEBP images are allowed.";
        return null;
    }

    // Validate file size (max 5MB)
    if ($file['size'] > 5 * 1024 * 1024) {
        $errors['image'] = "Image size cannot exceed 5MB.";
        return null;
    }

    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $errors['image'] = "Uploaded file is not a valid image.";
        return null;
    }

    $upload_dir = dirname(__DIR__) . "/assets/images/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_filename = "category_" . time() . "_" . uniqid() . "." . $extension;
    $target_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        $errors['image'] = "Failed to save uploaded image.";
        return null;
    }

    return "../assets/images/" . $new_filename;
}

function delete_old_category_image(?string $image_path): void
{
    if (!$image_path) {
        return;
    }

    $filename = basename($image_path);
    $full_path = dirname(__DIR__) . "/assets/images/" . $filename;

    if (is_file($full_path)) {
        unlink($full_path);
    }
}

$message = '';
$errors = []; // Associative array for inline errors

// Pick up flash toast from redirect
$chef_toast = null;
if (isset($_SESSION['_toast'])) {
    $chef_toast = $_SESSION['_toast'];
    unset($_SESSION['_toast']);
}
$edit_item = null;
$edit_category = null;
$form_data = []; // Preserve form data on error

/* ---------------------------
   HANDLE ADD CATEGORY
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $form_data['category_name'] = $_POST['category_name'] ?? '';
    $form_data['category_description'] = $_POST['category_description'] ?? '';
    $form_data['category_is_available'] = isset($_POST['category_is_available']);
    
    $category_name = validate_text($_POST['category_name'] ?? '', 'category name', 100, $errors, true);
    
    $category_description = validate_text($_POST['category_description'] ?? '', 'description', 500, $errors, false);
    if ($category_description === false) {
        $category_description = '';
    }
    
    $category_is_available = isset($_POST['category_is_available']) ? 1 : 0;

    $category_image_url = upload_category_image($_FILES['category_image'] ?? [], $errors);

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO categories (name, image_url, description, is_available)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("sssi", $category_name, $category_image_url, $category_description, $category_is_available);

        if ($stmt->execute()) {
            $_SESSION["_toast"] = ["text" => "Category added successfully.", "type" => "success"];
            $form_data = [];
            session_write_close(); // FIX: flush toast to disk before redirect
            header("Location: chef-control.php#categories-section");
            exit;
        } else {
            $errors['general'] = "Failed to add category. Category name may already exist.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   LOAD CATEGORY FOR EDIT
---------------------------- */
if (isset($_GET['edit_category_id'])) {
    $edit_category_id = filter_var($_GET['edit_category_id'], FILTER_VALIDATE_INT);
    
    if ($edit_category_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM categories WHERE category_id = ?");
        $stmt->bind_param("i", $edit_category_id);
        $stmt->execute();
        $edit_category = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/* ---------------------------
   HANDLE UPDATE CATEGORY
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_category'])) {
    $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    
    $category_name = validate_text($_POST['category_name'] ?? '', 'category name', 100, $errors, true);
    
    $category_description = validate_text($_POST['category_description'] ?? '', 'description', 500, $errors, false);
    if ($category_description === false) {
        $category_description = '';
    }
    
    $category_is_available = isset($_POST['category_is_available']) ? 1 : 0;
    $current_image = $_POST['current_image'] ?? '';

    if ($category_id === false || $category_id <= 0) {
        $errors['general'] = "Invalid category.";
    }

    $new_uploaded_image = upload_category_image($_FILES['category_image'] ?? [], $errors);
    $final_image_url = $current_image;

    if ($new_uploaded_image !== null) {
        if ($current_image !== '') {
            delete_old_category_image($current_image);
        }
        $final_image_url = $new_uploaded_image;
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE categories
            SET name = ?, image_url = ?, description = ?, is_available = ?
            WHERE category_id = ?
        ");
        $stmt->bind_param("sssii", $category_name, $final_image_url, $category_description, $category_is_available, $category_id);

        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION["_toast"] = ["text" => "Category updated successfully.", "type" => "success"];
            // FIX: was missing a redirect (no PRG pattern) — the toast was set
            // then immediately consumed on the same render, so it showed once
            // but a browser refresh would resubmit the POST. Add PRG + flush.
            session_write_close();
            header("Location: chef-control.php#categories-section");
            exit;
        } else {
            $errors['general'] = "Failed to update category.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   HANDLE ADD MENU ITEM
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $form_data['category_id'] = $_POST['category_id'] ?? '';
    $form_data['name'] = $_POST['name'] ?? '';
    $form_data['description'] = $_POST['description'] ?? '';
    $form_data['price'] = $_POST['price'] ?? '';
    $form_data['rating'] = $_POST['rating'] ?? '';
    $form_data['is_available'] = isset($_POST['is_available']);
    
    $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $name = validate_text($_POST['name'] ?? '', 'item name', 150, $errors, true);
    $description = validate_text($_POST['description'] ?? '', 'description', 500, $errors, false);
    if ($description === false) {
        $description = '';
    }
    $price = validate_price($_POST['price'] ?? 0, $errors);
    $rating = validate_rating($_POST['rating'] ?? 0, $errors);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if ($category_id === false || $category_id <= 0) {
        $errors['category_id'] = "Please select a valid category.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO menu_items (category_id, name, description, price, rating, is_available)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issddi", $category_id, $name, $description, $price, $rating, $is_available);

        if ($stmt->execute()) {
            $_SESSION["_toast"] = ["text" => "Menu item added successfully.", "type" => "success"];
            $form_data = [];
            session_write_close(); // FIX: flush toast to disk before redirect
            header("Location: chef-control.php#menu-section");
            exit;
        } else {
            $errors['general'] = "Failed to add menu item.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   HANDLE DELETE MENU ITEM
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $is_ajax_chef = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';
    $item_id = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT);

    if ($item_id > 0) {
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);

        if ($stmt->execute()) {
            $stmt->close();
            if ($is_ajax_chef) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => true, 'item_id' => $item_id]);
                exit;
            }
            $_SESSION["_toast"] = ["text" => "Menu item deleted successfully.", "type" => "danger"];
            session_write_close();
            header("Location: chef-control.php#menu-section");
            exit;
        } else {
            $stmt->close();
            if ($is_ajax_chef) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Failed to delete menu item.']);
                exit;
            }
            $errors['general'] = "Failed to delete menu item.";
        }
    } else {
        if (!empty($is_ajax_chef)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid item ID.']);
            exit;
        }
    }
}

/* ---------------------------
   LOAD ITEM FOR EDIT
---------------------------- */
if (isset($_GET['edit_id'])) {
    $edit_id = filter_var($_GET['edit_id'], FILTER_VALIDATE_INT);

    if ($edit_id > 0) {
        $stmt = $conn->prepare("SELECT * FROM menu_items WHERE item_id = ?");
        $stmt->bind_param("i", $edit_id);
        $stmt->execute();
        $edit_item = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    }
}

/* ---------------------------
   HANDLE UPDATE MENU ITEM
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_item'])) {
    $item_id = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT);
    $category_id = filter_var($_POST['category_id'] ?? 0, FILTER_VALIDATE_INT);
    $name = validate_text($_POST['name'] ?? '', 'item name', 150, $errors, true);
    $description = validate_text($_POST['description'] ?? '', 'description', 500, $errors, false);
    if ($description === false) {
        $description = '';
    }
    $price = validate_price($_POST['price'] ?? 0, $errors);
    $rating = validate_rating($_POST['rating'] ?? 0, $errors);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if ($item_id === false || $item_id <= 0) {
        $errors['general'] = "Invalid menu item.";
    }
    
    if ($category_id === false || $category_id <= 0) {
        $errors['category_id'] = "Please select a valid category.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE menu_items
            SET category_id = ?, name = ?, description = ?, price = ?, rating = ?, is_available = ?
            WHERE item_id = ?
        ");
        $stmt->bind_param("issddii", $category_id, $name, $description, $price, $rating, $is_available, $item_id);

        if ($stmt->execute()) {
            $_SESSION["_toast"] = ["text" => "Menu item updated successfully.", "type" => "success"];
            session_write_close(); // FIX: flush toast to disk before redirect
            header("Location: chef-control.php#menu-section");
            exit;
        } else {
            $errors['general'] = "Failed to update menu item.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   HANDLE ORDER STATUS UPDATE
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $is_ajax_chef = !empty($_SERVER['HTTP_X_REQUESTED_WITH']) &&
                    strtolower($_SERVER['HTTP_X_REQUESTED_WITH']) === 'xmlhttprequest';

    $order_id = filter_var($_POST['order_id'] ?? 0, FILTER_VALIDATE_INT);
    $new_status = sanitize_input($_POST['new_status'] ?? '');

    if ($order_id > 0 && in_array($new_status, ['preparing', 'ready'], true)) {
        $stmt = $conn->prepare("SELECT status FROM orders WHERE order_id = ?");
        $stmt->bind_param("i", $order_id);
        $stmt->execute();
        $current_order = $stmt->get_result()->fetch_assoc();
        $stmt->close();

        if ($current_order) {
            $current_status = $current_order['status'];

            $allowed_transition =
                ($current_status === 'pending' && $new_status === 'preparing') ||
                ($current_status === 'preparing' && $new_status === 'ready');

            if ($allowed_transition) {
                $stmt = $conn->prepare("UPDATE orders SET status = ? WHERE order_id = ?");
                $stmt->bind_param("si", $new_status, $order_id);

                if ($stmt->execute()) {
                    $stmt->close();
                    if ($is_ajax_chef) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => true, 'new_status' => $new_status, 'order_id' => $order_id]);
                        exit;
                    }
                    $_SESSION["_toast"] = ["text" => "Order status updated successfully.", "type" => "success"];
                    session_write_close();
                    header("Location: chef-control.php#orders-section");
                    exit;
                } else {
                    $stmt->close();
                    if ($is_ajax_chef) {
                        header('Content-Type: application/json');
                        echo json_encode(['ok' => false, 'error' => 'Failed to update order status.']);
                        exit;
                    }
                    $errors['general'] = "Failed to update order status.";
                }
            } else {
                if ($is_ajax_chef) {
                    header('Content-Type: application/json');
                    echo json_encode(['ok' => false, 'error' => 'Invalid status transition.']);
                    exit;
                }
                $errors['general'] = "Invalid status transition for chef.";
            }
        } else {
            if ($is_ajax_chef) {
                header('Content-Type: application/json');
                echo json_encode(['ok' => false, 'error' => 'Order not found.']);
                exit;
            }
            $errors['general'] = "Order not found.";
        }
    } else {
        if (!empty($is_ajax_chef)) {
            header('Content-Type: application/json');
            echo json_encode(['ok' => false, 'error' => 'Invalid status update request.']);
            exit;
        }
        $errors['general'] = "Invalid status update request.";
    }
}

/* ---------------------------
   FETCH CATEGORY OPTIONS FOR MENU FORM
---------------------------- */
$category_options = $conn->query("
    SELECT category_id, name
    FROM categories
    WHERE is_available = 1
    ORDER BY name
");

/* ---------------------------
   FETCH ALL CATEGORIES
---------------------------- */
$category_list = $conn->query("
    SELECT category_id, name, image_url, description, is_available
    FROM categories
    ORDER BY category_id DESC
");

/* ---------------------------
   FETCH MENU ITEMS
---------------------------- */
$menu_items = $conn->query("
    SELECT mi.item_id, mi.name, mi.description, mi.price, mi.rating, mi.is_available,
           c.name AS category_name
    FROM menu_items mi
    INNER JOIN categories c ON mi.category_id = c.category_id
    ORDER BY mi.item_id DESC
");

/* ---------------------------
   FETCH CHEF ORDERS
---------------------------- */
$orders = $conn->query("
    SELECT
        o.order_id,
        o.total_amount,
        o.status,
        o.created_at,
        u.full_name
    FROM orders o
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE o.status IN ('pending', 'preparing', 'ready')
    ORDER BY o.order_id DESC
");

/* ---------------------------
   FETCH KOTs — only show active kitchen work.
   Active KOTs: orders pending or preparing (not yet ready).
   Archived KOTs are hidden — the order is done from the kitchen's perspective.
   KOT disappears from this view once the order reaches 'ready' (requirement: AC-6).
---------------------------- */
// FIX: Also include archived KOTs whose order is 'ready' or 'out_for_delivery'
// so the chef can still print/view the KOT after marking an order Ready.
// The trigger trg_archive_kot_on_ready sets kot_status='archived' on ready,
// which previously caused KOTs to disappear the moment the chef marked them done.
$kots = $conn->query("
    SELECT
        k.kot_id,
        k.order_id,
        k.kot_status,
        k.delivery_mode,
        k.special_notes,
        k.created_at AS kot_created_at,
        o.total_amount,
        o.status AS order_status,
        o.payment_method,
        o.created_at AS order_created_at,
        u.full_name AS customer_name
    FROM kitchen_order_tickets k
    INNER JOIN orders o ON k.order_id = o.order_id
    INNER JOIN users u ON o.user_id = u.user_id
    WHERE (
        (k.kot_status = 'active'   AND o.status IN ('pending', 'preparing'))
        OR
        (k.kot_status = 'archived' AND o.status IN ('ready', 'out_for_delivery'))
    )
    ORDER BY k.kot_id DESC
");

/* ---------------------------
   FETCH KOT ITEMS (all active KOT orders)
---------------------------- */
$kot_items_map = [];
if ($kots && $kots->num_rows > 0) {
    $kots->data_seek(0);
    $kot_order_ids = [];
    while ($row = $kots->fetch_assoc()) {
        $kot_order_ids[] = (int)$row['order_id'];
    }
    $kots->data_seek(0);

    if (!empty($kot_order_ids)) {
        $placeholders = implode(',', $kot_order_ids);
        $items_result = $conn->query("
            SELECT oi.order_id, mi.name AS item_name, oi.quantity, oi.price
            FROM order_items oi
            INNER JOIN menu_items mi ON oi.item_id = mi.item_id
            WHERE oi.order_id IN ($placeholders)
            ORDER BY oi.order_item_id
        ");
        while ($irow = $items_result->fetch_assoc()) {
            $kot_items_map[$irow['order_id']][] = $irow;
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Control Panel</title>
    <script src="../assets/js/theme.js"></script>
    <link rel="stylesheet" href="../assets/css/style.css">
    <style>
        /* ── Themed Modal Popup ── */
        .modal-overlay {
            position: fixed; inset: 0;
            background: rgba(0,0,0,0.65);
            z-index: 1000;
            display: flex;
            align-items: center;
            justify-content: center;
            backdrop-filter: blur(4px);
        }
        .modal-box {
            background: #2a2a2a;
            border: 1px solid rgba(77,184,72,0.2);
            border-radius: 14px;
            padding: 32px 28px;
            max-width: 400px;
            width: 90%;
            text-align: center;
            box-shadow: 0 24px 60px rgba(0,0,0,0.5);
            animation: modalIn .18s ease;
        }
        @keyframes modalIn {
            from { opacity:0; transform:scale(.93) translateY(8px); }
            to   { opacity:1; transform:scale(1)  translateY(0); }
        }
        .modal-icon { width:56px; height:56px; border-radius:50%; margin:0 auto 14px; display:flex; align-items:center; justify-content:center; }
        .modal-icon-danger { background: rgba(229,57,53,0.12); }
        .modal-title { font-size:18px; font-weight:700; color:#fff; margin-bottom:8px; }
        .modal-body  { font-size:14px; color:rgba(255,255,255,0.6); line-height:1.6; margin-bottom:24px; }
        .modal-actions { display:flex; gap:12px; justify-content:center; }
        .modal-btn { padding:10px 24px; border-radius:8px; font-size:14px; font-weight:600; cursor:pointer; border:none; transition:all .15s; }
        .modal-btn-cancel { background:rgba(255,255,255,0.08); color:rgba(255,255,255,0.7); }
        .modal-btn-cancel:hover { background:rgba(255,255,255,0.14); }
        .modal-btn-danger { background:#e53935; color:#fff; }
        .modal-btn-danger:hover { background:#c62828; }

        /* ── KOT Grid ── */
        .kot-grid {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 20px;
            margin-top: 8px;
        }
        .kot-ticket {
            background: #1e1e1e;
            border: 1px solid rgba(77,184,72,0.2);
            border-radius: 12px;
            overflow: hidden;
        }
        .kot-header {
            background: rgba(77,184,72,0.08);
            border-bottom: 1px solid rgba(77,184,72,0.15);
            padding: 12px 16px;
            display: flex;
            align-items: center;
            gap: 10px;
            flex-wrap: wrap;
        }
        .kot-id { font-size:16px; font-weight:800; color:#4db848; }
        .kot-order-id { font-size:12px; color:rgba(255,255,255,0.5); }
        .kot-body { padding: 14px 16px; }
        .kot-row { display:flex; justify-content:space-between; margin-bottom:8px; font-size:13px; }
        .kot-label { color:rgba(255,255,255,0.5); }
        .kot-value { color:#fff; font-weight:500; text-align:right; max-width:60%; }
        .kot-items-title { font-size:12px; color:#4db848; text-transform:uppercase; letter-spacing:.5px; font-weight:700; margin:14px 0 8px; }
        .kot-items-table { width:100%; border-collapse:collapse; font-size:13px; }
        .kot-items-table th { color:rgba(255,255,255,0.4); font-size:11px; text-transform:uppercase; letter-spacing:.3px; text-align:left; padding:4px 8px; border-bottom:1px solid rgba(255,255,255,0.06); }
        .kot-items-table td { padding:6px 8px; color:rgba(255,255,255,0.85); border-bottom:1px solid rgba(255,255,255,0.04); }
        .kot-total-row td { color:#4db848; border-top:1px solid rgba(77,184,72,0.2); border-bottom:none; }
        .kot-footer { padding:12px 16px; border-top:1px solid rgba(255,255,255,0.05); display:flex; gap:8px; }
        .chef-btn-sm { padding:6px 14px; font-size:12px; }
        .chef-btn-danger { background:rgba(229,57,53,0.15); color:#ef9a9a; border:1px solid rgba(229,57,53,0.3); }
        .chef-btn-danger:hover { background:rgba(229,57,53,0.25); }
    </style>
</head>
<body>

<div class="layout">

    <div class="sidebar">
        <div class="navbar-title">
            Herald Canteen
            <span>Chef Portal</span>
        </div>
        <nav>
            <a href="chef-control.php" class="active">👨‍🍳 Chef Home</a>
            <a href="#categories-section">🖼️ Categories</a>
            <a href="#menu-section">🍽️ Manage Menu</a>
            <a href="#orders-section">📦 Kitchen Orders</a>
            <a href="#kot-section">🎫 KOT Tickets</a>
            <a href="user-logs.php">📋 User Logs</a>
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </div>

    <div class="main">

        <div class="topbar">
            <div class="topbar-welcome">
                Welcome, <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?>
            </div>

            <!-- Theme Toggle -->
            <label class="theme-toggle" title="Toggle light/dark mode">
                <input type="checkbox" class="theme-checkbox">
                <span class="theme-slider"></span>
            </label>
        </div>

        <div class="content">

            <div class="section-title">
                <h2>Chef Control Panel</h2>
                <p>Manage categories, menu items, and kitchen order progress.</p>
            </div>

            <?php if (isset($errors['general'])): ?>
                <div class="alert-error"><?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

            <!-- ── ADD / EDIT CATEGORY FORM ── -->
            <div class="panel-card" id="categories-section">
                <h3><?php echo $edit_category ? 'Edit Category' : 'Add Category'; ?></h3>

                <form method="POST" enctype="multipart/form-data" novalidate>
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?php echo htmlspecialchars($edit_category['category_id'], ENT_QUOTES, 'UTF-8'); ?>">
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($edit_category['image_url'] ?? '', ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>

                    <div class="chef-form-grid">
                        <div>
                            <label>Category Name *</label>
                            <input
                                type="text"
                                name="category_name"
                                required
                                class="<?php echo isset($errors['category name']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($edit_category['name'] ?? $form_data['category_name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <?php if (isset($errors['category name'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['category name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label>Category Image (Max 5MB, JPG, PNG, WEBP)</label>
                            <input type="file" name="category_image" accept=".jpg,.jpeg,.png,.webp" class="<?php echo isset($errors['image']) ? 'error' : ''; ?>">
                            <?php if (isset($errors['image'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['image'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                            <?php if ($edit_category && !empty($edit_category['image_url'])): ?>
                                <div class="current-image-preview">
                                    <img src="<?php echo htmlspecialchars($edit_category['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Current Category Image">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="full">
                            <label>Description (Max 500 characters)</label>
                            <textarea name="category_description" class="<?php echo isset($errors['description']) ? 'error' : ''; ?>"><?php echo htmlspecialchars($edit_category['description'] ?? $form_data['category_description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="full">
                            <label>
                                <input
                                    type="checkbox"
                                    name="category_is_available"
                                    value="1"
                                    <?php echo ($edit_category ? ((int)$edit_category['is_available'] === 1 ? 'checked' : '') : (isset($form_data['category_is_available']) && $form_data['category_is_available'] ? 'checked' : 'checked')); ?>
                                >
                                Available
                            </label>
                        </div>

                        <div class="full">
                            <?php if ($edit_category): ?>
                                <button type="submit" name="update_category" class="chef-btn">Update Category</button>
                            <?php else: ?>
                                <button type="submit" name="add_category" class="chef-btn">Add Category</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ── CATEGORIES TABLE ── -->
            <div class="panel-card">
                <h3>Categories</h3>

                <table class="chef-table">
                    <tr>
                        <th>ID</th>
                        <th>Image</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>

                    <?php if ($category_list && $category_list->num_rows > 0): ?>
                        <?php $counter = 1; ?>
                        <?php while ($cat = $category_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td>
                                    <?php if (!empty($cat['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($cat['image_url'], ENT_QUOTES, 'UTF-8'); ?>" alt="Category Image" class="category-thumb">
                                    <?php else: ?>
                                        No image
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cat['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($cat['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo ((int)$cat['is_available'] === 1) ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="chef-control.php?edit_category_id=<?php echo htmlspecialchars($cat['category_id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No categories found.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- ── ADD / EDIT MENU ITEM FORM ── -->
            <div class="panel-card">
                <h3><?php echo $edit_item ? 'Edit Menu Item' : 'Add Menu Item'; ?></h3>

                <form method="POST" novalidate>
                    <?php if ($edit_item): ?>
                        <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($edit_item['item_id'], ENT_QUOTES, 'UTF-8'); ?>">
                    <?php endif; ?>

                    <div class="chef-form-grid">
                        <div>
                            <label>Category *</label>
                            <select name="category_id" required class="<?php echo isset($errors['category_id']) ? 'error' : ''; ?>">
                                <option value="">Select Category</option>
                                <?php if ($category_options && $category_options->num_rows > 0): ?>
                                    <?php while ($category = $category_options->fetch_assoc()): ?>
                                        <?php 
                                        $selected = false;
                                        if ($edit_item && $edit_item['category_id'] == $category['category_id']) {
                                            $selected = true;
                                        } elseif (isset($form_data['category_id']) && $form_data['category_id'] == $category['category_id']) {
                                            $selected = true;
                                        }
                                        ?>
                                        <option value="<?php echo htmlspecialchars($category['category_id'], ENT_QUOTES, 'UTF-8'); ?>" <?php echo $selected ? 'selected' : ''; ?>>
                                            <?php echo htmlspecialchars($category['name'], ENT_QUOTES, 'UTF-8'); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                            <?php if (isset($errors['category_id'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['category_id'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label>Item Name * (Max 150 characters)</label>
                            <input
                                type="text"
                                name="name"
                                required
                                class="<?php echo isset($errors['item name']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($edit_item['name'] ?? $form_data['name'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <?php if (isset($errors['item name'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['item name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="full">
                            <label>Description (Max 500 characters)</label>
                            <textarea name="description" class="<?php echo isset($errors['description']) ? 'error' : ''; ?>"><?php echo htmlspecialchars($edit_item['description'] ?? $form_data['description'] ?? '', ENT_QUOTES, 'UTF-8'); ?></textarea>
                            <?php if (isset($errors['description'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['description'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label>Price * (Positive number)</label>
                            <input
                                type="number"
                                name="price"
                                step="0.01"
                                min="0.01"
                                required
                                class="<?php echo isset($errors['price']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($edit_item['price'] ?? $form_data['price'] ?? '', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <?php if (isset($errors['price'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['price'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div>
                            <label>Rating (0 to 5)</label>
                            <input
                                type="number"
                                name="rating"
                                step="0.1"
                                min="0"
                                max="5"
                                class="<?php echo isset($errors['rating']) ? 'error' : ''; ?>"
                                value="<?php echo htmlspecialchars($edit_item['rating'] ?? $form_data['rating'] ?? '0', ENT_QUOTES, 'UTF-8'); ?>"
                            >
                            <?php if (isset($errors['rating'])): ?>
                                <span class="error-message"><?php echo htmlspecialchars($errors['rating'], ENT_QUOTES, 'UTF-8'); ?></span>
                            <?php endif; ?>
                        </div>

                        <div class="full">
                            <label>
                                <input
                                    type="checkbox"
                                    name="is_available"
                                    value="1"
                                    <?php echo ($edit_item ? ((int)$edit_item['is_available'] === 1 ? 'checked' : '') : (isset($form_data['is_available']) && $form_data['is_available'] ? 'checked' : 'checked')); ?>
                                >
                                Available
                            </label>
                        </div>

                        <div class="full">
                            <?php if ($edit_item): ?>
                                <button type="submit" name="update_item" class="chef-btn">Update Item</button>
                            <?php else: ?>
                                <button type="submit" name="add_item" class="chef-btn">Add Item</button>
                            <?php endif; ?>
                        </div>
                    </div>
                </form>
            </div>

            <!-- ── MENU ITEMS TABLE ── -->
            <div class="panel-card" id="menu-section">
                <h3>Menu Items</h3>

                <table class="chef-table">
                    <tr>
                        <th>ID</th>
                        <th>Category</th>
                        <th>Name</th>
                        <th>Description</th>
                        <th>Price</th>
                        <th>Rating</th>
                        <th>Available</th>
                        <th>Actions</th>
                    </tr>

                    <?php if ($menu_items && $menu_items->num_rows > 0): ?>
                        <?php $counter = 1; ?>
                        <?php while ($item = $menu_items->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($item['category_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><?php echo htmlspecialchars($item['description'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>Rs. <?php echo number_format((float)$item['price'], 2); ?></td>
                                <td><?php echo number_format((float)$item['rating'], 1); ?></td>
                                <td><?php echo ((int)$item['is_available'] === 1) ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="chef-control.php?edit_id=<?php echo htmlspecialchars($item['item_id'], ENT_QUOTES, 'UTF-8'); ?>">Edit</a>

                                        <button type="button" class="chef-btn chef-btn-danger"
                                            onclick="openDeleteConfirm(<?php echo (int)$item['item_id']; ?>, '<?php echo addslashes(htmlspecialchars($item['name'], ENT_QUOTES, 'UTF-8')); ?>')">
                                            Delete
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="8">No menu items found.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- ── KITCHEN ORDERS TABLE ── -->
            <div class="panel-card" id="orders-section">
                <h3>Kitchen Orders</h3>

                <table class="chef-table">
                    <tr>
                        <th>ID</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Update</th>
                    </tr>

                    <?php if ($orders && $orders->num_rows > 0): ?>
                        <?php $counter = 1; ?>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $counter++; ?></td>
                                <td><?php echo htmlspecialchars($order['full_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>Rs. <?php echo number_format((float)$order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>">
                                        <?php echo htmlspecialchars($order['status'], ENT_QUOTES, 'UTF-8'); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($order['created_at'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td>
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <button type="button" class="chef-btn"
                                            onclick="updateOrderStatus(<?php echo (int)$order['order_id']; ?>, 'preparing', this)">
                                            Mark Preparing
                                        </button>
                                    <?php elseif ($order['status'] === 'preparing'): ?>
                                        <button type="button" class="chef-btn"
                                            onclick="updateOrderStatus(<?php echo (int)$order['order_id']; ?>, 'ready', this)">
                                            Mark Ready
                                        </button>
                                    <?php else: ?>
                                        Ready for staff
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    <?php else: ?>
                        <tr>
                            <td colspan="6">No chef-side orders found.</td>
                        </tr>
                    <?php endif; ?>
                </table>
            </div>

            <!-- ── KOT TICKETS SECTION ── -->
            <div class="panel-card" id="kot-section">
                <h3>🎫 Kitchen Order Tickets (KOT)</h3>
                <p style="color:rgba(255,255,255,0.5);font-size:13px;margin-bottom:16px;">Active tickets auto-archive when order status reaches "Ready". Only visible to Chef role.</p>

                <?php if ($kots && $kots->num_rows > 0): ?>
                    <div class="kot-grid">
                    <?php while ($kot = $kots->fetch_assoc()): ?>
                    <?php $items = $kot_items_map[$kot['order_id']] ?? []; ?>
                    <div class="kot-ticket" id="kot-<?php echo (int)$kot['kot_id']; ?>">
                        <div class="kot-header">
                            <div class="kot-id">KOT #<?php echo (int)$kot['kot_id']; ?></div>
                            <div class="kot-order-id">Order #<?php echo (int)$kot['order_id']; ?></div>
                            <span class="status-badge status-<?php echo htmlspecialchars($kot['order_status'], ENT_QUOTES, 'UTF-8'); ?>">
                                <?php echo ucfirst(htmlspecialchars($kot['order_status'], ENT_QUOTES, 'UTF-8')); ?>
                            </span>
                        </div>

                        <div class="kot-body">
                            <div class="kot-row">
                                <span class="kot-label">👤 Customer</span>
                                <span class="kot-value"><?php echo htmlspecialchars($kot['customer_name'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <div class="kot-row">
                                <span class="kot-label">🚚 Mode</span>
                                <span class="kot-value"><?php
                                    echo $kot['delivery_mode'] === 'takeaway' ? 'Takeaway 🥡' : 'Delivery 🚚';
                                ?></span>
                            </div>
                            <div class="kot-row">
                                <span class="kot-label">🕐 Time</span>
                                <span class="kot-value"><?php echo htmlspecialchars(date('d M, g:i A', strtotime($kot['order_created_at'])), ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>

                            <?php if (!empty($kot['special_notes'])): ?>
                            <div class="kot-row" style="margin-top:6px;background:rgba(77,184,72,0.07);border-radius:6px;padding:6px 8px;">
                                <span class="kot-label">📝 Customer Remark</span>
                                <span class="kot-value" style="color:#6dcc68;"><?php echo htmlspecialchars($kot['special_notes'], ENT_QUOTES, 'UTF-8'); ?></span>
                            </div>
                            <?php endif; ?>

                            <div class="kot-items-title">📋 Order Items</div>
                            <table class="kot-items-table">
                                <tr><th>Item</th><th>Qty</th><th>Price</th></tr>
                                <?php foreach ($items as $it): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($it['item_name'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    <td><?php echo (int)$it['quantity']; ?></td>
                                    <td>Rs. <?php echo number_format((float)$it['price'], 2); ?></td>
                                </tr>
                                <?php endforeach; ?>
                                <tr class="kot-total-row">
                                    <td colspan="2"><strong>Total</strong></td>
                                    <td><strong>Rs. <?php echo number_format((float)$kot['total_amount'], 2); ?></strong></td>
                                </tr>
                            </table>
                        </div>

                        <div class="kot-footer">
                            <a href="chef_kot_print.php?kot_id=<?php echo (int)$kot['kot_id']; ?>"
                               target="_blank"
                               class="chef-btn chef-btn-sm"
                               title="Open printable KOT (also works after Ready)">
                                🖨️ Print / Download KOT
                            </a>
                        </div>
                    </div>
                    <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <p class="no-result">No KOT tickets to display. Tickets appear when orders are placed and remain visible until delivered.</p>
                <?php endif; ?>
            </div>

        </div><!-- /.content -->
    </div><!-- /.main -->
</div><!-- /.layout -->

<!-- ── Delete Confirm Modal ── -->
<div class="modal-overlay" id="deleteModal" style="display:none;">
    <div class="modal-box">
        <div class="modal-icon modal-icon-danger">
            <svg width="28" height="28" viewBox="0 0 28 28" fill="none">
                <circle cx="14" cy="14" r="14" fill="rgba(229,57,53,0.15)"/>
                <path d="M9 9L19 19M19 9L9 19" stroke="#e53935" stroke-width="2.2" stroke-linecap="round"/>
            </svg>
        </div>
        <div class="modal-title">Delete Menu Item</div>
        <div class="modal-body" id="deleteModalBody">Are you sure you want to delete this item? This action cannot be undone.</div>
        <div class="modal-actions">
            <button class="modal-btn modal-btn-cancel" onclick="closeDeleteModal()">Cancel</button>
            <form method="POST" id="deleteForm" style="display:inline;">
                <input type="hidden" name="item_id" id="deleteItemId">
                <button type="submit" name="delete_item" class="modal-btn modal-btn-danger">Yes, Delete</button>
            </form>
        </div>
    </div>
</div>

<!-- ── Chef Toast Notification ── -->
<?php if ($chef_toast): ?>
<div class="toast chef-toast <?php echo $chef_toast['type'] === 'danger' ? 'toast-danger' : ''; ?>" id="chefToast">
    <div class="toast-icon">
        <?php if ($chef_toast['type'] === 'danger'): ?>
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <circle cx="9" cy="9" r="9" fill="#e53935"/>
            <path d="M6 6L12 12M12 6L6 12" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <?php else: ?>
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none">
            <circle cx="9" cy="9" r="9" fill="#4db848"/>
            <path d="M5 9.5L7.5 12L13 6.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/>
        </svg>
        <?php endif; ?>
    </div>
    <div class="toast-body">
        <span class="toast-title"><?php echo htmlspecialchars($chef_toast['text'], ENT_QUOTES, 'UTF-8'); ?></span>
        <span class="toast-sub"><?php echo $chef_toast['type'] === 'danger' ? 'Action completed.' : 'Changes saved successfully ✓'; ?></span>
    </div>
    <button class="toast-close" onclick="this.closest('.toast').classList.add('toast-hide')">✕</button>
</div>
<script>
    const chefToast = document.getElementById('chefToast');
    if (chefToast) {
        setTimeout(() => { chefToast.classList.add('toast-hide'); }, 3500);
    }
</script>
<?php endif; ?>

<script>
// ── Delete Confirm Modal ────────────────────────────────────────
function openDeleteConfirm(itemId, itemName) {
    document.getElementById('deleteItemId').value = itemId;
    document.getElementById('deleteModalBody').textContent =
        'Are you sure you want to delete "' + itemName + '"? This action cannot be undone.';
    document.getElementById('deleteModal').style.display = 'flex';
}
function closeDeleteModal() {
    document.getElementById('deleteModal').style.display = 'none';
}
document.getElementById('deleteModal').addEventListener('click', function(e) {
    if (e.target === this) closeDeleteModal();
});

// ── Delete via AJAX (no page reload) ───────────────────────────
document.getElementById('deleteForm').addEventListener('submit', function(e) {
    e.preventDefault();
    var itemId   = document.getElementById('deleteItemId').value;
    var confirmBtn = this.querySelector('button[type="submit"]');
    confirmBtn.disabled = true;
    confirmBtn.textContent = 'Deleting...';

    fetch('chef-control.php', {
        method:  'POST',
        headers: {
            'Content-Type':     'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'delete_item=1&item_id=' + encodeURIComponent(itemId)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        closeDeleteModal();
        if (data.ok) {
            // Remove the row from the table without reload
            var rows = document.querySelectorAll('#menu-section tr');
            rows.forEach(function(row) {
                var deleteBtn = row.querySelector('button[onclick*="openDeleteConfirm(' + data.item_id + ',"]');
                if (!deleteBtn) {
                    // Also check via data attribute approach
                    deleteBtn = row.querySelector('button[onclick*="openDeleteConfirm(' + itemId + ',"]');
                }
                if (deleteBtn) {
                    row.style.transition = 'opacity 0.25s';
                    row.style.opacity = '0';
                    setTimeout(function() { row.remove(); reindexTable(); }, 260);
                }
            });
            showChefToast('Menu item deleted successfully.', 'danger');
        } else {
            alert(data.error || 'Failed to delete item.');
            confirmBtn.disabled = false;
            confirmBtn.textContent = 'Yes, Delete';
        }
    })
    .catch(function() {
        closeDeleteModal();
        alert('Network error. Please try again.');
        confirmBtn.disabled = false;
        confirmBtn.textContent = 'Yes, Delete';
    });
});

function reindexTable() {
    var rows = document.querySelectorAll('#menu-section tr:not(:first-child)');
    rows.forEach(function(row, i) {
        var td = row.querySelector('td:first-child');
        if (td) td.textContent = i + 1;
    });
}

// ── Update Order Status via AJAX (no page reload) ──────────────
function updateOrderStatus(orderId, newStatus, btn) {
    btn.disabled = true;
    btn.textContent = '...';

    fetch('chef-control.php', {
        method:  'POST',
        headers: {
            'Content-Type':     'application/x-www-form-urlencoded',
            'X-Requested-With': 'XMLHttpRequest'
        },
        body: 'update_order_status=1&order_id=' + encodeURIComponent(orderId)
              + '&new_status=' + encodeURIComponent(newStatus)
    })
    .then(function(r) { return r.json(); })
    .then(function(data) {
        if (data.ok) {
            // Update the status badge and button in the row
            var row = btn.closest('tr');
            if (row) {
                var badge = row.querySelector('.status-badge');
                if (badge) {
                    badge.className = 'status-badge status-' + data.new_status;
                    badge.textContent = data.new_status;
                }
                var td = btn.parentElement;
                if (data.new_status === 'preparing') {
                    btn.disabled = false;
                    btn.textContent = 'Mark Ready';
                    btn.onclick = function() { updateOrderStatus(orderId, 'ready', btn); };
                } else if (data.new_status === 'ready') {
                    td.textContent = 'Ready for staff';
                }
            }
            showChefToast('Order status updated successfully.', 'success');
        } else {
            btn.disabled = false;
            btn.textContent = newStatus === 'preparing' ? 'Mark Preparing' : 'Mark Ready';
            alert(data.error || 'Failed to update order status.');
        }
    })
    .catch(function() {
        btn.disabled = false;
        btn.textContent = newStatus === 'preparing' ? 'Mark Preparing' : 'Mark Ready';
        alert('Network error. Please try again.');
    });
}

// ── Chef toast for AJAX actions ────────────────────────────────
function showChefToast(text, type) {
    var existing = document.getElementById('chefToast');
    if (existing) existing.remove();

    var isDanger = type === 'danger';
    var svgIcon = isDanger
        ? '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="9" fill="#e53935"/><path d="M6 6L12 12M12 6L6 12" stroke="white" stroke-width="2" stroke-linecap="round"/></svg>'
        : '<svg width="18" height="18" viewBox="0 0 18 18" fill="none"><circle cx="9" cy="9" r="9" fill="#4db848"/><path d="M5 9.5L7.5 12L13 6.5" stroke="white" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/></svg>';

    var toast = document.createElement('div');
    toast.id = 'chefToast';
    toast.className = 'toast chef-toast' + (isDanger ? ' toast-danger' : '');
    toast.innerHTML = '<div class="toast-icon">' + svgIcon + '</div>'
        + '<div class="toast-body"><span class="toast-title">' + text + '</span>'
        + '<span class="toast-sub">' + (isDanger ? 'Action completed.' : 'Changes saved successfully \u2713') + '</span></div>'
        + '<button class="toast-close" onclick="this.closest(\'.toast\').classList.add(\'toast-hide\')">&#x2715;</button>';
    document.body.appendChild(toast);
    setTimeout(function() { toast.classList.add('toast-hide'); }, 3500);
}


// The "Print / Download KOT" link opens chef_kot_print.php?kot_id=N in a new tab.
</script>

</body>
</html>