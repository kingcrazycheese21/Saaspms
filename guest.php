<?php
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

require_once 'init.php';

// Create guest
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_guest'])) {
    $first = trim($_POST['first_name'] ?? '');
    $last = trim($_POST['last_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    if ($first=='' || $last=='' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $err = "First/Last name and valid email required";
    } else {
        $stmt = $pdo->prepare("INSERT INTO guests (hotel_id, first_name, last_name, email, phone, address, city, country) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$hotel_id, $first, $last, $email, $phone, $_POST['address'] ?? '', $_POST['city'] ?? '', $_POST['country'] ?? '']);
        header('Location: guests.php?created=1'); exit;
    }
}

// Fetch guests
$stmt = $pdo->prepare("SELECT * FROM guests WHERE hotel_id = ? ORDER BY last_name, first_name");
$stmt->execute([$hotel_id]);
$guests = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Guests - Hotel PMS</title>
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
    <h1>Guests</h1>

    <?php if (!empty($err)): ?><div style="color:red; margin-bottom:10px;"><?= h($err) ?></div><?php endif; ?>
    <?php if (!empty($_GET['created'])): ?><div style="background:#d4edda;padding:10px;border-left:4px solid #28a745;margin-bottom:10px;">Guest created.</div><?php endif; ?>

    <div class="card">
        <h3>Add Guest</h3>
        <form method="post">
            <input type="hidden" name="create_guest" value="1">
            <label>First name<br><input name="first_name" required></label><br><br>
            <label>Last name<br><input name="last_name" required></label><br><br>
            <label>Email<br><input name="email" required></label><br><br>
            <label>Phone<br><input name="phone"></label><br><br>
            <label>Address<br><textarea name="address"></textarea></label><br>
            <button type="submit" class="btn">Add Guest</button>
        </form>
    </div>

    <div class="card">
        <h3>Guest List</h3>
        <table>
            <tr><th>Name</th><th>Email</th><th>Phone</th><th>Actions</th></tr>
            <?php if (empty($guests)): ?>
                <tr><td colspan="4" style="text-align:center;">No guests</td></tr>
            <?php else: foreach ($guests as $g): ?>
                <tr>
                    <td><?= h($g['first_name'].' '.$g['last_name']) ?></td>
                    <td><?= h($g['email']) ?></td>
                    <td><?= h($g['phone']) ?></td>
                    <td><a class="btn" href="guests_edit.php?id=<?= $g['id'] ?>">Edit</a></td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
</body>
</html>