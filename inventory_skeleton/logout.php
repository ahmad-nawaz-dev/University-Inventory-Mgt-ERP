<?php
// logout.php
require_once __DIR__ . '/core/config.php';
require_once __DIR__ . '/core/session.php';

session_unset();
session_destroy();

header("Location: " . BASE_URL . "/login.php");
exit();
?>