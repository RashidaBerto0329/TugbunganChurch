<?php
// church/modules/members/index.php
// Phase 5 — Step 5.1: Members & Volunteers master list
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$page_title = 'Members & Volunteers';

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// ── Stats ────────────────────────────────────────────────────
$total_members    = (int)$conn->query("SELECT COUNT(*) FROM members    WHERE is_archived = 0")->fetch_row()[0];
$active_members   = (int)$conn->query("SELECT COUNT(*) FROM members    WHERE is_archived = 0 AND membership_status = 'active'")->fetch_row()[0];
$inactive_members = $total_members - $active_members;
$total_volunteers = (int)$conn->query("SELECT COUNT(*) FROM volunteers WHERE is_archived = 0")->fetch_row()[0];

// ── Active tab ───────────────────────────────────────────────
$tab = in_array($_GET['tab'] ?? '', ['volunteers']) ? 'volunteers' : 'members';

// ── Members: search + filter + sort + pagination ─────────────
$m_search = trim($_GET['q']      ?? '');
$m_status = trim($_GET['status'] ?? '');
$m_sort   = trim($_GET['sort']   ?? 'name_asc');
$m_page   = max(1, (int)($_GET['p'] ?? 1));
$per_page = 15;

$m_where_parts = ['is_archived = 0'];
$m_params      = [];
$m_types       = '';

if ($m_search !== '') {
    $m_where_parts[] = '(name LIKE ? OR contact_number LIKE ? OR email LIKE ? OR address LIKE ?)';
    $like = "%{$m_search}%";
    $m_params = array_merge($m_params, [$like, $like, $like, $like]);
    $m_types .= 'ssss';
}
if ($m_status !== '') {
    $m_where_parts[] = 'membership_status = ?';
    $m_params[]       = $m_status;
    $m_types         .= 's';
}

$m_where_sql = implode(' AND ', $m_where_parts);
$m_order_sql = match($m_sort) {
    'name_desc'   => 'name DESC',
    'joined_asc'  => 'joined_date ASC',
    'joined_desc' => 'joined_date DESC',
    default       => 'name ASC',
};

$m_count_stmt = $conn->prepare("SELECT COUNT(*) FROM members WHERE {$m_where_sql}");
if ($m_types) $m_count_stmt->bind_param($m_types, ...$m_params);
$m_count_stmt->execute();
$m_total = (int)$m_count_stmt->get_result()->fetch_row()[0];
$m_count_stmt->close();

$m_pages  = max(1, (int)ceil($m_total / $per_page));
$m_page   = min($m_page, $m_pages);
$m_offset = ($m_page - 1) * $per_page;

$m_stmt = $conn->prepare("SELECT id, name, contact_number, email, address, membership_status, joined_date, created_at FROM members WHERE {$m_where_sql} ORDER BY {$m_order_sql} LIMIT ? OFFSET ?");
$all_params = array_merge($m_params, [$per_page, $m_offset]);
$all_types  = $m_types . 'ii';
$m_stmt->bind_param($all_types, ...$all_params);
$m_stmt->execute();
$members = $m_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$m_stmt->close();

// ── Volunteers: search + sort + pagination ───────────────────
$v_search = trim($_GET['vq']    ?? '');
$v_sort   = trim($_GET['vsort'] ?? 'name_asc');
$v_page   = max(1, (int)($_GET['vp'] ?? 1));

$v_where_parts = ['is_archived = 0'];
$v_params      = [];
$v_types       = '';

if ($v_search !== '') {
    $v_where_parts[] = '(name LIKE ? OR contact_number LIKE ? OR email LIKE ? OR role LIKE ?)';
    $vlike = "%{$v_search}%";
    $v_params = array_merge($v_params, [$vlike, $vlike, $vlike, $vlike]);
    $v_types .= 'ssss';
}

$v_where_sql = implode(' AND ', $v_where_parts);
$v_order_sql = match($v_sort) {
    'name_desc'  => 'name DESC',
    'role_asc'   => 'role ASC, name ASC',
    'joined_asc' => 'joined_date ASC',
    default      => 'name ASC',
};

