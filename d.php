<?php
require_once 'session_config.php';

// Load config array (DB credentials)
$config = require 'config.php';

// Create PDO connection (clean + correct)
try {
    $pdo = new PDO(
        "mysql:host={$config['db_host']};dbname={$config['db_name']};charset={$config['db_charset']}",
        $config['db_user'],
        $config['db_pass'],
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    die("<strong>Database connection error</strong><br>" . $e->getMessage());
}

// -----------------------------
// Dashboard Variables
// -----------------------------
$hotel_id = isset($_SESSION['hotel_id']) ? (int)$_SESSION['hotel_id'] : 1;
$today = date('Y-m-d');

// -----------------------------
// KPI Queries
// -----------------------------
try {
    // Total rooms
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE hotel_id = ?");
    $stmt->execute([$hotel_id]);
    $total_rooms = (int)$stmt->fetchColumn();

    // Occupied rooms
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE hotel_id = ? AND status = 'checked-in'
    ");
    $stmt->execute([$hotel_id]);
    $occupied_rooms = (int)$stmt->fetchColumn();

    // Vacant clean rooms
    $stmt = $pdo->prepare("
        SELECT COUNT(*) 
        FROM rooms r
        LEFT JOIN (
            SELECT room_id FROM housekeeping
            WHERE hotel_id = ? AND status != 'completed'
            GROUP BY room_id
        ) h ON h.room_id = r.id
        WHERE r.hotel_id = ? AND h.room_id IS NULL
    ");
    $stmt->execute([$hotel_id, $hotel_id]);
    $vacant_clean_rooms = (int)$stmt->fetchColumn();

    // Rooms needing cleaning
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM housekeeping
        WHERE hotel_id = ? AND status != 'completed'
    ");
    $stmt->execute([$hotel_id]);
    $rooms_need_cleaning = (int)$stmt->fetchColumn();

    // Arrivals today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE hotel_id = ? AND check_in = ? AND status = 'confirmed'
    ");
    $stmt->execute([$hotel_id, $today]);
    $arrivals_today = (int)$stmt->fetchColumn();

    // Departures today
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE hotel_id = ? AND check_out = ? AND status = 'checked-in'
    ");
    $stmt->execute([$hotel_id, $today]);
    $departures_today = (int)$stmt->fetchColumn();

    // In-house guests
    $stmt = $pdo->prepare("
        SELECT COUNT(*) FROM reservations
        WHERE hotel_id = ? AND status = 'checked-in'
    ");
    $stmt->execute([$hotel_id]);
    $inhouse_guests = (int)$stmt->fetchColumn();

    // Arrivals list
    $stmt = $pdo->prepare("
        SELECT r.*, rm.room_type
        FROM reservations r
        LEFT JOIN rooms rm ON rm.id = r.room_id
        WHERE r.hotel_id = ? AND r.check_in = ? AND r.status = 'confirmed'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$hotel_id, $today]);
    $arrivals_list = $stmt->fetchAll();

    // Departures list
    $stmt = $pdo->prepare("
        SELECT r.*, rm.room_type
        FROM reservations r
        LEFT JOIN rooms rm ON rm.id = r.room_id
        WHERE r.hotel_id = ? AND r.check_out = ? AND r.status = 'checked-in'
        ORDER BY r.created_at DESC
    ");
    $stmt->execute([$hotel_id, $today]);
    $departures_list = $stmt->fetchAll();

} catch (PDOException $e) {
    die("<strong>Query Error</strong><br>" . $e->getMessage());
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Dashboard - Hotel PMS</title>
    <style>
        body { margin:0; font-family: Arial, sans-serif; background:#f0f2f5; }
        .sidebar { width:230px; background:#002147; color:white; height:100vh; position:fixed; padding-top:20px; }
        .sidebar h2 { text-align:center; margin-bottom:30px; font-size:22px; font-weight:bold; }
        .sidebar a { display:block; padding:12px 20px; color:white; text-decoration:none; font-size:16px; }
        .sidebar a:hover { background:#003366; }
        .content { margin-left:230px; padding:25px; }

        .dashboard-cards {
            display:grid; 
            grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
            gap:20px; 
            margin-bottom:30px;
        }
        .kpi-card { background:white; padding:20px; border-radius:6px;
            box-shadow:0 1px 4px rgba(0,0,0,0.1); text-align:center; }
        .kpi-card h3 { font-size:15px; color:#666; margin-bottom:10px; }
        .kpi-card p { font-size:26px; font-weight:bold; margin:0; color:#2b6cb0; }

        table { width:100%; border-collapse:collapse; background:white; border-radius:6px;
            overflow:hidden; margin-bottom:30px; box-shadow:0 1px 4px rgba(0,0,0,0.1); }
        table th, table td { padding:12px 15px; border-bottom:1px solid #eee; }
        table th { background:#fafafa; text-align:left; font-weight:bold; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2>Hotel PMS</h2>
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
    <h1>Dashboard</h1>

    <div class="dashboard-cards">
        <div class="kpi-card"><h3>Total Rooms</h3><p><?= $total_rooms ?></p></div>
        <div class="kpi-card"><h3>Occupied Rooms</h3><p><?= $occupied_rooms ?></p></div>
        <div class="kpi-card"><h3>Vacant Clean Rooms</h3><p><?= $vacant_clean_rooms ?></p></div>
        <div class="kpi-card"><h3>Rooms Needing Cleaning</h3><p><?= $rooms_need_cleaning ?></p></div>
        <div class="kpi-card"><h3>Arrivals Today</h3><p><?= $arrivals_today ?></p></div>
        <div class="kpi-card"><h3>Departures Today</h3><p><?= $departures_today ?></p></div>
        <div class="kpi-card"><h3>In-House Guests</h3><p><?= $inhouse_guests ?></p></div>
    </div>

    <div class="card">
        <h3>User Information</h3>
        <p><strong>User:</strong> <?= htmlspecialchars($_SESSION['email'] ?? 'Unknown') ?></p>
        <p><strong>Hotel ID:</strong> <?= htmlspecialchars($hotel_id) ?></p>
        <p><strong>Role:</strong> <?= htmlspecialchars($_SESSION['role'] ?? 'Unknown') ?></p>
    </div>

    <h2>Today's Arrivals</h2>
    <table>
        <tr><th>Guest</th><th>Room</th><th>Type</th><th>Adults</th><th>Children</th><th>Total</th><th>Status</th></tr>
        <?php if (empty($arrivals_list)): ?>
            <tr><td colspan="7" style="text-align:center;">No arrivals today</td></tr>
        <?php else: foreach ($arrivals_list as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['guest_name']) ?></td>
                <td><?= htmlspecialchars($r['room_number']) ?></td>
                <td><?= htmlspecialchars($r['room_type']) ?></td>
                <td><?= (int)$r['adults'] ?></td>
                <td><?= (int)$r['children'] ?></td>
                <td>£<?= number_format($r['total_amount'], 2) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </table>

    <h2>Today's Departures</h2>
    <table>
        <tr><th>Guest</th><th>Room</th><th>Type</th><th>Adults</th><th>Children</th><th>Total</th><th>Status</th></tr>
        <?php if (empty($departures_list)): ?>
            <tr><td colspan="7" style="text-align:center;">No departures today</td></tr>
        <?php else: foreach ($departures_list as $r): ?>
            <tr>
                <td><?= htmlspecialchars($r['guest_name']) ?></td>
                <td><?= htmlspecialchars($r['room_number']) ?></td>
                <td><?= htmlspecialchars($r['room_type']) ?></td>
                <td><?= (int)$r['adults'] ?></td>
                <td><?= (int)$r['children'] ?></td>
                <td>£<?= number_format($r['total_amount'], 2) ?></td>
                <td><?= htmlspecialchars($r['status']) ?></td>
            </tr>
        <?php endforeach; endif; ?>
    </table>
<?php var_dump($_SESSION); ?>
</div>
</body>
</html>