<?php
// church/auth/update_password.php
// All logged-in users (staff + parishioner) can update their name, email, and password.

if (session_status() === PHP_SESSION_NONE) session_start();

// Must be logged in
if (empty($_SESSION['user_id'])) {
    header('Location: /church/login.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

$is_parishioner = $_SESSION['user_role'] === 'parishioner';
$user_id        = $_SESSION['user_id'];
$error          = '';
$success        = '';

// ── Fetch current user data ────────────────────────────────
$stmt = $conn->prepare("SELECT name, email, password FROM users WHERE id = ? LIMIT 1");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$user) {
    session_destroy();
    header('Location: /church/login.php');
    exit;
}

// ── Handle POST ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $new_name  = trim($_POST['name']            ?? '');
    $new_email = trim($_POST['email']           ?? '');
    $current   = $_POST['current_password']     ?? '';
    $new_pw    = $_POST['new_password']         ?? '';
    $confirm   = $_POST['confirm_password']     ?? '';

    $change_pw = !empty($new_pw) || !empty($confirm);

    // ── Validate name & email ──────────────────────────────
    if (empty($new_name)) {
        $error = 'Name is required.';
    } elseif (empty($new_email) || !filter_var($new_email, FILTER_VALIDATE_EMAIL)) {
        $error = 'A valid email address is required.';
    } else {
        // Check email uniqueness (excluding self)
        $chk = $conn->prepare("SELECT id FROM users WHERE email = ? AND id != ? LIMIT 1");
        $chk->bind_param("si", $new_email, $user_id);
        $chk->execute();
        $chk->store_result();
        if ($chk->num_rows > 0) {
            $error = 'That email address is already in use by another account.';
        }
        $chk->close();
    }

    // ── Validate password (only if trying to change it) ────
    if (!$error && $change_pw) {
        if (empty($current)) {
            $error = 'Please enter your current password to set a new one.';
        } elseif (strlen($new_pw) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new_pw !== $confirm) {
            $error = 'New passwords do not match.';
        } elseif (!password_verify($current, $user['password'])) {
            $error = 'Your current password is incorrect.';
        } elseif (password_verify($new_pw, $user['password'])) {
            $error = 'New password must be different from your current password.';
        }
    }

    // ── Save ───────────────────────────────────────────────
    if (!$error) {
        if ($change_pw) {
            $hash = password_hash($new_pw, PASSWORD_BCRYPT);
            $upd  = $conn->prepare("UPDATE users SET name = ?, email = ?, password = ? WHERE id = ?");
            $upd->bind_param("sssi", $new_name, $new_email, $hash, $user_id);
        } else {
            $upd = $conn->prepare("UPDATE users SET name = ?, email = ? WHERE id = ?");
            $upd->bind_param("ssi", $new_name, $new_email, $user_id);
        }

        if ($upd->execute()) {
            $_SESSION['user_name'] = $new_name; // reflect in header immediately
            $user['name']  = $new_name;
            $user['email'] = $new_email;
            $success = $change_pw
                ? 'Your profile and password have been updated successfully.'
                : 'Your profile has been updated successfully.';
        } else {
            $error = 'Database error. Please try again.';
        }
        $upd->close();
    }
}

