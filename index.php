<?php
// Root entry point for Apache/IP access.
// Redirect users from http://SERVER_IP/ to the public login/home page.
header('Location: pages/index.php', true, 302);
exit;
