<?php
$host = "localhost";
$user = "root";
$pass = "";
<<<<<<< HEAD
$dbname = "Swaad_Unlimited";

$conn = mysqli_connect($host, $user, $pass, $dbname);

if (!$conn) {
    die("Connection failed: " . mysqli_connect_error());
=======
$db   = "Swaad_Unlimited";

// Create connection
$conn = new mysqli($host, $user, $pass, $db);

// Check connection
if ($conn->connect_error) {
    die("Database Connection Failed: " . $conn->connect_error);
>>>>>>> 735e22a (Updating user page)
}
?>