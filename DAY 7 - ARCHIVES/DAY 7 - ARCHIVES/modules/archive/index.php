<?php
// church/modules/archive/index.php
// Phase 8 — Archive Module
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$page_title = 'Archive';

// ── Access: admin & clergy only for management; finance can view ─
$can_manage = in_array($current_user_role, ['admin', 'clergy']);

// ── Flash messages ───────────────────────────────────────────
$flash_success = $_SESSION['success'] ?? null;
$flash_error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// ── Handle unarchive POST ────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $action  = $_POST['action']  ?? '';
    $ref_type = $_POST['ref_type'] ?? '';
    $ref_id   = (int)($_POST['ref_id'] ?? 0);

    $allowed_types = ['baptism','confirmation','wedding','funeral','member','volunteer'];

    if ($action === 'unarchive' && in_array($ref_type, $allowed_types) && $ref_id > 0) {
        $table_map = [
            'baptism'      => 'baptism_records',
            'confirmation' => 'confirmation_records',
            'wedding'      => 'wedding_records',
            'funeral'      => 'funeral_records',
            'member'       => 'members',
            'volunteer'    => 'volunteers',
        ];
        $table = $table_map[$ref_type];

        // Set is_archived = 0
        $u = $conn->prepare("UPDATE `{$table}` SET is_archived = 0 WHERE id = ?");
        $u->bind_param("i", $ref_id);
        $u->execute();
        $u->close();

        // Remove from archives log
        $d = $conn->prepare("DELETE FROM archives WHERE reference_type = ? AND reference_id = ?");
        $d->bind_param("si", $ref_type, $ref_id);
        $d->execute();
        $d->close();

        $_SESSION['success'] = "Record successfully restored from archive.";
        $qs = $_GET ? '?' . http_build_query($_GET) : '';
        header("Location: /church/modules/archive/index.php{$qs}");
        exit;
    }
}

// ── Filters ──────────────────────────────────────────────────
$type_f  = trim($_GET['type'] ?? '');
$q       = trim($_GET['q']    ?? '');
$year_f  = trim($_GET['year'] ?? '');
$page    = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset  = ($page - 1) * $per_page;

// ── Stat counts ──────────────────────────────────────────────
function arc_count($conn, $type) {
    $r = $conn->query("SELECT COUNT(*) FROM archives WHERE reference_type = '{$type}'");
    return $r ? (int)$r->fetch_row()[0] : 0;
}
$counts = [
    'baptism'      => arc_count($conn, 'baptism'),
    'confirmation' => arc_count($conn, 'confirmation'),
    'wedding'      => arc_count($conn, 'wedding'),
    'funeral'      => arc_count($conn, 'funeral'),
    'member'       => arc_count($conn, 'member'),
    'volunteer'    => arc_count($conn, 'volunteer'),
];
$total_archived = array_sum($counts);

