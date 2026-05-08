<?php
require_once "../includes/auth.php";
configure_secure_session();
session_start();
logout_user();
header("Location: portal-login.php");
exit();
?>
