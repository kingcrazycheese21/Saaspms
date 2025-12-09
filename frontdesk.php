<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'init.php';

// Arrivals today (confirmed or checked-in)
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE hotel_id = ? AND check_in = ? AND status IN ('confirmed','checked-in') ORDER BY room_number");
$stmt->execute([$hotel_id, $today]);
$arrivals = $stmt->fetchAll();

// Departures today (checked-in)
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE hotel_id = ? AND check_out = ? AND status = 'checked-in' ORDER BY room_number");
$stmt->execute([$hotel_id, $today]);
$departures = $stmt->fetchAll();

// In-house (checked-in)
$stmt = $pdo->prepare("SELECT * FROM reservations WHERE hotel_id = ? AND status = 'checked-in' ORDER BY room_number");
$stmt->execute([$hotel_id]);
$inhouse = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Front Desk - Hotel PMS</title>
<style>
body { margin:0; font-family: Arial, sans-serif; background:#f0f2f5; }
.sidebar { width:230px; background:#002147; color:white; position:fixed; top:0; bottom:0; padding-top:20px; }
.sidebar a { display:block; padding:12px 20px; color:white; text-decoration:none; }
.sidebar a:hover { background:#003366; }
.content { margin-left:230px; padding:25px; }
.card { background:white; padding:15px; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,0.08); margin-bottom:20px; }
table { width:100%; border-collapse:collapse; background:white; border-radius:6px; overflow:hidden; margin-bottom:10px; }
th, td { padding:10px; border-bottom:1px solid #eee; text-align:left; }
th { background:#fafafa; }
.btn { padding:6px 10px; border-radius:4px; text-decoration:none; margin-right:6px; }
.checkin { background:#28a745; color:white; }
.checkout { background:#ff9900; color:white; }
.folio { background:#003366; color:white; }
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
    <a href="logout.php" style="background:#990000; margin-top:50px;">Logout</a>
</div>

<div class="content">
    <h1>Front Desk</h1>

    <div class="card">
        <h2>Arrivals Today (<?= count($arrivals) ?>)</h2>
        <table>
            <tr><th>Guest</th><th>Room</th><th>Check-in</th><th>Actions</th></tr>
            <?php if (empty($arrivals)): ?>
                <tr><td colspan="4" style="text-align:center;">No arrivals today</td></tr>
            <?php else: foreach ($arrivals as $a): ?>
                <tr>
                    <td><?= h($a['guest_name']) ?><br><small><?= h($a['guest_email']) ?></small></td>
                    <td><?= h($a['room_number']) ?></td>
                    <td><?= h($a['check_in']) ?></td>
                    <td>
                        <?php if ($a['status'] === 'confirmed'): ?>
                            <a class="btn checkin" href="reservations.php?checkin=<?= $a['id'] ?>">Check-in</a>
                        <?php else: echo '<span class="btn" style="background:#ccc;">Checked-in</span>'; endif; ?>
                        <a class="btn folio" href="billing.php?reservation=<?= $a['id'] ?>">Folio</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <div class="card">
        <h2>Departures Today (<?= count($departures) ?>)</h2>
        <table>
            <tr><th>Guest</th><th>Room</th><th>Check-out</th><th>Actions</th></tr>
            <?php if (empty($departures)): ?>
                <tr><td colspan="4" style="text-align:center;">No departures today</td></tr>
            <?php else: foreach ($departures as $d): ?>
                <tr>
                    <td><?= h($d['guest_name']) ?></td>
                    <td><?= h($d['room_number']) ?></td>
                    <td><?= h($d['check_out']) ?></td>
                    <td>
                        <a class="btn checkout" href="reservations.php?checkout=<?= $d['id'] ?>">Check-out</a>
                        <a class="btn folio" href="billing.php?reservation=<?= $d['id'] ?>">Folio</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

    <div class="card">
        <h2>In-House Guests (<?= count($inhouse) ?>)</h2>
        <table>
            <tr><th>Guest</th><th>Room</th><th>Checked In</th><th>Actions</th></tr>
            <?php if (empty($inhouse)): ?>
                <tr><td colspan="4" style="text-align:center;">No in-house guests</td></tr>
            <?php else: foreach ($inhouse as $i): ?>
                <tr>
                    <td><?= h($i['guest_name']) ?></td>
                    <td><?= h($i['room_number']) ?></td>
                    <td><?= h($i['check_in']) ?></td>
                    <td>
                        <a class="btn folio" href="billing.php?reservation=<?= $i['id'] ?>">Post Charge</a>
                        <a class="btn checkout" href="reservations.php?checkout=<?= $i['id'] ?>">Check-out</a>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>

</div>
</body>
</html>