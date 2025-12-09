<?php
require_once __DIR__ . '/init.php';
if (!isset($_SESSION['user_id'])) { http_response_code(403); echo json_encode(['error'=>'login']); exit; }
$hotel_id = $_SESSION['hotel_id'] ?? 1;
$stmt = $pdo->prepare("SELECT id, room_number, room_type, status FROM rooms WHERE hotel_id = ? ORDER BY room_number");
$stmt->execute([$hotel_id]);
$rooms = $stmt->fetchAll(PDO::FETCH_ASSOC);
header('Content-Type: application/json');
echo json_encode($rooms);
