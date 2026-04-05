<?php 
include('db.php'); 

// --- 1. DELETE LOGIC ---
if(isset($_GET['delete_id'])) {
    $id = $_GET['delete_id'];
    mysqli_query($conn, "DELETE FROM foods WHERE id = $id");
    header("Location: manage-foods.php");
}

// --- 2. ADD FOOD LOGIC ---
if(isset($_POST['add_food'])) {
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $category = $_POST['category'];
    $price = $_POST['price'];
    $availability = $_POST['availability'];
    mysqli_query($conn, "INSERT INTO foods (name, category, price, availability) VALUES ('$name', '$category', '$price', '$availability')");
    header("Location: manage-foods.php");
}

// --- 3. EDIT/UPDATE LOGIC ---
if(isset($_POST['update_food'])) {
    $id = $_POST['food_id'];
    $name = mysqli_real_escape_string($conn, $_POST['name']);
    $category = $_POST['category'];
    $price = $_POST['price'];
    $availability = $_POST['availability'];
    mysqli_query($conn, "UPDATE foods SET name='$name', category='$category', price='$price', availability='$availability' WHERE id=$id");
    header("Location: manage-foods.php");
}

// --- 4. SEARCH LOGIC ---
$search = isset($_GET['search']) ? mysqli_real_escape_string($conn, $_GET['search']) : '';
$query = "SELECT * FROM foods WHERE name LIKE '%$search%' OR category LIKE '%$search%' ORDER BY id DESC";
$res = mysqli_query($conn, $query);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <link rel="stylesheet" href="style1.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    <title>Swaad Unlimited - Manage Foods</title>
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
            <i class="far fa-user"></i>
            <span>Admin</span>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
        <div class="header" style="display:flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2>Manage Foods</h2>
            <button onclick="openModal('foodModal')" class="add-btn">+ Add New Food</button>
        </div>

        <div class="search-container" style="margin-bottom: 25px;">
            <form id="searchForm" action="manage-foods.php" method="GET">
                <input type="text" name="search" id="searchInput" placeholder="Search foods..." 
                       value="<?php echo htmlspecialchars($search); ?>" 
                       style="width: 100%; padding: 12px; border-radius: 8px; border: 1px solid #ddd;">
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
                <?php while($row = mysqli_fetch_assoc($res)): ?>
                <tr>
                    <td><strong><?php echo $row['name']; ?></strong></td>
                    <td><span class="badge-cat"><?php echo $row['category']; ?></span></td>
                    <td>Rs <?php echo $row['price']; ?></td>
                    <td>
                        <span class="badge <?php echo strtolower($row['availability']); ?>">
                            <?php echo $row['availability']; ?>
                        </span>
                    </td>
                   <td>
    <button class="edit-btn" onclick="openEditModal(<?php echo htmlspecialchars(json_encode($row)); ?>)">
        <i class="fas fa-edit"></i>
    </button>

    <a href="manage-foods.php?delete_id=<?php echo $row['id']; ?>" 
       class="delete-btn" 
       onclick="return confirm('Delete this item?')">
        <i class="fas fa-trash-alt"></i>
    </a>
</td>
                </tr>
                <?php endwhile; ?>
            </tbody>
        </table>
    </div>

    <div id="foodModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('foodModal')">&times;</span>
            <h3>Add Food</h3>
            <form method="POST">
                <input type="text" name="name" placeholder="Food Name" required>
                <select name="category">
                    <option>Main Course</option><option>Appetizer</option><option>Noodles</option><option>Dessert</option>
                </select>
                <input type="number" name="price" placeholder="Price" required>
                <select name="availability">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
                <button type="submit" name="add_food" class="save-btn">Save Food</button>
            </form>
        </div>
    </div>

    <div id="editModal" class="modal">
        <div class="modal-content">
            <span class="close" onclick="closeModal('editModal')">&times;</span>
            <h3>Edit Food</h3>
            <form method="POST">
                <input type="hidden" name="food_id" id="edit_id">
                <input type="text" name="name" id="edit_name" required>
                <select name="category" id="edit_category">
                    <option>Main Course</option><option>Appetizer</option><option>Noodles</option><option>Dessert</option>
                </select>
                <input type="number" name="price" id="edit_price" required>
                <select name="availability" id="edit_availability">
                    <option value="Available">Available</option>
                    <option value="Unavailable">Unavailable</option>
                </select>
                <button type="submit" name="update_food" class="save-btn">Update Food</button>
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

        // Search logic: if backspaced to empty, reload page
        document.getElementById('searchInput').addEventListener('input', function() {
            if(this.value === "") {
                window.location.href = "manage-foods.php";
            }
        });
    </script>
</body>
</html>