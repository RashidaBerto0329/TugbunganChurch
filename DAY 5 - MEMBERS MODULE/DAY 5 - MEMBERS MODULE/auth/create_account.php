<?php
// church/auth/create_account.php
$allowed_roles = ['admin'];
require_once __DIR__ . '/check_session.php';
require_once __DIR__ . '/../config/db.php';

$page_title = 'Manage Accounts';

$error   = '';
$success = '';

$accounts = [];
$res = $conn->query("SELECT id, name, email, role, created_at FROM users WHERE role != 'parishioner' ORDER BY created_at DESC");
while ($row = $res->fetch_assoc()) $accounts[] = $row;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'create') {
        $name     = trim($_POST['name']     ?? '');
        $email    = trim($_POST['email']    ?? '');
        $role     = trim($_POST['role']     ?? '');
        $password = trim($_POST['password'] ?? '');
        $confirm  = trim($_POST['confirm']  ?? '');
        $valid_roles = ['admin', 'clergy', 'finance'];

        if (empty($name) || empty($email) || empty($role) || empty($password)) {
            $error = 'All fields are required.';
        } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $error = 'Please enter a valid email address.';
        } elseif (!in_array($role, $valid_roles, true)) {
            $error = 'Invalid role selected.';
        } elseif (strlen($password) < 8) {
            $error = 'Password must be at least 8 characters.';
        } elseif ($password !== $confirm) {
            $error = 'Passwords do not match.';
        } else {
            $chk = $conn->prepare("SELECT id FROM users WHERE email = ? LIMIT 1");
            $chk->bind_param("s", $email);
            $chk->execute();
            $chk->store_result();
            if ($chk->num_rows > 0) {
                $error = 'An account with that email already exists.';
            } else {
                $hash = password_hash($password, PASSWORD_BCRYPT);
                $ins  = $conn->prepare("INSERT INTO users (name, email, password, role) VALUES (?, ?, ?, ?)");
                $ins->bind_param("ssss", $name, $email, $hash, $role);
                if ($ins->execute()) {
                    $success = "Account for \"{$name}\" created successfully.";
                    $accounts = [];
                    $res2 = $conn->query("SELECT id, name, email, role, created_at FROM users WHERE role != 'parishioner' ORDER BY created_at DESC");
                    while ($row = $res2->fetch_assoc()) $accounts[] = $row;
                } else {
                    $error = 'Database error. Please try again.';
                }
                $ins->close();
            }
            $chk->close();
        }
    }

    if ($action === 'delete') {
        $del_id = (int)($_POST['user_id'] ?? 0);
        if ($del_id === $current_user_id) {
            $error = 'You cannot delete your own account.';
        } elseif ($del_id > 0) {
            $del = $conn->prepare("DELETE FROM users WHERE id = ? AND role != 'parishioner'");
            $del->bind_param("i", $del_id);
            if ($del->execute() && $del->affected_rows > 0) {
                $success = 'Account deleted successfully.';
            } else {
                $error = 'Could not delete that account.';
            }
            $del->close();
            $accounts = [];
            $res3 = $conn->query("SELECT id, name, email, role, created_at FROM users WHERE role != 'parishioner' ORDER BY created_at DESC");
            while ($row = $res3->fetch_assoc()) $accounts[] = $row;
        }
    }
}

// Role counts
$role_counts = ['admin' => 0, 'clergy' => 0, 'finance' => 0];
foreach ($accounts as $a) {
    if (isset($role_counts[$a['role']])) $role_counts[$a['role']]++;
}

include __DIR__ . '/../includes/header.php';
?>

<style>
/* ══════════════════════════════════════════════════════
   MANAGE ACCOUNTS — REVAMPED
══════════════════════════════════════════════════════ */

.ma-wrapper {
    padding: 28px 32px 60px;
    width: 100%;
    box-sizing: border-box;
}

