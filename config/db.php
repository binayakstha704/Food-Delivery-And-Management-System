<?php
// db.php — Herald Canteen shared database connection
// Uses mysqli to match the project's existing binayakdb.php style.
// Place this file at the root: bs123/db.php

$host    = 'localhost';
$db_user = 'root';
$db_pass = '';
$db_name = 'herald_canteen';

$conn = new mysqli($host, $db_user, $db_pass, $db_name);

if ($conn->connect_error) {
    die('<pre style="color:red;padding:20px;font-size:14px;">
DB CONNECTION FAILED
--------------------
' . $conn->connect_error . '

Fix: Check db.php — make sure db_user, db_pass are correct
and the database "herald_canteen" exists in phpMyAdmin.
</pre>');
}

$conn->set_charset('utf8mb4');