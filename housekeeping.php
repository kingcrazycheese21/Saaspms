<?php
require_once 'init.php';

// ----------------------------------------------------
// HANDLE CREATE NEW HOUSEKEEPING TASK
// ----------------------------------------------------
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['create_task'])) {
    $room_id     = $_POST['room_id'] ?? null;
    $task_type   = $_POST['task_type'] ?? 'cleaning';
    $priority    = $_POST['priority'] ?? 'medium';
    $notes       = $_POST['notes'] ?? '';

    $stmt = $pdo->prepare("
        INSERT INTO housekeeping (hotel_id, room_id, room_number, task_type, priority, notes)
        SELECT ?, id, room_number, ?, ?, ?
        FROM rooms
        WHERE id = ? AND hotel_id = ?
        LIMIT 1
    ");
    $stmt->execute([$hotel_id, $task_type, $priority, $notes, $room_id, $hotel_id]);

    header("Location: housekeeping.php?success=1");
    exit;
}

// ----------------------------------------------------
// HANDLE STATUS UPDATE
// ----------------------------------------------------
if (isset($_GET['update']) && isset($_GET['id'])) {
    $task_id = (int)$_GET['id'];
    $new_status = $_GET['update'];

    if (in_array($new_status, ['pending','in-progress','completed'])) {
        $stmt = $pdo->prepare("
            UPDATE housekeeping
            SET status = ?, completed_at = IF(?='completed', NOW(), completed_at)
            WHERE id = ? AND hotel_id = ?
        ");
        $stmt->execute([$new_status, $new_status, $task_id, $hotel_id]);
    }

    header("Location: housekeeping.php");
    exit;
}

// ----------------------------------------------------
// DELETE TASK
// ----------------------------------------------------
if (isset($_GET['delete'])) {
    $task_id = (int)$_GET['delete'];
    $stmt = $pdo->prepare("DELETE FROM housekeeping WHERE id = ? AND hotel_id = ?");
    $stmt->execute([$task_id, $hotel_id]);

    header("Location: housekeeping.php");
    exit;
}

// ----------------------------------------------------
// FETCH HOUSEKEEPING TASKS
// ----------------------------------------------------
$stmt = $pdo->prepare("
    SELECT h.*, r.room_type
    FROM housekeeping h
    LEFT JOIN rooms r ON r.id = h.room_id
    WHERE h.hotel_id = ?
    ORDER BY h.priority DESC, h.status ASC, h.created_at ASC
");
$stmt->execute([$hotel_id]);
$tasks = $stmt->fetchAll();

// Rooms for dropdown
$stmt = $pdo->prepare("SELECT id, room_number, room_type FROM rooms WHERE hotel_id = ? ORDER BY room_number ASC");
$stmt->execute([$hotel_id]);
$rooms = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Housekeeping - Hotel PMS</title>
    <style>
        body { margin:0; font-family: Arial, sans-serif; background:#f0f2f5; }
        .sidebar { width:230px; background:#002147; color:white; position:fixed; top:0; bottom:0; padding-top:20px; }
        .sidebar a { display:block; padding:12px 20px; color:white; text-decoration:none; }
        .sidebar a:hover { background:#003366; }
        .content { margin-left:230px; padding:25px; }
        table { width:100%; border-collapse:collapse; background:white; border-radius:6px; overflow:hidden; margin-bottom:30px; box-shadow:0 1px 4px rgba(0,0,0,0.1); }
        table th, table td { padding:12px; border-bottom:1px solid #eee; }
        table th { background:#fafafa; font-weight:bold; }
        .btn { padding:6px 12px; border-radius:4px; text-decoration:none; }
        .pending { background:#ddd; }
        .in-progress { background:#ffcc00; }
        .completed { background:#99cc66; }
        .delete { background:#cc0000; color:white; }
        .form-card { background:white; padding:20px; border-radius:6px; box-shadow:0 1px 4px rgba(0,0,0,0.1); margin-bottom:30px; }
        select, textarea, input { width:100%; padding:10px; margin-top:5px; margin-bottom:15px; }
    </style>
</head>
<body>

<div class="sidebar">
    <h2 style="text-align:center;">Hotel PMS</h2>
    <a href="dashboard.php">Dashboard</a>
    <a href="housekeeping.php" style="background:#003366;">Housekeeping</a>
    <a href="reservations.php">Reservations</a>
    <a href="billing.php">Billing</a>
    <a href="reports.php">Reports & Charts</a>
    <a href="nightaudit.php">Night Audit</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php" style="background:#990000; margin-top:50px;">Logout</a>
</div>

<div class="content">
    <h1>Housekeeping</h1>

    <?php if (!empty($_GET['success'])): ?>
        <div style="padding:10px; background:#d4edda; border-left:4px solid #28a745; margin-bottom:20px;">
            New task created successfully.
        </div>
    <?php endif; ?>

    <!-- ---------------------------------------------------
         CREATE TASK FORM
    --------------------------------------------------- -->
    <div class="form-card">
        <h3>Create New Task</h3>

        <form method="post">
            <label>Room</label>
            <select name="room_id" required>
                <option value="">Select room</option>
                <?php foreach ($rooms as $r): ?>
                    <option value="<?= $r['id'] ?>">
                        <?= h($r['room_number']) ?> — <?= h($r['room_type']) ?>
                    </option>
                <?php endforeach; ?>
            </select>

            <label>Task Type</label>
            <select name="task_type">
                <option value="cleaning">Cleaning</option>
                <option value="maintenance">Maintenance</option>
                <option value="inspection">Inspection</option>
            </select>

            <label>Priority</label>
            <select name="priority">
                <option value="low">Low</option>
                <option value="medium" selected>Medium</option>
                <option value="high">High</option>
            </select>

            <label>Notes</label>
            <textarea name="notes" rows="3"></textarea>

            <button type="submit" name="create_task" class="btn" style="background:#002147; color:white;">Create Task</button>
        </form>
    </div>

    <!-- ---------------------------------------------------
         TASK LIST
    --------------------------------------------------- -->
    <h2>Housekeeping Tasks</h2>
    <table>
        <tr>
            <th>ID</th>
            <th>Room</th>
            <th>Type</th>
            <th>Priority</th>
            <th>Status</th>
            <th>Notes</th>
            <th>Created</th>
            <th>Actions</th>
        </tr>

        <?php if (empty($tasks)): ?>
            <tr><td colspan="8" style="text-align:center;">No tasks found</td></tr>

        <?php else: foreach ($tasks as $t): ?>
            <tr>
                <td><?= $t['id'] ?></td>
                <td><?= h($t['room_number']) ?> (<?= h($t['room_type']) ?>)</td>
                <td><?= h($t['task_type']) ?></td>
                <td><?= ucfirst($t['priority']) ?></td>

                <td>
                    <span class="btn <?= $t['status'] ?>">
                        <?= ucfirst($t['status']) ?>
                    </span>
                </td>

                <td><?= nl2br(h($t['notes'])) ?></td>
                <td><?= $t['created_at'] ?></td>

                <td>
                    <?php if ($t['status'] !== 'in-progress'): ?>
                        <a href="?update=in-progress&id=<?= $t['id'] ?>" class="btn in-progress">Start</a>
                    <?php endif; ?>

                    <?php if ($t['status'] !== 'completed'): ?>
                        <a href="?update=completed&id=<?= $t['id'] ?>" class="btn completed">Complete</a>
                    <?php endif; ?>

                    <a href="?delete=<?= $t['id'] ?>" class="btn delete" onclick="return confirm('Delete task?');">Delete</a>
                </td>
            </tr>
        <?php endforeach; endif; ?>
    </table>

</div>

</body>
</html>