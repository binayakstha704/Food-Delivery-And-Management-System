<<<<<<< HEAD
<?php include('db.php'); ?>
<!DOCTYPE html>
<html lang="en">
<head>
    <link rel="stylesheet" href="style.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
    
    <title>Swaad Unlimited - Admin</title>
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
            
            <span>Admin</span>
        </div>

        <a href="logout.php" class="logout-btn">
            <i class="fas fa-sign-out-alt"></i>
        </a>
    </div>
</div>
        <div class="header">
            <h2>Dashboard Overview</h2>
            <div class="admin-profile">Admin Profile</div>
        </div>

        <div class="card" style="background: white; padding: 20px; border-radius: 15px; width: 200px;">
            <p>Menu Items</p>
            <h1>7</h1>
        </div>

        <h3>Recent Orders</h3>
        <table>
            <thead>
                <tr>
                    <th>Order ID</th>
                    <th>Customer</th>
                    <th>Items</th>
                    <th>Status</th>
                </tr>
            </thead>
            <tbody>
                <tr>
                    <td>#1234</td>
                    <td>Ram Sharma</td>
                    <td>Dal Bhat, Momo</td>
                    <td><span class="badge available">Delivered</span></td>
                </tr>
            </tbody>
        </table>
    </div>
</body>
</html>
=======
<?php
include "db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];

    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    // CHECK DUPLICATE
    $check = $conn->prepare("SELECT id FROM users WHERE username=? OR email=?");
    $check->bind_param("ss", $username, $email);
    $check->execute();
    $check->store_result();

    if ($check->num_rows > 0) {
        $message = "⚠️ Username or Email already exists!";
    } else {

        $stmt = $conn->prepare("INSERT INTO users (firstname, lastname, username, email, password) VALUES (?, ?, ?, ?, ?)");
        $stmt->bind_param("sssss", $firstname, $lastname, $username, $email, $hashed_password);

        if ($stmt->execute()) {
            $message = "✅ Registration Successful!";
        } else {
            $message = "❌ Error: " . $stmt->error;
        }
    }
}

?>

<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">

<title>User Registration</title>

<!-- Google Fonts -->
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>

<!-- External CSS -->
<link rel="stylesheet" href="style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">

</head>

<body>
    <a href="login.php" class="reg-back-btn">
    <span class="reg-back-arrow">&#8592;</span>
    <span class="reg-back-text">Back</span>
</a>

<div class="container">
    <div class="reg-logo-circle">
    <img src="logo.png.png" alt="Swaad Unlimited Logo">
</div>

    <!-- Branding -->
    <div class="logo">
    <span class="brand">
        <span class="swaad">Swaad</span>
        <span class="unlimited">Unlimited</span>
    </span>
    <small>Delicious Food Will Light Up Your Mood</small>
</div>

    <!-- Card -->
    <div class="card">
        <h2>Create Account</h2>
         <?php if($message != ""): ?>
    <div id="reg-popup" class="reg-popup-overlay">
        <div class="reg-popup-box <?php echo (strpos($message, 'Successful') !== false) ? 'reg-popup-success' : 'reg-popup-error'; ?>">
            <div class="reg-popup-icon">
                <?php echo (strpos($message, 'Successful') !== false) ? '' : ''; ?>
            </div>
            <div class="reg-popup-msg"><?php echo $message; ?></div>
        </div>
    </div>

    <script>
        const popup = document.getElementById('reg-popup');
        if (popup) {
            setTimeout(() => {
                popup.classList.add('reg-popup-hide');
                setTimeout(() => popup.remove(), 300);
            }, 1500);
        }
    </script>

<?php endif; ?>

        <form method="POST">

            <div class="input-group">
                <label>First Name</label>
                <input type="text" name="firstname" required>
            </div>

            <div class="input-group">
                <label>Last Name</label>
                <input type="text" name="lastname" required>
            </div>

            <div class="input-group">
                <label>Unique Username</label>
                <input type="text" name="username" required>
            </div>

            <div class="input-group">
                <label>Email Address</label>
                <input type="email" name="email" required>
            </div>

            <div class="input-group">
                <label>Password</label>
                <input type="password" name="password" required>
            </div>

            <button type="submit">Sign Up</button>

        </form>

    </div>

</div>

</body>
</html>
>>>>>>> 735e22a (Updating user page)
