<?php
// ============================================================
// config/secrets.php — Herald Canteen
// ============================================================
// Store sensitive constants here. Add this file to .gitignore.
// NEVER commit plain-text secrets.
//
// HOW TO GENERATE ROLE_SECRET_HASH:
//   Open a PHP shell (php -a) or a throwaway script and run:
//     echo password_hash('your-chosen-6-digit-code', PASSWORD_DEFAULT);
//   Paste the resulting hash below and delete the throwaway script.
//
// The plain-text secret is distributed to chef/staff by the admin
// out-of-band (e.g. printed card, Signal message). It is NEVER
// stored or logged in plaintext anywhere in this codebase.
// ============================================================

// Replace this placeholder hash before deploying.
// Current hash below is for the default '123456' — CHANGE IT.
// Generated with: password_hash('123456', PASSWORD_BCRYPT, ['cost' => 12])
define('ROLE_SECRET_HASH', '$2y$12$r.oCC43qUmfpYbME.rhXSeyyoco/6T8MiG2JnQM3wVwDbJ0vyWBRm');
