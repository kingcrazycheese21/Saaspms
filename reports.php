<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'init.php';

$from = $_GET['from'] ?? date('Y-m-01');
$to = $_GET['to'] ?? date('Y-m-t');

// Occupancy
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE hotel_id = ?");
$stmt->execute([$hotel_id]); $totalRooms = (int)$stmt->fetchColumn();
$stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE hotel_id = ? AND status = 'occupied'"); $stmt->execute([$hotel_id]); $occupied = (int)$stmt->fetchColumn();
$occupancyRate = $totalRooms ? round($occupied / $totalRooms * 100,2) : 0;

// Revenue
$stmt = $pdo->prepare("SELECT COALESCE(SUM(total_amount),0) FROM billing WHERE created_at BETWEEN ? AND ? AND reservation_id IN (SELECT id FROM reservations WHERE hotel_id = ?)");
$stmt->execute([$from.' 00:00:00', $to.' 23:59:59', $hotel_id]); $revenue = (float)$stmt->fetchColumn();

// Housekeeping summary
$stmt = $pdo->prepare("SELECT status, COUNT(*) AS c FROM housekeeping WHERE hotel_id = ? GROUP BY status");
$stmt->execute([$hotel_id]); $hk = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Reports - Hotel PMS</title>
<style>
body{margin:0;font-family:Arial, sans-serif;background:#f0f2f5;}
.sidebar{width:230px;background:#002147;color:white;position:fixed;top:0;bottom:0;padding-top:20px;}
.sidebar a{display:block;padding:12px 20px;color:white;text-decoration:none;}
.sidebar a:hover{background:#003366;}
.content{margin-left:230px;padding:25px;}
.card{background:white;padding:15px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,0.08);margin-bottom:20px;}
table{width:100%;border-collapse:collapse;background:white;border-radius:6px;overflow:hidden;margin-bottom:10px;}
th,td{padding:10px;border-bottom:1px solid #eee;text-align:left;}
th{background:#fafafa;}
</style>
</head>
<body>
<div class="sidebar">
    <h2 style="text-align:center;">Hotel PMS</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="housekeeping.php">Housekeeping</a>
    <a href="reservations.php">Reservations</a>
    <a href="billing.php">Billing</a>
    <a href="reports.php" style="background:#003366;">Reports & Charts</a>
    <a href="nightaudit.php">Night Audit</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php" style="background:#990000;margin-top:50px;">Logout</a>
</div>
<div class="content">
    <h1>Reports</h1>

    <div class="card">
        <h3>Occupancy</h3>
        <p>Total Rooms: <?= $totalRooms ?> — Occupied: <?= $occupied ?> — Rate: <?= $occupancyRate ?>%</p>
    </div>

    <div class="card">
        <h3>Revenue</h3>
        <form method="get">
            From <input type="date" name="from" value="<?= h($from) ?>">
            To <input type="date" name="to" value="<?= h($to) ?>">
            <button type="submit">Run</button>
        </form>
        <p>Total revenue: £<?= number_format($revenue,2) ?></p>
    </div>

    <div class="card">
        <h3>Housekeeping Summary</h3>
        <table>
            <tr><th>Status</th><th>Count</th></tr>
            <?php foreach ($hk as $h): ?><tr><td><?= h($h['status']) ?></td><td><?= (int)$h['c'] ?></td></tr><?php endforeach; ?>
        </table>
    </div>
</div>
</body>
</html>