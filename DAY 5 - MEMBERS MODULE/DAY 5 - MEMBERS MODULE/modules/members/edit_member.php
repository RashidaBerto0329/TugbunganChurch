<?php
// church/modules/members/edit_member.php
// Phase 5 — Step 5.4: Edit existing parish member
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    http_response_code(403);
    include $root . '/auth/access_denied.php';
    exit;
}

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /church/modules/members/index.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM members WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    $_SESSION['error'] = "Member not found.";
    header('Location: /church/modules/members/index.php');
    exit;
}

$errors = [];
$old    = $member; // pre-fill

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $name              = trim($_POST['name']              ?? '');
    $contact_number    = trim($_POST['contact_number']    ?? '');
    $email             = trim($_POST['email']             ?? '');
    $address           = trim($_POST['address']           ?? '');
    $membership_status = trim($_POST['membership_status'] ?? 'active');
    $joined_date       = trim($_POST['joined_date']       ?? '') ?: null;
    $notes             = trim($_POST['notes']             ?? '');

    if ($name === '') $errors[] = "Full name is required.";
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Please enter a valid email address.";
    if (!in_array($membership_status, ['active', 'inactive'])) $membership_status = 'active';

    if (empty($errors)) {
        $stmt = $conn->prepare("
            UPDATE members
            SET name=?, contact_number=?, email=?, address=?,
                membership_status=?, joined_date=?, notes=?,
                updated_at=NOW()
            WHERE id=?
        ");
        $stmt->bind_param("sssssssi",
            $name, $contact_number, $email, $address,
            $membership_status, $joined_date, $notes, $id
        );
        if ($stmt->execute()) {
            $stmt->close();
            $_SESSION['success'] = "Member profile updated successfully.";
            header("Location: /church/modules/members/view_member.php?id={$id}");
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
            $stmt->close();
        }
    }
}

// Detect changed fields for sidebar tracker
$changed_fields = [];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $watched = ['name','contact_number','email','address','membership_status','joined_date','notes'];
    foreach ($watched as $f) {
        $new_val = trim($_POST[$f] ?? '');
        $old_val = trim($member[$f] ?? '');
        if ($new_val !== $old_val) $changed_fields[] = $f;
    }
}

$page_title = 'Edit — ' . htmlspecialchars($member['name']);
include $root . '/includes/header.php';
?>

