<?php
// church/dashboard.php
require_once __DIR__ . '/auth/check_session.php';
require_once __DIR__ . '/config/db.php';

$page_title = 'Dashboard';

// ── Stat queries ─────────────────────────────────────────────
function db_count($conn, $sql) {
    $r = $conn->query($sql);
    return $r ? (int)$r->fetch_row()[0] : 0;
}

$total_baptism      = db_count($conn, "SELECT COUNT(*) FROM baptism_records      WHERE is_archived = 0");
$total_confirmation = db_count($conn, "SELECT COUNT(*) FROM confirmation_records WHERE is_archived = 0");
$total_communion    = db_count($conn, "SELECT COUNT(*) FROM communion_records    WHERE is_archived = 0");
$total_wedding      = db_count($conn, "SELECT COUNT(*) FROM wedding_records      WHERE is_archived = 0");
$total_funeral      = db_count($conn, "SELECT COUNT(*) FROM funeral_records      WHERE is_archived = 0");
$total_records      = $total_baptism + $total_confirmation + $total_communion + $total_wedding + $total_funeral;

$total_members      = db_count($conn, "SELECT COUNT(*) FROM members    WHERE is_archived = 0");
$total_volunteers   = db_count($conn, "SELECT COUNT(*) FROM volunteers WHERE is_archived = 0");

$fin  = $conn->query("SELECT COALESCE(SUM(amount),0) FROM donations");
$total_donations_amt = $fin ? (float)$fin->fetch_row()[0] : 0;
$fin2 = $conn->query("SELECT COALESCE(SUM(amount),0) FROM collections");
$total_collections_amt = $fin2 ? (float)$fin2->fetch_row()[0] : 0;
$total_finance = $total_donations_amt + $total_collections_amt;

$upcoming_events = db_count($conn, "SELECT COUNT(*) FROM events WHERE date >= CURDATE()");
$total_archived  = db_count($conn,
    "SELECT COUNT(*) FROM archives WHERE reference_type IN ('baptism','confirmation','communion','wedding','funeral','member','volunteer')"
);

$pending_bookings = 0; $confirmed_bookings = 0; $total_bookings = 0; $bookings_exist = false;
try {
    $pending_bookings   = db_count($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'pending'");
    $confirmed_bookings = db_count($conn, "SELECT COUNT(*) FROM bookings WHERE status = 'confirmed'");
    $total_bookings     = db_count($conn, "SELECT COUNT(*) FROM bookings");
    $bookings_exist = true;
} catch (Exception $e) {}

// ── Chart data ───────────────────────────────────────────────
$monthly_labels = []; $monthly_baptism = []; $monthly_communion = []; $monthly_confirmation = []; $monthly_wedding = []; $monthly_funeral = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-{$i} months");
    $monthly_labels[] = date('M Y', $ts);
    $y = date('Y', $ts); $m = date('m', $ts);
    $monthly_baptism[]      = db_count($conn, "SELECT COUNT(*) FROM baptism_records      WHERE YEAR(created_at)=$y AND MONTH(created_at)=$m AND is_archived=0");
    $monthly_communion[]    = db_count($conn, "SELECT COUNT(*) FROM communion_records    WHERE YEAR(created_at)=$y AND MONTH(created_at)=$m AND is_archived=0");
    $monthly_confirmation[] = db_count($conn, "SELECT COUNT(*) FROM confirmation_records WHERE YEAR(created_at)=$y AND MONTH(created_at)=$m AND is_archived=0");
    $monthly_wedding[]      = db_count($conn, "SELECT COUNT(*) FROM wedding_records      WHERE YEAR(created_at)=$y AND MONTH(created_at)=$m AND is_archived=0");
    $monthly_funeral[]      = db_count($conn, "SELECT COUNT(*) FROM funeral_records      WHERE YEAR(created_at)=$y AND MONTH(created_at)=$m AND is_archived=0");
}

$monthly_donations = [];
for ($i = 5; $i >= 0; $i--) {
    $ts = strtotime("-{$i} months");
    $y = date('Y', $ts); $m = date('m', $ts);
    $r = $conn->query("SELECT COALESCE(SUM(amount),0) FROM donations WHERE YEAR(date)=$y AND MONTH(date)=$m");
    $monthly_donations[] = $r ? round((float)$r->fetch_row()[0], 2) : 0;
}

$booking_by_type = ['baptism' => 0, 'wedding' => 0, 'funeral' => 0];
if ($bookings_exist) {
    $r = $conn->query("SELECT type, COUNT(*) as cnt FROM bookings GROUP BY type");
    while ($row = $r->fetch_assoc()) $booking_by_type[$row['type']] = (int)$row['cnt'];
}

$today = date('Y-m-d');
$today_events = [];
$te = $conn->query("SELECT name, time FROM events WHERE date = '$today' ORDER BY time ASC");
while ($row = $te->fetch_assoc()) $today_events[] = $row;

