<?php
// church/modules/church_records/shared/download_pdf.php
// Shared PDF generator for all sacramental certificates
// Usage: download_pdf.php?type=baptism&id=12
//
// Requires Dompdf installed via Composer:
//   cd /path/to/church && composer require dompdf/dompdf
//
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Dompdf autoloader ──────────────────────────────────────
$autoload = $root . '/vendor/autoload.php';
if (!file_exists($autoload)) {
    http_response_code(500);
    die('<b>Dompdf not installed.</b><br>Run: <code>composer require dompdf/dompdf</code> in your /church directory.');
}
require_once $autoload;

use Dompdf\Dompdf;
use Dompdf\Options;

// ── Input validation ───────────────────────────────────────
$type = strtolower(trim($_GET['type'] ?? ''));
$id   = (int)($_GET['id'] ?? 0);

$allowed_types = ['baptism', 'communion', 'confirmation', 'wedding', 'funeral'];
if (!in_array($type, $allowed_types) || $id <= 0) {
    http_response_code(400);
    die('Invalid request.');
}

// ── Fetch record ───────────────────────────────────────────
$tables = [
    'baptism'      => 'baptism_records',
    'communion'    => 'communion_records',
    'confirmation' => 'confirmation_records',
    'wedding'      => 'wedding_records',
    'funeral'      => 'funeral_records',
];
$table = $tables[$type];
$stmt  = $conn->prepare("SELECT * FROM `{$table}` WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    http_response_code(404);
    die('Record not found or archived.');
}

// ── Helpers ────────────────────────────────────────────────
function fmt_date($d, $format = 'F j, Y') {
    return $d ? date($format, strtotime($d)) : '—';
}
function fmt_val($v) {
    return $v ? htmlspecialchars($v, ENT_QUOTES) : '<span class="empty">—</span>';
}
function fv($v, $fallback = '—') {
    return $v ? htmlspecialchars($v, ENT_QUOTES) : $fallback;
}

