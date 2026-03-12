<?php
// church/modules/church_records/communion/print_certificate.php
// Communion Module: Printable First Holy Communion Certificate
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Invalid record ID.";
    header('Location: /church/modules/church_records/communion/series_list.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM communion_records WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    $_SESSION['error'] = "Record not found or archived.";
    header('Location: /church/modules/church_records/communion/series_list.php');
    exit;
}

function fmt_date($d, $format = 'F j, Y') {
    return $d ? date($format, strtotime($d)) : '—';
}
function fmt_val($v) {
    return $v ? htmlspecialchars($v) : '<span class="empty">—</span>';
}

$year           = (int)$rec['series_year'];
$communion_date = fmt_date($rec['date_of_communion']);
$birth_date     = fmt_date($rec['date_of_birth']);
$baptism_date   = fmt_date($rec['baptism_date']);
$printed_on     = date('F j, Y');

$sponsor  = $rec['sponsor']  ?? null;
$minister = $rec['minister'] ?? null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Certificate of First Holy Communion</title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600&family=EB+Garamond:ital,wght@0,400;0,500;1,400;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

        :root {
            --navy:    #0f2044;
            --gold:    #b8933a;
            --gold-dk: #9a7a2e;
            --gold-lt: #e8d99a;
            --parchment: #faf7f0;
        }

        body {
            font-family: 'DM Sans', sans-serif;
            background: #e8e4dc;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 32px 16px 60px;
        }

        .screen-toolbar {
            width: 100%; max-width: 820px;
            display: flex; align-items: center; justify-content: space-between;
            gap: 12px; margin-bottom: 20px; flex-wrap: wrap;
        }
        .toolbar-btn {
            display: inline-flex; align-items: center; gap: 6px;
            padding: 9px 18px; border-radius: 8px;
            font-size: 0.83rem; font-weight: 600;
            text-decoration: none; border: none; cursor: pointer;
            font-family: 'DM Sans', sans-serif; transition: all 0.18s;
        }
        .btn-navy  { background: var(--navy); color: #fff; }
        .btn-navy:hover { background: #162d5c; }
        .btn-gold  { background: var(--gold); color: #fff; }
        .btn-gold:hover { background: var(--gold-dk); }
        .btn-outline-sm { background: #fff; color: #6b7280; border: 1px solid #d1c9b8; font-size: 0.78rem; padding: 7px 14px; }
        .btn-outline-sm:hover { background: var(--parchment); color: var(--navy); }

        .certificate-page {
            width: 100%; max-width: 820px;
            background: var(--parchment);
            border: 1px solid #c9b98a;
            box-shadow: 0 8px 40px rgba(0,0,0,0.18);
            position: relative; overflow: hidden;
        }

        .cert-border { position: absolute; inset: 10px; border: 2px solid var(--gold-lt); pointer-events: none; z-index: 1; }
        .cert-border::before { content: ''; position: absolute; inset: 4px; border: 0.5px solid rgba(184,147,58,0.25); }

        .corner { position: absolute; width: 52px; height: 52px; z-index: 2; pointer-events: none; }
        .corner svg { width: 100%; height: 100%; }
        .corner-tl { top: 10px;  left: 10px;  }
        .corner-tr { top: 10px;  right: 10px; transform: scaleX(-1); }
        .corner-bl { bottom: 10px; left: 10px;  transform: scaleY(-1); }
        .corner-br { bottom: 10px; right: 10px; transform: scale(-1,-1); }

        .cert-inner { position: relative; z-index: 3; padding: 52px 60px 48px; }

        .cert-letterhead { text-align: center; margin-bottom: 8px; }
        .cert-diocese { font-family: 'EB Garamond', serif; font-size: 0.82rem; letter-spacing: 0.2em; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
        .cert-parish-name { font-family: 'Playfair Display', serif; font-size: 1.65rem; font-weight: 700; color: var(--navy); line-height: 1.15; margin-bottom: 4px; }
        .cert-parish-address { font-family: 'EB Garamond', serif; font-size: 0.88rem; color: #6b7280; font-style: italic; }

        .cert-divider { display: flex; align-items: center; gap: 14px; margin: 18px 0; }
        .cert-divider-line { flex: 1; height: 1px; background: linear-gradient(to right, transparent, var(--gold-lt), var(--gold), var(--gold-lt), transparent); }
        .cert-divider-icon { color: var(--gold); font-size: 1rem; flex-shrink: 0; }

        .cert-title-wrap { text-align: center; margin-bottom: 24px; }
        .cert-subtitle { font-family: 'EB Garamond', serif; font-size: 0.78rem; letter-spacing: 0.25em; text-transform: uppercase; color: var(--gold); margin-bottom: 6px; }
        .cert-title { font-family: 'Playfair Display', serif; font-size: 2.1rem; font-weight: 700; color: var(--navy); line-height: 1; }
        .cert-title em { font-style: italic; color: var(--gold); }

        .cert-intro { font-family: 'EB Garamond', serif; font-size: 1rem; color: #374151; text-align: center; line-height: 1.7; margin-bottom: 28px; }
        .cert-intro .highlight { font-family: 'Playfair Display', serif; font-size: 1.4rem; font-weight: 700; color: var(--navy); display: block; margin: 6px 0; border-bottom: 1px solid var(--gold-lt); padding-bottom: 6px; }

        .cert-details { background: rgba(184,147,58,0.04); border: 1px solid var(--gold-lt); border-radius: 6px; padding: 20px 28px; margin-bottom: 24px; }
        .cert-detail-row { display: grid; grid-template-columns: repeat(2, 1fr); gap: 12px 24px; }
        .cert-detail-item { display: flex; flex-direction: column; gap: 2px; padding: 8px 0; border-bottom: 1px solid rgba(184,147,58,0.12); }
        .cert-detail-item:nth-last-child(-n+2) { border-bottom: none; }
        .cert-detail-label { font-family: 'DM Sans', sans-serif; font-size: 0.62rem; font-weight: 700; letter-spacing: 0.14em; text-transform: uppercase; color: var(--gold); }
        .cert-detail-value { font-family: 'EB Garamond', serif; font-size: 1.02rem; color: var(--navy); font-weight: 500; }
        .cert-detail-value.empty { color: #c4b89a; font-style: italic; font-size: 0.88rem; }

        .cert-declaration { font-family: 'EB Garamond', serif; font-size: 0.92rem; color: #4b5563; text-align: center; line-height: 1.8; margin-bottom: 32px; font-style: italic; }

        .cert-signatures { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; margin-top: 8px; }
        .cert-sig-block { text-align: center; }
        .cert-sig-line { border-top: 1px solid var(--navy); margin: 0 auto 6px; width: 80%; }
        .cert-sig-name { font-family: 'EB Garamond', serif; font-size: 0.9rem; color: var(--navy); font-weight: 500; margin-bottom: 2px; }
        .cert-sig-role { font-size: 0.68rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: var(--gold); }

        .cert-footer { display: flex; align-items: center; justify-content: space-between; margin-top: 28px; padding-top: 14px; border-top: 1px solid var(--gold-lt); flex-wrap: wrap; gap: 8px; }
        .cert-footer-left, .cert-footer-right { font-size: 0.68rem; color: #9ca3af; font-family: 'DM Sans', sans-serif; }
        .cert-footer-center { font-family: 'Playfair Display', serif; font-size: 0.7rem; color: var(--gold); letter-spacing: 0.1em; text-transform: uppercase; }

        .cert-watermark { position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%) rotate(-30deg); font-family: 'Playfair Display', serif; font-size: 5.5rem; font-weight: 700; color: rgba(184,147,58,0.04); white-space: nowrap; pointer-events: none; z-index: 0; user-select: none; letter-spacing: -3px; }

        .claim-notice {
            background: rgba(184,147,58,0.04);
            border: 1px solid var(--gold-lt);
            border-radius: 6px;
            padding: 12px 18px;
            margin-bottom: 24px;
            text-align: center;
        }
        .claim-notice p { font-family: 'EB Garamond', serif; font-size: 0.82rem; color: #6b5f4e; line-height: 1.6; }
        .claim-notice strong { color: var(--gold-dk); }

        @media print {
            @page { size: A4 portrait; margin: 0; }
            body { background: #fff; padding: 0; display: block; }
            .screen-toolbar { display: none !important; }
            .certificate-page { max-width: 100%; width: 100%; box-shadow: none; border: none; min-height: 100vh; }
            .cert-inner { padding: 40px 50px 36px; }
            .cert-border { border-color: var(--gold); }
        }
    </style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-toolbar">
    <div>
        <a href="/church/modules/church_records/communion/view_record.php?id=<?= $id ?>"
           class="toolbar-btn btn-outline-sm">
            <i class="fas fa-arrow-left"></i> Back to Record
        </a>
    </div>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:0.78rem;color:#6b7280;">
            <i class="fas fa-eye" style="margin-right:4px;"></i> Print Preview
        </span>
        <button onclick="window.print()" class="toolbar-btn btn-gold">
            <i class="fas fa-print"></i> Print Certificate
        </button>
        <button onclick="window.location.href='/church/modules/church_records/shared/download_pdf.php?type=communion&id=<?= $id ?>'" class="toolbar-btn btn-navy">
            <i class="fas fa-file-pdf"></i> Save as PDF
        </button>
    </div>
</div>

<div class="certificate-page">

    <div class="cert-border"></div>

    <?php
    $corner_svg = '<svg viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg">
        <path d="M2 2 L20 2 M2 2 L2 20" stroke="#b8933a" stroke-width="2" stroke-linecap="round"/>
        <path d="M6 2 L6 10 Q6 14 10 14 Q6 14 6 18 L6 26" stroke="#b8933a" stroke-width="0.8" stroke-linecap="round" opacity="0.5"/>
        <circle cx="2" cy="2" r="2" fill="#b8933a"/>
        <circle cx="14" cy="14" r="3" fill="none" stroke="#e8d99a" stroke-width="1"/>
    </svg>';
    ?>
    <div class="corner corner-tl"><?= $corner_svg ?></div>
    <div class="corner corner-tr"><?= $corner_svg ?></div>
    <div class="corner corner-bl"><?= $corner_svg ?></div>
    <div class="corner corner-br"><?= $corner_svg ?></div>

    <div class="cert-watermark">FIRST COMMUNION</div>

    <div class="cert-inner">

        <!-- Letterhead -->
        <div class="cert-letterhead">
            <div class="cert-diocese">Diocese of Zamboanga · Parish Records Office</div>
            <div class="cert-parish-name">Our Lady of Peace<br>and Good Voyage Parish</div>
            <div class="cert-parish-address">Tugbungan, Zamboanga City, Philippines</div>
        </div>

        <!-- Divider -->
        <div class="cert-divider">
            <div class="cert-divider-line"></div>
            <div class="cert-divider-icon"><i class="fas fa-bread-slice"></i></div>
            <div class="cert-divider-line"></div>
        </div>

        <!-- Title -->
        <div class="cert-title-wrap">
            <div class="cert-subtitle">Sacrament of the Eucharist</div>
            <div class="cert-title">Certificate of <em>First Holy Communion</em></div>
        </div>

        <!-- Intro -->
        <div class="cert-intro">
            This is to certify that the following person has received the<br>
            Sacrament of First Holy Communion for the first time on
            <strong><?= $communion_date ?></strong>
            <span class="highlight"><?= htmlspecialchars($rec['name']) ?></span>
            at Our Lady of Peace and Good Voyage Parish, Tugbungan, Zamboanga City
        </div>

        <!-- Details grid -->
        <div class="cert-details">
            <div class="cert-detail-row">

                <div class="cert-detail-item">
                    <span class="cert-detail-label">Date of Birth</span>
                    <span class="cert-detail-value <?= !$rec['date_of_birth'] ? 'empty' : '' ?>">
                        <?= $birth_date ?>
                    </span>
                </div>

                <div class="cert-detail-item">
                    <span class="cert-detail-label">Date of First Communion</span>
                    <span class="cert-detail-value <?= !$rec['date_of_communion'] ? 'empty' : '' ?>">
                        <?= $communion_date ?>
                    </span>
                </div>

                <div class="cert-detail-item">
                    <span class="cert-detail-label">Father's Name</span>
                    <span class="cert-detail-value <?= empty($rec['father_name']) ? 'empty' : '' ?>">
                        <?= fmt_val($rec['father_name']) ?>
                    </span>
                </div>

                <div class="cert-detail-item">
                    <span class="cert-detail-label">Mother's Name</span>
                    <span class="cert-detail-value <?= empty($rec['mother_name']) ? 'empty' : '' ?>">
                        <?= fmt_val($rec['mother_name']) ?>
                    </span>
                </div>

                <div class="cert-detail-item">
                    <span class="cert-detail-label">Sponsor</span>
                    <span class="cert-detail-value <?= empty($sponsor) ? 'empty' : '' ?>">
                        <?= fmt_val($sponsor) ?>
                    </span>
                </div>

                <div class="cert-detail-item">
                    <span class="cert-detail-label">Officiating Minister</span>
                    <span class="cert-detail-value <?= empty($minister) ? 'empty' : '' ?>">
                        <?= fmt_val($minister) ?>
                    </span>
                </div>

                <?php if (!empty($rec['baptism_date']) || !empty($rec['baptism_parish'])): ?>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Date of Baptism</span>
                    <span class="cert-detail-value <?= empty($rec['baptism_date']) ? 'empty' : '' ?>">
                        <?= $baptism_date ?>
                    </span>
                </div>
                <div class="cert-detail-item">
                    <span class="cert-detail-label">Baptism Parish</span>
                    <span class="cert-detail-value <?= empty($rec['baptism_parish']) ? 'empty' : '' ?>">
                        <?= fmt_val($rec['baptism_parish']) ?>
                    </span>
                </div>
                <?php else: ?>
                <div class="cert-detail-item" style="grid-column:span 2;">
                    <span class="cert-detail-label">Home Address</span>
                    <span class="cert-detail-value <?= empty($rec['address']) ? 'empty' : '' ?>">
                        <?= fmt_val($rec['address']) ?>
                    </span>
                </div>
                <?php endif; ?>

            </div>
        </div>

        <!-- Certificate Claim Policy Notice -->
        <div class="claim-notice">
            <p>
                <strong>Certificate Claim Policy:</strong>
                This certificate may only be claimed by the person who requested it.
                If the requestor is unavailable, the representative must present an
                <strong>Authorization Letter with notarization from an attorney.</strong>
            </p>
        </div>

        <!-- Declaration -->
        <div class="cert-declaration">
            In witness whereof, we hereby affix our signature and the seal of the Parish,<br>
            at Tugbungan, Zamboanga City, on the <?= date('jS') ?> day of <?= date('F, Y') ?>.
        </div>

        <!-- Signatures -->
        <div class="cert-signatures">
            <div class="cert-sig-block">
                <div style="height:48px;"></div>
                <div class="cert-sig-line"></div>
                <div class="cert-sig-name"><?= $minister ? htmlspecialchars($minister) : 'Parish Priest' ?></div>
                <div class="cert-sig-role">Officiating Minister</div>
            </div>
            <div class="cert-sig-block">
                <div style="height:48px;"></div>
                <div class="cert-sig-line"></div>
                <div class="cert-sig-name">Parish Priest</div>
                <div class="cert-sig-role">Parish Administrator</div>
            </div>
            <div class="cert-sig-block">
                <div style="height:48px;display:flex;align-items:center;justify-content:center;">
                    <div style="width:60px;height:60px;border-radius:50%;border:2px solid var(--gold-lt);display:flex;align-items:center;justify-content:center;color:var(--gold-lt);font-size:1.4rem;">
                        <i class="fas fa-stamp"></i>
                    </div>
                </div>
                <div class="cert-sig-line"></div>
                <div class="cert-sig-name">Parish Secretary</div>
                <div class="cert-sig-role">Records Officer</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="cert-footer">
            <div class="cert-footer-left">
                Record No.: <strong><?= htmlspecialchars($rec['record_no']) ?></strong>
                &nbsp;·&nbsp; Series: <strong><?= $year ?></strong>
            </div>
            <div class="cert-footer-center">
                ✦ Our Lady of Peace and Good Voyage Parish ✦
            </div>
            <div class="cert-footer-right">
                Printed: <?= $printed_on ?>
            </div>
        </div>

    </div>
</div>

<script>
const params = new URLSearchParams(window.location.search);
if (params.get('print') === '1') {
    window.addEventListener('load', () => { setTimeout(() => window.print(), 600); });
}
</script>

</body>
</html>