$today_appointments = [];
if ($bookings_exist) {
    $ta = $conn->query("SELECT requestor_name, type, confirmed_time FROM bookings WHERE confirmed_date = '$today' AND status = 'confirmed' ORDER BY confirmed_time ASC");
    while ($row = $ta->fetch_assoc()) $today_appointments[] = $row;
}

$cal_year  = (int)($_GET['cy'] ?? date('Y'));
$cal_month = (int)($_GET['cm'] ?? date('n'));
if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }
$cal_first_day = mktime(0,0,0,$cal_month,1,$cal_year);
$cal_days_in   = (int)date('t', $cal_first_day);
$cal_start_dow = (int)date('w', $cal_first_day);

$event_days = [];
$ev = $conn->query("SELECT DAY(date) as d, event_type, COUNT(*) as cnt FROM events WHERE YEAR(date)=$cal_year AND MONTH(date)=$cal_month GROUP BY DAY(date), event_type");
while ($row = $ev->fetch_assoc()) $event_days[(int)$row['d']][$row['event_type']] = (int)$row['cnt'];

$appt_days = [];
if ($bookings_exist) {
    $ap = $conn->query("SELECT DAY(confirmed_date) as d, type, COUNT(*) as cnt FROM bookings WHERE YEAR(confirmed_date)=$cal_year AND MONTH(confirmed_date)=$cal_month AND status='confirmed' GROUP BY DAY(confirmed_date), type");
    while ($row = $ap->fetch_assoc()) $appt_days[(int)$row['d']][$row['type']] = (int)$row['cnt'];
}

$recent_bookings = [];
if ($bookings_exist) {
    $rb = $conn->query("SELECT id, type, requestor_name, contact_number, preferred_date, status, created_at FROM bookings ORDER BY created_at DESC LIMIT 5");
    while ($row = $rb->fetch_assoc()) $recent_bookings[] = $row;
}

include __DIR__ . '/includes/header.php';
?>

