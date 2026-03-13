<?php
// church/modules/finance/add_collection.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy', 'finance'])) {
    include $root . '/auth/access_denied.php'; exit;
}

$edit_id = (int)($_GET['edit'] ?? 0);
$rec = null;
if ($edit_id > 0) {
    $s = $conn->prepare("SELECT * FROM collections WHERE id = ?");
    $s->bind_param("i", $edit_id); $s->execute();
    $rec = $s->get_result()->fetch_assoc(); $s->close();
    if (!$rec) { $_SESSION['error'] = "Collection not found."; header('Location: /church/modules/finance/collections.php'); exit; }
}
$is_edit = $rec !== null;
$errors = []; $old = $rec ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old           = $_POST;
    $name          = trim($_POST['name']            ?? '');
    $amount        = trim($_POST['amount']          ?? '');
    $ctype         = trim($_POST['collection_type'] ?? 'cash');
    $date          = trim($_POST['date']            ?? '');
    $time_schedule = trim($_POST['time_schedule']   ?? '');
    $notes         = trim($_POST['notes']           ?? '');

    if ($name === '') $errors[] = "Collection name is required.";
    if ($date === '') $errors[] = "Date is required.";
    if ($ctype === 'cash' && (!is_numeric($amount) || (float)$amount <= 0)) $errors[] = "Amount must be a positive number.";

    if (empty($errors)) {
        $amt_val = $ctype === 'cash' ? (float)$amount : 0.00;
        if ($is_edit) {
$s = $conn->prepare("UPDATE collections SET name=?, amount=?, collection_type=?, date=?, time_schedule=?, notes=? WHERE id=?");
$s->bind_param("sdssssi", $name, $amt_val, $ctype, $date, $time_schedule, $notes, $edit_id);
$s->execute(); $s->close();
            $_SESSION['success'] = "Collection updated successfully.";
        } else {
$s = $conn->prepare("INSERT INTO collections (name, amount, collection_type, date, time_schedule, notes, created_by) VALUES (?,?,?,?,?,?,?)");
$s->bind_param("sdssssi", $name, $amt_val, $ctype, $date, $time_schedule, $notes, $current_user_id);
$s->execute(); $s->close();
            $_SESSION['success'] = "Collection \"{$name}\" recorded successfully.";
        }
        header('Location: /church/modules/finance/collections.php'); exit;
    }
}

function ov($old, $rec, $key, $default = '') { return htmlspecialchars($old[$key] ?? $rec[$key] ?? $default); }

