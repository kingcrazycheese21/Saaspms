
<?php
require_once __DIR__ . '/session_config.php';
include __DIR__ . '/init.php';

if (!isset($_GET['id'])) die("Missing ID");

$id = (int)$_GET['id'];

$stmt = $pdo->prepare("
    SELECT r.*, rm.room_number, rm.room_type
    FROM reservations r
    LEFT JOIN rooms rm ON rm.id = r.room_id
    WHERE r.id = ?
");
$stmt->execute([$id]);
$res = $stmt->fetch();

if (!$res) die("Reservation not found");

function h($s){ return htmlspecialchars($s, ENT_QUOTES); }
?>
<!DOCTYPE html>
<html>
<head>
<title>View Reservation</title>
<style>
body { font-family:Arial; padding:20px; background:#f7f7f7; }
.card { background:white; padding:20px; border-radius:8px; width:450px;
       box-shadow:0 2px 6px rgba(0,0,0,0.1); }
label { font-weight:bold; }
</style>
</head>
<body>

<h1>Reservation #<?= h($res['id']) ?></h1>

<div class="card">
    <p><label>Guest:</label> <?= h($res['guest_name']) ?></p>
    <p><label>Email:</label> <?= h($res['guest_email']) ?></p>
    <p><label>Room:</label> <?= h($res['room_number']) ?> (<?= h($res['room_type']) ?>)</p>
    <p><label>Dates:</label> <?= h($res['check_in']) ?> → <?= h($res['check_out']) ?></p>
    <p><label>Adults:</label> <?= h($res['adults']) ?> &nbsp; <label>Children:</label> <?= h($res['children']) ?></p>
    <p><label>Total:</label> £<?= number_format($res['total_amount'],2) ?></p>
    <p><label>Status:</label> <?= h($res['status']) ?></p>
</div>

<p><br><a href="reservations.php">← Back to Reservations</a></p>

</body>
</html>