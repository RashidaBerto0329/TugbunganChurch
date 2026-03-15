<?php
// church/modules/finance/index.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$current_year  = (int)date('Y');
$current_month = (int)date('m');

// ── This month ────────────────────────────────────────────────
$r = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN donation_type='cash' THEN amount ELSE 0 END),0) as cash,
    COALESCE(SUM(CASE WHEN donation_type='sacramental_fee' THEN amount ELSE 0 END),0) as sacr,
    COUNT(CASE WHEN donation_type='in-kind' THEN 1 END) as inkind
    FROM donations WHERE MONTH(date)={$current_month} AND YEAR(date)={$current_year}")->fetch_assoc();
$donations_month_cash   = (float)$r['cash'];
$donations_month_sacr   = (float)$r['sacr'];
$donations_month_inkind = (int)$r['inkind'];
$donations_month        = $donations_month_cash + $donations_month_sacr;

$r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE MONTH(date)={$current_month} AND YEAR(date)={$current_year} AND collection_type='cash'");
$collections_month = (float)$r->fetch_row()[0];

$r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(date)={$current_month} AND YEAR(date)={$current_year}");
$payments_month = (float)$r->fetch_row()[0];

$net_month = $donations_month + $collections_month - $payments_month;

// ── Year-to-date ──────────────────────────────────────────────
$r = $conn->query("SELECT
    COALESCE(SUM(CASE WHEN donation_type='cash' THEN amount ELSE 0 END),0) as cash,
    COALESCE(SUM(CASE WHEN donation_type='sacramental_fee' THEN amount ELSE 0 END),0) as sacr
    FROM donations WHERE YEAR(date)={$current_year}")->fetch_assoc();
$donations_ytd_cash = (float)$r['cash'];
$donations_ytd_sacr = (float)$r['sacr'];
$donations_ytd      = $donations_ytd_cash + $donations_ytd_sacr;

$r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE YEAR(date)={$current_year} AND collection_type='cash'");
$collections_ytd = (float)$r->fetch_row()[0];

$r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE YEAR(date)={$current_year}");
$payments_ytd = (float)$r->fetch_row()[0];

$net_ytd = $donations_ytd + $collections_ytd - $payments_ytd;

// In-kind counts this month
$r = $conn->query("SELECT COUNT(*) FROM collections WHERE MONTH(date)={$current_month} AND YEAR(date)={$current_year} AND collection_type='in-kind'");
$inkind_collections = (int)$r->fetch_row()[0];

// ── 6-month chart data ─────────────────────────────────────────
$monthly = [];
for ($i = 5; $i >= 0; $i--) {
    $ts  = mktime(0,0,0, $current_month - $i, 1, $current_year);
    $m   = (int)date('m', $ts);
    $y   = (int)date('Y', $ts);
    $lbl = date('M', $ts);

    $r = $conn->query("SELECT
        COALESCE(SUM(CASE WHEN donation_type='cash' THEN amount ELSE 0 END),0),
        COALESCE(SUM(CASE WHEN donation_type='sacramental_fee' THEN amount ELSE 0 END),0)
        FROM donations WHERE MONTH(date)={$m} AND YEAR(date)={$y}")->fetch_row();
    $d_cash = (float)$r[0]; $d_sacr = (float)$r[1];

    $r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM collections WHERE MONTH(date)={$m} AND YEAR(date)={$y} AND collection_type='cash'");
    $c = (float)$r->fetch_row()[0];

    $r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM payments WHERE MONTH(date)={$m} AND YEAR(date)={$y}");
    $p = (float)$r->fetch_row()[0];

    $monthly[] = ['label'=>$lbl, 'donations'=>$d_cash, 'sacramental'=>$d_sacr, 'collections'=>$c, 'payments'=>$p];
}

// ── Recent transactions ───────────────────────────────────────
$recent = [];
$rd = $conn->query("SELECT 'donation' AS type, donor_name AS name, amount, donation_type AS subtype, date, description AS detail FROM donations ORDER BY created_at DESC LIMIT 4");
while ($row = $rd->fetch_assoc()) $recent[] = $row;

$rc = $conn->query("SELECT 'collection' AS type, name, amount, collection_type AS subtype, date, notes AS detail FROM collections ORDER BY created_at DESC LIMIT 4");
while ($row = $rc->fetch_assoc()) $recent[] = $row;

$rp = $conn->query("SELECT 'payment' AS type, name, amount, '' AS subtype, date, reason AS detail FROM payments ORDER BY created_at DESC LIMIT 4");
while ($row = $rp->fetch_assoc()) $recent[] = $row;

usort($recent, fn($a,$b) => strtotime($b['date']) - strtotime($a['date']));
$recent = array_slice($recent, 0, 8);

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$page_title = "Finance";
include $root . '/includes/header.php';
?>
<style>
    /* ── Stat cards ── */
    .fin-stat-grid-5 {
        display: grid;
        grid-template-columns: repeat(5, 1fr);
        gap: 16px;
        margin-bottom: 22px;
    }
    .fin-stat {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        padding: 18px 20px;
        position: relative;
        overflow: hidden;
        min-width: 0;
    }
    .fin-stat::after {
        content: '';
        position: absolute;
        top: 0; left: 0;
        width: 4px; height: 100%;
    }
    .fin-stat.green::after  { background: linear-gradient(to bottom, #16a34a, #22c55e); }
    .fin-stat.blue::after   { background: linear-gradient(to bottom, #2563eb, #60a5fa); }
    .fin-stat.amber::after  { background: linear-gradient(to bottom, #d97706, #f59e0b); }
    .fin-stat.navy::after   { background: linear-gradient(to bottom, #0f2044, #1e3a8a); }
    .fin-stat.purple::after { background: linear-gradient(to bottom, #7c3aed, #a78bfa); }

    .fin-stat-label {
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
    .fin-stat-val {
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
    .fin-stat-sub {
        font-size: 0.7rem;
        color: #9ca3af;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
    }
    .fin-stat-icon {
        position: absolute;
        right: 14px; top: 50%;
        transform: translateY(-50%);
        font-size: 2rem;
        opacity: 0.06;
        color: #0f2044;
        pointer-events: none;
    }

    /* ── Main grid ── */
    .fin-grid {
        display: grid;
        grid-template-columns: 1fr 360px;
        gap: 20px;
        min-width: 0;
    }
    .fin-left-col {
        display: flex;
        flex-direction: column;
        gap: 18px;
        min-width: 0;
        overflow: hidden;
    }
    .fin-right-col {
        display: flex;
        flex-direction: column;
        gap: 18px;
        min-width: 0;
    }

    /* ── Cards ── */
    .card { background: #fff; border: 1px solid #ede8de; border-radius: 14px; overflow: hidden; min-width: 0; }
    .card-head {
        padding: 14px 20px;
        border-bottom: 1px solid #f3ede3;
        background: #fdfcfa;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
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

    /* ── Quick links ── */
    .quick-links { display: grid; grid-template-columns: 1fr 1fr; gap: 10px; }
    .ql {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 13px 15px;
        border-radius: 10px;
        text-decoration: none;
        border: 1px solid #ede8de;
        transition: all 0.18s;
        background: #fff;
        min-width: 0;
    }
    .ql:hover { border-color: currentColor; transform: translateY(-1px); box-shadow: 0 4px 12px rgba(0,0,0,0.06); }
    .ql-icon {
        width: 36px; height: 36px;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.9rem;
        flex-shrink: 0;
    }
    .ql-label { font-size: 0.82rem; font-weight: 600; color: #0f2044; line-height: 1.2; }
    .ql-sub   { font-size: 0.7rem; color: #9ca3af; margin-top: 1px; }

    /* ── Recent transactions ── */
    .recent-list { display: flex; flex-direction: column; }
    .rt {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 11px 0;
        border-bottom: 1px solid #f5f0e8;
        min-width: 0;
    }
    .rt:last-child { border-bottom: none; }
    .rt-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.78rem;
        flex-shrink: 0;
    }
    .rt-name   { font-size: 0.83rem; font-weight: 600; color: #0f2044; line-height: 1.2; }
    .rt-detail { font-size: 0.72rem; color: #9ca3af; margin-top: 1px; }
    .rt-amount { font-family: 'Playfair Display', serif; font-size: 0.92rem; font-weight: 700; white-space: nowrap; }

    /* ── YTD rows ── */
    .ytd-row {
        display: flex;
        align-items: center;
        justify-content: space-between;
        padding: 10px 0;
        border-bottom: 1px solid #f5f0e8;
        font-size: 0.83rem;
    }
    .ytd-row:last-child { border-bottom: none; }

    /* ── Alerts ── */
    .alert {
        display: flex; align-items: center; gap: 10px;
        padding: 12px 16px;
        border-radius: 10px;
        font-size: 0.83rem;
        margin-bottom: 18px;
    }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

    /* ── Responsive ── */
    @media (max-width: 1200px) {
        .fin-stat-grid-5 { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 1000px) {
        .fin-grid { grid-template-columns: 1fr; }
        .fin-stat-grid-5 { grid-template-columns: repeat(3, 1fr); }
    }
    @media (max-width: 800px) {
        .fin-stat-grid-5 { grid-template-columns: repeat(2, 1fr); }
        .fin-stat-val { font-size: 1.25rem; }
        .fin-stat { padding: 14px 14px 14px 18px; }
    }
    @media (max-width: 560px) {
        .quick-links { grid-template-columns: 1fr; }
    }
    @media (max-width: 480px) {
        .fin-stat-grid-5 { grid-template-columns: 1fr 1fr; }
        .fin-stat-val { font-size: 1.1rem; }
        .fin-stat-label { font-size: 0.65rem; }
    }
    @media (max-width: 360px) {
        .fin-stat-grid-5 { grid-template-columns: 1fr; }
    }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-coins" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>Finance</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Finance</span>
        </p>
    </div>
    <a href="/church/modules/finance/reports.php"
       style="display:inline-flex;align-items:center;gap:7px;padding:9px 18px;border-radius:8px;
              background:#0f2044;color:#fff;text-decoration:none;font-size:0.83rem;font-weight:600;">
        <i class="fas fa-chart-bar"></i> View Reports
    </a>
</div>

<div style="padding:20px 24px 60px;">

    <?php if ($success): ?>
    <div class="alert alert-success"><i class="fas fa-circle-check"></i><?= htmlspecialchars($success) ?></div>
    <?php endif; ?>
    <?php if ($error): ?>
    <div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div>
    <?php endif; ?>

    <!-- ── Stat cards ── -->
    <div class="fin-stat-grid-5">

        <div class="fin-stat green">
            <div class="fin-stat-label">Donations — <?= date('M Y') ?></div>
            <div class="fin-stat-val">₱<?= number_format($donations_month_cash, 2) ?></div>
            <div class="fin-stat-sub">YTD: ₱<?= number_format($donations_ytd_cash, 2) ?><?= $donations_month_inkind ? " · {$donations_month_inkind} in-kind" : '' ?></div>
            <div class="fin-stat-icon"><i class="fas fa-hand-holding-heart"></i></div>
        </div>

        <div class="fin-stat purple">
            <div class="fin-stat-label">Sacramental — <?= date('M Y') ?></div>
            <div class="fin-stat-val" style="color:#7c3aed;">₱<?= number_format($donations_month_sacr, 2) ?></div>
            <div class="fin-stat-sub">YTD: ₱<?= number_format($donations_ytd_sacr, 2) ?></div>
            <div class="fin-stat-icon"><i class="fas fa-cross"></i></div>
        </div>

        <div class="fin-stat blue">
            <div class="fin-stat-label">Collections — <?= date('M Y') ?></div>
            <div class="fin-stat-val">₱<?= number_format($collections_month, 2) ?></div>
            <div class="fin-stat-sub">YTD: ₱<?= number_format($collections_ytd, 2) ?><?= $inkind_collections ? " · {$inkind_collections} in-kind" : '' ?></div>
            <div class="fin-stat-icon"><i class="fas fa-church"></i></div>
        </div>

        <div class="fin-stat amber">
            <div class="fin-stat-label">Payments — <?= date('M Y') ?></div>
            <div class="fin-stat-val">₱<?= number_format($payments_month, 2) ?></div>
            <div class="fin-stat-sub">YTD: ₱<?= number_format($payments_ytd, 2) ?></div>
            <div class="fin-stat-icon"><i class="fas fa-money-bill-wave"></i></div>
        </div>

        <div class="fin-stat <?= $net_month >= 0 ? 'navy' : 'amber' ?>">
            <div class="fin-stat-label">Net Balance — <?= date('M Y') ?></div>
            <div class="fin-stat-val" style="color:<?= $net_month >= 0 ? '#0f2044' : '#dc2626' ?>">
                <?= $net_month >= 0 ? '' : '-' ?>₱<?= number_format(abs($net_month), 2) ?>
            </div>
            <div class="fin-stat-sub">YTD: <?= $net_ytd >= 0 ? '' : '-' ?>₱<?= number_format(abs($net_ytd), 2) ?></div>
            <div class="fin-stat-icon"><i class="fas fa-scale-balanced"></i></div>
        </div>

    </div>

    <!-- ── Main grid ── -->
    <div class="fin-grid">

        <!-- LEFT COLUMN -->
        <div class="fin-left-col">

            <!-- Chart -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title">
                        <i class="fas fa-chart-bar" style="color:#b8933a;font-size:0.8rem;"></i>6-Month Overview
                    </div>
                    <span style="font-size:0.72rem;color:#9ca3af;">Cash transactions only</span>
                </div>
                <div class="card-body">
                    <canvas id="finChart" height="90"></canvas>
                </div>
            </div>

            <!-- Recent transactions -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title">
                        <i class="fas fa-clock-rotate-left" style="color:#b8933a;font-size:0.8rem;"></i>Recent Transactions
                    </div>
                    <a href="/church/modules/finance/reports.php" style="font-size:0.75rem;color:#b8933a;text-decoration:none;">View all →</a>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <?php if (empty($recent)): ?>
                    <p style="text-align:center;padding:24px 0;font-size:0.83rem;color:#9ca3af;">No transactions yet.</p>
                    <?php else: ?>
                    <div class="recent-list">
                    <?php foreach ($recent as $tx):
                        $is_payment = $tx['type'] === 'payment';
                        $is_sacr    = $tx['subtype'] === 'sacramental_fee';
                        $is_inkind  = $tx['subtype'] === 'in-kind';
                        if ($is_payment)            { $icon_bg='#fef3c7'; $icon_color='#d97706'; $icon_name='fa-money-bill-wave'; $amt_color='#dc2626'; $amt_sign='-'; }
                        elseif ($is_sacr)           { $icon_bg='#f5f3ff'; $icon_color='#7c3aed'; $icon_name='fa-cross';           $amt_color='#7c3aed'; $amt_sign='+'; }
                        elseif ($tx['type']==='donation') { $icon_bg='#f0fdf4'; $icon_color='#16a34a'; $icon_name='fa-hand-holding-heart'; $amt_color='#15803d'; $amt_sign='+'; }
                        else                        { $icon_bg='#eff6ff'; $icon_color='#2563eb'; $icon_name='fa-church';          $amt_color='#15803d'; $amt_sign='+'; }
                    ?>
                    <div class="rt">
                        <div class="rt-icon" style="background:<?= $icon_bg ?>;color:<?= $icon_color ?>;">
                            <i class="fas <?= $icon_name ?>"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div class="rt-name"><?= htmlspecialchars($tx['name']) ?></div>
                            <div class="rt-detail">
                                <?php if ($is_sacr): ?>Sacramental Fee
                                <?php else: ?><?= ucfirst($tx['type']) ?><?php endif; ?>
                                <?php if ($tx['detail']): ?> · <?= htmlspecialchars(mb_strimwidth($tx['detail'], 0, 38, '…')) ?><?php endif; ?>
                                · <?= date('M j', strtotime($tx['date'])) ?>
                            </div>
                        </div>
                        <?php if ($is_inkind): ?>
                        <span style="font-size:0.7rem;font-weight:600;background:#fef3c7;color:#d97706;padding:2px 8px;border-radius:99px;white-space:nowrap;">In-kind</span>
                        <?php else: ?>
                        <div class="rt-amount" style="color:<?= $amt_color ?>;">
                            <?= $amt_sign ?>₱<?= number_format($tx['amount'], 2) ?>
                        </div>
                        <?php endif; ?>
                    </div>
                    <?php endforeach; ?>
                    </div>
                    <?php endif; ?>
                </div>
            </div>

        </div>
        <!-- END LEFT COLUMN -->

        <!-- RIGHT COLUMN -->
        <div class="fin-right-col">

            <!-- Quick actions -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-bolt" style="color:#b8933a;font-size:0.8rem;"></i>Quick Actions</div>
                </div>
                <div class="card-body">
                    <div class="quick-links">
                        <a href="/church/modules/finance/add_donation.php" class="ql" style="color:#16a34a;">
                            <div class="ql-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-plus"></i></div>
                            <div><div class="ql-label">Add Donation</div><div class="ql-sub">Cash or in-kind</div></div>
                        </a>
                        <a href="/church/modules/finance/add_donation.php?type=sacramental_fee" class="ql" style="color:#7c3aed;">
                            <div class="ql-icon" style="background:#f5f3ff;color:#7c3aed;"><i class="fas fa-cross"></i></div>
                            <div><div class="ql-label">Sacramental Fee</div><div class="ql-sub">Wedding, Baptism…</div></div>
                        </a>
                        <a href="/church/modules/finance/add_collection.php" class="ql" style="color:#2563eb;">
                            <div class="ql-icon" style="background:#eff6ff;color:#2563eb;"><i class="fas fa-plus"></i></div>
                            <div><div class="ql-label">Add Collection</div><div class="ql-sub">Log mass collection</div></div>
                        </a>
                        <a href="/church/modules/finance/add_payment.php" class="ql" style="color:#d97706;">
                            <div class="ql-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-plus"></i></div>
                            <div><div class="ql-label">Add Payment</div><div class="ql-sub">Record an expense</div></div>
                        </a>
                    </div>
                </div>
            </div>

            <!-- All records -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-layer-group" style="color:#b8933a;font-size:0.8rem;"></i>All Records</div>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <?php
                    $modules = [
                        ['Donations',        '/church/modules/finance/donations.php?type=cash',            'fa-hand-holding-heart', '#f0fdf4', '#16a34a',
                         "SELECT COUNT(*), COALESCE(SUM(amount),0) FROM donations WHERE YEAR(date)={$current_year} AND donation_type='cash'"],
                        ['Sacramental Fees', '/church/modules/finance/donations.php?type=sacramental_fee', 'fa-cross',              '#f5f3ff', '#7c3aed',
                         "SELECT COUNT(*), COALESCE(SUM(amount),0) FROM donations WHERE YEAR(date)={$current_year} AND donation_type='sacramental_fee'"],
                        ['Collections',      '/church/modules/finance/collections.php',                    'fa-church',             '#eff6ff', '#2563eb',
                         "SELECT COUNT(*), COALESCE(SUM(CASE WHEN collection_type='cash' THEN amount ELSE 0 END),0) FROM collections WHERE YEAR(date)={$current_year}"],
                        ['Payments',         '/church/modules/finance/payments.php',                       'fa-money-bill-wave',    '#fef3c7', '#d97706',
                         "SELECT COUNT(*), COALESCE(SUM(amount),0) FROM payments WHERE YEAR(date)={$current_year}"],
                    ];
                    foreach ($modules as [$lbl, $url, $icon, $bg, $color, $sql]):
                        $res = $conn->query($sql)->fetch_row();
                        $cnt = (int)$res[0]; $amt = (float)$res[1];
                    ?>
                    <a href="<?= $url ?>" style="display:flex;align-items:center;gap:12px;padding:12px 0;border-bottom:1px solid #f5f0e8;text-decoration:none;min-width:0;">
                        <div style="width:34px;height:34px;border-radius:9px;background:<?= $bg ?>;color:<?= $color ?>;display:flex;align-items:center;justify-content:center;font-size:0.82rem;flex-shrink:0;">
                            <i class="fas <?= $icon ?>"></i>
                        </div>
                        <div style="flex:1;min-width:0;">
                            <div style="font-size:0.83rem;font-weight:600;color:#0f2044;"><?= $lbl ?></div>
                            <div style="font-size:0.72rem;color:#9ca3af;"><?= $cnt ?> record<?= $cnt!==1?'s':'' ?> in <?= $current_year ?></div>
                        </div>
                        <div style="font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:700;color:<?= $color ?>;white-space:nowrap;flex-shrink:0;">
                            ₱<?= number_format($amt, 2) ?>
                        </div>
                    </a>
                    <?php endforeach; ?>
                    <a href="/church/modules/finance/reports.php" style="display:flex;align-items:center;gap:12px;padding:12px 0;text-decoration:none;">
                        <div style="width:34px;height:34px;border-radius:9px;background:#f3f4f6;color:#374151;display:flex;align-items:center;justify-content:center;font-size:0.82rem;flex-shrink:0;">
                            <i class="fas fa-file-chart-column"></i>
                        </div>
                        <div style="flex:1;">
                            <div style="font-size:0.83rem;font-weight:600;color:#0f2044;">Reports</div>
                            <div style="font-size:0.72rem;color:#9ca3af;">Monthly & annual summaries</div>
                        </div>
                        <i class="fas fa-chevron-right" style="font-size:0.65rem;color:#c4b89a;"></i>
                    </a>
                </div>
            </div>

            <!-- YTD breakdown -->
            <div class="card">
                <div class="card-head">
                    <div class="card-title"><i class="fas fa-calendar" style="color:#b8933a;font-size:0.8rem;"></i><?= $current_year ?> Year-to-Date</div>
                </div>
                <div class="card-body" style="padding:8px 20px;">
                    <div class="ytd-row">
                        <span style="color:#6b7280;">Total Inflow</span>
                        <span style="font-weight:700;color:#15803d;font-family:'Playfair Display',serif;">₱<?= number_format($donations_ytd + $collections_ytd, 2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#6b7280;padding-left:12px;font-size:0.78rem;">↳ Cash Donations</span>
                        <span style="color:#374151;">₱<?= number_format($donations_ytd_cash, 2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#7c3aed;padding-left:12px;font-size:0.78rem;">↳ Sacramental Fees</span>
                        <span style="color:#7c3aed;">₱<?= number_format($donations_ytd_sacr, 2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#6b7280;padding-left:12px;font-size:0.78rem;">↳ Collections</span>
                        <span style="color:#374151;">₱<?= number_format($collections_ytd, 2) ?></span>
                    </div>
                    <div class="ytd-row">
                        <span style="color:#6b7280;">Total Outflow</span>
                        <span style="font-weight:700;color:#dc2626;font-family:'Playfair Display',serif;">₱<?= number_format($payments_ytd, 2) ?></span>
                    </div>
                    <div class="ytd-row" style="border-top:2px solid #f0ebe0;margin-top:4px;padding-top:12px;">
                        <span style="font-weight:700;color:#0f2044;">Net Balance</span>
                        <span style="font-weight:700;font-family:'Playfair Display',serif;font-size:1rem;color:<?= $net_ytd >= 0 ? '#15803d' : '#dc2626' ?>;">
                            <?= $net_ytd >= 0 ? '' : '-' ?>₱<?= number_format(abs($net_ytd), 2) ?>
                        </span>
                    </div>
                </div>
            </div>

        </div>
        <!-- END RIGHT COLUMN -->

    </div><!-- /fin-grid -->
</div><!-- /padding wrapper -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.0/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#9ca3af';

const monthly = <?= json_encode($monthly) ?>;

let finChart = null;

function buildFinChart() {
    const canvas = document.getElementById('finChart');
    if (!canvas) return;

    // Destroy existing instance before recreating
    if (finChart) {
        finChart.destroy();
        finChart = null;
    }

    finChart = new Chart(canvas.getContext('2d'), {
        type: 'bar',
        data: {
            labels: monthly.map(m => m.label),
            datasets: [
                { label: 'Donations',        data: monthly.map(m => m.donations),   backgroundColor: 'rgba(22,163,74,0.75)',  borderRadius: 4 },
                { label: 'Sacramental Fees', data: monthly.map(m => m.sacramental), backgroundColor: 'rgba(124,58,237,0.75)', borderRadius: 4 },
                { label: 'Collections',      data: monthly.map(m => m.collections), backgroundColor: 'rgba(37,99,235,0.75)',  borderRadius: 4 },
                { label: 'Payments',         data: monthly.map(m => m.payments),    backgroundColor: 'rgba(220,38,38,0.6)',   borderRadius: 4 },
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
                x: { grid: { display: false }, ticks: { font: { family: 'DM Sans', size: 11 } } },
                y: { grid: { color: '#f3ede3' }, ticks: { font: { family: 'DM Sans', size: 10 }, callback: v => '₱' + v.toLocaleString() } }
            }
        }
    });
}

// Initial build
buildFinChart();

// Debounced resize — destroy + rebuild so chart fills container correctly
let resizeTimer = null;
window.addEventListener('resize', () => {
    clearTimeout(resizeTimer);
    resizeTimer = setTimeout(() => {
        buildFinChart();
    }, 150);
});
</script>
<?php include $root . '/includes/footer.php'; ?>