// ── Available years ──────────────────────────────────────────
$years = [];
$yr = $conn->query("
    SELECT DISTINCT series_year FROM (
        SELECT b.series_year FROM archives a JOIN baptism_records b      ON b.id=a.reference_id WHERE a.reference_type='baptism'      AND b.series_year IS NOT NULL
        UNION
        SELECT c.series_year FROM archives a JOIN confirmation_records c ON c.id=a.reference_id WHERE a.reference_type='confirmation' AND c.series_year IS NOT NULL
        UNION
        SELECT w.series_year FROM archives a JOIN wedding_records w      ON w.id=a.reference_id WHERE a.reference_type='wedding'       AND w.series_year IS NOT NULL
        UNION
        SELECT f.series_year FROM archives a JOIN funeral_records f      ON f.id=a.reference_id WHERE a.reference_type='funeral'       AND f.series_year IS NOT NULL
    ) t ORDER BY series_year DESC
");
while ($row = $yr->fetch_row()) $years[] = $row[0];

// ── Build the unified query ───────────────────────────────────
$like   = '%' . $conn->real_escape_string($q) . '%';
$yr_c   = $year_f ? "AND r.series_year = " . (int)$year_f : "";
$type_where = $type_f ? "AND a.reference_type = '" . $conn->real_escape_string($type_f) . "'" : "";

$union_parts = [];

// BAPTISM
if (!$type_f || $type_f === 'baptism') {
    $union_parts[] = "
        SELECT a.id AS arc_id, a.reference_type, a.reference_id, a.archived_at,
               u.name AS archived_by_name,
               'Baptism' AS type_label, r.record_no, r.series_year,
               r.child_name AS primary_name,
               CONCAT(COALESCE(r.father_name,''),' / ',COALESCE(r.mother_name,'')) AS secondary,
               r.date_of_baptism AS ref_date
        FROM archives a
        JOIN baptism_records r ON r.id = a.reference_id AND a.reference_type = 'baptism'
        LEFT JOIN users u ON u.id = a.archived_by
        WHERE 1=1 $yr_c
          AND (r.child_name LIKE '$like' OR r.father_name LIKE '$like'
            OR r.mother_name LIKE '$like' OR r.record_no LIKE '$like')
    ";
}
// CONFIRMATION
if (!$type_f || $type_f === 'confirmation') {
    $union_parts[] = "
        SELECT a.id AS arc_id, a.reference_type, a.reference_id, a.archived_at,
               u.name AS archived_by_name,
               'Confirmation' AS type_label, r.record_no, r.series_year,
               r.name AS primary_name,
               CONCAT(COALESCE(r.father_name,''),' / ',COALESCE(r.mother_name,'')) AS secondary,
               r.date_of_confirmation AS ref_date
        FROM archives a
        JOIN confirmation_records r ON r.id = a.reference_id AND a.reference_type = 'confirmation'
        LEFT JOIN users u ON u.id = a.archived_by
        WHERE 1=1 $yr_c
          AND (r.name LIKE '$like' OR r.father_name LIKE '$like'
            OR r.mother_name LIKE '$like' OR r.record_no LIKE '$like')
    ";
}
// WEDDING
if (!$type_f || $type_f === 'wedding') {
    $union_parts[] = "
        SELECT a.id AS arc_id, a.reference_type, a.reference_id, a.archived_at,
               u.name AS archived_by_name,
               'Wedding' AS type_label, r.record_no, r.series_year,
               CONCAT(r.groom_name,' & ',r.bride_name) AS primary_name,
               '' AS secondary,
               r.date_of_wedding AS ref_date
        FROM archives a
        JOIN wedding_records r ON r.id = a.reference_id AND a.reference_type = 'wedding'
        LEFT JOIN users u ON u.id = a.archived_by
        WHERE 1=1 $yr_c
          AND (r.groom_name LIKE '$like' OR r.bride_name LIKE '$like'
            OR r.record_no LIKE '$like')
    ";
}
// FUNERAL
if (!$type_f || $type_f === 'funeral') {
    $union_parts[] = "
        SELECT a.id AS arc_id, a.reference_type, a.reference_id, a.archived_at,
               u.name AS archived_by_name,
               'Funeral' AS type_label, r.record_no, r.series_year,
               r.deceased_name AS primary_name,
               COALESCE(r.next_of_kin_name, r.next_of_kin,'') AS secondary,
               r.date_of_funeral AS ref_date
        FROM archives a
        JOIN funeral_records r ON r.id = a.reference_id AND a.reference_type = 'funeral'
        LEFT JOIN users u ON u.id = a.archived_by
        WHERE 1=1 $yr_c
          AND (r.deceased_name LIKE '$like' OR r.next_of_kin LIKE '$like'
            OR r.record_no LIKE '$like')
    ";
}
// MEMBERS
if (!$type_f || $type_f === 'member') {
    $union_parts[] = "
        SELECT a.id AS arc_id, a.reference_type, a.reference_id, a.archived_at,
               u.name AS archived_by_name,
               'Member' AS type_label, '' AS record_no, NULL AS series_year,
               r.name AS primary_name,
               COALESCE(r.contact_number,'') AS secondary,
               r.joined_date AS ref_date
        FROM archives a
        JOIN members r ON r.id = a.reference_id AND a.reference_type = 'member'
        LEFT JOIN users u ON u.id = a.archived_by
        WHERE 1=1
          AND (r.name LIKE '$like' OR r.contact_number LIKE '$like' OR r.email LIKE '$like')
    ";
}
// VOLUNTEERS
if (!$type_f || $type_f === 'volunteer') {
    $union_parts[] = "
        SELECT a.id AS arc_id, a.reference_type, a.reference_id, a.archived_at,
               u.name AS archived_by_name,
               'Volunteer' AS type_label, '' AS record_no, NULL AS series_year,
               r.name AS primary_name,
               COALESCE(r.role,'') AS secondary,
               r.joined_date AS ref_date
        FROM archives a
        JOIN volunteers r ON r.id = a.reference_id AND a.reference_type = 'volunteer'
        LEFT JOIN users u ON u.id = a.archived_by
        WHERE 1=1
          AND (r.name LIKE '$like' OR r.role LIKE '$like' OR r.email LIKE '$like')
    ";
}

$results    = [];
$total_hits = 0;

if (!empty($union_parts)) {
    $union_sql = implode(" UNION ALL ", $union_parts);
    $cnt = $conn->query("SELECT COUNT(*) FROM ($union_sql) counted");
    $total_hits = $cnt ? (int)$cnt->fetch_row()[0] : 0;
    $res = $conn->query("$union_sql ORDER BY archived_at DESC LIMIT $per_page OFFSET $offset");
    if ($res) while ($row = $res->fetch_assoc()) $results[] = $row;
}

$total_pages = $total_hits > 0 ? ceil($total_hits / $per_page) : 1;

include $root . '/includes/header.php';
?>

<style>
    /* ── Banner ── */
    .arc-banner {
        background: linear-gradient(135deg, #1f2937, #374151);
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
    .arc-banner::after {
        content: '\2726';
        position: absolute; right: 28px; top: 50%;
        transform: translateY(-50%);
        font-size: 5rem; color: rgba(255,255,255,0.03);
        pointer-events: none; font-family: serif;
    }
    .arc-banner-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.1rem; font-weight: 700; color: #fff; margin-bottom: 3px;
    }
    .arc-banner-sub { font-size: 0.78rem; color: rgba(255,255,255,0.4); }
    .arc-stats { display: flex; gap: 24px; flex-wrap: wrap; }
    .arc-stat { text-align: center; }
    .arc-stat-val {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem; color: #e5e7eb; font-weight: 600; line-height: 1;
    }
    .arc-stat-lbl { font-size: 0.68rem; color: rgba(255,255,255,0.35); text-transform: uppercase; letter-spacing: 0.06em; margin-top: 3px; }
    .arc-divider { width: 1px; height: 36px; background: rgba(255,255,255,0.1); }

    /* ── Type tabs ── */
    .type-tabs {
        display: flex; gap: 8px; flex-wrap: wrap; margin-bottom: 20px;
    }
    .type-tab {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 7px 16px; border-radius: 99px;
        font-size: 0.78rem; font-weight: 600;
        border: 1.5px solid #ede8de;
        background: #fff; color: #6b7280;
        text-decoration: none; transition: all 0.15s;
        cursor: pointer;
    }
    .type-tab:hover { border-color: #b8933a; color: #b8933a; }
    .type-tab.active { background: #0f2044; border-color: #0f2044; color: #fff; }
    .type-tab .tab-count {
        font-size: 0.68rem; padding: 1px 7px; border-radius: 99px;
        background: rgba(0,0,0,0.08);
    }
    .type-tab.active .tab-count { background: rgba(255,255,255,0.2); }

    /* ── Search bar ── */
    .arc-search-bar {
        display: flex; gap: 10px; flex-wrap: wrap; margin-bottom: 20px;
    }
    .arc-search-wrap { position: relative; flex: 1; min-width: 200px; }
    .arc-search-icon {
        position: absolute; left: 12px; top: 50%;
        transform: translateY(-50%);
        color: #c4b89a; font-size: 0.8rem; pointer-events: none;
    }
    .arc-search-input {
        width: 100%; padding: 9px 12px 9px 36px;
        border: 1px solid #ede8de; border-radius: 9px;
        font-size: 0.86rem; color: #1a1a2e;
        font-family: 'DM Sans', sans-serif;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        background: #fff;
    }
    .arc-search-input:focus {
        border-color: #6b7280; box-shadow: 0 0 0 3px rgba(107,114,128,0.1);
    }
    .arc-filter-select {
        padding: 9px 12px; border: 1px solid #ede8de; border-radius: 9px;
        font-size: 0.85rem; font-family: 'DM Sans', sans-serif;
        color: #374151; background: #fff; outline: none; cursor: pointer;
        transition: border-color 0.18s;
    }
    .arc-filter-select:focus { border-color: #6b7280; }
    .arc-search-btn {
        padding: 9px 20px; border-radius: 9px;
        background: #374151; color: #fff; font-size: 0.85rem; font-weight: 600;
        border: none; cursor: pointer; display: inline-flex; align-items: center; gap: 7px;
        font-family: 'DM Sans', sans-serif; transition: background 0.14s; white-space: nowrap;
    }
    .arc-search-btn:hover { background: #1f2937; }

    /* ── Results card ── */
    .arc-card {
        background: #fff; border: 1px solid #ede8de;
        border-radius: 14px; overflow: hidden;
    }
    .arc-card-header {
        padding: 14px 22px; border-bottom: 1px solid #f0ebe0;
        background: #fdfcfa;
        display: flex; align-items: center; justify-content: space-between;
        flex-wrap: wrap; gap: 10px;
    }
    .arc-card-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.9rem; font-weight: 600; color: #0f2044;
        display: flex; align-items: center; gap: 8px;
    }
    .result-row {
        display: grid;
        grid-template-columns: 1fr auto auto auto auto;
        align-items: center;
        gap: 14px;
        padding: 13px 22px;
        border-bottom: 1px solid #f5f0e8;
        transition: background 0.12s;
    }
    .result-row:last-child { border-bottom: none; }
    .result-row:hover { background: #faf9f7; }
    .result-name {
        font-size: 0.87rem; font-weight: 600; color: #0f2044; margin-bottom: 2px;
    }
    .result-secondary { font-size: 0.74rem; color: #9ca3af; }
    .result-rec-no { font-size: 0.73rem; color: #6b7280; font-family: monospace; white-space: nowrap; }
    .result-date { font-size: 0.73rem; color: #9ca3af; white-space: nowrap; text-align: right; }
    .archived-meta { font-size: 0.7rem; color: #c4b89a; white-space: nowrap; text-align: right; }

    /* Type badges */
    .type-badge {
        display: inline-flex; align-items: center; gap: 5px;
        font-size: 0.7rem; font-weight: 600; padding: 3px 10px;
        border-radius: 99px; white-space: nowrap;
    }
    .type-baptism      { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .type-confirmation { background: #f5f3ff; color: #8b5cf6; border: 1px solid #ddd6fe; }
    .type-wedding      { background: #fdf8ec; color: #b8933a; border: 1px solid #fde68a; }
    .type-funeral      { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }
    .type-member       { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .type-volunteer    { background: #fdf4ff; color: #9333ea; border: 1px solid #e9d5ff; }

    /* Action buttons */
    .btn-sm {
        display: inline-flex; align-items: center; gap: 5px;
        padding: 6px 12px; border-radius: 7px;
        font-size: 0.75rem; font-weight: 500;
        text-decoration: none; border: none; cursor: pointer;
        transition: all 0.14s; white-space: nowrap;
        font-family: 'DM Sans', sans-serif;
    }
    .btn-view    { background: #f5f0e8; color: #6b5f4e; border: 1px solid #e0d9cc; }
    .btn-view:hover    { background: #0f2044; color: #fff; border-color: #0f2044; }
    .btn-restore { background: #f0fdf4; color: #16a34a; border: 1px solid #bbf7d0; }
    .btn-restore:hover { background: #16a34a; color: #fff; border-color: #16a34a; }

    /* Empty state */
    .arc-empty {
        text-align: center; padding: 56px 24px; color: #c4b89a;
    }
    .arc-empty i { font-size: 2.4rem; opacity: 0.2; display: block; margin-bottom: 14px; }

    /* Pagination */
    .pagination {
        display: flex; gap: 4px; align-items: center; justify-content: center;
        padding: 16px 22px; border-top: 1px solid #f0ebe0; flex-wrap: wrap;
    }
    .page-btn {
        min-width: 34px; height: 34px; border-radius: 8px;
        border: 1px solid #ede8de; background: #fff; color: #6b7280;
        font-size: 0.8rem; display: inline-flex; align-items: center;
        justify-content: center; text-decoration: none; padding: 0 10px;
        transition: all 0.14s; font-family: 'DM Sans', sans-serif;
    }
    .page-btn:hover  { background: #f3f4f6; border-color: #374151; color: #374151; }
    .page-btn.active { background: #0f2044; border-color: #0f2044; color: #fff; font-weight: 600; }
    .page-btn.disabled { opacity: 0.35; pointer-events: none; }

    @media (max-width: 700px) {
        .result-row { grid-template-columns: 1fr auto auto; }
        .result-rec-no, .result-date, .archived-meta { display: none; }
    }
</style>

<!-- Page header -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-box-archive" style="color:#6b7280;margin-right:8px;font-size:1rem;"></i>
            Archive
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Archive</span>
        </p>
    </div>
</div>

<div style="padding:24px 24px 60px;">

    <!-- Flash messages -->
    <?php if ($flash_success): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;
                background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;
                font-size:0.83rem;margin-bottom:18px;" class="auto-dismiss">
        <i class="fas fa-circle-check"></i> <?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;
                background:#fef2f2;border:1px solid #fecaca;color:#dc2626;
                font-size:0.83rem;margin-bottom:18px;">
        <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($flash_error) ?>
    </div>
    <?php endif; ?>

    <!-- Banner -->
    <div class="arc-banner">
        <div>
            <div class="arc-banner-title">
                <i class="fas fa-box-archive" style="color:#9ca3af;margin-right:8px;"></i>
                Archived Records
            </div>
            <div class="arc-banner-sub">
                Safely stored records removed from active views. Restore anytime.
            </div>
        </div>
        <div class="arc-stats">
            <div class="arc-stat">
                <div class="arc-stat-val"><?= number_format($total_archived) ?></div>
                <div class="arc-stat-lbl">Total Archived</div>
            </div>
            <?php foreach ([
                'baptism' => 'Baptism', 'confirmation' => 'Confirm.',
                'wedding' => 'Wedding', 'funeral' => 'Funeral',
                'member'  => 'Members', 'volunteer' => 'Volunteers',
            ] as $t => $lbl):
                if ($counts[$t] === 0) continue; ?>
            <div class="arc-divider"></div>
            <div class="arc-stat">
                <div class="arc-stat-val"><?= $counts[$t] ?></div>
                <div class="arc-stat-lbl"><?= $lbl ?></div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Type tabs -->
    <?php
    $tab_types = [
        ''             => ['All',          'fa-layer-group', $total_archived],
        'baptism'      => ['Baptism',      'fa-droplet',     $counts['baptism']],
        'confirmation' => ['Confirmation', 'fa-hands-praying',$counts['confirmation']],
        'wedding'      => ['Wedding',      'fa-ring',        $counts['wedding']],
        'funeral'      => ['Funeral',      'fa-cross',       $counts['funeral']],
        'member'       => ['Member',       'fa-user',        $counts['member']],
        'volunteer'    => ['Volunteer',    'fa-hand-holding-heart', $counts['volunteer']],
    ];
    ?>
    <div class="type-tabs">
        <?php foreach ($tab_types as $val => [$label, $icon, $cnt]):
            $params = array_filter(['type' => $val, 'q' => $q, 'year' => $year_f]);
        ?>
        <a href="?<?= http_build_query($params) ?>"
           class="type-tab <?= $type_f === $val ? 'active' : '' ?>">
            <i class="fas <?= $icon ?>"></i>
            <?= $label ?>
            <span class="tab-count"><?= $cnt ?></span>
        </a>
        <?php endforeach; ?>
    </div>

    <!-- Search bar -->
    <form method="GET" action="">
        <?php if ($type_f): ?>
        <input type="hidden" name="type" value="<?= htmlspecialchars($type_f) ?>">
        <?php endif; ?>
        <div class="arc-search-bar">
            <div class="arc-search-wrap">
                <i class="fas fa-magnifying-glass arc-search-icon"></i>
                <input type="text" name="q" class="arc-search-input"
                       placeholder="Search by name, record number…"
                       value="<?= htmlspecialchars($q) ?>">
            </div>
            <?php if (!in_array($type_f, ['member','volunteer'])): ?>
            <select name="year" class="arc-filter-select">
                <option value="">All Years</option>
                <?php foreach ($years as $y): ?>
                <option value="<?= $y ?>" <?= $year_f == $y ? 'selected' : '' ?>><?= $y ?></option>
                <?php endforeach; ?>
            </select>
            <?php endif; ?>
            <button type="submit" class="arc-search-btn">
                <i class="fas fa-magnifying-glass"></i> Search
            </button>
            <?php if ($q || $year_f): ?>
            <a href="?<?= $type_f ? 'type=' . urlencode($type_f) : '' ?>"
               style="padding:9px 14px;border-radius:9px;border:1px solid #ede8de;
                      background:#fff;color:#6b7280;font-size:0.82rem;text-decoration:none;
                      display:inline-flex;align-items:center;gap:6px;transition:all 0.14s;"
               onmouseover="this.style.borderColor='#374151';this.style.color='#374151'"
               onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
                <i class="fas fa-xmark"></i> Clear
            </a>
            <?php endif; ?>
        </div>
    </form>

    <!-- Results -->
    <div class="arc-card">
        <div class="arc-card-header">
            <div class="arc-card-title">
                <i class="fas fa-box-archive" style="color:#6b7280;font-size:0.8rem;"></i>
                <?= $type_f ? ucfirst($type_f) . ' Records' : 'All Archived Items' ?>
                <span style="display:inline-flex;align-items:center;padding:2px 10px;
                             background:#f3f4f6;color:#374151;border-radius:99px;
                             font-size:0.7rem;font-weight:600;">
                    <?= number_format($total_hits) ?>
                </span>
            </div>
            <?php if ($q || $year_f || $type_f): ?>
            <span style="font-size:0.74rem;color:#9ca3af;">
                <?= $q ? 'Matching "' . htmlspecialchars($q) . '"' : '' ?>
                <?= $year_f ? ' · Year ' . $year_f : '' ?>
            </span>
            <?php endif; ?>
        </div>

        <?php if (empty($results)): ?>
        <div class="arc-empty">
            <i class="fas fa-box-open"></i>
            <p style="font-size:0.9rem;color:#6b7280;font-weight:500;margin-bottom:6px;">
                <?= ($q || $year_f) ? 'No matches found' : 'No archived items yet' ?>
            </p>
            <p style="font-size:0.78rem;color:#9ca3af;">
                <?= ($q || $year_f)
                    ? 'Try a different search or clear the filters.'
                    : 'Items archived from Church Records or Members will appear here.' ?>
            </p>
        </div>
        <?php else: ?>

        <?php
        $type_icons = [
            'baptism'      => 'fa-droplet',
            'confirmation' => 'fa-hands-praying',
            'wedding'      => 'fa-ring',
            'funeral'      => 'fa-cross',
            'member'       => 'fa-user',
            'volunteer'    => 'fa-hand-holding-heart',
        ];
        $view_paths = [
            'baptism'      => '/church/modules/church_records/baptism/view_record.php',
            'confirmation' => '/church/modules/church_records/confirmation/view_record.php',
            'wedding'      => '/church/modules/church_records/wedding/view_record.php',
            'funeral'      => '/church/modules/church_records/funeral/view_record.php',
            'member'       => '/church/modules/members/view_member.php',
            'volunteer'    => '/church/modules/members/view_volunteer.php',
        ];
        foreach ($results as $r):
            $slug = $r['reference_type'];
            $view_url = ($view_paths[$slug] ?? '#') . '?id=' . $r['reference_id'];
        ?>
        <div class="result-row">
            <div>
                <div class="result-name"><?= htmlspecialchars($r['primary_name']) ?></div>
                <?php if (!empty(trim($r['secondary'], '/ '))): ?>
                <div class="result-secondary"><?= htmlspecialchars(trim($r['secondary'], '/ ')) ?></div>
                <?php endif; ?>
            </div>

            <span class="type-badge type-<?= $slug ?>">
                <i class="fas <?= $type_icons[$slug] ?? 'fa-file' ?>"></i>
                <?= $r['type_label'] ?>
            </span>

            <div style="text-align:right;">
                <?php if ($r['record_no']): ?>
                <div class="result-rec-no"><?= htmlspecialchars($r['record_no']) ?></div>
                <?php endif; ?>
                <?php if ($r['ref_date']): ?>
                <div class="result-date"><?= date('M j, Y', strtotime($r['ref_date'])) ?></div>
                <?php endif; ?>
            </div>

            <div class="archived-meta">
                <div style="color:#9ca3af;margin-bottom:1px;">Archived</div>
                <div><?= date('M j, Y', strtotime($r['archived_at'])) ?></div>
                <?php if ($r['archived_by_name']): ?>
                <div style="color:#c4b89a;">by <?= htmlspecialchars($r['archived_by_name']) ?></div>
                <?php endif; ?>
            </div>

            <div style="display:flex;gap:6px;flex-shrink:0;">
                <a href="<?= $view_url ?>" class="btn-sm btn-view">
                    <i class="fas fa-eye"></i> View
                </a>
                <?php if ($can_manage): ?>
                <form method="POST" style="margin:0;"
                      onsubmit="return confirm('Restore this record to active status?')">
                    <input type="hidden" name="action"   value="unarchive">
                    <input type="hidden" name="ref_type" value="<?= $slug ?>">
                    <input type="hidden" name="ref_id"   value="<?= $r['reference_id'] ?>">
                    <button type="submit" class="btn-sm btn-restore">
                        <i class="fas fa-rotate-left"></i> Restore
                    </button>
                </form>
                <?php endif; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php $base = array_filter(['type' => $type_f, 'q' => $q, 'year' => $year_f]); ?>
            <a href="?<?= http_build_query(array_merge($base, ['page' => max(1, $page-1)])) ?>"
               class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
            </a>
            <?php for ($p = max(1, $page-2); $p <= min($total_pages, $page+2); $p++): ?>
            <a href="?<?= http_build_query(array_merge($base, ['page' => $p])) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>"><?= $p ?></a>
            <?php endfor; ?>
            <a href="?<?= http_build_query(array_merge($base, ['page' => min($total_pages, $page+1)])) ?>"
               class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <i class="fas fa-chevron-right" style="font-size:0.7rem;"></i>
            </a>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="padding:10px 22px;background:#fdfcfa;border-top:1px solid #f0ebe0;
                    font-size:0.72rem;color:#9ca3af;border-radius:0 0 14px 14px;">
            Page <?= $page ?> of <?= $total_pages ?> &middot;
            <?= number_format($total_hits) ?> item<?= $total_hits !== 1 ? 's' : '' ?>
        </div>

        <?php endif; ?>
    </div>
    <!-- /results card -->

</div>

<?php include $root . '/includes/footer.php'; ?>