<style>
    .form-card { background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:18px; }
    .form-card-header { padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px; }
    .form-card-header-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0; }
    .form-card-title { font-family:'Playfair Display',serif;font-size:0.9rem;font-weight:600;color:#0f2044; }
    .form-card-body { padding:20px 22px; }
    .form-grid-2 { display:grid;grid-template-columns:1fr 1fr;gap:14px; }
    .col-span-2 { grid-column:span 2; }
    .form-group { display:flex;flex-direction:column;gap:5px; }
    .form-label { font-size:0.79rem;font-weight:600;color:#374151; }
    .form-label .req { color:#ef4444;margin-left:2px; }
    .form-label .opt { color:#9ca3af;font-weight:400;font-size:0.71rem;margin-left:4px; }
    .form-input,.form-select,.form-textarea { padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.855rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif; }
    .form-input:focus,.form-select:focus,.form-textarea:focus { border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,0.1); }
    .form-input.error { border-color:#ef4444; }
    .form-input.changed,.form-select.changed,.form-textarea.changed { border-color:#f97316;box-shadow:0 0 0 3px rgba(249,115,22,0.1); }
    .form-input::placeholder { color:#c4b89a; }
    .form-textarea { resize:vertical;min-height:80px; }
    .error-list { background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px; }
    .error-list ul { margin:6px 0 0 16px;padding:0; }
    .error-list li { font-size:0.82rem;color:#dc2626;margin-bottom:3px; }
    .btn-save { display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:8px;font-size:0.845rem;font-weight:600;background:#8b5cf6;color:#fff;border:none;cursor:pointer;transition:background 0.15s; }
    .btn-save:hover { background:#7c3aed; }
    .btn-cancel-link { display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:8px;font-size:0.845rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;transition:all 0.15s; }
    .btn-cancel-link:hover { background:#faf7f0;border-color:#c4b89a;color:#0f2044; }
    .change-tracker { background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px; }
    .changed-field-item { display:flex;align-items:center;gap:7px;font-size:0.78rem;color:#f97316;margin-bottom:6px; }
    @media (max-width:768px) { .form-grid-2 { grid-template-columns:1fr; } .col-span-2 { grid-column:span 1; } #editLayout { grid-template-columns:1fr !important; } }
    @media (min-width:769px) { #editLayout > div:last-child { position:sticky;top:20px; } }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1><i class="fas fa-pen" style="color:#8b5cf6;margin-right:8px;font-size:1rem;"></i>Edit Member</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/members/index.php" style="color:#9ca3af;text-decoration:none;">Members & Volunteers</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/members/view_member.php?id=<?= $id ?>" style="color:#9ca3af;text-decoration:none;"><?= htmlspecialchars($member['name']) ?></a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Edit</span>
        </p>
    </div>
</div>

<div style="padding:24px 24px 60px;">

    <?php if (!empty($errors)): ?>
    <div class="error-list">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-circle-exclamation" style="color:#dc2626;"></i>
            <strong style="font-size:0.84rem;color:#dc2626;">Please fix the following errors:</strong>
        </div>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="editLayout">

        <form method="POST" id="editMemberForm">

            <!-- Personal Info -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#f5f3ff;color:#8b5cf6;"><i class="fas fa-id-card"></i></div>
                    <div><div class="form-card-title">Personal Information</div></div>
                </div>
                <div class="form-card-body">
                    <div class="form-grid-2">
                        <div class="form-group col-span-2">
                            <label class="form-label">Full Name <span class="req">*</span></label>
                            <input type="text" name="name" id="f_name"
                                   class="form-input <?= in_array('Full name is required.', $errors) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($member['name']) ?>" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="opt">(optional)</span></label>
                            <input type="text" name="contact_number" id="f_contact_number" class="form-input"
                                   value="<?= htmlspecialchars($old['contact_number'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($member['contact_number'] ?? '') ?>">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="opt">(optional)</span></label>
                            <input type="email" name="email" id="f_email" class="form-input"
                                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($member['email'] ?? '') ?>">
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Home Address <span class="opt">(optional)</span></label>
                            <input type="text" name="address" id="f_address" class="form-input"
                                   value="<?= htmlspecialchars($old['address'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($member['address'] ?? '') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Membership -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-church"></i></div>
                    <div><div class="form-card-title">Membership Details</div></div>
                </div>
                <div class="form-card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Membership Status</label>
                            <select name="membership_status" id="f_membership_status" class="form-select"
                                    data-original="<?= htmlspecialchars($member['membership_status']) ?>">
                                <option value="active"   <?= ($old['membership_status'] ?? '') === 'active'   ? 'selected' : '' ?>>Active</option>
                                <option value="inactive" <?= ($old['membership_status'] ?? '') === 'inactive' ? 'selected' : '' ?>>Inactive</option>
                            </select>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date Joined <span class="opt">(optional)</span></label>
                            <input type="date" name="joined_date" id="f_joined_date" class="form-input"
                                   value="<?= htmlspecialchars($old['joined_date'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($member['joined_date'] ?? '') ?>"
                                   max="<?= date('Y-m-d') ?>">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div>
                    <div><div class="form-card-title">Notes</div></div>
                </div>
                <div class="form-card-body">
                    <textarea name="notes" id="f_notes" class="form-textarea" rows="3"
                              data-original="<?= htmlspecialchars($member['notes'] ?? '') ?>"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;
                        padding:16px 22px;background:#faf7f0;border:1px solid #ede8de;
                        border-radius:0 0 14px 14px;border-top:1px solid #f3ede3;">
                <a href="/church/modules/members/view_member.php?id=<?= $id ?>" class="btn-cancel-link" id="cancelBtn">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-save" id="submitBtn">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                </button>
            </div>

        </form>

        <!-- Sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Editing header -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#4c1d95,#8b5cf6);padding:14px 18px;">
                    <p style="font-family:'Playfair Display',serif;font-size:0.92rem;color:#fff;font-weight:600;margin-bottom:2px;">
                        <?= htmlspecialchars($member['name']) ?>
                    </p>
                    <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Editing Member Profile</p>
                </div>
                <div style="padding:14px 18px;">
                    <div class="change-tracker" style="border:none;padding:0;">
                        <p style="font-size:0.75rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:8px;">
                            Changed Fields
                        </p>
                        <div id="changedFieldsList">
                            <?php if (!empty($changed_fields)): ?>
                                <?php
                                $labels = ['name'=>'Full Name','contact_number'=>'Contact Number','email'=>'Email','address'=>'Address','membership_status'=>'Status','joined_date'=>'Date Joined','notes'=>'Notes'];
                                foreach ($changed_fields as $cf): ?>
                                <div class="changed-field-item">
                                    <i class="fas fa-circle" style="font-size:0.35rem;"></i>
                                    <?= $labels[$cf] ?? $cf ?>
                                </div>
                                <?php endforeach; ?>
                            <?php else: ?>
                            <p style="font-size:0.78rem;color:#c4b89a;font-style:italic;" id="noChangesMsg">No changes yet</p>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
            </div>

            <div style="background:#fff7ed;border:1px solid #fed7aa;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.8rem;font-weight:600;color:#ea580c;display:flex;align-items:center;gap:6px;margin-bottom:6px;">
                    <i class="fas fa-triangle-exclamation" style="font-size:0.75rem;"></i> Unsaved changes
                </p>
                <p style="font-size:0.74rem;color:#c2410c;line-height:1.5;">Fields outlined in <strong>orange</strong> have been modified. Click "Save Changes" to apply.</p>
            </div>

        </div>

    </div>
</div>

<script>
const fieldIds = ['f_name','f_contact_number','f_email','f_address','f_membership_status','f_joined_date','f_notes'];
const fieldLabels = {
    f_name: 'Full Name', f_contact_number: 'Contact Number',
    f_email: 'Email', f_address: 'Address',
    f_membership_status: 'Status', f_joined_date: 'Date Joined', f_notes: 'Notes'
};

function updateChanges() {
    const changed = [];
    fieldIds.forEach(fid => {
        const el = document.getElementById(fid);
        if (!el) return;
        const orig = el.dataset.original ?? '';
        const curr = el.value ?? '';
        const isChanged = curr.trim() !== orig.trim();
        el.classList.toggle('changed', isChanged);
        el.classList.remove('error');
        if (isChanged) changed.push(fieldLabels[fid]);
    });
    const container = document.getElementById('changedFieldsList');
    const noMsg = document.getElementById('noChangesMsg');
    if (changed.length === 0) {
        container.innerHTML = '<p style="font-size:0.78rem;color:#c4b89a;font-style:italic;" id="noChangesMsg">No changes yet</p>';
    } else {
        container.innerHTML = changed.map(l =>
            `<div class="changed-field-item"><i class="fas fa-circle" style="font-size:0.35rem;"></i> ${l}</div>`
        ).join('');
    }
}

fieldIds.forEach(fid => {
    const el = document.getElementById(fid);
    if (el) el.addEventListener('input', updateChanges);
    if (el) el.addEventListener('change', updateChanges);
});
updateChanges();

let formDirty = false;
document.getElementById('editMemberForm').addEventListener('input', () => formDirty = true);
document.getElementById('editMemberForm').addEventListener('change', () => formDirty = true);
document.getElementById('cancelBtn').addEventListener('click', function(e) {
    if (formDirty && !confirm('Discard unsaved changes?')) e.preventDefault();
});
document.getElementById('editMemberForm').addEventListener('submit', function() {
    formDirty = false;
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>

<?php include $root . '/includes/footer.php'; ?>