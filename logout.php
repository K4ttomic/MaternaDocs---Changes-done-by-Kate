<?php
require 'includes/auth.php';
$u = current_user();
if ($u) log_action($u['id'], 'logout');
$staff = $u && $u['role'] !== 'patient';
$_SESSION = []; session_destroy();
header('Location: login.php' . ($staff ? '?portal=staff' : '')); exit;
