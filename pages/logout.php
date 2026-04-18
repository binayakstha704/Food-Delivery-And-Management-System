<?php
session_start();

// Destroy session
session_unset();
session_destroy();

// ✅ Correct redirect
header("Location: portal-login.php"); 
exit();
?>