$page_title = $is_edit ? "Edit Collection" : "Add Collection";
include $root . '/includes/header.php';
?>
<style>
    .form-card{background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:16px;}
    .fch{padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px;}
    .fchi{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;}
    .fct{font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;color:#0f2044;}
    .fcb{padding:20px 22px;}
    .fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
    .fg{display:flex;flex-direction:column;gap:5px;}
    .lbl{font-size:0.8rem;font-weight:600;color:#374151;}
    .req{color:#ef4444;} .opt{color:#9ca3af;font-weight:400;font-size:0.72rem;margin-left:4px;}
    .inp,.txa{padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.865rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif;}
    .inp:focus,.txa:focus{border-color:#2563eb;box-shadow:0 0 0 3px rgba(37,99,235,0.1);}
    .txa{resize:vertical;min-height:70px;}
    .type-toggle{display:grid;grid-template-columns:1fr 1fr;gap:0;border:1px solid #ede8de;border-radius:8px;overflow:hidden;}
    .type-btn{padding:10px;text-align:center;cursor:pointer;font-size:0.83rem;font-weight:600;border:none;background:#fff;color:#6b7280;transition:all 0.18s;}
    .type-btn.active-cash{background:#eff6ff;color:#2563eb;}
    .type-btn.active-inkind{background:#fef3c7;color:#d97706;}
    .btn-save{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#2563eb;color:#fff;border:none;cursor:pointer;} .btn-save:hover{background:#1d4ed8;}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;}
    .error-list{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:18px;}
    .error-list li{font-size:0.82rem;color:#dc2626;margin-bottom:3px;}
    @media(max-width:900px){#addLayout{grid-template-columns:1fr!important;}}
    @media(max-width:640px){.fg2,.fg3{grid-template-columns:1fr;}}
</style>

<div class="page-header">
    <h1><i class="fas fa-<?= $is_edit?'pen':'plus' ?>" style="color:#2563eb;margin-right:8px;font-size:1rem;"></i><?= $is_edit?'Edit':'Add' ?> Collection</h1>
    <p class="breadcrumb">
        <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <a href="/church/modules/finance/index.php" style="color:#9ca3af;text-decoration:none;">Finance</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <a href="/church/modules/finance/collections.php" style="color:#9ca3af;text-decoration:none;">Collections</a>
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
            <div class="fch"><div class="fchi" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-church"></i></div>
            <div><div class="fct">Collection Details</div><div style="font-size:0.72rem;color:#9ca3af;">Mass or event collection information</div></div></div>
            <div class="fcb">
                <div class="fg" style="margin-bottom:16px;">
                    <label class="lbl">Collection Type <span class="req">*</span></label>
                    <div class="type-toggle">
                        <button type="button" id="btn-cash" class="type-btn <?= (($old['collection_type']??'cash')==='cash')?'active-cash':'' ?>" onclick="setType('cash')"><i class="fas fa-peso-sign" style="margin-right:5px;"></i>Cash</button>
                        <button type="button" id="btn-inkind" class="type-btn <?= (($old['collection_type']??'')==='in-kind')?'active-inkind':'' ?>" onclick="setType('in-kind')"><i class="fas fa-box" style="margin-right:5px;"></i>In-Kind</button>
                    </div>
                    <input type="hidden" name="collection_type" id="collection_type" value="<?= htmlspecialchars($old['collection_type'] ?? 'cash') ?>">
                </div>
                <div class="fg3">
                    <div class="fg" style="grid-column:span 2;"><label class="lbl">Collection Name <span class="req">*</span></label>
                        <input type="text" name="name" class="inp" value="<?= ov($old,$rec??[],'name') ?>" placeholder="e.g. Sunday Mass Offertory" required></div>
                    <div class="fg"><label class="lbl">Date <span class="req">*</span></label>
                        <input type="date" name="date" class="inp" value="<?= ov($old,$rec??[],'date',date('Y-m-d')) ?>" max="<?= date('Y-m-d') ?>" required></div>
                </div>
                <div class="fg2" style="margin-top:14px;">
                    <div class="fg" id="amountField">
                        <label class="lbl">Amount <span class="req">*</span></label>
                        <div style="position:relative;"><span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.85rem;pointer-events:none;">₱</span>
                        <input type="number" name="amount" class="inp" style="padding-left:26px;" step="0.01" min="0" value="<?= ov($old,$rec??[],'amount','') ?>" placeholder="0.00"></div>
                    </div>
                    <div class="fg"><label class="lbl">Mass Schedule <span class="opt">(optional)</span></label>
                        <input type="text" name="time_schedule" class="inp" value="<?= ov($old,$rec??[],'time_schedule') ?>" placeholder="e.g. 7:00 AM Mass, 6:00 PM Novena"></div>
                </div>
                <div class="fg" style="margin-top:14px;"><label class="lbl">Notes <span class="opt">(optional)</span></label>
                    <textarea name="notes" class="txa" rows="2" placeholder="Any notes about this collection…"><?= ov($old,$rec??[],'notes') ?></textarea></div>
            </div>
        </div>
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 22px;background:#faf7f0;border:1px solid #ede8de;border-radius:0 0 14px 14px;">
            <a href="/church/modules/finance/collections.php" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn-save"><i class="fas fa-floppy-disk"></i> <?= $is_edit?'Save Changes':'Record Collection' ?></button>
        </div>
    </form>
    </div>
    <div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:20px;">
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Examples</p>
            <div style="font-size:0.78rem;color:#6b7280;display:flex;flex-direction:column;gap:7px;">
                <?php foreach(['Sunday Mass Offertory','Fiesta Collection','Abuloy (Wake Contribution)','Candle Offerings','Cemetery Sunday Collection'] as $ex): ?>
                <div style="cursor:pointer;padding:6px 10px;border-radius:6px;border:1px solid #ede8de;" onclick="document.querySelector('[name=name]').value='<?= $ex ?>';"><?= $ex ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
</div>
<script>
function setType(type){
    document.getElementById('collection_type').value=type;
    const af=document.getElementById('amountField');
    const bc=document.getElementById('btn-cash');
    const bi=document.getElementById('btn-inkind');
    if(type==='cash'){af.style.display='';bc.className='type-btn active-cash';bi.className='type-btn';}
    else{af.style.display='none';bc.className='type-btn';bi.className='type-btn active-inkind';}
}
setType(document.getElementById('collection_type').value);
</script>
<?php include $root . '/includes/footer.php'; ?>