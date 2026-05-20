<?php
// chef-kitchen-tickets.php — Redirects to the unified Chef Dashboard
// Kitchen ticket management has been merged into chef-control.php
require_once "../includes/auth.php";
start_session();
session_security_check();
require_role('chef');

header('Location: chef-control.php');
exit;
