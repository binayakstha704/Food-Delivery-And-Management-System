<?php
session_start();
require_once "../config/db.php";
require_once "../includes/auth.php";

require_role('chef');

function upload_category_image(array $file, array &$errors): ?string
{
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) {
        return null;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        $errors[] = "Image upload failed.";
        return null;
    }

    $allowed_extensions = ['jpg', 'jpeg', 'png', 'webp'];
    $extension = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));

    if (!in_array($extension, $allowed_extensions, true)) {
        $errors[] = "Only JPG, JPEG, PNG, and WEBP images are allowed.";
        return null;
    }

    $image_info = @getimagesize($file['tmp_name']);
    if ($image_info === false) {
        $errors[] = "Uploaded file is not a valid image.";
        return null;
    }

    $upload_dir = dirname(__DIR__) . "/assets/images/";

    if (!is_dir($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }

    $new_filename = "category_" . time() . "_" . uniqid() . "." . $extension;
    $target_path = $upload_dir . $new_filename;

    if (!move_uploaded_file($file['tmp_name'], $target_path)) {
        $errors[] = "Failed to save uploaded image.";
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
$errors = [];
$edit_item = null;
$edit_category = null;

/* ---------------------------
   HANDLE ADD CATEGORY
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_category'])) {
    $category_name = trim($_POST['category_name'] ?? '');
    $category_description = trim($_POST['category_description'] ?? '');
    $category_is_available = isset($_POST['category_is_available']) ? 1 : 0;

    if ($category_name === '') {
        $errors[] = "Category name is required.";
    }

    $category_image_url = upload_category_image($_FILES['category_image'] ?? [], $errors);

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO categories (name, image_url, description, is_available)
            VALUES (?, ?, ?, ?)
        ");
        $stmt->bind_param("sssi", $category_name, $category_image_url, $category_description, $category_is_available);

        if ($stmt->execute()) {
            $message = "Category added successfully.";
        } else {
            $errors[] = "Failed to add category. Category name may already exist.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   LOAD CATEGORY FOR EDIT
---------------------------- */
if (isset($_GET['edit_category_id'])) {
    $edit_category_id = (int) $_GET['edit_category_id'];

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
    $category_id = (int) ($_POST['category_id'] ?? 0);
    $category_name = trim($_POST['category_name'] ?? '');
    $category_description = trim($_POST['category_description'] ?? '');
    $category_is_available = isset($_POST['category_is_available']) ? 1 : 0;
    $current_image = $_POST['current_image'] ?? '';

    if ($category_id <= 0) {
        $errors[] = "Invalid category.";
    }

    if ($category_name === '') {
        $errors[] = "Category name is required.";
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
            $message = "Category updated successfully.";
            $edit_category = null;
        } else {
            $errors[] = "Failed to update category.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   HANDLE ADD MENU ITEM
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['add_item'])) {
    $category_id  = (int)($_POST['category_id'] ?? 0);
    $name         = trim($_POST['name'] ?? '');
    $description  = trim($_POST['description'] ?? '');
    $price        = (float)($_POST['price'] ?? 0);
    $rating       = (float)($_POST['rating'] ?? 0);
    $is_available = isset($_POST['is_available']) ? 1 : 0;

    if ($category_id <= 0) {
        $errors[] = "Please select a category.";
    }
    if ($name === '') {
        $errors[] = "Item name is required.";
    }
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0.";
    }
    if ($rating < 0 || $rating > 5) {
        $errors[] = "Rating must be between 0 and 5.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO menu_items (category_id, name, description, price, rating, is_available)
            VALUES (?, ?, ?, ?, ?, ?)
        ");
        $stmt->bind_param("issddi", $category_id, $name, $description, $price, $rating, $is_available);

        if ($stmt->execute()) {
            $message = "Menu item added successfully.";
        } else {
            $errors[] = "Failed to add menu item.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   HANDLE DELETE MENU ITEM
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_item'])) {
    $item_id = (int)($_POST['item_id'] ?? 0);

    if ($item_id > 0) {
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);

        if ($stmt->execute()) {
            $message = "Menu item deleted successfully.";
        } else {
            $errors[] = "Failed to delete menu item.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   LOAD ITEM FOR EDIT
---------------------------- */
if (isset($_GET['edit_id'])) {
    $edit_id = (int)$_GET['edit_id'];

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
    $item_id       = (int)($_POST['item_id'] ?? 0);
    $category_id   = (int)($_POST['category_id'] ?? 0);
    $name          = trim($_POST['name'] ?? '');
    $description   = trim($_POST['description'] ?? '');
    $price         = (float)($_POST['price'] ?? 0);
    $rating        = (float)($_POST['rating'] ?? 0);
    $is_available  = isset($_POST['is_available']) ? 1 : 0;

    if ($item_id <= 0) {
        $errors[] = "Invalid menu item.";
    }
    if ($category_id <= 0) {
        $errors[] = "Please select a category.";
    }
    if ($name === '') {
        $errors[] = "Item name is required.";
    }
    if ($price <= 0) {
        $errors[] = "Price must be greater than 0.";
    }
    if ($rating < 0 || $rating > 5) {
        $errors[] = "Rating must be between 0 and 5.";
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE menu_items
            SET category_id = ?, name = ?, description = ?, price = ?, rating = ?, is_available = ?
            WHERE item_id = ?
        ");
        $stmt->bind_param("issddii", $category_id, $name, $description, $price, $rating, $is_available, $item_id);

        if ($stmt->execute()) {
            $message = "Menu item updated successfully.";
            $edit_item = null;
        } else {
            $errors[] = "Failed to update menu item.";
        }

        $stmt->close();
    }
}

/* ---------------------------
   HANDLE ORDER STATUS UPDATE
---------------------------- */
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_order_status'])) {
    $order_id   = (int)($_POST['order_id'] ?? 0);
    $new_status = $_POST['new_status'] ?? '';

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
                    $message = "Order status updated successfully.";
                } else {
                    $errors[] = "Failed to update order status.";
                }

                $stmt->close();
            } else {
                $errors[] = "Invalid status transition for chef.";
            }
        } else {
            $errors[] = "Order not found.";
        }
    } else {
        $errors[] = "Invalid status update request.";
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Control Panel</title>
    <link rel="stylesheet" href="../assets/css/dashboard.css">

    <style>
        :root {
            --chef-primary: #1f6f43;
            --chef-primary-dark: #155233;
            --chef-soft: #eaf6ef;
        }

        body {
            color: #222;
        }

        .sidebar {
            background: linear-gradient(180deg, var(--chef-primary), var(--chef-primary-dark));
        }

        .navbar-title,
        .navbar-title span {
            color: #fff;
        }

        .sidebar nav a {
            color: #f5f5f5;
        }

        .sidebar nav a.active,
        .sidebar nav a:hover {
            background: rgba(255, 255, 255, 0.18);
            color: #fff;
        }

        .topbar {
            border-bottom: 2px solid var(--chef-soft);
        }

        .topbar div {
            color: var(--chef-primary) !important;
        }

        .section-title h2 {
            color: var(--chef-primary) !important;
        }

        .section-title p {
            color: #555 !important;
        }

        .panel-card {
            background: #fff;
            border-radius: 18px;
            padding: 24px;
            margin-bottom: 24px;
            box-shadow: 0 4px 14px rgba(0,0,0,0.08);
        }

        .panel-card,
        .panel-card * {
            color: #222;
        }

        .panel-card h3 {
            margin-bottom: 18px;
            color: var(--chef-primary) !important;
        }

        .chef-form-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
        }

        .chef-form-grid .full {
            grid-column: 1 / -1;
        }

        .chef-form-grid label {
            display: block;
            margin-bottom: 6px;
            font-weight: 600;
            color: #333;
        }

        .chef-form-grid input,
        .chef-form-grid select,
        .chef-form-grid textarea {
            width: 100%;
            padding: 12px 14px;
            border: 1px solid #d6d6d6;
            border-radius: 10px;
            font-size: 14px;
            background: #fff;
            color: #222 !important;
        }

        .chef-form-grid textarea {
            min-height: 100px;
            resize: vertical;
        }

        .chef-form-grid input::placeholder,
        .chef-form-grid textarea::placeholder {
            color: #777;
        }

        .chef-form-grid select option {
            color: #222;
            background: #fff;
        }

        .chef-form-grid input:focus,
        .chef-form-grid select:focus,
        .chef-form-grid textarea:focus {
            outline: none;
            border-color: var(--chef-primary);
            box-shadow: 0 0 0 3px rgba(31, 111, 67, 0.12);
        }

        .chef-btn {
            background: var(--chef-primary);
            color: #fff !important;
            border: none;
            padding: 12px 18px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
        }

        .chef-btn:hover {
            background: var(--chef-primary-dark);
        }

        .chef-table {
            width: 100%;
            border-collapse: collapse;
            background: #fff;
            overflow: hidden;
            border-radius: 12px;
        }

        .chef-table th,
        .chef-table td {
            border: 1px solid #eee;
            padding: 12px;
            text-align: left;
            vertical-align: top;
            color: #222 !important;
        }

        .chef-table th {
            background: var(--chef-soft);
            color: var(--chef-primary-dark) !important;
        }

        .chef-table td a {
            color: var(--chef-primary) !important;
            text-decoration: none;
            font-weight: 600;
        }

        .chef-table td a:hover {
            color: var(--chef-primary-dark) !important;
            text-decoration: underline;
        }

        .status-badge {
            display: inline-block;
            padding: 6px 10px;
            border-radius: 999px;
            font-size: 12px;
            font-weight: 700;
            color: white !important;
            text-transform: capitalize;
        }

        .status-pending { background: #ef6c00; }
        .status-preparing { background: #1565c0; }
        .status-ready { background: #2e7d32; }

        .alert-success {
            background: #e8f5e9;
            color: #1b5e20 !important;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 18px;
        }

        .alert-error {
            background: #ffebee;
            color: #b71c1c !important;
            padding: 12px 16px;
            border-radius: 10px;
            margin-bottom: 12px;
        }

        .action-links {
            display: flex;
            gap: 10px;
            align-items: center;
            flex-wrap: wrap;
        }

        .action-links a {
            color: var(--chef-primary) !important;
            text-decoration: none;
            font-weight: 600;
        }

        .action-links a:hover {
            color: var(--chef-primary-dark) !important;
            text-decoration: underline;
        }

        .category-thumb {
            width: 70px;
            height: 70px;
            object-fit: cover;
            border-radius: 10px;
            border: 1px solid #ddd;
            background: #f5f5f5;
        }

        .current-image-preview {
            margin-top: 10px;
        }

        .current-image-preview img {
            width: 90px;
            height: 90px;
            object-fit: cover;
            border-radius: 12px;
            border: 1px solid #ddd;
        }

        @media (max-width: 900px) {
            .chef-form-grid {
                grid-template-columns: 1fr;
            }

            .chef-form-grid .full {
                grid-column: auto;
            }
        }
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
            <a href="logout.php">🚪 Logout</a>
        </nav>
    </div>

    <div class="main">

        <div class="topbar">
            <div style="font-weight:600;">
                Welcome, <?php echo htmlspecialchars($_SESSION['full_name']); ?>
            </div>
        </div>

        <div class="content">

            <div class="section-title">
                <h2>Chef Control Panel</h2>
                <p>Manage categories, menu items, and kitchen order progress.</p>
            </div>

            <?php if ($message !== ''): ?>
                <div class="alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <?php if (!empty($errors)): ?>
                <?php foreach ($errors as $error): ?>
                    <div class="alert-error"><?php echo htmlspecialchars($error); ?></div>
                <?php endforeach; ?>
            <?php endif; ?>

            <div class="panel-card" id="categories-section">
                <h3><?php echo $edit_category ? 'Edit Category' : 'Add Category'; ?></h3>

                <form method="POST" enctype="multipart/form-data">
                    <?php if ($edit_category): ?>
                        <input type="hidden" name="category_id" value="<?php echo $edit_category['category_id']; ?>">
                        <input type="hidden" name="current_image" value="<?php echo htmlspecialchars($edit_category['image_url'] ?? ''); ?>">
                    <?php endif; ?>

                    <div class="chef-form-grid">
                        <div>
                            <label>Category Name</label>
                            <input
                                type="text"
                                name="category_name"
                                required
                                value="<?php echo htmlspecialchars($edit_category['name'] ?? ''); ?>"
                            >
                        </div>

                        <div>
                            <label>Category Image</label>
                            <input type="file" name="category_image" accept=".jpg,.jpeg,.png,.webp">
                            <?php if ($edit_category && !empty($edit_category['image_url'])): ?>
                                <div class="current-image-preview">
                                    <img src="<?php echo htmlspecialchars($edit_category['image_url']); ?>" alt="Current Category Image">
                                </div>
                            <?php endif; ?>
                        </div>

                        <div class="full">
                            <label>Description</label>
                            <textarea name="category_description"><?php echo htmlspecialchars($edit_category['description'] ?? ''); ?></textarea>
                        </div>

                        <div class="full">
                            <label>
                                <input
                                    type="checkbox"
                                    name="category_is_available"
                                    value="1"
                                    <?php echo ($edit_category ? ((int)$edit_category['is_available'] === 1 ? 'checked' : '') : 'checked'); ?>
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
                        <?php while ($cat = $category_list->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $cat['category_id']; ?></td>
                                <td>
                                    <?php if (!empty($cat['image_url'])): ?>
                                        <img src="<?php echo htmlspecialchars($cat['image_url']); ?>" alt="Category Image" class="category-thumb">
                                    <?php else: ?>
                                        No image
                                    <?php endif; ?>
                                </td>
                                <td><?php echo htmlspecialchars($cat['name']); ?></td>
                                <td><?php echo htmlspecialchars($cat['description']); ?></td>
                                <td><?php echo ((int)$cat['is_available'] === 1) ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="chef-control.php?edit_category_id=<?php echo $cat['category_id']; ?>">Edit</a>
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

            <div class="panel-card">
                <h3><?php echo $edit_item ? 'Edit Menu Item' : 'Add Menu Item'; ?></h3>

                <form method="POST">
                    <?php if ($edit_item): ?>
                        <input type="hidden" name="item_id" value="<?php echo $edit_item['item_id']; ?>">
                    <?php endif; ?>

                    <div class="chef-form-grid">
                        <div>
                            <label>Category</label>
                            <select name="category_id" required>
                                <option value="">Select Category</option>
                                <?php if ($category_options && $category_options->num_rows > 0): ?>
                                    <?php while ($category = $category_options->fetch_assoc()): ?>
                                        <?php $selected = ($edit_item && $edit_item['category_id'] == $category['category_id']) ? 'selected' : ''; ?>
                                        <option value="<?php echo $category['category_id']; ?>" <?php echo $selected; ?>>
                                            <?php echo htmlspecialchars($category['name']); ?>
                                        </option>
                                    <?php endwhile; ?>
                                <?php endif; ?>
                            </select>
                        </div>

                        <div>
                            <label>Item Name</label>
                            <input
                                type="text"
                                name="name"
                                required
                                value="<?php echo htmlspecialchars($edit_item['name'] ?? ''); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>Description</label>
                            <textarea name="description"><?php echo htmlspecialchars($edit_item['description'] ?? ''); ?></textarea>
                        </div>

                        <div>
                            <label>Price</label>
                            <input
                                type="number"
                                name="price"
                                step="0.01"
                                min="0.01"
                                required
                                value="<?php echo htmlspecialchars($edit_item['price'] ?? ''); ?>"
                            >
                        </div>

                        <div>
                            <label>Rating</label>
                            <input
                                type="number"
                                name="rating"
                                step="0.1"
                                min="0"
                                max="5"
                                value="<?php echo htmlspecialchars($edit_item['rating'] ?? '0'); ?>"
                            >
                        </div>

                        <div class="full">
                            <label>
                                <input
                                    type="checkbox"
                                    name="is_available"
                                    value="1"
                                    <?php echo ($edit_item ? ((int)$edit_item['is_available'] === 1 ? 'checked' : '') : 'checked'); ?>
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
                        <?php while ($item = $menu_items->fetch_assoc()): ?>
                            <tr>
                                <td><?php echo $item['item_id']; ?></td>
                                <td><?php echo htmlspecialchars($item['category_name']); ?></td>
                                <td><?php echo htmlspecialchars($item['name']); ?></td>
                                <td><?php echo htmlspecialchars($item['description']); ?></td>
                                <td>Rs. <?php echo number_format((float)$item['price'], 2); ?></td>
                                <td><?php echo number_format((float)$item['rating'], 1); ?></td>
                                <td><?php echo ((int)$item['is_available'] === 1) ? 'Yes' : 'No'; ?></td>
                                <td>
                                    <div class="action-links">
                                        <a href="chef-control.php?edit_id=<?php echo $item['item_id']; ?>">Edit</a>

                                        <form method="POST" onsubmit="return confirm('Delete this item?');" style="display:inline;">
                                            <input type="hidden" name="item_id" value="<?php echo $item['item_id']; ?>">
                                            <button type="submit" name="delete_item" class="chef-btn">Delete</button>
                                        </form>
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

            <div class="panel-card" id="orders-section">
                <h3>Kitchen Orders</h3>

                <table class="chef-table">
                    <tr>
                        <th>Order ID</th>
                        <th>Customer</th>
                        <th>Total Amount</th>
                        <th>Status</th>
                        <th>Created At</th>
                        <th>Update</th>
                    </tr>

                    <?php if ($orders && $orders->num_rows > 0): ?>
                        <?php while ($order = $orders->fetch_assoc()): ?>
                            <tr>
                                <td>#<?php echo $order['order_id']; ?></td>
                                <td><?php echo htmlspecialchars($order['full_name']); ?></td>
                                <td>Rs. <?php echo number_format((float)$order['total_amount'], 2); ?></td>
                                <td>
                                    <span class="status-badge status-<?php echo htmlspecialchars($order['status']); ?>">
                                        <?php echo htmlspecialchars($order['status']); ?>
                                    </span>
                                </td>
                                <td><?php echo htmlspecialchars($order['created_at']); ?></td>
                                <td>
                                    <?php if ($order['status'] === 'pending'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="new_status" value="preparing">
                                            <button type="submit" name="update_order_status" class="chef-btn">Mark Preparing</button>
                                        </form>
                                    <?php elseif ($order['status'] === 'preparing'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo $order['order_id']; ?>">
                                            <input type="hidden" name="new_status" value="ready">
                                            <button type="submit" name="update_order_status" class="chef-btn">Mark Ready</button>
                                        </form>
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

        </div>
    </div>
</div>

</body>
</html>