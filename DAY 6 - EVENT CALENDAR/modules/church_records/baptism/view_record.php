<?php
// church/modules/church_records/baptism/view_record.php
// Phase 4 — Step 4.6: View a single baptism record
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Get & validate record ID ─────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

// ── Fetch record ─────────────────────────────────────────────
$stmt = $conn->prepare("
    SELECT
        br.*,
        u.name AS created_by_name
    FROM baptism_records br
    LEFT JOIN users u ON u.id = br.created_by
    WHERE br.id = ? 
");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    $_SESSION['error'] = "Record not found.";
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

$year = (int)$rec['series_year'];

// ── Fetch booking if linked ──────────────────────────────────
$booking = null;
if (!empty($rec['booking_id'])) {
    $bq = $conn->prepare("SELECT id, requestor_name, contact_number, status FROM bookings WHERE id = ?");
    $bq->bind_param("i", $rec['booking_id']);
    $bq->execute();
    $booking = $bq->get_result()->fetch_assoc();
    $bq->close();
}

// ── Flash messages ───────────────────────────────────────────
$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

// ── Prev / Next navigation within the same series ───────────
$prev_rec = $conn->query("
    SELECT id, child_name FROM baptism_records
    WHERE series_year = {$year} AND is_archived = 0 AND id < {$id}
    ORDER BY id DESC LIMIT 1
")->fetch_assoc();

$next_rec = $conn->query("
    SELECT id, child_name FROM baptism_records
    WHERE series_year = {$year} AND is_archived = 0 AND id > {$id}
    ORDER BY id ASC LIMIT 1
")->fetch_assoc();

$page_title = htmlspecialchars($rec['child_name']) . ' — Baptism Record';
include $root . '/includes/header.php';
?>

<!-- ── Page-specific styles ─────────────────────────────── -->
<style>
    /* ── Detail card ── */
    .detail-card {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 18px;
    }
    .detail-card-header {
        padding: 14px 22px;
        border-bottom: 1px solid #f3ede3;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .detail-card-header-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem;
        flex-shrink: 0;
    }
    .detail-card-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.88rem;
        font-weight: 600;
        color: #0f2044;
    }
    .detail-card-body {
        padding: 20px 22px;
    }

    /* ── Detail grid ── */
    .detail-grid {
        display: grid;
        grid-template-columns: repeat(3, 1fr);
        gap: 18px;
    }
    .detail-grid-2 {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 18px;
    }

    .detail-field { display: flex; flex-direction: column; gap: 3px; }
    .detail-label {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #c4b89a;
    }
    .detail-value {
        font-size: 0.88rem;
        color: #1a1a2e;
        font-weight: 500;
        line-height: 1.4;
    }
    .detail-value.empty { color: #d1c9b8; font-style: italic; font-weight: 400; }

    /* ── Record hero ── */
    .record-hero {
        background: linear-gradient(135deg, #1e3a8a 0%, #1d4ed8 60%, #2563eb 100%);
        border-radius: 14px;
        padding: 24px 28px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 20px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        position: relative;
        overflow: hidden;
    }
    .record-hero::after {
        content: attr(data-recno);
        position: absolute;
        right: 24px; top: 50%;
        transform: translateY(-50%);
        font-family: 'Playfair Display', serif;
        font-size: 4.5rem;
        font-weight: 700;
        color: rgba(255,255,255,0.05);
        pointer-events: none;
        letter-spacing: -2px;
        white-space: nowrap;
    }
    .hero-icon-wrap {
        width: 56px; height: 56px;
        border-radius: 16px;
        background: rgba(255,255,255,0.12);
        display: flex; align-items: center; justify-content: center;
        color: #fff; font-size: 1.5rem; flex-shrink: 0;
    }
    .hero-name {
        font-family: 'Playfair Display', serif;
        font-size: 1.3rem; font-weight: 700; color: #fff;
        margin-bottom: 4px; line-height: 1.2;
    }
    .hero-meta {
        font-size: 0.78rem; color: rgba(255,255,255,0.5);
        display: flex; align-items: center; gap: 10px; flex-wrap: wrap;
    }
    .hero-meta-sep { color: rgba(255,255,255,0.2); }
    .hero-record-no {
        display: inline-flex; align-items: center; gap: 5px;
        background: rgba(255,255,255,0.12);
        color: #93c5fd;
        font-size: 0.75rem; font-weight: 600;
        padding: 3px 10px; border-radius: 6px;
        font-family: monospace;
        flex-shrink: 0;
    }

    /* ── Action buttons ── */
    .action-bar {
        display: flex;
        align-items: center;
        gap: 8px;
        flex-wrap: wrap;
    }
    .act-btn {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 8px 16px; border-radius: 8px;
        font-size: 0.82rem; font-weight: 600;
        text-decoration: none; border: none; cursor: pointer;
        transition: all 0.18s;
    }
    .act-btn-blue  { background: #2563eb; color: #fff; }
    .act-btn-blue:hover { background: #1d4ed8; }
    .act-btn-gold  { background: #fdf8ec; color: #b8933a; border: 1px solid #e8d99a; }
    .act-btn-gold:hover { background: #b8933a; color: #fff; border-color: #b8933a; }
    .act-btn-outline { background: #fff; color: #6b7280; border: 1px solid #ede8de; }
    .act-btn-outline:hover { background: #faf7f0; border-color: #c4b89a; color: #0f2044; }
    .act-btn-red { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .act-btn-red:hover { background: #dc2626; color: #fff; border-color: #dc2626; }

    /* ── Document preview ── */
    .doc-preview-wrap {
        border: 1px solid #ede8de;
        border-radius: 10px;
        overflow: hidden;
        background: #f9fafb;
    }
    .doc-preview-toolbar {
        padding: 10px 14px;
        background: #f3f4f6;
        border-bottom: 1px solid #ede8de;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 8px;
    }
    .doc-preview-name {
        font-size: 0.78rem; color: #374151; font-weight: 500;
        display: flex; align-items: center; gap: 6px;
    }
    .doc-preview-img {
        width: 100%; max-height: 420px;
        object-fit: contain;
        display: block;
        background: #fff;
    }
    .doc-no-file {
        text-align: center; padding: 40px 20px;
        color: #c4b89a;
    }
    .doc-no-file i { font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 10px; }

    /* ── Nav prev/next ── */
    .record-nav {
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .record-nav-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 7px 14px; border-radius: 8px; font-size: 0.78rem;
        font-weight: 500; text-decoration: none;
        border: 1px solid #ede8de; background: #fff; color: #6b7280;
        transition: all 0.15s; max-width: 220px;
    }
    .record-nav-btn:hover { border-color: #3b82f6; color: #3b82f6; background: #eff6ff; }
    .record-nav-btn.disabled { opacity: 0.3; pointer-events: none; }
    .record-nav-btn span {
        white-space: nowrap; overflow: hidden;
        text-overflow: ellipsis; flex: 1;
    }

    /* ── Alert ── */
    .alert {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px; border-radius: 10px;
        font-size: 0.83rem; margin-bottom: 18px;
    }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }

    /* ── Sidebar info rows ── */
    .info-row {
        display: flex; justify-content: space-between;
        align-items: flex-start; gap: 8px;
        padding: 9px 0;
        border-bottom: 1px solid #f5f0e8;
        font-size: 0.8rem;
    }
    .info-row:last-child { border-bottom: none; padding-bottom: 0; }
    .info-row-label { color: #9ca3af; flex-shrink: 0; }
    .info-row-value { color: #0f2044; font-weight: 500; text-align: right; }

    @media (max-width: 900px) {
        #viewLayout { grid-template-columns: 1fr !important; }
        .detail-grid { grid-template-columns: repeat(2, 1fr); }
        .detail-grid-2 { grid-template-columns: 1fr; }
    }
    @media (max-width: 560px) {
        .detail-grid { grid-template-columns: 1fr; }
        .hero-name { font-size: 1.05rem; }
    }
</style>

<!-- ── Page header ───────────────────────────────────────── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-droplet" style="color:#3b82f6;margin-right:8px;font-size:1rem;"></i>
            Baptism Record
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/baptism/series_list.php" style="color:#9ca3af;text-decoration:none;">Baptism</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/baptism/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current"><?= htmlspecialchars($rec['child_name']) ?></span>
        </p>
    </div>

    <!-- Action bar -->
    <div class="action-bar">
        <a href="/church/modules/church_records/baptism/print_certificate.php?id=<?= $id ?>"
           class="act-btn act-btn-outline" target="_blank">
            <i class="fas fa-print"></i> Print Certificate
        </a>
        <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
        <a href="/church/modules/church_records/baptism/edit_record.php?id=<?= $id ?>"
           class="act-btn act-btn-gold">
            <i class="fas fa-pen"></i> Edit
        </a>
        <?php endif; ?>
    </div>
</div>

<!-- ── Main content ──────────────────────────────────────── -->
<div style="padding:24px 24px 60px;">

    <?php if ($success): ?>
    <div class="alert alert-success auto-dismiss">
        <i class="fas fa-circle-check"></i> <?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Prev / Next navigation -->
    <div class="record-nav">
        <a href="<?= $prev_rec ? '/church/modules/church_records/baptism/view_record.php?id=' . $prev_rec['id'] : '#' ?>"
           class="record-nav-btn <?= !$prev_rec ? 'disabled' : '' ?>">
            <i class="fas fa-chevron-left" style="font-size:0.65rem;flex-shrink:0;"></i>
            <span><?= $prev_rec ? htmlspecialchars($prev_rec['child_name']) : 'No previous record' ?></span>
        </a>

        <a href="/church/modules/church_records/baptism/series_records.php?year=<?= $year ?>"
           style="font-size:0.78rem;color:#9ca3af;text-decoration:none;
                  display:flex;align-items:center;gap:5px;">
            <i class="fas fa-layer-group" style="font-size:0.7rem;"></i>
            <?= $year ?> Series
        </a>

        <a href="<?= $next_rec ? '/church/modules/church_records/baptism/view_record.php?id=' . $next_rec['id'] : '#' ?>"
           class="record-nav-btn <?= !$next_rec ? 'disabled' : '' ?>"
           style="justify-content:flex-end;">
            <span><?= $next_rec ? htmlspecialchars($next_rec['child_name']) : 'No next record' ?></span>
            <i class="fas fa-chevron-right" style="font-size:0.65rem;flex-shrink:0;"></i>
        </a>
    </div>

    <!-- Two-column layout -->
    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;"
         id="viewLayout">

        <!-- ── LEFT: Main detail panels ─────────────────── -->
        <div>

            <!-- Record hero -->
            <div class="record-hero" data-recno="<?= htmlspecialchars($rec['record_no']) ?>">
                <div style="display:flex;align-items:center;gap:18px;flex:1;min-width:0;">
                    <div class="hero-icon-wrap">
                        <i class="fas fa-droplet"></i>
                    </div>
                    <div style="min-width:0;">
                        <div class="hero-name"><?= htmlspecialchars($rec['child_name']) ?></div>
                        <div class="hero-meta">
                            <?php if ($rec['date_of_baptism']): ?>
                            <span>
                                <i class="fas fa-calendar" style="font-size:0.65rem;margin-right:3px;"></i>
                                <?= date('F j, Y', strtotime($rec['date_of_baptism'])) ?>
                            </span>
                            <span class="hero-meta-sep">·</span>
                            <?php endif; ?>
                            <span><?= $year ?> Baptism Series</span>
                        </div>
                    </div>
                </div>
                <?php if ($rec['record_no']): ?>
                <div class="hero-record-no">
                    <i class="fas fa-hashtag" style="font-size:0.65rem;"></i>
                    <?= htmlspecialchars($rec['record_no']) ?>
                </div>
                <?php endif; ?>
            </div>

            <!-- ── Section: Record Details ─────────────── -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#eff6ff;color:#3b82f6;">
                        <i class="fas fa-hashtag"></i>
                    </div>
                    <div class="detail-card-title">Record Details</div>
                </div>
                <div class="detail-card-body">
                    <div class="detail-grid">

                        <div class="detail-field">
                            <span class="detail-label">Record Number</span>
                            <span class="detail-value" style="font-family:monospace;color:#3b82f6;">
                                <?= $rec['record_no'] ? htmlspecialchars($rec['record_no']) : '<span class="empty">—</span>' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Series Year</span>
                            <span class="detail-value"><?= $year ?></span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Date of Baptism</span>
                            <span class="detail-value">
                                <?= $rec['date_of_baptism']
                                    ? date('F j, Y', strtotime($rec['date_of_baptism']))
                                    : '<span class="detail-value empty">Not set</span>' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Place of Baptism</span>
                            <span class="detail-value <?= empty($rec['place_of_baptism']) ? 'empty' : '' ?>">
                                <?= $rec['place_of_baptism'] ? htmlspecialchars($rec['place_of_baptism']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Date of Birth</span>
                            <span class="detail-value <?= empty($rec['date_of_birth']) ? 'empty' : '' ?>">
                                <?= $rec['date_of_birth']
                                    ? date('F j, Y', strtotime($rec['date_of_birth']))
                                    : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Place of Birth</span>
                            <span class="detail-value <?= empty($rec['place_of_birth']) ? 'empty' : '' ?>">
                                <?= $rec['place_of_birth'] ? htmlspecialchars($rec['place_of_birth']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Officiating Minister</span>
                            <span class="detail-value <?= empty($rec['minister']) ? 'empty' : '' ?>">
                                <?= $rec['minister'] ? htmlspecialchars($rec['minister']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Baptism Type</span>
                            <span class="detail-value <?= empty($rec['baptism_type']) ? 'empty' : '' ?>">
                                <?php
                                    $btype_map = ['weekday' => 'Tuesday – Saturday', 'sunday_mass' => 'Sunday Mass'];
                                    echo $rec['baptism_type'] ? ($btype_map[$rec['baptism_type']] ?? htmlspecialchars($rec['baptism_type'])) : '—';
                                ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Time of Baptism</span>
                            <span class="detail-value <?= empty($rec['time_of_baptism']) ? 'empty' : '' ?>">
                                <?= $rec['time_of_baptism'] ? date('g:i A', strtotime($rec['time_of_baptism'])) : '—' ?>
                            </span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Section: Child & Parents ───────────── -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#f5f3ff;color:#8b5cf6;">
                        <i class="fas fa-users"></i>
                    </div>
                    <div class="detail-card-title">Child & Parents</div>
                </div>
                <div class="detail-card-body">
                    <div class="detail-grid" style="margin-bottom:18px;">

                        <div class="detail-field" style="grid-column:span 3;">
                            <span class="detail-label">Child's Full Name</span>
                            <span class="detail-value" style="font-size:1rem;font-family:'Playfair Display',serif;">
                                <?= htmlspecialchars($rec['child_name']) ?>
                            </span>
                        </div>

                    </div>

                    <div class="detail-grid-2">

                        <div class="detail-field">
                            <span class="detail-label">Father's Name</span>
                            <span class="detail-value <?= empty($rec['father_name']) ? 'empty' : '' ?>">
                                <?= $rec['father_name'] ? htmlspecialchars($rec['father_name']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Father's Place of Birth</span>
                            <span class="detail-value <?= empty($rec['father_place_of_birth']) ? 'empty' : '' ?>">
                                <?= $rec['father_place_of_birth'] ? htmlspecialchars($rec['father_place_of_birth']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Mother's Name</span>
                            <span class="detail-value <?= empty($rec['mother_name']) ? 'empty' : '' ?>">
                                <?= $rec['mother_name'] ? htmlspecialchars($rec['mother_name']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Mother's Place of Birth</span>
                            <span class="detail-value <?= empty($rec['mother_place_of_birth']) ? 'empty' : '' ?>">
                                <?= $rec['mother_place_of_birth'] ? htmlspecialchars($rec['mother_place_of_birth']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field" style="grid-column:span 2;">
                            <span class="detail-label">Home Address</span>
                            <span class="detail-value <?= empty($rec['address']) ? 'empty' : '' ?>">
                                <?= $rec['address'] ? htmlspecialchars($rec['address']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Kind of Marriage</span>
                            <span class="detail-value <?= empty($rec['kind_of_marriage']) ? 'empty' : '' ?>">
                                <?php
                                    $marriage_map = [
                                        'catholic'   => 'Catholic',
                                        'civil'      => 'Civil',
                                        'protestant' => 'Protestant',
                                        'aglipay'    => 'Aglipay',
                                        'others'     => 'Others',
                                    ];
                                    if ($rec['kind_of_marriage']) {
                                        $label = $marriage_map[$rec['kind_of_marriage']] ?? ucfirst($rec['kind_of_marriage']);
                                        if ($rec['kind_of_marriage'] === 'others' && !empty($rec['kind_of_marriage_other'])) {
                                            $label .= ' (' . htmlspecialchars($rec['kind_of_marriage_other']) . ')';
                                        }
                                        echo $label;
                                    } else {
                                        echo '—';
                                    }
                                ?>
                            </span>
                        </div>

                    </div>
                </div>
            </div>

            <!-- ── Section: Godparents ─────────────────── -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#fdf8ec;color:#b8933a;">
                        <i class="fas fa-hands-praying"></i>
                    </div>
                    <div class="detail-card-title">Godparents</div>
                </div>
                <div class="detail-card-body">
                    <div class="detail-grid-2">

                        <div class="detail-field">
                            <span class="detail-label">Godfather (Ninong)</span>
                            <span class="detail-value <?= empty($rec['godfather']) ? 'empty' : '' ?>">
                                <?= $rec['godfather'] ? htmlspecialchars($rec['godfather']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Godfather's Address</span>
                            <span class="detail-value <?= empty($rec['godfather_address']) ? 'empty' : '' ?>">
                                <?= $rec['godfather_address'] ? htmlspecialchars($rec['godfather_address']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Godmother (Ninang)</span>
                            <span class="detail-value <?= empty($rec['godmother']) ? 'empty' : '' ?>">
                                <?= $rec['godmother'] ? htmlspecialchars($rec['godmother']) : '—' ?>
                            </span>
                        </div>

                        <div class="detail-field">
                            <span class="detail-label">Godmother's Address</span>
                            <span class="detail-value <?= empty($rec['godmother_address']) ? 'empty' : '' ?>">
                                <?= $rec['godmother_address'] ? htmlspecialchars($rec['godmother_address']) : '—' ?>
                            </span>
                        </div>

                        <?php if (!empty($rec['other_sponsors'])): ?>
                        <div class="detail-field" style="grid-column:span 2;">
                            <span class="detail-label">Other Sponsors</span>
                            <span class="detail-value" style="white-space:pre-wrap;">
                                <?= htmlspecialchars($rec['other_sponsors']) ?>
                            </span>
                        </div>
                        <?php endif; ?>

                    </div>
                </div>
            </div>

            <!-- ── Section: Scanned Document ──────────── -->
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                        <i class="fas fa-file-image"></i>
                    </div>
                    <div class="detail-card-title">Scanned Document</div>
                    <?php if (!empty($rec['document_path'])): ?>
                    <div style="margin-left:auto;display:flex;gap:8px;">
                        <a href="<?= htmlspecialchars($rec['document_path']) ?>"
                           download
                           class="act-btn act-btn-outline"
                           style="padding:5px 12px;font-size:0.75rem;">
                            <i class="fas fa-download"></i> Download
                        </a>
                        <a href="<?= htmlspecialchars($rec['document_path']) ?>"
                           target="_blank"
                           class="act-btn act-btn-outline"
                           style="padding:5px 12px;font-size:0.75rem;">
                            <i class="fas fa-external-link"></i> Open
                        </a>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="detail-card-body" style="padding:16px;">
                    <?php if (!empty($rec['document_path'])): ?>
                    <div class="doc-preview-wrap">
                        <div class="doc-preview-toolbar">
                            <div class="doc-preview-name">
                                <i class="fas fa-file-image" style="color:#16a34a;"></i>
                                <?= basename($rec['document_path']) ?>
                            </div>
                            <span style="font-size:0.7rem;color:#9ca3af;">Scanned document</span>
                        </div>
                        <?php
                            $ext = strtolower(pathinfo($rec['document_path'], PATHINFO_EXTENSION));
                        ?>
                        <?php if ($ext === 'pdf'): ?>
                        <iframe src="<?= htmlspecialchars($rec['document_path']) ?>"
                                style="width:100%;height:480px;border:none;display:block;"
                                title="Baptismal Record Scan"></iframe>
                        <?php else: ?>
                        <a href="<?= htmlspecialchars($rec['document_path']) ?>" target="_blank">
                            <img src="<?= htmlspecialchars($rec['document_path']) ?>"
                                 alt="Baptismal record scan for <?= htmlspecialchars($rec['child_name']) ?>"
                                 class="doc-preview-img"
                                 loading="lazy">
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php else: ?>
                    <div class="doc-no-file">
                        <i class="fas fa-file-slash"></i>
                        <p style="font-size:0.85rem;color:#9ca3af;margin-bottom:8px;">No document uploaded</p>
                        <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
                        <a href="/church/modules/church_records/baptism/edit_record.php?id=<?= $id ?>"
                           class="act-btn act-btn-outline"
                           style="font-size:0.78rem;display:inline-flex;margin-top:4px;">
                            <i class="fas fa-upload"></i> Upload Document
                        </a>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- ── Section: Remarks ────────────────────── -->
            <?php if (!empty($rec['remarks'])): ?>
            <div class="detail-card">
                <div class="detail-card-header">
                    <div class="detail-card-header-icon" style="background:#f9fafb;color:#6b7280;">
                        <i class="fas fa-note-sticky"></i>
                    </div>
                    <div class="detail-card-title">Remarks</div>
                </div>
                <div class="detail-card-body">
                    <p style="font-size:0.875rem;color:#374151;line-height:1.65;white-space:pre-wrap;">
                        <?= htmlspecialchars($rec['remarks']) ?>
                    </p>
                </div>
            </div>
            <?php endif; ?>

        </div>
        <!-- /left column -->

        <!-- ── RIGHT SIDEBAR ──────────────────────────── -->
        <div style="display:flex;flex-direction:column;gap:16px;" id="viewSidebar">

            <!-- Quick actions -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;
                           color:#c4b89a;margin-bottom:12px;">Actions</p>
                <div style="display:flex;flex-direction:column;gap:8px;">
                    <a href="/church/modules/church_records/baptism/print_certificate.php?id=<?= $id ?>"
                       class="act-btn act-btn-blue" target="_blank"
                       style="justify-content:center;">
                        <i class="fas fa-print"></i> Print Certificate
                    </a>
                    <?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
                    <a href="/church/modules/church_records/baptism/edit_record.php?id=<?= $id ?>"
                       class="act-btn act-btn-gold"
                       style="justify-content:center;">
                        <i class="fas fa-pen"></i> Edit Record
                    </a>
                    <button onclick="confirmArchive()"
                            class="act-btn act-btn-red"
                            style="justify-content:center;width:100%;">
                        <i class="fas fa-box-archive"></i> Archive Record
                    </button>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Record metadata -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;
                           color:#c4b89a;margin-bottom:12px;">Record Info</p>
                <div>
                    <div class="info-row">
                        <span class="info-row-label">Record No.</span>
                        <span class="info-row-value" style="font-family:monospace;color:#3b82f6;">
                            <?= $rec['record_no'] ? htmlspecialchars($rec['record_no']) : '—' ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Series Year</span>
                        <span class="info-row-value"><?= $year ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Document</span>
                        <span class="info-row-value">
                            <?php if (!empty($rec['document_path'])): ?>
                            <span style="color:#16a34a;display:flex;align-items:center;gap:4px;justify-content:flex-end;">
                                <i class="fas fa-check-circle" style="font-size:0.75rem;"></i> Uploaded
                            </span>
                            <?php else: ?>
                            <span style="color:#c4b89a;">None</span>
                            <?php endif; ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Added by</span>
                        <span class="info-row-value">
                            <?= !empty($rec['created_by_name'])
                                ? htmlspecialchars($rec['created_by_name'])
                                : 'Unknown' ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Date Added</span>
                        <span class="info-row-value">
                            <?= $rec['created_at']
                                ? date('M j, Y', strtotime($rec['created_at']))
                                : '—' ?>
                        </span>
                    </div>
                    <?php if ($rec['updated_at'] && $rec['updated_at'] !== $rec['created_at']): ?>
                    <div class="info-row">
                        <span class="info-row-label">Last Updated</span>
                        <span class="info-row-value">
                            <?= date('M j, Y', strtotime($rec['updated_at'])) ?>
                        </span>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Linked booking -->
            <?php if ($booking): ?>
            <div style="background:#fffbeb;border:1px solid #fde68a;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;
                           color:#d97706;margin-bottom:10px;display:flex;align-items:center;gap:6px;">
                    <i class="fas fa-calendar-check"></i> Linked Booking
                </p>
                <div class="info-row" style="border-color:#fde68a;">
                    <span class="info-row-label" style="color:#b45309;">Booking #</span>
                    <span class="info-row-value"><?= $booking['id'] ?></span>
                </div>
                <div class="info-row" style="border-color:#fde68a;">
                    <span class="info-row-label" style="color:#b45309;">Requestor</span>
                    <span class="info-row-value"><?= htmlspecialchars($booking['requestor_name']) ?></span>
                </div>
                <div class="info-row" style="border-color:#fde68a;border-bottom:none;padding-bottom:0;">
                    <span class="info-row-label" style="color:#b45309;">Status</span>
                    <span class="info-row-value" style="text-transform:capitalize;"><?= $booking['status'] ?></span>
                </div>
                <a href="/church/bookings/view_booking.php?id=<?= $booking['id'] ?>"
                   style="display:flex;align-items:center;gap:6px;font-size:0.75rem;
                          color:#d97706;text-decoration:none;margin-top:12px;">
                    <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                    View Booking
                </a>
            </div>
            <?php endif; ?>

            <!-- Back to series -->
            <a href="/church/modules/church_records/baptism/series_records.php?year=<?= $year ?>"
               style="display:flex;align-items:center;gap:8px;padding:12px 16px;
                      background:#fff;border:1px solid #ede8de;border-radius:10px;
                      text-decoration:none;font-size:0.8rem;color:#6b7280;
                      transition:all 0.15s;"
               onmouseover="this.style.borderColor='#3b82f6';this.style.color='#3b82f6'"
               onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
                <i class="fas fa-arrow-left" style="font-size:0.7rem;color:#3b82f6;"></i>
                Back to <?= $year ?> Series
            </a>

        </div>
        <!-- /right sidebar -->

    </div>
    <!-- /viewLayout -->

</div>
<!-- /padding wrapper -->

<!-- Archive confirmation -->
<?php if (in_array($current_user_role, ['admin', 'clergy'])): ?>
<form id="archiveForm" method="POST" action="/church/modules/archive/archive_record.php">
    <input type="hidden" name="action"       value="archive">
    <input type="hidden" name="ref_type"     value="baptism">
    <input type="hidden" name="ref_id"       value="<?= $id ?>">
    <input type="hidden" name="redirect_url" value="/church/modules/church_records/baptism/view_record.php?id=<?= $id ?>">
</form>
<?php endif; ?>

<script>
function confirmArchive() {
    if (confirm('Archive this record for "<?= addslashes($rec['child_name']) ?>"?\n\nArchived records can be restored from the Archive module.')) {
        document.getElementById('archiveForm').submit();
    }
}

// Make sidebar sticky on desktop
<?php if (isset($_SERVER['HTTP_USER_AGENT'])): ?>
if (window.innerWidth >= 900) {
    document.getElementById('viewSidebar').style.position = 'sticky';
    document.getElementById('viewSidebar').style.top = '20px';
}
<?php endif; ?>
</script>

<?php include $root . '/includes/footer.php'; ?>