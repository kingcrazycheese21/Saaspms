<?php
// session_config.php
// Custom session handling for PMS Rebuild

// Create sessionstorage directory if it doesn't exist
$sessionDir = __DIR__ . '/sessionstorage';
if (!file_exists($sessionDir)) {
    mkdir($sessionDir, 0700, true);
}

// Set custom session save path
session_save_path($sessionDir);

// Set session cookie parameters for security
session_set_cookie_params([
    'lifetime' => 86400, // 24 hours
    'path' => '/',
    'domain' => $_SERVER['HTTP_HOST'] ?? 'localhost',
    'secure' => isset($_SERVER['HTTPS']), // Auto-detect HTTPS
    'httponly' => true,
    'samesite' => 'Strict'
]);

// Start session with custom name
session_name('PMS_SESSION');
session_start();

// Regenerate session ID periodically for security
if (!isset($_SESSION['last_regeneration'])) {
    $_SESSION['last_regeneration'] = time();
} elseif (time() - $_SESSION['last_regeneration'] > 1800) { // 30 minutes
    session_regenerate_id(true);
    $_SESSION['last_regeneration'] = time();
}
?>