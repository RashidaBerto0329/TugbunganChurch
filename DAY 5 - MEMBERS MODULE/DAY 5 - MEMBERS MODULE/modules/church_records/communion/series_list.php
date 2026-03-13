<?php
// church/modules/church_records/communion/series_list.php
// Communion Module: Year series list
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$page_title = 'Communion Records';

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$series_sql = "
    SELECT
        cs.series_year,
        cs.notes,
        COUNT(cr.id)                        AS record_count,
        MIN(cr.date_of_communion)           AS first_date,
        MAX(cr.date_of_communion)           AS last_date,
        MAX(cr.created_at)                  AS last_updated,
        COUNT(CASE WHEN cr.document_path IS NOT NULL
                    AND cr.document_path != '' THEN 1 END) AS scanned_count
    FROM communion_series cs
    LEFT JOIN communion_records cr
        ON cr.series_year = cs.series_year AND cr.is_archived = 0
    GROUP BY cs.series_year, cs.notes
    ORDER BY cs.series_year DESC
";
$series_list = [];
$sr = $conn->query($series_sql);
if ($sr) {
    while ($row = $sr->fetch_assoc()) $series_list[] = $row;
}

$total_records = array_sum(array_column($series_list, 'record_count'));
$total_series  = count($series_list);
$total_scanned = array_sum(array_column($series_list, 'scanned_count'));

$current_year        = (int)date('Y');
$years_existing      = array_column($series_list, 'series_year');
$current_year_exists = in_array((string)$current_year, $years_existing);

include $root . '/includes/header.php';
?>

