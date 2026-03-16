<?php
// church/modules/members/add_volunteer.php
// Phase 5 — Step 5.6: Add new volunteer
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    http_response_code(403);
    include $root . '/auth/access_denied.php';
    exit;
}

// Fetch existing roles for datalist suggestions
$roles_r = $conn->query("SELECT DISTINCT role FROM volunteers WHERE role IS NOT NULL AND role != '' AND is_archived = 0 ORDER BY role");
$existing_roles = [];
while ($r = $roles_r->fetch_row()) $existing_roles[] = $r[0];

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $name           = trim($_POST['name']           ?? '');
    $contact_number = trim($_POST['contact_number'] ?? '');
    $email          = trim($_POST['email']          ?? '');
    $address        = trim($_POST['address']        ?? '');
    $role           = trim($_POST['role']           ?? '');
    $joined_date    = trim($_POST['joined_date']    ?? '') ?: null;
    $notes          = trim($_POST['notes']          ?? '');

    if ($name === '') $errors[] = "Full name is required.";
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL))
        $errors[] = "Please enter a valid email address.";

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO volunteers (name, contact_number, email, address, role, joined_date, notes, created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param("sssssssi",
            $name, $contact_number, $email, $address, $role, $joined_date, $notes, $current_user_id
        );
        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            $_SESSION['success'] = "Volunteer \"{$name}\" added successfully.";
            header("Location: /church/modules/members/volunteers_list.php?id={$new_id}");
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
            $stmt->close();
        }
    }
}

