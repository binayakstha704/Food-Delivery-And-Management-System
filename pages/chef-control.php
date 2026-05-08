<?php
require_once "../includes/auth.php";
configure_secure_session();
session_start();
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
$errors = []; // Now associative array for inline errors

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
    
    // Validate and sanitize category name
    $category_name = validate_text($_POST['category_name'] ?? '', 'category name', 100, $errors, true);
    
    // Validate and sanitize description (optional, max 500 chars)
    $category_description = validate_text($_POST['category_description'] ?? '', 'description', 500, $errors, false);
    if ($category_description === false) {
        $category_description = ''; // Optional field, so empty if validation fails
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
    
    // Validate and sanitize category name
    $category_name = validate_text($_POST['category_name'] ?? '', 'category name', 100, $errors, true);
    
    // Validate and sanitize description
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
            $_SESSION["_toast"] = ["text" => "Category updated successfully.", "type" => "success"];
            $edit_category = null;
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
            header("Location: chef-control.php#manage-section");
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
    $item_id = filter_var($_POST['item_id'] ?? 0, FILTER_VALIDATE_INT);

    if ($item_id > 0) {
        $stmt = $conn->prepare("DELETE FROM menu_items WHERE item_id = ?");
        $stmt->bind_param("i", $item_id);

        if ($stmt->execute()) {
            $_SESSION["_toast"] = ["text" => "Menu item deleted successfully.", "type" => "danger"];
        header("Location: chef-control.php#manage-section");
        exit;
        } else {
            $errors['general'] = "Failed to delete menu item.";
        }

        $stmt->close();
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
            header("Location: chef-control.php#manage-section");
            exit;
            $edit_item = null;
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
                    $_SESSION["_toast"] = ["text" => "Order status updated successfully.", "type" => "success"];
                    header("Location: chef-control.php#orders-section");
                    exit;
                } else {
                    $errors['general'] = "Failed to update order status.";
                }

                $stmt->close();
            } else {
                $errors['general'] = "Invalid status transition for chef.";
            }
        } else {
            $errors['general'] = "Order not found.";
        }
    } else {
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
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Chef Control Panel</title>
    <link rel="stylesheet" href="../assets/css/style.css">


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
            <div class="topbar-welcome">
                Welcome, <?php echo htmlspecialchars($_SESSION['full_name'], ENT_QUOTES, 'UTF-8'); ?>
            </div>
        </div>

        <div class="content">

            <div class="section-title">
                <h2>Chef Control Panel</h2>
                <p>Manage categories, menu items, and kitchen order progress.</p>
            </div>

            <?php /* Messages now shown as toast notifications */ ?>

            <?php if (isset($errors['general'])): ?>
                <div class="alert-error"><?php echo htmlspecialchars($errors['general'], ENT_QUOTES, 'UTF-8'); ?></div>
            <?php endif; ?>

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

                                        <form method="POST" onsubmit="return confirm('Delete this item?');">
                                            <input type="hidden" name="item_id" value="<?php echo htmlspecialchars($item['item_id'], ENT_QUOTES, 'UTF-8'); ?>">
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
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'], ENT_QUOTES, 'UTF-8'); ?>">
                                            <input type="hidden" name="new_status" value="preparing">
                                            <button type="submit" name="update_order_status" class="chef-btn">Mark Preparing</button>
                                        </form>
                                    <?php elseif ($order['status'] === 'preparing'): ?>
                                        <form method="POST">
                                            <input type="hidden" name="order_id" value="<?php echo htmlspecialchars($order['order_id'], ENT_QUOTES, 'UTF-8'); ?>">
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

<!-- Chef Toast Notification -->
<?php if ($chef_toast): ?>
<div class="toast chef-toast <?php echo $chef_toast['type'] === 'danger' ? 'toast-danger' : ''; ?>" id="chefToast">
    <div class="toast-icon">
        <?php if ($chef_toast['type'] === 'danger'): ?>
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
            <circle cx="9" cy="9" r="9" fill="#e53935"/>
            <path d="M6 6L12 12M12 6L6 12" stroke="white" stroke-width="2" stroke-linecap="round"/>
        </svg>
        <?php else: ?>
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" xmlns="http://www.w3.org/2000/svg">
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

</body>
</html>