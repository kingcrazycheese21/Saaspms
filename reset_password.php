<?php
// reset_passwords.php - RUN THIS ONCE THEN DELETE
error_reporting(E_ALL);
ini_set('display_errors', 1);

// Load config
$configPath = __DIR__ . "/config.php";
if (!file_exists($configPath)) {
    die("FATAL: Missing config file at: $configPath");
}

$config = require $configPath;

// Connect to database
$conn = new mysqli(
    $config["db_host"],
    $config["db_user"],
    $config["db_pass"],
    $config["db_name"]
);

if ($conn->connect_error) {
    die("DB CONNECTION FAILED: " . $conn->connect_error);
}

// Custom hashing function (same as in login.php)
function hashPassword($password) {
    $salt = "hotel_pms_salt_2024"; // Must match the one in login.php
    return hash('sha256', $password . $salt);
}

// Reset passwords for common test accounts
$users = [
    ['admin@hotel.com', 'admin123'],
];

foreach ($users as $user) {
    list($email, $password) = $user;
    $hashedPassword = hashPassword($password);
    
    $stmt = $conn->prepare("UPDATE staff SET password_hash = ? WHERE email = ?");
    $stmt->bind_param("ss", $hashedPassword, $email);
    
    if ($stmt->execute()) {
        echo "Password reset for $email to: $password<br>";
    } else {
        echo "Error resetting $email: " . $conn->error . "<br>";
    }
    
    $stmt->close();
}

echo "<br>All passwords have been reset. Please delete this file for security.";

$conn->close();
?>