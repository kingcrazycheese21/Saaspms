<?php
// nightaudit_action.php
// Runs the night audit steps and returns JSON with progress and messages.
//
// WARNING: This script makes changes to your DB. Test on a staging environment first.
// It is written to work with the schema you described (reservations, rooms, billing, audit_logs).
// Adjust table/column names to match your environment if needed.

// Use existing session (do not call session_start if your app already started it).
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Security: allow only logged-in staff
$user_id = $_SESSION['user_id'] ?? $_SESSION['staff_id'] ?? null;
$user_role = $_SESSION['user_role'] ?? $_SESSION['role'] ?? null;
if (!$user_id) {
    http_response_code(403);
    echo "Not authenticated";
    exit;
}

// Create / use PDO (if $pdo exists from init, use it; otherwise create using config.php)
if (!isset($pdo) || !($pdo instanceof PDO)) {
    $configPath = __DIR__ . '/config.php';
    if (!file_exists($configPath)) {
        // try config in parent (some setups)
        $configPath = __DIR__ . '/ai_assistant_v7/ai_config_v7.php'; // fallback placeholder
    }
    // Expect your config.php to return array with db_host, db_name, db_user, db_pass, db_charset
    if (file_exists(__DIR__ . '/config.php')) {
        $cfg = include __DIR__ . '/config.php';
    } elseif (file_exists(__DIR__ . '/../config.php')) {
        $cfg = include __DIR__ . '/../config.php';
    } else {
        // As a last resort, fail
        http_response_code(500);
        echo "DB config not found. Please ensure config.php is available.";
        exit;
    }

    try {
        $dsn = "mysql:host={$cfg['db_host']};dbname={$cfg['db_name']};charset={$cfg['db_charset']}";
        $pdo = new PDO($dsn, $cfg['db_user'], $cfg['db_pass'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);
    } catch (PDOException $ex) {
        http_response_code(500);
        echo "DB connect error: " . $ex->getMessage();
        exit;
    }
}

// Only accept POST with JSON body
$raw = file_get_contents('php://input');
$body = json_decode($raw, true);
if (!is_array($body) || ($body['action'] ?? '') !== 'run') {
    http_response_code(400);
    echo "Invalid request";
    exit;
}

$today = date('Y-m-d');
$steps = [];
$summary = [
    'auto_checked_out' => 0,
    'force_checked_out' => 0,
    'charges_posted' => 0,
    'audit_log_id' => null,
];

// We'll run several steps in a transaction
try {
    $pdo->beginTransaction();

    // STEP 1: Auto-checkout reservations that have check_out < today and are not checked-out
    $steps[] = ['title' => 'Auto-checkout overdue reservations', 'percent' => 10, 'msg' => 'Searching for overdue reservations...','status'=>'running'];
    // Select reservations that should be auto-checked-out
    $stmt = $pdo->prepare("SELECT id, room_id, room_number, status FROM reservations WHERE hotel_id = ? AND check_out < ? AND status != 'checked-out'");
    // determine hotel_id from session or default to 1
    $hotel_id = (int)($_SESSION['hotel_id'] ?? 1);
    $stmt->execute([$hotel_id, $today]);
    $rows = $stmt->fetchAll();

    $autoCount = 0;
    foreach ($rows as $r) {
        // Update reservation status
        $u = $pdo->prepare("UPDATE reservations SET status = 'checked-out', updated_at = NOW() WHERE id = ? AND hotel_id = ?");
        $u->execute([$r['id'], $hotel_id]);
        // Set room status to available if room exists
        if (!empty($r['room_id'])) {
            $up = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ? AND hotel_id = ?");
            $up->execute([$r['room_id'], $hotel_id]);
        }
        $autoCount++;
    }
    $summary['auto_checked_out'] = $autoCount;
    $steps[] = ['title' => 'Auto-checkout overdue reservations', 'percent' => 30, 'msg' => "Auto-checked-out {$autoCount} reservations", 'status'=>'done'];

    // STEP 2: Force checkout for reservations whose check_out == today and status = 'checked-in'
    $steps[] = ['title' => 'Process departures for today', 'percent' => 35, 'msg'=>'Processing departures for today...', 'status'=>'running'];
    $stmt = $pdo->prepare("SELECT id, room_id FROM reservations WHERE hotel_id = ? AND check_out = ? AND status = 'checked-in'");
    $stmt->execute([$hotel_id, $today]);
    $rows = $stmt->fetchAll();
    $forceCount = 0;
    foreach ($rows as $r) {
        $u = $pdo->prepare("UPDATE reservations SET status = 'checked-out', updated_at = NOW() WHERE id = ? AND hotel_id = ?");
        $u->execute([$r['id'], $hotel_id]);
        if (!empty($r['room_id'])) {
            $up = $pdo->prepare("UPDATE rooms SET status = 'available' WHERE id = ? AND hotel_id = ?");
            $up->execute([$r['room_id'], $hotel_id]);
        }
        $forceCount++;
    }
    $summary['force_checked_out'] = $forceCount;
    $steps[] = ['title' => 'Process departures for today', 'percent' => 50, 'msg' => "Processed {$forceCount} departures", 'status'=>'done'];

    // STEP 3: Post nightly room charges for in-house guests (status = 'checked-in')
    // We'll insert a billing row per reservation for price_per_night (read from rooms)
    $steps[] = ['title' => 'Post nightly room charges', 'percent' => 55, 'msg' => 'Posting room charges for in-house guests...', 'status'=>'running'];

    // fetch checked-in reservations with a valid room
    $stmt = $pdo->prepare("
        SELECT r.id AS reservation_id, r.room_id, r.total_amount, rm.price_per_night
        FROM reservations r
        JOIN rooms rm ON rm.id = r.room_id
        WHERE r.hotel_id = ? AND r.status = 'checked-in'
    ");
    $stmt->execute([$hotel_id]);
    $rows = $stmt->fetchAll();
    $chargeCount = 0;

    foreach ($rows as $r) {
        // Build a simple invoice (items as JSON)
        $items = json_encode([['desc' => 'Room charge (night)', 'amount' => (float)$r['price_per_night']]]);

        // create invoice_number (date + reservation id)
        $invoice_number = 'INV-' . date('Ymd') . '-' . $r['reservation_id'] . '-' . bin2hex(random_bytes(2));

        $ins = $pdo->prepare("INSERT INTO billing (reservation_id, guest_id, invoice_number, items, subtotal, tax_amount, total_amount, paid_amount, balance_amount, payment_method, payment_status, created_at)
            VALUES (?, NULL, ?, ?, ?, 0.00, ?, 0.00, ?, 'room', 'pending', NOW())");

        $subtotal = (float)$r['price_per_night'];
        $total_amount = $subtotal; // taxes not calculated here; implement tax if needed
        $balance = $total_amount;

        $ins->execute([$r['reservation_id'], $invoice_number, $items, $subtotal, $total_amount, $balance]);
        $chargeCount++;
    }
    $summary['charges_posted'] = $chargeCount;
    $steps[] = ['title' => 'Post nightly room charges', 'percent' => 80, 'msg' => "Posted {$chargeCount} charges.", 'status'=>'done'];

    // STEP 4: Insert an audit log entry (requires audit_logs table). If not available, skip gracefully.
    $steps[] = ['title' => 'Create audit log', 'percent' => 90, 'msg' => 'Recording audit log...', 'status'=>'running'];
    try {
        $ins = $pdo->prepare("INSERT INTO audit_logs (hotel_id, performed_by, summary, created_at) VALUES (?, ?, ?, NOW())");
        $summaryText = json_encode($summary);
        $ins->execute([$hotel_id, $user_id, $summaryText]);
        $audit_id = $pdo->lastInsertId();
        $summary['audit_log_id'] = $audit_id;
        $steps[] = ['title' => 'Create audit log', 'percent' => 95, 'msg' => "Audit log created: #{$audit_id}", 'status'=>'done'];
    } catch (Exception $e) {
        // audit_logs may not exist — log error server-side and continue
        error_log("AI NightAudit: could not create audit log: " . $e->getMessage());
        $steps[] = ['title' => 'Create audit log', 'percent' => 95, 'msg' => "Audit log table missing or error.", 'status'=>'skipped'];
    }

    // COMMIT
    $pdo->commit();

    // Final step
    $steps[] = ['title' => 'Finalize', 'percent' => 100, 'msg' => 'Night audit finished successfully.', 'status'=>'done'];

    // Return JSON containing steps and summary
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['steps' => $steps, 'summary' => $summary], JSON_PRETTY_PRINT);
    exit;

} catch (Exception $ex) {
    // Rollback on error
    if ($pdo->inTransaction()) $pdo->rollBack();
    error_log("NightAudit error: " . $ex->getMessage());

    http_response_code(500);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['error' => $ex->getMessage(), 'steps' => $steps, 'summary' => $summary]);
    exit;
}