$page_title = 'Add Volunteer';
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
    .form-input::placeholder { color:#c4b89a; }
    .form-textarea { resize:vertical;min-height:80px; }
    .error-list { background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px; }
    .error-list ul { margin:6px 0 0 16px;padding:0; }
    .error-list li { font-size:0.82rem;color:#dc2626;margin-bottom:3px; }
    .btn-save { display:inline-flex;align-items:center;gap:7px;padding:10px 22px;border-radius:8px;font-size:0.845rem;font-weight:600;background:#8b5cf6;color:#fff;border:none;cursor:pointer;transition:background 0.15s; }
    .btn-save:hover { background:#7c3aed; }
    .btn-cancel-link { display:inline-flex;align-items:center;gap:6px;padding:10px 16px;border-radius:8px;font-size:0.845rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;transition:all 0.15s; }
    .btn-cancel-link:hover { background:#faf7f0;border-color:#c4b89a;color:#0f2044; }
    @media (max-width:768px) { .form-grid-2 { grid-template-columns:1fr; } .col-span-2 { grid-column:span 1; } #formLayout { grid-template-columns:1fr !important; } }
    @media (min-width:769px) { #formLayout > div:last-child { position:sticky;top:20px; } }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1><i class="fas fa-plus" style="color:#8b5cf6;margin-right:8px;font-size:1rem;"></i>Add Volunteer</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/members/index.php?tab=volunteers" style="color:#9ca3af;text-decoration:none;">Members & Volunteers</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Add Volunteer</span>
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

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="formLayout">

        <form method="POST" id="addVolunteerForm">

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
                            <input type="text" name="name" class="form-input <?= in_array('Full name is required.', $errors) ? 'error' : '' ?>"
                                   value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                                   placeholder="e.g. Juan dela Cruz" required>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Contact Number <span class="opt">(optional)</span></label>
                            <input type="text" name="contact_number" class="form-input"
                                   value="<?= htmlspecialchars($old['contact_number'] ?? '') ?>"
                                   placeholder="e.g. 09171234567">
                        </div>
                        <div class="form-group">
                            <label class="form-label">Email Address <span class="opt">(optional)</span></label>
                            <input type="email" name="email" class="form-input"
                                   value="<?= htmlspecialchars($old['email'] ?? '') ?>"
                                   placeholder="e.g. juan@email.com">
                        </div>
                        <div class="form-group col-span-2">
                            <label class="form-label">Home Address <span class="opt">(optional)</span></label>
                            <input type="text" name="address" class="form-input"
                                   value="<?= htmlspecialchars($old['address'] ?? '') ?>"
                                   placeholder="e.g. Purok 5, Tugbungan, Zamboanga City">
                        </div>
                    </div>
                </div>
            </div>

            <!-- Volunteer Role -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#fdf8ec;color:#b8933a;"><i class="fas fa-star"></i></div>
                    <div><div class="form-card-title">Ministry & Role</div><div style="font-size:0.72rem;color:#9ca3af;">What this volunteer does for the parish</div></div>
                </div>
                <div class="form-card-body">
                    <div class="form-grid-2">
                        <div class="form-group">
                            <label class="form-label">Role / Ministry <span class="opt">(optional)</span></label>
                            <input type="text" name="role" class="form-input" list="rolesSuggestions"
                                   value="<?= htmlspecialchars($old['role'] ?? '') ?>"
                                   placeholder="e.g. Lector, Choir, Sacristan">
                            <?php if (!empty($existing_roles)): ?>
                            <datalist id="rolesSuggestions">
                                <?php foreach ($existing_roles as $r): ?>
                                <option value="<?= htmlspecialchars($r) ?>">
                                <?php endforeach; ?>
                            </datalist>
                            <?php endif; ?>
                        </div>
                        <div class="form-group">
                            <label class="form-label">Date Joined <span class="opt">(optional)</span></label>
                            <input type="date" name="joined_date" class="form-input"
                                   value="<?= htmlspecialchars($old['joined_date'] ?? '') ?>"
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
                    <textarea name="notes" class="form-textarea" rows="3"
                              placeholder="Any additional information about this volunteer…"><?= htmlspecialchars($old['notes'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Actions -->
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;
                        padding:16px 22px;background:#faf7f0;border:1px solid #ede8de;
                        border-radius:0 0 14px 14px;border-top:1px solid #f3ede3;">
                <a href="/church/modules/members/index.php?tab=volunteers" class="btn-cancel-link">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-save" id="submitBtn">
                    <i class="fas fa-floppy-disk"></i> Save Volunteer
                </button>
            </div>

        </form>

        <!-- Sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px;">
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#4c1d95,#8b5cf6);padding:14px 18px;">
                    <p style="font-family:'Playfair Display',serif;font-size:0.92rem;color:#fff;font-weight:600;margin-bottom:2px;">New Volunteer</p>
                    <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Parish Volunteer Registry</p>
                </div>
                <div style="padding:14px 18px;">
                    <p style="font-size:0.75rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Required Fields</p>
                    <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;">
                        <i class="fas fa-check-circle" style="color:#8b5cf6;font-size:0.75rem;"></i> Full Name
                    </div>
                    <hr style="border:none;border-top:1px solid #f3ede3;margin:12px 0;">
                    <p style="font-size:0.72rem;color:#9ca3af;line-height:1.5;">
                        All other fields are optional. The Role field supports autocomplete from existing roles.
                    </p>
                </div>
            </div>
            <?php if (!empty($existing_roles)): ?>
            <div style="background:#f5f3ff;border:1px solid #ddd6fe;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:600;color:#7c3aed;margin-bottom:8px;display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-tags" style="font-size:0.75rem;"></i> Existing Roles
                </p>
                <div style="display:flex;flex-wrap:wrap;gap:5px;">
                    <?php foreach ($existing_roles as $r): ?>
                    <span onclick="document.querySelector('[name=role]').value='<?= addslashes(htmlspecialchars($r)) ?>'"
                          style="font-size:0.72rem;padding:3px 9px;border-radius:6px;background:rgba(139,92,246,0.1);color:#7c3aed;cursor:pointer;border:1px solid #ddd6fe;transition:all 0.15s;"
                          onmouseover="this.style.background='#8b5cf6';this.style.color='#fff'"
                          onmouseout="this.style.background='rgba(139,92,246,0.1)';this.style.color='#7c3aed'">
                        <?= htmlspecialchars($r) ?>
                    </span>
                    <?php endforeach; ?>
                </div>
            </div>
            <?php endif; ?>
        </div>

    </div>
</div>

<script>
document.getElementById('addVolunteerForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>

<?php include $root . '/includes/footer.php'; ?>