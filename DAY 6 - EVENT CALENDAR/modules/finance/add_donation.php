<?php
// church/modules/finance/add_donation.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy', 'finance'])) {
    include $root . '/auth/access_denied.php'; exit;
}

$edit_id = (int)($_GET['edit'] ?? 0);
$rec = null;
if ($edit_id > 0) {
    $s = $conn->prepare("SELECT * FROM donations WHERE id = ?");
    $s->bind_param("i", $edit_id); $s->execute();
    $rec = $s->get_result()->fetch_assoc(); $s->close();
    if (!$rec) { $_SESSION['error'] = "Donation not found."; header('Location: /church/modules/finance/donations.php'); exit; }
}
$is_edit = $rec !== null;
// Pre-select type from URL (e.g. quick action link from finance/index.php)
$default_type = !$is_edit && isset($_GET['type']) ? $_GET['type'] : 'cash';
$errors = []; $old = $rec ?? [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old         = $_POST;
    $donor_name  = trim($_POST['donor_name']      ?? '');
    $contact     = trim($_POST['contact_number']  ?? '');
    $amount      = trim($_POST['amount']          ?? '');
    $dtype       = trim($_POST['donation_type']   ?? 'cash');
    $description = trim($_POST['description']     ?? '');
    $service_type= trim($_POST['service_type']    ?? '');
    $date        = trim($_POST['date']            ?? '');

    if ($donor_name === '')  $errors[] = "Donor name is required.";
    if ($date === '')        $errors[] = "Date is required.";
    if (in_array($dtype, ['cash', 'sacramental_fee'])) {
        if (!is_numeric($amount) || (float)$amount <= 0) $errors[] = "Amount must be a positive number.";
    }
    if ($dtype === 'sacramental_fee' && $service_type === '') $errors[] = "Please select the service type.";

    if (empty($errors)) {
        $amt_val = ($dtype === 'in-kind') ? 0.00 : (float)$amount;
        // Merge service_type into description for sacramental fees
        $desc_val = $description;
        if ($dtype === 'sacramental_fee' && $service_type !== '') {
            $desc_val = $service_type . ($description !== '' ? ' — ' . $description : '');
        }

        if ($is_edit) {
            $s = $conn->prepare("UPDATE donations SET donor_name=?, contact_number=?, amount=?, donation_type=?, description=?, date=? WHERE id=?");
            $s->bind_param("ssdsssi", $donor_name, $contact, $amt_val, $dtype, $desc_val, $date, $edit_id);
            $s->execute(); $s->close();
            $_SESSION['success'] = "Donation updated successfully.";
        } else {
            $s = $conn->prepare("INSERT INTO donations (donor_name, contact_number, amount, donation_type, description, date, created_by) VALUES (?,?,?,?,?,?,?)");
            $s->bind_param("ssdsssi", $donor_name, $contact, $amt_val, $dtype, $desc_val, $date, $current_user_id);
            $s->execute(); $s->close();
            $_SESSION['success'] = "Donation from \"{$donor_name}\" recorded successfully.";
        }
        header('Location: /church/modules/finance/donations.php'); exit;
    }
}

// For edit mode: detect if description has a service_type prefix
$prefill_service = '';
$prefill_desc    = $rec['description'] ?? '';
if (($rec['donation_type'] ?? '') === 'sacramental_fee') {
    $services = ['Wedding', 'Baptism', 'Funeral', 'Confirmation'];
    foreach ($services as $svc) {
        if (str_starts_with($prefill_desc, $svc)) {
            $prefill_service = $svc;
            $prefill_desc    = ltrim(substr($prefill_desc, strlen($svc)), ' —');
            break;
        }
    }
}

function ov($old, $rec, $key, $default = '') {
    return htmlspecialchars($old[$key] ?? $rec[$key] ?? $default);
}