<style>
/* ── Stat cards ──────────────────────────────────────────────── */
.stat-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(180px, 1fr));
    gap: 16px;
}
.stat-card {
    background: #fff;
    border: 1px solid #ede8de;
    border-radius: 14px;
    padding: 20px 18px 16px;
    position: relative;
    overflow: hidden;
    transition: transform 0.2s ease, box-shadow 0.2s ease;
    text-decoration: none;
    display: block;
    cursor: default;
}
a.stat-card { cursor: pointer; }
.stat-card:hover { transform: translateY(-3px); box-shadow: 0 8px 28px rgba(0,0,0,0.09); }
.stat-card::before {
    content: '';
    position: absolute;
    top: 0; left: 0; right: 0;
    height: 3px;
    background: var(--sc-color, #b8933a);
    border-radius: 14px 14px 0 0;
}
.stat-card-icon {
    width: 40px; height: 40px;
    border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    font-size: 1rem;
    margin-bottom: 14px;
    background: var(--sc-bg, #fdf8ec);
    color: var(--sc-color, #b8933a);
}
.stat-card-value {
    font-family: 'Playfair Display', serif;
    font-size: 1.9rem;
    font-weight: 600;
    color: #0f2044;
    line-height: 1;
    margin-bottom: 5px;
}
.stat-card-label { font-size: 0.75rem; color: #9ca3af; font-weight: 500; letter-spacing: 0.03em; }
.stat-card-sub   { font-size: 0.7rem;  color: #c4b89a; margin-top: 4px; }
.stat-card.pending-alert { border-color: #fde68a; background: linear-gradient(135deg, #fffbeb, #fff); }
.stat-card.pending-alert::before { background: #f59e0b; }
.pending-pulse {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.68rem; font-weight: 600; color: #92400e;
    background: #fef3c7; padding: 2px 8px; border-radius: 99px; margin-top: 6px;
}
.pending-pulse::before {
    content: ''; width: 6px; height: 6px; border-radius: 50%;
    background: #f59e0b; animation: blink 1.4s ease-in-out infinite;
}
@keyframes blink { 0%,100%{opacity:1} 50%{opacity:0.3} }

/* ── Section headers ─────────────────────────────────────────── */
.section-head {
    display: flex; align-items: center; justify-content: space-between;
    margin-bottom: 14px; flex-wrap: wrap; gap: 8px;
}
.section-title {
    font-family: 'Playfair Display', serif;
    font-size: 0.92rem; font-weight: 600; color: #0f2044;
    display: flex; align-items: center; gap: 8px;
}
.section-title i { color: #b8933a; font-size: 0.85rem; }

/* ── Chart cards ─────────────────────────────────────────────── */
.chart-card {
    background: #fff;
    border: 1px solid #ede8de;
    border-radius: 14px;
    padding: 20px 22px 18px;
    min-width: 0;
    overflow: hidden;
}
.chart-card canvas { max-height: 220px; width: 100% !important; }

/* ── Bookings table card ──────────────────────────────────────── */
.bookings-card {
    background: #fff;
    border: 1px solid #ede8de;
    border-radius: 14px;
    overflow: hidden;
    display: flex;
    flex-direction: column;
    min-width: 0;
}
.bookings-card-head {
    padding: 16px 20px 14px;
    display: flex; align-items: center; justify-content: space-between;
    border-bottom: 1px solid #f3ede3;
    flex-shrink: 0;
}
.bookings-card-head .section-title { margin-bottom: 0; }
.bookings-view-all {
    font-size: 0.75rem; color: #b8933a; text-decoration: none;
    display: flex; align-items: center; gap: 4px;
    transition: color 0.15s;
}
.bookings-view-all:hover { color: #9a7820; }

/* Empty state */
.bookings-empty {
    display: flex; flex-direction: column; align-items: center;
    justify-content: center; padding: 40px 20px; gap: 10px;
    color: #c4b89a;
}
.bookings-empty i { font-size: 2rem; opacity: 0.35; }
.bookings-empty p { font-size: 0.82rem; margin: 0; }

/* Table resets inside the card */
.bookings-table {
    width: 100%;
    border-collapse: collapse;
    font-size: 0.83rem;
    table-layout: fixed;
}
.bookings-table thead tr {
    background: #faf8f4;
    border-bottom: 1px solid #f0e8d8;
}
.bookings-table th {
    padding: 9px 16px;
    text-align: left;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.08em;
    text-transform: uppercase;
    color: #a08060;
    white-space: nowrap;
}
.bookings-table td {
    padding: 11px 16px;
    border-bottom: 1px solid #f5f0e8;
    vertical-align: middle;
    color: #374151;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}
.bookings-table tbody tr:last-child td { border-bottom: none; }
.bookings-table tbody tr {
    transition: background 0.12s;
}
.bookings-table tbody tr:hover { background: #faf8f4; }

/* Row tint by status */
.brow-pending   td:first-child { border-left: 3px solid #f59e0b; }
.brow-confirmed td:first-child { border-left: 3px solid #10b981; }
.brow-declined  td:first-child { border-left: 3px solid #ef4444; }
.brow-completed td:first-child { border-left: 3px solid #6366f1; }

/* Requestor name cell */
.bk-name {
    font-weight: 600;
    color: #0f2044;
    font-size: 0.83rem;
    text-decoration: none;
    display: block;
    overflow: hidden;
    text-overflow: ellipsis;
}
.bk-name:hover { color: #b8933a; }

/* Type pill */
.bk-type {
    display: inline-flex; align-items: center; gap: 5px;
    font-size: 0.72rem; font-weight: 500;
    padding: 3px 10px; border-radius: 99px;
    text-transform: capitalize;
    white-space: nowrap;
}
.bk-type-baptism   { background: #eff6ff; color: #1d4ed8; }
.bk-type-communion { background: #ecfdf5; color: #065f46; }
.bk-type-wedding   { background: #fef9c3; color: #92400e; }
.bk-type-funeral   { background: #f5f3ff; color: #6d28d9; }

/* Status badge */
.bk-status {
    display: inline-block;
    padding: 3px 10px;
    border-radius: 99px;
    font-size: 0.68rem;
    font-weight: 700;
    letter-spacing: 0.05em;
    text-transform: uppercase;
    white-space: nowrap;
}
.bk-status-pending   { background: #fef3c7; color: #92400e; }
.bk-status-confirmed { background: #dcfce7; color: #166534; }
.bk-status-declined  { background: #fee2e2; color: #991b1b; }
.bk-status-completed { background: #ede9fe; color: #5b21b6; }

/* Date cell */
.bk-date { font-size: 0.75rem; color: #9ca3af; white-space: nowrap; }

/* ── Calendar ────────────────────────────────────────────────── */
.cal-card { background:#fff; border:1px solid #ede8de; border-radius:14px; }
.cal-header {
    background:#0f2044; color:#fff; padding:14px 18px;
    display:flex; align-items:center; justify-content:space-between;
    border-radius: 14px 14px 0 0;
}
.cal-header-title { font-family:'Playfair Display',serif; font-size:0.92rem; font-weight:600; letter-spacing:0.04em; }
.cal-nav { display:flex; gap:4px; }
.cal-nav a {
    width:28px; height:28px; border-radius:6px;
    display:flex; align-items:center; justify-content:center;
    color:rgba(255,255,255,0.7); font-size:0.75rem;
    background:rgba(255,255,255,0.08); transition:background 0.15s, color 0.15s; text-decoration:none;
}
.cal-nav a:hover { background:rgba(201,162,39,0.25); color:#e0c060; }
.cal-dow-row { display:grid; grid-template-columns:repeat(7,1fr); background:#f5f0e8; border-bottom:1px solid #ede8de; }
.cal-dow { text-align:center; font-size:0.64rem; font-weight:700; letter-spacing:0.07em; text-transform:uppercase; color:#9ca3af; padding:7px 0; }
.cal-grid { display:grid; grid-template-columns:repeat(7,1fr); gap:1px; background:#ede8de; padding:1px; }
.cal-cell { background:#fff; min-height:44px; padding:5px 4px 4px; position:relative; display:flex; flex-direction:column; align-items:center; gap:3px; }
.cal-cell.other-month { background:#faf8f4; }
.cal-num { font-size:0.75rem; font-weight:500; color:#6b7280; line-height:1; width:22px; height:22px; border-radius:50%; display:flex; align-items:center; justify-content:center; }
.cal-cell.today .cal-num { background:#0f2044; color:#fff; font-weight:700; }
.cal-dots { display:flex; gap:2px; flex-wrap:wrap; justify-content:center; }
.cal-dot { width:5px; height:5px; border-radius:50%; flex-shrink:0; }
.dot-baptism   { background:#3b82f6; }
.dot-communion { background:#10b981; }
.dot-confirmation { background:#0d9488; }
.dot-wedding   { background:#f43f5e; }
.dot-funeral   { background:#8b5cf6; }
.dot-other     { background:#f59e0b; }
.dot-appt-baptism   { background:#1d4ed8; }
.dot-appt-communion { background:#059669; }
.dot-appt-wedding   { background:#be123c; }
.dot-appt-funeral   { background:#6d28d9; }
.cal-legend { padding:10px 14px; display:flex; gap:14px; font-size:0.72rem; color:#9ca3af; border-top:1px solid #f3ede3; flex-wrap:wrap; }
.cal-legend span { display:flex; align-items:center; gap:5px; }
.cal-legend .cal-dot { width:8px; height:8px; }

/* ── Today panel ─────────────────────────────────────────────── */
.today-card { background:#fff; border:1px solid #ede8de; border-radius:14px; overflow:hidden; }
.today-header {
    background:linear-gradient(135deg,#0f2044,#1a3870); padding:14px 18px;
    display:flex; align-items:center; justify-content:space-between;
}
.today-date-label { font-family:'Playfair Display',serif; font-size:0.88rem; color:#fff; font-weight:600; }
.today-day-badge { font-size:0.65rem; font-weight:600; letter-spacing:0.1em; text-transform:uppercase; color:#e0c060; background:rgba(201,162,39,0.15); padding:3px 9px; border-radius:99px; }
.today-body { padding:14px 16px; }
.today-item { display:flex; align-items:flex-start; gap:10px; padding:9px 0; border-bottom:1px solid #f5f0e8; }
.today-item:last-child { border-bottom:none; }
.today-item-dot { width:8px; height:8px; border-radius:50%; margin-top:4px; flex-shrink:0; }
.today-item-time { font-size:0.7rem; color:#b0a898; white-space:nowrap; min-width:48px; margin-top:1px; }
.today-item-name { font-size:0.82rem; color:#374151; font-weight:500; }
.today-item-sub  { font-size:0.72rem; color:#9ca3af; }
.today-empty { text-align:center; padding:24px 16px; color:#c4b89a; font-size:0.82rem; }
.today-empty i { font-size:1.4rem; display:block; margin-bottom:8px; opacity:0.4; }

/* ── Welcome bar ─────────────────────────────────────────────── */
.welcome-bar {
    background:linear-gradient(135deg,#0f2044 0%,#162d5c 60%,#1a3870 100%);
    border-radius:14px; padding:22px 28px;
    display:flex; align-items:center; justify-content:space-between;
    gap:16px; flex-wrap:wrap; margin-bottom:24px;
    position:relative; overflow:hidden;
}
.welcome-bar::after {
    content:'✦'; position:absolute; right:28px; top:50%; transform:translateY(-50%);
    font-size:5rem; color:rgba(201,162,39,0.06); pointer-events:none; font-family:serif;
}
.welcome-greeting { font-family:'Playfair Display',serif; font-size:1.1rem; color:#fff; font-weight:600; margin-bottom:3px; }
.welcome-sub      { font-size:0.78rem; color:rgba(255,255,255,0.45); }
.welcome-date     { text-align:right; flex-shrink:0; }
.welcome-date-main { font-family:'Playfair Display',serif; font-size:1rem; color:#e0c060; font-weight:600; }
.welcome-date-sub  { font-size:0.72rem; color:rgba(255,255,255,0.35); }

/* ── Responsive ──────────────────────────────────────────────── */
@media (max-width:1100px) { .dash-main-grid { grid-template-columns:1fr !important; } }
@media (max-width:900px)  { .chart-two-col  { grid-template-columns:1fr !important; } }
@media (max-width:640px)  { .stat-grid { grid-template-columns:repeat(2,1fr); gap:12px; } .welcome-date { display:none; } }
@media (max-width:380px)  { .stat-grid { grid-template-columns:1fr; } }
</style>

<!-- ── Page header ──────────────────────────────────────── -->
<div class="page-header">
    <div class="page-header-left">
        <h1><i class="fas fa-th-large" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>Dashboard</h1>
        <p class="breadcrumb"><span class="current">Overview</span></p>
    </div>
    <?php if ($pending_bookings > 0): ?>
    <a href="/church/bookings/index.php"
       style="display:inline-flex;align-items:center;gap:8px;padding:8px 16px;
              background:#fef3c7;border:1px solid #fde68a;border-radius:8px;
              font-size:0.8rem;font-weight:600;color:#92400e;text-decoration:none;">
        <i class="fas fa-bell" style="animation:blink 1.4s infinite;"></i>
        <?= $pending_bookings ?> pending booking<?= $pending_bookings !== 1 ? 's' : '' ?> need attention
        <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i>
    </a>
    <?php endif; ?>
</div>

<!-- ── Main content ─────────────────────────────────────── -->
<div style="padding:24px 24px 60px;">

    <!-- Welcome bar -->
    <div class="welcome-bar">
        <div>
            <div class="welcome-greeting">
                Good <?= (date('G') < 12) ? 'morning' : ((date('G') < 18) ? 'afternoon' : 'evening') ?>,
                <?= htmlspecialchars(explode(' ', $current_user_name)[0]) ?> 👋
            </div>
            <div class="welcome-sub">Here's what's happening at the parish today.</div>
        </div>
        <div class="welcome-date">
            <div class="welcome-date-main"><?= date('l') ?></div>
            <div class="welcome-date-sub"><?= date('F j, Y') ?></div>
        </div>
    </div>

    <!-- ── STAT CARDS ──────────────────────────────────────── -->
    <div class="stat-grid" style="margin-bottom:24px;">

        <a href="/church/modules/church_records/index.php" class="stat-card" style="--sc-color:#3b82f6;--sc-bg:#eff6ff;">
            <div class="stat-card-icon"><i class="fas fa-book-open"></i></div>
            <div class="stat-card-value"><?= number_format($total_records) ?></div>
            <div class="stat-card-label">Total Records</div>
            <div class="stat-card-sub"><?= $total_baptism ?> baptism · <?= $total_communion ?> communion · <?= $total_wedding ?> wedding · <?= $total_funeral ?> funeral</div>
        </a>

        <a href="/church/modules/members/index.php" class="stat-card" style="--sc-color:#8b5cf6;--sc-bg:#f5f3ff;">
            <div class="stat-card-icon"><i class="fas fa-users"></i></div>
            <div class="stat-card-value"><?= number_format($total_members) ?></div>
            <div class="stat-card-label">Parish Members</div>
            <div class="stat-card-sub"><?= $total_volunteers ?> volunteer<?= $total_volunteers !== 1 ? 's' : '' ?></div>
        </a>

        <a href="/church/modules/finance/index.php" class="stat-card" style="--sc-color:#0d9488;--sc-bg:#f0fdfa;">
            <div class="stat-card-icon"><i class="fas fa-coins"></i></div>
            <div class="stat-card-value">₱<?= number_format($total_finance, 0) ?></div>
            <div class="stat-card-label">Total Funds</div>
            <div class="stat-card-sub">₱<?= number_format($total_donations_amt, 0) ?> donations · ₱<?= number_format($total_collections_amt, 0) ?> collections</div>
        </a>

        <a href="/church/modules/events/calendar.php" class="stat-card" style="--sc-color:#ec4899;--sc-bg:#fdf2f8;">
            <div class="stat-card-icon"><i class="fas fa-calendar-days"></i></div>
            <div class="stat-card-value"><?= number_format($upcoming_events) ?></div>
            <div class="stat-card-label">Upcoming Events</div>
            <div class="stat-card-sub"><?= count($today_events) ?> scheduled today</div>
        </a>

        <a href="/church/bookings/index.php" class="stat-card <?= $pending_bookings > 0 ? 'pending-alert' : '' ?>" style="--sc-color:#f59e0b;--sc-bg:#fffbeb;">
            <div class="stat-card-icon"><i class="fas fa-calendar-check"></i></div>
            <div class="stat-card-value"><?= number_format($total_bookings) ?></div>
            <div class="stat-card-label">Bookings</div>
            <?php if ($pending_bookings > 0): ?>
            <div class="pending-pulse"><?= $pending_bookings ?> pending</div>
            <?php else: ?>
            <div class="stat-card-sub"><?= $confirmed_bookings ?> confirmed</div>
            <?php endif; ?>
        </a>

        <a href="/church/modules/archive/index.php" class="stat-card" style="--sc-color:#6b7280;--sc-bg:#f9fafb;">
            <div class="stat-card-icon"><i class="fas fa-box-archive"></i></div>
            <div class="stat-card-value"><?= number_format($total_archived) ?></div>
            <div class="stat-card-label">Archived Items</div>
            <div class="stat-card-sub">Across all modules</div>
        </a>

    </div>

    <!-- ── MAIN GRID ────────────────────────────────────────── -->
    <div class="dash-main-grid" style="display:grid;grid-template-columns:1fr minmax(0,320px);gap:20px;align-items:start;">

        <!-- LEFT column -->
        <div style="display:flex;flex-direction:column;gap:20px;min-width:0;">

            <!-- Charts row -->
            <div class="chart-two-col" style="display:grid;grid-template-columns:1fr 1fr;gap:16px;min-width:0;">
                <div class="chart-card">
                    <div class="section-head">
                        <div class="section-title"><i class="fas fa-chart-line"></i> Records (6 months)</div>
                    </div>
                    <canvas id="chartRecords"></canvas>
                </div>
                <div class="chart-card">
                    <div class="section-head">
                        <div class="section-title"><i class="fas fa-chart-bar"></i> Donations (6 months)</div>
                    </div>
                    <canvas id="chartDonations"></canvas>
                </div>
            </div>

            <!-- Bookings row -->
            <div class="chart-two-col" style="display:grid;grid-template-columns:200px 1fr;gap:16px;min-width:0;align-items:start;">

                <!-- Donut -->
                <div class="chart-card" style="display:flex;flex-direction:column;align-items:center;">
                    <div class="section-head" style="width:100%;">
                        <div class="section-title"><i class="fas fa-chart-pie"></i> Bookings</div>
                    </div>
                    <canvas id="chartBookings" style="max-height:160px;max-width:160px;"></canvas>
                    <div style="display:flex;flex-direction:column;gap:6px;margin-top:14px;width:100%;">
                        <span style="font-size:0.72rem;color:#6b7280;display:flex;align-items:center;gap:6px;">
                            <span style="width:9px;height:9px;border-radius:50%;background:#3b82f6;display:inline-block;flex-shrink:0;"></span>
                            Baptism <strong style="margin-left:auto;color:#374151;"><?= $booking_by_type['baptism'] ?></strong>
                        </span>
                        <span style="font-size:0.72rem;color:#6b7280;display:flex;align-items:center;gap:6px;">
                            <span style="width:9px;height:9px;border-radius:50%;background:#b8933a;display:inline-block;flex-shrink:0;"></span>
                            Wedding <strong style="margin-left:auto;color:#374151;"><?= $booking_by_type['wedding'] ?></strong>
                        </span>
                        <span style="font-size:0.72rem;color:#6b7280;display:flex;align-items:center;gap:6px;">
                            <span style="width:9px;height:9px;border-radius:50%;background:#8b5cf6;display:inline-block;flex-shrink:0;"></span>
                            Funeral <strong style="margin-left:auto;color:#374151;"><?= $booking_by_type['funeral'] ?></strong>
                        </span>
                    </div>
                </div>

                <!-- ── RECENT BOOKINGS TABLE ── -->
                <div class="bookings-card">

                    <div class="bookings-card-head">
                        <div class="section-title">
                            <i class="fas fa-inbox"></i> Recent Bookings
                        </div>
                        <a href="/church/bookings/index.php" class="bookings-view-all">
                            View all <i class="fas fa-arrow-right" style="font-size:0.65rem;"></i>
                        </a>
                    </div>

                    <?php if (empty($recent_bookings)): ?>
                    <div class="bookings-empty">
                        <i class="fas fa-calendar-check"></i>
                        <p>No bookings yet.</p>
                    </div>

                    <?php else: ?>
                    <div style="overflow-x:auto;">
                        <table class="bookings-table">
                            <colgroup>
                                <col style="width:36%">
                                <col style="width:22%">
                                <col style="width:18%">
                                <col style="width:24%">
                            </colgroup>
                            <thead>
                                <tr>
                                    <th>Requestor</th>
                                    <th>Type</th>
                                    <th>Date</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody>
                            <?php foreach ($recent_bookings as $bk):
                                $st = $bk['status'];
                                $type_icon = [
                                    'baptism'   => 'fa-droplet',
                                    'communion' => 'fa-bread-slice',
                                    'wedding'   => 'fa-ring',
                                    'funeral'   => 'fa-cross',
                                ][$bk['type']] ?? 'fa-calendar';
                            ?>
                            <tr class="brow-<?= $st ?>">
                                <td>
                                    <a href="/church/bookings/view_booking.php?id=<?= $bk['id'] ?>" class="bk-name">
                                        <?= htmlspecialchars($bk['requestor_name']) ?>
                                    </a>
                                </td>
                                <td>
                                    <span class="bk-type bk-type-<?= $bk['type'] ?>">
                                        <i class="fas <?= $type_icon ?>" style="font-size:0.65rem;"></i>
                                        <?= ucfirst($bk['type']) ?>
                                    </span>
                                </td>
                                <td class="bk-date">
                                    <?= $bk['preferred_date'] ? date('M j, Y', strtotime($bk['preferred_date'])) : '—' ?>
                                </td>
                                <td>
                                    <span class="bk-status bk-status-<?= $st ?>">
                                        <?= ucfirst($st) ?>
                                    </span>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                            </tbody>
                        </table>
                    </div>
                    <?php endif; ?>

                </div><!-- /bookings-card -->

            </div><!-- /bookings row -->
        </div><!-- /LEFT column -->

        <!-- RIGHT column: Calendar + Today -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <div class="cal-card">
                <div class="cal-header">
                    <span class="cal-header-title"><?= date('F Y', mktime(0,0,0,$cal_month,1,$cal_year)) ?></span>
                    <div class="cal-nav">
                        <?php
                            $pm = $cal_month - 1; $py = $cal_year; if ($pm < 1)  { $pm = 12; $py--; }
                            $nm = $cal_month + 1; $ny = $cal_year; if ($nm > 12) { $nm = 1;  $ny++; }
                        ?>
                        <a href="?cy=<?= $py ?>&cm=<?= $pm ?>"><i class="fas fa-chevron-left"></i></a>
                        <a href="?cy=<?= date('Y') ?>&cm=<?= date('n') ?>"><i class="fas fa-circle" style="font-size:0.4rem;"></i></a>
                        <a href="?cy=<?= $ny ?>&cm=<?= $nm ?>"><i class="fas fa-chevron-right"></i></a>
                    </div>
                </div>
                <div class="cal-dow-row">
                    <?php foreach (['Su','Mo','Tu','We','Th','Fr','Sa'] as $d): ?>
                    <div class="cal-dow"><?= $d ?></div>
                    <?php endforeach; ?>
                </div>
                <div class="cal-grid">
                    <?php
                    for ($i = 0; $i < $cal_start_dow; $i++) echo '<div class="cal-cell other-month"></div>';
                    $today_day = (date('Y') == $cal_year && date('n') == $cal_month) ? (int)date('j') : -1;
                    $type_colors = ['baptism'=>'dot-baptism','communion'=>'dot-communion','confirmation'=>'dot-confirmation','wedding'=>'dot-wedding','funeral'=>'dot-funeral','other'=>'dot-other'];
                    $appt_colors = ['baptism'=>'dot-appt-baptism','communion'=>'dot-appt-communion','wedding'=>'dot-appt-wedding','funeral'=>'dot-appt-funeral'];
                    for ($d = 1; $d <= $cal_days_in; $d++) {
                        $dots = [];
                        foreach ($type_colors as $type => $cls) if (isset($event_days[$d][$type])) $dots[] = '<span class="cal-dot '.$cls.'" title="'.$event_days[$d][$type].' '.$type.' event(s)"></span>';
                        foreach ($appt_colors as $type => $cls)  if (isset($appt_days[$d][$type]))  $dots[] = '<span class="cal-dot '.$cls.'" title="'.$appt_days[$d][$type].' '.$type.' appt(s)"></span>';
                        echo '<div class="cal-cell'.($d===$today_day?' today':'').'">';
                        echo '<span class="cal-num">'.$d.'</span>';
                        if (!empty($dots)) echo '<div class="cal-dots">'.implode('',array_slice($dots,0,4)).'</div>';
                        echo '</div>';
                    }
                    $trailing = (7-(($cal_start_dow+$cal_days_in)%7))%7;
                    for ($i=0;$i<$trailing;$i++) echo '<div class="cal-cell other-month"></div>';
                    ?>
                </div>
                <div class="cal-legend">
                    <span><span class="cal-dot dot-baptism"></span> Baptism</span>
                    <span><span class="cal-dot dot-communion"></span> Communion</span>
                    <span><span class="cal-dot dot-confirmation"></span> Confirmation</span>
                    <span><span class="cal-dot dot-wedding"></span> Wedding</span>
                    <span><span class="cal-dot dot-funeral"></span> Funeral</span>
                    <span><span class="cal-dot dot-other"></span> Other</span>
                </div>
            </div>

            <div class="today-card">
                <div class="today-header">
                    <span class="today-date-label">Today's Schedule</span>
                    <span class="today-day-badge"><?= date('M j') ?></span>
                </div>
                <div class="today-body">
                    <?php if (empty($today_events) && empty($today_appointments)): ?>
                    <div class="today-empty">
                        <i class="fas fa-calendar-check"></i>
                        Nothing scheduled for today.
                    </div>
                    <?php else: ?>
                        <?php if (!empty($today_events)): ?>
                        <p style="font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#c4b89a;margin-bottom:4px;">Events</p>
                        <?php foreach ($today_events as $ev): ?>
                        <div class="today-item">
                            <div class="today-item-dot" style="background:#3b82f6;"></div>
                            <div class="today-item-time"><?= $ev['time'] ? date('g:i A', strtotime($ev['time'])) : 'All day' ?></div>
                            <div><div class="today-item-name"><?= htmlspecialchars($ev['name']) ?></div></div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                        <?php if (!empty($today_appointments)): ?>
                        <p style="font-size:0.68rem;font-weight:700;letter-spacing:0.1em;text-transform:uppercase;color:#c4b89a;margin-top:10px;margin-bottom:4px;">Appointments</p>
                        <?php foreach ($today_appointments as $ap): ?>
                        <div class="today-item">
                            <div class="today-item-dot" style="background:#b8933a;"></div>
                            <div class="today-item-time"><?= $ap['confirmed_time'] ? date('g:i A', strtotime($ap['confirmed_time'])) : '—' ?></div>
                            <div>
                                <div class="today-item-name"><?= htmlspecialchars($ap['requestor_name']) ?></div>
                                <div class="today-item-sub" style="text-transform:capitalize;"><?= $ap['type'] ?> booking</div>
                            </div>
                        </div>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    <?php endif; ?>
                </div>
            </div>

        </div><!-- /RIGHT column -->

    </div><!-- /dash-main-grid -->
</div><!-- /padding wrapper -->

<script src="https://cdnjs.cloudflare.com/ajax/libs/Chart.js/4.4.1/chart.umd.min.js"></script>
<script>
Chart.defaults.font.family = "'DM Sans', sans-serif";
Chart.defaults.color = '#9ca3af';
const labels6 = <?= json_encode($monthly_labels) ?>;

new Chart(document.getElementById('chartRecords'), {
    type: 'line',
    data: {
        labels: labels6,
        datasets: [
            { label:'Baptism',      data:<?= json_encode($monthly_baptism) ?>,      borderColor:'#3b82f6', backgroundColor:'rgba(59,130,246,0.08)',  tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#3b82f6' },
            { label:'Communion',    data:<?= json_encode($monthly_communion) ?>,    borderColor:'#10b981', backgroundColor:'rgba(16,185,129,0.08)',  tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#10b981' },
            { label:'Confirmation', data:<?= json_encode($monthly_confirmation) ?>, borderColor:'#0d9488', backgroundColor:'rgba(13,148,136,0.08)',  tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#0d9488' },
            { label:'Wedding',      data:<?= json_encode($monthly_wedding) ?>,      borderColor:'#b8933a', backgroundColor:'rgba(184,147,58,0.08)',  tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#b8933a' },
            { label:'Funeral',      data:<?= json_encode($monthly_funeral) ?>,      borderColor:'#8b5cf6', backgroundColor:'rgba(139,92,246,0.08)',  tension:0.4, fill:true, pointRadius:3, pointBackgroundColor:'#8b5cf6' },
        ]
    },
    options: { responsive:true, maintainAspectRatio:true, plugins:{ legend:{ position:'bottom', labels:{ boxWidth:10, padding:14, font:{size:11} } }, tooltip:{ mode:'index', intersect:false } }, scales:{ x:{ grid:{display:false}, ticks:{font:{size:10}} }, y:{ beginAtZero:true, ticks:{stepSize:1,font:{size:10}}, grid:{color:'#f3ede3'} } } }
});

new Chart(document.getElementById('chartDonations'), {
    type: 'bar',
    data: { labels:labels6, datasets:[{ label:'Donations (₱)', data:<?= json_encode($monthly_donations) ?>, backgroundColor:'rgba(13,110,110,0.75)', borderColor:'#0d6e6e', borderWidth:1, borderRadius:5, borderSkipped:false }] },
    options: { responsive:true, maintainAspectRatio:true, plugins:{ legend:{display:false}, tooltip:{ callbacks:{ label: ctx => '₱'+ctx.parsed.y.toLocaleString() } } }, scales:{ x:{ grid:{display:false}, ticks:{font:{size:10}} }, y:{ beginAtZero:true, grid:{color:'#f3ede3'}, ticks:{ font:{size:10}, callback: v => '₱'+v.toLocaleString() } } } }
});

const bookingData = [<?= (int)$booking_by_type['baptism'] ?>, <?= (int)$booking_by_type['wedding'] ?>, <?= (int)$booking_by_type['funeral'] ?>];
const hasBookings = bookingData.some(v => v > 0);
new Chart(document.getElementById('chartBookings'), {
    type: 'doughnut',
    data: { labels:['Baptism','Wedding','Funeral'], datasets:[{ data: hasBookings ? bookingData : [1,1,1], backgroundColor: hasBookings ? ['#3b82f6','#b8933a','#8b5cf6'] : ['#e5e7eb','#e5e7eb','#e5e7eb'], borderWidth:0, hoverOffset:6 }] },
    options: { responsive:true, cutout:'68%', plugins:{ legend:{display:false}, tooltip:{enabled:hasBookings} } }
});
</script>

<?php include __DIR__ . '/includes/footer.php'; ?>