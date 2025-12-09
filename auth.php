<?php
// auth.php — authentication guard (include AFTER session_config.php)
if (empty($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