$v_count_stmt = $conn->prepare("SELECT COUNT(*) FROM volunteers WHERE {$v_where_sql}");
if ($v_types) $v_count_stmt->bind_param($v_types, ...$v_params);
$v_count_stmt->execute();
$v_total = (int)$v_count_stmt->get_result()->fetch_row()[0];
$v_count_stmt->close();

$v_pages  = max(1, (int)ceil($v_total / $per_page));
$v_page   = min($v_page, $v_pages);
$v_offset = ($v_page - 1) * $per_page;

$v_stmt = $conn->prepare("SELECT id, name, contact_number, email, address, role, joined_date FROM volunteers WHERE {$v_where_sql} ORDER BY {$v_order_sql} LIMIT ? OFFSET ?");
$v_all_params = array_merge($v_params, [$per_page, $v_offset]);
$v_all_types  = $v_types . 'ii';
$v_stmt->bind_param($v_all_types, ...$v_all_params);
$v_stmt->execute();
$volunteers = $v_stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$v_stmt->close();

// ── Volunteer roles for filter dropdown ──────────────────────
$roles_r = $conn->query("SELECT DISTINCT role FROM volunteers WHERE role IS NOT NULL AND role != '' AND is_archived = 0 ORDER BY role");
$volunteer_roles = [];
while ($row = $roles_r->fetch_row()) $volunteer_roles[] = $row[0];

function page_url_m($p, $tab, $q, $status, $sort) {
    return '?' . http_build_query(array_filter(['tab' => $tab === 'members' ? '' : $tab, 'q' => $q, 'status' => $status, 'sort' => $sort === 'name_asc' ? '' : $sort, 'p' => $p], fn($v) => $v !== ''));
}
function page_url_v($p, $vq, $vsort) {
    return '?tab=volunteers&' . http_build_query(array_filter(['vq' => $vq, 'vsort' => $vsort === 'name_asc' ? '' : $vsort, 'vp' => $p], fn($v) => $v !== ''));
}

include $root . '/includes/header.php';
?>

