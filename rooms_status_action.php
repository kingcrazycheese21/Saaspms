<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/inc_nav.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$hotel_id = $_SESSION['hotel_id'] ?? 1;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $room_id = (int)($_POST['room_id'] ?? 0);
    $status = $_POST['status'] ?? '';
    if ($room_id && $status) {
        $stmt = $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ? AND hotel_id = ?");
        $stmt->execute([$status, $room_id, $hotel_id]);
        // log
        try {
            $l = $pdo->prepare("INSERT INTO activity_log (staff_id, action, created_at) VALUES (?,?,NOW())");
            $l->execute([$_SESSION['user_id'], "Updated room {$room_id} status to {$status}"]);
        } catch (Exception $e) {}
    }
}
header('Location: rooms_status.php');
