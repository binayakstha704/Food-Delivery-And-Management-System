<?php
// session_mock.php — Herald Canteen Sprint 1 Demo Helper
// Injects a mock logged-in session so pages work without a real login.
// REMOVE THIS FILE (and its includes) before Sprint 2 integration.

if (empty($_SESSION['user_id'])) {
    $_SESSION['user_id'] = 2;              // Sangam Rijal (student in seed data)
    $_SESSION['name']    = 'Sangam Rijal';
    $_SESSION['email']   = 'sangam@heraldcollege.edu.np';
    $_SESSION['role']    = 'student';
}

if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}
