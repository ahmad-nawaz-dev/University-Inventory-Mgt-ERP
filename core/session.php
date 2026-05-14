<?php
// core/session.php
session_start();

function isLoggedIn() {
    return isset($_SESSION['user']) && !empty($_SESSION['user']);
}

function currentUser() {
    return $_SESSION['user'] ?? null;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: " . BASE_URL . "/login.php");
        exit();
    }
}
?>