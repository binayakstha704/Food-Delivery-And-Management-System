<?php
// db.php — Herald Canteen database connection
// Shared by all pages. Change DB_USER / DB_PASS to match your XAMPP setup.

define('DB_HOST',    'localhost');
define('DB_USER',    'root');   // default XAMPP username
define('DB_PASS',    '');       // default XAMPP password (empty)
define('DB_NAME',    'herald_canteen');
define('DB_CHARSET', 'utf8mb4');

try {
    $pdo = new PDO(
        'mysql:host=' . DB_HOST . ';dbname=' . DB_NAME . ';charset=' . DB_CHARSET,
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]
    );
} catch (PDOException $e) {
    // Show error clearly during development
    die('<pre style="color:red;padding:20px;font-size:14px;">
DB CONNECTION FAILED
--------------------
' . $e->getMessage() . '

Fix: Check db.php — make sure DB_USER, DB_PASS are correct
and the database "herald_canteen" exists in phpMyAdmin.
</pre>');
}
