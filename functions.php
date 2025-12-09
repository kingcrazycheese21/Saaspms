<?php
require_once __DIR__ . '/config.php';

function create_session($user) {
    $token = bin2hex(random_bytes(32));
    $data = [
        'user_id' => $user['id'],
        'hotel_id' => $user['hotel_id'],
        'role' => $user['role'],
        'name' => $user['name'],
        'created' => time()
    ];
    file_put_contents(SESSION_PATH . "/$token.json", json_encode($data));
    setcookie('SESSION_ID', $token, time()+3600, "/");
}

function get_session() {
    if (empty($_COOKIE['SESSION_ID'])) return false;
    $file = SESSION_PATH . "/" . basename($_COOKIE['SESSION_ID']) . ".json";
    if (!file_exists($file)) return false;
    return json_decode(file_get_contents($file), true);
}

function require_login() {
    $session = get_session();
    if (!$session) {
        header("Location: login.php");
        exit;
    }
    return $session;
}

function destroy_session() {
    if (!empty($_COOKIE['SESSION_ID'])) {
        $file = SESSION_PATH . "/" . basename($_COOKIE['SESSION_ID']) . ".json";
        if (file_exists($file)) unlink($file);
        setcookie('SESSION_ID', '', time()-3600, "/");
    }
}
