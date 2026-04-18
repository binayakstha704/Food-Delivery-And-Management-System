<?php

function require_login(): void
{
    if (!isset($_SESSION['user_id'])) {
        header("Location: portal-login.php");
        exit;
    }
}

function require_role(string $required_role): void
{
    require_login();

    if (!isset($_SESSION['role']) || $_SESSION['role'] !== $required_role) {
        header("Location: portal-login.php");
        exit;
    }
}
?>