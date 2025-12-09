<?php
require_once __DIR__ . '/init.php';
require_once __DIR__ . '/inc_nav.php';
if (!isset($_SESSION['user_id'])) { header('Location: login.php'); exit; }
$id = (int)($_GET['id'] ?? 0);
if ($id) {
    $_SESSION['hotel_id'] = $id;
}
header('Location: dashboard.php');
