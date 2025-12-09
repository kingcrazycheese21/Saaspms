<?php
// init.php - Session and application initialization

// Create session directory if it doesn't exist
$sessionDir = __DIR__ . '/sessionstorage';
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}

// Set session configuration BEFORE starting session
session_save_path($sessionDir);
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'secure' => isset($_SERVER['HTTPS']),
    'httponly' => true,
    'samesite' => 'Strict'
]);
session_name('PMS_SESSION');

// Start the session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Include database config
require_once 'config.php';

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}

// Authentication check function
function requireLogin() {
    if (!isset($_SESSION['loggedin']) || $_SESSION['loggedin'] !== true) {
        // Store current URL for redirect back after login
        $_SESSION['redirect_url'] = $_SERVER['REQUEST_URI'];
        header("Location: login.php");
        exit();
    }
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['loggedin']) && $_SESSION['loggedin'] === true;
}

// Get current user info
function getCurrentUser() {
    if (isLoggedIn()) {
        return [
            'id' => $_SESSION['user_id'] ?? null,
            'email' => $_SESSION['email'] ?? null,
            'name' => $_SESSION['name'] ?? null,
            'role' => $_SESSION['role'] ?? null
        ];
    }
    return null;
}
?>