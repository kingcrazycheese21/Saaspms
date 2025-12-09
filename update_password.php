<?php
// update_password.php - Update a specific user's password
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

function hashPassword($password) {
    $salt = "hotel_pms_salt_2024";
    return hash('sha256', $password . $salt);
}

// Set these values for the user you want to update
$email = "admin@hotel.com"; // Change to the user's email
$new_password = "admin123"; // Change to the desired password

$hashedPassword = hashPassword($new_password);

$stmt = $conn->prepare("UPDATE staff SET password_hash = ? WHERE email = ?");
$stmt->bind_param("ss", $hashedPassword, $email);

if ($stmt->execute()) {
    echo "Password updated for $email to: $new_password<br>";
    echo "Hashed value: $hashedPassword<br>";
} else {
    echo "Error: " . $conn->error;
}

$stmt->close();
$conn->close();
?>