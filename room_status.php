<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'init.php';

// Update room status if requested
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_room'])) {
    $rid = (int)$_POST['room_id'];
    $status = $_POST['status'] ?? 'available';
    $stmt = $pdo->prepare("UPDATE rooms SET status = ? WHERE id = ? AND hotel_id = ?");
    $stmt->execute([$status, $rid, $hotel_id]);
    header('Location: room_status.php');
    exit;
}

// Fetch rooms
$stmt = $pdo->prepare("SELECT * FROM rooms WHERE hotel_id = ? ORDER BY floor, room_number");
$stmt->execute([$hotel_id]);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Room Status - Hotel PMS</title>
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
.btn{padding:6px 10px;border-radius:4px;text-decoration:none;}
.form-inline{display:flex;gap:8px;align-items:center;}
</style>
</head>
<body>
<div class="sidebar">
    <h2 style="text-align:center;">Hotel PMS</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="housekeeping.php">Housekeeping</a>
    <a href="reservations.php">Reservations</a>
    <a href="billing.php">Billing</a>
    <a href="reports.php">Reports & Charts</a>
    <a href="nightaudit.php">Night Audit</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php" style="background:#990000;margin-top:50px;">Logout</a>
</div>

<div class="content">
    <h1>Room Status</h1>

    <div class="card">
        <table>
            <tr><th>Room</th><th>Type</th><th>Floor</th><th>Price</th><th>Status</th><th>Action</th></tr>
            <?php if (empty($rooms)): ?>
                <tr><td colspan="6" style="text-align:center;">No rooms defined</td></tr>
            <?php else: foreach ($rooms as $r): ?>
                <tr>
                    <td><?= h($r['room_number']) ?></td>
                    <td><?= h($r['room_type']) ?></td>
                    <td><?= h($r['floor']) ?></td>
                    <td>£<?= number_format($r['price_per_night'],2) ?></td>
                    <td><?= h($r['status']) ?></td>
                    <td>
                        <form method="post" class="form-inline">
                            <input type="hidden" name="room_id" value="<?= $r['id'] ?>">
                            <select name="status">
                                <option value="available" <?= $r['status']=='available'?'selected':'' ?>>Available</option>
                                <option value="occupied" <?= $r['status']=='occupied'?'selected':'' ?>>Occupied</option>
                                <option value="cleaning" <?= $r['status']=='cleaning'?'selected':'' ?>>Cleaning</option>
                                <option value="maintenance" <?= $r['status']=='maintenance'?'selected':'' ?>>Maintenance</option>
                            </select>
                            <button type="submit" name="update_room" class="btn">Update</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
</body>
</html>