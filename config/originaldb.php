<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conn = new mysqli('localhost', 'root', '', 'herald_canteen');
    $conn->set_charset("utf8mb4");
} catch (Exception $e) {
    die("DB ERROR: " . $e->getMessage());
}
?>