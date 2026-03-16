<?php
// church/modules/church_records/index.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$page_title = 'Church Records';

// ── Record counts per type ──────────────────────────────────
function db_count($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? (int)$r->fetch_row()[0] : 0;
}

$count_baptism      = db_count($conn, "SELECT COUNT(*) FROM baptism_records      WHERE is_archived = 0");
$count_confirmation = db_count($conn, "SELECT COUNT(*) FROM confirmation_records WHERE is_archived = 0");
$count_communion    = db_count($conn, "SELECT COUNT(*) FROM communion_records    WHERE is_archived = 0");
$count_wedding      = db_count($conn, "SELECT COUNT(*) FROM wedding_records      WHERE is_archived = 0");
$count_funeral      = db_count($conn, "SELECT COUNT(*) FROM funeral_records      WHERE is_archived = 0");
$count_total        = $count_baptism + $count_confirmation + $count_communion + $count_wedding + $count_funeral;

// ── Series counts per type ───────────────────────────────────
$series_baptism      = db_count($conn, "SELECT COUNT(*) FROM baptism_series");
$series_confirmation = db_count($conn, "SELECT COUNT(*) FROM confirmation_series");
$series_communion    = db_count($conn, "SELECT COUNT(*) FROM communion_series");
$series_wedding      = db_count($conn, "SELECT COUNT(*) FROM wedding_series");
$series_funeral      = db_count($conn, "SELECT COUNT(*) FROM funeral_series");

// ── Most recent record dates per type ───────────────────────
function last_record_date($conn, $table) {
    $r = $conn->query("SELECT MAX(created_at) FROM `{$table}` WHERE is_archived = 0");
    $val = $r ? $r->fetch_row()[0] : null;
    return $val ? date('M j, Y', strtotime($val)) : 'No records yet';
}

$last_baptism      = last_record_date($conn, 'baptism_records');
$last_confirmation = last_record_date($conn, 'confirmation_records');
$last_communion    = last_record_date($conn, 'communion_records');
$last_wedding      = last_record_date($conn, 'wedding_records');
$last_funeral      = last_record_date($conn, 'funeral_records');

// ── Recent records across all types ─────────────────────────
$recent_sql = "
    SELECT 'Baptism' AS type, 'baptism' AS type_slug,
           child_name AS primary_name,
           series_year, created_at, id
    FROM baptism_records WHERE is_archived = 0
    UNION ALL
    SELECT 'Communion', 'communion',
           name,
           series_year, created_at, id
    FROM communion_records WHERE is_archived = 0
    UNION ALL
    SELECT 'Confirmation', 'confirmation',
           name,
           series_year, created_at, id
    FROM confirmation_records WHERE is_archived = 0
    UNION ALL
    SELECT 'Wedding', 'wedding',
           CONCAT(groom_name, ' & ', bride_name),
           series_year, created_at, id
    FROM wedding_records WHERE is_archived = 0
    UNION ALL
    SELECT 'Funeral', 'funeral',
           deceased_name,
           series_year, created_at, id
    FROM funeral_records WHERE is_archived = 0
    ORDER BY created_at DESC
    LIMIT 6
";
$recent_records = [];
$rr = $conn->query($recent_sql);
if ($rr) {
    while ($row = $rr->fetch_assoc()) $recent_records[] = $row;
}

include $root . '/includes/header.php';
?>

