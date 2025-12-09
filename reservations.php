<?php
// reservations.php
require_once __DIR__ . '/session_config.php';

// Ensure PDO exists
if (!isset($pdo)) {
    include __DIR__ . '/init.php';
}

if (!isset($hotel_id)) {
    $hotel_id = $_SESSION['hotel_id'] ?? 1;
}

$msg = isset($_GET['msg']) ? strip_tags($_GET['msg']) : "";

// Fetch reservations
$stmt = $pdo->prepare("
    SELECT r.*, rm.room_number, rm.room_type
    FROM reservations r
    LEFT JOIN rooms rm ON rm.id = r.room_id
    WHERE r.hotel_id = ?
    ORDER BY r.check_in DESC
");
$stmt->execute([$hotel_id]);
$reservations = $stmt->fetchAll();

// ❌ REMOVED duplicate function h()
// init.php already contains: function h($str){ ... }

?>
<!DOCTYPE html>
<html>
<head>
    <title>Reservations</title>
    <link rel="stylesheet" href="styles.css">
    <style>
        body { font-family: Arial; background:#f7f7f7; padding:20px; }
        .card { background:white; padding:20px; border-radius:8px; box-shadow:0 2px 6px rgba(0,0,0,0.1); }
        table { width:100%; border-collapse:collapse; }
        th, td { padding:10px; border-bottom:1px solid #eee; }
        th { background:#fafafa; }
        .btn { padding:6px 10px; text-decoration:none; border-radius:4px; margin-right:4px; display:inline-block; }
        .btn-view { background:#dce9ff; }
        .btn-checkin { background:#d9f5d9; }
        .btn-checkout { background:#ffdcdc; }
        .status { padding:4px 8px; border-radius:4px; font-size:12px; }
        .status-confirmed { background:#e6f0ff; }
        .status-checkedin { background:#e3f9e5; }
        .status-checkedout { background:#f1f1f1; }
    </style>
</head>
<body>

<h1>Reservations</h1>

<?php if ($msg): ?>
<div style="background:#eaf8d9; padding:10px; border-left:4px solid #4CAF50; margin-bottom:15px;">
    <?= h($msg) ?>
</div>
<?php endif; ?>

<div class="card">
<table>
    <thead>
        <tr>
            <th>#</th>
            <th>Guest</th>
            <th>Room</th>
            <th>Dates</th>
            <th>Adults/Children</th>
            <th>Total</th>
            <th>Status</th>
            <th>Actions</th>
        </tr>
    </thead>

    <tbody>
        <?php if (!$reservations): ?>
            <tr><td colspan="8" style="text-align:center;padding:20px;">No reservations found.</td></tr>
        <?php else: foreach ($reservations as $r): ?>
        <tr>
            <td><?= h($r['id']) ?></td>
            <td><?= h($r['guest_name']) ?><br><small><?= h($r['guest_email']) ?></small></td>
            <td><?= h($r['room_number']) ?><br><small><?= h($r['room_type']) ?></small></td>
            <td><?= h($r['check_in']) ?> → <?= h($r['check_out']) ?></td>
            <td><?= (int)$r['adults'] ?>/<?= (int)$r['children'] ?></td>
            <td>£<?= number_format($r['total_amount'],2) ?></td>

            <?php
            $status = $r['status'];
            $cls = $status == "checked-in" ? "status-checkedin" :
                   ($status == "checked-out" ? "status-checkedout" : "status-confirmed");
            ?>

            <td><span class="status <?= $cls ?>"><?= h($status) ?></span></td>

            <td>
                <a class="btn btn-view" href="reservation_view.php?id=<?= $r['id'] ?>">View</a>

                <?php if ($status === "confirmed" || $status === "reserved"): ?>
                    <a class="btn btn-checkin"
                       href="reservation_action.php?action=checkin&id=<?= $r['id'] ?>"
                       onclick="return confirm('Check the guest in?');">
                       Check In
                    </a>
                <?php endif; ?>

                <?php if ($status === "checked-in"): ?>
                    <a class="btn btn-checkout"
                       href="reservation_action.php?action=checkout&id=<?= $r['id'] ?>"
                       onclick="return confirm('Check the guest out?');">
                       Check Out
                    </a>
                <?php endif; ?>
            </td>
        </tr>
        <?php endforeach; endif; ?>
    </tbody>
</table>
</div>

</body>
</html>