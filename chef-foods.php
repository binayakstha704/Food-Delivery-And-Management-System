<?php 
session_start();
include('db1.php');

// --- CHEF ACCESS ONLY ---
if (!isset($_SESSION['role']) || $_SESSION['role'] !== 'chef') {
    header("Location: login.php");
    exit();
}

// --- 1. DELETE LOGIC ---
if(isset($_GET['delete_id'])) {
    $id = (int)$_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM foods WHERE id = $id");
    header("Location: chef-foods.php");
    exit();
}

// --- 2. ADD FOOD LOGIC ---
if(isset($_POST['add_food'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $price = (float)$_POST['price'];
    $availability = mysqli_real_escape_string($conn, $_POST['availability']);
    mysqli_query($conn, "INSERT INTO foods (name, category, price, availability) VALUES ('$name', '$category', '$price', '$availability')");
    header("Location: chef-foods.php");
    exit();
}

// --- 3. EDIT/UPDATE LOGIC ---
if(isset($_POST['update_food'])) {
    $id = (int)$_POST['food_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $category = mysqli_real_escape_string($conn, $_POST['category']);
    $price = (float)$_POST['price'];
    $availability = mysqli_real_escape_string($conn, $_POST['availability']);
    mysqli_query($conn, "UPDATE foods SET name='$name', category='$category', price='$price', availability='$availability' WHERE id=$id");
    header("Location: chef-foods.php");
    exit();
}

// --- 4. SEARCH LOGIC ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$query = "SELECT * FROM foods WHERE name LIKE '%$search%' OR category LIKE '%$search%' ORDER BY id DESC";
$res = mysqli_query($conn, $query);

// Notification count
$notifCount = mysqli_fetch_row(mysqli_query($conn, "SELECT COUNT(*) FROM orders WHERE status='Pending'"))[0] ?? 0;
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <title>Swaad Unlimited - Manage Menu</title>
    <style>
        .notif-badge { background: #e74c3c; color: white; border-radius: 50%; padding: 1px 6px; font-size: 11px; margin-left: 4px; }
    </style>
</head>
<body>
    <div class="sidebar">
        <div class="logo-section">
            <img src="logo.png.png" alt="Logo">
            <div>
                <span style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--peach);">Swaad</span><span style="font-family:'Playfair Display',serif;font-size:1.2rem;color:var(--bright-orange);">Unlimited</span>
                <small>Chef Panel</small>
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
                <img src="logo.png.png" alt="Logo" class="nav-logo">
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

        <div class="header" style="display:flex;justify-content:space-between;align-items:center;margin-bottom:20px;">
            <h2>Manage Menu</h2>
            <button onclick="openModal('foodModal')" class="add-btn">+ Add New Item</button>
        </div>

        <div class="search-container" style="margin-bottom:25px;">
            <form action="chef-foods.php" method="GET">
                <input type="text" name="search" id="searchInput" placeholder="Search menu items..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       style="width:100%;padding:12px;border-radius:8px;border:1px solid #ddd;">
            </form>
        </div>

        <table>
            <thead>
                <tr>
                    <th>Name</th>
                    <th>Category</th>
                    <th>Price</th>
                    <th>Availability</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if($res && mysqli_num_rows($res) > 0):
                    while($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><strong><?php echo htmlspecialchars($row['name']); ?></strong></td>
                    <td><span class="badge-cat"><?php echo htmlspecialchars($row['category']); ?></span></td>
                    <td>Rs <?php echo htmlspecialchars($row['price']); ?></td>
                    <td>
                        <span class="badge <?php echo strtolower($row['availability']); ?>">
                            <?php echo htmlspecialchars($row['availability']); ?>
                        </span>
                    </td>
                    <td>
                        <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
                            <i class="fas fa-edit"></i>
                        </button>
                        <a href="chef-foods.php?delete_id=<?php echo $row['id']; ?>" 
                           class="delete-btn" 
                           onclick="return confirm('Delete this menu item?')">
                            <i class="fas fa-trash-alt"></i>
                        </a>
                    </td>
                </tr>
                <?php endwhile; else: ?>
                <tr><td colspan="5" class="no-data">No menu items found.</td></tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>

    <!-- ADD MODAL -->
    <div id="foodModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('foodModal')">&times;</span>
            <h3>Add Menu Item</h3>
            <form method="POST" action="chef-foods.php">
                <input type="text" name="name" placeholder="Food Name" required>
                <select name="category">
                    <option>Main Course</option>
                    <option>Appetizer</option>
                    <option>Noodles</option>
                    <option>Dessert</option>
                </select>
                <input type="number" name="price" placeholder="Price" required min="0" step="0.01">
                <select name="availability">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
                <button type="submit" name="add_food" class="save-btn">Save Item</button>
            </form>
        </div>
    </div>

    <!-- EDIT MODAL -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h3>Edit Menu Item</h3>
            <form method="POST" action="chef-foods.php">
                <input type="hidden" name="food_id" id="edit_id">
                <input type="text" name="name" id="edit_name" required>
                <select name="category" id="edit_category">
                    <option>Main Course</option>
                    <option>Appetizer</option>
                    <option>Noodles</option>
                    <option>Dessert</option>
                </select>
                <input type="number" name="price" id="edit_price" required min="0" step="0.01">
                <select name="availability" id="edit_availability">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
                <button type="submit" name="update_food" class="save-btn">Update Item</button>
            </form>
        </div>
    </div>

    <script>
        function openModal(id) { document.getElementById(id).style.display = "flex"; }
        function closeModal(id) { document.getElementById(id).style.display = "none"; }

        function openEditModal(data) {
            document.getElementById('edit_id').value = data.id;
            document.getElementById('edit_name').value = data.name;
            document.getElementById('edit_category').value = data.category;
            document.getElementById('edit_price').value = data.price;
            document.getElementById('edit_availability').value = data.availability;
            openModal('editModal');
        }

        document.getElementById('searchInput').addEventListener('input', function() {
            if(this.value === "") window.location.href = "chef-foods.php";
        });

        function toggleNotifDropdown() {
            const d = document.getElementById('notifDropdown');
            d.style.display = d.style.display === 'none' ? 'block' : 'none';
        }
        document.addEventListener('click', function(e) {
            const icon = document.querySelector('.notification-icon');
            if (icon && !icon.contains(e.target)) {
                document.getElementById('notifDropdown').style.display = 'none';
            }
        });
    </script>
</body>
</html>