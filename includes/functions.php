<?php

function clean_input(string $data): string
{
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

function validate_registration_data(string $full_name, string $email, string $password, string $confirm_password): array
{
    $errors = [];

    if ($full_name === '') {
        $errors[] = 'Full name is required.';
    } elseif (mb_strlen($full_name) < 2) {
        $errors[] = 'Full name must be at least 2 characters long.';
    }

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    } elseif (strlen($password) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($confirm_password === '') {
        $errors[] = 'Please confirm your password.';
    } elseif ($password !== $confirm_password) {
        $errors[] = 'Passwords do not match.';
    }

    return $errors;
}

function email_exists(mysqli $conn, string $email): bool
{
    $sql = "SELECT user_id FROM users WHERE email = ? LIMIT 1";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();
    $stmt->store_result();

    $exists = $stmt->num_rows > 0;
    $stmt->close();

    return $exists;
}

function register_user(mysqli $conn, string $full_name, string $email, string $password): bool
{
    $hashed_password = password_hash($password, PASSWORD_DEFAULT);

    $sql = "INSERT INTO users (full_name, email, password, role) VALUES (?, ?, ?, 'customer')";
    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return false;
    }

    $stmt->bind_param("sss", $full_name, $email, $hashed_password);
    $success = $stmt->execute();
    $stmt->close();

    return $success;
}

/* ---------- LOGIN HELPERS ---------- */

function find_user_by_email(mysqli $conn, string $email): ?array
{
    $sql = "SELECT user_id, full_name, email, password, role, is_active
            FROM users
            WHERE email = ?
            LIMIT 1";

    $stmt = $conn->prepare($sql);

    if (!$stmt) {
        return null;
    }

    $stmt->bind_param("s", $email);
    $stmt->execute();

    $result = $stmt->get_result();
    $user = $result->fetch_assoc();

    $stmt->close();

    return $user ?: null;
}

function validate_login_data(string $email, string $password): array
{
    $errors = [];

    if ($email === '') {
        $errors[] = 'Email is required.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Please enter a valid email address.';
    }

    if ($password === '') {
        $errors[] = 'Password is required.';
    }

    return $errors;
}

function login_user(array $user): void
{
    $_SESSION['user_id'] = $user['user_id'];
    $_SESSION['full_name'] = $user['full_name'];
    $_SESSION['email'] = $user['email'];
    $_SESSION['role'] = $user['role'];
}

function redirect_user_by_role(string $role): void
{
    if ($role === 'chef') {
        header("Location: chef-control.php");
        exit;
    }

    if ($role === 'staff') {
        header("Location: staff-control.php");
        exit;
    }

    if ($role === 'customer') {
        header("Location: dashboard.php");
        exit;
    }

    header("Location: user-login.php");
    exit;
}
?>