/* ── Stats strip ── */
.ma-stats-strip {
    display: grid;
    grid-template-columns: repeat(4, 1fr);
    gap: 14px;
    margin-bottom: 28px;
}
.ma-stat-card {
    background: #fff;
    border: 1px solid #d1cdc4;
    border-radius: 12px;
    padding: 18px 20px;
    display: flex;
    align-items: center;
    gap: 14px;
    box-shadow: 0 1px 6px rgba(15,32,68,0.06);
    position: relative;
    overflow: hidden;
}
.ma-stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--stat-color, #b8933a);
    border-radius: 12px 12px 0 0;
}
.ma-stat-icon {
    width: 42px; height: 42px;
    border-radius: 10px;
    background: var(--stat-bg, #fdf8ec);
    color: var(--stat-color, #b8933a);
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    flex-shrink: 0;
}
.ma-stat-value {
    font-family: 'Playfair Display', serif;
    font-size: 1.6rem;
    font-weight: 700;
    color: #0f2044;
    line-height: 1;
}
.ma-stat-label {
    font-size: 0.72rem;
    color: #9ca3af;
    font-weight: 500;
    text-transform: uppercase;
    letter-spacing: 0.05em;
    margin-top: 3px;
}

/* ── Main layout ── */
.ma-grid {
    display: grid;
    grid-template-columns: 400px minmax(0, 1fr);
    gap: 22px;
    align-items: start;
}

/* ── Form card ── */
.ma-form-card {
    background: #fff;
    border: 1px solid #d1cdc4;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(15,32,68,0.08);
    position: sticky;
    top: 20px;
}
.ma-form-header {
    background: linear-gradient(135deg, #0f2044 0%, #162d5c 55%, #1a3870 100%);
    padding: 22px 24px;
    position: relative;
    overflow: hidden;
}
.ma-form-header::after {
    content: '';
    position: absolute;
    right: -20px; bottom: -20px;
    width: 100px; height: 100px;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
    pointer-events: none;
}
.ma-form-header-icon {
    width: 44px; height: 44px;
    border-radius: 12px;
    background: rgba(224,192,96,0.15);
    display: flex; align-items: center; justify-content: center;
    color: #e0c060;
    font-size: 1.1rem;
    margin-bottom: 12px;
}
.ma-form-header-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.1rem;
    color: #fff;
    font-weight: 600;
    margin-bottom: 4px;
}
.ma-form-header-sub {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
}
.ma-form-body {
    padding: 24px;
}

/* ── Form fields ── */
.ma-field {
    margin-bottom: 16px;
}
.ma-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.03em;
    margin-bottom: 6px;
    text-transform: uppercase;
}
.ma-input-wrap {
    position: relative;
}
.ma-input-wrap .field-icon {
    position: absolute;
    left: 12px; top: 50%;
    transform: translateY(-50%);
    color: #c4b89a;
    font-size: 0.78rem;
    pointer-events: none;
    z-index: 1;
}
.ma-input {
    width: 100%;
    padding: 10px 14px 10px 36px;
    border: 1.5px solid #e0dbd2;
    border-radius: 9px;
    font-size: 0.855rem;
    font-family: 'DM Sans', sans-serif;
    color: #1a1a2e;
    background: #fdfcfa;
    outline: none;
    transition: border-color 0.18s, box-shadow 0.18s, background 0.18s;
    box-sizing: border-box;
}
.ma-input:focus {
    border-color: #b8933a;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(184,147,58,0.12);
}
.ma-input::placeholder { color: #c4b89a; }

/* Role selector pills */
.role-pills {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 8px;
    margin-top: 2px;
}
.role-pill input[type="radio"] { display: none; }
.role-pill label {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 5px;
    padding: 10px 8px;
    border: 1.5px solid #e0dbd2;
    border-radius: 10px;
    cursor: pointer;
    transition: all 0.18s;
    background: #fdfcfa;
    text-align: center;
}
.role-pill label .pill-icon {
    width: 30px; height: 30px;
    border-radius: 8px;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    background: #f3ede3;
    color: #9ca3af;
    transition: all 0.18s;
}
.role-pill label .pill-name {
    font-size: 0.72rem;
    font-weight: 600;
    color: #9ca3af;
    letter-spacing: 0.04em;
    text-transform: uppercase;
    transition: color 0.18s;
}
.role-pill input:checked + label {
    border-color: var(--pill-color);
    background: var(--pill-bg);
    box-shadow: 0 0 0 3px var(--pill-glow);
}
.role-pill input:checked + label .pill-icon {
    background: var(--pill-color);
    color: #fff;
}
.role-pill input:checked + label .pill-name {
    color: var(--pill-color);
}
.role-pill label:hover {
    border-color: #c4b89a;
    background: #faf7f0;
}

/* Divider */
.ma-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, #e8e0d0, transparent);
    margin: 18px 0;
}

/* Submit btn */
.ma-submit {
    width: 100%;
    padding: 12px;
    background: linear-gradient(135deg, #0f2044, #1a3870);
    color: #fff;
    border: none;
    border-radius: 9px;
    font-family: 'DM Sans', sans-serif;
    font-size: 0.855rem;
    font-weight: 600;
    letter-spacing: 0.05em;
    cursor: pointer;
    display: flex; align-items: center; justify-content: center; gap: 8px;
    transition: all 0.2s;
    box-shadow: 0 3px 12px rgba(15,32,68,0.2);
}
.ma-submit:hover {
    background: linear-gradient(135deg, #162d5c, #1e4080);
    box-shadow: 0 5px 20px rgba(15,32,68,0.3);
    transform: translateY(-1px);
}

/* ── Accounts table card ── */
.ma-table-card {
    background: #fff;
    border: 1px solid #d1cdc4;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(15,32,68,0.08);
}
.ma-table-header {
    padding: 18px 22px;
    border-bottom: 2px solid #f0ebe0;
    background: #fdfcfa;
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 12px;
}
.ma-table-title {
    font-family: 'Playfair Display', serif;
    font-size: 1rem;
    font-weight: 600;
    color: #0f2044;
    display: flex;
    align-items: center;
    gap: 10px;
}
.ma-table-title-icon {
    width: 34px; height: 34px;
    border-radius: 8px;
    background: #fdf8ec;
    display: flex; align-items: center; justify-content: center;
    color: #b8933a;
    font-size: 0.85rem;
}
.ma-role-pills-header {
    display: flex;
    gap: 7px;
    flex-wrap: wrap;
}
.ma-role-chip {
    display: inline-flex;
    align-items: center;
    gap: 5px;
    font-size: 0.7rem;
    font-weight: 600;
    padding: 4px 10px;
    border-radius: 99px;
    letter-spacing: 0.03em;
}

/* Table */
.ma-table {
    width: 100%;
    border-collapse: collapse;
}
.ma-table th {
    background: #f5f0e8;
    padding: 11px 18px;
    text-align: left;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #6b5f4e;
    border-bottom: 2px solid #e8e0d0;
    white-space: nowrap;
}
.ma-table td {
    padding: 14px 18px;
    border-bottom: 1px solid #f3ede3;
    vertical-align: middle;
}
.ma-table tr:last-child td { border-bottom: none; }
.ma-table tbody tr { transition: background 0.15s; }
.ma-table tbody tr:hover { background: #faf8f5; }

/* Avatar */
.ma-avatar {
    width: 38px; height: 38px;
    border-radius: 50%;
    background: linear-gradient(135deg, #0f2044, #1a3870);
    color: #e0c060;
    display: flex; align-items: center; justify-content: center;
    font-size: 0.85rem;
    font-weight: 700;
    flex-shrink: 0;
    box-shadow: 0 2px 8px rgba(15,32,68,0.2);
}

/* Role badges */
.rb {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.7rem; font-weight: 700;
    padding: 4px 10px; border-radius: 99px;
    letter-spacing: 0.05em; text-transform: uppercase;
}
.rb-admin   { background: rgba(184,147,58,0.12); color: #9a7820; border: 1px solid rgba(184,147,58,0.3); }
.rb-clergy  { background: #eff6ff; color: #1d4ed8; border: 1px solid #bfdbfe; }
.rb-finance { background: #f0fdf4; color: #15803d; border: 1px solid #bbf7d0; }

/* Delete btn */
.ma-delete-btn {
    display: inline-flex; align-items: center; justify-content: center;
    width: 34px; height: 34px;
    border-radius: 8px;
    background: #fef2f2;
    border: 1px solid #fecaca;
    color: #dc2626;
    cursor: pointer;
    transition: all 0.15s;
    font-size: 0.78rem;
}
.ma-delete-btn:hover {
    background: #dc2626;
    border-color: #dc2626;
    color: #fff;
    box-shadow: 0 2px 8px rgba(220,38,38,0.25);
}

/* ── Alerts ── */
.ma-alert {
    display: flex; align-items: center; gap: 10px;
    padding: 13px 16px; border-radius: 10px;
    font-size: 0.84rem; margin-bottom: 20px;
}
.ma-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.ma-alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

/* ── Empty state ── */
.ma-empty {
    text-align: center;
    padding: 56px 24px;
}
.ma-empty-icon {
    width: 64px; height: 64px;
    border-radius: 18px;
    background: #fdf8ec;
    display: flex; align-items: center; justify-content: center;
    margin: 0 auto 16px;
    color: #c4b89a;
    font-size: 1.6rem;
}

/* ── You badge ── */
.you-badge {
    font-size: 0.64rem;
    font-weight: 700;
    background: rgba(184,147,58,0.1);
    color: #9a7820;
    padding: 1px 7px;
    border-radius: 99px;
    border: 1px solid rgba(184,147,58,0.25);
    text-transform: uppercase;
    letter-spacing: 0.05em;
}

/* ── Responsive ── */
@media (max-width: 1100px) {
    .ma-grid { grid-template-columns: 360px minmax(0, 1fr); }
    .ma-stats-strip { grid-template-columns: repeat(2, 1fr); }
}
@media (max-width: 900px) {
    .ma-grid { grid-template-columns: 1fr; }
    .ma-form-card { position: static; }
}
@media (max-width: 640px) {
    .ma-wrapper { padding: 20px 16px 40px; }
    .ma-stats-strip { grid-template-columns: repeat(2, 1fr); }
}
</style>

<!-- ── Page header ── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-users-gear" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>
            Manage Accounts
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Manage Accounts</span>
        </p>
    </div>
</div>

<div class="ma-wrapper">

    <?php if ($success): ?>
    <div class="ma-alert ma-alert-success">
        <i class="fas fa-circle-check"></i>
        <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="ma-alert ma-alert-error">
        <i class="fas fa-circle-exclamation"></i>
        <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- ── Stats strip ── -->
    <div class="ma-stats-strip">
        <div class="ma-stat-card" style="--stat-color:#0f2044;--stat-bg:#f0f4ff;">
            <div class="ma-stat-icon"><i class="fas fa-users"></i></div>
            <div>
                <div class="ma-stat-value"><?= count($accounts) ?></div>
                <div class="ma-stat-label">Total Staff</div>
            </div>
        </div>
        <div class="ma-stat-card" style="--stat-color:#9a7820;--stat-bg:#fdf8ec;">
            <div class="ma-stat-icon"><i class="fas fa-shield-halved"></i></div>
            <div>
                <div class="ma-stat-value"><?= $role_counts['admin'] ?></div>
                <div class="ma-stat-label">Admins</div>
            </div>
        </div>
        <div class="ma-stat-card" style="--stat-color:#1d4ed8;--stat-bg:#eff6ff;">
            <div class="ma-stat-icon"><i class="fas fa-hands-praying"></i></div>
            <div>
                <div class="ma-stat-value"><?= $role_counts['clergy'] ?></div>
                <div class="ma-stat-label">Clergy</div>
            </div>
        </div>
        <div class="ma-stat-card" style="--stat-color:#15803d;--stat-bg:#f0fdf4;">
            <div class="ma-stat-icon"><i class="fas fa-coins"></i></div>
            <div>
                <div class="ma-stat-value"><?= $role_counts['finance'] ?></div>
                <div class="ma-stat-label">Finance</div>
            </div>
        </div>
    </div>

    <!-- ── Main grid ── -->
    <div class="ma-grid">

        <!-- ══ CREATE FORM ══ -->
        <div class="ma-form-card">
            <div class="ma-form-header">
                <div class="ma-form-header-icon">
                    <i class="fas fa-user-plus"></i>
                </div>
                <div class="ma-form-header-title">New Staff Account</div>
                <div class="ma-form-header-sub">Create login access for parish staff</div>
            </div>

            <div class="ma-form-body">
                <form method="POST" action="">
                    <input type="hidden" name="action" value="create">

                    <div class="ma-field">
                        <label class="ma-label">Full Name</label>
                        <div class="ma-input-wrap">
                            <i class="fas fa-user field-icon"></i>
                            <input type="text" name="name" class="ma-input"
                                   placeholder="e.g. Fr. Juan dela Cruz"
                                   value="<?= htmlspecialchars($_POST['name'] ?? '') ?>"
                                   required>
                        </div>
                    </div>

                    <div class="ma-field">
                        <label class="ma-label">Email Address</label>
                        <div class="ma-input-wrap">
                            <i class="fas fa-envelope field-icon"></i>
                            <input type="email" name="email" class="ma-input"
                                   placeholder="staff@parish.com"
                                   value="<?= htmlspecialchars($_POST['email'] ?? '') ?>"
                                   required>
                        </div>
                    </div>

                    <!-- Role pill selector -->
                    <div class="ma-field">
                        <label class="ma-label">Role</label>
                        <div class="role-pills">
                            <div class="role-pill" style="--pill-color:#9a7820;--pill-bg:#fdf8ec;--pill-glow:rgba(184,147,58,0.12);">
                                <input type="radio" name="role" id="role-admin" value="admin"
                                       <?= ($_POST['role'] ?? '') === 'admin' ? 'checked' : '' ?>>
                                <label for="role-admin">
                                    <div class="pill-icon"><i class="fas fa-shield-halved"></i></div>
                                    <div class="pill-name">Admin</div>
                                </label>
                            </div>
                            <div class="role-pill" style="--pill-color:#1d4ed8;--pill-bg:#eff6ff;--pill-glow:rgba(59,130,246,0.12);">
                                <input type="radio" name="role" id="role-clergy" value="clergy"
                                       <?= ($_POST['role'] ?? '') === 'clergy' ? 'checked' : '' ?>>
                                <label for="role-clergy">
                                    <div class="pill-icon"><i class="fas fa-hands-praying"></i></div>
                                    <div class="pill-name">Clergy</div>
                                </label>
                            </div>
                            <div class="role-pill" style="--pill-color:#15803d;--pill-bg:#f0fdf4;--pill-glow:rgba(34,197,94,0.12);">
                                <input type="radio" name="role" id="role-finance" value="finance"
                                       <?= ($_POST['role'] ?? '') === 'finance' ? 'checked' : '' ?>>
                                <label for="role-finance">
                                    <div class="pill-icon"><i class="fas fa-coins"></i></div>
                                    <div class="pill-name">Finance</div>
                                </label>
                            </div>
                        </div>
                        <p style="font-size:0.71rem;color:#b0a898;margin-top:8px;display:flex;align-items:center;gap:5px;">
                            <i class="fas fa-circle-info" style="color:#c4b89a;"></i>
                            Parishioner accounts are self-registered via the portal.
                        </p>
                        <!-- Hidden select for form submission fallback -->
                        <select name="role" id="role-hidden" style="display:none;" aria-hidden="true">
                            <option value="admin"   <?= ($_POST['role'] ?? '') === 'admin'   ? 'selected' : '' ?>>Admin</option>
                            <option value="clergy"  <?= ($_POST['role'] ?? '') === 'clergy'  ? 'selected' : '' ?>>Clergy</option>
                            <option value="finance" <?= ($_POST['role'] ?? '') === 'finance' ? 'selected' : '' ?>>Finance</option>
                        </select>
                    </div>

                    <div class="ma-divider"></div>

                    <div class="ma-field">
                        <label class="ma-label">Password</label>
                        <div class="ma-input-wrap">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" name="password" id="pw-new" class="ma-input"
                                   placeholder="Min. 8 characters" required
                                   style="padding-right:40px;">
                            <button type="button" data-toggle-password="#pw-new"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                           background:none;border:none;cursor:pointer;color:#c4b89a;font-size:0.8rem;">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <div class="ma-field" style="margin-bottom:22px;">
                        <label class="ma-label">Confirm Password</label>
                        <div class="ma-input-wrap">
                            <i class="fas fa-lock field-icon"></i>
                            <input type="password" name="confirm" id="pw-confirm" class="ma-input"
                                   placeholder="Re-enter password" required
                                   style="padding-right:40px;">
                            <button type="button" data-toggle-password="#pw-confirm"
                                    style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                           background:none;border:none;cursor:pointer;color:#c4b89a;font-size:0.8rem;">
                                <i class="fas fa-eye-slash"></i>
                            </button>
                        </div>
                    </div>

                    <button type="submit" class="ma-submit">
                        <i class="fas fa-user-plus"></i>
                        Create Account
                    </button>
                </form>
            </div>
        </div>
        <!-- /form card -->

        <!-- ══ ACCOUNTS TABLE ══ -->
        <div class="ma-table-card">
            <div class="ma-table-header">
                <div class="ma-table-title">
                    <div class="ma-table-title-icon">
                        <i class="fas fa-users"></i>
                    </div>
                    <div>
                        <div>Staff Accounts</div>
                        <div style="font-family:'DM Sans',sans-serif;font-size:0.72rem;color:#9ca3af;font-weight:400;margin-top:2px;">
                            <?= count($accounts) ?> account<?= count($accounts) !== 1 ? 's' : '' ?> with system access
                        </div>
                    </div>
                </div>
                <div class="ma-role-pills-header">
                    <span class="ma-role-chip" style="background:rgba(184,147,58,0.1);color:#9a7820;border:1px solid rgba(184,147,58,0.25);">
                        <i class="fas fa-shield-halved"></i> <?= $role_counts['admin'] ?> Admin
                    </span>
                    <span class="ma-role-chip" style="background:#eff6ff;color:#1d4ed8;border:1px solid #bfdbfe;">
                        <i class="fas fa-hands-praying"></i> <?= $role_counts['clergy'] ?> Clergy
                    </span>
                    <span class="ma-role-chip" style="background:#f0fdf4;color:#15803d;border:1px solid #bbf7d0;">
                        <i class="fas fa-coins"></i> <?= $role_counts['finance'] ?> Finance
                    </span>
                </div>
            </div>

            <?php if (empty($accounts)): ?>
            <div class="ma-empty">
                <div class="ma-empty-icon"><i class="fas fa-users"></i></div>
                <p style="font-size:0.9rem;color:#6b7280;font-weight:500;margin-bottom:4px;">No staff accounts yet</p>
                <p style="font-size:0.78rem;color:#c4b89a;">Use the form to create the first account.</p>
            </div>
            <?php else: ?>
            <div style="overflow-x:auto;">
                <table class="ma-table">
                    <thead>
                        <tr>
                            <th>Staff Member</th>
                            <th>Email</th>
                            <th>Role</th>
                            <th>Member Since</th>
                            <th style="text-align:center;width:70px;">Action</th>
                        </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($accounts as $acc):
                        $initials = strtoupper(substr($acc['name'], 0, 1));
                    ?>
                    <tr>
                        <td>
                            <div style="display:flex;align-items:center;gap:12px;">
                                <div class="ma-avatar"><?= $initials ?></div>
                                <div>
                                    <div style="font-weight:600;color:#0f2044;font-size:0.855rem;line-height:1.3;">
                                        <?= htmlspecialchars($acc['name']) ?>
                                    </div>
                                    <?php if ($acc['id'] === $current_user_id): ?>
                                    <span class="you-badge">You</span>
                                    <?php endif; ?>
                                </div>
                            </div>
                        </td>
                        <td>
                            <span style="color:#6b7280;font-size:0.82rem;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-envelope" style="color:#d4c9b5;font-size:0.65rem;"></i>
                                <?= htmlspecialchars($acc['email']) ?>
                            </span>
                        </td>
                        <td>
                            <?php
                            $rb_map = [
                                'admin'   => ['rb-admin',   'fa-shield-halved',  'Admin'],
                                'clergy'  => ['rb-clergy',  'fa-hands-praying',  'Clergy'],
                                'finance' => ['rb-finance', 'fa-coins',          'Finance'],
                            ];
                            [$rb_class, $rb_icon, $rb_label] = $rb_map[$acc['role']] ?? ['', 'fa-user', ucfirst($acc['role'])];
                            ?>
                            <span class="rb <?= $rb_class ?>">
                                <i class="fas <?= $rb_icon ?>"></i>
                                <?= $rb_label ?>
                            </span>
                        </td>
                        <td>
                            <span style="color:#9ca3af;font-size:0.78rem;display:flex;align-items:center;gap:6px;">
                                <i class="fas fa-calendar-days" style="color:#d4c9b5;font-size:0.65rem;"></i>
                                <?= date('M j, Y', strtotime($acc['created_at'])) ?>
                            </span>
                        </td>
                        <td style="text-align:center;">
                            <?php if ($acc['id'] !== $current_user_id): ?>
                            <button type="button" class="ma-delete-btn"
                                    title="Delete account"
                                    onclick="openDeleteModal(<?= $acc['id'] ?>, '<?= htmlspecialchars(addslashes($acc['name']), ENT_QUOTES) ?>', '<?= ucfirst($acc['role']) ?>')">
                                <i class="fas fa-trash-can"></i>
                            </button>
                            <?php else: ?>
                            <span style="color:#e5e0d8;font-size:0.85rem;" title="Cannot delete your own account">
                                <i class="fas fa-lock"></i>
                            </span>
                            <?php endif; ?>
                        </td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <!-- Table footer -->
            <div style="padding:12px 20px;background:#fdfcfa;border-top:1px solid #f0ebe0;
                        border-radius:0 0 16px 16px;">
                <p style="font-size:0.73rem;color:#b0a898;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-circle-info" style="color:#c4b89a;"></i>
                    Parishioner accounts are managed separately and not shown here.
                </p>
            </div>
            <?php endif; ?>
        </div>
        <!-- /table card -->

    </div>
    <!-- /ma-grid -->

</div>
<!-- /ma-wrapper -->

<!-- ══════════════════════════════════════════════
     DELETE CONFIRMATION MODAL
══════════════════════════════════════════════ -->
<div id="deleteModal" style="
    display:none; position:fixed; inset:0; z-index:9999;
    align-items:center; justify-content:center;
    background:rgba(10,20,50,0.55); backdrop-filter:blur(4px);
    -webkit-backdrop-filter:blur(4px);
    padding:20px;
">
    <!-- Modal panel -->
    <div id="deleteModalPanel" style="
        background:#fff; border-radius:18px;
        width:100%; max-width:420px;
        box-shadow:0 24px 80px rgba(10,20,50,0.25), 0 4px 20px rgba(10,20,50,0.1);
        overflow:hidden;
        transform:scale(0.92) translateY(12px);
        opacity:0;
        transition:transform 0.22s cubic-bezier(0.16,1,0.3,1), opacity 0.22s ease;
    ">
        <!-- Red top bar -->
        <div style="height:4px;background:linear-gradient(90deg,#dc2626,#ef4444);"></div>

        <!-- Header -->
        <div style="padding:26px 26px 20px; display:flex; align-items:flex-start; gap:16px;">
            <!-- Warning icon -->
            <div style="
                width:48px; height:48px; border-radius:14px; flex-shrink:0;
                background:#fef2f2; border:1px solid #fecaca;
                display:flex; align-items:center; justify-content:center;
                color:#dc2626; font-size:1.2rem;
            ">
                <i class="fas fa-triangle-exclamation"></i>
            </div>
            <div style="flex:1; min-width:0;">
                <h3 style="
                    font-family:'Playfair Display',serif;
                    font-size:1.05rem; font-weight:700;
                    color:#0f2044; margin-bottom:5px; line-height:1.3;
                ">Delete Account</h3>
                <p style="font-size:0.82rem; color:#6b7280; line-height:1.55;">
                    You are about to permanently delete the account for:
                </p>
            </div>
        </div>

        <!-- Account preview -->
        <div style="margin:0 26px 22px; padding:14px 16px;
                    background:#faf8f5; border:1px solid #e8e0d0; border-radius:10px;
                    display:flex; align-items:center; gap:12px;">
            <div id="modal-avatar" style="
                width:40px; height:40px; border-radius:50%; flex-shrink:0;
                background:linear-gradient(135deg,#0f2044,#1a3870);
                color:#e0c060; display:flex; align-items:center; justify-content:center;
                font-size:0.9rem; font-weight:700;
                box-shadow:0 2px 8px rgba(15,32,68,0.2);
            "></div>
            <div style="min-width:0;">
                <div id="modal-name" style="font-weight:700; color:#0f2044; font-size:0.88rem; line-height:1.3;"></div>
                <div id="modal-role" style="font-size:0.72rem; color:#9ca3af; margin-top:2px;"></div>
            </div>
        </div>

        <!-- Warning message -->
        <div style="margin:0 26px 24px; padding:12px 14px;
                    background:#fffbeb; border:1px solid #fde68a; border-radius:8px;
                    display:flex; align-items:flex-start; gap:9px;">
            <i class="fas fa-circle-exclamation" style="color:#d97706; font-size:0.8rem; margin-top:1px; flex-shrink:0;"></i>
            <p style="font-size:0.78rem; color:#92400e; line-height:1.5; margin:0;">
                This action <strong>cannot be undone</strong>. The account and all associated access will be permanently removed.
            </p>
        </div>

        <!-- Actions -->
        <div style="padding:0 26px 24px; display:flex; gap:10px;">
            <button type="button" onclick="closeDeleteModal()" style="
                flex:1; padding:11px; border-radius:9px;
                background:#fff; border:1.5px solid #d1cdc4;
                color:#6b7280; font-family:'DM Sans',sans-serif;
                font-size:0.84rem; font-weight:600;
                cursor:pointer; transition:all 0.15s;
            " onmouseover="this.style.background='#faf7f0';this.style.borderColor='#b8933a';this.style.color='#0f2044';"
               onmouseout="this.style.background='#fff';this.style.borderColor='#d1cdc4';this.style.color='#6b7280';">
                Cancel
            </button>
            <!-- The actual delete form, submitted via JS -->
            <form id="deleteForm" method="POST" action="" style="flex:1;">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="user_id" id="modal-user-id" value="">
                <button type="submit" style="
                    width:100%; padding:11px; border-radius:9px;
                    background:linear-gradient(135deg,#dc2626,#b91c1c);
                    border:none; color:#fff;
                    font-family:'DM Sans',sans-serif;
                    font-size:0.84rem; font-weight:600;
                    cursor:pointer; transition:all 0.2s;
                    display:flex; align-items:center; justify-content:center; gap:7px;
                    box-shadow:0 2px 10px rgba(220,38,38,0.3);
                " onmouseover="this.style.boxShadow='0 4px 18px rgba(220,38,38,0.45)';this.style.transform='translateY(-1px)';"
                   onmouseout="this.style.boxShadow='0 2px 10px rgba(220,38,38,0.3)';this.style.transform='translateY(0)';">
                    <i class="fas fa-trash-can"></i> Yes, Delete Account
                </button>
            </form>
        </div>
    </div>
</div>

<script>
// ── Delete modal ─────────────────────────────────────────────
const modal      = document.getElementById('deleteModal');
const modalPanel = document.getElementById('deleteModalPanel');

function openDeleteModal(id, name, role) {
    document.getElementById('modal-user-id').value = id;
    document.getElementById('modal-name').textContent = name;
    document.getElementById('modal-role').textContent = role + ' Account';
    document.getElementById('modal-avatar').textContent = name.charAt(0).toUpperCase();

    modal.style.display = 'flex';
    document.body.style.overflow = 'hidden';

    // Animate in
    requestAnimationFrame(() => {
        modalPanel.style.transform = 'scale(1) translateY(0)';
        modalPanel.style.opacity   = '1';
    });
}

function closeDeleteModal() {
    modalPanel.style.transform = 'scale(0.92) translateY(12px)';
    modalPanel.style.opacity   = '0';
    setTimeout(() => {
        modal.style.display = 'none';
        document.body.style.overflow = '';
    }, 200);
}

// Close on backdrop click
modal.addEventListener('click', function (e) {
    if (e.target === this) closeDeleteModal();
});

// Close on Escape key
document.addEventListener('keydown', function (e) {
    if (e.key === 'Escape' && modal.style.display === 'flex') closeDeleteModal();
});

// ── Password toggle ──────────────────────────────────────────
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', function () {
        const target = document.querySelector(this.dataset.togglePassword);
        if (!target) return;
        const isText = target.type === 'text';
        target.type = isText ? 'password' : 'text';
        this.querySelector('i').className = isText ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
});

// ── Sync role radio pills → hidden select ────────────────────
document.querySelectorAll('.role-pill input[type="radio"]').forEach(radio => {
    radio.addEventListener('change', function () {
        const hidden = document.getElementById('role-hidden');
        if (hidden) hidden.value = this.value;
    });
});

// ── Auto-dismiss success alerts ──────────────────────────────
document.querySelectorAll('.ma-alert-success').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 3500);
});
</script>

<?php include __DIR__ . '/../includes/footer.php'; ?>