$page_title = $is_edit ? "Edit Donation" : "Add Donation";
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
    .inp,.txa,.sel{padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.865rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif;}
    .inp:focus,.txa:focus,.sel:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,0.1);}
    .txa{resize:vertical;min-height:70px;}

    /* ── Type toggle: 3 options ── */
    .type-toggle{display:grid;grid-template-columns:1fr 1fr 1fr;gap:0;border:1px solid #ede8de;border-radius:8px;overflow:hidden;}
    .type-btn{padding:10px 8px;text-align:center;cursor:pointer;font-size:0.8rem;font-weight:600;border:none;background:#fff;color:#6b7280;transition:all 0.18s;border-right:1px solid #ede8de;line-height:1.3;}
    .type-btn:last-child{border-right:none;}
    .type-btn.active-cash       {background:#f0fdf4;color:#16a34a;}
    .type-btn.active-inkind     {background:#fef3c7;color:#d97706;}
    .type-btn.active-sacramental{background:#f5f3ff;color:#7c3aed;}

    /* ── Sacramental service selector ── */
    .service-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:4px;}
    .svc-btn{padding:9px 6px;border-radius:8px;border:1px solid #ede8de;background:#fff;cursor:pointer;text-align:center;font-size:0.78rem;font-weight:600;color:#6b7280;transition:all 0.18s;}
    .svc-btn:hover{border-color:#7c3aed;color:#7c3aed;}
    .svc-btn.active{background:#f5f3ff;border-color:#7c3aed;color:#7c3aed;}
    .svc-btn i{display:block;font-size:1rem;margin-bottom:4px;}

    .btn-save{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#16a34a;color:#fff;border:none;cursor:pointer;} .btn-save:hover{background:#15803d;}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;}
    .error-list{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:18px;}
    .error-list li{font-size:0.82rem;color:#dc2626;margin-bottom:3px;}
    @media(max-width:900px){#addLayout{grid-template-columns:1fr!important;}}
    @media(max-width:640px){.fg2{grid-template-columns:1fr;} .service-grid{grid-template-columns:repeat(2,1fr);} .type-toggle{grid-template-columns:1fr;}}
</style>

<div class="page-header">
    <h1><i class="fas fa-<?= $is_edit?'pen':'plus' ?>" style="color:#16a34a;margin-right:8px;font-size:1rem;"></i><?= $is_edit?'Edit':'Add' ?> Donation</h1>
    <p class="breadcrumb">
        <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <a href="/church/modules/finance/index.php" style="color:#9ca3af;text-decoration:none;">Finance</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <a href="/church/modules/finance/donations.php" style="color:#9ca3af;text-decoration:none;">Donations</a>
        <span style="margin:0 6px;color:#d1c9b8;">›</span>
        <span class="current"><?= $is_edit?'Edit':'Add' ?></span>
    </p>
</div>

<div style="padding:24px 24px 60px;">
    <?php if (!empty($errors)): ?>
    <div class="error-list">
        <div style="display:flex;align-items:center;gap:8px;margin-bottom:6px;">
            <i class="fas fa-circle-exclamation" style="color:#dc2626;"></i>
            <strong style="font-size:0.85rem;color:#dc2626;">Please fix the following:</strong>
        </div>
        <ul style="margin-left:16px;"><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 270px;gap:20px;align-items:start;" id="addLayout">
    <div>
    <form method="POST" id="donationForm">
        <div class="form-card">
            <div class="fch">
                <div class="fchi" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-hand-holding-heart"></i></div>
                <div>
                    <div class="fct">Donation Details</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Donor and amount information</div>
                </div>
            </div>
            <div class="fcb">

                <!-- ── Type toggle ── -->
                <div class="fg" style="margin-bottom:18px;">
                    <label class="lbl">Donation Type <span class="req">*</span></label>
                    <div class="type-toggle">
                        <button type="button" id="btn-cash"
                                class="type-btn <?= (($old['donation_type']??'cash')==='cash')?'active-cash':'' ?>"
                                onclick="setType('cash')">
                            <i class="fas fa-peso-sign" style="margin-right:4px;"></i> Cash
                        </button>
                        <button type="button" id="btn-inkind"
                                class="type-btn <?= (($old['donation_type']??'')==='in-kind')?'active-inkind':'' ?>"
                                onclick="setType('in-kind')">
                            <i class="fas fa-box" style="margin-right:4px;"></i> In-Kind
                        </button>
                        <button type="button" id="btn-sacramental"
                                class="type-btn <?= (($old['donation_type']??'')==='sacramental_fee')?'active-sacramental':'' ?>"
                                onclick="setType('sacramental_fee')">
                            <i class="fas fa-cross" style="margin-right:4px;"></i> Sacramental
                        </button>
                    </div>
                    <input type="hidden" name="donation_type" id="donation_type"
                           value="<?= htmlspecialchars($old['donation_type'] ?? $default_type) ?>">
                </div>

                <!-- ── Sacramental: service type picker ── -->
                <div class="fg" id="serviceField" style="margin-bottom:18px;display:none;">
                    <label class="lbl">Service Type <span class="req">*</span></label>
                    <div class="service-grid">
                        <?php
                        $services = [
                            ['Wedding',      'fa-rings-wedding'],
                            ['Baptism',      'fa-droplet'],
                            ['Funeral',      'fa-dove'],
                            ['Confirmation', 'fa-hands-praying'],
                        ];
                        $cur_svc = $old['service_type'] ?? $prefill_service;
                        foreach ($services as [$svc, $icon]):
                        ?>
                        <button type="button"
                                class="svc-btn <?= $cur_svc === $svc ? 'active' : '' ?>"
                                onclick="setSvc('<?= $svc ?>')">
                            <i class="fas <?= $icon ?>"></i><?= $svc ?>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <input type="hidden" name="service_type" id="service_type"
                           value="<?= htmlspecialchars($cur_svc) ?>">
                </div>

                <!-- ── Donor info ── -->
                <div class="fg2">
                    <div class="fg" style="grid-column:span 2;">
                        <label class="lbl">Donor / Requestor Name <span class="req">*</span></label>
                        <input type="text" name="donor_name" class="inp"
                               value="<?= ov($old,$rec??[],'donor_name') ?>"
                               placeholder="e.g. Juan dela Cruz" required>
                    </div>
                    <div class="fg">
                        <label class="lbl">Contact Number <span class="opt">(optional)</span></label>
                        <input type="text" name="contact_number" class="inp"
                               value="<?= ov($old,$rec??[],'contact_number') ?>"
                               placeholder="e.g. 09171234567">
                    </div>
                    <div class="fg">
                        <label class="lbl">Date <span class="req">*</span></label>
                        <input type="date" name="date" class="inp"
                               value="<?= ov($old,$rec??[],'date', date('Y-m-d')) ?>"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>

                <!-- ── Amount (cash + sacramental) ── -->
                <div class="fg" style="margin-top:14px;" id="amountField">
                    <label class="lbl" id="amountLabel">Amount <span class="req">*</span></label>
                    <div style="position:relative;">
                        <span style="position:absolute;left:12px;top:50%;transform:translateY(-50%);color:#9ca3af;font-size:0.85rem;pointer-events:none;">₱</span>
                        <input type="number" name="amount" class="inp" style="padding-left:26px;"
                               step="0.01" min="0"
                               value="<?= ov($old,$rec??[],'amount','') ?>" placeholder="0.00">
                    </div>
                </div>

                <!-- ── Description / Notes ── -->
                <div class="fg" style="margin-top:14px;">
                    <label class="lbl" id="descLabel">
                        Description <span class="opt">(optional)</span>
                    </label>
                    <input type="text" name="description" class="inp"
                           value="<?= htmlspecialchars($old['description'] ?? $prefill_desc) ?>"
                           id="descInput"
                           placeholder="e.g. Sunday offertory donation">
                </div>

            </div>
        </div>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:16px 22px;background:#faf7f0;border:1px solid #ede8de;border-radius:0 0 14px 14px;">
            <a href="/church/modules/finance/donations.php" class="btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn-save">
                <i class="fas fa-floppy-disk"></i> <?= $is_edit?'Save Changes':'Record Donation' ?>
            </button>
        </div>
    </form>
    </div>

    <!-- ── Sidebar ── -->
    <div style="display:flex;flex-direction:column;gap:14px;position:sticky;top:20px;">

        <!-- Type guide -->
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Type Guide</p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="padding:10px 12px;background:#f0fdf4;border-radius:8px;border:1px solid #bbf7d0;">
                    <p style="font-size:0.78rem;font-weight:600;color:#15803d;margin-bottom:2px;"><i class="fas fa-peso-sign" style="margin-right:5px;"></i>Cash Donation</p>
                    <p style="font-size:0.72rem;color:#6b7280;">Monetary donations — amount is required.</p>
                </div>
                <div style="padding:10px 12px;background:#fef3c7;border-radius:8px;border:1px solid #fde68a;">
                    <p style="font-size:0.78rem;font-weight:600;color:#d97706;margin-bottom:2px;"><i class="fas fa-box" style="margin-right:5px;"></i>In-Kind Donation</p>
                    <p style="font-size:0.72rem;color:#6b7280;">Goods, items, or services. Describe what was donated.</p>
                </div>
                <div style="padding:10px 12px;background:#f5f3ff;border-radius:8px;border:1px solid #ddd6fe;">
                    <p style="font-size:0.78rem;font-weight:600;color:#7c3aed;margin-bottom:2px;"><i class="fas fa-cross" style="margin-right:5px;"></i>Sacramental Fee</p>
                    <p style="font-size:0.72rem;color:#6b7280;">Fees paid for Wedding, Baptism, Funeral, or Confirmation services.</p>
                </div>
            </div>
        </div>

        <?php if ($is_edit): ?>
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Record Info</p>
            <div style="font-size:0.8rem;color:#374151;">
                <div style="display:flex;justify-content:space-between;padding:6px 0;border-bottom:1px solid #f5f0e8;">
                    <span style="color:#9ca3af;">Recorded</span>
                    <span><?= date('M j, Y', strtotime($rec['created_at'])) ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;padding:6px 0;">
                    <span style="color:#9ca3af;">Type</span>
                    <span><?= ucfirst(str_replace(['_fee','-'], [' Fee',' '], $rec['donation_type'])) ?></span>
                </div>
            </div>
        </div>
        <?php endif; ?>
    </div>
    </div>
</div>

<script>
const INIT_TYPE = <?= json_encode($old['donation_type'] ?? $default_type) ?>;

function setType(type) {
    document.getElementById('donation_type').value = type;
    const af  = document.getElementById('amountField');
    const sf  = document.getElementById('serviceField');
    const dl  = document.getElementById('descLabel');
    const di  = document.getElementById('descInput');
    const al  = document.getElementById('amountLabel');
    const bc  = document.getElementById('btn-cash');
    const bi  = document.getElementById('btn-inkind');
    const bs  = document.getElementById('btn-sacramental');
    const inp = document.querySelector('[name=amount]');

    // Reset button states
    bc.className = 'type-btn'; bi.className = 'type-btn'; bs.className = 'type-btn';

    if (type === 'cash') {
        bc.className = 'type-btn active-cash';
        af.style.display = '';
        sf.style.display = 'none';
        al.innerHTML = 'Amount <span class="req">*</span>';
        dl.innerHTML = 'Description <span class="opt">(optional)</span>';
        di.placeholder = 'e.g. Sunday offertory donation';
        inp.required = true;
    } else if (type === 'in-kind') {
        bi.className = 'type-btn active-inkind';
        af.style.display = 'none';
        sf.style.display = 'none';
        dl.innerHTML = 'Item Description <span class="req">*</span>';
        di.placeholder = 'e.g. 50 sacks of rice, school supplies, etc.';
        inp.required = false;
    } else {
        bs.className = 'type-btn active-sacramental';
        af.style.display = '';
        sf.style.display = '';
        al.innerHTML = 'Fee Amount <span class="req">*</span>';
        dl.innerHTML = 'Additional Notes <span class="opt">(optional)</span>';
        di.placeholder = 'e.g. Includes candles and certificate fee';
        inp.required = true;
    }
}

function setSvc(svc) {
    document.getElementById('service_type').value = svc;
    document.querySelectorAll('.svc-btn').forEach(b => b.classList.remove('active'));
    event.currentTarget.classList.add('active');
}

// Init on page load
setType(INIT_TYPE);
</script>
<?php include $root . '/includes/footer.php'; ?>