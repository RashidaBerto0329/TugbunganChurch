<?php
// church/modules/finance/reports.php
// Phase 6 — Step 6.5: Financial Reports
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$current_year  = (int)date('Y');
$current_month = (int)date('m');

// ── Filters ──────────────────────────────────────────────────
$sel_year  = (int)($_GET['year']  ?? $current_year);
$sel_month = (int)($_GET['month'] ?? 0);
$view_mode = $_GET['view'] ?? 'year';
if ($sel_month > 0) $view_mode = 'month';

// ── Available years ───────────────────────────────────────────
$all_years = [];
$r = $conn->query("SELECT DISTINCT YEAR(date) y FROM donations UNION SELECT DISTINCT YEAR(date) FROM collections UNION SELECT DISTINCT YEAR(date) FROM payments ORDER BY y DESC");
while ($row = $r->fetch_row()) $all_years[] = (int)$row[0];
if (!in_array($current_year, $all_years)) array_unshift($all_years, $current_year);
if (!in_array($sel_year, $all_years)) $sel_year = $all_years[0];

// ── Helpers ───────────────────────────────────────────────────
function fin_sum(mysqli $conn, string $table, string $amount_col, ?int $year, ?int $month, string $extra_where = ''): float {
    $conds = [];
    if ($year)        $conds[] = "YEAR(date) = {$year}";
    if ($month)       $conds[] = "MONTH(date) = {$month}";
    if ($extra_where) $conds[] = $extra_where;
    $w = $conds ? 'WHERE '.implode(' AND ', $conds) : '';
    return (float)$conn->query("SELECT COALESCE(SUM({$amount_col}),0) FROM `{$table}` {$w}")->fetch_row()[0];
}
function fin_count(mysqli $conn, string $table, ?int $year, ?int $month, string $extra_where = ''): int {
    $conds = [];
    if ($year)        $conds[] = "YEAR(date) = {$year}";
    if ($month)       $conds[] = "MONTH(date) = {$month}";
    if ($extra_where) $conds[] = $extra_where;
    $w = $conds ? 'WHERE '.implode(' AND ', $conds) : '';
    return (int)$conn->query("SELECT COUNT(*) FROM `{$table}` {$w}")->fetch_row()[0];
}

// ── Summary ───────────────────────────────────────────────────
$y = $sel_year;
$m = $view_mode === 'month' ? $sel_month : null;

$donations_cash          = fin_sum($conn,   'donations',   'amount', $y, $m, "donation_type='cash'");
$donations_inkind        = fin_count($conn, 'donations',   $y, $m, "donation_type='in-kind'");
$donations_total_count   = fin_count($conn, 'donations',   $y, $m);
$collections_cash        = fin_sum($conn,   'collections', 'amount', $y, $m, "collection_type='cash'");
$collections_inkind      = fin_count($conn, 'collections', $y, $m, "collection_type='in-kind'");
$collections_total_count = fin_count($conn, 'collections', $y, $m);
$payments_total          = fin_sum($conn,   'payments', 'amount', $y, $m);
$payments_count          = fin_count($conn, 'payments', $y, $m);

$total_inflow  = $donations_cash + $collections_cash;
$total_outflow = $payments_total;
$net_balance   = $total_inflow - $total_outflow;

// ── Monthly breakdown ─────────────────────────────────────────
$monthly_data = [];
for ($mo = 1; $mo <= 12; $mo++) {
    $dc = fin_sum($conn, 'donations',   'amount', $y, $mo, "donation_type='cash'");
    $cc = fin_sum($conn, 'collections', 'amount', $y, $mo, "collection_type='cash'");
    $pt = fin_sum($conn, 'payments',    'amount', $y, $mo);
    $monthly_data[] = [
        'month'       => $mo,
        'label'       => date('M', mktime(0,0,0,$mo,1)),
        'donations'   => $dc,
        'collections' => $cc,
        'payments'    => $pt,
        'inflow'      => $dc + $cc,
        'net'         => ($dc + $cc) - $pt,
    ];
}

