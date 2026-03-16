<?php
// church/modules/church_records/search.php
// 4.12 — Search across all record types
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$page_title = 'Search Records';

// ── Parameters ───────────────────────────────────────────────
$q        = trim($_GET['q']    ?? '');
$type_f   = trim($_GET['type'] ?? '');   // baptism | confirmation | wedding | funeral | ''
$year_f   = trim($_GET['year'] ?? '');
$page     = max(1, (int)($_GET['page'] ?? 1));
$per_page = 20;
$offset   = ($page - 1) * $per_page;

$results    = [];
$total_hits = 0;
$searched   = ($q !== '' || $year_f !== '');

// ── Available years for filter ───────────────────────────────
$years = [];
$yr = $conn->query("
    SELECT DISTINCT series_year FROM (
        SELECT series_year FROM baptism_records      WHERE is_archived=0
        UNION SELECT series_year FROM confirmation_records WHERE is_archived=0
        UNION SELECT series_year FROM wedding_records      WHERE is_archived=0
        UNION SELECT series_year FROM funeral_records      WHERE is_archived=0
    ) t WHERE series_year IS NOT NULL ORDER BY series_year DESC
");
while ($row = $yr->fetch_row()) $years[] = $row[0];

// ── Build search queries per type ────────────────────────────
if ($searched) {
    $like = '%' . $conn->real_escape_string($q) . '%';
    $yr_c = $year_f !== '' ? "AND series_year = " . (int)$year_f : "";

    $type_queries = [];

    // BAPTISM
    if ($type_f === '' || $type_f === 'baptism') {
        $type_queries[] = "
            SELECT 'Baptism' AS type_label, 'baptism' AS type_slug,
                   id, record_no, series_year,
                   child_name          AS primary_name,
                   CONCAT(COALESCE(father_name,''), ' / ', COALESCE(mother_name,'')) AS secondary_name,
                   date_of_baptism     AS record_date,
                   created_at
            FROM baptism_records
            WHERE is_archived = 0 $yr_c
              AND (child_name LIKE '$like'
                OR father_name LIKE '$like'
                OR mother_name LIKE '$like'
                OR godfather   LIKE '$like'
                OR godmother   LIKE '$like'
                OR record_no   LIKE '$like'
                OR address     LIKE '$like')
        ";
    }

    // CONFIRMATION
    if ($type_f === '' || $type_f === 'confirmation') {
        $type_queries[] = "
            SELECT 'Confirmation' AS type_label, 'confirmation' AS type_slug,
                   id, record_no, series_year,
                   name                AS primary_name,
                   CONCAT(COALESCE(father_name,''), ' / ', COALESCE(mother_name,'')) AS secondary_name,
                   date_of_confirmation AS record_date,
                   created_at
            FROM confirmation_records
            WHERE is_archived = 0 $yr_c
              AND (name        LIKE '$like'
                OR father_name LIKE '$like'
                OR mother_name LIKE '$like'
                OR sponsor     LIKE '$like'
                OR record_no   LIKE '$like'
                OR address     LIKE '$like')
        ";
    }

    // WEDDING
    if ($type_f === '' || $type_f === 'wedding') {
        $type_queries[] = "
            SELECT 'Wedding' AS type_label, 'wedding' AS type_slug,
                   id, record_no, series_year,
                   CONCAT(groom_name, ' & ', bride_name) AS primary_name,
                   ''                  AS secondary_name,
                   date_of_wedding     AS record_date,
                   created_at
            FROM wedding_records
            WHERE is_archived = 0 $yr_c
              AND (groom_name LIKE '$like'
                OR bride_name LIKE '$like'
                OR principal_sponsor_male   LIKE '$like'
                OR principal_sponsor_female LIKE '$like'
                OR record_no LIKE '$like'
                OR groom_address LIKE '$like'
                OR bride_address LIKE '$like')
        ";
    }

    // FUNERAL
    if ($type_f === '' || $type_f === 'funeral') {
        $type_queries[] = "
            SELECT 'Funeral' AS type_label, 'funeral' AS type_slug,
                   id, record_no, series_year,
                   deceased_name       AS primary_name,
                   COALESCE(next_of_kin, next_of_kin_name, '') AS secondary_name,
                   date_of_funeral     AS record_date,
                   created_at
            FROM funeral_records
            WHERE is_archived = 0 $yr_c
              AND (deceased_name   LIKE '$like'
                OR next_of_kin     LIKE '$like'
                OR next_of_kin_name LIKE '$like'
                OR record_no       LIKE '$like'
                OR address         LIKE '$like')
        ";
    }

    if (!empty($type_queries)) {
        $union_sql = implode(" UNION ALL ", $type_queries);

        // Total count
        $count_res = $conn->query("SELECT COUNT(*) FROM ($union_sql) counted");
        $total_hits = $count_res ? (int)$count_res->fetch_row()[0] : 0;

        // Paginated results
        $res = $conn->query("$union_sql ORDER BY created_at DESC LIMIT $per_page OFFSET $offset");
        if ($res) {
            while ($row = $res->fetch_assoc()) $results[] = $row;
        }
    }
}

$total_pages = $total_hits > 0 ? ceil($total_hits / $per_page) : 1;

include $root . '/includes/header.php';
?>

<style>
    .search-hero {
        background: linear-gradient(135deg, #0f2044, #162d5c);
        border-radius: 16px;
        padding: 28px 32px 24px;
        margin-bottom: 24px;
        position: relative;
        overflow: hidden;
    }
    .search-hero::after {
        content: '\2726';
        position: absolute;
        right: 32px; top: 50%;
        transform: translateY(-50%);
        font-size: 6rem;
        color: rgba(201,162,39,0.05);
        pointer-events: none;
        font-family: serif;
    }
    .search-hero-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.2rem;
        font-weight: 700;
        color: #fff;
        margin-bottom: 4px;
    }
    .search-hero-sub {
        font-size: 0.78rem;
        color: rgba(255,255,255,0.4);
        margin-bottom: 20px;
    }
    .search-bar-wrap {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    .search-input-main {
        flex: 1;
        min-width: 200px;
        padding: 11px 16px 11px 42px;
        border-radius: 10px;
        border: 1.5px solid rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.08);
        color: #fff;
        font-size: 0.9rem;
        font-family: 'DM Sans', sans-serif;
        outline: none;
        transition: border-color 0.18s, background 0.18s;
        position: relative;
    }
    .search-input-main::placeholder { color: rgba(255,255,255,0.35); }
    .search-input-main:focus {
        border-color: #e0c060;
        background: rgba(255,255,255,0.12);
    }
    .search-input-wrap { position: relative; flex: 1; min-width: 200px; }
    .search-input-icon {
        position: absolute;
        left: 13px; top: 50%;
        transform: translateY(-50%);
        color: rgba(255,255,255,0.4);
        font-size: 0.82rem;
        pointer-events: none;
    }
    .search-filter-select {
        padding: 11px 14px;
        border-radius: 10px;
        border: 1.5px solid rgba(255,255,255,0.15);
        background: rgba(255,255,255,0.08);
        color: rgba(255,255,255,0.8);
        font-size: 0.85rem;
        font-family: 'DM Sans', sans-serif;
        outline: none;
        cursor: pointer;
        transition: border-color 0.18s;
        min-width: 140px;
    }
    .search-filter-select option { background: #0f2044; color: #fff; }
    .search-filter-select:focus { border-color: #e0c060; }
    .search-btn {
        padding: 11px 22px;
        border-radius: 10px;
        background: #b8933a;
        color: #fff;
        font-size: 0.88rem;
        font-weight: 600;
        border: none;
        cursor: pointer;
        display: inline-flex;
        align-items: center;
        gap: 8px;
        transition: background 0.15s;
        font-family: 'DM Sans', sans-serif;
        white-space: nowrap;
    }
    .search-btn:hover { background: #9a7a30; }

    /* Results */
    .results-card {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        overflow: hidden;
    }
    .results-header {
        padding: 14px 22px;
        border-bottom: 1px solid #f0ebe0;
        background: #fdfcfa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .results-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.9rem;
        font-weight: 600;
        color: #0f2044;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .results-meta {
        font-size: 0.75rem;
        color: #9ca3af;
    }
    .hit-count {
        display: inline-flex;
        align-items: center;
        padding: 2px 10px;
        background: #eff6ff;
        color: #1d4ed8;
        border-radius: 99px;
        font-size: 0.72rem;
        font-weight: 600;
    }
    .result-row {
        display: grid;
        grid-template-columns: 1fr auto auto auto;
        align-items: center;
        gap: 16px;
        padding: 14px 22px;
        border-bottom: 1px solid #f5f0e8;
        transition: background 0.12s;
    }
    .result-row:last-child { border-bottom: none; }
    .result-row:hover { background: #faf7f0; }
    .result-name {
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f2044;
        margin-bottom: 3px;
    }
    .result-secondary {
        font-size: 0.75rem;
        color: #9ca3af;
    }
    .result-no {
        font-size: 0.75rem;
        color: #6b7280;
        font-family: monospace;
        white-space: nowrap;
    }
    .result-date {
        font-size: 0.75rem;
        color: #9ca3af;
        white-space: nowrap;
    }

    /* Type badges */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 99px;
        white-space: nowrap;
    }
    .type-baptism      { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .type-confirmation { background: #f5f3ff; color: #8b5cf6; border: 1px solid #ddd6fe; }
    .type-wedding      { background: #fdf8ec; color: #b8933a; border: 1px solid #fde68a; }
    .type-funeral      { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

    /* Type filter pills */
    .type-pills { display: flex; gap: 8px; flex-wrap: wrap; margin-top: 14px; }
    .type-pill {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 5px 14px;
        border-radius: 99px;
        font-size: 0.75rem;
        font-weight: 600;
        border: 1.5px solid rgba(255,255,255,0.15);
        color: rgba(255,255,255,0.6);
        background: rgba(255,255,255,0.07);
        cursor: pointer;
        text-decoration: none;
        transition: all 0.15s;
    }
    .type-pill:hover { border-color: rgba(255,255,255,0.4); color: #fff; }
    .type-pill.active { border-color: #e0c060; color: #e0c060; background: rgba(224,192,96,0.12); }

    /* Empty / no results */
    .no-results {
        text-align: center;
        padding: 56px 24px;
        color: #c4b89a;
    }
    .no-results i { font-size: 2.4rem; opacity: 0.25; display: block; margin-bottom: 14px; }

    /* Pagination */
    .pagination {
        display: flex;
        gap: 4px;
        align-items: center;
        justify-content: center;
        padding: 16px 22px;
        border-top: 1px solid #f0ebe0;
        flex-wrap: wrap;
    }
    .page-btn {
        min-width: 34px; height: 34px;
        border-radius: 8px;
        border: 1px solid #ede8de;
        background: #fff;
        color: #6b7280;
        font-size: 0.8rem;
        display: inline-flex;
        align-items: center;
        justify-content: center;
        text-decoration: none;
        padding: 0 10px;
        transition: all 0.14s;
        font-family: 'DM Sans', sans-serif;
    }
    .page-btn:hover { background: #faf7f0; border-color: #b8933a; color: #b8933a; }
    .page-btn.active { background: #0f2044; border-color: #0f2044; color: #fff; font-weight: 600; }
    .page-btn.disabled { opacity: 0.35; pointer-events: none; }

    @media (max-width: 680px) {
        .result-row { grid-template-columns: 1fr auto; }
        .result-no, .result-date { display: none; }
    }
    @media (max-width: 440px) {
        .search-bar-wrap { flex-direction: column; }
        .search-filter-select { min-width: unset; }
    }
</style>

<!-- Page header -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-magnifying-glass" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>
            Search Records
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Search</span>
        </p>
    </div>
    <a href="/church/modules/church_records/index.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:8px 16px;
              background:#fff;border:1px solid #ede8de;border-radius:8px;
              font-size:0.8rem;color:#6b7280;text-decoration:none;transition:all 0.15s;"
       onmouseover="this.style.borderColor='#b8933a';this.style.color='#b8933a'"
       onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
        <i class="fas fa-arrow-left" style="font-size:0.7rem;"></i> Back to Records
    </a>
</div>

<div style="padding:24px 24px 60px;">

    <!-- Search hero -->
    <div class="search-hero">
        <div class="search-hero-title">
            <i class="fas fa-magnifying-glass" style="color:#e0c060;margin-right:8px;"></i>
            Search All Sacramental Records
        </div>
        <div class="search-hero-sub">
            Search by name, record number, address, sponsors, or parents — across all record types at once.
        </div>

        <form method="GET" action="">
            <div class="search-bar-wrap">
                <div class="search-input-wrap">
                    <i class="fas fa-magnifying-glass search-input-icon"></i>
                    <input type="text"
                           name="q"
                           class="search-input-main"
                           placeholder="e.g. Juan dela Cruz, 2024-B-001, sponsor name…"
                           value="<?= htmlspecialchars($q) ?>"
                           autofocus>
                </div>

                <select name="year" class="search-filter-select">
                    <option value="">All Years</option>
                    <?php foreach ($years as $y): ?>
                    <option value="<?= $y ?>" <?= $year_f == $y ? 'selected' : '' ?>><?= $y ?></option>
                    <?php endforeach; ?>
                </select>

                <button type="submit" class="search-btn">
                    <i class="fas fa-magnifying-glass"></i> Search
                </button>

                <?php if ($searched): ?>
                <a href="/church/modules/church_records/search.php"
                   style="padding:11px 16px;border-radius:10px;border:1.5px solid rgba(255,255,255,0.15);
                          background:transparent;color:rgba(255,255,255,0.5);font-size:0.82rem;
                          text-decoration:none;display:inline-flex;align-items:center;gap:6px;
                          transition:all 0.15s;"
                   onmouseover="this.style.color='#fff';this.style.borderColor='rgba(255,255,255,0.4)'"
                   onmouseout="this.style.color='rgba(255,255,255,0.5)';this.style.borderColor='rgba(255,255,255,0.15)'">
                    <i class="fas fa-xmark"></i> Clear
                </a>
                <?php endif; ?>
            </div>

            <!-- Type filter pills -->
            <div class="type-pills">
                <?php
                $pill_types = [
                    '' => ['All Types', 'fa-layer-group'],
                    'baptism'      => ['Baptism',      'fa-droplet'],
                    'confirmation' => ['Confirmation', 'fa-hands-praying'],
                    'wedding'      => ['Wedding',      'fa-ring'],
                    'funeral'      => ['Funeral',      'fa-cross'],
                ];
                foreach ($pill_types as $val => [$label, $icon]):
                    $params = http_build_query(array_filter(['q' => $q, 'year' => $year_f, 'type' => $val]));
                ?>
                <a href="?<?= $params ?>"
                   class="type-pill <?= $type_f === $val ? 'active' : '' ?>">
                    <i class="fas <?= $icon ?>"></i> <?= $label ?>
                </a>
                <?php endforeach; ?>
            </div>
        </form>
    </div>

    <!-- Results -->
    <?php if (!$searched): ?>
    <!-- Idle state -->
    <div class="results-card">
        <div class="no-results">
            <i class="fas fa-magnifying-glass"></i>
            <p style="font-size:0.92rem;color:#6b7280;font-weight:500;margin-bottom:6px;">
                Enter a name or record number to begin
            </p>
            <p style="font-size:0.78rem;color:#9ca3af;">
                Searches across Baptism, Confirmation, Wedding, and Funeral records simultaneously.
            </p>
        </div>
    </div>

    <?php elseif (empty($results)): ?>
    <!-- No matches -->
    <div class="results-card">
        <div class="no-results">
            <i class="fas fa-file-circle-xmark"></i>
            <p style="font-size:0.92rem;color:#6b7280;font-weight:500;margin-bottom:6px;">
                No records found for "<?= htmlspecialchars($q) ?>"
            </p>
            <p style="font-size:0.78rem;color:#9ca3af;margin-bottom:16px;">
                Try a different name, record number, or remove year/type filters.
            </p>
            <a href="/church/modules/church_records/search.php"
               style="font-size:0.8rem;color:#b8933a;text-decoration:none;">
                Clear search and try again
            </a>
        </div>
    </div>

    <?php else: ?>
    <!-- Results table -->
    <div class="results-card">
        <div class="results-header">
            <div class="results-title">
                <i class="fas fa-list" style="color:#b8933a;font-size:0.8rem;"></i>
                Search Results
                <span class="hit-count"><?= number_format($total_hits) ?> match<?= $total_hits !== 1 ? 'es' : '' ?></span>
            </div>
            <div class="results-meta">
                <?php if ($q): ?>
                Showing results for <strong style="color:#0f2044;">"<?= htmlspecialchars($q) ?>"</strong>
                <?php endif; ?>
                <?php if ($type_f): ?>
                · <?= ucfirst($type_f) ?> only
                <?php endif; ?>
                <?php if ($year_f): ?>
                · Year <?= $year_f ?>
                <?php endif; ?>
            </div>
        </div>

        <?php
        $type_icons = [
            'baptism'      => 'fa-droplet',
            'confirmation' => 'fa-hands-praying',
            'wedding'      => 'fa-ring',
            'funeral'      => 'fa-cross',
        ];
        foreach ($results as $r):
            $slug = $r['type_slug'];
            $view_url = "/church/modules/church_records/{$slug}/view_record.php?id={$r['id']}";
        ?>
        <div class="result-row">
            <div>
                <div class="result-name">
                    <?php
                    // Highlight query in name
                    if ($q !== '') {
                        $safe_name = htmlspecialchars($r['primary_name']);
                        $safe_q    = preg_quote(htmlspecialchars($q), '/');
                        echo preg_replace('/(' . $safe_q . ')/i',
                            '<mark style="background:#fef9c3;padding:0 1px;border-radius:2px;">$1</mark>',
                            $safe_name);
                    } else {
                        echo htmlspecialchars($r['primary_name']);
                    }
                    ?>
                </div>
                <?php if (!empty(trim($r['secondary_name'], '/ '))): ?>
                <div class="result-secondary">
                    <?= htmlspecialchars(trim($r['secondary_name'], '/ ')) ?>
                </div>
                <?php endif; ?>
            </div>

            <span class="type-badge type-<?= $slug ?>">
                <i class="fas <?= $type_icons[$slug] ?>"></i>
                <?= $r['type_label'] ?>
            </span>

            <div style="text-align:right;">
                <div class="result-no"><?= htmlspecialchars($r['record_no']) ?></div>
                <div class="result-date">
                    <?= $r['series_year'] ?>
                    <?= $r['record_date'] ? ' · ' . date('M j', strtotime($r['record_date'])) : '' ?>
                </div>
            </div>

            <a href="<?= $view_url ?>"
               style="display:inline-flex;align-items:center;gap:5px;padding:7px 14px;
                      border-radius:7px;background:#fdf8ec;color:#b8933a;
                      border:1px solid #e8d99a;font-size:0.78rem;font-weight:500;
                      text-decoration:none;white-space:nowrap;transition:all 0.14s;"
               onmouseover="this.style.background='#b8933a';this.style.color='#fff';this.style.borderColor='#b8933a'"
               onmouseout="this.style.background='#fdf8ec';this.style.color='#b8933a';this.style.borderColor='#e8d99a'">
                View <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
            </a>
        </div>
        <?php endforeach; ?>

        <!-- Pagination -->
        <?php if ($total_pages > 1): ?>
        <div class="pagination">
            <?php
            $base_params = array_filter(['q' => $q, 'type' => $type_f, 'year' => $year_f]);
            ?>
            <a href="?<?= http_build_query(array_merge($base_params, ['page' => max(1, $page-1)])) ?>"
               class="page-btn <?= $page <= 1 ? 'disabled' : '' ?>">
                <i class="fas fa-chevron-left" style="font-size:0.7rem;"></i>
            </a>

            <?php for ($p = max(1, $page-2); $p <= min($total_pages, $page+2); $p++): ?>
            <a href="?<?= http_build_query(array_merge($base_params, ['page' => $p])) ?>"
               class="page-btn <?= $p === $page ? 'active' : '' ?>">
                <?= $p ?>
            </a>
            <?php endfor; ?>

            <a href="?<?= http_build_query(array_merge($base_params, ['page' => min($total_pages, $page+1)])) ?>"
               class="page-btn <?= $page >= $total_pages ? 'disabled' : '' ?>">
                <i class="fas fa-chevron-right" style="font-size:0.7rem;"></i>
            </a>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <div style="padding:10px 22px;background:#fdfcfa;border-top:1px solid #f0ebe0;
                    font-size:0.72rem;color:#9ca3af;border-radius:0 0 14px 14px;">
            Page <?= $page ?> of <?= $total_pages ?> &middot;
            <?= number_format($total_hits) ?> total result<?= $total_hits !== 1 ? 's' : '' ?>
            <?= $q ? ' for "' . htmlspecialchars($q) . '"' : '' ?>
        </div>
    </div>
    <?php endif; ?>

</div>

<?php include $root . '/includes/footer.php'; ?>