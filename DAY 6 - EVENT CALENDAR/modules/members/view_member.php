<?php
// church/modules/members/view_member.php
// Phase 5 — Step 5.3: View a single parish member
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /church/modules/members/index.php');
    exit;
}

$stmt = $conn->prepare("
    SELECT m.*, u.name AS created_by_name
    FROM members m
    LEFT JOIN users u ON u.id = m.created_by
    WHERE m.id = ? AND m.is_archived = 0
");
$stmt->bind_param("i", $id);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$member) {
    $_SESSION['error'] = "Member not found or has been archived.";
    header('Location: /church/modules/members/index.php');
    exit;
}

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// Prev / next navigation
$prev = $conn->query("SELECT id, name FROM members WHERE is_archived = 0 AND id < {$id} ORDER BY id DESC LIMIT 1")->fetch_assoc();
$next = $conn->query("SELECT id, name FROM members WHERE is_archived = 0 AND id > {$id} ORDER BY id ASC  LIMIT 1")->fetch_assoc();

$page_title = htmlspecialchars($member['name']) . ' — Member Profile';
include $root . '/includes/header.php';
?>

<style>
    .detail-card { background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:18px; }
    .detail-card-header { padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px; }
    .detail-card-header-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0; }
    .detail-card-title { font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;color:#0f2044; }
    .detail-card-body { padding:20px 22px; }
    .detail-grid { display:grid;grid-template-columns:repeat(3,1fr);gap:18px; }
    .detail-grid-2 { display:grid;grid-template-columns:repeat(2,1fr);gap:18px; }
    .detail-field { display:flex;flex-direction:column;gap:3px; }
    .detail-label { font-size:0.68rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a; }
    .detail-value { font-size:0.88rem;color:#1a1a2e;font-weight:500;line-height:1.4; }
    .detail-value.empty { color:#d1c9b8;font-style:italic;font-weight:400; }
    .member-hero { background:linear-gradient(135deg,#4c1d95 0%,#6d28d9 60%,#8b5cf6 100%);border-radius:14px;padding:24px 28px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:20px;position:relative;overflow:hidden; }
    .member-hero::after { content:attr(data-name);position:absolute;right:24px;top:50%;transform:translateY(-50%);font-family:'Playfair Display',serif;font-size:4rem;font-weight:700;color:rgba(255,255,255,0.05);pointer-events:none;letter-spacing:-2px;white-space:nowrap;max-width:300px;overflow:hidden; }
    .hero-icon-wrap { width:56px;height:56px;border-radius:16px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.5rem;flex-shrink:0; }
    .hero-name { font-family:'Playfair Display',serif;font-size:1.3rem;font-weight:700;color:#fff;margin-bottom:4px;line-height:1.2; }
    .hero-meta { font-size:0.78rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:10px;flex-wrap:wrap; }
    .status-badge { display:inline-flex;align-items:center;gap:4px;font-size:0.72rem;font-weight:600;padding:3px 10px;border-radius:6px; }
    .status-active { background:rgba(16,185,129,0.2);color:#6ee7b7; }
    .status-inactive { background:rgba(107,114,128,0.2);color:#d1d5db; }
    .act-btn { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:0.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all 0.18s; }
    .act-btn-purple { background:#8b5cf6;color:#fff; }
    .act-btn-purple:hover { background:#7c3aed; }
    .act-btn-gold { background:#fdf8ec;color:#b8933a;border:1px solid #e8d99a; }
    .act-btn-gold:hover { background:#b8933a;color:#fff;border-color:#b8933a; }
    .act-btn-outline { background:#fff;color:#6b7280;border:1px solid #ede8de; }
    .act-btn-outline:hover { background:#faf7f0;border-color:#c4b89a;color:#0f2044; }
    .act-btn-red { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; }
    .act-btn-red:hover { background:#dc2626;color:#fff;border-color:#dc2626; }
    .record-nav { display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:20px;flex-wrap:wrap; }
    .record-nav-btn { display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:8px;font-size:0.78rem;font-weight:500;text-decoration:none;border:1px solid #ede8de;background:#fff;color:#6b7280;transition:all 0.15s;max-width:220px; }
    .record-nav-btn:hover { border-color:#8b5cf6;color:#8b5cf6;background:#f5f3ff; }
    .record-nav-btn.disabled { opacity:0.3;pointer-events:none; }
    .record-nav-btn span { white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1; }
    .info-row { display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding:9px 0;border-bottom:1px solid #f5f0e8;font-size:0.8rem; }
    .info-row:last-child { border-bottom:none;padding-bottom:0; }
    .info-row-label { color:#9ca3af;flex-shrink:0; }
    .info-row-value { color:#0f2044;font-weight:500;text-align:right; }
    .alert { display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.83rem;margin-bottom:18px; }
    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d; }
    @media (max-width:900px) { #viewLayout { grid-template-columns:1fr !important; } .detail-grid { grid-template-columns:repeat(2,1fr); } }
    @media (max-width:560px) { .detail-grid { grid-template-columns:1fr; } .hero-name { font-size:1.05rem; } }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1><i class="fas fa-id-card" style="color:#8b5cf6;margin-right:8px;font-size:1rem;"></i>Member Profile</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/members/index.php" style="color:#9ca3af;text-decoration:none;">Members & Volunteers</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current"><?= htmlspecialchars($member['name']) ?></span>
        </p>
    </div>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
        <a href="/church/modules/members/edit_member.php?id=<?= $id ?>" class="act-btn act-btn-gold">
            <i class="fas fa-pen"></i> Edit
        </a>
        <?php endif; ?>
    </div>
</div>

<div style="padding:24px 24px 60px;">

    <?php if ($success): ?>
    <div class="alert alert-success auto-dismiss">
        <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Prev / Next -->
    <div class="record-nav">
        <a href="<?= $prev ? '/church/modules/members/view_member.php?id=' . $prev['id'] : '#' ?>"
           class="record-nav-btn <?= !$prev ? 'disabled' : '' ?>">
            <i class="fas fa-chevron-left" style="font-size:0.65rem;flex-shrink:0;"></i>
            <span><?= $prev ? htmlspecialchars($prev['name']) : 'No previous' ?></span>
        </a>
        <a href="/church/modules/members/index.php"
           style="font-size:0.78rem;color:#9ca3af;text-decoration:none;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-list" style="font-size:0.7rem;"></i> All Members
        </a>
        <a href="<?= $next ? '/church/modules/members/view_member.php?id=' . $next['id'] : '#' ?>"
           class="record-nav-btn <?= !$next ? 'disabled' : '' ?>" style="justify-content:flex-end;">
            <span><?= $next ? htmlspecialchars($next['name']) : 'No next' ?></span>
            <i class="fas fa-chevron-right" style="font-size:0.65rem;flex-shrink:0;"></i>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="viewLayout">

        <div>
            <!-- Hero -->
            <div class="member-hero" data-name="<?= htmlspecialchars($member['name']) ?>">
                <div style="display:flex;align-items:center;gap:18px;flex:1;min-width:0;">
                    <div class="hero-icon-wrap"><i class="fas fa-user"></i></div>
                    <div style="min-width:0;">
                        <div class="hero-name"><?= htmlspecialchars($member['name']) ?></div>
                        <div class="hero-meta">
                            <?php if ($member['joined_date']): ?>
                            <span><i class="fas fa-calendar" style="font-size:0.65rem;margin-right:3px;"></i>
                                Joined <?= date('F j, Y', strtotime($member['joined_date'])) ?>
                            </span>
                            <span style="color:rgba(255,255,255,0.2);">·</span>
                            <?php endif; ?>
                            <span>Parish Member</span>
                        </div>
                    </div>
                </div>
                <span class="status-badge <?= $member['membership_status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                    <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                    <?= ucfirst($member['membership_status']) ?>
                </span>
            </div>

            <!-- Contact & Personal -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#f5f3ff;color:#8b5cf6;"><i class="fas fa-address-card"></i></div>
                    <div class="detail-card-title">Contact Information</div>
                </div>
                <div class="detail-card-body">
                    <div class="detail-grid">
                        <div class="detail-field" style="grid-column:span 3;">
                            <span class="detail-label">Full Name</span>
                            <span class="detail-value" style="font-size:1rem;font-family:'Playfair Display',serif;">
                                <?= htmlspecialchars($member['name']) ?>
                            </span>
                        </div>
                        <div class="detail-field">
                            <span class="detail-label">Contact Number</span>
                            <span class="detail-value <?= empty($member['contact_number']) ? 'empty' : '' ?>">
                                <?= $member['contact_number'] ? htmlspecialchars($member['contact_number']) : '—' ?>
                            </span>
                        </div>
                        <div class="detail-field">
                            <span class="detail-label">Email Address</span>
                            <span class="detail-value <?= empty($member['email']) ? 'empty' : '' ?>">
                                <?php if ($member['email']): ?>
                                <a href="mailto:<?= htmlspecialchars($member['email']) ?>"
                                   style="color:#8b5cf6;text-decoration:none;"><?= htmlspecialchars($member['email']) ?></a>
                                <?php else: ?>—<?php endif; ?>
                            </span>
                        </div>
                        <div class="detail-field">
                            <span class="detail-label">Home Address</span>
                            <span class="detail-value <?= empty($member['address']) ? 'empty' : '' ?>">
                                <?= $member['address'] ? htmlspecialchars($member['address']) : '—' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Membership -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-church"></i></div>
                    <div class="detail-card-title">Membership Details</div>
                </div>
                <div class="detail-card-body">
                    <div class="detail-grid-2">
                        <div class="detail-field">
                            <span class="detail-label">Membership Status</span>
                            <span class="detail-value" style="<?= $member['membership_status'] === 'active' ? 'color:#16a34a;' : 'color:#9ca3af;' ?>">
                                <?= ucfirst($member['membership_status']) ?>
                            </span>
                        </div>
                        <div class="detail-field">
                            <span class="detail-label">Date Joined</span>
                            <span class="detail-value <?= empty($member['joined_date']) ? 'empty' : '' ?>">
                                <?= $member['joined_date'] ? date('F j, Y', strtotime($member['joined_date'])) : '—' ?>
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Notes -->
            <?php if (!empty($member['notes'])): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div>
                    <div class="detail-card-title">Notes</div>
                </div>
                <div class="detail-card-body">
                    <p style="font-size:0.875rem;color:#374151;line-height:1.65;white-space:pre-wrap;">
                        <?= htmlspecialchars($member['notes']) ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>
        </div>

        <!-- Sidebar -->
        <div style="display:flex;flex-direction:column;gap:16px;" id="viewSidebar">
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:12px;">Actions</p>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
                    <a href="/church/modules/members/edit_member.php?id=<?= $id ?>"
                       class="act-btn act-btn-gold" style="justify-content:center;">
                        <i class="fas fa-pen"></i> Edit Member
                    </a>
                    <button onclick="confirmArchive()" class="act-btn act-btn-red" style="justify-content:center;width:100%;">
                        <i class="fas fa-box-archive"></i> Archive Member
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:12px;">Record Info</p>
                <div>
                    <div class="info-row">
                        <span class="info-row-label">Status</span>
                        <span class="info-row-value" style="<?= $member['membership_status'] === 'active' ? 'color:#16a34a;' : 'color:#9ca3af;' ?>">
                            <?= ucfirst($member['membership_status']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Date Joined</span>
                        <span class="info-row-value"><?= $member['joined_date'] ? date('M j, Y', strtotime($member['joined_date'])) : '—' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Added by</span>
                        <span class="info-row-value"><?= !empty($member['created_by_name']) ? htmlspecialchars($member['created_by_name']) : 'Unknown' ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Date Added</span>
                        <span class="info-row-value"><?= $member['created_at'] ? date('M j, Y', strtotime($member['created_at'])) : '—' ?></span>
                    </div>
                    <?php if ($member['updated_at'] && $member['updated_at'] !== $member['created_at']): ?>
                    <div class="info-row">
                        <span class="info-row-label">Last Updated</span>
                        <span class="info-row-value"><?= date('M j, Y', strtotime($member['updated_at'])) ?></span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <a href="/church/modules/members/index.php"
               style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:#fff;border:1px solid #ede8de;border-radius:10px;text-decoration:none;font-size:0.8rem;color:#6b7280;transition:all 0.15s;"
               onmouseover="this.style.borderColor='#8b5cf6';this.style.color='#8b5cf6'"
               onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
                <i class="fas fa-arrow-left" style="font-size:0.7rem;color:#8b5cf6;"></i>
                Back to Members List
            </a>
        </div>

    </div>
</div>

<?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
<form id="archiveForm" method="POST" action="/church/modules/archive/archive_record.php">
    <input type="hidden" name="action"       value="archive">
    <input type="hidden" name="ref_type"     value="member">
    <input type="hidden" name="ref_id"       value="<?= $id ?>">
    <input type="hidden" name="redirect_url" value="/church/modules/members/view_member.php?id=<?= $id ?>">
</form>
<?php endif; ?>

<script>
function confirmArchive() {
    if (confirm('Archive "<?= addslashes($member['name']) ?>"?\n\nArchived records can be restored from the Archive module.')) {
        document.getElementById('archiveForm').submit();
    }
}
if (window.innerWidth >= 900) {
    document.getElementById('viewSidebar').style.position = 'sticky';
    document.getElementById('viewSidebar').style.top = '20px';
}
</script>

<?php include $root . '/includes/footer.php'; ?>