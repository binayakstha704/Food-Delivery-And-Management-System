<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "Swaad_Unlimited";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
}
?>
