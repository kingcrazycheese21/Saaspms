<?php
ini_set('display_errors',1);
ini_set('display_startup_errors',1);
error_reporting(E_ALL);

require_once 'init.php';

function new_invoice_number(){ return 'INV'.date('YmdHis').rand(10,99); }

// Post a simple invoice (form)
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['post_charge'])) {
    $res_id = (int)($_POST['reservation_id'] ?? 0);
    $guest_id = (int)($_POST['guest_id'] ?? 0);
    $desc = trim($_POST['description'] ?? '');
    $amount = (float)($_POST['amount'] ?? 0);
    if ($amount <= 0 || $desc === '') $err = "Description and positive amount required.";
    else {
        $invoice = new_invoice_number();
        $items = json_encode([['desc'=>$desc,'amount'=>$amount]]);
        $subtotal = number_format($amount,2,'.','');
        $total = $subtotal;
        $stmt = $pdo->prepare("INSERT INTO billing (reservation_id, guest_id, invoice_number, items, subtotal, tax_amount, total_amount, paid_amount, balance_amount, payment_status, created_at) VALUES (?,?,?,?,?,?,?,?,?,'pending',NOW())");
        $stmt->execute([$res_id, $guest_id, $invoice, $items, $subtotal, 0.00, $total, 0.00, $total]);
        header("Location: billing.php?posted=1");
        exit;
    }
}

// Make a payment
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['make_payment'])) {
    $bill_id = (int)$_POST['bill_id'];
    $paid = (float)$_POST['paid_amount'];
    if ($paid <= 0) $err = "Enter a payment amount.";
    else {
        $pdo->beginTransaction();
        $stmt = $pdo->prepare("SELECT paid_amount, total_amount FROM billing WHERE id = ? FOR UPDATE");
        $stmt->execute([$bill_id]);
        $row = $stmt->fetch();
        if ($row) {
            $new_paid = $row['paid_amount'] + $paid;
            $new_balance = max(0, $row['total_amount'] - $new_paid);
            $status = $new_balance <= 0 ? 'paid' : 'partial';
            $pdo->prepare("UPDATE billing SET paid_amount = ?, balance_amount = ?, payment_status = ? WHERE id = ?")->execute([$new_paid, $new_balance, $status, $bill_id]);
            $pdo->commit();
            header('Location: billing.php?paid=1');
            exit;
        } else {
            $pdo->rollBack();
            $err = "Invoice not found.";
        }
    }
}

// List invoices
$stmt = $pdo->prepare("SELECT b.*, g.first_name, g.last_name FROM billing b LEFT JOIN guests g ON b.guest_id = g.id WHERE b.reservation_id IN (SELECT id FROM reservations WHERE hotel_id = ?) ORDER BY b.created_at DESC");
$stmt->execute([$hotel_id]);
$bills = $stmt->fetchAll();

// Reservations for posting charges
$stmt = $pdo->prepare("SELECT id, guest_name FROM reservations WHERE hotel_id = ? AND status IN ('confirmed','checked-in') ORDER BY check_in");
$stmt->execute([$hotel_id]);
$resList = $stmt->fetchAll();
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<title>Billing - Hotel PMS</title>
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
    <a href="billing.php" style="background:#003366;">Billing</a>
    <a href="reports.php">Reports & Charts</a>
    <a href="nightaudit.php">Night Audit</a>
    <a href="settings.php">Settings</a>
    <a href="logout.php" style="background:#990000;margin-top:50px;">Logout</a>
</div>

<div class="content">
    <h1>Billing & Folios</h1>

    <?php if (!empty($err)): ?><div style="color:red; margin-bottom:10px;"><?= h($err) ?></div><?php endif; ?>
    <?php if (!empty($_GET['posted'])): ?><div style="background:#d4edda;padding:10px;border-left:4px solid:#28a745;margin-bottom:10px;">Charge posted.</div><?php endif; ?>

    <div class="card">
        <h3>Post Charge / Create Invoice</h3>
        <form method="post">
            <label>Reservation
                <select name="reservation_id">
                    <?php foreach($resList as $r): ?>
                        <option value="<?= $r['id'] ?>"><?= h($r['guest_name'].' (ID '.$r['id'].')') ?></option>
                    <?php endforeach;?>
                </select>
            </label><br><br>
            <label>Guest ID (optional)<br><input name="guest_id"></label><br><br>
            <label>Description<br><input name="description" required></label><br><br>
            <label>Amount<br><input name="amount" type="number" step="0.01" required></label><br><br>
            <button name="post_charge" type="submit" class="btn">Post Charge</button>
        </form>
    </div>

    <div class="card">
        <h3>Invoices</h3>
        <table>
            <tr><th>Invoice</th><th>Guest</th><th>Total</th><th>Paid</th><th>Balance</th><th>Status</th><th>Actions</th></tr>
            <?php if (empty($bills)): ?>
                <tr><td colspan="7" style="text-align:center;">No invoices</td></tr>
            <?php else: foreach ($bills as $b): ?>
                <tr>
                    <td><?= h($b['invoice_number']) ?></td>
                    <td><?= h($b['first_name'].' '.$b['last_name']) ?></td>
                    <td>£<?= number_format($b['total_amount'],2) ?></td>
                    <td>£<?= number_format($b['paid_amount'],2) ?></td>
                    <td>£<?= number_format($b['balance_amount'],2) ?></td>
                    <td><?= h($b['payment_status']) ?></td>
                    <td>
                        <form method="post" style="display:inline;">
                            <input type="hidden" name="bill_id" value="<?= $b['id'] ?>">
                            <input type="number" name="paid_amount" step="0.01" placeholder="Amount">
                            <button name="make_payment" type="submit" class="btn">Pay</button>
                        </form>
                    </td>
                </tr>
            <?php endforeach; endif; ?>
        </table>
    </div>
</div>
</body>
</html>