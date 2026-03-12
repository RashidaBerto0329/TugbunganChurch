<?php
// church/modules/church_records/confirmation/series_records.php
// Phase 4 — Step 4.9: Records list inside a confirmation year series
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$year = (int)($_GET['year'] ?? 0);
if ($year < 1900 || $year > (int)date('Y') + 1) {
    header('Location: /church/modules/church_records/confirmation/series_list.php');
    exit;
}

$s = $conn->prepare("SELECT id, notes FROM confirmation_series WHERE series_year = ?");
$s->bind_param("i", $year);
$s->execute();
$series = $s->get_result()->fetch_assoc();
$s->close();

if (!$series) {
    $_SESSION['error'] = "No confirmation series found for {$year}.";
    header('Location: /church/modules/church_records/confirmation/series_list.php');
    exit;
}

$page_title = "Confirmation Records — {$year}";

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$search     = trim($_GET['q']    ?? '');
$filter_doc = trim($_GET['doc']  ?? '');
$sort       = trim($_GET['sort'] ?? 'date_desc');
$page_num   = max(1, (int)($_GET['p'] ?? 1));
$per_page   = 15;

$where_parts = ["cr.is_archived = 0", "cr.series_year = ?"];
$params      = [$year];
$types       = "i";

if ($search !== '') {
    $where_parts[] = "(cr.name LIKE ? OR cr.record_no LIKE ? OR cr.sponsor LIKE ?)";
    $like = "%{$search}%";
    $params = array_merge($params, [$like, $like, $like]);
    $types .= "sss";
}

if ($filter_doc === 'yes') {
    $where_parts[] = "(cr.document_path IS NOT NULL AND cr.document_path != '')";
} elseif ($filter_doc === 'no') {
    $where_parts[] = "(cr.document_path IS NULL OR cr.document_path = '')";
}

$where_sql = implode(' AND ', $where_parts);

$order_sql = match($sort) {
    'name_asc'   => 'cr.name ASC',
    'name_desc'  => 'cr.name DESC',
    'date_asc'   => 'cr.date_of_confirmation ASC',
    default      => 'cr.date_of_confirmation DESC',
};

$count_stmt = $conn->prepare("SELECT COUNT(*) FROM confirmation_records cr WHERE {$where_sql}");
$count_stmt->bind_param($types, ...$params);
$count_stmt->execute();
$total_records = (int)$count_stmt->get_result()->fetch_row()[0];
$count_stmt->close();

$total_pages = max(1, (int)ceil($total_records / $per_page));
$page_num    = min($page_num, $total_pages);
$offset      = ($page_num - 1) * $per_page;

$fetch_sql = "
    SELECT
        cr.id, cr.record_no, cr.name, cr.date_of_birth,
        cr.date_of_confirmation, cr.sponsor, cr.minister,
        cr.document_path, cr.remarks, cr.created_at
    FROM confirmation_records cr
    WHERE {$where_sql}
    ORDER BY {$order_sql}
    LIMIT ? OFFSET ?
";
$fetch_params = array_merge($params, [$per_page, $offset]);
$fetch_types  = $types . "ii";

$stmt = $conn->prepare($fetch_sql);
$stmt->bind_param($fetch_types, ...$fetch_params);
$stmt->execute();
$records = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

