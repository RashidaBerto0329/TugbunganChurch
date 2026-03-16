<?php
// church/modules/church_records/wedding/print_certificate.php
// ALL PHP runs BEFORE any HTML output
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    $_SESSION['error'] = "Invalid record ID.";
    header('Location: /church/modules/church_records/wedding/series_list.php');
    exit;
}

$stmt = $conn->prepare("SELECT * FROM wedding_records WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    $_SESSION['error'] = "Record not found or archived.";
    header('Location: /church/modules/church_records/wedding/series_list.php');
    exit;
}

function fd($d, $fmt = 'F j, Y') { return $d ? date($fmt, strtotime($d)) : '—'; }
function fv($v) { return $v ? htmlspecialchars($v) : '<span class="empty">—</span>'; }

$year        = (int)$rec['series_year'];
$wed_date    = fd($rec['date_of_wedding']);
$printed_on  = date('F j, Y');
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Marriage Certificate — <?= htmlspecialchars($rec['groom_name']) ?> & <?= htmlspecialchars($rec['bride_name']) ?></title>
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Playfair+Display:ital,wght@0,400;0,600;0,700;1,400;1,600&family=DM+Sans:wght@300;400;500;600&family=EB+Garamond:ital,wght@0,400;0,500;1,400;1,500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.0/css/all.min.css">
    <style>
        *,*::before,*::after{box-sizing:border-box;margin:0;padding:0;}
        :root{--navy:#0f2044;--gold:#b8933a;--gold-lt:#e8d99a;--parchment:#faf7f0;}
        body{font-family:'DM Sans',sans-serif;background:#e8e4dc;min-height:100vh;display:flex;flex-direction:column;align-items:center;padding:32px 16px 60px;}
        .screen-toolbar{width:100%;max-width:860px;display:flex;align-items:center;justify-content:space-between;gap:12px;margin-bottom:20px;flex-wrap:wrap;}
        .tb{display:inline-flex;align-items:center;gap:6px;padding:9px 18px;border-radius:8px;font-size:0.83rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;font-family:'DM Sans',sans-serif;transition:all 0.18s;}
        .tb-navy{background:var(--navy);color:#fff;} .tb-navy:hover{background:#162d5c;}
        .tb-gold{background:var(--gold);color:#fff;} .tb-gold:hover{background:#9a7a2e;}
        .tb-out{background:#fff;color:#6b7280;border:1px solid #d1c9b8;font-size:0.78rem;padding:7px 14px;} .tb-out:hover{background:var(--parchment);color:var(--navy);}
        .cert-page{width:100%;max-width:860px;background:var(--parchment);border:1px solid #c9b98a;box-shadow:0 8px 40px rgba(0,0,0,0.18);position:relative;overflow:hidden;}
        .cert-border{position:absolute;inset:10px;border:2px solid var(--gold-lt);pointer-events:none;z-index:1;}
        .cert-border::before{content:'';position:absolute;inset:4px;border:0.5px solid rgba(184,147,58,0.25);}
        .corner{position:absolute;width:52px;height:52px;z-index:2;pointer-events:none;}
        .corner-tl{top:10px;left:10px;} .corner-tr{top:10px;right:10px;transform:scaleX(-1);}
        .corner-bl{bottom:10px;left:10px;transform:scaleY(-1);} .corner-br{bottom:10px;right:10px;transform:scale(-1,-1);}
        .watermark{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%) rotate(-30deg);font-family:'Playfair Display',serif;font-size:6rem;font-weight:700;color:rgba(184,147,58,0.04);white-space:nowrap;pointer-events:none;z-index:0;user-select:none;}
        .inner{position:relative;z-index:3;padding:52px 60px 48px;}
        /* Letterhead */
        .diocese{font-family:'EB Garamond',serif;font-size:0.8rem;letter-spacing:0.2em;text-transform:uppercase;color:var(--gold);text-align:center;margin-bottom:5px;}
        .parish-name{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--navy);text-align:center;line-height:1.15;margin-bottom:3px;}
        .parish-addr{font-family:'EB Garamond',serif;font-size:0.87rem;color:#6b7280;font-style:italic;text-align:center;}
        .divider{display:flex;align-items:center;gap:14px;margin:16px 0;}
        .divider-line{flex:1;height:1px;background:linear-gradient(to right,transparent,var(--gold-lt),var(--gold),var(--gold-lt),transparent);}
        .divider-icon{color:var(--gold);font-size:1rem;}
        /* Title */
        .cert-subtitle{font-family:'EB Garamond',serif;font-size:0.76rem;letter-spacing:0.25em;text-transform:uppercase;color:var(--gold);text-align:center;margin-bottom:5px;}
        .cert-title{font-family:'Playfair Display',serif;font-size:2.1rem;font-weight:700;color:var(--navy);text-align:center;line-height:1;}
        .cert-title em{font-style:italic;color:var(--gold);}
        /* Couple names banner */
        .couple-banner{background:linear-gradient(135deg,rgba(184,147,58,0.06),rgba(184,147,58,0.03));border:1px solid var(--gold-lt);border-radius:8px;padding:16px 24px;text-align:center;margin:22px 0 18px;}
        .couple-names{font-family:'Playfair Display',serif;font-size:1.6rem;font-weight:700;color:var(--navy);line-height:1.2;margin-bottom:5px;}
        .couple-amp{color:var(--gold);font-style:italic;font-size:1.2rem;margin:0 10px;}
        .couple-date{font-family:'EB Garamond',serif;font-size:0.92rem;color:#6b7280;font-style:italic;}
        /* Details grid */
        .cert-details{border:1px solid var(--gold-lt);border-radius:6px;padding:18px 24px;margin-bottom:20px;}
        .details-grid{display:grid;grid-template-columns:1fr 1fr;gap:0;}
        .d-col{padding:0 12px;} .d-col:first-child{border-right:1px solid var(--gold-lt);}
        .d-section-title{font-family:'DM Sans',sans-serif;font-size:0.6rem;font-weight:700;letter-spacing:0.18em;text-transform:uppercase;color:var(--gold);margin-bottom:10px;display:flex;align-items:center;gap:6px;}
        .d-item{display:flex;flex-direction:column;gap:2px;padding:7px 0;border-bottom:1px solid rgba(184,147,58,0.1);}
        .d-item:last-child{border-bottom:none;}
        .d-label{font-family:'DM Sans',sans-serif;font-size:0.6rem;font-weight:700;letter-spacing:0.12em;text-transform:uppercase;color:var(--gold);}
        .d-value{font-family:'EB Garamond',serif;font-size:0.98rem;color:var(--navy);}
        .empty{color:#c4b89a;font-style:italic;font-size:0.88rem;}
        /* Bottom section: sponsors + declaration */
        .cert-sponsors{background:rgba(184,147,58,0.03);border:1px solid var(--gold-lt);border-radius:6px;padding:14px 24px;margin-bottom:18px;}
        .sponsors-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:12px;}
        .sp-item{display:flex;flex-direction:column;gap:2px;}
        /* Declaration */
        .declaration{font-family:'EB Garamond',serif;font-size:0.9rem;color:#4b5563;text-align:center;line-height:1.8;margin-bottom:26px;font-style:italic;}
        /* Signatures */
        .sigs{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;margin-top:4px;}
        .sig-block{text-align:center;}
        .sig-line{border-top:1px solid var(--navy);margin:0 auto 5px;width:80%;}
        .sig-name{font-family:'EB Garamond',serif;font-size:0.88rem;color:var(--navy);font-weight:500;margin-bottom:2px;}
        .sig-role{font-size:0.65rem;font-weight:600;letter-spacing:0.1em;text-transform:uppercase;color:var(--gold);}
        /* Footer */
        .cert-footer{display:flex;align-items:center;justify-content:space-between;margin-top:24px;padding-top:12px;border-top:1px solid var(--gold-lt);font-size:0.67rem;color:#9ca3af;flex-wrap:wrap;gap:8px;}
        .cert-footer-center{font-family:'Playfair Display',serif;font-size:0.68rem;color:var(--gold);letter-spacing:0.08em;text-transform:uppercase;}
        @media print{
            @page{size:A4 portrait;margin:0;}
            body{background:#fff;padding:0;display:block;}
            .screen-toolbar{display:none!important;}
            .cert-page{max-width:100%;width:100%;box-shadow:none;border:none;min-height:100vh;}
            .inner{padding:38px 48px 34px;}
        }
    </style>
</head>
<body>

<!-- Screen toolbar -->
<div class="screen-toolbar">
    <a href="/church/modules/church_records/wedding/view_record.php?id=<?= $id ?>" class="tb tb-out">
        <i class="fas fa-arrow-left"></i> Back to Record
    </a>
    <div style="display:flex;gap:8px;align-items:center;">
        <span style="font-size:0.78rem;color:#6b7280;"><i class="fas fa-eye" style="margin-right:4px;"></i>Print Preview</span>
        <button onclick="window.print()" class="tb tb-gold"><i class="fas fa-print"></i> Print Certificate</button>
        <button onclick="window.location.href='/church/modules/church_records/shared/download_pdf.php?type=wedding&id=<?= $id ?>'" class="tb tb-navy"><i class="fas fa-file-pdf"></i> Save as PDF</button>
    </div>
</div>

<!-- Certificate -->
<div class="cert-page">
    <div class="cert-border"></div>
    <?php $corner = '<svg viewBox="0 0 52 52" fill="none" xmlns="http://www.w3.org/2000/svg"><path d="M2 2 L20 2 M2 2 L2 20" stroke="#b8933a" stroke-width="2" stroke-linecap="round"/><path d="M6 2 L6 10 Q6 14 10 14 Q6 14 6 18 L6 26" stroke="#b8933a" stroke-width="0.8" stroke-linecap="round" opacity="0.5"/><circle cx="2" cy="2" r="2" fill="#b8933a"/><circle cx="14" cy="14" r="3" fill="none" stroke="#e8d99a" stroke-width="1"/></svg>'; ?>
    <div class="corner corner-tl"><?= $corner ?></div>
    <div class="corner corner-tr"><?= $corner ?></div>
    <div class="corner corner-bl"><?= $corner ?></div>
    <div class="corner corner-br"><?= $corner ?></div>
    <div class="watermark">MATRIMONY</div>

    <div class="inner">

        <!-- Letterhead -->
        <div class="diocese">Diocese of Zamboanga · Parish Records Office</div>
        <div class="parish-name">Our Lady of Peace<br>and Good Voyage Parish</div>
        <div class="parish-addr">Tugbungan, Zamboanga City, Philippines</div>

        <div class="divider">
            <div class="divider-line"></div>
            <div class="divider-icon"><i class="fas fa-ring"></i></div>
            <div class="divider-line"></div>
        </div>

        <!-- Title -->
        <div class="cert-subtitle">Sacrament of Matrimony</div>
        <div class="cert-title">Certificate of <em>Marriage</em></div>

        <!-- Couple banner -->
        <div class="couple-banner">
            <div class="couple-names">
                <?= htmlspecialchars($rec['groom_name']) ?>
                <span class="couple-amp">&amp;</span>
                <?= htmlspecialchars($rec['bride_name']) ?>
            </div>
            <div class="couple-date">
                Solemnly united in Holy Matrimony on <strong><?= $wed_date ?></strong>
            </div>
        </div>

        <!-- Two-column details -->
        <div class="cert-details">
            <div class="details-grid">

                <!-- Groom column -->
                <div class="d-col" style="padding-right:24px;">
                    <div class="d-section-title"><i class="fas fa-mars" style="font-size:0.65rem;color:#3b82f6;"></i>Groom</div>
                    <div class="d-item"><span class="d-label">Full Name</span><span class="d-value"><?= htmlspecialchars($rec['groom_name']) ?></span></div>
                    <div class="d-item"><span class="d-label">Date of Birth</span><span class="d-value"><?= fd($rec['groom_dob']) ?></span></div>
                    <div class="d-item"><span class="d-label">Address</span><span class="d-value"><?= fv($rec['groom_address']) ?></span></div>
                    <div class="d-item"><span class="d-label">Father</span><span class="d-value"><?= fv($rec['groom_father']) ?></span></div>
                    <div class="d-item"><span class="d-label">Mother</span><span class="d-value"><?= fv($rec['groom_mother']) ?></span></div>
                </div>

                <!-- Bride column -->
                <div class="d-col" style="padding-left:24px;">
                    <div class="d-section-title"><i class="fas fa-venus" style="font-size:0.65rem;color:#ec4899;"></i>Bride</div>
                    <div class="d-item"><span class="d-label">Full Name</span><span class="d-value"><?= htmlspecialchars($rec['bride_name']) ?></span></div>
                    <div class="d-item"><span class="d-label">Date of Birth</span><span class="d-value"><?= fd($rec['bride_dob']) ?></span></div>
                    <div class="d-item"><span class="d-label">Address</span><span class="d-value"><?= fv($rec['bride_address']) ?></span></div>
                    <div class="d-item"><span class="d-label">Father</span><span class="d-value"><?= fv($rec['bride_father']) ?></span></div>
                    <div class="d-item"><span class="d-label">Mother</span><span class="d-value"><?= fv($rec['bride_mother']) ?></span></div>
                </div>

            </div>
        </div>

        <!-- Sponsors -->
        <div class="cert-sponsors">
            <div style="font-family:'DM Sans',sans-serif;font-size:0.62rem;font-weight:700;letter-spacing:0.16em;text-transform:uppercase;color:var(--gold);margin-bottom:10px;">
                Principal Sponsors &amp; Witnesses
            </div>
            <div class="sponsors-grid">
                <div class="sp-item"><span class="d-label">Sponsor (Male)</span><span class="d-value" style="font-family:'EB Garamond',serif;font-size:0.95rem;color:var(--navy);"><?= fv($rec['principal_sponsor_male']) ?></span></div>
                <div class="sp-item"><span class="d-label">Sponsor (Female)</span><span class="d-value" style="font-family:'EB Garamond',serif;font-size:0.95rem;color:var(--navy);"><?= fv($rec['principal_sponsor_female']) ?></span></div>
                <div class="sp-item"><span class="d-label">Witness 1</span><span class="d-value" style="font-family:'EB Garamond',serif;font-size:0.95rem;color:var(--navy);"><?= fv($rec['witness1_name']) ?></span></div>
                <div class="sp-item"><span class="d-label">Witness 2</span><span class="d-value" style="font-family:'EB Garamond',serif;font-size:0.95rem;color:var(--navy);"><?= fv($rec['witness2_name']) ?></span></div>
            </div>
        </div>

        <!-- Declaration -->
        <div class="declaration">
            In witness whereof, we hereby affix our signature and the seal of the Parish,<br>
            at Tugbungan, Zamboanga City, on the <?= date('jS') ?> day of <?= date('F, Y') ?>.
        </div>

        <!-- Signatures -->
        <div class="sigs">
            <div class="sig-block">
                <div style="height:48px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name"><?= $rec['minister'] ? htmlspecialchars($rec['minister']) : 'Parish Priest' ?></div>
                <div class="sig-role">Officiating Minister</div>
            </div>
            <div class="sig-block">
                <div style="height:48px;"></div>
                <div class="sig-line"></div>
                <div class="sig-name">Parish Priest</div>
                <div class="sig-role">Parish Administrator</div>
            </div>
            <div class="sig-block">
                <div style="height:48px;display:flex;align-items:center;justify-content:center;">
                    <div style="width:58px;height:58px;border-radius:50%;border:2px solid var(--gold-lt);display:flex;align-items:center;justify-content:center;color:var(--gold-lt);font-size:1.3rem;">
                        <i class="fas fa-stamp"></i>
                    </div>
                </div>
                <div class="sig-line"></div>
                <div class="sig-name">Parish Secretary</div>
                <div class="sig-role">Records Officer</div>
            </div>
        </div>

        <!-- Footer -->
        <div class="cert-footer">
            <span>Record No.: <strong><?= htmlspecialchars($rec['record_no']) ?></strong> · Series: <strong><?= $year ?></strong></span>
            <span class="cert-footer-center">✦ Our Lady of Peace and Good Voyage Parish ✦</span>
            <span>Printed: <?= $printed_on ?></span>
        </div>

    </div>
</div>

<script>
const params = new URLSearchParams(window.location.search);
if (params.get('print') === '1') {
    window.addEventListener('load', () => setTimeout(() => window.print(), 600));
}
</script>

</body>
</html>