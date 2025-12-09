<?php
require_once __DIR__ . '/session_config.php';
include __DIR__ . '/init.php';

if (!isset($_GET['action'], $_GET['id'])) {
    die("Invalid request");
}

$action = $_GET['action'];
$id = (int)$_GET['id'];

if ($action === "checkin") {
    $stmt = $pdo->prepare("UPDATE reservations SET status='checked-in' WHERE id=?");
    $stmt->execute([$id]);
    header("Location: reservations.php?msg=Guest Checked In");
    exit;

} elseif ($action === "checkout") {
    $stmt = $pdo->prepare("UPDATE reservations SET status='checked-out' WHERE id=?");
    $stmt->execute([$id]);
    header("Location: reservations.php?msg=Guest Checked Out");
    exit;

} else {
    die("Unknown action");
}