$stats_r = $conn->prepare("
    SELECT
        COUNT(*) AS total,
        COUNT(CASE WHEN document_path IS NOT NULL AND document_path != '' THEN 1 END) AS scanned,
        MIN(date_of_confirmation) AS first_date,
        MAX(date_of_confirmation) AS last_date
    FROM confirmation_records
    WHERE series_year = ? AND is_archived = 0
");
$stats_r->bind_param("i", $year);
$stats_r->execute();
$stats = $stats_r->get_result()->fetch_assoc();
$stats_r->close();

$scan_pct = $stats['total'] > 0
    ? round(($stats['scanned'] / $stats['total']) * 100)
    : 0;

function page_url($p, $year, $search, $filter_doc, $sort) {
    $q = http_build_query(array_filter([
        'year' => $year, 'q' => $search,
        'doc'  => $filter_doc, 'sort' => $sort, 'p' => $p,
    ], fn($v) => $v !== '' && $v !== 'date_desc' || $v === $p));
    return '?' . $q;
}

include $root . '/includes/header.php';
?>

<style>
    .series-header-card {
        background: linear-gradient(135deg, #4c1d95 0%, #6d28d9 60%, #7c3aed 100%);
        border-radius: 14px; padding: 22px 28px;
        display: flex; align-items: center;
        justify-content: space-between; gap: 20px; flex-wrap: wrap;
        margin-bottom: 20px; position: relative; overflow: hidden;
        box-shadow: 0 4px 20px rgba(109,40,217,0.25);
    }
    .series-header-card::after {
        content: '<?= $year ?>';
        position: absolute; right: 24px; top: 50%;
        transform: translateY(-50%);
        font-family: 'Playfair Display', serif; font-size: 5.5rem; font-weight: 700;
        color: rgba(255,255,255,0.06); pointer-events: none; line-height: 1; letter-spacing: -3px;
    }
    .shc-icon { width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.35rem;flex-shrink:0; }
    .shc-title { font-family:'Playfair Display',serif;font-size:1.15rem;color:#fff;font-weight:600;margin-bottom:3px; }
    .shc-sub { font-size:0.78rem;color:rgba(255,255,255,0.5); }
    .shc-stats { display:flex;gap:24px;flex-wrap:wrap; }
    .shc-stat { text-align:center; }
    .shc-stat-value { font-family:'Playfair Display',serif;font-size:1.55rem;color:#c4b5fd;font-weight:600;line-height:1; }
    .shc-stat-label { font-size:0.68rem;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:0.06em;margin-top:3px; }
    .shc-divider { width:1px;height:38px;background:rgba(255,255,255,0.15); }

    .scan-strip { background:#fff;border:1px solid #d1cdc4;border-radius:10px;padding:13px 18px;display:flex;align-items:center;gap:16px;margin-bottom:18px;flex-wrap:wrap;box-shadow:0 1px 4px rgba(15,32,68,0.05); }
    .scan-strip-bar { flex:1;min-width:120px;height:7px;background:#f3ede3;border-radius:99px;overflow:hidden; }
    .scan-strip-fill { height:100%;background:linear-gradient(to right,#8b5cf6,#a78bfa);border-radius:99px;transition:width 0.5s ease; }
    .scan-strip-pct { font-size:0.8rem;font-weight:600;color:#8b5cf6;white-space:nowrap; }

    .toolbar { display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px; }
    .search-wrap { position:relative;flex:1;min-width:200px;max-width:360px; }
    .search-wrap i { position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#c4b89a;font-size:0.82rem;pointer-events:none; }
    .search-input { width:100%;padding:8px 12px 8px 32px;border:1px solid #d1cdc4;border-radius:8px;font-size:0.845rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s; }
    .search-input:focus { border-color:#8b5cf6;box-shadow:0 0 0 3px rgba(139,92,246,0.1); }
    .filter-select { padding:8px 10px;border:1px solid #d1cdc4;border-radius:8px;font-size:0.82rem;color:#374151;background:#fff;outline:none;cursor:pointer; }
    .filter-select:focus { border-color:#8b5cf6; }
    .toolbar-btn { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:0.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all 0.18s; }
    .btn-purple { background:#7c3aed;color:#fff; }
    .btn-purple:hover { background:#6d28d9; }
    .btn-outline { background:#fff;color:#6b7280;border:1px solid #d1cdc4; }
    .btn-outline:hover { background:#faf7f0;border-color:#c4b89a;color:#0f2044; }

    .records-table-wrap { background:#fff;border:1px solid #d1cdc4;border-radius:14px;overflow:hidden;width:100%;min-width:0;box-shadow:0 2px 16px rgba(15,32,68,0.08); }
    .records-table-header { padding:15px 20px;border-bottom:2px solid #f0ebe0;background:#fdfcfa;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px; }
    .records-table-title { font-family:'Playfair Display',serif;font-size:0.92rem;font-weight:600;color:#0f2044;display:flex;align-items:center;gap:8px; }
    .records-count-badge { display:inline-flex;align-items:center;font-size:0.72rem;font-weight:600;background:#f5f3ff;color:#8b5cf6;padding:2px 9px;border-radius:99px; }

    .table { width:100%;border-collapse:collapse;font-size:0.85rem;table-layout:fixed; }
    .table th { background:#f5f0e8;padding:11px 16px;text-align:left;font-size:0.7rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#6b5f4e;border-bottom:2px solid #e8e0d0;white-space:nowrap; }
    .table td { padding:13px 16px;border-bottom:1px solid #f3ede3;color:#4a4a6a;vertical-align:middle; }
    .table tr:last-child td { border-bottom:none; }
    .table tbody tr:hover { background:#faf8f5; }
    .record-name { font-weight:600;color:#0f2044;font-size:0.855rem; }
    .record-sub { font-size:0.72rem;color:#9ca3af;margin-top:2px; }
    .doc-badge { display:inline-flex;align-items:center;gap:4px;font-size:0.7rem;font-weight:600;padding:3px 9px;border-radius:99px; }
    .doc-yes { background:#f0fdf4;color:#16a34a;border:1px solid #bbf7d0; }
    .doc-no  { background:#f9fafb;color:#9ca3af;border:1px solid #e5e7eb; }
    .action-btn { display:inline-flex;align-items:center;gap:4px;font-size:0.74rem;font-weight:500;padding:4px 10px;border-radius:6px;text-decoration:none;transition:all 0.15s;white-space:nowrap; }
    .action-view  { color:#8b5cf6;background:#f5f3ff; }
    .action-view:hover { background:#8b5cf6;color:#fff; }
    .action-edit  { color:#b8933a;background:#fdf8ec; }
    .action-edit:hover { background:#b8933a;color:#fff; }
    .action-print { color:#6b7280;background:#f3f4f6; }
    .action-print:hover { background:#6b7280;color:#fff; }

    .empty-state { text-align:center;padding:60px 24px; }
    .empty-icon { width:72px;height:72px;border-radius:20px;background:#f5f3ff;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;color:#8b5cf6;font-size:2rem; }

    .pagination { display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #f0ebe0;background:#fdfcfa;flex-wrap:wrap;gap:10px;border-radius:0 0 14px 14px; }
    .pagination-info { font-size:0.78rem;color:#9ca3af; }
    .pagination-links { display:flex;gap:4px; }
    .page-btn { display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;font-size:0.78rem;font-weight:500;text-decoration:none;border:1px solid #d1cdc4;color:#6b7280;background:#fff;transition:all 0.15s; }
    .page-btn:hover { border-color:#8b5cf6;color:#8b5cf6; }
    .page-btn.active { background:#7c3aed;color:#fff;border-color:#7c3aed; }
    .page-btn.disabled { opacity:0.35;pointer-events:none; }

    .alert { display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.83rem;margin-bottom:20px; }
    .alert-success { background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d; }
    .alert-error   { background:#fef2f2;border:1px solid #fecaca;color:#dc2626; }

    @media (max-width:768px) {
        .shc-stats { display:none; }
        .toolbar { flex-direction:column;align-items:stretch; }
        .search-wrap { max-width:100%; }
        .action-print { display:none; }
    }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-dove" style="color:#8b5cf6;margin-right:8px;font-size:1rem;"></i>
            Confirmation — <?= $year ?>
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/confirmation/series_list.php" style="color:#9ca3af;text-decoration:none;">Confirmation</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current"><?= $year ?> Series</span>
        </p>
    </div>
    <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
    <a href="/church/modules/church_records/confirmation/add_record.php?year=<?= $year ?>"
       class="toolbar-btn btn-purple">
        <i class="fas fa-plus"></i> Add Record
    </a>
    <?php endif; ?>
</div>

<div style="padding:24px 28px 60px;width:100%;box-sizing:border-box;">

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <div class="series-header-card">
        <div style="display:flex;align-items:center;gap:18px;">
            <div class="shc-icon"><i class="fas fa-dove"></i></div>
            <div>
                <div class="shc-title"><?= $year ?> Confirmation Series</div>
                <div class="shc-sub">
                    <?php if ($stats['first_date'] && $stats['last_date']): ?>
                        <?= date('M j', strtotime($stats['first_date'])) ?> –
                        <?= date('M j, Y', strtotime($stats['last_date'])) ?>
                    <?php else: ?>
                        No records yet
                    <?php endif; ?>
                    <?php if ($series['notes']): ?>
                        &nbsp;·&nbsp; <?= htmlspecialchars($series['notes']) ?>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <div class="shc-stats">
            <div class="shc-stat"><div class="shc-stat-value"><?= number_format($stats['total']) ?></div><div class="shc-stat-label">Records</div></div>
            <div class="shc-divider"></div>
            <div class="shc-stat"><div class="shc-stat-value"><?= $stats['scanned'] ?></div><div class="shc-stat-label">Scanned</div></div>
            <div class="shc-divider"></div>
            <div class="shc-stat"><div class="shc-stat-value"><?= $scan_pct ?>%</div><div class="shc-stat-label">Coverage</div></div>
        </div>
    </div>

    <?php if ($stats['total'] > 0): ?>
    <div class="scan-strip">
        <div style="font-size:0.78rem;color:#6b7280;white-space:nowrap;display:flex;align-items:center;gap:6px;">
            <i class="fas fa-file-image" style="color:#8b5cf6;"></i> Document scan coverage
        </div>
        <div class="scan-strip-bar"><div class="scan-strip-fill" style="width:<?= $scan_pct ?>%;"></div></div>
        <div class="scan-strip-pct"><?= $stats['scanned'] ?> / <?= $stats['total'] ?> scanned</div>
        <?php if ($scan_pct < 100): ?>
        <div style="font-size:0.72rem;color:#9ca3af;"><?= $stats['total'] - $stats['scanned'] ?> record<?= ($stats['total'] - $stats['scanned']) !== 1 ? 's' : '' ?> missing a scan</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <form method="GET" action="" id="filterForm">
        <input type="hidden" name="year" value="<?= $year ?>">
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="q" class="search-input"
                       placeholder="Search by name, record no., sponsor…"
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </div>
            <select name="doc" class="filter-select" onchange="this.form.submit()">
                <option value=""    <?= $filter_doc === ''    ? 'selected' : '' ?>>All Records</option>
                <option value="yes" <?= $filter_doc === 'yes' ? 'selected' : '' ?>>With Document</option>
                <option value="no"  <?= $filter_doc === 'no'  ? 'selected' : '' ?>>Missing Document</option>
            </select>
            <select name="sort" class="filter-select" onchange="this.form.submit()">
                <option value="date_desc" <?= $sort === 'date_desc' ? 'selected' : '' ?>>Newest First</option>
                <option value="date_asc"  <?= $sort === 'date_asc'  ? 'selected' : '' ?>>Oldest First</option>
                <option value="name_asc"  <?= $sort === 'name_asc'  ? 'selected' : '' ?>>Name A–Z</option>
                <option value="name_desc" <?= $sort === 'name_desc' ? 'selected' : '' ?>>Name Z–A</option>
            </select>
            <button type="submit" class="toolbar-btn btn-purple">
                <i class="fas fa-magnifying-glass"></i> Search
            </button>
            <?php if ($search !== '' || $filter_doc !== ''): ?>
            <a href="?year=<?= $year ?>" class="toolbar-btn btn-outline">
                <i class="fas fa-times"></i> Clear
            </a>
            <?php endif; ?>
            <div style="flex:1;"></div>
            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
            <a href="/church/modules/church_records/confirmation/series_list.php"
               class="toolbar-btn btn-outline" title="Back to series list">
                <i class="fas fa-layer-group"></i> All Series
            </a>
            <?php endif; ?>
        </div>
    </form>

    <div class="records-table-wrap">
        <div class="records-table-header">
            <div class="records-table-title">
                <i class="fas fa-list" style="color:#8b5cf6;font-size:0.8rem;"></i>
                Confirmation Records
                <span class="records-count-badge"><?= number_format($total_records) ?></span>
                <?php if ($search !== '' || $filter_doc !== ''): ?>
                <span style="font-size:0.72rem;color:#9ca3af;font-family:'DM Sans',sans-serif;font-weight:400;">— filtered results</span>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?>
            <div style="font-size:0.75rem;color:#9ca3af;">Page <?= $page_num ?> of <?= $total_pages ?></div>
            <?php endif; ?>
        </div>

        <?php if (empty($records)): ?>
        <div class="empty-state">
            <div class="empty-icon"><i class="fas fa-dove"></i></div>
            <?php if ($search !== '' || $filter_doc !== ''): ?>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:8px;">No matching records</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;">No confirmation records match your search. Try adjusting your filters.</p>
            <a href="?year=<?= $year ?>" class="toolbar-btn btn-outline" style="display:inline-flex;"><i class="fas fa-times"></i> Clear filters</a>
            <?php else: ?>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:8px;">No Records in <?= $year ?> Series</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:20px;max-width:340px;margin-left:auto;margin-right:auto;line-height:1.6;">This series is empty. Add the first confirmation record for <?= $year ?>.</p>
            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
            <a href="/church/modules/church_records/confirmation/add_record.php?year=<?= $year ?>"
               class="toolbar-btn btn-purple" style="display:inline-flex;">
                <i class="fas fa-plus"></i> Add First Record
            </a>
            <?php endif; ?>
            <?php endif; ?>
        </div>

        <?php else: ?>
        <div style="width:100%;overflow-x:auto;">
            <table class="table">
                <colgroup>
                    <col style="width:120px;">
                    <col style="min-width:180px;">
                    <col style="width:130px;">
                    <col style="min-width:160px;">
                    <col style="min-width:140px;">
                    <col style="width:110px;">
                    <col style="width:160px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Record No.</th>
                        <th>Confirmand's Name</th>
                        <th>Confirmation Date</th>
                        <th>Sponsor</th>
                        <th>Minister</th>
                        <th>Document</th>
                        <th style="text-align:center;">Actions</th>
                    </tr>
                </thead>
                <tbody>
                <?php foreach ($records as $rec): ?>
                <tr>
                    <td style="font-size:0.78rem;color:#6b7280;font-family:monospace;">
                        <?= $rec['record_no'] ? htmlspecialchars($rec['record_no']) : '—' ?>
                    </td>
                    <td>
                        <div class="record-name"><?= htmlspecialchars($rec['name']) ?></div>
                        <?php if ($rec['date_of_birth']): ?>
                        <div class="record-sub">b. <?= date('M j, Y', strtotime($rec['date_of_birth'])) ?></div>
                        <?php endif; ?>
                    </td>
                    <td style="font-size:0.8rem;color:#374151;white-space:nowrap;">
                        <?= $rec['date_of_confirmation']
                            ? date('M j, Y', strtotime($rec['date_of_confirmation']))
                            : '<span style="color:#c4b89a;">—</span>' ?>
                    </td>
                    <td style="font-size:0.8rem;color:#374151;">
                        <?= $rec['sponsor'] ? htmlspecialchars($rec['sponsor']) : '<span style="color:#c4b89a;font-size:0.78rem;">—</span>' ?>
                    </td>
                    <td style="font-size:0.8rem;color:#374151;">
                        <?= $rec['minister'] ? htmlspecialchars($rec['minister']) : '<span style="color:#c4b89a;font-size:0.78rem;">—</span>' ?>
                    </td>
                    <td>
                        <?php if (!empty($rec['document_path'])): ?>
                        <span class="doc-badge doc-yes"><i class="fas fa-file-image"></i> Scanned</span>
                        <?php else: ?>
                        <span class="doc-badge doc-no"><i class="fas fa-file-slash"></i> None</span>
                        <?php endif; ?>
                    </td>
                    <td style="text-align:center;white-space:nowrap;">
                        <div style="display:flex;align-items:center;justify-content:center;gap:5px;">
                            <a href="/church/modules/church_records/confirmation/view_record.php?id=<?= $rec['id'] ?>"
                               class="action-btn action-view" title="View">
                                <i class="fas fa-eye"></i> View
                            </a>
                            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
                            <a href="/church/modules/church_records/confirmation/edit_record.php?id=<?= $rec['id'] ?>"
                               class="action-btn action-edit" title="Edit">
                                <i class="fas fa-pen"></i>
                            </a>
                            <a href="/church/modules/church_records/confirmation/print_certificate.php?id=<?= $rec['id'] ?>"
                               class="action-btn action-print" title="Print Certificate" target="_blank">
                                <i class="fas fa-print"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <?php if ($total_pages > 1 || $total_records > 0): ?>
        <div class="pagination">
            <div class="pagination-info">
                Showing <?= number_format($offset + 1) ?>–<?= number_format(min($offset + $per_page, $total_records)) ?>
                of <?= number_format($total_records) ?> record<?= $total_records !== 1 ? 's' : '' ?>
            </div>
            <?php if ($total_pages > 1): ?>
            <div class="pagination-links">
                <a href="<?= page_url($page_num - 1, $year, $search, $filter_doc, $sort) ?>"
                   class="page-btn <?= $page_num <= 1 ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-left" style="font-size:0.65rem;"></i>
                </a>
                <?php
                $ws = max(1, $page_num - 2); $we = min($total_pages, $page_num + 2);
                if ($ws > 1): ?>
                <a href="<?= page_url(1, $year, $search, $filter_doc, $sort) ?>" class="page-btn">1</a>
                <?php if ($ws > 2): ?><span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span><?php endif;
                endif;
                for ($i = $ws; $i <= $we; $i++): ?>
                <a href="<?= page_url($i, $year, $search, $filter_doc, $sort) ?>"
                   class="page-btn <?= $i === $page_num ? 'active' : '' ?>"><?= $i ?></a>
                <?php endfor;
                if ($we < $total_pages): ?>
                <?php if ($we < $total_pages - 1): ?><span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span><?php endif; ?>
                <a href="<?= page_url($total_pages, $year, $search, $filter_doc, $sort) ?>" class="page-btn"><?= $total_pages ?></a>
                <?php endif; ?>
                <a href="<?= page_url($page_num + 1, $year, $search, $filter_doc, $sort) ?>"
                   class="page-btn <?= $page_num >= $total_pages ? 'disabled' : '' ?>">
                    <i class="fas fa-chevron-right" style="font-size:0.65rem;"></i>
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<?php include $root . '/includes/footer.php'; ?>