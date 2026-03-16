<?php
// church/modules/events/index.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$can_manage = in_array($current_user_role, ['admin', 'clergy']);

$today      = date('Y-m-d');
$cal_year   = (int)($_GET['year']  ?? date('Y'));
$cal_month  = (int)($_GET['month'] ?? date('n'));

if ($cal_month < 1)  { $cal_month = 12; $cal_year--; }
if ($cal_month > 12) { $cal_month = 1;  $cal_year++; }

$cal_month_str = sprintf('%02d', $cal_month);
$month_name    = date('F', mktime(0,0,0,$cal_month,1,$cal_year));
$days_in_month = (int)date('t', mktime(0,0,0,$cal_month,1,$cal_year));
$first_weekday = (int)date('w', mktime(0,0,0,$cal_month,1,$cal_year));

$prev_month = $cal_month - 1; $prev_year = $cal_year;
if ($prev_month < 1)  { $prev_month = 12; $prev_year--; }
$next_month = $cal_month + 1; $next_year = $cal_year;
if ($next_month > 12) { $next_month = 1;  $next_year++; }

$errors  = [];
$success = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $can_manage) {
    $action = $_POST['action'] ?? '';

    if ($action === 'add' || $action === 'edit') {
        $name        = trim($_POST['name']           ?? '');
        $description = trim($_POST['description']    ?? '');
        $date        = trim($_POST['date']            ?? '');
        $end_date    = trim($_POST['end_date']        ?? '') ?: null;
        $time        = trim($_POST['time']            ?? '') ?: null;
        $event_type  = trim($_POST['event_type']      ?? 'other');
        $duration    = (int)($_POST['duration_hours'] ?? 1) ?: 1;
        $event_id    = (int)($_POST['event_id']       ?? 0);

        if ($name === '') $errors[] = "Event name is required.";
        if ($date === '') $errors[] = "Date is required.";

        if (empty($errors)) {
            if ($action === 'add') {
                $stmt = $conn->prepare("INSERT INTO events (name, description, date, end_date, duration_hours, event_type, time, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
                $stmt->bind_param("ssssissi", $name, $description, $date, $end_date, $duration, $event_type, $time, $current_user_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Event \"{$name}\" added successfully.";
                } else {
                    $_SESSION['error'] = "Database error: " . $conn->error;
                }
                $stmt->close();
            } else {
                $stmt = $conn->prepare("UPDATE events SET name=?, description=?, date=?, end_date=?, duration_hours=?, event_type=?, time=?, updated_at=NOW() WHERE id=?");
                $stmt->bind_param("ssssissi", $name, $description, $date, $end_date, $duration, $event_type, $time, $event_id);
                if ($stmt->execute()) {
                    $_SESSION['success'] = "Event \"{$name}\" updated successfully.";
                } else {
                    $_SESSION['error'] = "Database error: " . $conn->error;
                }
                $stmt->close();
            }
            header("Location: /church/modules/events/index.php?year={$cal_year}&month={$cal_month}");
            exit;
        }

    } elseif ($action === 'delete') {
        $event_id = (int)($_POST['event_id'] ?? 0);
        if ($event_id > 0) {
            $del = $conn->prepare("DELETE FROM events WHERE id = ?");
            $del->bind_param("i", $event_id);
            $del->execute();
            $del->close();
            $_SESSION['success'] = "Event deleted.";
        }
        header("Location: /church/modules/events/index.php?year={$cal_year}&month={$cal_month}");
        exit;
    }
}

$flash_success = $_SESSION['success'] ?? null;
$flash_error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$month_events = [];
$eq = $conn->prepare("
    SELECT e.*, u.name AS added_by_name
    FROM events e
    LEFT JOIN users u ON u.id = e.created_by
    WHERE YEAR(e.date) = ? AND MONTH(e.date) = ?
    ORDER BY e.date ASC, e.time ASC
");
$eq->bind_param("ii", $cal_year, $cal_month);
$eq->execute();
$res = $eq->get_result();
while ($row = $res->fetch_assoc()) {
    $day = (int)date('j', strtotime($row['date']));
    $month_events[$day][] = $row;
}
$eq->close();

$upcoming = [];
$uq = $conn->query("
    SELECT * FROM events
    WHERE date >= CURDATE() AND date <= DATE_ADD(CURDATE(), INTERVAL 30 DAY)
    ORDER BY date ASC, time ASC
    LIMIT 10
");
while ($row = $uq->fetch_assoc()) $upcoming[] = $row;

$edit_event = null;
if ($can_manage && isset($_GET['edit'])) {
    $eid = (int)$_GET['edit'];
    $eq2 = $conn->prepare("SELECT * FROM events WHERE id = ?");
    $eq2->bind_param("i", $eid);
    $eq2->execute();
    $edit_event = $eq2->get_result()->fetch_assoc();
    $eq2->close();
}

$page_title = "Events & Calendar";
include $root . '/includes/header.php';
?>

<style>
    .events-layout {
        display: grid;
        grid-template-columns: 1fr 290px;
        gap: 20px;
        align-items: start;
    }

    .cal-card {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .cal-card-header {
        padding: 16px 22px;
        border-bottom: 1px solid #f3ede3;
        display: flex;
        align-items: center;
        justify-content: space-between;
        gap: 10px;
        flex-wrap: wrap;
    }
    .cal-nav-btn {
        display: inline-flex; align-items: center; justify-content: center;
        width: 32px; height: 32px; border-radius: 8px;
        border: 1px solid #ede8de; background: #fff;
        color: #6b7280; cursor: pointer; text-decoration: none;
        font-size: 0.75rem; transition: all 0.15s;
        flex-shrink: 0;
    }
    .cal-nav-btn:hover { background: #eff6ff; border-color: #3b82f6; color: #3b82f6; }
    .cal-month-title {
        font-family: 'Playfair Display', serif;
        font-size: 1.1rem; font-weight: 700; color: #0f2044;
    }
    .cal-grid {
        display: grid;
        grid-template-columns: repeat(7, 1fr);
    }
    .cal-dow {
        padding: 10px 4px 8px;
        text-align: center;
        font-size: 0.65rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        color: #c4b89a;
        border-bottom: 1px solid #f5f0e8;
    }
    .cal-cell {
        min-height: 88px;
        padding: 6px 8px 8px;
        border-right: 1px solid #f5f0e8;
        border-bottom: 1px solid #f5f0e8;
        vertical-align: top;
        cursor: default;
        transition: background 0.12s;
        position: relative;
    }
    .cal-cell:nth-child(7n) { border-right: none; }
    .cal-cell.other-month { background: #faf9f7; }
    .cal-cell.today { background: #eff6ff; }
    .cal-cell.today .cal-day-num { color: #2563eb; font-weight: 700; }
    .cal-cell.has-events { cursor: pointer; }
    .cal-cell.has-events:hover { background: #faf7f0; }
    .cal-day-num {
        font-size: 0.78rem;
        font-weight: 500;
        color: #374151;
        margin-bottom: 4px;
        line-height: 1;
    }
    .cal-event-pill {
        display: block;
        font-size: 0.68rem;
        font-weight: 500;
        padding: 2px 6px;
        border-radius: 4px;
        margin-bottom: 2px;
        white-space: nowrap;
        overflow: hidden;
        text-overflow: ellipsis;
        line-height: 1.4;
        cursor: pointer;
        transition: opacity 0.12s;
    }
    .cal-event-pill:hover { opacity: 0.8; }
    .pill-blue    { background: #dbeafe; color: #1d4ed8; }
    .pill-green   { background: #d1fae5; color: #065f46; }
    .pill-teal    { background: #ccfbf1; color: #0f766e; }
    .pill-amber   { background: #fef3c7; color: #92400e; }
    .pill-purple  { background: #ede9fe; color: #5b21b6; }
    .pill-rose    { background: #ffe4e6; color: #9f1239; }
    .pill-more {
        font-size: 0.65rem; color: #9ca3af;
        padding: 1px 6px; cursor: pointer;
    }
    .pill-more:hover { color: #3b82f6; }

    .cal-add-btn {
        position: absolute;
        top: 4px; right: 4px;
        width: 18px; height: 18px;
        border-radius: 4px;
        background: rgba(59,130,246,0.12);
        border: none;
        color: #3b82f6;
        font-size: 0.65rem;
        display: none;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background 0.12s;
    }
    .cal-cell:hover .cal-add-btn { display: flex; }
    .cal-add-btn:hover { background: #3b82f6; color: #fff; }

    .event-list-row {
        display: grid;
        grid-template-columns: 90px 1fr auto;
        gap: 12px;
        align-items: center;
        padding: 12px 22px;
        border-bottom: 1px solid #f5f0e8;
        transition: background 0.12s;
    }
    .event-list-row:last-child { border-bottom: none; }
    .event-list-row:hover { background: #faf7f0; }
    .event-date-badge {
        text-align: center;
        padding: 6px 10px;
        border-radius: 8px;
        background: #f5f0e8;
    }
    .event-date-day   { font-size: 1.3rem; font-weight: 700; color: #0f2044; line-height: 1; }
    .event-date-month { font-size: 0.65rem; font-weight: 600; letter-spacing: 0.1em; text-transform: uppercase; color: #9ca3af; margin-top: 2px; }
    .event-date-badge.today-badge { background: #dbeafe; }
    .event-date-badge.today-badge .event-date-day { color: #2563eb; }
    .event-name { font-size: 0.88rem; font-weight: 600; color: #0f2044; margin-bottom: 2px; }
    .event-meta { font-size: 0.75rem; color: #9ca3af; display: flex; align-items: center; gap: 8px; flex-wrap: wrap; }
    .event-actions { display: flex; gap: 6px; flex-shrink: 0; }
    .act-sm {
        display: inline-flex; align-items: center; gap: 4px;
        padding: 5px 10px; border-radius: 6px;
        font-size: 0.75rem; font-weight: 500;
        text-decoration: none; border: none; cursor: pointer;
        transition: all 0.12s;
    }
    .act-sm-gold { background: #fdf8ec; color: #b8933a; border: 1px solid #e8d99a; }
    .act-sm-gold:hover { background: #b8933a; color: #fff; border-color: #b8933a; }
    .act-sm-red  { background: #fef2f2; color: #dc2626; border: 1px solid #fecaca; }
    .act-sm-red:hover  { background: #dc2626; color: #fff; border-color: #dc2626; }

    .form-card { background: #fff; border: 1px solid #ede8de; border-radius: 14px; overflow: hidden; margin-bottom: 18px; }
    .form-card-header { padding: 14px 20px; border-bottom: 1px solid #f3ede3; display: flex; align-items: center; gap: 10px; }
    .form-card-header-icon { width: 32px; height: 32px; border-radius: 8px; display: flex; align-items: center; justify-content: center; font-size: 0.8rem; flex-shrink: 0; }
    .form-card-title { font-family: 'Playfair Display', serif; font-size: 0.88rem; font-weight: 600; color: #0f2044; }
    .form-card-body { padding: 18px 20px; }
    .form-group { display: flex; flex-direction: column; gap: 5px; margin-bottom: 14px; }
    .form-group:last-child { margin-bottom: 0; }
    .form-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
    .form-label .req { color: #ef4444; margin-left: 2px; }
    .form-label .opt { color: #9ca3af; font-weight: 400; font-size: 0.72rem; margin-left: 4px; }
    .form-input, .form-select, .form-textarea {
        padding: 9px 12px; border: 1px solid #ede8de; border-radius: 8px;
        font-size: 0.865rem; color: #1a1a2e; background: #fff;
        outline: none; transition: border-color 0.18s, box-shadow 0.18s;
        width: 100%; font-family: 'DM Sans', sans-serif;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: #3b82f6; box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .form-textarea { resize: vertical; min-height: 72px; }
    .btn-primary {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 9px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 600;
        background: #2563eb; color: #fff; border: none; cursor: pointer; transition: background 0.15s;
        width: 100%; justify-content: center;
    }
    .btn-primary:hover { background: #1d4ed8; }
    .btn-cancel {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 9px 20px; border-radius: 8px; font-size: 0.85rem; font-weight: 500;
        border: 1px solid #ede8de; background: #fff; color: #6b7280;
        text-decoration: none; transition: all 0.15s; width: 100%; justify-content: center;
        margin-top: 8px;
    }
    .btn-cancel:hover { background: #faf7f0; border-color: #c4b89a; color: #0f2044; }

    .alert { display: flex; align-items: center; gap: 10px; padding: 12px 16px; border-radius: 10px; font-size: 0.83rem; margin-bottom: 18px; }
    .alert-success { background: #f0fdf4; border: 1px solid #bbf7d0; color: #15803d; }
    .alert-error   { background: #fef2f2; border: 1px solid #fecaca; color: #dc2626; }

    .upcoming-item { padding: 10px 0; border-bottom: 1px solid #f5f0e8; display: flex; align-items: flex-start; gap: 10px; }
    .upcoming-item:last-child { border-bottom: none; }
    .upcoming-dot { width: 8px; height: 8px; border-radius: 50%; background: #3b82f6; flex-shrink: 0; margin-top: 5px; }
    .upcoming-name { font-size: 0.82rem; font-weight: 600; color: #0f2044; margin-bottom: 2px; }
    .upcoming-date { font-size: 0.73rem; color: #9ca3af; }

    .event-modal-overlay {
        display: none; position: fixed; inset: 0;
        background: rgba(0,0,0,0.4); z-index: 999;
        align-items: center; justify-content: center; padding: 20px;
    }
    .event-modal-overlay.open { display: flex; }
    .event-modal {
        background: #fff; border-radius: 14px; width: 100%; max-width: 440px;
        overflow: hidden; box-shadow: 0 20px 60px rgba(0,0,0,0.2);
    }
    .event-modal-header { background: linear-gradient(135deg, #1e3a8a, #2563eb); padding: 18px 22px; color: #fff; }
    .event-modal-body { padding: 20px 22px; }
    .event-modal-row { display: flex; gap: 10px; margin-bottom: 12px; font-size: 0.84rem; }
    .event-modal-label { color: #9ca3af; min-width: 80px; font-size: 0.75rem; font-weight: 600; text-transform: uppercase; letter-spacing: 0.07em; }
    .event-modal-value { color: #0f2044; font-weight: 500; flex: 1; }

    .empty-cal { text-align: center; padding: 40px 20px; color: #c4b89a; }
    .empty-cal i { font-size: 2rem; opacity: 0.3; display: block; margin-bottom: 10px; }

    @media (max-width: 900px) { .events-layout { grid-template-columns: 1fr; } .cal-cell { min-height: 60px; } }
    @media (max-width: 560px) { .cal-event-pill { display: none; } .cal-cell { min-height: 44px; } }
</style>

<!-- ── Page header ─────────────────────────────────────────── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-calendar-days" style="color:#3b82f6;margin-right:8px;font-size:1rem;"></i>
            Events & Calendar
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Events</span>
        </p>
    </div>
    <?php if ($can_manage): ?>
    <button onclick="openAddModal()" class="btn-primary" style="width:auto;padding:9px 18px;">
        <i class="fas fa-plus"></i> Add Event
    </button>
    <?php endif; ?>
</div>

<!-- ── Main content ────────────────────────────────────────── -->
<div style="padding:24px 24px 60px;">

    <?php if ($flash_success): ?>
    <div class="alert alert-success auto-dismiss">
        <i class="fas fa-circle-check"></i> <?= htmlspecialchars($flash_success) ?>
    </div>
    <?php endif; ?>
    <?php if ($flash_error): ?>
    <div class="alert alert-error">
        <i class="fas fa-circle-exclamation"></i> <?= htmlspecialchars($flash_error) ?>
    </div>
    <?php endif; ?>

    <div class="events-layout">

        <!-- ── LEFT: Calendar + List ──────────────────────── -->
        <div>
            <div class="cal-card">
                <div class="cal-card-header">
                    <a href="?year=<?= $prev_year ?>&month=<?= $prev_month ?>" class="cal-nav-btn" title="Previous month">
                        <i class="fas fa-chevron-left"></i>
                    </a>
                    <div style="text-align:center;">
                        <div class="cal-month-title"><?= $month_name ?> <?= $cal_year ?></div>
                        <?php if ($cal_year === (int)date('Y') && $cal_month === (int)date('n')): ?>
                        <div style="font-size:0.7rem;color:#9ca3af;">Current month</div>
                        <?php else: ?>
                        <a href="?year=<?= date('Y') ?>&month=<?= date('n') ?>" style="font-size:0.7rem;color:#3b82f6;text-decoration:none;">Back to today</a>
                        <?php endif; ?>
                    </div>
                    <a href="?year=<?= $next_year ?>&month=<?= $next_month ?>" class="cal-nav-btn" title="Next month">
                        <i class="fas fa-chevron-right"></i>
                    </a>
                </div>

                <!-- Type legend -->
                <div style="padding:8px 22px;border-bottom:1px solid #f5f0e8;display:flex;gap:10px;flex-wrap:wrap;">
                    <?php foreach ([
                        'baptism'      => ['pill-blue',   'Baptism'],
                        'communion'    => ['pill-green',  'Communion'],
                        'confirmation' => ['pill-teal',   'Confirmation'],
                        'wedding'      => ['pill-rose',   'Wedding'],
                        'funeral'      => ['pill-purple', 'Funeral'],
                        'other'        => ['pill-amber',  'Other'],
                    ] as $t => [$cls, $label]): ?>
                    <span class="cal-event-pill <?= $cls ?>" style="margin:0;padding:2px 9px;font-size:0.65rem;font-weight:600;cursor:default;">
                        <?= $label ?>
                    </span>
                    <?php endforeach; ?>
                </div>

                <!-- Day-of-week headers -->
                <div class="cal-grid">
                    <?php foreach (['Sun','Mon','Tue','Wed','Thu','Fri','Sat'] as $dow): ?>
                    <div class="cal-dow"><?= $dow ?></div>
                    <?php endforeach; ?>

                    <?php for ($i = 0; $i < $first_weekday; $i++): ?>
                    <div class="cal-cell other-month"></div>
                    <?php endfor; ?>

                    <?php
                    $type_colors = [
                        'baptism'      => 'pill-blue',
                        'communion'    => 'pill-green',
                        'confirmation' => 'pill-teal',
                        'wedding'      => 'pill-rose',
                        'funeral'      => 'pill-purple',
                        'other'        => 'pill-amber',
                    ];
                    for ($d = 1; $d <= $days_in_month; $d++):
                        $cell_date  = sprintf('%04d-%02d-%02d', $cal_year, $cal_month, $d);
                        $is_today   = ($cell_date === $today);
                        $day_events = $month_events[$d] ?? [];
                        $has_events = count($day_events) > 0;
                        $show_max   = 2;
                    ?>
                    <div class="cal-cell <?= $is_today ? 'today' : '' ?> <?= $has_events ? 'has-events' : '' ?>"
                         <?= $has_events ? "onclick=\"scrollToDay({$d})\"" : '' ?>>
                        <div class="cal-day-num"><?= $d ?></div>

                        <?php foreach (array_slice($day_events, 0, $show_max) as $ev): ?>
                        <span class="cal-event-pill <?= $type_colors[$ev['event_type'] ?? 'other'] ?? 'pill-amber' ?>"
                              onclick="event.stopPropagation(); showEventModal(<?= $ev['id'] ?>)"
                              title="<?= htmlspecialchars($ev['name']) ?>">
                            <?php if ($ev['time']): ?>
                            <span style="opacity:0.7;"><?= date('g:ia', strtotime($ev['time'])) ?></span>
                            <?php endif; ?>
                            <?= htmlspecialchars($ev['name']) ?>
                        </span>
                        <?php endforeach; ?>

                        <?php if (count($day_events) > $show_max): ?>
                        <span class="pill-more">+<?= count($day_events) - $show_max ?> more</span>
                        <?php endif; ?>

                        <?php if ($can_manage): ?>
                        <button class="cal-add-btn"
                                onclick="event.stopPropagation(); openAddModal('<?= $cell_date ?>')"
                                title="Add event on <?= $cell_date ?>">
                            <i class="fas fa-plus"></i>
                        </button>
                        <?php endif; ?>
                    </div>
                    <?php endfor; ?>

                    <?php
                    $total_cells = $first_weekday + $days_in_month;
                    $remaining   = (7 - ($total_cells % 7)) % 7;
                    for ($i = 0; $i < $remaining; $i++): ?>
                    <div class="cal-cell other-month"></div>
                    <?php endfor; ?>
                </div>
            </div>

            <!-- Events list for this month -->
            <div class="cal-card">
                <div class="cal-card-header">
                    <div style="font-family:'Playfair Display',serif;font-size:0.92rem;font-weight:600;color:#0f2044;">
                        <?= $month_name ?> <?= $cal_year ?> — All Events
                    </div>
                    <?php
                    $total_this_month = 0;
                    foreach ($month_events as $day_list) $total_this_month += count($day_list);
                    ?>
                    <span style="font-size:0.75rem;color:#9ca3af;"><?= $total_this_month ?> event<?= $total_this_month !== 1 ? 's' : '' ?></span>
                </div>

                <?php if ($total_this_month === 0): ?>
                <div class="empty-cal">
                    <i class="fas fa-calendar-days"></i>
                    <p style="font-size:0.85rem;color:#9ca3af;margin-bottom:6px;">No events this month</p>
                    <?php if ($can_manage): ?>
                    <button onclick="openAddModal()" style="font-size:0.78rem;color:#3b82f6;background:none;border:none;cursor:pointer;text-decoration:underline;">
                        + Add the first event
                    </button>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <?php
                $type_labels = [
                    'baptism'      => 'Baptism',
                    'communion'    => 'Communion',
                    'confirmation' => 'Confirmation',
                    'wedding'      => 'Wedding',
                    'funeral'      => 'Funeral',
                    'other'        => 'Other',
                ];
                $type_pill = [
                    'baptism'      => 'pill-blue',
                    'communion'    => 'pill-green',
                    'confirmation' => 'pill-teal',
                    'wedding'      => 'pill-rose',
                    'funeral'      => 'pill-purple',
                    'other'        => 'pill-amber',
                ];
                foreach ($month_events as $d => $day_list):
                foreach ($day_list as $ev):
                    $ev_date     = $ev['date'];
                    $ev_is_today = ($ev_date === $today);
                    $ev_past     = ($ev_date < $today);
                    $ev_day      = date('j', strtotime($ev_date));
                    $ev_month    = date('M', strtotime($ev_date));
                    $ev_type     = $ev['event_type'] ?? 'other';
                ?>
                <div class="event-list-row" id="event-day-<?= $ev_day ?>">
                    <div class="event-date-badge <?= $ev_is_today ? 'today-badge' : '' ?>"
                         style="<?= $ev_past && !$ev_is_today ? 'opacity:0.5;' : '' ?>">
                        <div class="event-date-day"><?= $ev_day ?></div>
                        <div class="event-date-month"><?= $ev_month ?></div>
                    </div>
                    <div>
                        <div class="event-name"><?= htmlspecialchars($ev['name']) ?></div>
                        <div class="event-meta">
                            <span class="cal-event-pill <?= $type_pill[$ev_type] ?? 'pill-amber' ?>" style="display:inline-flex;margin:0;padding:2px 8px;font-size:0.65rem;">
                                <?= $type_labels[$ev_type] ?? 'Other' ?>
                            </span>
                            <?php if ($ev['time']): ?>
                            <span><i class="fas fa-clock" style="font-size:0.65rem;margin-right:3px;"></i><?= date('g:i A', strtotime($ev['time'])) ?></span>
                            <?php endif; ?>
                            <?php if (!empty($ev['end_date']) && $ev['end_date'] !== $ev['date']): ?>
                            <span><i class="fas fa-arrow-right" style="font-size:0.6rem;margin-right:3px;opacity:0.5;"></i>ends <?= date('M j', strtotime($ev['end_date'])) ?></span>
                            <?php endif; ?>
                            <?php if ($ev['description']): ?>
                            <span style="color:#c4b89a;">·</span>
                            <span><?= htmlspecialchars(mb_strimwidth($ev['description'], 0, 60, '…')) ?></span>
                            <?php endif; ?>
                        </div>
                    </div>
                    <?php if ($can_manage): ?>
                    <div class="event-actions">
                        <button class="act-sm act-sm-gold"
                                onclick="openEditModal(<?= htmlspecialchars(json_encode($ev)) ?>)"
                                title="Edit">
                            <i class="fas fa-pen"></i>
                        </button>
                        <form method="POST" style="margin:0;" onsubmit="return confirm('Delete this event?')">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="event_id" value="<?= $ev['id'] ?>">
                            <button type="submit" class="act-sm act-sm-red" title="Delete">
                                <i class="fas fa-trash"></i>
                            </button>
                        </form>
                    </div>
                    <?php else: ?>
                    <button class="act-sm act-sm-gold" style="opacity:0.7;"
                            onclick="showEventModal(<?= $ev['id'] ?>)">
                        <i class="fas fa-eye"></i>
                    </button>
                    <?php endif; ?>
                </div>
                <?php endforeach; endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
        <!-- /left -->

        <!-- ── RIGHT SIDEBAR ──────────────────────────────── -->
        <div style="display:flex;flex-direction:column;gap:16px;">

            <!-- Upcoming events -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:14px 18px;">
                    <p style="font-family:'Playfair Display',serif;font-size:0.92rem;color:#fff;font-weight:600;margin-bottom:2px;">Upcoming Events</p>
                    <p style="font-size:0.7rem;color:rgba(255,255,255,0.5);">Next 30 days</p>
                </div>
                <div style="padding:6px 16px 12px;">
                    <?php if (empty($upcoming)): ?>
                    <div style="text-align:center;padding:24px 0;color:#c4b89a;font-size:0.8rem;">No upcoming events</div>
                    <?php else: ?>
                    <?php foreach ($upcoming as $uev): ?>
                    <?php $uev_is_today = ($uev['date'] === $today); ?>
                    <div class="upcoming-item">
                        <div class="upcoming-dot" style="<?= $uev_is_today ? 'background:#f59e0b;' : '' ?>"></div>
                        <div>
                            <div class="upcoming-name"><?= htmlspecialchars($uev['name']) ?></div>
                            <div class="upcoming-date">
                                <?= date('M j, Y', strtotime($uev['date'])) ?>
                                <?= $uev['time'] ? ' · ' . date('g:i A', strtotime($uev['time'])) : '' ?>
                                <?php if ($uev_is_today): ?>
                                <span style="display:inline-flex;align-items:center;padding:1px 6px;background:#fef3c7;color:#92400e;border-radius:4px;font-size:0.65rem;font-weight:600;margin-left:4px;">TODAY</span>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div>
            </div>

            <!-- Quick stats -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:12px;">This Month</p>
                <?php
                $past_count = 0; $future_count = 0; $today_count = 0;
                foreach ($month_events as $day_list) {
                    foreach ($day_list as $ev) {
                        if ($ev['date'] === $today)   $today_count++;
                        elseif ($ev['date'] < $today) $past_count++;
                        else                          $future_count++;
                    }
                }
                ?>
                <div style="display:flex;flex-direction:column;gap:10px;">
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                        <span style="color:#9ca3af;">Total events</span>
                        <span style="font-weight:600;color:#0f2044;"><?= $total_this_month ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                        <span style="color:#9ca3af;">Today</span>
                        <span style="font-weight:600;color:#f59e0b;"><?= $today_count ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                        <span style="color:#9ca3af;">Upcoming</span>
                        <span style="font-weight:600;color:#3b82f6;"><?= $future_count ?></span>
                    </div>
                    <div style="display:flex;justify-content:space-between;font-size:0.82rem;">
                        <span style="color:#9ca3af;">Past</span>
                        <span style="font-weight:600;color:#c4b89a;"><?= $past_count ?></span>
                    </div>
                </div>
            </div>

            <!-- Jump to month -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Jump To</p>
                <div style="display:flex;gap:8px;">
                    <select id="jumpMonth" class="form-select" style="flex:1;">
                        <?php for ($m = 1; $m <= 12; $m++): ?>
                        <option value="<?= $m ?>" <?= $m === $cal_month ? 'selected' : '' ?>>
                            <?= date('F', mktime(0,0,0,$m,1)) ?>
                        </option>
                        <?php endfor; ?>
                    </select>
                    <input type="number" id="jumpYear" class="form-input" style="width:76px;"
                           value="<?= $cal_year ?>" min="2000" max="2099">
                </div>
                <button onclick="jumpTo()" class="btn-primary" style="margin-top:10px;padding:8px 16px;font-size:0.82rem;">
                    <i class="fas fa-arrow-right" style="font-size:0.7rem;"></i> Go
                </button>
            </div>

            <!-- Back to dashboard -->
            <a href="/church/dashboard.php"
               style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:#fff;border:1px solid #ede8de;border-radius:10px;text-decoration:none;font-size:0.8rem;color:#6b7280;transition:all 0.15s;"
               onmouseover="this.style.borderColor='#3b82f6';this.style.color='#3b82f6'"
               onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
                <i class="fas fa-arrow-left" style="font-size:0.7rem;color:#3b82f6;"></i>
                Back to Dashboard
            </a>

        </div>
        <!-- /sidebar -->

    </div>
</div>

<?php if ($can_manage): ?>
<!-- ADD / EDIT MODAL -->
<div class="event-modal-overlay" id="eventFormModal">
    <div class="event-modal" style="max-width:480px;">
        <div class="event-modal-header">
            <p style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:2px;" id="modalTitle">Add Event</p>
            <p style="font-size:0.72rem;color:rgba(255,255,255,0.5);" id="modalSubtitle">New parish event</p>
        </div>
        <div class="event-modal-body">
            <form method="POST" id="eventForm">
                <input type="hidden" name="action"   id="formAction"  value="add">
                <input type="hidden" name="event_id" id="formEventId" value="">

                <div class="form-group">
                    <label class="form-label">Event Name <span class="req">*</span></label>
                    <input type="text" name="name" id="formName" class="form-input"
                           placeholder="e.g. Parish Fiesta Mass" required>
                </div>

                <div class="form-group">
                    <label class="form-label">Event Type <span class="req">*</span></label>
                    <select name="event_type" id="formType" class="form-select" onchange="onTypeChange(this)">
                        <option value="other">Other / General</option>
                        <option value="baptism">Baptism</option>
                        <option value="communion">Communion / First Holy Communion</option>
                        <option value="confirmation">Confirmation</option>
                        <option value="wedding">Wedding</option>
                        <option value="funeral">Funeral</option>
                    </select>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Date <span class="req">*</span></label>
                        <input type="date" name="date" id="formDate" class="form-input" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">End Date <span class="opt">(optional)</span></label>
                        <input type="date" name="end_date" id="formEndDate" class="form-input">
                        <span style="font-size:0.7rem;color:#9ca3af;" id="endDateHint">For multi-day events</span>
                    </div>
                </div>

                <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;">
                    <div class="form-group">
                        <label class="form-label">Time <span class="opt">(optional)</span></label>
                        <input type="time" name="time" id="formTime" class="form-input">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Duration <span class="opt">(hrs)</span></label>
                        <input type="number" name="duration_hours" id="formDuration" class="form-input"
                               min="1" max="168" value="1">
                    </div>
                </div>

                <div class="form-group">
                    <label class="form-label">Description <span class="opt">(optional)</span></label>
                    <textarea name="description" id="formDesc" class="form-textarea" rows="3"
                              placeholder="Brief description of the event…"></textarea>
                </div>

                <div style="display:flex;gap:8px;margin-top:6px;">
                    <button type="submit" class="btn-primary" id="formSubmitBtn">
                        <i class="fas fa-floppy-disk"></i> <span id="formSubmitLabel">Save Event</span>
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeFormModal()">
                        <i class="fas fa-times"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>
</div>
<?php endif; ?>

<!-- EVENT DETAIL MODAL -->
<div class="event-modal-overlay" id="eventViewModal">
    <div class="event-modal" style="max-width:420px;">
        <div class="event-modal-header" id="viewModalHeader">
            <p style="font-family:'Playfair Display',serif;font-size:1rem;font-weight:700;margin-bottom:2px;" id="viewModalName">—</p>
            <p style="font-size:0.72rem;color:rgba(255,255,255,0.5);" id="viewModalDate">—</p>
        </div>
        <div class="event-modal-body">
            <div class="event-modal-row">
                <span class="event-modal-label">Type</span>
                <span class="event-modal-value" id="viewType">—</span>
            </div>
            <div class="event-modal-row">
                <span class="event-modal-label">Date</span>
                <span class="event-modal-value" id="viewDate">—</span>
            </div>
            <div class="event-modal-row">
                <span class="event-modal-label">Time</span>
                <span class="event-modal-value" id="viewTime">—</span>
            </div>
            <div class="event-modal-row" id="viewEndDateRow" style="display:none;">
                <span class="event-modal-label">Ends</span>
                <span class="event-modal-value" id="viewEndDate">—</span>
            </div>
            <div class="event-modal-row" id="viewDescRow" style="display:none;">
                <span class="event-modal-label">Details</span>
                <span class="event-modal-value" id="viewDesc" style="white-space:pre-wrap;"></span>
            </div>
            <div style="display:flex;justify-content:flex-end;margin-top:8px;">
                <button onclick="closeViewModal()" class="btn-cancel" style="width:auto;">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
const ALL_EVENTS = <?= json_encode(array_merge(...array_values($month_events) ?: [[]])) ?>;

function onTypeChange(sel) {
    const hint = document.getElementById('endDateHint');
    const dur  = document.getElementById('formDuration');
    if (sel.value === 'funeral') {
        hint.textContent = 'Funeral typically spans ~1 week';
        hint.style.color = '#b45309';
        dur.value = 168;
    } else {
        hint.textContent = 'For multi-day events';
        hint.style.color = '#9ca3af';
        if (dur.value == 168) dur.value = 1;
    }
}

function openAddModal(prefillDate) {
    const modal = document.getElementById('eventFormModal');
    document.getElementById('formAction').value         = 'add';
    document.getElementById('formEventId').value        = '';
    document.getElementById('formName').value           = '';
    document.getElementById('formType').value           = 'other';
    document.getElementById('formDate').value           = prefillDate || '<?= $today ?>';
    document.getElementById('formTime').value           = '';
    document.getElementById('formEndDate').value        = '';
    document.getElementById('formDuration').value       = '1';
    document.getElementById('formDesc').value           = '';
    document.getElementById('modalTitle').textContent       = 'Add Event';
    document.getElementById('modalSubtitle').textContent    = 'New parish event';
    document.getElementById('formSubmitLabel').textContent  = 'Save Event';
    onTypeChange(document.getElementById('formType'));
    modal.classList.add('open');
    setTimeout(() => document.getElementById('formName').focus(), 100);
}

function openEditModal(ev) {
    const modal = document.getElementById('eventFormModal');
    document.getElementById('formAction').value         = 'edit';
    document.getElementById('formEventId').value        = ev.id;
    document.getElementById('formName').value           = ev.name;
    document.getElementById('formType').value           = ev.event_type || 'other';
    document.getElementById('formDate').value           = ev.date;
    document.getElementById('formTime').value           = ev.time || '';
    document.getElementById('formEndDate').value        = ev.end_date || '';
    document.getElementById('formDuration').value       = ev.duration_hours || 1;
    document.getElementById('formDesc').value           = ev.description || '';
    document.getElementById('modalTitle').textContent       = 'Edit Event';
    document.getElementById('modalSubtitle').textContent    = ev.name;
    document.getElementById('formSubmitLabel').textContent  = 'Save Changes';
    onTypeChange(document.getElementById('formType'));
    modal.classList.add('open');
    setTimeout(() => document.getElementById('formName').focus(), 100);
}

function closeFormModal() {
    document.getElementById('eventFormModal').classList.remove('open');
}

function showEventModal(id) {
    const ev = ALL_EVENTS.find(e => e.id == id);
    if (!ev) return;

    const d       = new Date(ev.date + 'T00:00:00');
    const dateStr = d.toLocaleDateString('en-PH', { weekday:'long', year:'numeric', month:'long', day:'numeric' });
    const timeStr = ev.time
        ? new Date('1970-01-01T' + ev.time).toLocaleTimeString('en-PH', { hour:'numeric', minute:'2-digit', hour12:true })
        : '—';

    const typeLabels = {
        baptism:      'Baptism',
        communion:    'Communion',
        confirmation: 'Confirmation',
        wedding:      'Wedding',
        funeral:      'Funeral',
        other:        'Other / General',
    };

    document.getElementById('viewModalName').textContent = ev.name;
    document.getElementById('viewModalDate').textContent = dateStr;
    document.getElementById('viewType').textContent      = typeLabels[ev.event_type] || 'Other';
    document.getElementById('viewDate').textContent      = dateStr;
    document.getElementById('viewTime').textContent      = timeStr;

    const endRow = document.getElementById('viewEndDateRow');
    if (ev.end_date && ev.end_date !== ev.date) {
        const ed = new Date(ev.end_date + 'T00:00:00');
        document.getElementById('viewEndDate').textContent = ed.toLocaleDateString('en-PH', { year:'numeric', month:'long', day:'numeric' });
        endRow.style.display = 'flex';
    } else {
        endRow.style.display = 'none';
    }

    const descRow = document.getElementById('viewDescRow');
    if (ev.description) {
        document.getElementById('viewDesc').textContent = ev.description;
        descRow.style.display = 'flex';
    } else {
        descRow.style.display = 'none';
    }

    document.getElementById('eventViewModal').classList.add('open');
}

function closeViewModal() {
    document.getElementById('eventViewModal').classList.remove('open');
}

document.getElementById('eventViewModal').addEventListener('click', function(e) {
    if (e.target === this) closeViewModal();
});
<?php if ($can_manage): ?>
document.getElementById('eventFormModal').addEventListener('click', function(e) {
    if (e.target === this) closeFormModal();
});
<?php endif; ?>

function jumpTo() {
    const m = document.getElementById('jumpMonth').value;
    const y = document.getElementById('jumpYear').value;
    window.location.href = `/church/modules/events/index.php?year=${y}&month=${m}`;
}

function scrollToDay(day) {
    const el = document.getElementById('event-day-' + day);
    if (el) el.scrollIntoView({ behavior: 'smooth', block: 'center' });
}

document.addEventListener('keydown', e => {
    if (e.key === 'Escape') { closeFormModal(); closeViewModal(); }
});
</script>

<?php include $root . '/includes/footer.php'; ?>