<style>
    /* ── Banner ── */
    .members-banner {
        background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 60%, #8b5cf6 100%);
        border-radius: 14px;
        padding: 22px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .members-banner::after {
        content: '';
        position: absolute; right: -20px; top: -20px;
        width: 160px; height: 160px; border-radius: 50%;
        background: rgba(255,255,255,0.04); pointer-events: none;
    }
    .banner-stats { display: flex; gap: 28px; align-items: center; flex-wrap: nowrap; }
    .banner-stat-item { text-align: center; }
    .banner-stat-value { font-family: 'Playfair Display',serif; font-size: 1.6rem; color: #c4b5fd; font-weight: 600; line-height: 1; }
    .banner-stat-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; margin-top: 3px; }
    .banner-stat-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.1); flex-shrink: 0; }

    /* ── Tabs ── */
    .tab-bar {
        display: flex;
        gap: 0;
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 10px;
        overflow: hidden;
        margin-bottom: 18px;
        width: fit-content;
    }
    .tab-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 10px 20px; font-size: 0.83rem; font-weight: 600;
        text-decoration: none; color: #6b7280;
        border-right: 1px solid #ede8de;
        transition: all 0.15s;
    }
    .tab-btn:last-child { border-right: none; }
    .tab-btn.active { background: #8b5cf6; color: #fff; }
    .tab-btn:not(.active):hover { background: #faf7f0; color: #0f2044; }
    .tab-count {
        display: inline-flex; align-items: center; justify-content: center;
        font-size: 0.68rem; font-weight: 700;
        min-width: 18px; height: 18px; padding: 0 5px;
        border-radius: 99px;
        background: rgba(255,255,255,0.25);
    }
    .tab-btn:not(.active) .tab-count {
        background: #f3ede3; color: #9ca3af;
    }

    /* ── Toolbar ── */
    .toolbar { display: flex; align-items: center; gap: 10px; flex-wrap: wrap; margin-bottom: 16px; }
    .search-wrap { position: relative; flex: 1; min-width: 200px; max-width: 360px; }
    .search-wrap i { position: absolute; left: 11px; top: 50%; transform: translateY(-50%); color: #c4b89a; font-size: 0.82rem; pointer-events: none; }
    .search-input { width: 100%; padding: 8px 12px 8px 32px; border: 1px solid #d1cdc4; border-radius: 8px; font-size: 0.845rem; color: #1a1a2e; background: #fff; outline: none; transition: border-color 0.18s, box-shadow 0.18s; }
    .search-input:focus { border-color: #8b5cf6; box-shadow: 0 0 0 3px rgba(139,92,246,0.1); }
    .search-input::placeholder { color: #c4b89a; }
    .filter-select { padding: 8px 10px; border: 1px solid #d1cdc4; border-radius: 8px; font-size: 0.82rem; color: #374151; background: #fff; outline: none; cursor: pointer; transition: border-color 0.18s; }
    .filter-select:focus { border-color: #8b5cf6; }
    .toolbar-btn { display: inline-flex; align-items: center; gap: 6px; padding: 8px 16px; border-radius: 8px; font-size: 0.82rem; font-weight: 600; text-decoration: none; border: none; cursor: pointer; transition: all 0.18s; }
    .btn-purple { background: #8b5cf6; color: #fff; }
    .btn-purple:hover { background: #7c3aed; }
    .btn-outline { background: #fff; color: #6b7280; border: 1px solid #d1cdc4; }
    .btn-outline:hover { background: #faf7f0; border-color: #c4b89a; color: #0f2044; }

    /* ── Table card ── */
    .table-card { background: #fff; border: 1px solid #d1cdc4; border-radius: 14px; overflow: hidden; box-shadow: 0 2px 16px rgba(15,32,68,0.08); }
    .table-card-header { padding: 15px 20px; border-bottom: 2px solid #f0ebe0; background: #fdfcfa; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 8px; }
    .table-card-title { font-family: 'Playfair Display',serif; font-size: 0.92rem; font-weight: 600; color: #0f2044; display: flex; align-items: center; gap: 8px; }
    .count-badge { display: inline-flex; align-items: center; font-size: 0.72rem; font-weight: 600; background: #f5f3ff; color: #8b5cf6; padding: 2px 9px; border-radius: 99px; }
    .table { width: 100%; border-collapse: collapse; font-size: 0.85rem; table-layout: fixed; }
    .table th { background: #f5f0e8; padding: 11px 16px; text-align: left; font-size: 0.7rem; font-weight: 700; letter-spacing: 0.07em; text-transform: uppercase; color: #6b5f4e; border-bottom: 2px solid #e8e0d0; white-space: nowrap; }
    .table td { padding: 13px 16px; border-bottom: 1px solid #f3ede3; color: #4a4a6a; vertical-align: middle; }
    .table tr:last-child td { border-bottom: none; }
    .table tbody tr:hover { background: #faf8f5; }
    .person-name { font-weight: 600; color: #0f2044; font-size: 0.855rem; }
    .person-sub { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }
    .status-badge { display: inline-flex; align-items: center; gap: 4px; font-size: 0.7rem; font-weight: 600; padding: 3px 9px; border-radius: 99px; }
    .status-active   { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .status-inactive { background: #f9fafb; color: #9ca3af; border: 1px solid #e5e7eb; }
    .role-badge { display: inline-flex; align-items: center; font-size: 0.72rem; font-weight: 500; padding: 3px 9px; border-radius: 99px; background: #f5f3ff; color: #7c3aed; border: 1px solid #ddd6fe; }
    .action-btn { display: inline-flex; align-items: center; gap: 4px; font-size: 0.74rem; font-weight: 500; padding: 4px 10px; border-radius: 6px; text-decoration: none; transition: all 0.15s; white-space: nowrap; }
    .action-view  { color: #8b5cf6; background: #f5f3ff; }
    .action-view:hover { background: #8b5cf6; color: #fff; }
    .action-edit  { color: #b8933a; background: #fdf8ec; }
    .action-edit:hover { background: #b8933a; color: #fff; }

    /* ── Empty state ── */
    .empty-state { text-align: center; padding: 60px 24px; }
    .empty-icon { width: 72px; height: 72px; border-radius: 20px; background: #f5f3ff; display: flex; align-items: center; justify-content: center; margin: 0 auto 20px; color: #8b5cf6; font-size: 2rem; }

    /* ── Pagination ── */
    .pagination { display: flex; align-items: center; justify-content: space-between; padding: 14px 20px; border-top: 1px solid #f0ebe0; background: #fdfcfa; flex-wrap: wrap; gap: 10px; }
    .pagination-info { font-size: 0.78rem; color: #9ca3af; }
    .pagination-links { display: flex; gap: 4px; }
    .page-btn { display: inline-flex; align-items: center; justify-content: center; width: 32px; height: 32px; border-radius: 6px; font-size: 0.78rem; font-weight: 500; text-decoration: none; border: 1px solid #d1cdc4; color: #6b7280; background: #fff; transition: all 0.15s; }
    .page-btn:hover { border-color: #8b5cf6; color: #8b5cf6; }
    .page-btn.active { background: #8b5cf6; color: #fff; border-color: #8b5cf6; }
    .page-btn.disabled { opacity: 0.35; pointer-events: none; }

    /* ── Alert ── */
    .alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; font-size: 0.83rem; margin-bottom: 20px; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

    @media (max-width: 768px) {
        .banner-stats { display: none; }
        .toolbar { flex-direction: column; align-items: stretch; }
        .search-wrap { max-width: 100%; }
        .tab-bar { width: 100%; }
        .tab-btn { flex: 1; justify-content: center; }
    }
    @media (max-width: 640px) {
        .members-banner { flex-direction: column; align-items: flex-start; }
    }
</style>

<!-- ── Page header ── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-users" style="color:#8b5cf6;margin-right:8px;font-size:1rem;"></i>
            Members & Volunteers
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Members & Volunteers</span>
        </p>
    </div>
    <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
    <div style="display:flex;gap:8px;flex-wrap:wrap;">
        <?php if ($tab === 'volunteers'): ?>
        <a href="/church/modules/members/add_volunteer.php" class="toolbar-btn btn-purple">
            <i class="fas fa-plus"></i> Add Volunteer
        </a>
        <?php else: ?>
        <a href="/church/modules/members/add_member.php" class="toolbar-btn btn-purple">
            <i class="fas fa-plus"></i> Add Member
        </a>
        <?php endif; ?>
    </div>
    <?php endif; ?>
</div>

<div style="padding:24px 24px 60px;">

    <?php if ($success): ?>
    <div class="alert alert-success auto-dismiss">
        <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error">
        <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <!-- Banner -->
    <div class="members-banner">
        <div style="display:flex;align-items:center;gap:18px;">
            <div style="width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;flex-shrink:0;">
                <i class="fas fa-users"></i>
            </div>
            <div>
                <p style="font-family:'Playfair Display',serif;font-size:1.1rem;color:#fff;font-weight:600;margin-bottom:3px;">
                    Members & Volunteers
                </p>
                <p style="font-size:0.78rem;color:rgba(255,255,255,0.45);">
                    Parish community directory · Track participation · Manage profiles
                </p>
            </div>
        </div>
        <div class="banner-stats">
            <div class="banner-stat-item">
                <div class="banner-stat-value"><?= number_format($total_members) ?></div>
                <div class="banner-stat-label">Total Members</div>
            </div>
            <div class="banner-stat-divider"></div>
            <div class="banner-stat-item">
                <div class="banner-stat-value"><?= $active_members ?></div>
                <div class="banner-stat-label">Active</div>
            </div>
            <div class="banner-stat-divider"></div>
            <div class="banner-stat-item">
                <div class="banner-stat-value"><?= $total_volunteers ?></div>
                <div class="banner-stat-label">Volunteers</div>
            </div>
        </div>
    </div>

    <!-- Tabs -->
    <div class="tab-bar">
        <a href="?tab=members" class="tab-btn <?= $tab === 'members' ? 'active' : '' ?>">
            <i class="fas fa-id-card" style="font-size:0.8rem;"></i>
            Parish Members
            <span class="tab-count"><?= number_format($total_members) ?></span>
        </a>
        <a href="?tab=volunteers" class="tab-btn <?= $tab === 'volunteers' ? 'active' : '' ?>">
            <i class="fas fa-hand-holding-heart" style="font-size:0.8rem;"></i>
            Volunteers
            <span class="tab-count"><?= number_format($total_volunteers) ?></span>
        </a>
    </div>

    <?php if ($tab === 'members'): ?>
    <!-- ── MEMBERS TAB ── -->
    <form method="GET" action="" id="memberFilterForm">
        <input type="hidden" name="tab" value="members">
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="q" class="search-input"
                       placeholder="Search name, contact, email, address…"
                       value="<?= htmlspecialchars($m_search) ?>" autocomplete="off">
            </div>
            <select name="status" class="filter-select" onchange="this.form.submit()">
                <option value=""       <?= $m_status === ''         ? 'selected' : '' ?>>All Status</option>
                <option value="active" <?= $m_status === 'active'   ? 'selected' : '' ?>>Active</option>
                <option value="inactive" <?= $m_status === 'inactive' ? 'selected' : '' ?>>Inactive</option>
            </select>
            <select name="sort" class="filter-select" onchange="this.form.submit()">
                <option value="name_asc"   <?= $m_sort === 'name_asc'   ? 'selected' : '' ?>>Name A–Z</option>
                <option value="name_desc"  <?= $m_sort === 'name_desc'  ? 'selected' : '' ?>>Name Z–A</option>
                <option value="joined_desc" <?= $m_sort === 'joined_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="joined_asc"  <?= $m_sort === 'joined_asc'  ? 'selected' : '' ?>>Oldest First</option>
            </select>
            <button type="submit" class="toolbar-btn btn-purple">
                <i class="fas fa-magnifying-glass"></i> Search
            </button>
            <?php if ($m_search !== '' || $m_status !== ''): ?>
            <a href="?tab=members" class="toolbar-btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card">
        <div class="table-card-header">
            <div class="table-card-title">
                <i class="fas fa-list" style="color:#8b5cf6;font-size:0.8rem;"></i>
                Parish Members
                <span class="count-badge"><?= number_format($m_total) ?></span>
                <?php if ($m_search !== '' || $m_status !== ''): ?>
                <span style="font-size:0.72rem;color:#9ca3af;font-family:'DM Sans',sans-serif;font-weight:400;">— filtered</span>
                <?php endif; ?>
            </div>
            <?php if ($m_pages > 1): ?>
            <div style="font-size:0.75rem;color:#9ca3af;">Page <?= $m_page ?> of <?= $m_pages ?></div>
            <?php endif; ?>
        </div>

        <?php if (empty($members)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-id-card"></i></div>
            <?php if ($m_search !== '' || $m_status !== ''): ?>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:8px;">No matching members</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;">Try adjusting your filters.</p>
            <a href="?tab=members" class="toolbar-btn btn-outline" style="display:inline-flex;">
                <i class="fas fa-times"></i> Clear filters
            </a>
            <?php else: ?>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:8px;">No Members Yet</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;max-width:340px;margin-left:auto;margin-right:auto;line-height:1.6;">
                Register the first parish member to get started.
            </p>
            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
            <a href="/church/modules/members/add_member.php" class="toolbar-btn btn-purple" style="display:inline-flex;">
                <i class="fas fa-plus"></i> Add First Member
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="width:100%;overflow-x:auto;">
            <table class="table">
                <colgroup>
                    <col style="min-width:200px;">
                    <col style="width:140px;">
                    <col style="min-width:160px;">
                    <col style="min-width:160px;">
                    <col style="width:100px;">
                    <col style="width:110px;">
                    <col style="width:130px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Status</th>
                        <th>Joined</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($members as $m): ?>
                <tr>
                    <td>
                        <div class="person-name"><?= htmlspecialchars($m['name']) ?></div>
                        <?php if ($m['joined_date']): ?>
                        <div class="person-sub">Since <?= date('Y', strtotime($m['joined_date'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:#374151;">
                        <?= $m['contact_number'] ? htmlspecialchars($m['contact_number']) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">
                        <?= $m['email'] ? htmlspecialchars($m['email']) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= $m['address'] ? htmlspecialchars($m['address']) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td>
                        <span class="status-badge <?= $m['membership_status'] === 'active' ? 'status-active' : 'status-inactive' ?>">
                            <i class="fas fa-circle" style="font-size:0.4rem;"></i>
                            <?= ucfirst($m['membership_status']) ?>
                        </span>
                    </td>
                    <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap;">
                        <?= $m['joined_date'] ? date('M j, Y', strtotime($m['joined_date'])) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                            <a href="/church/modules/members/view_member.php?id=<?= $m['id'] ?>"
                               class="action-btn action-view" title="View">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
                            <a href="/church/modules/members/edit_member.php?id=<?= $m['id'] ?>"
                               class="action-btn action-edit" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($m_pages > 1 || $m_total > 0): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?= number_format(($m_page - 1) * $per_page + 1) ?>–<?= number_format(min($m_page * $per_page, $m_total)) ?>
                of <?= number_format($m_total) ?> member<?= $m_total !== 1 ? 's' : '' ?>
            </div>
            <?php if ($m_pages > 1): ?>
            <div class="pagination-links">
                <a href="<?= page_url_m($m_page - 1, $tab, $m_search, $m_status, $m_sort) ?>"
                   class="page-btn <?= $m_page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left" style="font-size:0.65rem;"></i>
                </a>
                <?php
                $ws = max(1, $m_page - 2); $we = min($m_pages, $m_page + 2);
                if ($ws > 1) { echo '<a href="' . page_url_m(1, $tab, $m_search, $m_status, $m_sort) . '" class="page-btn">1</a>'; if ($ws > 2) echo '<span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span>'; }
                for ($i = $ws; $i <= $we; $i++) echo '<a href="' . page_url_m($i, $tab, $m_search, $m_status, $m_sort) . '" class="page-btn ' . ($i === $m_page ? 'active' : '') . '">' . $i . '</a>';
                if ($we < $m_pages) { if ($we < $m_pages - 1) echo '<span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span>'; echo '<a href="' . page_url_m($m_pages, $tab, $m_search, $m_status, $m_sort) . '" class="page-btn">' . $m_pages . '</a>'; }
                ?>
                <a href="<?= page_url_m($m_page + 1, $tab, $m_search, $m_status, $m_sort) ?>"
                   class="page-btn <?= $m_page >= $m_pages ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <!-- ── VOLUNTEERS TAB ── -->
    <form method="GET" action="" id="volunteerFilterForm">
        <input type="hidden" name="tab" value="volunteers">
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="vq" class="search-input"
                       placeholder="Search name, contact, email, role…"
                       value="<?= htmlspecialchars($v_search) ?>" autocomplete="off">
            </div>
            <select name="vsort" class="filter-select" onchange="this.form.submit()">
                <option value="name_asc"  <?= $v_sort === 'name_asc'  ? 'selected' : '' ?>>Name A–Z</option>
                <option value="name_desc" <?= $v_sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
                <option value="role_asc"  <?= $v_sort === 'role_asc'  ? 'selected' : '' ?>>By Role</option>
                <option value="joined_asc" <?= $v_sort === 'joined_asc' ? 'selected' : '' ?>>Oldest First</option>
            </select>
            <button type="submit" class="toolbar-btn btn-purple">
                <i class="fas fa-magnifying-glass"></i> Search
            </button>
            <?php if ($v_search !== ''): ?>
            <a href="?tab=volunteers" class="toolbar-btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="table-card">
        <div class="table-card-header">
            <div class="table-card-title">
                <i class="fas fa-hand-holding-heart" style="color:#8b5cf6;font-size:0.8rem;"></i>
                Volunteers
                <span class="count-badge"><?= number_format($v_total) ?></span>
                <?php if ($v_search !== ''): ?>
                <span style="font-size:0.72rem;color:#9ca3af;font-family:'DM Sans',sans-serif;font-weight:400;">— filtered</span>
                <?php endif; ?>
            </div>
            <?php if ($v_pages > 1): ?>
            <div style="font-size:0.75rem;color:#9ca3af;">Page <?= $v_page ?> of <?= $v_pages ?></div>
            <?php endif; ?>
        </div>

        <?php if (empty($volunteers)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <?php if ($v_search !== ''): ?>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:8px;">No matching volunteers</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;">Try adjusting your search.</p>
            <a href="?tab=volunteers" class="toolbar-btn btn-outline" style="display:inline-flex;">
                <i class="fas fa-times"></i> Clear filters
            </a>
            <?php else: ?>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:8px;">No Volunteers Yet</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;max-width:340px;margin-left:auto;margin-right:auto;line-height:1.6;">
                Add your first volunteer to keep track of who serves the parish.
            </p>
            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
            <a href="/church/modules/members/add_volunteer.php" class="toolbar-btn btn-purple" style="display:inline-flex;">
                <i class="fas fa-plus"></i> Add First Volunteer
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="width:100%;overflow-x:auto;">
            <table class="table">
                <colgroup>
                    <col style="min-width:200px;">
                    <col style="width:140px;">
                    <col style="min-width:160px;">
                    <col style="min-width:160px;">
                    <col style="min-width:120px;">
                    <col style="width:110px;">
                    <col style="width:130px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name</th>
                        <th>Contact</th>
                        <th>Email</th>
                        <th>Address</th>
                        <th>Role</th>
                        <th>Joined</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($volunteers as $v): ?>
                <tr>
                    <td>
                        <div class="person-name"><?= htmlspecialchars($v['name']) ?></div>
                        <?php if ($v['joined_date']): ?>
                        <div class="person-sub">Since <?= date('Y', strtotime($v['joined_date'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:#374151;">
                        <?= $v['contact_number'] ? htmlspecialchars($v['contact_number']) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;max-width:160px;">
                        <?= $v['email'] ? htmlspecialchars($v['email']) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                        <?= $v['address'] ? htmlspecialchars($v['address']) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td>
                        <?= $v['role']
                            ? '<span class="role-badge">' . htmlspecialchars($v['role']) . '</span>'
                            : '<span style="color:#c4b89a;font-size:0.78rem;">—</span>' ?>
                    </td>
                    <td style="font-size:0.78rem;color:#6b7280;white-space:nowrap;">
                        <?= $v['joined_date'] ? date('M j, Y', strtotime($v['joined_date'])) : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                            <a href="/church/modules/members/volunteers_list.php?id=<?= $v['id'] ?>"
                               class="action-btn action-view" title="View">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
                            <a href="/church/modules/members/edit_volunteer.php?id=<?= $v['id'] ?>"
                               class="action-btn action-edit" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($v_pages > 1 || $v_total > 0): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?= number_format(($v_page - 1) * $per_page + 1) ?>–<?= number_format(min($v_page * $per_page, $v_total)) ?>
                of <?= number_format($v_total) ?> volunteer<?= $v_total !== 1 ? 's' : '' ?>
            </div>
            <?php if ($v_pages > 1): ?>
            <div class="pagination-links">
                <a href="<?= page_url_v($v_page - 1, $v_search, $v_sort) ?>"
                   class="page-btn <?= $v_page <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left" style="font-size:0.65rem;"></i>
                </a>
                <?php
                $ws = max(1, $v_page - 2); $we = min($v_pages, $v_page + 2);
                if ($ws > 1) { echo '<a href="' . page_url_v(1, $v_search, $v_sort) . '" class="page-btn">1</a>'; if ($ws > 2) echo '<span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span>'; }
                for ($i = $ws; $i <= $we; $i++) echo '<a href="' . page_url_v($i, $v_search, $v_sort) . '" class="page-btn ' . ($i === $v_page ? 'active' : '') . '">' . $i . '</a>';
                if ($we < $v_pages) { if ($we < $v_pages - 1) echo '<span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span>'; echo '<a href="' . page_url_v($v_pages, $v_search, $v_sort) . '" class="page-btn">' . $v_pages . '</a>'; }
                ?>
                <a href="<?= page_url_v($v_page + 1, $v_search, $v_sort) ?>"
                   class="page-btn <?= $v_page >= $v_pages ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php include $root . '/includes/footer.php'; ?>