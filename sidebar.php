<?php
if (defined('SIDEBAR_LOADED')) return;
define('SIDEBAR_LOADED', true);
?>
<link rel="stylesheet" href="sidebar.css">

<div class="sidebar">
    <div class="logo">Hotel PMS</div>
    <a href="dashboard.php">Dashboard</a>
    <a href="housekeeping.php">Housekeeping</a>
    <a href="reservations.php">Reservations</a>
    <a href="billing.php">Billing</a>
    <a href="reports.php">Reports & Charts</a>
    <a href="audit.php">Night Audit</a>
    <a href="settings.php">Settings</a>
    <a class="logout" href="logout.php">Logout</a>
</div>

<div class="main">