// ── Top donors ────────────────────────────────────────────────
$top_donor_q = $conn->prepare("
    SELECT donor_name, SUM(amount) AS total, COUNT(*) AS cnt
    FROM donations WHERE donation_type='cash' AND YEAR(date)=?
    " . ($m ? "AND MONTH(date)={$m}" : "") . "
    GROUP BY donor_name ORDER BY total DESC LIMIT 5");
$top_donor_q->bind_param("i", $y);
$top_donor_q->execute();
$top_donors = $top_donor_q->get_result()->fetch_all(MYSQLI_ASSOC);
$top_donor_q->close();

// ── Top expenses ──────────────────────────────────────────────
$top_exp_q = $conn->prepare("
    SELECT reason, SUM(amount) AS total, COUNT(*) AS cnt
    FROM payments WHERE YEAR(date)=?
    " . ($m ? "AND MONTH(date)={$m}" : "") . "
    GROUP BY reason ORDER BY total DESC LIMIT 5");
$top_exp_q->bind_param("i", $y);
$top_exp_q->execute();
$top_expenses = $top_exp_q->get_result()->fetch_all(MYSQLI_ASSOC);
$top_exp_q->close();

// ── Recent transactions ───────────────────────────────────────
$pw = "YEAR(date)={$y}" . ($m ? " AND MONTH(date)={$m}" : "");
$recent_q = $conn->query("
    (SELECT 'Donation' AS type, donor_name AS name, amount, donation_type AS subtype, date, description AS detail
     FROM donations WHERE {$pw} ORDER BY date DESC LIMIT 5)
    UNION ALL
    (SELECT 'Collection', name, amount, collection_type, date, notes
     FROM collections WHERE {$pw} ORDER BY date DESC LIMIT 5)
    UNION ALL
    (SELECT 'Payment', name, amount, '', date, reason
     FROM payments WHERE {$pw} ORDER BY date DESC LIMIT 5)
    ORDER BY date DESC LIMIT 10");
$recent_txns = $recent_q->fetch_all(MYSQLI_ASSOC);

$period_label = $view_mode === 'month'
    ? date('F Y', mktime(0,0,0,$sel_month,1,$sel_year))
    : "Full Year {$sel_year}";

// Pre-compute ratio
$total_all = $total_inflow + $total_outflow;
$in_pct    = $total_all > 0 ? round(($total_inflow  / $total_all) * 100) : 0;
$out_pct   = 100 - $in_pct;

// YTD column sums
$ytd_d = array_sum(array_column($monthly_data, 'donations'));
$ytd_c = array_sum(array_column($monthly_data, 'collections'));
$ytd_p = array_sum(array_column($monthly_data, 'payments'));
$ytd_net = $ytd_d + $ytd_c - $ytd_p;

$page_title = "Financial Reports";
include $root . '/includes/header.php';
?>
<style>
    /* ── Reset & containment ── */
    *, *::before, *::after { box-sizing: border-box; }

    /* ── Stat cards — identical pattern to index.php ── */
    .rpt-stat-grid {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 22px;
    }
    .rpt-stat {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        padding: 18px 20px;
        position: relative;
        overflow: hidden;
        min-width: 0;
    }
    .rpt-stat::after {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
    }
    .rpt-stat.green::after  { background: linear-gradient(to bottom, #16a34a, #22c55e); }
    .rpt-stat.blue::after   { background: linear-gradient(to bottom, #2563eb, #60a5fa); }
    .rpt-stat.teal::after   { background: linear-gradient(to bottom, #0d9488, #2dd4bf); }
    .rpt-stat.red::after    { background: linear-gradient(to bottom, #dc2626, #f87171); }
    .rpt-stat.navy::after   { background: linear-gradient(to bottom, #0f2044, #1e3a8a); }
    .rpt-stat-label {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.06em;
        text-transform: uppercase;
        color: #9ca3af;
        margin-bottom: 6px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rpt-stat-val {
        font-family: 'Playfair Display', serif;
        font-size: 1.5rem;
        font-weight: 700;
        color: #0f2044;
        line-height: 1;
        margin-bottom: 4px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rpt-stat-sub {
        font-size: 0.7rem;
        color: #9ca3af;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .rpt-stat-icon {
        position: absolute;
        right: 14px; top: 50%;
        transform: translateY(-50%);
        font-size: 2rem;
        opacity: 0.06;
        color: #0f2044;
        pointer-events: none;
    }

    /* ── Main two-column grid — same as fin-grid ── */
    .rpt-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        min-width: 0;
    }
    .rpt-left {
        display: flex;
        flex-direction: column;
        gap: 18px;
        min-width: 0;
        overflow: hidden;
    }
    .rpt-right {
        display: flex;
        flex-direction: column;
        gap: 18px;
        min-width: 0;
    }

    /* ── Cards — identical to index.php ── */
    .card { background: #fff; border: 1px solid #ede8de; border-radius: 14px; overflow: hidden; min-width: 0; }
    .card-head {
        padding: 14px 20px;
        border-bottom: 1px solid #f3ede3;
        background: #fdfcfa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }
    .card-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.9rem;
        font-weight: 600;
        color: #0f2044;
        display: flex;
        align-items: center;
        gap: 8px;
    }
    .card-body { padding: 18px 20px; }

    /* ── Filter bar ── */
    .rpt-filter-bar {
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
        margin-bottom: 20px;
        padding: 12px 16px;
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 12px;
    }
    .rpt-filter-bar label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #374151;
        white-space: nowrap;
    }
    .f-select {
        padding: 7px 10px;
        border: 1px solid #d1cdc4;
        border-radius: 8px;
        font-size: 0.8rem;
        color: #374151;
        background: #fff;
        outline: none;
        cursor: pointer;
        font-family: inherit;
    }
    .f-select:focus { border-color: #b8933a; box-shadow: 0 0 0 3px rgba(184,147,58,.1); }

    /* ── Ratio bar ── */
    .ratio-track { height: 9px; border-radius: 99px; background: #fef2f2; overflow: hidden; margin: 6px 0 14px; }
    .ratio-fill  { height: 100%; background: linear-gradient(90deg,#16a34a,#22c55e); border-radius: 99px; transition: width .4s; }

    /* ── Mini progress bars ── */
    .mini-bar-wrap { height: 5px; background: #f3ede3; border-radius: 99px; overflow: hidden; margin-top: 3px; }
    .mini-bar      { height: 100%; border-radius: 99px; }

    /* ── Table wrapper: only the table scrolls on small screens ── */
    .tbl-scroll { overflow-x: auto; -webkit-overflow-scrolling: touch; }
    .rpt-tbl {
        width: 100%;
        border-collapse: collapse;
        font-size: 0.82rem;
        min-width: 480px;  /* table itself has min-width; container scrolls */
    }
    .rpt-tbl th {
        background: #f5f0e8;
        padding: 8px 14px;
        text-align: left;
        font-size: 0.67rem;
        font-weight: 700;
        letter-spacing: .07em;
        text-transform: uppercase;
        color: #6b5f4e;
        border-bottom: 2px solid #e8e0d0;
        white-space: nowrap;
    }
    .rpt-tbl td { padding: 9px 14px; border-bottom: 1px solid #f3ede3; vertical-align: middle; white-space: nowrap; }
    .rpt-tbl tfoot td { font-weight: 700; background: #f5f0e8; border-top: 2px solid #e8e0d0; border-bottom: none; }
    .rpt-tbl tbody tr:hover { background: #faf8f5; }
    .rpt-tbl .is-current { background: #fdf8ec; }
    .tag-cur { font-size: 0.6rem; background: #fef3c7; color: #d97706; padding: 1px 6px; border-radius: 99px; font-weight: 600; margin-left: 5px; }
    .net-pos { color: #15803d; font-weight: 700; }
    .net-neg { color: #dc2626; font-weight: 700; }

    /* ── Recent transactions — same .rt pattern as index ── */
    .rt { display: flex; align-items: center; gap: 12px; padding: 11px 0; border-bottom: 1px solid #f5f0e8; min-width: 0; }
    .rt:last-child { border-bottom: none; }
    .rt-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.78rem; flex-shrink: 0; }
    .rt-name   { font-size: 0.83rem; font-weight: 600; color: #0f2044; line-height: 1.2; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rt-detail { font-size: 0.72rem; color: #9ca3af; margin-top: 1px; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rt-amount { font-family: 'Playfair Display', serif; font-size: 0.9rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }

    /* ── Rank rows (top donors / expenses) ── */
    .rank-item { padding: 9px 0; border-bottom: 1px solid #f5f0e8; }
    .rank-item:last-child { border-bottom: none; }
    .rank-row  { display: flex; align-items: center; gap: 8px; margin-bottom: 4px; }
    .rank-num  { width: 22px; height: 22px; border-radius: 6px; font-size: 0.68rem; font-weight: 700; display: flex; align-items: center; justify-content: center; flex-shrink: 0; }
    .rank-name { flex: 1; font-size: 0.8rem; font-weight: 600; color: #0f2044; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
    .rank-amt  { font-family: 'Playfair Display', serif; font-size: 0.85rem; font-weight: 700; white-space: nowrap; flex-shrink: 0; }

    /* ── YTD rows (mirrors index.php) ── */
    .ytd-row { display: flex; align-items: center; justify-content: space-between; padding: 10px 0; border-bottom: 1px solid #f5f0e8; font-size: 0.83rem; }
    .ytd-row:last-child { border-bottom: none; }

    /* ── Quick nav links ── */
    .qnav { display: flex; flex-direction: column; gap: 0; }
    .qnav a {
        display: flex; align-items: center; gap: 12px;
        padding: 12px 0; border-bottom: 1px solid #f5f0e8;
        text-decoration: none; min-width: 0;
    }
    .qnav a:last-child { border-bottom: none; }
    .qnav-icon { width: 34px; height: 34px; border-radius: 9px; display: flex; align-items: center; justify-content: center; font-size: 0.82rem; flex-shrink: 0; }
    .qnav-lbl  { font-size: 0.83rem; font-weight: 600; color: #0f2044; flex: 1; }
    .qnav-sub  { font-size: 0.72rem; color: #9ca3af; margin-top: 1px; }

    /* ── Donut chart month view ── */
    .donut-wrap { display: grid; grid-template-columns: 1fr 1fr; gap: 20px; align-items: center; }
    .donut-legend { display: flex; flex-direction: column; gap: 10px; }
    .donut-leg-row { display: flex; align-items: center; gap: 8px; }
    .donut-dot { width: 10px; height: 10px; border-radius: 2px; flex-shrink: 0; }

    /* ── Print button ── */
    .btn-print {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 18px; border-radius: 8px;
        background: #fff; color: #374151;
        border: 1px solid #d1cdc4; cursor: pointer;
        text-decoration: none; font-size: 0.83rem; font-weight: 600;
        transition: all .15s;
    }
    .btn-print:hover { border-color: #0f2044; color: #0f2044; }

    /* ═══════════════════════════════════════════
       RESPONSIVE — mirrors index.php breakpoints
    ═══════════════════════════════════════════ */
    @media (max-width: 1200px) {
        .rpt-stat-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 1000px) {
        .rpt-grid { grid-template-columns: 1fr; }
        .rpt-stat-grid { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 800px) {
        .rpt-stat-grid { grid-template-columns: repeat(2, 1fr); }
        .rpt-stat-val  { font-size: 1.25rem; }
        .rpt-stat      { padding: 14px 14px 14px 18px; }
        .donut-wrap    { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .rpt-stat-grid { grid-template-columns: 1fr 1fr; }
        .rpt-stat-val  { font-size: 1.1rem; }
        .rpt-stat-label { font-size: 0.65rem; }
        .rpt-filter-bar { gap: 7px; }
    }
    @media (max-width: 360px) {
        .rpt-stat-grid { grid-template-columns: 1fr; }
    }

    /* ═══════════════════════════════════════════════════════════
       PRINT TEMPLATE — professional layout, hidden on screen
    ═══════════════════════════════════════════════════════════ */
    #print-report { display: none; }

    @media print {
        @page { size: 8.5in 13in; margin: 12mm 14mm 14mm 14mm; }
        * { visibility: hidden !important; }
        #print-report, #print-report * { visibility: visible !important; }
        #print-report { display: block !important; position: absolute; top: 0; left: 0; width: 100%; background: #fff; }
        body, html { background: #fff !important; margin: 0 !important; padding: 0 !important; -webkit-print-color-adjust: exact; print-color-adjust: exact; }
        .pr-section-head { page-break-after: avoid; }
        .pr-table tr     { page-break-inside: avoid; }
        .pr-sig-block    { page-break-inside: avoid; }
        .pr-two-col      { page-break-inside: avoid; }
    }

    #print-report { font-family: 'Georgia', serif; color: #1a1a1a; font-size: 8.5pt; line-height: 1.5; }
    .pr-page { padding: 0; position: relative; }
    .pr-detail-section { margin-top: 10px; }
    .pr-letterhead { display: flex; align-items: center; gap: 12px; padding-bottom: 5px; }
    .pr-lh-cross   { font-size: 25pt; color: #8b1a1a; line-height: 1; flex-shrink: 0; margin-top: -4px; }
    .pr-lh-text    { display: flex; flex-direction: column; }
    .pr-lh-diocese { font-size: 6.5pt; color: #6b5f4e; letter-spacing: .14em; text-transform: uppercase; margin-bottom: 1px; }
    .pr-lh-parish  { font-family: 'Palatino Linotype','Book Antiqua',Palatino,Georgia,serif; font-size: 14pt; font-weight: bold; color: #0f2044; letter-spacing: .02em; line-height: 1.1; }
    .pr-lh-address { font-size: 6.5pt; color: #6b5f4e; margin-top: 2px; }
    .pr-lh-rule      { border: none; border-top: 2pt solid #0f2044; margin: 2px 0 0; }
    .pr-lh-rule-gold { border: none; border-top: 1pt solid #b8933a; margin: 2px 0 0; }
    .pr-title-block  { text-align: center; margin: 8px 0 10px; }
    .pr-report-type  { font-family: 'Palatino Linotype',Palatino,Georgia,serif; font-size: 12.5pt; font-weight: bold; color: #0f2044; letter-spacing: .2em; text-transform: uppercase; }
    .pr-period-label { font-size: 10pt; color: #b8933a; font-weight: bold; margin: 4px 0 2px; letter-spacing: .04em; }
    .pr-print-date   { font-size: 7pt; color: #9ca3af; font-style: italic; }
    .pr-stat-row     { display: flex; gap: 5px; margin-bottom: 9px; }
    .pr-stat-box     { flex: 1; border: 1pt solid #ddd5c8; border-radius: 3pt; padding: 6px 5px 5px; position: relative; overflow: hidden; background: #fff; }
    .pr-stat-box::before { content: ''; position: absolute; top: 0; left: 0; right: 0; height: 3pt; }
    .pr-stat-green::before { background: #16a34a; } .pr-stat-blue::before { background: #2563eb; }
    .pr-stat-teal::before  { background: #0d9488; } .pr-stat-red::before  { background: #dc2626; }
    .pr-stat-navy::before  { background: #0f2044; }
    .pr-stat-lbl  { font-size: 5.5pt; text-transform: uppercase; letter-spacing: .12em; color: #9ca3af; font-weight: bold; margin-bottom: 2px; font-family: Arial,sans-serif; }
    .pr-stat-amt  { font-family: 'Palatino Linotype',Palatino,Georgia,serif; font-size: 10pt; font-weight: bold; color: #0f2044; line-height: 1.1; }
    .pr-stat-green .pr-stat-amt { color: #15803d; } .pr-stat-blue .pr-stat-amt  { color: #1d4ed8; }
    .pr-stat-teal  .pr-stat-amt { color: #0f766e; } .pr-stat-red  .pr-stat-amt  { color: #dc2626; }
    .pr-stat-note { font-size: 5.5pt; color: #9ca3af; margin-top: 2px; font-family: Arial,sans-serif; }
    .pr-ratio-block   { margin-bottom: 9px; background: #faf8f5; border: 1pt solid #e8e0d0; border-radius: 3pt; padding: 6px 10px; }
    .pr-ratio-title   { font-size: 6pt; font-weight: bold; text-transform: uppercase; letter-spacing: .12em; color: #6b5f4e; margin-bottom: 5px; font-family: Arial,sans-serif; }
    .pr-ratio-bar-wrap { display: flex; height: 7pt; border-radius: 99pt; overflow: hidden; background: #fee2e2; }
    .pr-ratio-bar-in   { background: #16a34a; height: 100%; }
    .pr-ratio-bar-out  { background: #fca5a5; height: 100%; }
    .pr-ratio-labels   { display: flex; justify-content: space-between; margin-top: 3px; font-size: 6.5pt; font-family: Arial,sans-serif; }
    .pr-ratio-lbl-in   { color: #15803d; font-weight: bold; }
    .pr-ratio-lbl-out  { color: #dc2626; font-weight: bold; }
    .pr-two-col { display: flex; gap: 12px; margin-top: 6px; }
    .pr-col     { flex: 1; min-width: 0; }
    .pr-section-head { font-family: 'Palatino Linotype',Palatino,Georgia,serif; font-size: 8pt; font-weight: bold; color: #0f2044; border-bottom: 1.5pt solid #b8933a; padding-bottom: 3px; margin-bottom: 5px; display: flex; align-items: center; gap: 5px; }
    .pr-sh-icon { color: #b8933a; font-size: 8pt; }
    .pr-table   { width: 100%; border-collapse: collapse; font-size: 7pt; font-family: Arial,Helvetica,sans-serif; }
    .pr-table th { background: #f0ece3; padding: 4px 6px; text-align: left; font-size: 5.5pt; letter-spacing: .1em; text-transform: uppercase; color: #5a5040; border-bottom: 1.5pt solid #c8bca6; font-weight: bold; }
    .pr-table td { padding: 5px 7px; border-bottom: .5pt solid #ede8de; vertical-align: middle; }
    .pr-table tfoot td { font-weight: bold; background: #f0ece3; border-top: 1.5pt solid #c8bca6; border-bottom: none; }
    .pr-table tbody tr:nth-child(even) td { background: #fdfcfa; }
    .pr-ta-r { text-align: right !important; } .pr-ta-c { text-align: center !important; }
    .pr-amt-in  { color: #15803d; font-weight: 600; } .pr-amt-col { color: #1d4ed8; font-weight: 600; }
    .pr-amt-out { color: #dc2626; font-weight: 600; } .pr-amt-total { color: #0f2044; font-weight: 700; }
    .pr-amt-net-pos { color: #15803d; font-weight: 700; } .pr-amt-net-neg { color: #dc2626; font-weight: 700; }
    .pr-dash  { color: #bbb; }
    .pr-badge { font-size: 5.5pt; background: #fef3c7; color: #b45309; padding: 1px 5px; border-radius: 99pt; font-weight: bold; border: .5pt solid #fcd34d; }
    .pr-page-header { display: flex; justify-content: space-between; align-items: center; padding-bottom: 5px; border-bottom: 1pt solid #e8e0d0; margin-bottom: 10px; font-size: 7pt; color: #9ca3af; font-family: Arial,sans-serif; }
    .pr-ph-parish   { font-weight: bold; color: #0f2044; font-size: 7.5pt; }
    .pr-sig-block   { margin-top: 20px; page-break-inside: avoid; }
    .pr-sig-intro   { font-size: 7.5pt; color: #5a5040; font-style: italic; text-align: center; margin-bottom: 18px; padding: 0 24px; font-family: Georgia,serif; }
    .pr-sig-row { display: flex; gap: 18px; } .pr-sig-col { flex: 1; text-align: center; }
    .pr-sig-line { border-top: 1pt solid #0f2044; margin: 0 8px 5px; }
    .pr-sig-name { font-size: 8pt; font-weight: bold; color: #0f2044; font-family: Arial,sans-serif; }
    .pr-sig-role { font-size: 6.5pt; color: #9ca3af; font-family: Arial,sans-serif; margin-top: 1px; }
    .pr-footer   { margin-top: 14px; padding-top: 5px; border-top: .5pt solid #e0d8cc; display: flex; justify-content: space-between; font-size: 6.5pt; color: #9ca3af; font-family: Arial,sans-serif; }
</style>

<!-- ── Page header ── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-chart-bar" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>Financial Reports</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/finance/index.php" style="color:#9ca3af;text-decoration:none;">Finance</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Reports</span>
        </p>
    </div>
    <button onclick="window.print()" class="btn-print">
        <i class="fas fa-print"></i> Print Report
    </button>
</div>

<div style="padding:20px 24px 60px;">

    <!-- ── Filter bar ── -->
    <form method="GET" class="rpt-filter-bar">
        <i class="fas fa-filter" style="color:#b8933a;font-size:0.8rem;flex-shrink:0;"></i>
        <label>Period:</label>
        <select name="year" class="f-select" onchange="this.form.submit()">
            <?php foreach ($all_years as $yr): ?>
            <option value="<?= $yr ?>" <?= $yr===$sel_year?'selected':'' ?>><?= $yr ?></option>
            <?php endforeach; ?>
        </select>
        <select name="month" class="f-select" onchange="this.form.submit()">
            <option value="0" <?= $sel_month===0?'selected':'' ?>>Full Year</option>
            <?php for ($mo=1;$mo<=12;$mo++): ?>
            <option value="<?= $mo ?>" <?= $sel_month===$mo?'selected':'' ?>><?= date('F',mktime(0,0,0,$mo,1)) ?></option>
            <?php endfor; ?>
        </select>
        <span style="font-size:0.78rem;color:#9ca3af;flex:1;">Showing: <strong style="color:#374151;"><?= $period_label ?></strong></span>
        <?php if ($sel_year !== $current_year || $sel_month !== 0): ?>
        <a href="?" style="font-size:0.78rem;color:#b8933a;text-decoration:none;white-space:nowrap;flex-shrink:0;">
            <i class="fas fa-rotate-left" style="margin-right:3px;"></i>Reset
        </a>
        <?php endif; ?>
    </form>

    <!-- ── Stat cards ── -->
    <div class="rpt-stat-grid">
        <div class="rpt-stat green">
            <div class="rpt-stat-label">Cash Donations</div>
            <div class="rpt-stat-val" style="color:#15803d;">₱<?= number_format($donations_cash,2) ?></div>
            <div class="rpt-stat-sub"><?= $donations_total_count ?> record<?= $donations_total_count!=1?'s':'' ?><?= $donations_inkind?' · '.$donations_inkind.' in-kind':'' ?></div>
            <div class="rpt-stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
        </div>
        <div class="rpt-stat blue">
            <div class="rpt-stat-label">Collections</div>
            <div class="rpt-stat-val" style="color:#2563eb;">₱<?= number_format($collections_cash,2) ?></div>
            <div class="rpt-stat-sub"><?= $collections_total_count ?> record<?= $collections_total_count!=1?'s':'' ?><?= $collections_inkind?' · '.$collections_inkind.' in-kind':'' ?></div>
            <div class="rpt-stat-icon"><i class="fas fa-church"></i></div>
        </div>
        <div class="rpt-stat teal">
            <div class="rpt-stat-label">Total Inflow</div>
            <div class="rpt-stat-val" style="color:#0f766e;">₱<?= number_format($total_inflow,2) ?></div>
            <div class="rpt-stat-sub">Donations + Collections</div>
            <div class="rpt-stat-icon"><i class="fas fa-arrow-trend-up"></i></div>
        </div>
        <div class="rpt-stat red">
            <div class="rpt-stat-label">Total Payments</div>
            <div class="rpt-stat-val" style="color:#dc2626;">₱<?= number_format($total_outflow,2) ?></div>
            <div class="rpt-stat-sub"><?= $payments_count ?> expense<?= $payments_count!=1?'s':'' ?></div>
            <div class="rpt-stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>
        <div class="rpt-stat <?= $net_balance>=0?'navy':'red' ?>">
            <div class="rpt-stat-label">Net Balance</div>
            <div class="rpt-stat-val" style="color:<?= $net_balance>=0?'#0f2044':'#dc2626' ?>;">
                <?= $net_balance>=0?'':'-' ?>₱<?= number_format(abs($net_balance),2) ?>
            </div>
            <div class="rpt-stat-sub"><?= $net_balance>=0?'Surplus':'Deficit' ?></div>
            <div class="rpt-stat-icon"><i class="fas fa-scale-balanced"></i></div>
        </div>
    </div>

    <!-- ── Main two-column layout ── -->
    <div class="rpt-grid">

        <!-- ════ LEFT COLUMN ════ -->
        <div class="rpt-left">

            <!-- Inflow vs Outflow ratio -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-scale-balanced" style="color:#b8933a;font-size:0.8rem;"></i>Inflow vs Outflow</div>
                    <span style="font-size:0.72rem;color:#9ca3af;"><?= $period_label ?></span>
                </div>
                <div class="card-body">
                    <div style="display:flex;justify-content:space-between;font-size:0.76rem;flex-wrap:wrap;gap:4px;">
                        <span style="color:#15803d;font-weight:600;"><i class="fas fa-arrow-up" style="font-size:.6rem;margin-right:3px;"></i>Inflow <?= $in_pct ?>% &nbsp;·&nbsp; ₱<?= number_format($total_inflow,2) ?></span>
                        <span style="color:#dc2626;font-weight:600;">₱<?= number_format($total_outflow,2) ?> &nbsp;·&nbsp; <?= $out_pct ?>% Outflow <i class="fas fa-arrow-down" style="font-size:.6rem;margin-left:3px;"></i></span>
                    </div>
                    <div class="ratio-track"><div class="ratio-fill" style="width:<?= $in_pct ?>%;"></div></div>
                    <div style="display:flex;flex-direction:column;gap:8px;">
                        <?php foreach ([
                            ['Donations',   '#16a34a', $donations_cash,  $total_inflow],
                            ['Collections', '#2563eb', $collections_cash,$total_inflow],
                            ['Payments',    '#dc2626', $payments_total,  $total_outflow ?: 1],
                        ] as [$rl,$rc,$rv,$rb]):
                            $rp = $rb > 0 ? round(($rv/$rb)*100) : 0; ?>
                        <div>
                            <div style="display:flex;justify-content:space-between;font-size:0.73rem;margin-bottom:3px;">
                                <span style="color:#6b7280;"><?= $rl ?></span>
                                <span style="color:<?= $rc ?>;font-weight:600;">₱<?= number_format($rv,2) ?></span>
                            </div>
                            <div class="mini-bar-wrap"><div class="mini-bar" style="width:<?= $rp ?>%;background:<?= $rc ?>;"></div></div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
            </div>

            <?php if ($view_mode === 'year'): ?>
            <!-- Bar chart -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-chart-bar" style="color:#b8933a;font-size:0.8rem;"></i>Monthly Overview — <?= $sel_year ?></div>
                    <span style="font-size:0.72rem;color:#9ca3af;">Cash transactions only</span>
                </div>
                <div class="card-body">
                    <canvas id="mainChart" height="90"></canvas>
                </div>
            </div>

            <!-- Monthly breakdown table -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-table" style="color:#b8933a;font-size:0.8rem;"></i>Monthly Breakdown — <?= $sel_year ?></div>
                </div>
                <div class="tbl-scroll">
                <table class="rpt-tbl">
                    <thead>
                        <tr><th>Month</th><th>Donations</th><th>Collections</th><th>Total Inflow</th><th>Payments</th><th>Net</th></tr>
                    </thead>
                    <tbody>
                    <?php foreach ($monthly_data as $md):
                        $is_cur = ($sel_year===$current_year && $md['month']===$current_month);
                        $nc     = $md['net'] >= 0 ? 'net-pos' : 'net-neg';
                        $has    = ($md['donations']+$md['collections']+$md['payments']) > 0;
                    ?>
                    <tr <?= $is_cur?'class="is-current"':'' ?>>
                        <td style="font-weight:600;color:#0f2044;">
                            <?= $md['label'] ?><?= $is_cur?'<span class="tag-cur">Current</span>':'' ?>
                        </td>
                        <td style="color:#15803d;"><?= $md['donations']>0?'₱'.number_format($md['donations'],2):'<span style="color:#c4b89a;">—</span>' ?></td>
                        <td style="color:#2563eb;"><?= $md['collections']>0?'₱'.number_format($md['collections'],2):'<span style="color:#c4b89a;">—</span>' ?></td>
                        <td style="font-weight:600;color:#0f2044;"><?= $md['inflow']>0?'₱'.number_format($md['inflow'],2):'<span style="color:#c4b89a;">—</span>' ?></td>
                        <td style="color:#dc2626;"><?= $md['payments']>0?'₱'.number_format($md['payments'],2):'<span style="color:#c4b89a;">—</span>' ?></td>
                        <td class="<?= $nc ?>"><?= $has?(($md['net']>=0?'':'−').'₱'.number_format(abs($md['net']),2)):'<span style="color:#c4b89a;">—</span>' ?></td>
                    </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td>TOTAL</td>
                            <td style="color:#15803d;">₱<?= number_format($ytd_d,2) ?></td>
                            <td style="color:#2563eb;">₱<?= number_format($ytd_c,2) ?></td>
                            <td style="color:#0f2044;">₱<?= number_format($ytd_d+$ytd_c,2) ?></td>
                            <td style="color:#dc2626;">₱<?= number_format($ytd_p,2) ?></td>
                            <td class="<?= $ytd_net>=0?'net-pos':'net-neg' ?>"><?= ($ytd_net>=0?'':'−').'₱'.number_format(abs($ytd_net),2) ?></td>
                        </tr>
                    </tfoot>
                </table>
                </div>
            </div>

            <?php else: ?>
            <!-- Month view: doughnut -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-chart-pie" style="color:#b8933a;font-size:0.8rem;"></i>Breakdown — <?= $period_label ?></div>
                </div>
                <div class="card-body">
                    <div class="donut-wrap">
                        <canvas id="donutChart"></canvas>
                        <div class="donut-legend">
                            <?php foreach ([
                                ['Cash Donations','#16a34a',$donations_cash],
                                ['Collections',  '#2563eb',$collections_cash],
                                ['Payments',     '#dc2626',$payments_total],
                            ] as [$dl,$dc,$dv]): ?>
                            <div class="donut-leg-row">
                                <div class="donut-dot" style="background:<?= $dc ?>;"></div>
                                <span style="flex:1;font-size:0.78rem;color:#374151;"><?= $dl ?></span>
                                <span style="font-family:'Playfair Display',serif;font-size:0.84rem;font-weight:700;color:<?= $dc ?>;">₱<?= number_format($dv,2) ?></span>
                            </div>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>

            <!-- Recent transactions -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-clock-rotate-left" style="color:#b8933a;font-size:0.8rem;"></i>Recent Transactions</div>
                    <span style="font-size:0.72rem;color:#9ca3af;">Latest 10 · <?= $period_label ?></span>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <?php if (empty($recent_txns)): ?>
                    <p style="text-align:center;padding:24px 0;font-size:0.83rem;color:#9ca3af;">No transactions in this period.</p>
                    <?php else: ?>
                    <?php foreach ($recent_txns as $tx):
                        $is_pay   = $tx['type'] === 'Payment';
                        $is_inkind = $tx['subtype'] === 'in-kind';
                        $tc = $is_pay?'#d97706':($tx['type']==='Donation'?'#16a34a':'#2563eb');
                        $tb = $is_pay?'#fef3c7':($tx['type']==='Donation'?'#f0fdf4':'#eff6ff');
                        $ti = $is_pay?'fa-money-bill-wave':($tx['type']==='Donation'?'fa-hand-holding-heart':'fa-church');
                        $ac = $is_pay?'#dc2626':($tx['type']==='Donation'?'#15803d':'#15803d');
                        $as = $is_pay?'-':'+';
                    ?>
                    <div class="rt">
                        <div class="rt-icon" style="background:<?= $tb ?>;color:<?= $tc ?>;"><i class="fas <?= $ti ?>"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="rt-name"><?= htmlspecialchars($tx['name']) ?></div>
                            <div class="rt-detail"><?= $tx['type'] ?><?= $tx['detail']?' · '.htmlspecialchars(mb_strimwidth($tx['detail'],0,38,'…')):'' ?> · <?= date('M j',strtotime($tx['date'])) ?></div>
                        </div>
                        <?php if ($is_inkind): ?>
                        <span style="font-size:0.7rem;font-weight:600;background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:99px;white-space:nowrap;">In-kind</span>
                        <?php else: ?>
                        <div class="rt-amount" style="color:<?= $ac ?>;"><?= $as ?>₱<?= number_format($tx['amount'],2) ?></div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /rpt-left -->

        <!-- ════ RIGHT COLUMN ════ -->
        <div class="rpt-right">

            <!-- Top Donors -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-star" style="color:#b8933a;font-size:0.8rem;"></i>Top Donors</div>
                    <span style="font-size:0.72rem;color:#9ca3af;"><?= $period_label ?></span>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <?php if (empty($top_donors)): ?>
                    <p style="text-align:center;padding:18px 0;font-size:0.8rem;color:#9ca3af;">No donations this period.</p>
                    <?php else:
                        $max_d = (float)($top_donors[0]['total'] ?? 1);
                        $rank_bgs = ['#f0fdf4','#dcfce7','#bbf7d0','#86efac','#4ade80'];
                        foreach ($top_donors as $i => $d):
                            $dp = round(($d['total']/$max_d)*100);
                    ?>
                    <div class="rank-item">
                        <div class="rank-row">
                            <div class="rank-num" style="background:<?= $rank_bgs[$i]??'#f0fdf4' ?>;color:#16a34a;"><?= $i+1 ?></div>
                            <span class="rank-name"><?= htmlspecialchars($d['donor_name']) ?></span>
                            <span class="rank-amt" style="color:#15803d;">₱<?= number_format($d['total'],2) ?></span>
                        </div>
                        <div class="mini-bar-wrap"><div class="mini-bar" style="width:<?= $dp ?>%;background:linear-gradient(90deg,#16a34a,#4ade80);"></div></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Top Expenses -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-receipt" style="color:#b8933a;font-size:0.8rem;"></i>Top Expenses</div>
                    <span style="font-size:0.72rem;color:#9ca3af;"><?= $period_label ?></span>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <?php if (empty($top_expenses)): ?>
                    <p style="text-align:center;padding:18px 0;font-size:0.8rem;color:#9ca3af;">No expenses this period.</p>
                    <?php else:
                        $max_e = (float)($top_expenses[0]['total'] ?? 1);
                        foreach ($top_expenses as $i => $e):
                            $ep = round(($e['total']/$max_e)*100);
                    ?>
                    <div class="rank-item">
                        <div class="rank-row">
                            <div class="rank-num" style="background:#fef2f2;color:#dc2626;"><?= $i+1 ?></div>
                            <span class="rank-name"><?= htmlspecialchars(mb_strimwidth($e['reason'],0,30,'…')) ?></span>
                            <span class="rank-amt" style="color:#dc2626;">₱<?= number_format($e['total'],2) ?></span>
                        </div>
                        <div class="mini-bar-wrap"><div class="mini-bar" style="width:<?= $ep ?>%;background:linear-gradient(90deg,#dc2626,#f87171);"></div></div>
                    </div>
                    <?php endforeach; endif; ?>
                </div>
            </div>

            <!-- Year-to-date summary (mirrors index.php YTD card) -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-calendar" style="color:#b8933a;font-size:0.8rem;"></i><?= $sel_year ?> Year-to-Date</div>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <div class="ytd-row">
                        <span style="color:#6b7280;">Total Inflow</span>
                        <span style="font-weight:700;color:#15803d;font-family:'Playfair Display',serif;">₱<?= number_format($ytd_d+$ytd_c,2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#6b7280;padding-left:12px;font-size:0.78rem;">↳ Donations</span>
                        <span style="color:#374151;">₱<?= number_format($ytd_d,2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#6b7280;padding-left:12px;font-size:0.78rem;">↳ Collections</span>
                        <span style="color:#374151;">₱<?= number_format($ytd_c,2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#6b7280;">Total Outflow</span>
                        <span style="font-weight:700;color:#dc2626;font-family:'Playfair Display',serif;">₱<?= number_format($ytd_p,2) ?></span>
                    </div>
                    <div class="ytd-row" style="border-top:2px solid #f0ebe0;margin-top:4px;padding-top:12px;">
                        <span style="font-weight:700;color:#0f2044;">Net Balance</span>
                        <span style="font-weight:700;font-family:'Playfair Display',serif;font-size:1rem;color:<?= $ytd_net>=0?'#15803d':'#dc2626' ?>;">
                            <?= $ytd_net>=0?'':'-' ?>₱<?= number_format(abs($ytd_net),2) ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Quick nav (mirrors index.php All Records) -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-layer-group" style="color:#b8933a;font-size:0.8rem;"></i>All Records</div>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <div class="qnav">
                    <?php foreach ([
                        ['Donations',   '/church/modules/finance/donations.php',  'fa-hand-holding-heart','#f0fdf4','#16a34a','Cash & in-kind'],
                        ['Collections', '/church/modules/finance/collections.php','fa-church',            '#eff6ff','#2563eb','Mass collections'],
                        ['Payments',    '/church/modules/finance/payments.php',   'fa-money-bill-wave',   '#fef3c7','#d97706','Expenses & payouts'],
                    ] as [$ql,$qu,$qi,$qb,$qc,$qs]): ?>
                    <a href="<?= $qu ?>">
                        <div class="qnav-icon" style="background:<?= $qb ?>;color:<?= $qc ?>;"><i class="fas <?= $qi ?>"></i></div>
                        <div style="flex:1;min-width:0;">
                            <div class="qnav-lbl"><?= $ql ?></div>
                            <div class="qnav-sub"><?= $qs ?></div>
                        </div>
                        <i class="fas fa-chevron-right" style="font-size:0.65rem;color:#c4b89a;flex-shrink:0;"></i>
                    </a>
                    <?php endforeach; ?>
                    </div>
                </div>
            </div>

        </div><!-- /rpt-right -->
    </div><!-- /rpt-grid -->
</div>

<!-- ═══════════════════════════════════════════════════════════
     PRINT TEMPLATE — untouched professional layout
═══════════════════════════════════════════════════════════ -->
<div id="print-report">
    <div class="pr-page">
        <div class="pr-letterhead">
            <div class="pr-lh-cross">✝</div>
            <div class="pr-lh-text">
                <div class="pr-lh-diocese">Diocese of Pagadian &nbsp;·&nbsp; Parish Finance Office</div>
                <div class="pr-lh-parish">Our Lady of Peace and Good Voyage Parish</div>
                <div class="pr-lh-address">Tugbungan, Zamboanga City &nbsp;·&nbsp; Zamboanga del Sur</div>
            </div>
        </div>
        <div class="pr-lh-rule"></div>
        <div class="pr-lh-rule-gold"></div>
        <div class="pr-title-block">
            <div class="pr-report-type">Financial Report</div>
            <div class="pr-period-label"><?= htmlspecialchars($period_label) ?></div>
            <div class="pr-print-date">Prepared on <?= date('F j, Y') ?></div>
        </div>
        <div class="pr-stat-row">
            <div class="pr-stat-box pr-stat-green"><div class="pr-stat-lbl">Cash Donations</div><div class="pr-stat-amt">₱<?= number_format($donations_cash,2) ?></div><div class="pr-stat-note"><?= $donations_total_count ?> record<?= $donations_total_count!=1?'s':'' ?><?= $donations_inkind?' · '.$donations_inkind.' in-kind':'' ?></div></div>
            <div class="pr-stat-box pr-stat-blue"><div class="pr-stat-lbl">Collections</div><div class="pr-stat-amt">₱<?= number_format($collections_cash,2) ?></div><div class="pr-stat-note"><?= $collections_total_count ?> record<?= $collections_total_count!=1?'s':'' ?><?= $collections_inkind?' · '.$collections_inkind.' in-kind':'' ?></div></div>
            <div class="pr-stat-box pr-stat-teal"><div class="pr-stat-lbl">Total Inflow</div><div class="pr-stat-amt">₱<?= number_format($total_inflow,2) ?></div><div class="pr-stat-note">Donations + Collections</div></div>
            <div class="pr-stat-box pr-stat-red"><div class="pr-stat-lbl">Total Payments</div><div class="pr-stat-amt">₱<?= number_format($total_outflow,2) ?></div><div class="pr-stat-note"><?= $payments_count ?> expense<?= $payments_count!=1?'s':'' ?></div></div>
            <div class="pr-stat-box <?= $net_balance>=0?'pr-stat-navy':'pr-stat-red' ?>"><div class="pr-stat-lbl">Net Balance</div><div class="pr-stat-amt"><?= $net_balance>=0?'':'−' ?>₱<?= number_format(abs($net_balance),2) ?></div><div class="pr-stat-note"><?= $net_balance>=0?'Surplus':'Deficit' ?></div></div>
        </div>
        <?php $pa_=$total_inflow+$total_outflow; $ip_=$pa_>0?round(($total_inflow/$pa_)*100):0; $op_=100-$ip_; ?>
        <div class="pr-ratio-block">
            <div class="pr-ratio-title">Inflow vs. Outflow Ratio</div>
            <div class="pr-ratio-bar-wrap"><div class="pr-ratio-bar-in" style="width:<?= $ip_ ?>%;"></div><div class="pr-ratio-bar-out" style="width:<?= $op_ ?>%;"></div></div>
            <div class="pr-ratio-labels"><span class="pr-ratio-lbl-in">&#9632; Inflow <?= $ip_ ?>% (₱<?= number_format($total_inflow,2) ?>)</span><span class="pr-ratio-lbl-out">Outflow <?= $op_ ?>% (₱<?= number_format($total_outflow,2) ?>) &#9632;</span></div>
        </div>
        <div class="pr-two-col">
            <div class="pr-col">
                <div class="pr-section-head"><span class="pr-sh-icon">&#9733;</span> Top Donors — <?= htmlspecialchars($period_label) ?></div>
                <?php if (empty($top_donors)): ?><p style="font-size:7.5pt;color:#9ca3af;font-style:italic;padding:6px 0;">No records found.</p><?php else: ?>
                <table class="pr-table"><thead><tr><th class="pr-ta-c">#</th><th>Donor</th><th class="pr-ta-r">Total</th><th class="pr-ta-c">Cnt</th></tr></thead><tbody>
                <?php foreach ($top_donors as $i=>$d): ?><tr><td class="pr-ta-c" style="color:#b8933a;font-weight:bold;"><?= $i+1 ?></td><td><?= htmlspecialchars($d['donor_name']) ?></td><td class="pr-ta-r pr-amt-in">₱<?= number_format($d['total'],2) ?></td><td class="pr-ta-c" style="color:#6b7280;"><?= $d['cnt'] ?></td></tr><?php endforeach; ?>
                </tbody></table><?php endif; ?>
            </div>
            <div class="pr-col">
                <div class="pr-section-head"><span class="pr-sh-icon">&#9670;</span> Top Expenses — <?= htmlspecialchars($period_label) ?></div>
                <?php if (empty($top_expenses)): ?><p style="font-size:7.5pt;color:#9ca3af;font-style:italic;padding:6px 0;">No records found.</p><?php else: ?>
                <table class="pr-table"><thead><tr><th class="pr-ta-c">#</th><th>Category</th><th class="pr-ta-r">Total</th><th class="pr-ta-c">Cnt</th></tr></thead><tbody>
                <?php foreach ($top_expenses as $i=>$e): ?><tr><td class="pr-ta-c" style="color:#dc2626;font-weight:bold;"><?= $i+1 ?></td><td><?= htmlspecialchars($e['reason']) ?></td><td class="pr-ta-r pr-amt-out">₱<?= number_format($e['total'],2) ?></td><td class="pr-ta-c" style="color:#6b7280;"><?= $e['cnt'] ?></td></tr><?php endforeach; ?>
                </tbody></table><?php endif; ?>
            </div>
        </div>
        <div class="pr-detail-section">
            <div class="pr-page-header"><span class="pr-ph-parish">Our Lady of Peace and Good Voyage Parish</span><span>Financial Report — <?= htmlspecialchars($period_label) ?></span></div>
            <?php if ($view_mode === 'year'): ?>
            <div class="pr-section-head" style="margin-bottom:7px;"><span class="pr-sh-icon">&#9638;</span> Monthly Financial Breakdown — <?= $sel_year ?></div>
            <table class="pr-table">
                <thead><tr><th>Month</th><th class="pr-ta-r">Donations</th><th class="pr-ta-r">Collections</th><th class="pr-ta-r">Total Inflow</th><th class="pr-ta-r">Payments</th><th class="pr-ta-r">Net Balance</th></tr></thead>
                <tbody>
                <?php $y2d=0;$y2c=0;$y2p=0; foreach ($monthly_data as $md): $y2d+=$md['donations'];$y2c+=$md['collections'];$y2p+=$md['payments']; $hd=($md['donations']+$md['collections']+$md['payments'])>0; $np=$md['net']; ?>
                <tr><td style="font-weight:600;"><?= $md['label'] ?></td>
                <td class="pr-ta-r <?= $md['donations']>0?'pr-amt-in':'' ?>"><?= $md['donations']>0?'₱'.number_format($md['donations'],2):'<span class="pr-dash">—</span>' ?></td>
                <td class="pr-ta-r <?= $md['collections']>0?'pr-amt-col':'' ?>"><?= $md['collections']>0?'₱'.number_format($md['collections'],2):'<span class="pr-dash">—</span>' ?></td>
                <td class="pr-ta-r pr-amt-total"><?= $md['inflow']>0?'₱'.number_format($md['inflow'],2):'<span class="pr-dash">—</span>' ?></td>
                <td class="pr-ta-r <?= $md['payments']>0?'pr-amt-out':'' ?>"><?= $md['payments']>0?'₱'.number_format($md['payments'],2):'<span class="pr-dash">—</span>' ?></td>
                <td class="pr-ta-r <?= $hd?($np>=0?'pr-amt-net-pos':'pr-amt-net-neg'):'' ?>"><?= $hd?(($np>=0?'':'−').'₱'.number_format(abs($np),2)):'<span class="pr-dash">—</span>' ?></td></tr>
                <?php endforeach; ?>
                </tbody>
                <tfoot><tr><td>ANNUAL TOTAL</td><td class="pr-ta-r pr-amt-in">₱<?= number_format($y2d,2) ?></td><td class="pr-ta-r pr-amt-col">₱<?= number_format($y2c,2) ?></td><td class="pr-ta-r pr-amt-total">₱<?= number_format($y2d+$y2c,2) ?></td><td class="pr-ta-r pr-amt-out">₱<?= number_format($y2p,2) ?></td>
                <?php $yn2=$y2d+$y2c-$y2p; ?><td class="pr-ta-r <?= $yn2>=0?'pr-amt-net-pos':'pr-amt-net-neg' ?>"><?= ($yn2>=0?'':'−').'₱'.number_format(abs($yn2),2) ?></td></tr></tfoot>
            </table>
            <?php endif; ?>
            <div class="pr-section-head" style="margin:12px 0 7px;"><span class="pr-sh-icon">&#8635;</span> Recent Transactions — <?= htmlspecialchars($period_label) ?></div>
            <?php if (empty($recent_txns)): ?><p style="font-size:7.5pt;color:#9ca3af;font-style:italic;">No transactions found.</p><?php else: ?>
            <table class="pr-table">
                <thead><tr><th>Date</th><th>Type</th><th>Name</th><th>Detail</th><th class="pr-ta-c">Sub</th><th class="pr-ta-r">Amount</th></tr></thead>
                <tbody>
                <?php foreach ($recent_txns as $tx): $ip=$tx['type']==='Payment'; $ik=$tx['subtype']==='in-kind'; $ac=$ip?'pr-amt-out':($tx['type']==='Donation'?'pr-amt-in':'pr-amt-col'); ?>
                <tr><td style="white-space:nowrap;"><?= date('M j, Y',strtotime($tx['date'])) ?></td><td><?= htmlspecialchars($tx['type']) ?></td><td style="font-weight:600;"><?= htmlspecialchars($tx['name']) ?></td>
                <td style="color:#6b7280;"><?= $tx['detail']?htmlspecialchars(mb_strimwidth($tx['detail'],0,40,'…')):'<span class="pr-dash">—</span>' ?></td>
                <td class="pr-ta-c"><?= $ik?'<span class="pr-badge">In-kind</span>':($tx['subtype']?htmlspecialchars($tx['subtype']):'<span class="pr-dash">—</span>') ?></td>
                <td class="pr-ta-r <?= $ik?'':$ac ?>"><?= $ik?'<span class="pr-dash">—</span>':(($ip?'−':'+').'₱'.number_format($tx['amount'],2)) ?></td></tr>
                <?php endforeach; ?>
                </tbody>
            </table>
            <?php endif; ?>
            <div class="pr-sig-block">
                <div class="pr-sig-intro">We hereby certify that the above financial data is true and correct based on the official books and records of the Our Lady of Peace and Good Voyage Parish Finance Office for the period of <strong><?= htmlspecialchars($period_label) ?></strong>.</div>
                <div class="pr-sig-row">
                    <div class="pr-sig-col"><div class="pr-sig-line"></div><div class="pr-sig-name">Parish Finance Officer</div><div class="pr-sig-role">Signature over Printed Name &amp; Date</div></div>
                    <div class="pr-sig-col"><div class="pr-sig-line"></div><div class="pr-sig-name">Parish Priest / Parish Administrator</div><div class="pr-sig-role">Signature over Printed Name &amp; Date</div></div>
                    <div class="pr-sig-col"><div class="pr-sig-line"></div><div class="pr-sig-name">Parish Pastoral Council Treasurer</div><div class="pr-sig-role">Signature over Printed Name &amp; Date</div></div>
                </div>
            </div>
            <div class="pr-footer">
                <span>&copy; <?= date('Y') ?> Our Lady of Peace and Good Voyage Parish — Tugbungan, Zamboanga City</span>
                <span>Parish Records Management System &nbsp;|&nbsp; Printed <?= date('F j, Y \a\t g:i A') ?></span>
            </div>
        </div>
    </div>
</div>

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#9ca3af';

<?php if ($view_mode === 'year'): ?>
const mData = <?= json_encode($monthly_data) ?>;
let mainChart = null;

function buildMainChart() {
    const canvas = document.getElementById('mainChart');
    if (!canvas) return;
    if (mainChart) { mainChart.destroy(); mainChart = null; }
    mainChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: mData.map(d => d.label),
            datasets: [
                { label: 'Donations',   data: mData.map(d => d.donations),   backgroundColor: 'rgba(22,163,74,0.75)',  borderRadius: 4 },
                { label: 'Collections', data: mData.map(d => d.collections), backgroundColor: 'rgba(37,99,235,0.75)', borderRadius: 4 },
                { label: 'Payments',    data: mData.map(d => d.payments),    backgroundColor: 'rgba(220,38,38,0.65)', borderRadius: 4 },
            ]
        },
        options: {
            responsive: true,
            maintainAspectRatio: true,
            animation: { duration: 0 },
            plugins: {
                legend: { position: 'bottom', labels: { font: { family: 'DM Sans', size: 11 }, boxWidth: 10, padding: 14 } },
                tooltip: { callbacks: { label: ctx => ' ₱' + ctx.raw.toLocaleString('en-PH', { minimumFractionDigits: 2 }) } }
            },
            scales: {
                x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 10 } } },
                y: { grid: { color: '#f3ede3' }, ticks: { font: { family: 'DM Sans', size: 10 }, callback: v => '₱' + v.toLocaleString() } }
            }
        }
    });
}

buildMainChart();
let resizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(buildMainChart, 150);
});

<?php else: ?>
const dcEl = document.getElementById('donutChart');
if (dcEl) new Chart(dcEl.getContext('2d'), {
    type: 'doughnut',
    data: {
        labels: ['Donations','Collections','Payments'],
        datasets: [{ data:[<?= $donations_cash ?>,<?= $collections_cash ?>,<?= $payments_total ?>], backgroundColor:['#16a34a','#2563eb','#dc2626'], borderWidth:2, borderColor:'#fff' }]
    },
    options: { cutout:'65%', responsive:true, plugins:{ legend:{display:false}, tooltip:{callbacks:{label:ctx=>' ₱'+ctx.raw.toLocaleString('en-PH',{minimumFractionDigits:2})}} } }
});
<?php endif; ?>
</script>
<?php include $root . '/includes/footer.php'; ?>