<style>
    /* ── Record type cards ── */
    .record-type-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
        align-items: start;
    }
    @media (max-width: 1200px) {
        .record-type-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 860px) {
        .record-type-grid { grid-template-columns: repeat(2, 1fr); }
    }
    @media (max-width: 560px) {
        .record-type-grid { grid-template-columns: 1fr; }
    }

    .record-type-card {
        background: #fff;
        border: 1px solid #d1cdc4;
        border-radius: 16px;
        padding: 28px 24px 22px;
        position: relative;
        overflow: hidden;
        text-decoration: none;
        display: flex;
        flex-direction: column;
        gap: 0;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
        cursor: pointer;
        box-shadow: 0 1px 6px rgba(15,32,68,0.06);
    }
    .record-type-card:hover {
        transform: translateY(-4px);
        box-shadow: 0 12px 36px rgba(0,0,0,0.1);
        border-color: var(--rtc-color);
    }

    .record-type-card::before {
        content: '';
        position: absolute;
        top: 0; left: 0; right: 0;
        height: 4px;
        background: var(--rtc-color, #b8933a);
        border-radius: 16px 16px 0 0;
    }

    .rtc-icon-wrap {
        width: 52px; height: 52px;
        border-radius: 14px;
        display: flex; align-items: center; justify-content: center;
        font-size: 1.3rem;
        margin-bottom: 18px;
        background: var(--rtc-bg, #fdf8ec);
        color: var(--rtc-color, #b8933a);
        flex-shrink: 0;
    }

    .rtc-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.1rem;
        font-weight: 600;
        color: #0f2044;
        margin-bottom: 4px;
    }

    .rtc-desc {
        font-size: 0.8rem;
        color: #9ca3af;
        line-height: 1.5;
        margin-bottom: 20px;
        flex: 1;
    }

    .rtc-stats {
        display: flex;
        align-items: center;
        gap: 16px;
        padding-top: 16px;
        border-top: 1px solid #f3ede3;
        margin-top: auto;
    }

    .rtc-stat { display: flex; flex-direction: column; gap: 1px; }
    .rtc-stat-value {
        font-family: 'Playfair Display', serif;
        font-size: 1.3rem;
        font-weight: 600;
        color: #0f2044;
        line-height: 1;
    }
    .rtc-stat-label {
        font-size: 0.68rem;
        color: #c4b89a;
        font-weight: 500;
        letter-spacing: 0.03em;
        text-transform: uppercase;
    }
    .rtc-stat-divider {
        width: 1px;
        height: 28px;
        background: #ede8de;
    }
    .rtc-arrow {
        margin-left: auto;
        width: 32px; height: 32px;
        border-radius: 50%;
        display: flex; align-items: center; justify-content: center;
        background: var(--rtc-bg, #fdf8ec);
        color: var(--rtc-color, #b8933a);
        font-size: 0.75rem;
        transition: background 0.18s, color 0.18s;
        flex-shrink: 0;
    }
    .record-type-card:hover .rtc-arrow {
        background: var(--rtc-color, #b8933a);
        color: #fff;
    }

    /* ── Summary banner ── */
    .records-banner {
        background: linear-gradient(135deg, #0f2044 0%, #162d5c 60%, #1a3870 100%);
        border-radius: 14px;
        padding: 22px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 28px;
        position: relative;
        overflow: hidden;
        box-shadow: 0 4px 20px rgba(15,32,68,0.2);
    }
    .records-banner::after {
        content: '✦';
        position: absolute;
        right: 28px; top: 50%;
        transform: translateY(-50%);
        font-size: 5.5rem;
        color: rgba(201,162,39,0.06);
        pointer-events: none;
        font-family: serif;
    }
    .banner-left { display: flex; align-items: center; gap: 18px; }
    .banner-icon {
        width: 52px; height: 52px;
        border-radius: 14px;
        background: rgba(184,147,58,0.15);
        display: flex; align-items: center; justify-content: center;
        color: #e0c060;
        font-size: 1.3rem;
        flex-shrink: 0;
    }
    .banner-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.15rem;
        color: #fff;
        font-weight: 600;
        margin-bottom: 3px;
    }
    .banner-sub { font-size: 0.78rem; color: rgba(255,255,255,0.45); }
    .banner-stats { display: flex; gap: 28px; flex-wrap: wrap; }
    .banner-stat { text-align: center; }
    .banner-stat-value {
        font-family: 'Playfair Display', serif;
        font-size: 1.6rem;
        color: #e0c060;
        font-weight: 600;
        line-height: 1;
    }
    .banner-stat-label {
        font-size: 0.7rem;
        color: rgba(255,255,255,0.4);
        text-transform: uppercase;
        letter-spacing: 0.06em;
        margin-top: 3px;
    }
    .banner-divider {
        width: 1px; height: 40px;
        background: rgba(255,255,255,0.1);
    }

    /* ── Recent records card ── */
    .recent-card {
        background: #fff;
        border: 1px solid #d1cdc4;
        border-radius: 14px;
        overflow: hidden;
        width: 100%;
        box-shadow: 0 2px 16px rgba(15,32,68,0.08);
    }
    .recent-card-header {
        padding: 15px 22px;
        border-bottom: 2px solid #f0ebe0;
        background: #fdfcfa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        flex-wrap: wrap;
        gap: 10px;
    }
    .recent-card-header-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.92rem;
        font-weight: 600;
        color: #0f2044;
        display: flex;
        align-items: center;
        gap: 8px;
    }

    /* ── Table overrides ── */
    .table {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.85rem;
    }
    .table th {
        background: #f5f0e8;
        padding: 11px 16px;
        text-align: left;
        font-size: 0.7rem;
        font-weight: 700;
        letter-spacing: 0.07em;
        text-transform: uppercase;
        color: #6b5f4e;
        border-bottom: 2px solid #e8e0d0;
        white-space: nowrap;
    }
    .table td {
        padding: 13px 16px;
        border-bottom: 1px solid #f3ede3;
        color: #4a4a6a;
        vertical-align: middle;
    }
    .table tr:last-child td { border-bottom: none; }
    .table tbody tr:hover { background: #faf8f5; }

    /* ── Type badges ── */
    .type-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 0.72rem;
        font-weight: 600;
        padding: 3px 10px;
        border-radius: 99px;
    }
    .type-baptism      { background: #eff6ff; color: #3b82f6; border: 1px solid #bfdbfe; }
    .type-communion    { background: #ecfdf5; color: #10b981; border: 1px solid #a7f3d0; }
    .type-confirmation { background: #f5f3ff; color: #8b5cf6; border: 1px solid #ddd6fe; }
    .type-wedding      { background: #fdf8ec; color: #b8933a; border: 1px solid #fde68a; }
    .type-funeral      { background: #f3f4f6; color: #6b7280; border: 1px solid #e5e7eb; }

    /* ── Quick action buttons ── */
    .quick-actions { display: flex; gap: 10px; flex-wrap: wrap; }
    .quick-btn {
        display: inline-flex;
        align-items: center;
        gap: 7px;
        padding: 8px 16px;
        border-radius: 8px;
        font-size: 0.8rem;
        font-weight: 500;
        text-decoration: none;
        transition: all 0.18s;
        border: 1px solid transparent;
    }
    .quick-btn-primary { background: #0f2044; color: #fff; }
    .quick-btn-primary:hover { background: #162d5c; }
    .quick-btn-outline { background: #fff; color: #6b7280; border-color: #d1cdc4; }
    .quick-btn-outline:hover { background: #faf7f0; color: #0f2044; border-color: #c4b89a; }

    /* ── Responsive ── */
    @media (max-width: 640px) {
        .record-type-grid { grid-template-columns: 1fr; }
        .banner-stats { display: none; }
    }
</style>

<!-- ── Page header ───────────────────────────────────────── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-book-open" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>
            Church Records
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Church Records</span>
        </p>
    </div>
    <div class="quick-actions">
        <a href="/church/modules/church_records/search.php" class="quick-btn quick-btn-outline">
            <i class="fas fa-magnifying-glass"></i> Search All Records
        </a>
        <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
        <a href="/church/modules/church_records/baptism/series_list.php" class="quick-btn quick-btn-primary">
            <i class="fas fa-plus"></i> New Record
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main content ──────────────────────────────────────── -->
<div style="padding:24px 28px 60px; width:100%; box-sizing:border-box;">

    <!-- ── Summary banner ── -->
    <div class="records-banner">
        <div class="banner-left">
            <div class="banner-icon">
                <i class="fas fa-book-open"></i>
            </div>
            <div>
                <div class="banner-title">Sacramental Records</div>
                <div class="banner-sub">Baptism · Communion · Confirmation · Wedding · Funeral</div>
            </div>
        </div>
        <div class="banner-stats">
            <div class="banner-stat">
                <div class="banner-stat-value"><?= number_format($count_total) ?></div>
                <div class="banner-stat-label">Total Records</div>
            </div>
            <div class="banner-divider"></div>
            <div class="banner-stat">
                <div class="banner-stat-value"><?= $series_baptism + $series_confirmation + $series_communion + $series_wedding + $series_funeral ?></div>
                <div class="banner-stat-label">Year Series</div>
            </div>
            <div class="banner-divider"></div>
            <div class="banner-stat">
                <div class="banner-stat-value"><?= date('Y') ?></div>
                <div class="banner-stat-label">Current Year</div>
            </div>
        </div>
    </div>

    <!-- ── Section label ── -->
    <div style="margin-bottom:18px;">
        <p style="font-family:'Playfair Display',serif;font-size:0.92rem;font-weight:600;
                  color:#0f2044;margin-bottom:4px;display:flex;align-items:center;gap:8px;">
            <i class="fas fa-th-large" style="color:#b8933a;font-size:0.8rem;"></i>
            Select a Record Type
        </p>
        <p style="font-size:0.78rem;color:#9ca3af;">
            Choose a sacramental record category to view, manage, or add entries.
        </p>
    </div>

    <!-- ── Record type cards ── -->
    <div class="record-type-grid" style="margin-bottom:32px;">

        <!-- BAPTISM -->
        <a href="/church/modules/church_records/baptism/series_list.php"
           class="record-type-card"
           style="--rtc-color:#3b82f6; --rtc-bg:#eff6ff;">
            <div class="rtc-icon-wrap"><i class="fas fa-droplet"></i></div>
            <div class="rtc-title">Baptism</div>
            <div class="rtc-desc">
                Records of baptismal sacraments administered at the parish.
                Organized by year series with scanned document support.
            </div>
            <div class="rtc-stats">
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= number_format($count_baptism) ?></div>
                    <div class="rtc-stat-label">Records</div>
                </div>
                <div class="rtc-stat-divider"></div>
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= $series_baptism ?></div>
                    <div class="rtc-stat-label">Series</div>
                </div>
                <div class="rtc-arrow"><i class="fas fa-arrow-right"></i></div>
            </div>
            <div style="font-size:0.68rem;color:#c4b89a;margin-top:10px;">
                <i class="fas fa-clock" style="margin-right:4px;"></i>
                Last entry: <?= $last_baptism ?>
            </div>
        </a>

        <!-- COMMUNION -->
        <a href="/church/modules/church_records/communion/series_list.php"
           class="record-type-card"
           style="--rtc-color:#10b981; --rtc-bg:#ecfdf5;">
            <div class="rtc-icon-wrap"><i class="fas fa-bread-slice"></i></div>
            <div class="rtc-title">Communion</div>
            <div class="rtc-desc">
                Records of First Holy Communion sacraments, including the communicant's
                details, minister, and sponsoring information.
            </div>
            <div class="rtc-stats">
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= number_format($count_communion) ?></div>
                    <div class="rtc-stat-label">Records</div>
                </div>
                <div class="rtc-stat-divider"></div>
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= $series_communion ?></div>
                    <div class="rtc-stat-label">Series</div>
                </div>
                <div class="rtc-arrow"><i class="fas fa-arrow-right"></i></div>
            </div>
            <div style="font-size:0.68rem;color:#c4b89a;margin-top:10px;">
                <i class="fas fa-clock" style="margin-right:4px;"></i>
                Last entry: <?= $last_communion ?>
            </div>
        </a>

        <!-- CONFIRMATION -->
        <a href="/church/modules/church_records/confirmation/series_list.php"
           class="record-type-card"
           style="--rtc-color:#8b5cf6; --rtc-bg:#f5f3ff;">
            <div class="rtc-icon-wrap"><i class="fas fa-hands-praying"></i></div>
            <div class="rtc-title">Confirmation</div>
            <div class="rtc-desc">
                Records of the Sacrament of Confirmation, including confirmand details
                and sponsoring clergy information.
            </div>
            <div class="rtc-stats">
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= number_format($count_confirmation) ?></div>
                    <div class="rtc-stat-label">Records</div>
                </div>
                <div class="rtc-stat-divider"></div>
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= $series_confirmation ?></div>
                    <div class="rtc-stat-label">Series</div>
                </div>
                <div class="rtc-arrow"><i class="fas fa-arrow-right"></i></div>
            </div>
            <div style="font-size:0.68rem;color:#c4b89a;margin-top:10px;">
                <i class="fas fa-clock" style="margin-right:4px;"></i>
                Last entry: <?= $last_confirmation ?>
            </div>
        </a>

        <!-- WEDDING -->
        <a href="/church/modules/church_records/wedding/series_list.php"
           class="record-type-card"
           style="--rtc-color:#b8933a; --rtc-bg:#fdf8ec;">
            <div class="rtc-icon-wrap"><i class="fas fa-ring"></i></div>
            <div class="rtc-title">Wedding</div>
            <div class="rtc-desc">
                Marriage records including bride and groom information, witnesses,
                and officiating priest details.
            </div>
            <div class="rtc-stats">
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= number_format($count_wedding) ?></div>
                    <div class="rtc-stat-label">Records</div>
                </div>
                <div class="rtc-stat-divider"></div>
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= $series_wedding ?></div>
                    <div class="rtc-stat-label">Series</div>
                </div>
                <div class="rtc-arrow"><i class="fas fa-arrow-right"></i></div>
            </div>
            <div style="font-size:0.68rem;color:#c4b89a;margin-top:10px;">
                <i class="fas fa-clock" style="margin-right:4px;"></i>
                Last entry: <?= $last_wedding ?>
            </div>
        </a>

        <!-- FUNERAL -->
        <a href="/church/modules/church_records/funeral/series_list.php"
           class="record-type-card"
           style="--rtc-color:#6b7280; --rtc-bg:#f9fafb;">
            <div class="rtc-icon-wrap"><i class="fas fa-cross"></i></div>
            <div class="rtc-title">Funeral</div>
            <div class="rtc-desc">
                Records of funeral Masses and burial blessings, including the
                deceased's details, interment location, and next of kin.
            </div>
            <div class="rtc-stats">
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= number_format($count_funeral) ?></div>
                    <div class="rtc-stat-label">Records</div>
                </div>
                <div class="rtc-stat-divider"></div>
                <div class="rtc-stat">
                    <div class="rtc-stat-value"><?= $series_funeral ?></div>
                    <div class="rtc-stat-label">Series</div>
                </div>
                <div class="rtc-arrow"><i class="fas fa-arrow-right"></i></div>
            </div>
            <div style="font-size:0.68rem;color:#c4b89a;margin-top:10px;">
                <i class="fas fa-clock" style="margin-right:4px;"></i>
                Last entry: <?= $last_funeral ?>
            </div>
        </a>

        <!-- QUICK GUIDE -->
        <div style="background:#fff;border:1px solid #d1cdc4;border-radius:16px;overflow:hidden;
                    display:flex;flex-direction:column;box-shadow:0 1px 6px rgba(15,32,68,0.06);">
            <div style="background:linear-gradient(135deg,#0f2044,#162d5c);padding:20px 22px 16px;">
                <div style="width:40px;height:40px;border-radius:10px;background:rgba(184,147,58,0.15);
                            display:flex;align-items:center;justify-content:center;
                            color:#e0c060;font-size:1rem;margin-bottom:12px;">
                    <i class="fas fa-circle-info"></i>
                </div>
                <p style="font-family:'Playfair Display',serif;font-size:1rem;color:#fff;
                           font-weight:600;margin-bottom:3px;">Quick Guide</p>
                <p style="font-size:0.72rem;color:rgba(255,255,255,0.4);">How records are organized</p>
            </div>
            <div style="padding:18px;flex:1;display:flex;flex-direction:column;">
                <?php
                $steps = [
                    ['Choose a Record Type',           'Select Baptism, Communion, Confirmation, Wedding, or Funeral.'],
                    ['Select or Create a Year Series', 'Records are grouped by year. Create a new series if none exists.'],
                    ['Add or View Records',            'Add entries, upload scanned documents, view and print certificates.'],
                    ['Search Across All Types',        'Find any record by name, record number, or year.'],
                ];
                foreach ($steps as $i => $step): ?>
                <div style="display:flex;gap:12px;margin-bottom:14px;">
                    <div style="width:24px;height:24px;border-radius:50%;background:#fdf8ec;color:#b8933a;
                                display:flex;align-items:center;justify-content:center;
                                font-size:0.68rem;font-weight:700;flex-shrink:0;margin-top:1px;">
                        <?= $i + 1 ?>
                    </div>
                    <div>
                        <p style="font-size:0.8rem;font-weight:600;color:#0f2044;margin-bottom:2px;"><?= $step[0] ?></p>
                        <p style="font-size:0.73rem;color:#9ca3af;line-height:1.45;"><?= $step[1] ?></p>
                    </div>
                </div>
                <?php endforeach; ?>
                <hr style="border:none;border-top:1px solid #f3ede3;margin:0 0 14px;">
                <a href="/church/modules/church_records/search.php"
                   style="display:flex;align-items:center;gap:10px;padding:10px 14px;
                          background:#faf7f0;border:1px solid #d1cdc4;border-radius:8px;
                          text-decoration:none;margin-top:auto;transition:border-color 0.18s;"
                   onmouseover="this.style.borderColor='#b8933a'"
                   onmouseout="this.style.borderColor='#d1cdc4'">
                    <i class="fas fa-magnifying-glass" style="color:#b8933a;font-size:0.82rem;"></i>
                    <div>
                        <p style="font-size:0.78rem;font-weight:600;color:#0f2044;margin-bottom:1px;">Search All Records</p>
                        <p style="font-size:0.68rem;color:#9ca3af;">By name, number, or year</p>
                    </div>
                    <i class="fas fa-arrow-right" style="color:#c4b89a;font-size:0.68rem;margin-left:auto;"></i>
                </a>
            </div>
        </div>

    </div>
    <!-- /record-type-grid -->

    <!-- ── Recently Added Records ── -->
    <div class="recent-card">
        <div class="recent-card-header">
            <div class="recent-card-header-title">
                <i class="fas fa-clock-rotate-left" style="color:#b8933a;font-size:0.82rem;"></i>
                Recently Added Records
            </div>
            <a href="/church/modules/church_records/search.php"
               style="font-size:0.75rem;color:#b8933a;text-decoration:none;display:flex;align-items:center;gap:5px;">
                Search all <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
            </a>
        </div>

        <?php if (empty($recent_records)): ?>
        <div style="text-align:center;padding:48px 24px;">
            <i class="fas fa-book-open" style="font-size:2.2rem;color:#e8e0d0;display:block;margin-bottom:14px;"></i>
            <p style="font-size:0.88rem;color:#9ca3af;margin-bottom:4px;">No records yet.</p>
            <p style="font-size:0.78rem;color:#c4b89a;">Start by selecting a record type above and adding your first entry.</p>
        </div>
        <?php else: ?>
        <div style="width:100%;overflow-x:auto;">
            <table class="table">
                <colgroup>
                    <col style="min-width:200px;">
                    <col style="width:140px;">
                    <col style="width:120px;">
                    <col style="width:130px;">
                    <col style="width:80px;">
                </colgroup>
                <thead>
                    <tr>
                        <th>Name / Description</th>
                        <th>Type</th>
                        <th>Year Series</th>
                        <th>Date Added</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                <?php
                $icons = [
                    'baptism'      => 'fa-droplet',
                    'communion'    => 'fa-bread-slice',
                    'confirmation' => 'fa-hands-praying',
                    'wedding'      => 'fa-ring',
                    'funeral'      => 'fa-cross',
                ];
                foreach ($recent_records as $rec): ?>
                <tr>
                    <td style="font-weight:500;color:#0f2044;font-size:0.855rem;">
                        <?= htmlspecialchars($rec['primary_name']) ?>
                    </td>
                    <td>
                        <span class="type-badge type-<?= $rec['type_slug'] ?>">
                            <i class="fas <?= $icons[$rec['type_slug']] ?? 'fa-file' ?>"></i>
                            <?= $rec['type'] ?>
                        </span>
                    </td>
                    <td style="font-size:0.8rem;color:#6b7280;"><?= htmlspecialchars($rec['series_year']) ?></td>
                    <td style="font-size:0.78rem;color:#9ca3af;white-space:nowrap;">
                        <?= date('M j, Y', strtotime($rec['created_at'])) ?>
                    </td>
                    <td>
                        <a href="/church/modules/church_records/<?= $rec['type_slug'] ?>/view_record.php?id=<?= $rec['id'] ?>"
                           style="display:inline-flex;align-items:center;gap:4px;font-size:0.75rem;
                                  color:#b8933a;text-decoration:none;font-weight:500;white-space:nowrap;">
                            View <i class="fas fa-arrow-right" style="font-size:0.6rem;"></i>
                        </a>
                    </td>
                </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>

        <!-- Table footer -->
        <div style="padding:12px 20px;background:#fdfcfa;border-top:1px solid #f0ebe0;
                    display:flex;align-items:center;justify-content:space-between;
                    border-radius:0 0 14px 14px;">
            <span style="font-size:0.75rem;color:#9ca3af;">
                Showing the <?= count($recent_records) ?> most recent records across all types
            </span>
            <a href="/church/modules/church_records/search.php"
               style="font-size:0.75rem;color:#b8933a;text-decoration:none;
                      display:inline-flex;align-items:center;gap:5px;font-weight:500;">
                View all <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
            </a>
        </div>
        <?php endif; ?>
    </div>
    <!-- /recent-card -->

</div>
<!-- /main content -->

<?php include $root . '/includes/footer.php'; ?>