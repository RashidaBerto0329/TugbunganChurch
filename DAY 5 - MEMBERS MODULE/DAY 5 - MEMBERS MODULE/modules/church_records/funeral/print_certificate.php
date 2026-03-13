<?php
// church/modules/church_records/funeral/print_certificate.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /church/modules/church_records/funeral/series_list.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM funeral_records WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();
if (!$rec) { header('Location: /church/modules/church_records/funeral/series_list.php'); exit; }

$year = (int)$rec['series_year'];
$auto_print = isset($_GET['print']);

// Calculate age at death
$age_str = '';
if ($rec['date_of_birth'] && $rec['date_of_death']) {
    $dob = new DateTime($rec['date_of_birth']);
    $dod = new DateTime($rec['date_of_death']);
    $age_str = $dob->diff($dod)->y . ' years old';
}

function fd($d, $fmt = 'F j, Y') { return $d ? date($fmt, strtotime($d)) : ''; }
function fv($v, $fallback = '—') { return $v ? htmlspecialchars($v) : $fallback; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Funeral Certificate — <?= htmlspecialchars($rec['deceased_name']) ?></title>
<link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=EB+Garamond:ital,wght@0,400;0,500;1,400&family=DM+Sans:wght@400;500;600&display=swap" rel="stylesheet">
<style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        background: #e8e4dc;
        font-family: 'EB Garamond', serif;
        color: #1a1a2e;
        min-height: 100vh;
        display: flex;
        flex-direction: column;
        align-items: center;
        padding: 32px 16px 60px;
    }

    /* ── Screen toolbar ── */
    .screen-toolbar {
        width: 100%;
        max-width: 860px;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 12px;
        margin-bottom: 20px;
        flex-wrap: wrap;
    }
    .tb-btn {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px; border-radius: 8px; font-size: 0.83rem;
        font-weight: 600; border: none; cursor: pointer;
        text-decoration: none; font-family: 'DM Sans', sans-serif;
        transition: all 0.18s;
    }
    .tb-back  { background: #fff; color: #6b7280; border: 1px solid #d1c9b8; }
    .tb-back:hover  { background: #faf7f0; color: #0f2044; }
    .tb-print { background: #b8933a; color: #fff; }
    .tb-print:hover { background: #9a7a2e; }
    .tb-pdf   { background: #0f2044; color: #fff; }
    .tb-pdf:hover   { background: #162d5c; }

    /* ── Certificate page ── */
    .cert-wrap {
        width: 100%;
        max-width: 860px;
    }
    .cert-page {
        background: #faf7f0;
        box-shadow: 0 8px 40px rgba(0,0,0,0.18);
        position: relative;
        padding: 48px 52px;
        border: 1px solid #c9b98a;
        overflow: hidden;
    }

    /* ── Decorative borders ── */
    .cert-page::before {
        content: '';
        position: absolute;
        inset: 10px;
        border: 1.5px solid #b8933a;
        pointer-events: none;
    }
    .cert-page::after {
        content: '';
        position: absolute;
        inset: 14px;
        border: 0.5px solid rgba(184,147,58,0.35);
        pointer-events: none;
    }

    /* ── Watermark ── */
    .watermark {
        position: absolute;
        top: 50%; left: 50%;
        transform: translate(-50%, -50%) rotate(-35deg);
        font-family: 'Playfair Display', serif;
        font-size: 6rem;
        font-weight: 700;
        color: rgba(107,114,128,0.04);
        letter-spacing: 0.2em;
        pointer-events: none;
        white-space: nowrap;
        text-transform: uppercase;
        z-index: 0;
    }

    /* ── Corner ornaments ── */
    .corner {
        position: absolute;
        width: 36px; height: 36px;
        color: #b8933a;
        opacity: 0.5;
    }
    .corner-tl { top: 18px; left: 18px; }
    .corner-tr { top: 18px; right: 18px; transform: rotate(90deg); }
    .corner-bl { bottom: 18px; left: 18px; transform: rotate(-90deg); }
    .corner-br { bottom: 18px; right: 18px; transform: rotate(180deg); }

    /* ── Content above watermark ── */
    .cert-content { position: relative; z-index: 1; }

    /* ── Letterhead ── */
    .letterhead {
        text-align: center;
        margin-bottom: 20px;
        padding-bottom: 16px;
        border-bottom: 1px solid rgba(184,147,58,0.25);
    }
    .diocese {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #6b7280;
        margin-bottom: 4px;
    }
    .parish-name {
        font-family: 'Playfair Display', serif;
        font-size: 1.35rem;
        font-weight: 700;
        color: #0f2044;
        line-height: 1.2;
        margin-bottom: 3px;
    }
    .parish-address {
        font-size: 0.82rem;
        color: #9ca3af;
        font-style: italic;
    }

    /* ── Certificate title ── */
    .cert-title-section {
        text-align: center;
        margin: 20px 0 18px;
    }
    .cert-title-divider {
        display: flex;
        align-items: center;
        gap: 14px;
        margin-bottom: 12px;
    }
    .cert-title-divider::before,
    .cert-title-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, transparent, #b8933a, transparent);
    }
    .cert-title-icon {
        width: 44px; height: 44px;
        border-radius: 50%;
        background: linear-gradient(135deg, #374151, #4b5563);
        display: flex; align-items: center; justify-content: center;
        color: #d1d5db;
        font-size: 1rem;
        flex-shrink: 0;
    }
    .cert-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.72rem;
        font-weight: 600;
        letter-spacing: 0.22em;
        text-transform: uppercase;
        color: #b8933a;
        margin-bottom: 6px;
    }
    .cert-subtitle {
        font-family: 'Playfair Display', serif;
        font-size: 1.65rem;
        font-weight: 700;
        color: #0f2044;
        letter-spacing: 0.02em;
        line-height: 1.2;
    }

    /* ── Deceased name banner ── */
    .name-banner {
        text-align: center;
        background: linear-gradient(135deg, #1f2937, #374151);
        border-radius: 10px;
        padding: 18px 28px;
        margin: 20px 0;
        position: relative;
        overflow: hidden;
    }
    .name-banner::before {
        content: '✝';
        position: absolute;
        left: 18px; top: 50%;
        transform: translateY(-50%);
        font-size: 2rem;
        color: rgba(255,255,255,0.06);
    }
    .name-banner::after {
        content: '✝';
        position: absolute;
        right: 18px; top: 50%;
        transform: translateY(-50%);
        font-size: 2rem;
        color: rgba(255,255,255,0.06);
    }
    .nb-label {
        font-size: 0.65rem;
        font-weight: 600;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: rgba(209,213,219,0.5);
        font-family: 'DM Sans', sans-serif;
        margin-bottom: 4px;
    }
    .nb-name {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: #fff;
        line-height: 1.2;
    }
    .nb-dates {
        font-size: 0.82rem;
        color: rgba(209,213,219,0.6);
        margin-top: 5px;
        font-style: italic;
    }

    /* ── Section dividers ── */
    .section-divider {
        display: flex;
        align-items: center;
        gap: 10px;
        margin: 20px 0 14px;
    }
    .section-divider::before {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to right, #b8933a, rgba(184,147,58,0.1));
    }
    .section-divider-label {
        font-family: 'Playfair Display', serif;
        font-size: 0.78rem;
        font-weight: 600;
        color: #b8933a;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        display: flex;
        align-items: center;
        gap: 6px;
        white-space: nowrap;
    }
    .section-divider::after {
        content: '';
        flex: 1;
        height: 1px;
        background: linear-gradient(to left, #b8933a, rgba(184,147,58,0.1));
    }

    /* ── Details grid ── */
    .details-grid {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 14px 24px;
        margin-bottom: 14px;
    }
    .detail-row {
        border-bottom: 1px dotted rgba(184,147,58,0.25);
        padding-bottom: 8px;
    }
    .detail-row.full { grid-column: span 2; }
    .detail-label {
        font-family: 'DM Sans', sans-serif;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #9ca3af;
        display: block;
        margin-bottom: 3px;
    }
    .detail-value {
        font-size: 0.92rem;
        color: #1a1a2e;
        line-height: 1.3;
    }
    .detail-value.em {
        font-weight: 600;
    }

    /* ── Declaration ── */
    .declaration {
        background: rgba(184,147,58,0.04);
        border: 1px solid rgba(184,147,58,0.2);
        border-radius: 8px;
        padding: 16px 20px;
        text-align: center;
        margin: 20px 0;
    }
    .declaration p {
        font-style: italic;
        font-size: 0.9rem;
        color: #374151;
        line-height: 1.7;
    }

    /* ── Signature block ── */
    .sig-block {
        display: grid;
        grid-template-columns: 1fr 1fr 1fr;
        gap: 20px;
        margin-top: 32px;
        padding-top: 20px;
        border-top: 1px solid rgba(184,147,58,0.2);
    }
    .sig-item { text-align: center; }
    .sig-line {
        height: 1px;
        background: #1a1a2e;
        margin: 0 16px 5px;
    }
    .sig-name {
        font-size: 0.82rem;
        font-weight: 600;
        color: #0f2044;
        line-height: 1.2;
    }
    .sig-title {
        font-size: 0.68rem;
        color: #9ca3af;
        font-family: 'DM Sans', sans-serif;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        margin-top: 2px;
    }
    .sig-space { height: 52px; }
    .seal-circle {
        width: 72px; height: 72px;
        border-radius: 50%;
        border: 2px dashed rgba(184,147,58,0.35);
        display: flex; align-items: center; justify-content: center;
        margin: 0 auto 6px;
        color: rgba(184,147,58,0.3);
        font-size: 0.58rem;
        font-family: 'DM Sans', sans-serif;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        text-align: center;
    }

    /* ── Footer strip ── */
    .cert-footer {
        display: flex;
        align-items: center;
        justify-content: space-between;
        margin-top: 24px;
        padding-top: 14px;
        border-top: 1px solid rgba(184,147,58,0.18);
        font-family: 'DM Sans', sans-serif;
        font-size: 0.68rem;
        color: #c4b89a;
    }

    /* ── Print styles ── */
    @media print {
        @page { size: A4 portrait; margin: 0; }
        html, body { background: #fff !important; display: block !important; padding: 0 !important; }
        .screen-toolbar { display: none !important; }
        .cert-wrap { max-width: 100%; width: 100%; }
        .cert-page {
            box-shadow: none !important;
            border: none !important;
            padding: 38px 48px 34px;
            min-height: 100vh;
        }
        .cert-page::before { inset: 8px; }
        .cert-page::after  { inset: 12px; }
    }
</style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-toolbar">
    <a href="/church/modules/church_records/funeral/view_record.php?id=<?= $id ?>" class="tb-btn tb-back">
        <i class="fas fa-arrow-left"></i> Back to Record
    </a>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:0.78rem;color:#6b7280;font-family:'DM Sans',sans-serif;"><i class="fas fa-eye" style="margin-right:4px;"></i>Print Preview</span>
        <button onclick="window.print()" class="tb-btn tb-print"><i class="fas fa-print"></i> Print Certificate</button>
        <button onclick="window.location.href='/church/modules/church_records/shared/download_pdf.php?type=funeral&id=<?= $id ?>'" class="tb-btn tb-pdf"><i class="fas fa-file-pdf"></i> Save as PDF</button>
    </div>
</div>

<!-- Certificate -->
<div class="cert-wrap">
<div class="cert-page">

    <!-- Corner ornaments -->
    <svg class="corner corner-tl" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 34 L2 2 L34 2" stroke="currentColor" stroke-width="1.5"/><path d="M2 2 L10 10" stroke="currentColor" stroke-width="1"/></svg>
    <svg class="corner corner-tr" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 34 L2 2 L34 2" stroke="currentColor" stroke-width="1.5"/><path d="M2 2 L10 10" stroke="currentColor" stroke-width="1"/></svg>
    <svg class="corner corner-bl" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 34 L2 2 L34 2" stroke="currentColor" stroke-width="1.5"/><path d="M2 2 L10 10" stroke="currentColor" stroke-width="1"/></svg>
    <svg class="corner corner-br" viewBox="0 0 36 36" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 34 L2 2 L34 2" stroke="currentColor" stroke-width="1.5"/><path d="M2 2 L10 10" stroke="currentColor" stroke-width="1"/></svg>

    <!-- Watermark -->
    <div class="watermark">Funeral</div>

    <div class="cert-content">

        <!-- Letterhead -->
        <div class="letterhead">
            <div class="diocese">Diocese of Zamboanga</div>
            <div class="parish-name">Our Lady of Peace and Good Voyage Parish</div>
            <div class="parish-address">Tugbungan, Zamboanga City, Philippines</div>
        </div>

        <!-- Certificate title -->
        <div class="cert-title-section">
            <div class="cert-title">Sacramental Certificate</div>
            <div class="cert-title-divider">
                <div class="cert-title-icon">✝</div>
            </div>
            <div class="cert-subtitle">Certificate of Christian Burial</div>
        </div>

        <!-- Deceased name banner -->
        <div class="name-banner">
            <div class="nb-label">In Loving Memory of</div>
            <div class="nb-name"><?= htmlspecialchars($rec['deceased_name']) ?></div>
            <?php if ($rec['date_of_birth'] || $rec['date_of_death']): ?>
            <div class="nb-dates">
                <?php if ($rec['date_of_birth']): ?>&#9830; Born <?= fd($rec['date_of_birth']) ?><?php endif; ?>
                <?php if ($rec['date_of_birth'] && $rec['date_of_death']): ?> &nbsp;·&nbsp; <?php endif; ?>
                <?php if ($rec['date_of_death']): ?>Died <?= fd($rec['date_of_death']) ?><?php endif; ?>
                <?php if ($age_str): ?> &nbsp;·&nbsp; <?= htmlspecialchars($age_str) ?><?php endif; ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Funeral Details -->
        <div class="section-divider"><div class="section-divider-label">✝ Funeral Details</div></div>
        <div class="details-grid">
            <div class="detail-row">
                <span class="detail-label">Date of Funeral Mass</span>
                <span class="detail-value em"><?= $rec['date_of_funeral'] ? fd($rec['date_of_funeral']) : '—' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Record Number</span>
                <span class="detail-value" style="font-family:monospace;"><?= fv($rec['record_no']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Officiating Minister</span>
                <span class="detail-value em"><?= fv($rec['minister'] ?: ($rec['minister_name'] ?? null)) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Series Year</span>
                <span class="detail-value"><?= $year ?></span>
            </div>
        </div>

        <!-- Deceased Information -->
        <div class="section-divider"><div class="section-divider-label">✝ Deceased Information</div></div>
        <div class="details-grid">
            <div class="detail-row full">
                <span class="detail-label">Full Name of Deceased</span>
                <span class="detail-value em" style="font-size:1rem;font-family:'Playfair Display',serif;"><?= htmlspecialchars($rec['deceased_name']) ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date of Birth</span>
                <span class="detail-value"><?= $rec['date_of_birth'] ? fd($rec['date_of_birth']) : '—' ?></span>
            </div>
            <div class="detail-row">
                <span class="detail-label">Date of Death</span>
                <span class="detail-value"><?= $rec['date_of_death'] ? fd($rec['date_of_death']) : '—' ?></span>
            </div>
            <?php if ($rec['address']): ?>
            <div class="detail-row full">
                <span class="detail-label">Last Known Address</span>
                <span class="detail-value"><?= htmlspecialchars($rec['address']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($rec['place_of_burial']): ?>
            <div class="detail-row full">
                <span class="detail-label">Place of Burial</span>
                <span class="detail-value"><?= htmlspecialchars($rec['place_of_burial']) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <!-- Next of Kin -->
        <?php if ($rec['next_of_kin'] || $rec['next_of_kin_name']): ?>
        <div class="section-divider"><div class="section-divider-label">✝ Next of Kin</div></div>
        <div class="details-grid">
            <?php if ($rec['next_of_kin']): ?>
            <div class="detail-row">
                <span class="detail-label">Relationship / Family</span>
                <span class="detail-value"><?= htmlspecialchars($rec['next_of_kin']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($rec['next_of_kin_name']): ?>
            <div class="detail-row">
                <span class="detail-label">Contact Person</span>
                <span class="detail-value"><?= htmlspecialchars($rec['next_of_kin_name']) ?></span>
            </div>
            <?php endif; ?>
            <?php if ($rec['next_of_kin_contact']): ?>
            <div class="detail-row">
                <span class="detail-label">Contact Number</span>
                <span class="detail-value"><?= htmlspecialchars($rec['next_of_kin_contact']) ?></span>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <!-- Declaration -->
        <div class="declaration">
            <p>This is to certify that the Sacrament of Christian Burial was duly administered
            to <strong><?= htmlspecialchars($rec['deceased_name']) ?></strong>
            <?php if ($rec['date_of_funeral']): ?>on <strong><?= fd($rec['date_of_funeral']) ?></strong><?php endif; ?>
            in accordance with the rites and ceremonies of the Roman Catholic Church,
            as recorded in the Sacramental Register of this Parish.</p>
        </div>

        <!-- Remarks -->
        <?php if (!empty($rec['remarks'])): ?>
        <div style="background:rgba(184,147,58,0.04);border:1px solid rgba(184,147,58,0.15);border-radius:8px;padding:12px 18px;margin-bottom:20px;">
            <div style="font-family:'DM Sans',sans-serif;font-size:0.65rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:#9ca3af;margin-bottom:5px;">Notes</div>
            <p style="font-size:0.88rem;color:#374151;font-style:italic;line-height:1.6;"><?= htmlspecialchars($rec['remarks']) ?></p>
        </div>
        <?php endif; ?>

        <!-- Signature block -->
        <div class="sig-block">
            <div class="sig-item">
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-name"><?= fv($rec['minister'] ?: ($rec['minister_name'] ?? null), 'Officiating Minister') ?></div>
                <div class="sig-title">Officiating Minister</div>
            </div>
            <div class="sig-item">
                <div class="seal-circle">Parish<br>Seal</div>
                <div class="sig-line"></div>
                <div class="sig-name">Parish Priest</div>
                <div class="sig-title">Parish Priest</div>
            </div>
            <div class="sig-item">
                <div class="sig-space"></div>
                <div class="sig-line"></div>
                <div class="sig-name">Parish Secretary</div>
                <div class="sig-title">Parish Secretary</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="cert-footer">
            <span>Record No.: <?= htmlspecialchars($rec['record_no'] ?? '—') ?> &nbsp;·&nbsp; <?= $year ?> Funeral Series</span>
            <span>Printed: <?= date('F j, Y') ?></span>
        </div>

    </div><!-- /cert-content -->
</div><!-- /cert-page -->
</div><!-- /cert-wrap -->

<link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
<script>
function savePDF() {
    const orig = document.title;
    document.title = 'Funeral_Certificate_<?= preg_replace('/[^a-z0-9]/i','_', $rec['deceased_name']) ?>';
    window.print();
    document.title = orig;
}
<?php if ($auto_print): ?>
window.addEventListener('load', () => setTimeout(() => window.print(), 600));
<?php endif; ?>
</script>
</body>
</html>