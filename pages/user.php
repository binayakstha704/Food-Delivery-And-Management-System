<?php
session_start();
include "../config/db.php";

$message = "";

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $firstname = trim($_POST['firstname']);
    $lastname  = trim($_POST['lastname']);
    $username  = trim($_POST['username']);
    $email     = trim($_POST['email']);
    $password  = $_POST['password'];

    // ✅ VALIDATION
    if (empty($firstname) || empty($lastname) || empty($username) || empty($email) || empty($password)) {
        $message = "All fields are required!";

    } elseif (trim($password) !== $password) {
        $message = "Password must not start or end with spaces!";

    } elseif (strlen(trim($password)) < 8) {
        $message = "Password must be at least 8 characters!";

    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[^@]+@[^@]+\.[a-zA-Z]{2,}$/', $email)) {
        $message = "Please enter a valid email address!";

    } else {

        $hashed_password = password_hash($password, PASSWORD_DEFAULT);

        // ✅ CHECK DUPLICATE
        $check = $conn->prepare("SELECT id FROM customers WHERE username=? OR email=?");
        $check->bind_param("ss", $username, $email);
        $check->execute();
        $check->store_result();

        if ($check->num_rows > 0) {
            $message = "Username or Email already exists!";
        } else {

            // ✅ INSERT USER
            $stmt = $conn->prepare("INSERT INTO customers (firstname, lastname, username, email, password, role) VALUES (?, ?, ?, ?, ?, 'user')");
            $stmt->bind_param("sssss", $firstname, $lastname, $username, $email, $hashed_password);

            if ($stmt->execute()) {
                header("Location: login.php?success=1");
                exit();
            } else {
                $message = "Error: " . $stmt->error;
            }
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
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:wght@600;700&family=Poppins:wght@300;400;500;600&display=swap" rel="stylesheet"/>
<link rel="stylesheet" href="../assests/style.css">
<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
</head>

<body>
    <a href="login.php" class="reg-back-btn">
        <span class="reg-back-arrow">&#8592;</span>
        <span class="reg-back-text">Back</span>
    </a>

    <div class="container">
        <div class="reg-logo-circle">
            <img src="../logo.png.png" alt="Swaad Unlimited Logo">
        </div>

        <div class="logo">
            <span class="brand">
                <span class="swaad">Swaad</span>
                <span class="unlimited">Unlimited</span>
            </span>
            <small>Delicious Food Will Light Up Your Mood</small>
        </div>

        <div class="card">
            <h2>Create Account</h2>

            <?php if($message != ""): ?>
            <div id="reg-popup" class="reg-popup-overlay">
                <div class="reg-popup-box <?php echo (strpos($message, 'Successful') !== false) ? 'reg-popup-success' : 'reg-popup-error'; ?>">
                    <div class="reg-popup-icon"></div>
                    <div class="reg-popup-msg"><?php echo htmlspecialchars($message); ?></div>
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