<style>
    .series-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(280px, 1fr));
        gap: 18px;
    }
    .series-grid-item { display: flex; flex-direction: column; gap: 6px; }
    .series-card {
        background: #fff; border: 1px solid #ede8de; border-radius: 14px;
        overflow: hidden; text-decoration: none; display: flex; flex-direction: column;
        transition: transform 0.2s ease, box-shadow 0.2s ease, border-color 0.2s ease;
    }
    .series-card:hover {
        transform: translateY(-3px); box-shadow: 0 10px 32px rgba(0,0,0,0.09);
        border-color: #059669;
    }
    .series-card-head {
        background: linear-gradient(135deg, #065f46 0%, #059669 100%);
        padding: 20px 22px 16px; position: relative; overflow: hidden;
    }
    .series-card-head::after {
        content: attr(data-year); position: absolute; right: -8px; bottom: -18px;
        font-family: 'Playfair Display', serif; font-size: 4.5rem; font-weight: 700;
        color: rgba(255,255,255,0.07); pointer-events: none; line-height: 1; letter-spacing: -2px;
    }
    .series-year-label { font-family: 'Playfair Display', serif; font-size: 1.5rem; font-weight: 700; color: #fff; line-height: 1; margin-bottom: 4px; }
    .series-sub-label { font-size: 0.72rem; color: rgba(255,255,255,0.5); letter-spacing: 0.08em; text-transform: uppercase; }
    .current-year-tag { display: inline-block; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.1em; text-transform: uppercase; background: rgba(255,220,240,0.2); color: #ffd6ea; padding: 2px 8px; border-radius: 99px; margin-top: 6px; }
    .series-card-body { padding: 16px 20px 14px; flex: 1; display: flex; flex-direction: column; gap: 12px; }
    .series-stats-row { display: flex; gap: 0; }
    .series-stat { flex: 1; display: flex; flex-direction: column; gap: 2px; padding-right: 14px; border-right: 1px solid #f3ede3; }
    .series-stat:last-child { border-right: none; padding-right: 0; padding-left: 14px; }
    .series-stat-value { font-family: 'Playfair Display', serif; font-size: 1.35rem; font-weight: 600; color: #0f2044; line-height: 1; }
    .series-stat-label { font-size: 0.68rem; color: #c4b89a; text-transform: uppercase; letter-spacing: 0.04em; font-weight: 500; }
    .scan-progress-wrap { display: flex; flex-direction: column; gap: 5px; }
    .scan-progress-label { display: flex; justify-content: space-between; font-size: 0.72rem; color: #9ca3af; }
    .scan-progress-bar { height: 5px; background: #f3ede3; border-radius: 99px; overflow: hidden; }
    .scan-progress-fill { height: 100%; background: linear-gradient(to right, #059669, #34d399); border-radius: 99px; transition: width 0.4s ease; }
    .series-card-footer { padding: 12px 20px; border-top: 1px solid #f3ede3; display: flex; align-items: center; justify-content: space-between; gap: 10px; }
    .series-footer-date { font-size: 0.72rem; color: #c4b89a; display: flex; align-items: center; gap: 5px; }
    .series-open-btn { display: inline-flex; align-items: center; gap: 5px; font-size: 0.78rem; font-weight: 600; color: #059669; background: #f0fdf4; padding: 5px 12px; border-radius: 6px; text-decoration: none; transition: background 0.18s, color 0.18s; }
    .series-card:hover .series-open-btn { background: #059669; color: #fff; }
    .series-card-actions { display: flex; justify-content: flex-end; gap: 10px; font-size: 0.75rem; }
    .series-card-actions button { border: none; background: none; padding: 0; color: #059669; cursor: pointer; }
    .series-card-actions button:hover { text-decoration: underline; }
    .series-card-new { background: #faf7f0; border: 2px dashed #d4c9b5; border-radius: 14px; display: flex; flex-direction: column; align-items: center; justify-content: center; gap: 10px; padding: 36px 24px; text-decoration: none; transition: all 0.2s ease; cursor: pointer; min-height: 200px; }
    .series-card-new:hover { border-color: #059669; background: #f0fdf4; }
    .series-card-new-icon { width: 52px; height: 52px; border-radius: 14px; background: #ede8de; display: flex; align-items: center; justify-content: center; color: #9ca3af; font-size: 1.3rem; transition: background 0.2s, color 0.2s; }
    .series-card-new:hover .series-card-new-icon { background: #d1fae5; color: #059669; }
    .series-card-new-title { font-family: 'Playfair Display', serif; font-size: 0.95rem; font-weight: 600; color: #9ca3af; transition: color 0.2s; }
    .series-card-new:hover .series-card-new-title { color: #059669; }
    .series-card-new p { font-size: 0.75rem; color: #c4b89a; text-align: center; line-height: 1.45; transition: color 0.2s; }
    .series-card-new:hover p { color: #6b7280; }

    .communion-banner {
        background: linear-gradient(135deg, #065f46 0%, #047857 60%, #059669 100%);
        border-radius: 14px; padding: 22px 28px; display: flex; align-items: center;
        justify-content: space-between; gap: 20px; flex-wrap: wrap; margin-bottom: 28px;
        position: relative; overflow: hidden;
    }
    .communion-banner::after { content: ''; position: absolute; right: -20px; top: -20px; width: 160px; height: 160px; border-radius: 50%; background: rgba(255,255,255,0.04); pointer-events: none; }
    .banner-stats { display: flex; gap: 28px; align-items: center; flex-wrap: nowrap; }
    .banner-stat-item { text-align: center; }
    .banner-stat-value { font-family: 'Playfair Display', serif; font-size: 1.6rem; color: #d1fae5; font-weight: 600; line-height: 1; }
    .banner-stat-label { font-size: 0.7rem; color: rgba(255,255,255,0.4); text-transform: uppercase; letter-spacing: 0.06em; margin-top: 3px; }
    .banner-stat-divider { width: 1px; height: 40px; background: rgba(255,255,255,0.1); flex-shrink: 0; }

    .modal-overlay { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.45); z-index: 100; align-items: center; justify-content: center; }
    .modal-overlay.show { display: flex; }
    .modal-box { background: #fff; border-radius: 16px; width: 100%; max-width: 440px; margin: 20px; overflow: hidden; box-shadow: 0 24px 60px rgba(0,0,0,0.2); animation: modalIn 0.2s ease; }
    @keyframes modalIn { from { transform: translateY(20px); opacity: 0; } to { transform: translateY(0); opacity: 1; } }
    .modal-header { background: linear-gradient(135deg, #065f46, #059669); padding: 18px 24px; display: flex; align-items: center; justify-content: space-between; }
    .modal-header-title { font-family: 'Playfair Display', serif; font-size: 1rem; color: #fff; font-weight: 600; }
    .modal-close { background: rgba(255,255,255,0.12); border: none; color: rgba(255,255,255,0.7); width: 28px; height: 28px; border-radius: 6px; cursor: pointer; display: flex; align-items: center; justify-content: center; font-size: 0.85rem; transition: background 0.15s, color 0.15s; }
    .modal-close:hover { background: rgba(255,255,255,0.2); color: #fff; }
    .modal-body { padding: 24px; }
    .form-group { margin-bottom: 18px; }
    .form-label { display: block; font-size: 0.8rem; font-weight: 600; color: #374151; margin-bottom: 6px; }
    .form-label span { color: #ef4444; margin-left: 2px; }
    .form-input { width: 100%; padding: 9px 12px; border: 1px solid #ede8de; border-radius: 8px; font-size: 0.875rem; color: #1a1a2e; outline: none; transition: border-color 0.18s, box-shadow 0.18s; background: #fff; }
    .form-input:focus { border-color: #059669; box-shadow: 0 0 0 3px rgba(5,150,105,0.1); }
    .form-hint { font-size: 0.72rem; color: #9ca3af; margin-top: 5px; }
    .modal-footer { padding: 16px 24px; border-top: 1px solid #f3ede3; display: flex; justify-content: flex-end; gap: 10px; }
    .btn-cancel { padding: 8px 18px; border-radius: 8px; font-size: 0.83rem; font-weight: 500; border: 1px solid #ede8de; background: #fff; color: #6b7280; cursor: pointer; transition: all 0.15s; }
    .btn-cancel:hover { background: #f9fafb; border-color: #c4b89a; }
    .btn-submit { padding: 8px 20px; border-radius: 8px; font-size: 0.83rem; font-weight: 600; background: #059669; color: #fff; border: none; cursor: pointer; display: flex; align-items: center; gap: 7px; transition: background 0.15s; }
    .btn-submit:hover { background: #047857; }
    .alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; font-size: 0.83rem; margin-bottom: 20px; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #065f46; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

    @media (max-width: 640px) {
        .series-grid { grid-template-columns: 1fr; }
        .communion-banner { flex-direction: column; align-items: flex-start; gap: 0; padding: 20px; }
        .banner-stats { width: 100%; gap: 0; margin-top: 16px; padding-top: 14px; border-top: 1px solid rgba(255,255,255,0.12); justify-content: space-around; }
        .banner-stat-divider { display: none; }
    }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-bread-slice" style="color:#059669;margin-right:8px;font-size:1rem;"></i>
            Communion Records
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Communion</span>
        </p>
    </div>
    <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
    <button onclick="openModal()"
            style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;
                   border-radius:8px;font-size:0.83rem;font-weight:600;border:none;
                   background:#059669;color:#fff;cursor:pointer;transition:background 0.15s;">
        <i class="fas fa-plus"></i> New Year Series
    </button>
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

    <!-- Summary banner -->
    <div class="communion-banner">
        <div style="display:flex;align-items:center;gap:18px;">
            <div style="width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,0.12);
                        display:flex;align-items:center;justify-content:center;
                        color:#fff;font-size:1.4rem;flex-shrink:0;">
                <i class="fas fa-bread-slice"></i>
            </div>
            <div>
                <p style="font-family:'Playfair Display',serif;font-size:1.1rem;color:#fff;font-weight:600;margin-bottom:3px;">
                    Communion Records
                </p>
                <p style="font-size:0.78rem;color:rgba(255,255,255,0.45);">
                    Organized by year series · Upload scans · Print certificates
                </p>
            </div>
        </div>
        <div class="banner-stats">
            <div class="banner-stat-item">
                <div class="banner-stat-value"><?= number_format($total_records) ?></div>
                <div class="banner-stat-label">Total Records</div>
            </div>
            <div class="banner-stat-divider"></div>
            <div class="banner-stat-item">
                <div class="banner-stat-value"><?= $total_series ?></div>
                <div class="banner-stat-label">Year Series</div>
            </div>
            <div class="banner-stat-divider"></div>
            <div class="banner-stat-item">
                <div class="banner-stat-value"><?= $total_scanned ?></div>
                <div class="banner-stat-label">Scanned Docs</div>
            </div>
        </div>
    </div>

    <p style="font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;
              color:#0f2044;margin-bottom:6px;display:flex;align-items:center;gap:8px;">
        <i class="fas fa-layer-group" style="color:#059669;font-size:0.8rem;"></i>
        Year Series
    </p>
    <p style="font-size:0.78rem;color:#9ca3af;margin-bottom:18px;">
        Each year contains its own set of communion records. Click a series to view or add entries.
    </p>

    <?php if (empty($series_list)): ?>
    <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;text-align:center;padding:60px 24px;">
        <div style="width:72px;height:72px;border-radius:20px;background:#f0fdf4;display:flex;align-items:center;justify-content:center;margin:0 auto 20px;color:#059669;font-size:2rem;">
            <i class="fas fa-bread-slice"></i>
        </div>
        <h3 style="font-family:'Playfair Display',serif;font-size:1.1rem;color:#0f2044;margin-bottom:8px;">
            No Communion Records Yet
        </h3>
        <p style="font-size:0.85rem;color:#9ca3af;margin-bottom:24px;max-width:380px;margin-left:auto;margin-right:auto;line-height:1.6;">
            Create your first year series to start adding communion records.
        </p>
        <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
        <button onclick="openModal()"
                style="display:inline-flex;align-items:center;gap:8px;padding:10px 22px;
                       background:#059669;color:#fff;border:none;border-radius:8px;
                       font-size:0.85rem;font-weight:600;cursor:pointer;">
            <i class="fas fa-plus"></i> Create <?= $current_year ?> Series
        </button>
        <?php endif; ?>
    </div>

    <?php else: ?>
    <div class="series-grid">
        <?php foreach ($series_list as $series):
            $year       = (int)$series['series_year'];
            $count      = (int)$series['record_count'];
            $scanned    = (int)$series['scanned_count'];
            $scan_pct   = $count > 0 ? round(($scanned / $count) * 100) : 0;
            $is_current = ($year === $current_year);
            $last_upd   = $series['last_updated']
                            ? date('M j, Y', strtotime($series['last_updated'])) : '—';
        ?>
        <div class="series-grid-item">
            <a href="/church/modules/church_records/communion/series_records.php?year=<?= $year ?>"
               class="series-card">
                <div class="series-card-head" data-year="<?= $year ?>">
                    <div class="series-year-label"><?= $year ?></div>
                    <div class="series-sub-label">Communion Series</div>
                    <?php if ($is_current): ?>
                    <div class="current-year-tag">Current Year</div>
                    <?php endif; ?>
                </div>
                <div class="series-card-body">
                    <div class="series-stats-row">
                        <div class="series-stat">
                            <div class="series-stat-value"><?= number_format($count) ?></div>
                            <div class="series-stat-label">Records</div>
                        </div>
                        <div class="series-stat">
                            <div class="series-stat-value"><?= $scanned ?></div>
                            <div class="series-stat-label">Scanned</div>
                        </div>
                    </div>
                    <div class="scan-progress-wrap">
                        <div class="scan-progress-label">
                            <span>Document scans</span>
                            <span><?= $scan_pct ?>%</span>
                        </div>
                        <div class="scan-progress-bar">
                            <div class="scan-progress-fill" style="width:<?= $scan_pct ?>%;"></div>
                        </div>
                    </div>
                </div>
                <div class="series-card-footer">
                    <div class="series-footer-date">
                        <i class="fas fa-clock"></i> Updated <?= $last_upd ?>
                    </div>
                    <span class="series-open-btn">
                        Open <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                    </span>
                </div>
            </a>
            <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
            <div class="series-card-actions">
                <button type="button"
                        onclick="openEditSeriesModal(<?= $year ?>, '<?= htmlspecialchars($series['notes'] ?? '', ENT_QUOTES) ?>')">
                    <i class="fas fa-pen-to-square"></i> Edit series
                </button>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>

        <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
        <div class="series-card-new" onclick="openModal()">
            <div class="series-card-new-icon"><i class="fas fa-plus"></i></div>
            <div class="series-card-new-title">New Year Series</div>
            <p>
                <?php if (!$current_year_exists): ?>
                    Create the <strong><?= $current_year ?></strong> series to start<br>adding this year's records.
                <?php else: ?>
                    Add a series for a different<br>year or historical import.
                <?php endif; ?>
            </p>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

</div>

<?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
<!-- Modal — Add Series -->
<div class="modal-overlay" id="addSeriesModal">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-header-title">
                <i class="fas fa-plus" style="margin-right:8px;font-size:0.85rem;"></i>
                Create New Year Series
            </span>
            <button class="modal-close" onclick="closeModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="/church/modules/church_records/communion/add_series.php">
            <div class="modal-body">
                <div class="form-group">
                    <label class="form-label">Series Year <span>*</span></label>
                    <input type="number" name="series_year" class="form-input"
                           min="1900" max="<?= $current_year + 1 ?>"
                           value="<?= $current_year_exists ? '' : $current_year ?>"
                           placeholder="e.g. <?= $current_year ?>" required>
                    <p class="form-hint">Enter the year this series covers. Each year should have one series.</p>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Notes <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <textarea name="notes" class="form-input" rows="3"
                              placeholder="e.g. Includes children from the Katalista preparation class…"
                              style="resize:vertical;"></textarea>
                    <p class="form-hint">Any additional notes about this year's series.</p>
                </div>
                <?php if ($current_year_exists): ?>
                <div style="background:#fef3c7;border:1px solid #fde68a;border-radius:8px;padding:10px 14px;margin-top:14px;display:flex;align-items:center;gap:9px;">
                    <i class="fas fa-triangle-exclamation" style="color:#d97706;font-size:0.85rem;"></i>
                    <p style="font-size:0.78rem;color:#92400e;">
                        A <strong><?= $current_year ?></strong> series already exists.
                        You can create a series for a different year.
                    </p>
                </div>
                <?php endif; ?>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn-cancel" onclick="closeModal()">Cancel</button>
                <button type="submit" class="btn-submit">
                    <i class="fas fa-plus"></i> Create Series
                </button>
            </div>
        </form>
    </div>
</div>

<!-- Modal — Edit Series -->
<div class="modal-overlay" id="editSeriesModal">
    <div class="modal-box">
        <div class="modal-header">
            <span class="modal-header-title">
                <i class="fas fa-pen-to-square" style="margin-right:8px;font-size:0.85rem;"></i>
                Edit Year Series
            </span>
            <button class="modal-close" onclick="closeEditSeriesModal()"><i class="fas fa-times"></i></button>
        </div>
        <form method="POST" action="/church/modules/church_records/communion/edit_series.php">
            <div class="modal-body">
                <input type="hidden" name="series_year" id="edit_series_year">
                <div class="form-group">
                    <label class="form-label">Series Year</label>
                    <input type="text" id="edit_series_year_display" class="form-input" readonly>
                    <p class="form-hint">Year cannot be changed here.</p>
                </div>
                <div class="form-group" style="margin-bottom:0;">
                    <label class="form-label">Notes <span style="color:#9ca3af;font-weight:400;">(optional)</span></label>
                    <textarea name="notes" id="edit_series_notes" class="form-input" rows="3" style="resize:vertical;"></textarea>
                </div>
            </div>
            <div class="modal-footer" style="justify-content:space-between;align-items:center;">
                <button type="submit" name="action" value="delete"
                        style="padding:7px 14px;border-radius:8px;border:1px solid #fecaca;background:#fef2f2;color:#b91c1c;font-size:0.78rem;font-weight:600;cursor:pointer;"
                        onclick="return confirm('Are you sure you want to remove this series? It must have no communion records.');">
                    <i class="fas fa-trash-alt" style="margin-right:6px;"></i> Remove Series
                </button>
                <div>
                    <button type="button" class="btn-cancel" onclick="closeEditSeriesModal()">Cancel</button>
                    <button type="submit" name="action" value="update" class="btn-submit">
                        <i class="fas fa-floppy-disk"></i> Save Changes
                    </button>
                </div>
            </div>
        </form>
    </div>
</div>
<?php endif; ?>

<script>
    function openModal() { document.getElementById('addSeriesModal').classList.add('show'); document.body.style.overflow = 'hidden'; }
    function closeModal() { document.getElementById('addSeriesModal').classList.remove('show'); document.body.style.overflow = ''; }
    function openEditSeriesModal(year, notes) {
        document.getElementById('edit_series_year').value = year;
        document.getElementById('edit_series_year_display').value = year;
        document.getElementById('edit_series_notes').value = notes || '';
        document.getElementById('editSeriesModal').classList.add('show');
        document.body.style.overflow = 'hidden';
    }
    function closeEditSeriesModal() { document.getElementById('editSeriesModal').classList.remove('show'); document.body.style.overflow = ''; }
    document.getElementById('addSeriesModal')?.addEventListener('click', function(e) { if (e.target === this) closeModal(); });
    document.getElementById('editSeriesModal')?.addEventListener('click', function(e) { if (e.target === this) closeEditSeriesModal(); });
    document.addEventListener('keydown', function(e) { if (e.key === 'Escape') { closeModal(); closeEditSeriesModal(); } });
</script>

<?php include $root . '/includes/footer.php'; ?>