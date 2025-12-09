<?php
session_start();
$user = $_SESSION['user'] ?? null;
if (!$user && basename($_SERVER['PHP_SELF']) !== 'login.php') {
    header("Location: login.php");
    exit;
}
?>
<!doctype html><html><head>
<meta charset="utf-8">
<title>PMS</title>
<link rel="stylesheet" href="styles.css">
</head><body>
<div class="sidebar">
  <h2>PMS</h2>
  <a href="dashboard.php">Dashboard</a>
  <a href="reservations.php">Reservations</a>
  <a href="housekeeping.php">Housekeeping</a>
  <a href="billing.php">Billing</a>
  <a href="shifts.php">Shifts</a>
  <a href="reports.php">Reports</a>
  <a href="nightaudit.php">Night Audit</a>
  <a href="logout.php">Logout</a>
</div>
<div class="main">

<h2>Shifts</h2>
<form method="post">
Code (optional) <input name="code">
<button name="open">Open Shift</button>
</form>
<?php
require '../config/config.php';
$c=mysqli_connect($config['db_host'],$config['db_user'],$config['db_pass'],$config['db_name']);
if(isset($_POST['open'])){
  $code = $_POST['code'] ?: strtoupper(bin2hex(random_bytes(3)));
  $id = 'shift_'.bin2hex(random_bytes(4));
  mysqli_query($c,"UPDATE shifts SET status='closed',closed_at=NOW() WHERE hotel_id='hotel_demo' AND status='open'");
  $stmt=mysqli_prepare($c,"INSERT INTO shifts (id,hotel_id,code,started_at,started_by,status)
    VALUES (?, 'hotel_demo', ?, NOW(), ?, 'open')");
  mysqli_stmt_bind_param($stmt,'sss',$id,$code,$user['username']);
  mysqli_stmt_execute($stmt);
  echo "<p>Shift opened: $code</p>";
}
$res=mysqli_query($c,"SELECT * FROM shifts ORDER BY started_at DESC");
echo "<table border=1><tr><th>Code</th><th>Status</th><th>Start</th><th>End</th></tr>";
while($r=mysqli_fetch_assoc($res)){
  echo "<tr><td>{$r['code']}</td><td>{$r['status']}</td><td>{$r['started_at']}</td><td>{$r['closed_at']}</td></tr>";
}
echo "</table>";
?>
</div></body></html>