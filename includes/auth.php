<?php
session_start();

define('AUTH_USER', 'admin');
define('AUTH_PASS', 'admin'); // ← cambia questa password

function isLoggedIn() {
    return isset($_SESSION['signage_logged_in']) && $_SESSION['signage_logged_in'] === true;
}

function requireLogin() {
    if (!isLoggedIn()) {
        header('Location: /login.php');
        exit;
    }
}