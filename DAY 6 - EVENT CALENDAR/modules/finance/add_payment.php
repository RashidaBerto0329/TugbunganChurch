<?php
// church/modules/finance/add_payment.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy', 'finance'])) {
    include $root . '/auth/access_denied.php'; exit;
}

$edit_id = (int)($_GET['edit'] ?? 0);
$rec = null;
if ($edit_id > 0) {
    $s = $conn->prepare("SELECT * FROM payments WHERE id = ?");
    $s->bind_param("i", $edit_id); $s->execute();
    $rec = $s->get_result()->fetch_assoc(); $s->close();
    if (!$rec) { $_SESSION['error'] = "Payment not found."; header('Location: /church/modules/finance/payments.php'); exit; }
}
$is_edit = $rec !== null;
$errors = []; $old = $rec ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old    = $_POST;
    $name   = trim($_POST['name']   ?? '');
    $reason = trim($_POST['reason'] ?? '');
    $amount = trim($_POST['amount'] ?? '');
    $date   = trim($_POST['date']   ?? '');
    $time   = trim($_POST['time']   ?? '') ?: null;
    $notes  = trim($_POST['notes']  ?? '');

    if ($name === '')                                       $errors[] = "Payee / name is required.";
    if ($reason === '')                                     $errors[] = "Reason / purpose is required.";
    if ($date === '')                                       $errors[] = "Date is required.";
    if (!is_numeric($amount) || (float)$amount <= 0)       $errors[] = "Amount must be a positive number.";

    if (empty($errors)) {
        $amt_val = (float)$amount;
        if ($is_edit) {
            $s = $conn->prepare("UPDATE payments SET name=?, reason=?, amount=?, date=?, time=?, notes=? WHERE id=?");
            $s->bind_param("ssdsssi", $name, $reason, $amt_val, $date, $time, $notes, $edit_id);
            $s->execute(); $s->close();
            $_SESSION['success'] = "Payment updated successfully.";
        } else {
$s = $conn->prepare("INSERT INTO payments (name, reason, amount, date, time, notes, created_by) VALUES (?,?,?,?,?,?,?)");
$s->bind_param("ssdsssi", $name, $reason, $amt_val, $date, $time, $notes, $current_user_id);
$s->execute(); $s->close();
            $_SESSION['success'] = "Payment to \"{$name}\" recorded successfully.";
        }
        header('Location: /church/modules/finance/payments.php'); exit;
    }
}

function ov($old, $rec, $key, $default = '') { return htmlspecialchars($old[$key] ?? $rec[$key] ?? $default); }

