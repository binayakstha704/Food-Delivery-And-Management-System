<?php
require_once "../includes/auth.php";
start_session();
session_security_check();
require_once "../config/db.php";
require_once "../includes/functions.php";

// ── Log the logout event BEFORE destroying the session ────────────────────────
// We capture user_id and name here because session_destroy() wipes them.
// log_user_event() writes to user_logs so chef/staff can see logout history.
$logging_user_id   = isset($_SESSION['user_id'])   ? (int)$_SESSION['user_id']   : null;
$logging_full_name = isset($_SESSION['full_name'])  ? $_SESSION['full_name']       : 'Unknown';
$logging_role      = isset($_SESSION['role'])       ? $_SESSION['role']            : 'unknown';
$logging_ip        = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

log_user_event(
    $conn,
    'logout',
    $logging_ip,
    "User '{$logging_full_name}' ({$logging_role}) logged out",
    $logging_user_id
);

// ── Destroy the session ───────────────────────────────────────────────────────
logout_user();

header("Location: portal-login.php");
exit();