// ── Shared CSS (fonts embedded via base64 or web-safe fallbacks) ──
// Dompdf does not support Google Fonts; we use serif/sans-serif system stacks.
$shared_css = '
    @page {
        size: A4 portrait;
        margin: 16mm 16mm 16mm 16mm;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    body {
        font-family: Georgia, "Times New Roman", serif;
        background: #faf7f0;
        color: #0f2044;
        font-size: 10pt;
        margin: 0; padding: 0;
        width: 100%;
    }

    .cert-page {
        width: 100%;
        background: #faf7f0;
    }

    .cert-outer-border {
        border: 2px solid #e8d99a;
        padding: 10px;
    }
    .cert-inner-border {
        border: 0.5px solid #d4c27a;
        padding: 20px 24px;
    }

    /* ── Letterhead ── */
    .cert-letterhead { text-align: center; margin-bottom: 8px; }
    .cert-diocese {
        font-family: Georgia, serif;
        font-size: 7.5pt;
        letter-spacing: 0.2em;
        text-transform: uppercase;
        color: #b8933a;
        margin-bottom: 5px;
    }
    .cert-parish-name {
        font-family: Georgia, serif;
        font-size: 16pt;
        font-weight: bold;
        color: #0f2044;
        line-height: 1.2;
        margin-bottom: 3px;
    }
    .cert-parish-address {
        font-family: Georgia, serif;
        font-size: 8.5pt;
        color: #6b7280;
        font-style: italic;
    }

    /* ── Divider ── */
    .cert-divider {
        text-align: center;
        margin: 2px 0;
        color: #b8933a;
        font-size: 8pt;
        letter-spacing: 0.3em;
    }
    .cert-divider-line {
        border-top: 1px solid #e8d99a;
        margin: 5px 0;
    }

    /* ── Title ── */
    .cert-title-wrap { text-align: center; margin-bottom: 18px; }
    .cert-subtitle {
        font-family: Georgia, serif;
        font-size: 7pt;
        letter-spacing: 0.25em;
        text-transform: uppercase;
        color: #b8933a;
        margin-bottom: 5px;
    }
    .cert-title {
        font-family: Georgia, serif;
        font-size: 22pt;
        font-weight: bold;
        color: #0f2044;
        line-height: 1;
    }
    .cert-title em { font-style: italic; color: #b8933a; }

    /* ── Intro ── */
    .cert-intro {
        font-family: Georgia, serif;
        font-size: 10pt;
        color: #374151;
        text-align: center;
        line-height: 1.7;
        margin-bottom: 20px;
    }
    .cert-intro .highlight {
        font-family: Georgia, serif;
        font-size: 14pt;
        font-weight: bold;
        color: #0f2044;
        display: block;
        margin: 5px 0;
        border-bottom: 1px solid #e8d99a;
        padding-bottom: 4px;
    }

    /* ── Details box ── */
    .cert-details {
        background: #fdf9f0;
        border: 1px solid #e8d99a;
        padding: 14px 20px;
        margin-bottom: 18px;
    }
    .cert-detail-table { width: 100%; border-collapse: collapse; }
    .cert-detail-table td {
        padding: 6px 10px;
        vertical-align: top;
        border-bottom: 1px solid rgba(184,147,58,0.12);
        width: 50%;
    }
    .cert-detail-table tr:last-child td { border-bottom: none; }
    .cert-detail-label {
        font-family: Arial, sans-serif;
        font-size: 6.5pt;
        font-weight: bold;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: #b8933a;
        display: block;
        margin-bottom: 2px;
    }
    .cert-detail-value {
        font-family: Georgia, serif;
        font-size: 10pt;
        color: #0f2044;
    }
    .empty { color: #c4b89a; font-style: italic; font-size: 9pt; }

    /* ── Claim notice ── */
    .claim-notice {
        background: #fdf9f0;
        border: 1px solid #e8d99a;
        padding: 10px 16px;
        margin-bottom: 18px;
        text-align: center;
        font-family: Georgia, serif;
        font-size: 8.5pt;
        color: #6b5f4e;
        line-height: 1.6;
    }
    .claim-notice strong { color: #9a7a2e; }

    /* ── Declaration ── */
    .cert-declaration {
        font-family: Georgia, serif;
        font-size: 9pt;
        color: #4b5563;
        text-align: center;
        line-height: 1.8;
        margin-bottom: 28px;
        font-style: italic;
    }

    /* ── Signatures ── */
    .cert-sig-table { width: 100%; border-collapse: collapse; margin-top: 8px; }
    .cert-sig-table td { text-align: center; width: 33.33%; padding: 0 10px; vertical-align: bottom; }
    .cert-sig-space { height: 48px; }
    .cert-sig-line { border-top: 1px solid #0f2044; margin: 0 10%; }
    .cert-sig-name {
        font-family: Georgia, serif;
        font-size: 9pt;
        color: #0f2044;
        margin-top: 4px;
    }
    .cert-sig-role {
        font-family: Arial, sans-serif;
        font-size: 6.5pt;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #b8933a;
        margin-top: 2px;
    }
    .seal-circle {
        width: 60px; height: 60px;
        border-radius: 30px;
        border: 2px solid #e8d99a;
        margin: 0 auto;
        display: block;
        text-align: center;
        line-height: 60px;
        color: #e8d99a;
        font-size: 9pt;
    }

    /* ── Footer ── */
    .cert-footer {
        border-top: 1px solid #e8d99a;
        margin-top: 22px;
        padding-top: 10px;
        font-family: Arial, sans-serif;
        font-size: 7pt;
        color: #9ca3af;
    }
    .cert-footer table { width: 100%; border-collapse: collapse; }
    .cert-footer td { padding: 0; }
    .cert-footer .center { text-align: center; font-family: Georgia, serif; color: #b8933a; font-size: 7pt; text-transform: uppercase; letter-spacing: 0.08em; }
    .cert-footer .right  { text-align: right; }

    /* ── Wedding-specific ── */
    .couple-banner {
        background: #fdf9f0;
        border: 1px solid #e8d99a;
        padding: 14px 20px;
        text-align: center;
        margin: 16px 0 14px;
    }
    .couple-names {
        font-family: Georgia, serif;
        font-size: 15pt;
        font-weight: bold;
        color: #0f2044;
        line-height: 1.3;
    }
    .couple-amp { color: #b8933a; font-style: italic; }
    .couple-date { font-family: Georgia, serif; font-size: 9pt; color: #6b7280; font-style: italic; margin-top: 4px; }
    .d-section-title {
        font-family: Arial, sans-serif;
        font-size: 6.5pt;
        font-weight: bold;
        letter-spacing: 0.16em;
        text-transform: uppercase;
        color: #b8933a;
        margin-bottom: 8px;
    }

    /* ── Funeral-specific ── */
    .name-banner {
        background: #1f2937;
        color: #fff;
        padding: 14px 22px;
        text-align: center;
        margin: 16px 0;
        border-radius: 6px;
    }
    .nb-label { font-size: 6.5pt; letter-spacing: 0.2em; text-transform: uppercase; color: rgba(209,213,219,0.6); font-family: Arial, sans-serif; margin-bottom: 4px; }
    .nb-name  { font-family: Georgia, serif; font-size: 15pt; font-weight: bold; color: #fff; }
    .nb-dates { font-size: 8.5pt; color: rgba(209,213,219,0.65); font-style: italic; margin-top: 4px; }
    .section-label {
        font-family: Arial, sans-serif;
        font-size: 7pt;
        font-weight: bold;
        letter-spacing: 0.1em;
        text-transform: uppercase;
        color: #b8933a;
        border-bottom: 1px solid #e8d99a;
        padding-bottom: 4px;
        margin: 14px 0 8px;
    }
    .funeral-declaration {
        background: #fdf9f0;
        border: 1px solid #e8d99a;
        padding: 12px 18px;
        text-align: center;
        font-family: Georgia, serif;
        font-size: 9pt;
        color: #374151;
        line-height: 1.7;
        font-style: italic;
        margin: 14px 0;
    }
';

// ── Build HTML per type ────────────────────────────────────
ob_start();

switch ($type) {

    // ══════════════════════════════════════════
    case 'baptism':
    // ══════════════════════════════════════════
        $baptism_date = fmt_date($rec['date_of_baptism']);
        $birth_date   = fmt_date($rec['date_of_birth']);
        $year         = (int)$rec['series_year'];
        $printed_on   = date('F j, Y');
        $marriage_labels = ['catholic'=>'Catholic','civil'=>'Civil','protestant'=>'Protestant','aglipay'=>'Aglipay','others'=>'Others'];
        $marriage_display = '';
        if (!empty($rec['kind_of_marriage'])) {
            $marriage_display = $marriage_labels[$rec['kind_of_marriage']] ?? ucfirst($rec['kind_of_marriage']);
            if ($rec['kind_of_marriage'] === 'others' && !empty($rec['kind_of_marriage_other'])) {
                $marriage_display .= ' (' . htmlspecialchars($rec['kind_of_marriage_other']) . ')';
            }
        }
        $filename = 'Baptismal_Certificate_' . preg_replace('/[^a-z0-9]/i', '_', $rec['child_name']);
        ?>
        <div class="cert-page">
        <div class="cert-outer-border">
        <div class="cert-inner-border">
            <div class="cert-letterhead">
                <div class="cert-diocese">Diocese of Zamboanga &middot; Parish Records Office</div>
                <div class="cert-parish-name">Our Lady of Peace and Good Voyage Parish</div>
                <div class="cert-parish-address">Tugbungan, Zamboanga City, Philippines</div>
            </div>
            <div class="cert-divider-line"></div>
            <div class="cert-divider">* * *</div>
            <div class="cert-divider-line"></div>
            <div class="cert-title-wrap">
                <div class="cert-subtitle">Sacrament of Initiation</div>
                <div class="cert-title">Certificate of <em>Baptism</em></div>
            </div>
            <div class="cert-intro">
                This is to certify that the following person has been solemnly baptized<br/>
                according to the rites of the Roman Catholic Church on
                <strong><?= $baptism_date ?></strong>
                <span class="highlight"><?= htmlspecialchars($rec['child_name']) ?></span>
                at <?= $rec['place_of_baptism'] ? htmlspecialchars($rec['place_of_baptism']) : 'Our Lady of Peace and Good Voyage Parish' ?>
            </div>
            <div class="cert-details">
                <table class="cert-detail-table">
                    <tr>
                        <td><span class="cert-detail-label">Date of Birth</span><span class="cert-detail-value"><?= $birth_date ?></span></td>
                        <td><span class="cert-detail-label">Place of Birth</span><span class="cert-detail-value"><?= fmt_val($rec['place_of_birth']) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Date of Baptism</span><span class="cert-detail-value"><?= $baptism_date ?></span></td>
                        <td><span class="cert-detail-label">Father's Name</span><span class="cert-detail-value"><?= fmt_val($rec['father_name']) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Mother's Name</span><span class="cert-detail-value"><?= fmt_val($rec['mother_name']) ?></span></td>
                        <td><span class="cert-detail-label">Godfather (Ninong)</span><span class="cert-detail-value"><?= fmt_val($rec['godfather']) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Godmother (Ninang)</span><span class="cert-detail-value"><?= fmt_val($rec['godmother']) ?></span></td>
                        <td><span class="cert-detail-label">Kind of Marriage of Parents</span><span class="cert-detail-value"><?= $marriage_display ?: '<span class="empty">—</span>' ?></span></td>
                    </tr>
                    <tr>
                        <td colspan="2"><span class="cert-detail-label">Parents' Address</span><span class="cert-detail-value"><?= fmt_val($rec['address']) ?></span></td>
                    </tr>
                </table>
            </div>
            <div class="cert-declaration">
                In witness whereof, we hereby affix our signature and the seal of the Parish,<br/>
                at Tugbungan, Zamboanga City, on the <?= date('jS') ?> day of <?= date('F, Y') ?>.
            </div>
            <table class="cert-sig-table">
                <tr>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name"><?= $rec['minister'] ? htmlspecialchars($rec['minister']) : 'Parish Priest' ?></div><div class="cert-sig-role">Officiating Minister</div></td>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Priest</div><div class="cert-sig-role">Parish Administrator</div></td>
                    <td><div class="seal-circle">O</div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Secretary</div><div class="cert-sig-role">Records Officer</div></td>
                </tr>
            </table>
            <div class="cert-footer"><table><tr>
                <td>Record No.: <strong><?= htmlspecialchars($rec['record_no']) ?></strong> &nbsp;&middot;&nbsp; Series: <strong><?= $year ?></strong></td>
                <td class="center">- Our Lady of Peace and Good Voyage Parish -</td>
                <td class="right">Printed: <?= $printed_on ?></td>
            </tr></table></div>
            <div style="text-align:center;margin-top:18px;padding-top:10px;border-top:1px solid #e8d99a;">
                <span style="font-family:Georgia,serif;font-size:7pt;color:#c4b89a;letter-spacing:0.25em;text-transform:uppercase;">
                    Our Lady of Peace and Good Voyage Parish
                </span>
            </div>
        </div></div></div>
        <?php
        break;

    // ══════════════════════════════════════════
    case 'communion':
    // ══════════════════════════════════════════
        $communion_date = fmt_date($rec['date_of_communion']);
        $birth_date     = fmt_date($rec['date_of_birth']);
        $baptism_date   = fmt_date($rec['baptism_date']);
        $year           = (int)$rec['series_year'];
        $printed_on     = date('F j, Y');
        $sponsor        = $rec['sponsor'] ?? null;
        $minister       = $rec['minister'] ?? null;
        $filename = 'Communion_Certificate_' . preg_replace('/[^a-z0-9]/i', '_', $rec['name']);
        ?>
        <div class="cert-page">
        <div class="cert-outer-border">
        <div class="cert-inner-border">
            <div class="cert-letterhead">
                <div class="cert-diocese">Diocese of Zamboanga &middot; Parish Records Office</div>
                <div class="cert-parish-name">Our Lady of Peace and Good Voyage Parish</div>
                <div class="cert-parish-address">Tugbungan, Zamboanga City, Philippines</div>
            </div>
            <div class="cert-divider-line"></div>
            <div class="cert-divider">* * *</div>
            <div class="cert-divider-line"></div>
            <div class="cert-title-wrap">
                <div class="cert-subtitle">Sacrament of the Eucharist</div>
                <div class="cert-title">Certificate of <em>First Holy Communion</em></div>
            </div>
            <div class="cert-intro">
                This is to certify that the following person has received the<br/>
                Sacrament of First Holy Communion for the first time on
                <strong><?= $communion_date ?></strong>
                <span class="highlight"><?= htmlspecialchars($rec['name']) ?></span>
                at Our Lady of Peace and Good Voyage Parish, Tugbungan, Zamboanga City
            </div>
            <div class="cert-details">
                <table class="cert-detail-table">
                    <tr>
                        <td><span class="cert-detail-label">Date of Birth</span><span class="cert-detail-value"><?= $birth_date ?></span></td>
                        <td><span class="cert-detail-label">Date of First Communion</span><span class="cert-detail-value"><?= $communion_date ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Father's Name</span><span class="cert-detail-value"><?= fmt_val($rec['father_name']) ?></span></td>
                        <td><span class="cert-detail-label">Mother's Name</span><span class="cert-detail-value"><?= fmt_val($rec['mother_name']) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Sponsor</span><span class="cert-detail-value"><?= fmt_val($sponsor) ?></span></td>
                        <td><span class="cert-detail-label">Officiating Minister</span><span class="cert-detail-value"><?= fmt_val($minister) ?></span></td>
                    </tr>
                    <?php if (!empty($rec['baptism_date']) || !empty($rec['baptism_parish'])): ?>
                    <tr>
                        <td><span class="cert-detail-label">Date of Baptism</span><span class="cert-detail-value"><?= $baptism_date ?></span></td>
                        <td><span class="cert-detail-label">Baptism Parish</span><span class="cert-detail-value"><?= fmt_val($rec['baptism_parish']) ?></span></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="2"><span class="cert-detail-label">Home Address</span><span class="cert-detail-value"><?= fmt_val($rec['address']) ?></span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="claim-notice">
                <strong>Certificate Claim Policy:</strong>
                This certificate may only be claimed by the person who requested it.
                If the requestor is unavailable, the representative must present an
                <strong>Authorization Letter with notarization from an attorney.</strong>
            </div>
            <div class="cert-declaration">
                In witness whereof, we hereby affix our signature and the seal of the Parish,<br/>
                at Tugbungan, Zamboanga City, on the <?= date('jS') ?> day of <?= date('F, Y') ?>.
            </div>
            <table class="cert-sig-table">
                <tr>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name"><?= $minister ? htmlspecialchars($minister) : 'Parish Priest' ?></div><div class="cert-sig-role">Officiating Minister</div></td>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Priest</div><div class="cert-sig-role">Parish Administrator</div></td>
                    <td><div class="seal-circle">O</div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Secretary</div><div class="cert-sig-role">Records Officer</div></td>
                </tr>
            </table>
            <div class="cert-footer"><table><tr>
                <td>Record No.: <strong><?= htmlspecialchars($rec['record_no']) ?></strong> &nbsp;&middot;&nbsp; Series: <strong><?= $year ?></strong></td>
                <td class="center">- Our Lady of Peace and Good Voyage Parish -</td>
                <td class="right">Printed: <?= $printed_on ?></td>
            </tr></table></div>
            <div style="text-align:center;margin-top:18px;padding-top:10px;border-top:1px solid #e8d99a;">
                <span style="font-family:Georgia,serif;font-size:7pt;color:#c4b89a;letter-spacing:0.25em;text-transform:uppercase;">
                    Our Lady of Peace and Good Voyage Parish
                </span>
            </div>
        </div></div></div>
        <?php
        break;

    // ══════════════════════════════════════════
    case 'confirmation':
    // ══════════════════════════════════════════
        $confirmation_date = fmt_date($rec['date_of_confirmation']);
        $birth_date        = fmt_date($rec['date_of_birth']);
        $baptism_date      = fmt_date($rec['baptism_date']);
        $year              = (int)$rec['series_year'];
        $printed_on        = date('F j, Y');
        $sponsor  = $rec['sponsor'] ?? $rec['sponsor_name'] ?? null;
        $minister = $rec['minister'] ?? $rec['minister_name'] ?? null;
        $filename = 'Confirmation_Certificate_' . preg_replace('/[^a-z0-9]/i', '_', $rec['name']);
        ?>
        <div class="cert-page">
        <div class="cert-outer-border">
        <div class="cert-inner-border">
            <div class="cert-letterhead">
                <div class="cert-diocese">Diocese of Zamboanga &middot; Parish Records Office</div>
                <div class="cert-parish-name">Our Lady of Peace and Good Voyage Parish</div>
                <div class="cert-parish-address">Tugbungan, Zamboanga City, Philippines</div>
            </div>
            <div class="cert-divider-line"></div>
            <div class="cert-divider">* * *</div>
            <div class="cert-divider-line"></div>
            <div class="cert-title-wrap">
                <div class="cert-subtitle">Sacrament of Initiation</div>
                <div class="cert-title">Certificate of <em>Confirmation</em></div>
            </div>
            <div class="cert-intro">
                This is to certify that the following person has been solemnly confirmed<br/>
                according to the rites of the Roman Catholic Church on
                <strong><?= $confirmation_date ?></strong>
                <span class="highlight"><?= htmlspecialchars($rec['name']) ?></span>
                at Our Lady of Peace and Good Voyage Parish, Tugbungan, Zamboanga City
            </div>
            <div class="cert-details">
                <table class="cert-detail-table">
                    <tr>
                        <td><span class="cert-detail-label">Date of Birth</span><span class="cert-detail-value"><?= $birth_date ?></span></td>
                        <td><span class="cert-detail-label">Date of Confirmation</span><span class="cert-detail-value"><?= $confirmation_date ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Father's Name</span><span class="cert-detail-value"><?= fmt_val($rec['father_name']) ?></span></td>
                        <td><span class="cert-detail-label">Mother's Name</span><span class="cert-detail-value"><?= fmt_val($rec['mother_name']) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Sponsor</span><span class="cert-detail-value"><?= fmt_val($sponsor) ?></span></td>
                        <td><span class="cert-detail-label">Officiating Minister</span><span class="cert-detail-value"><?= fmt_val($minister) ?></span></td>
                    </tr>
                    <?php if (!empty($rec['baptism_date']) || !empty($rec['baptism_parish'])): ?>
                    <tr>
                        <td><span class="cert-detail-label">Date of Baptism</span><span class="cert-detail-value"><?= $baptism_date ?></span></td>
                        <td><span class="cert-detail-label">Baptism Parish</span><span class="cert-detail-value"><?= fmt_val($rec['baptism_parish']) ?></span></td>
                    </tr>
                    <?php else: ?>
                    <tr>
                        <td colspan="2"><span class="cert-detail-label">Home Address</span><span class="cert-detail-value"><?= fmt_val($rec['address']) ?></span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="cert-declaration">
                In witness whereof, we hereby affix our signature and the seal of the Parish,<br/>
                at Tugbungan, Zamboanga City, on the <?= date('jS') ?> day of <?= date('F, Y') ?>.
            </div>
            <table class="cert-sig-table">
                <tr>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name"><?= $minister ? htmlspecialchars($minister) : 'Parish Priest / Bishop' ?></div><div class="cert-sig-role">Officiating Minister</div></td>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Priest</div><div class="cert-sig-role">Parish Administrator</div></td>
                    <td><div class="seal-circle">O</div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Secretary</div><div class="cert-sig-role">Records Officer</div></td>
                </tr>
            </table>
            <div class="cert-footer"><table><tr>
                <td>Record No.: <strong><?= htmlspecialchars($rec['record_no']) ?></strong> &nbsp;&middot;&nbsp; Series: <strong><?= $year ?></strong></td>
                <td class="center">- Our Lady of Peace and Good Voyage Parish -</td>
                <td class="right">Printed: <?= $printed_on ?></td>
            </tr></table></div>
            <div style="text-align:center;margin-top:18px;padding-top:10px;border-top:1px solid #e8d99a;">
                <span style="font-family:Georgia,serif;font-size:7pt;color:#c4b89a;letter-spacing:0.25em;text-transform:uppercase;">
                    Our Lady of Peace and Good Voyage Parish
                </span>
            </div>
        </div></div></div>
        <?php
        break;

    // ══════════════════════════════════════════
    case 'wedding':
    // ══════════════════════════════════════════
        $wed_date   = fmt_date($rec['date_of_wedding']);
        $year       = (int)$rec['series_year'];
        $printed_on = date('F j, Y');
        $filename   = 'Marriage_Certificate_' . preg_replace('/[^a-z0-9]/i', '_', $rec['groom_name'] . '_and_' . $rec['bride_name']);
        ?>
        <div class="cert-page">
        <div class="cert-outer-border">
        <div class="cert-inner-border">
            <div class="cert-letterhead">
                <div class="cert-diocese">Diocese of Zamboanga &middot; Parish Records Office</div>
                <div class="cert-parish-name">Our Lady of Peace and Good Voyage Parish</div>
                <div class="cert-parish-address">Tugbungan, Zamboanga City, Philippines</div>
            </div>
            <div class="cert-divider-line"></div>
            <div class="cert-divider">* * *</div>
            <div class="cert-divider-line"></div>
            <div class="cert-title-wrap">
                <div class="cert-subtitle">Sacrament of Matrimony</div>
                <div class="cert-title">Certificate of <em>Marriage</em></div>
            </div>
            <div class="couple-banner">
                <div class="couple-names">
                    <?= htmlspecialchars($rec['groom_name']) ?>
                    <span class="couple-amp"> &amp; </span>
                    <?= htmlspecialchars($rec['bride_name']) ?>
                </div>
                <div class="couple-date">Solemnly united in Holy Matrimony on <strong><?= $wed_date ?></strong></div>
            </div>
            <div class="cert-details">
                <table class="cert-detail-table">
                    <tr>
                        <td style="border-right:1px solid #e8d99a; padding-right:18px;">
                            <div class="d-section-title">Groom</div>
                            <span class="cert-detail-label">Full Name</span><span class="cert-detail-value"><?= htmlspecialchars($rec['groom_name']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Date of Birth</span><span class="cert-detail-value"><?= fmt_date($rec['groom_dob']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Address</span><span class="cert-detail-value"><?= fv($rec['groom_address']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Father</span><span class="cert-detail-value"><?= fv($rec['groom_father']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Mother</span><span class="cert-detail-value"><?= fv($rec['groom_mother']) ?></span>
                        </td>
                        <td style="padding-left:18px;">
                            <div class="d-section-title">Bride</div>
                            <span class="cert-detail-label">Full Name</span><span class="cert-detail-value"><?= htmlspecialchars($rec['bride_name']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Date of Birth</span><span class="cert-detail-value"><?= fmt_date($rec['bride_dob']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Address</span><span class="cert-detail-value"><?= fv($rec['bride_address']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Father</span><span class="cert-detail-value"><?= fv($rec['bride_father']) ?></span><br/>
                            <span class="cert-detail-label" style="margin-top:5px;display:block;">Mother</span><span class="cert-detail-value"><?= fv($rec['bride_mother']) ?></span>
                        </td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Sponsor (Male)</span><span class="cert-detail-value"><?= fv($rec['principal_sponsor_male']) ?></span></td>
                        <td><span class="cert-detail-label">Sponsor (Female)</span><span class="cert-detail-value"><?= fv($rec['principal_sponsor_female']) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Witness 1</span><span class="cert-detail-value"><?= fv($rec['witness1_name']) ?></span></td>
                        <td><span class="cert-detail-label">Witness 2</span><span class="cert-detail-value"><?= fv($rec['witness2_name']) ?></span></td>
                    </tr>
                </table>
            </div>
            <div class="cert-declaration">
                In witness whereof, we hereby affix our signature and the seal of the Parish,<br/>
                at Tugbungan, Zamboanga City, on the <?= date('jS') ?> day of <?= date('F, Y') ?>.
            </div>
            <table class="cert-sig-table">
                <tr>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name"><?= $rec['minister'] ? htmlspecialchars($rec['minister']) : 'Parish Priest' ?></div><div class="cert-sig-role">Officiating Minister</div></td>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Priest</div><div class="cert-sig-role">Parish Administrator</div></td>
                    <td><div class="seal-circle">O</div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Secretary</div><div class="cert-sig-role">Records Officer</div></td>
                </tr>
            </table>
            <div class="cert-footer"><table><tr>
                <td>Record No.: <strong><?= htmlspecialchars($rec['record_no']) ?></strong> &nbsp;&middot;&nbsp; Series: <strong><?= $year ?></strong></td>
                <td class="center">- Our Lady of Peace and Good Voyage Parish -</td>
                <td class="right">Printed: <?= $printed_on ?></td>
            </tr></table></div>
            <div style="text-align:center;margin-top:18px;padding-top:10px;border-top:1px solid #e8d99a;">
                <span style="font-family:Georgia,serif;font-size:7pt;color:#c4b89a;letter-spacing:0.25em;text-transform:uppercase;">
                    Our Lady of Peace and Good Voyage Parish
                </span>
            </div>
        </div></div></div>
        <?php
        break;

    // ══════════════════════════════════════════
    case 'funeral':
    // ══════════════════════════════════════════
        $year     = (int)$rec['series_year'];
        $age_str  = '';
        if ($rec['date_of_birth'] && $rec['date_of_death']) {
            $dob = new DateTime($rec['date_of_birth']);
            $dod = new DateTime($rec['date_of_death']);
            $age_str = $dob->diff($dod)->y . ' years old';
        }
        $minister_name = $rec['minister'] ?? $rec['minister_name'] ?? null;
        $filename = 'Funeral_Certificate_' . preg_replace('/[^a-z0-9]/i', '_', $rec['deceased_name']);
        ?>
        <div class="cert-page">
        <div class="cert-outer-border">
        <div class="cert-inner-border">
            <div class="cert-letterhead">
                <div class="cert-diocese">Diocese of Zamboanga &middot; Parish Records Office</div>
                <div class="cert-parish-name">Our Lady of Peace and Good Voyage Parish</div>
                <div class="cert-parish-address">Tugbungan, Zamboanga City, Philippines</div>
            </div>
            <div class="cert-divider-line"></div>
            <div class="cert-divider">* * *</div>
            <div class="cert-divider-line"></div>
            <div class="cert-title-wrap">
                <div class="cert-subtitle">Sacramental Certificate</div>
                <div class="cert-title">Certificate of <em>Christian Burial</em></div>
            </div>
            <div class="name-banner">
                <div class="nb-label">In Loving Memory of</div>
                <div class="nb-name"><?= htmlspecialchars($rec['deceased_name']) ?></div>
                <?php if ($rec['date_of_birth'] || $rec['date_of_death']): ?>
                <div class="nb-dates">
                    <?php if ($rec['date_of_birth']): ?>* Born <?= fmt_date($rec['date_of_birth']) ?><?php endif; ?>
                    <?php if ($rec['date_of_birth'] && $rec['date_of_death']): ?> &nbsp;&middot;&nbsp; <?php endif; ?>
                    <?php if ($rec['date_of_death']): ?>Died <?= fmt_date($rec['date_of_death']) ?><?php endif; ?>
                    <?php if ($age_str): ?> &nbsp;&middot;&nbsp; <?= htmlspecialchars($age_str) ?><?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
            <div class="section-label">Funeral Details</div>
            <div class="cert-details">
                <table class="cert-detail-table">
                    <tr>
                        <td><span class="cert-detail-label">Date of Funeral Mass</span><span class="cert-detail-value"><?= $rec['date_of_funeral'] ? fmt_date($rec['date_of_funeral']) : '—' ?></span></td>
                        <td><span class="cert-detail-label">Officiating Minister</span><span class="cert-detail-value"><?= fv($minister_name) ?></span></td>
                    </tr>
                    <tr>
                        <td><span class="cert-detail-label">Date of Birth</span><span class="cert-detail-value"><?= $rec['date_of_birth'] ? fmt_date($rec['date_of_birth']) : '—' ?></span></td>
                        <td><span class="cert-detail-label">Date of Death</span><span class="cert-detail-value"><?= $rec['date_of_death'] ? fmt_date($rec['date_of_death']) : '—' ?></span></td>
                    </tr>
                    <?php if ($rec['address']): ?>
                    <tr><td colspan="2"><span class="cert-detail-label">Last Known Address</span><span class="cert-detail-value"><?= htmlspecialchars($rec['address']) ?></span></td></tr>
                    <?php endif; ?>
                    <?php if ($rec['place_of_burial']): ?>
                    <tr><td colspan="2"><span class="cert-detail-label">Place of Burial</span><span class="cert-detail-value"><?= htmlspecialchars($rec['place_of_burial']) ?></span></td></tr>
                    <?php endif; ?>
                    <?php if ($rec['next_of_kin'] || ($rec['next_of_kin_name'] ?? null)): ?>
                    <tr>
                        <td><span class="cert-detail-label">Next of Kin</span><span class="cert-detail-value"><?= fv($rec['next_of_kin']) ?></span></td>
                        <td><span class="cert-detail-label">Contact Person</span><span class="cert-detail-value"><?= fv($rec['next_of_kin_name'] ?? null) ?></span></td>
                    </tr>
                    <?php endif; ?>
                </table>
            </div>
            <div class="funeral-declaration">
                This is to certify that the Sacrament of Christian Burial was duly administered
                to <strong><?= htmlspecialchars($rec['deceased_name']) ?></strong>
                <?php if ($rec['date_of_funeral']): ?>on <strong><?= fmt_date($rec['date_of_funeral']) ?></strong><?php endif; ?>
                in accordance with the rites and ceremonies of the Roman Catholic Church,
                as recorded in the Sacramental Register of this Parish.
            </div>
            <?php if (!empty($rec['remarks'])): ?>
            <div style="border:1px solid #e8d99a;padding:10px 16px;margin-bottom:14px;">
                <span class="cert-detail-label">Notes</span>
                <p style="font-style:italic;font-size:8.5pt;color:#374151;line-height:1.6;"><?= htmlspecialchars($rec['remarks']) ?></p>
            </div>
            <?php endif; ?>
            <table class="cert-sig-table">
                <tr>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name"><?= fv($minister_name, 'Officiating Minister') ?></div><div class="cert-sig-role">Officiating Minister</div></td>
                    <td><div class="seal-circle">O</div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Priest</div><div class="cert-sig-role">Parish Priest</div></td>
                    <td><div class="cert-sig-space"></div><div class="cert-sig-line"></div><div class="cert-sig-name">Parish Secretary</div><div class="cert-sig-role">Parish Secretary</div></td>
                </tr>
            </table>
            <div class="cert-footer"><table><tr>
                <td>Record No.: <strong><?= htmlspecialchars($rec['record_no'] ?? '—') ?></strong> &nbsp;&middot;&nbsp; <?= $year ?> Funeral Series</td>
                <td class="center">- Our Lady of Peace and Good Voyage Parish -</td>
                <td class="right">Printed: <?= date('F j, Y') ?></td>
            </tr></table></div>
            <div style="text-align:center;margin-top:18px;padding-top:10px;border-top:1px solid #e8d99a;">
                <span style="font-family:Georgia,serif;font-size:7pt;color:#c4b89a;letter-spacing:0.25em;text-transform:uppercase;">
                    Our Lady of Peace and Good Voyage Parish
                </span>
            </div>
        </div></div></div>
        <?php
        break;
}

$html_body = ob_get_clean();

// ── Assemble full HTML document ────────────────────────────
$full_html = '<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<style>' . $shared_css . '</style>
</head>
<body>' . $html_body . '</body>
</html>';

// ── Dompdf render ──────────────────────────────────────────
$options = new Options();
$options->set('isHtml5ParserEnabled', true);
$options->set('isRemoteEnabled', false);
$options->set('defaultFont', 'serif');
$options->set('dpi', 96);

$dompdf = new Dompdf($options);
$dompdf->loadHtml($full_html);
// A4 in points at 72dpi: 595 x 842
// We let @page CSS handle margins; pass paper size only
$dompdf->setPaper('A4', 'portrait');
$dompdf->render();

// ── Stream as download ─────────────────────────────────────
$safe_filename = $filename . '.pdf';
$dompdf->stream($safe_filename, ['Attachment' => true]);
exit;