// ── Render: staff use dashboard layout, parishioners get standalone ─────────
if ($is_parishioner):
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile — Our Lady of Peace Parish</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cinzel:wght@400;500&family=DM+Sans:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <link rel="stylesheet" href="/church/assets/css/style.css">
    <style>
        body { min-height:100vh; background:#f5f0e8; display:flex; flex-direction:column; }
        .pw-wrap { flex:1; display:flex; align-items:center; justify-content:center; padding:40px 16px; }
        .pw-card { background:#fff; border-radius:16px; padding:44px 40px; max-width:480px; width:100%;
                   box-shadow:0 2px 24px rgba(0,0,0,0.08); border:1px solid #ede8de; }
        .pw-header { text-align:center; margin-bottom:28px; }
        .pw-icon { width:56px;height:56px;border-radius:50%;background:#fdf8ec;border:1.5px solid #e0c060;
                   display:flex;align-items:center;justify-content:center;color:#b8933a;font-size:1.3rem;
                   margin:0 auto 16px; }
        .pw-header h1 { font-family:'Cinzel',serif; font-size:1.1rem; letter-spacing:0.06em; color:#0f2044; margin-bottom:6px; }
        .pw-header p { font-size:0.82rem; color:#9ca3af; }
        .pw-footer { margin-top:20px; text-align:center; }
        .pw-footer a { font-size:0.78rem; color:#b0a898; display:inline-flex; align-items:center; gap:6px; }
        .pw-footer a:hover { color:#9a7820; }
        .section-label {
            font-size:0.68rem; font-weight:700; letter-spacing:0.12em;
            text-transform:uppercase; color:#b8933a;
            margin:22px 0 14px; display:flex; align-items:center; gap:8px;
        }
        .section-label:first-of-type { margin-top: 0; }
        .section-label::after { content:''; flex:1; height:1px; background:#f0ebe0; }
        .opt-tag { font-size:0.65rem; font-weight:400; color:#c4b89a; text-transform:none; letter-spacing:0; }
    </style>
</head>
<body>
<div class="pw-wrap">
    <div class="pw-card">
        <div class="pw-header">
            <div class="pw-icon"><i class="fas fa-user-pen"></i></div>
            <h1>My Profile</h1>
            <p>Update your name, email, or password.</p>
        </div>

        <?php if ($success): ?>
        <div class="alert alert-success auto-dismiss"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
        <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
        <?php endif; ?>

        <form method="POST" action="">

            <div class="section-label"><i class="fas fa-id-card"></i> Profile Info</div>

            <div class="form-group">
                <label class="form-label">Full Name</label>
                <input type="text" name="name" class="form-control"
                       value="<?= htmlspecialchars($user['name']) ?>"
                       placeholder="Your full name" required>
            </div>
            <div class="form-group">
                <label class="form-label">Email Address</label>
                <input type="email" name="email" class="form-control"
                       value="<?= htmlspecialchars($user['email']) ?>"
                       placeholder="your@email.com" required>
            </div>

            <div class="section-label">
                <i class="fas fa-lock"></i> Change Password
                <span class="opt-tag">(optional)</span>
            </div>

            <div class="form-group">
                <label class="form-label">Current Password</label>
                <div style="position:relative;">
                    <input type="password" name="current_password" id="pw-cur" class="form-control"
                           placeholder="Required only if changing password" style="padding-right:40px;">
                    <button type="button" data-toggle-password="#pw-cur"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;cursor:pointer;color:#c4b89a;font-size:0.85rem;">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">New Password</label>
                <div style="position:relative;">
                    <input type="password" name="new_password" id="pw-new" class="form-control"
                           placeholder="Min. 8 characters" style="padding-right:40px;">
                    <button type="button" data-toggle-password="#pw-new"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;cursor:pointer;color:#c4b89a;font-size:0.85rem;">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>
            <div class="form-group">
                <label class="form-label">Confirm New Password</label>
                <div style="position:relative;">
                    <input type="password" name="confirm_password" id="pw-con" class="form-control"
                           placeholder="Re-enter new password" style="padding-right:40px;">
                    <button type="button" data-toggle-password="#pw-con"
                            style="position:absolute;right:12px;top:50%;transform:translateY(-50%);
                                   background:none;border:none;cursor:pointer;color:#c4b89a;font-size:0.85rem;">
                        <i class="fas fa-eye-slash"></i>
                    </button>
                </div>
            </div>

            <button type="submit" class="btn btn-navy" style="width:100%;justify-content:center;margin-top:8px;">
                <i class="fas fa-floppy-disk"></i> Save Changes
            </button>
        </form>

        <div class="pw-footer">
            <a href="/church/portal/index.php"><i class="fas fa-arrow-left"></i> Back to Portal</a>
        </div>
    </div>
</div>
<script src="/church/assets/js/main.js"></script>
<script>
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', function () {
        const target = document.querySelector(this.dataset.togglePassword);
        if (!target) return;
        const isText = target.type === 'text';
        target.type = isText ? 'password' : 'text';
        this.querySelector('i').className = isText ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
});
</script>
</body>
</html>
<?php
else:
    // Staff layout
    $page_title = 'My Profile';
    include __DIR__ . '/../includes/header.php';
?>

<style>
.cp-wrapper {
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 48px 24px 80px;
    min-height: calc(100vh - 120px);
}
.cp-card {
    background: #fff;
    border: 1px solid #d1cdc4;
    border-radius: 16px;
    overflow: hidden;
    box-shadow: 0 2px 16px rgba(15,32,68,0.08);
    width: 100%;
    max-width: 480px;
}

/* Navy gradient header */
.cp-header {
    background: linear-gradient(135deg, #0f2044 0%, #162d5c 55%, #1a3870 100%);
    padding: 28px 28px 24px;
    position: relative;
    overflow: hidden;
}
.cp-header::after {
    content: '';
    position: absolute;
    right: -20px; bottom: -20px;
    width: 110px; height: 110px;
    border-radius: 50%;
    background: rgba(255,255,255,0.03);
    pointer-events: none;
}
.cp-header-icon {
    width: 46px; height: 46px;
    border-radius: 12px;
    background: rgba(224,192,96,0.15);
    display: flex; align-items: center; justify-content: center;
    color: #e0c060;
    font-size: 1.15rem;
    margin-bottom: 14px;
}
.cp-header-title {
    font-family: 'Playfair Display', serif;
    font-size: 1.15rem;
    color: #fff;
    font-weight: 600;
    margin-bottom: 4px;
}
.cp-header-sub {
    font-size: 0.75rem;
    color: rgba(255,255,255,0.4);
    display: flex;
    align-items: center;
    gap: 6px;
    flex-wrap: wrap;
}
.cp-header-sub .role-chip {
    display: inline-flex;
    align-items: center;
    gap: 4px;
    padding: 2px 9px;
    border-radius: 99px;
    font-size: 0.66rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    background: rgba(224,192,96,0.15);
    color: #e0c060;
    border: 1px solid rgba(224,192,96,0.25);
}

/* Form body */
.cp-body { padding: 28px; }

/* Section labels */
.cp-section {
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-transform: uppercase;
    color: #b8933a;
    margin: 22px 0 14px;
    display: flex;
    align-items: center;
    gap: 8px;
}
.cp-section:first-child { margin-top: 0; }
.cp-section::after { content: ''; flex: 1; height: 1px; background: #f0ebe0; }
.cp-section .opt-tag {
    font-size: 0.65rem;
    font-weight: 400;
    color: #c4b89a;
    text-transform: none;
    letter-spacing: 0;
}

/* Alerts */
.cp-alert {
    display: flex; align-items: center; gap: 10px;
    padding: 12px 15px; border-radius: 10px;
    font-size: 0.83rem; margin-bottom: 20px;
}
.cp-alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
.cp-alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

/* Fields */
.cp-field { margin-bottom: 16px; }
.cp-label {
    display: block;
    font-size: 0.75rem;
    font-weight: 600;
    color: #374151;
    letter-spacing: 0.03em;
    margin-bottom: 6px;
    text-transform: uppercase;
}
.cp-input-wrap { position: relative; }
.cp-input-wrap .field-icon {
    position: absolute;
    left: 12px; top: 50%;
    transform: translateY(-50%);
    color: #c4b89a;
    font-size: 0.78rem;
    pointer-events: none;
    z-index: 1;
}
.cp-input {
    width: 100%;
    padding: 10px 40px 10px 36px;
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
.cp-input:focus {
    border-color: #b8933a;
    background: #fff;
    box-shadow: 0 0 0 3px rgba(184,147,58,0.12);
}
.cp-input::placeholder { color: #c4b89a; }
.cp-toggle-btn {
    position: absolute;
    right: 12px; top: 50%;
    transform: translateY(-50%);
    background: none; border: none;
    cursor: pointer; color: #c4b89a;
    font-size: 0.8rem; padding: 0;
    transition: color 0.15s;
}
.cp-toggle-btn:hover { color: #9a7820; }

.cp-divider {
    height: 1px;
    background: linear-gradient(to right, transparent, #e8e0d0, transparent);
    margin: 4px 0 20px;
}

/* Submit button */
.cp-submit {
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
    margin-bottom: 12px;
}
.cp-submit:hover {
    background: linear-gradient(135deg, #162d5c, #1e4080);
    box-shadow: 0 5px 20px rgba(15,32,68,0.3);
    transform: translateY(-1px);
}

.cp-cancel {
    display: flex; align-items: center; justify-content: center; gap: 6px;
    font-size: 0.78rem; color: #b0a898;
    text-decoration: none;
    transition: color 0.15s;
}
.cp-cancel:hover { color: #9a7820; }
</style>

<!-- Page header -->
<div class="page-header">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-user-pen" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>
            My Profile
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">My Profile</span>
        </p>
    </div>
</div>

<div class="cp-wrapper">
    <div class="cp-card">

        <!-- Navy header -->
        <div class="cp-header">
            <div class="cp-header-icon"><i class="fas fa-user-pen"></i></div>
            <div class="cp-header-title">My Profile</div>
            <div class="cp-header-sub">
                Logged in as
                <strong style="color:rgba(255,255,255,0.75);"><?= htmlspecialchars($_SESSION['user_name']) ?></strong>
                <span class="role-chip">
                    <i class="fas fa-shield-halved"></i>
                    <?= ucfirst($_SESSION['user_role']) ?>
                </span>
            </div>
        </div>

        <!-- Form body -->
        <div class="cp-body">

            <?php if ($success): ?>
            <div class="cp-alert cp-alert-success">
                <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
            </div>
            <?php endif; ?>
            <?php if ($error): ?>
            <div class="cp-alert cp-alert-error">
                <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
            </div>
            <?php endif; ?>

            <form method="POST" action="">

                <!-- Profile Info -->
                <div class="cp-section"><i class="fas fa-id-card"></i> Profile Info</div>

                <div class="cp-field">
                    <label class="cp-label">Full Name</label>
                    <div class="cp-input-wrap">
                        <i class="fas fa-user field-icon"></i>
                        <input type="text" name="name" class="cp-input"
                               value="<?= htmlspecialchars($user['name']) ?>"
                               placeholder="Your full name" required>
                    </div>
                </div>

                <div class="cp-field">
                    <label class="cp-label">Email Address</label>
                    <div class="cp-input-wrap">
                        <i class="fas fa-envelope field-icon"></i>
                        <input type="email" name="email" class="cp-input"
                               value="<?= htmlspecialchars($user['email']) ?>"
                               placeholder="your@email.com" required>
                    </div>
                </div>

                <!-- Change Password (optional) -->
                <div class="cp-section">
                    <i class="fas fa-lock"></i> Change Password
                    <span class="opt-tag">(optional)</span>
                </div>

                <div class="cp-field">
                    <label class="cp-label">Current Password</label>
                    <div class="cp-input-wrap">
                        <i class="fas fa-lock field-icon"></i>
                        <input type="password" name="current_password" id="pw-cur"
                               class="cp-input" placeholder="Required only if changing password">
                        <button type="button" class="cp-toggle-btn" data-toggle-password="#pw-cur">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="cp-field">
                    <label class="cp-label">New Password</label>
                    <div class="cp-input-wrap">
                        <i class="fas fa-lock-open field-icon"></i>
                        <input type="password" name="new_password" id="pw-new"
                               class="cp-input" placeholder="Min. 8 characters">
                        <button type="button" class="cp-toggle-btn" data-toggle-password="#pw-new">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="cp-field" style="margin-bottom:24px;">
                    <label class="cp-label">Confirm New Password</label>
                    <div class="cp-input-wrap">
                        <i class="fas fa-lock-open field-icon"></i>
                        <input type="password" name="confirm_password" id="pw-con"
                               class="cp-input" placeholder="Re-enter new password">
                        <button type="button" class="cp-toggle-btn" data-toggle-password="#pw-con">
                            <i class="fas fa-eye-slash"></i>
                        </button>
                    </div>
                </div>

                <div class="cp-divider"></div>

                <button type="submit" class="cp-submit">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                </button>

            </form>

            <a href="/church/dashboard.php" class="cp-cancel">
                <i class="fas fa-arrow-left"></i> Back to Dashboard
            </a>

        </div>
    </div>
</div>

<script>
document.querySelectorAll('[data-toggle-password]').forEach(btn => {
    btn.addEventListener('click', function () {
        const target = document.querySelector(this.dataset.togglePassword);
        if (!target) return;
        const isText = target.type === 'text';
        target.type = isText ? 'password' : 'text';
        this.querySelector('i').className = isText ? 'fas fa-eye-slash' : 'fas fa-eye';
    });
});

// Auto-dismiss success alert
document.querySelectorAll('.cp-alert-success').forEach(el => {
    setTimeout(() => {
        el.style.transition = 'opacity 0.4s';
        el.style.opacity = '0';
        setTimeout(() => el.remove(), 400);
    }, 3500);
});
</script>

<?php
    include __DIR__ . '/../includes/footer.php';
endif;
?>