$page_title = $is_edit ? "Edit Payment" : "Add Payment";
include $root . '/includes/header.php';
?>
<style>
    .form-card{background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:16px;}
    .fch{padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px;}
    .fchi{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;}
    .fct{font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;color:#0f2044;}
    .fcb{padding:20px 22px;}
    .fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .fg{display:flex;flex-direction:column;gap:5px;}
    .lbl{font-size:0.8rem;font-weight:600;color:#374151;}
    .req{color:#ef4444;} .opt{color:#9ca3af;font-weight:400;font-size:0.72rem;margin-left:4px;}
    .inp,.txa{padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.865rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif;}
    .inp:focus,.txa:focus{border-color:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,0.1);}
    .txa{resize:vertical;min-height:70px;}
    .btn-save{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#d97706;color:#fff;border:none;cursor:pointer;} .btn-save:hover{background:#b45309;}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;}
    .error-list{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:18px;}
    .error-list li{font-size:0.82rem;color:#dc2626;margin-bottom:3px;}
    @media(max-width:900px){#addLayout{grid-template-columns:1fr!important;}}
    @media(max-width:640px){.fg2{grid-template-columns:1fr;}}
</style>

<div class="page-header">
    <h1><i class="fas fa-<?= $is_edit?'pen':'plus' ?>" style="color:#d97706;margin-right:8px;font-size:1rem;"></i><?= $is_edit?'Edit':'Add' ?> Payment</h1>
    <p class="breadcrumb">
        <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <a href="/church/modules/finance/index.php" style="color:#9ca3af;text-decoration:none;">Finance</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <a href="/church/modules/finance/payments.php" style="color:#9ca3af;text-decoration:none;">Payments</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <span class="current"><?= $is_edit?'Edit':'Add' ?></span>
    </p>
</div>

<div style="padding:24px 24px 60px;">
    <?php if (!empty($errors)): ?>
    <div class="error-list"><div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;"><i class="fas fa-circle-exclamation" style="color:#dc2626;"></i><strong style="font-size:0.85rem;color:#dc2626;">Please fix the following:</strong></div>
    <ul style="margin-left:16px;"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 260px;gap:20px;align-items:start;" id="addLayout">
    <div>
    <form method="POST">
        <div class="form-card">
            <div class="fch"><div class="fchi" style="background:#fef3c7;color:#d97706;"><i class="fas fa-money-bill-wave"></i></div>
            <div><div class="fct">Payment Details</div><div style="font-size:0.72rem;color:#9ca3af;">Expense / disbursement record</div></div></div>
            <div class="fcb">
                <div class="fg2">
                    <div class="fg" style="grid-column:span 2;">
                        <label class="lbl">Payee / Name <span class="req">*</span></label>
                        <input type="text" name="name" class="inp" value="<?= ov($old,$rec??[],'name') ?>" placeholder="e.g. VECO Electric Bill, Juan dela Cruz" required>
                    </div>
                    <div class="fg" style="grid-column:span 2;">
                        <label class="lbl">Reason / Purpose <span class="req">*</span></label>
                        <input type="text" name="reason" class="inp" value="<?= ov($old,$rec??[],'reason') ?>" placeholder="e.g. Monthly electricity bill, Repair of church roof" required>
                    </div>
                    <div class="fg">
                        <label class="lbl">Amount <span class="req">*</span></label>
                        <div style="position:relative;"><span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.85rem;pointer-events:none;">₱</span>
                        <input type="number" name="amount" class="inp" style="padding-left:26px;" step="0.01" min="0" value="<?= ov($old,$rec??[],'amount','') ?>" placeholder="0.00" required></div>
                    </div>
                    <div class="fg">
                        <label class="lbl">Date <span class="req">*</span></label>
                        <input type="date" name="date" class="inp" value="<?= ov($old,$rec??[],'date',date('Y-m-d')) ?>" required>
                    </div>
                    <div class="fg">
                        <label class="lbl">Time <span class="opt">(optional)</span></label>
                        <input type="time" name="time" class="inp" value="<?= ov($old,$rec??[],'time') ?>">
                    </div>
                    <div class="fg" style="grid-column:span 2;">
                        <label class="lbl">Notes <span class="opt">(optional)</span></label>
                        <textarea name="notes" class="txa" rows="2" placeholder="Additional details, receipt no., etc."><?= ov($old,$rec??[],'notes') ?></textarea>
                    </div>
                </div>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 22px;background:#faf7f0;border:1px solid #ede8de;border-radius:0 0 14px 14px;">
            <a href="/church/modules/finance/payments.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> <?= $is_edit?'Save Changes':'Record Payment' ?></button>
        </div>
    </form>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:20px;">
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Common Expenses</p>
            <div style="font-size:0.78rem;color:#6b7280;display:flex;flex-direction:column;gap:6px;">
                <?php foreach(['VECO Electric Bill','ZAMCELCO Electric Bill','MWSS Water Bill','Telephone / Internet Bill','Repair & Maintenance','Staff Honorarium','Altar Supplies','Candles & Incense','Office Supplies','Caretaker Salary'] as $ex): ?>
                <div style="cursor:pointer;padding:5px 10px;border-radius:6px;border:1px solid #ede8de;" onclick="document.querySelector('[name=reason]').value='<?= $ex ?>';"><?= $ex ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
</div>
<?php include $root . '/includes/footer.php'; ?>