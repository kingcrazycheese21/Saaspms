<?php
require_once 'session_config.php';
require_once 'config.php';
require_once 'functions.php';
require_once 'auth.php';
require_role('admin');

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['_csrf'] ?? '')) {
        $error = 'Invalid CSRF token';
    } else {
        $action = $_POST['action'] ?? '';
        if ($action === 'add') {
            $email = trim($_POST['email'] ?? '');
            $password = $_POST['password'] ?? '';
            $role = $_POST['role'] ?? 'staff';
            if ($email === '' || $password === '') {
                $error = 'Email and password required';
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('INSERT INTO staff (email, password, role) VALUES (?, ?, ?)');
                $stmt->execute([$email, $hash, $role]);
                audit_log($pdo, $_SESSION['user_id'], 'user_add', $email);
                $success = 'User added';
            }
        } elseif ($action === 'edit') {
            $id = intval($_POST['id']);
            $role = $_POST['role'] ?? 'staff';
            $password = $_POST['password'] ?? '';
            if ($password !== '') {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare('UPDATE staff SET password = ?, role = ? WHERE id = ?');
                $stmt->execute([$hash, $role, $id]);
            } else {
                $stmt = $pdo->prepare('UPDATE staff SET role = ? WHERE id = ?');
                $stmt->execute([$role, $id]);
            }
            audit_log($pdo, $_SESSION['user_id'], 'user_edit', 'id='.$id);
            $success = 'User updated';
        } elseif ($action === 'perms') {
            $id = intval($_POST['id']);
            $perms = $_POST['perms'] ?? [];
            $pm = [];
            foreach ($perms as $p) $pm[$p] = 1;
            set_user_permissions($id, $pm);
            audit_log($pdo, $_SESSION['user_id'], 'user_perms_update', 'id='.$id);
            $success = 'Permissions updated';
        }
    }
}

$users = $pdo->query('SELECT id, email, role FROM staff ORDER BY id DESC')->fetchAll();

function csrf_input() { echo csrf_field(); }
?>
<!doctype html>
<html>
<head><meta charset="utf-8"><title>Admin - Users</title></head>
<body>
<h1>Admin - User Management</h1>
<?php if ($error): ?><div style="color:red"><?=htmlspecialchars($error)?></div><?php endif; ?>
<?php if ($success): ?><div style="color:green"><?=htmlspecialchars($success)?></div><?php endif; ?>

<h2>Add User</h2>
<form method="post">
<?php csrf_input(); ?>
<input type="hidden" name="action" value="add">
Email: <input name="email" type="email" required><br>
Password: <input name="password" type="password" required><br>
Role: <select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select><br>
<button type="submit">Add</button>
</form>

<h2>Existing Users</h2>
<table border="1" cellpadding="6">
<tr><th>ID</th><th>Email</th><th>Role</th><th>Actions</th></tr>
<?php foreach($users as $u): ?>
<tr>
<td><?=htmlspecialchars($u['id'])?></td>
<td><?=htmlspecialchars($u['email'])?></td>
<td><?=htmlspecialchars($u['role'])?></td>
<td>
<form method="post" style="display:inline">
<?php csrf_input(); ?>
<input type="hidden" name="action" value="edit">
<input type="hidden" name="id" value="<?=htmlspecialchars($u['id'])?>">
New password: <input name="password" type="password" placeholder="leave blank to keep"><br>
Role: <select name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select>
<button type="submit">Update</button>
</form>

<form method="post" style="display:inline">
<?php csrf_input(); ?>
<input type="hidden" name="action" value="perms">
<input type="hidden" name="id" value="<?=htmlspecialchars($u['id'])?>">
Permissions:<br>
<label><input type="checkbox" name="perms[]" value="billing"> Billing</label>
<label><input type="checkbox" name="perms[]" value="housekeeping"> Housekeeping</label>
<label><input type="checkbox" name="perms[]" value="reservations"> Reservations</label>
<button type="submit">Save Perms</button>
</form>

</td>
</tr>
<?php endforeach; ?>
</table>

</body>
</html>
