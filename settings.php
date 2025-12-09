<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'init.php';

// Ensure logged in
if (!isset($_SESSION['user_id'])) {
    die("Not logged in.");
}

$user_id  = $_SESSION['user_id'];
$hotel_id = $_SESSION['hotel_id'];
$email    = $_SESSION['email'];

// ---------------------------
// UPDATE PROFILE
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_profile'])) {

    $new_email = trim($_POST['email'] ?? '');

    $stmt = $pdo->prepare("UPDATE staff SET email=? WHERE id=? AND hotel_id=?");
    $stmt->execute([$new_email, $user_id, $hotel_id]);

    $_SESSION['email'] = $new_email;

    $ok = "Profile updated.";
}

// ---------------------------
// CHANGE PASSWORD
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['change_password'])) {

    $current = $_POST['current_password'] ?? '';
    $new     = $_POST['new_password'] ?? '';

    // Fetch current password
    $stmt = $pdo->prepare("SELECT password FROM staff WHERE id=? AND hotel_id=?");
    $stmt->execute([$user_id, $hotel_id]);
    $row = $stmt->fetch();

    if (!$row || !password_verify($current, $row['password'])) {
        $err = "Current password is incorrect.";
    } else {
        $newHash = password_hash($new, PASSWORD_DEFAULT);
        $stmt = $pdo->prepare("UPDATE staff SET password=? WHERE id=? AND hotel_id=?");
        $stmt->execute([$newHash, $user_id, $hotel_id]);
        $ok = "Password changed successfully.";
    }
}

// ---------------------------
// UPDATE ROOM COUNT
// ---------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['update_rooms'])) {

    $newCount = (int)$_POST['room_count'];

    // Get current count
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE hotel_id=?");
    $stmt->execute([$hotel_id]);
    $currentCount = (int)$stmt->fetchColumn();

    if ($newCount > $currentCount) {

        // Add rooms
        $toAdd = $newCount - $currentCount;

        // Correct: room_number + valid default status
        $stmt = $pdo->prepare("
            INSERT INTO rooms (hotel_id, room_number, status)
            VALUES (?, ?, 'available')
        ");

        for ($i = $currentCount + 1; $i <= $newCount; $i++) {
            $stmt->execute([$hotel_id, "Room $i"]);
        }

        $ok = "$toAdd room(s) added.";
    }

    elseif ($newCount < $currentCount) {

        // Remove rooms
        $toRemove = $currentCount - $newCount;

        // Delete newest rooms based on ID descending
        $stmt = $pdo->prepare("
            DELETE FROM rooms
            WHERE hotel_id=?
            ORDER BY id DESC
            LIMIT $toRemove
        ");

        $stmt->execute([$hotel_id]);

        $ok = "$toRemove room(s) removed.";
    }

    else {
        $ok = "No changes made.";
    }
}
// ---------------------------
// LOAD CONFIG AND COUNTS
// ---------------------------
$cfg = include __DIR__ . '/config.php';

$stmt = $pdo->prepare("SELECT COUNT(*) FROM rooms WHERE hotel_id=?");
$stmt->execute([$hotel_id]);
$roomCount = (int)$stmt->fetchColumn();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Settings - Hotel PMS</title>
<style>
body{margin:0;font-family:Arial, sans-serif;background:#f0f2f5;}
.sidebar{width:230px;background:#002147;color:white;position:fixed;top:0;bottom:0;padding-top:20px;}
.sidebar a{display:block;padding:12px 20px;color:white;text-decoration:none;}
.sidebar a:hover{background:#003366;}
.content{margin-left:230px;padding:25px;}
.card{background:white;padding:15px;border-radius:6px;box-shadow:0 1px 4px rgba(0,0,0,0.08);margin-bottom:20px;}
label{display:block;margin-bottom:8px;}
input{padding:8px;width:100%;box-sizing:border-box;margin-bottom:10px;}
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
    <a href="settings.php" style="background:#003366;">Settings</a>
    <a href="logout.php" style="background:#990000;margin-top:50px;">Logout</a>
</div>

<div class="content">
    <h1>Settings</h1>

    <?php if (!empty($ok)): ?>
        <div style="background:#d4edda;padding:10px;border-left:4px solid:#28a745;margin-bottom:10px;"><?= htmlspecialchars($ok) ?></div>
    <?php endif; ?>

    <?php if (!empty($err)): ?>
        <div style="color:red;margin-bottom:10px;"><?= htmlspecialchars($err) ?></div>
    <?php endif; ?>

    <div class="card">
        <h3>System</h3>
        <p>DB Host: <?= htmlspecialchars($cfg['db_host']) ?></p>
        <p>DB Name: <?= htmlspecialchars($cfg['db_name']) ?></p>
        <p>Total Rooms: <?= $roomCount ?></p>
        <p><strong>Session ID:</strong> <?= session_id(); ?></p>
        <p><strong>Logged in as:</strong> <?= htmlspecialchars($email) ?></p>
    </div>

    <div class="card">
        <h3>Your Profile</h3>
        <form method="post">
            <input type="hidden" name="update_profile" value="1">
            <label>Email<input name="email" value="<?= htmlspecialchars($email) ?>"></label>
            <button type="submit">Update Email</button>
        </form>
    </div>

    <div class="card">
        <h3>Change Password</h3>
        <form method="post">
            <input type="hidden" name="change_password" value="1">
            <label>Current Password<input type="password" name="current_password"></label>
            <label>New Password<input type="password" name="new_password"></label>
            <button type="submit">Change Password</button>
        </form>
    </div>

    <div class="card">
        <h3>Hotel Room Configuration</h3>
        <form method="post">
            <input type="hidden" name="update_rooms" value="1">
            <label>Total Rooms<input type="number" name="room_count" min="1" value="<?= $roomCount ?>"></label>
            <button type="submit">Update Room Count</button>
        </form>
    </div>

</div>
</body>
</html>