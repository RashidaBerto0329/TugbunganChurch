<?php
// church/modules/finance/donations.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$success = $_SESSION['success'] ?? null;
$error   = $_SESSION['error']   ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$search   = trim($_GET['q']      ?? '');
$type_f   = trim($_GET['type']   ?? '');
$year_f   = (int)($_GET['year']  ?? 0);
$month_f  = (int)($_GET['month'] ?? 0);
$sort     = trim($_GET['sort']   ?? 'date_desc');
$page_num = max(1, (int)($_GET['p'] ?? 1));
$per_page = 15;

$where_parts = ["1=1"];
$params = []; $types = "";

if ($search !== '') {
    $where_parts[] = "(donor_name LIKE ? OR description LIKE ?)";
    $like = "%{$search}%";
    $params[] = $like; $params[] = $like; $types .= "ss";
}
if ($type_f !== '')  { $where_parts[] = "donation_type = ?";   $params[] = $type_f;  $types .= "s"; }
if ($year_f > 0)     { $where_parts[] = "YEAR(date) = ?";      $params[] = $year_f;  $types .= "i"; }
if ($month_f > 0)    { $where_parts[] = "MONTH(date) = ?";     $params[] = $month_f; $types .= "i"; }

$where_sql = implode(' AND ', $where_parts);
$order_sql = match($sort) {
    'date_asc'   => 'date ASC, created_at ASC',
    'name_asc'   => 'donor_name ASC',
    'amount_desc'=> 'amount DESC',
    'amount_asc' => 'amount ASC',
    default      => 'date DESC, created_at DESC',
};

if ($types) {
    $cnt = $conn->prepare("SELECT COUNT(*) FROM donations WHERE {$where_sql}");
    $cnt->bind_param($types, ...$params); $cnt->execute();
    $total = (int)$cnt->get_result()->fetch_row()[0]; $cnt->close();
} else {
    $total = (int)$conn->query("SELECT COUNT(*) FROM donations")->fetch_row()[0];
}

$total_pages = max(1, (int)ceil($total / $per_page));
$page_num    = min($page_num, $total_pages);
$offset      = ($page_num - 1) * $per_page;

$fp = array_merge($params, [$per_page, $offset]);
$ft = $types . "ii";
if ($ft !== "ii") {
    $stmt = $conn->prepare("SELECT * FROM donations WHERE {$where_sql} ORDER BY {$order_sql} LIMIT ? OFFSET ?");
    $stmt->bind_param($ft, ...$fp);
} else {
    $stmt = $conn->prepare("SELECT * FROM donations ORDER BY {$order_sql} LIMIT ? OFFSET ?");
    $stmt->bind_param("ii", $per_page, $offset);
}
$stmt->execute();
$rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
$stmt->close();

// Banner stats — full table always
$banner = $conn->query("
    SELECT
        COUNT(*) as total,
        COALESCE(SUM(CASE WHEN donation_type='cash' THEN amount ELSE 0 END),0) as cash_total,
        COALESCE(SUM(CASE WHEN donation_type='sacramental_fee' THEN amount ELSE 0 END),0) as sacr_total,
        COUNT(CASE WHEN donation_type='in-kind' THEN 1 END) as inkind_count
    FROM donations
")->fetch_assoc();

// Filtered summary
if ($types) {
    $sm = $conn->prepare("
        SELECT
            COALESCE(SUM(CASE WHEN donation_type='cash' THEN amount ELSE 0 END),0),
            COALESCE(SUM(CASE WHEN donation_type='sacramental_fee' THEN amount ELSE 0 END),0),
            COUNT(CASE WHEN donation_type='in-kind' THEN 1 END)
        FROM donations WHERE {$where_sql}
    ");
    $sm->bind_param($types, ...$params); $sm->execute();
    [$sum_cash, $sum_sacr, $cnt_inkind] = $sm->get_result()->fetch_row(); $sm->close();
} else {
    $sum_cash   = $banner['cash_total'];
    $sum_sacr   = $banner['sacr_total'];
    $cnt_inkind = $banner['inkind_count'];
}

$years = $conn->query("SELECT DISTINCT YEAR(date) AS y FROM donations ORDER BY y DESC")->fetch_all(MYSQLI_ASSOC);

function page_url_d($p, $s, $t, $y, $m, $so) {
    $a = ['p'=>$p];
    if ($s) $a['q']=$s; if ($t) $a['type']=$t;
    if ($y) $a['year']=$y; if ($m) $a['month']=$m;
    if ($so!=='date_desc') $a['sort']=$so;
    return '?'.http_build_query($a);
}

$page_title = "Donations";
include $root . '/includes/header.php';
?>
<style>
    .fin-banner{border-radius:14px;padding:22px 28px;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;margin-bottom:24px;position:relative;overflow:hidden;}
    .fin-banner::after{content:'';position:absolute;right:-20px;top:-20px;width:160px;height:160px;border-radius:50%;background:rgba(255,255,255,0.04);pointer-events:none;}
    .fin-banner::before{content:'';position:absolute;right:60px;bottom:-30px;width:100px;height:100px;border-radius:50%;background:rgba(255,255,255,0.03);pointer-events:none;}
    .fin-banner-left{display:flex;align-items:center;gap:18px;}
    .fin-banner-icon{width:52px;height:52px;border-radius:14px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;flex-shrink:0;}
    .fin-banner-title{font-family:'Playfair Display',serif;font-size:1.1rem;color:#fff;font-weight:600;margin-bottom:3px;}
    .fin-banner-sub{font-size:0.78rem;color:rgba(255,255,255,0.45);}
    .fin-banner-stats{display:flex;gap:24px;align-items:center;flex-wrap:nowrap;}
    .fin-banner-stat{text-align:center;}
    .fin-banner-stat-value{font-family:'Playfair Display',serif;font-size:1.4rem;font-weight:600;line-height:1;color:var(--baccent,#bbf7d0);}
    .fin-banner-stat-label{font-size:0.65rem;color:rgba(255,255,255,0.4);text-transform:uppercase;letter-spacing:0.06em;margin-top:3px;}
    .fin-banner-divider{width:1px;height:40px;background:rgba(255,255,255,0.1);flex-shrink:0;}
    .fin-table-wrap{background:#fff;border:1px solid #d1cdc4;border-radius:14px;overflow:hidden;box-shadow:0 2px 16px rgba(15,32,68,0.07);}
    .fin-table-head{padding:15px 20px;border-bottom:2px solid #f0ebe0;background:#fdfcfa;display:flex;align-items:center;justify-content:space-between;flex-wrap:wrap;gap:8px;}
    .fin-table-title{font-family:'Playfair Display',serif;font-size:0.92rem;font-weight:600;color:#0f2044;display:flex;align-items:center;gap:8px;}
    .badge{display:inline-flex;align-items:center;gap:3px;font-size:0.72rem;font-weight:600;padding:2px 9px;border-radius:99px;}
    .badge-green {background:#f0fdf4;color:#16a34a;}
    .badge-amber {background:#fef3c7;color:#d97706;}
    .badge-purple{background:#f5f3ff;color:#7c3aed;}
    .table{width:100%;border-collapse:collapse;font-size:0.85rem;}
    .table th{background:#f5f0e8;padding:11px 16px;text-align:left;font-size:0.7rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#6b5f4e;border-bottom:2px solid #e8e0d0;white-space:nowrap;}
    .table td{padding:12px 16px;border-bottom:1px solid #f3ede3;color:#4a4a6a;vertical-align:middle;}
    .table tr:last-child td{border-bottom:none;}
    .table tbody tr:hover{background:#faf8f5;}
    .table tbody tr.row-sacr{background:#faf5ff;}
    .table tbody tr.row-sacr:hover{background:#f3f0ff;}
    .toolbar{display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:16px;}
    .search-wrap{position:relative;flex:1;min-width:200px;max-width:320px;}
    .search-wrap i{position:absolute;left:11px;top:50%;transform:translateY(-50%);color:#c4b89a;font-size:0.82rem;pointer-events:none;}
    .search-input{width:100%;padding:8px 12px 8px 32px;border:1px solid #d1cdc4;border-radius:8px;font-size:0.845rem;color:#1a1a2e;background:#fff;outline:none;}
    .search-input:focus{border-color:#16a34a;box-shadow:0 0 0 3px rgba(22,163,74,0.1);}
    .filter-select{padding:8px 10px;border:1px solid #d1cdc4;border-radius:8px;font-size:0.82rem;color:#374151;background:#fff;outline:none;cursor:pointer;font-family:'DM Sans',sans-serif;}
    .toolbar-btn{display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:0.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all 0.18s;font-family:'DM Sans',sans-serif;}
    .btn-green{background:#16a34a;color:#fff;} .btn-green:hover{background:#15803d;}
    .btn-outline{background:#fff;color:#6b7280;border:1px solid #d1cdc4;} .btn-outline:hover{background:#faf7f0;color:#0f2044;}
    .pagination{display:flex;align-items:center;justify-content:space-between;padding:14px 20px;border-top:1px solid #f0ebe0;background:#fdfcfa;flex-wrap:wrap;gap:10px;}
    .pagination-info{font-size:0.78rem;color:#9ca3af;}
    .page-btn{display:inline-flex;align-items:center;justify-content:center;width:32px;height:32px;border-radius:6px;font-size:0.78rem;font-weight:500;text-decoration:none;border:1px solid #d1cdc4;color:#6b7280;background:#fff;transition:all 0.15s;}
    .page-btn:hover{border-color:#16a34a;color:#16a34a;}
    .page-btn.active{background:#16a34a;color:#fff;border-color:#16a34a;}
    .page-btn.disabled{opacity:0.35;pointer-events:none;}
    .alert{display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.83rem;margin-bottom:18px;}
    .alert-success{background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;}
    .alert-error{background:#fef2f2;border:1px solid #fecaca;color:#dc2626;}
    .action-btn{display:inline-flex;align-items:center;gap:4px;font-size:0.73rem;font-weight:500;padding:4px 10px;border-radius:6px;text-decoration:none;transition:all 0.15s;cursor:pointer;border:none;background:none;}
    .ab-red{color:#dc2626;background:#fef2f2;} .ab-red:hover{background:#dc2626;color:#fff;}
    @media(max-width:768px){.fin-banner-stats{display:none;}}
    @media(max-width:640px){.fin-banner{flex-direction:column;align-items:flex-start;}}
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-hand-holding-heart" style="color:#16a34a;margin-right:8px;font-size:1rem;"></i>Donations</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/finance/index.php" style="color:#9ca3af;text-decoration:none;">Finance</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Donations</span>
        </p>
    </div>
    <?php if (in_array($current_user_role, ['admin', 'clergy', 'finance'])): ?>
    <a href="/church/modules/finance/add_donation.php" class="toolbar-btn btn-green">
        <i class="fas fa-plus"></i> Add Donation
    </a>
    <?php endif; ?>
</div>

<div style="padding:24px 28px 60px;">
    <?php if ($success): ?><div class="alert alert-success"><i class="fas fa-circle-check"></i><?= htmlspecialchars($success) ?></div><?php endif; ?>
    <?php if ($error): ?><div class="alert alert-error"><i class="fas fa-circle-exclamation"></i><?= htmlspecialchars($error) ?></div><?php endif; ?>

    <!-- ── Banner ── -->
    <div class="fin-banner" style="background:linear-gradient(135deg,#14532d 0%,#16a34a 60%,#22c55e 100%);--baccent:#bbf7d0;">
        <div class="fin-banner-left">
            <div class="fin-banner-icon"><i class="fas fa-hand-holding-heart"></i></div>
            <div>
                <p class="fin-banner-title">Donations</p>
                <p class="fin-banner-sub">Cash donations, in-kind gifts & sacramental fees</p>
            </div>
        </div>
        <div class="fin-banner-stats">
            <div class="fin-banner-stat">
                <div class="fin-banner-stat-value"><?= number_format($banner['total']) ?></div>
                <div class="fin-banner-stat-label">Records</div>
            </div>
            <div class="fin-banner-divider"></div>
            <div class="fin-banner-stat">
                <div class="fin-banner-stat-value">₱<?= number_format($banner['cash_total'], 0) ?></div>
                <div class="fin-banner-stat-label">Cash</div>
            </div>
            <div class="fin-banner-divider"></div>
            <div class="fin-banner-stat">
                <div class="fin-banner-stat-value" style="color:#ddd6fe;">₱<?= number_format($banner['sacr_total'], 0) ?></div>
                <div class="fin-banner-stat-label">Sacramental</div>
            </div>
            <div class="fin-banner-divider"></div>
            <div class="fin-banner-stat">
                <div class="fin-banner-stat-value"><?= number_format($banner['inkind_count']) ?></div>
                <div class="fin-banner-stat-label">In-Kind</div>
            </div>
        </div>
    </div>

    <form method="GET" id="filterForm">
        <div class="toolbar">
            <div class="search-wrap">
                <i class="fas fa-magnifying-glass"></i>
                <input type="text" name="q" class="search-input" placeholder="Search donor, description…"
                       value="<?= htmlspecialchars($search) ?>" autocomplete="off">
            </div>
            <select name="type" class="filter-select" onchange="this.form.submit()">
                <option value="">All Types</option>
                <option value="cash"           <?= $type_f==='cash'           ?'selected':'' ?>>Cash</option>
                <option value="in-kind"        <?= $type_f==='in-kind'        ?'selected':'' ?>>In-Kind</option>
                <option value="sacramental_fee"<?= $type_f==='sacramental_fee'?'selected':'' ?>>Sacramental Fee</option>
            </select>
            <select name="year" class="filter-select" onchange="this.form.submit()">
                <option value="">All Years</option>
                <?php foreach ($years as $yr): ?>
                <option value="<?= $yr['y'] ?>" <?= $year_f==$yr['y']?'selected':'' ?>><?= $yr['y'] ?></option>
                <?php endforeach; ?>
            </select>
            <select name="month" class="filter-select" onchange="this.form.submit()">
                <option value="">All Months</option>
                <?php for ($m=1;$m<=12;$m++): ?>
                <option value="<?= $m ?>" <?= $month_f==$m?'selected':'' ?>><?= date('F',mktime(0,0,0,$m,1)) ?></option>
                <?php endfor; ?>
            </select>
            <select name="sort" class="filter-select" onchange="this.form.submit()">
                <option value="date_desc"  <?= $sort==='date_desc'  ?'selected':'' ?>>Newest First</option>
                <option value="date_asc"   <?= $sort==='date_asc'   ?'selected':'' ?>>Oldest First</option>
                <option value="amount_desc"<?= $sort==='amount_desc'?'selected':'' ?>>Highest Amount</option>
                <option value="name_asc"   <?= $sort==='name_asc'   ?'selected':'' ?>>Donor A–Z</option>
            </select>
            <button type="submit" class="toolbar-btn btn-green"><i class="fas fa-magnifying-glass"></i> Search</button>
            <?php if ($search||$type_f||$year_f||$month_f): ?>
            <a href="?" class="toolbar-btn btn-outline"><i class="fas fa-times"></i> Clear</a>
            <?php endif; ?>
        </div>
    </form>

    <?php if (($search||$type_f||$year_f||$month_f) && $total > 0): ?>
    <div style="display:flex;align-items:center;gap:16px;padding:10px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:10px;margin-bottom:16px;font-size:0.8rem;color:#15803d;flex-wrap:wrap;">
        <span><i class="fas fa-filter" style="margin-right:5px;"></i><strong><?= $total ?></strong> result<?= $total!==1?'s':'' ?></span>
        <?php if ($sum_cash > 0): ?><span>Cash: <strong>₱<?= number_format($sum_cash, 2) ?></strong></span><?php endif; ?>
        <?php if ($sum_sacr > 0): ?><span style="color:#7c3aed;">Sacramental: <strong>₱<?= number_format($sum_sacr, 2) ?></strong></span><?php endif; ?>
        <?php if ($cnt_inkind > 0): ?><span>In-kind: <strong><?= $cnt_inkind ?></strong></span><?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="fin-table-wrap">
        <div class="fin-table-head">
            <div class="fin-table-title">
                <i class="fas fa-hand-holding-heart" style="color:#16a34a;font-size:0.78rem;"></i>
                Donation Records
                <span class="badge badge-green"><?= number_format($total) ?></span>
                <?php if ($search||$type_f||$year_f||$month_f): ?>
                <span style="font-size:0.72rem;color:#9ca3af;font-weight:400;">— filtered</span>
                <?php endif; ?>
            </div>
            <?php if ($total_pages > 1): ?><div style="font-size:0.75rem;color:#9ca3af;">Page <?= $page_num ?> of <?= $total_pages ?></div><?php endif; ?>
        </div>

        <?php if (empty($rows)): ?>
        <div style="text-align:center;padding:56px 20px;">
            <i class="fas fa-hand-holding-heart" style="font-size:2.2rem;color:#d1fae5;display:block;margin-bottom:14px;"></i>
            <h3 style="font-family:'Playfair Display',serif;font-size:1rem;color:#0f2044;margin-bottom:6px;">No donations found</h3>
            <p style="font-size:0.82rem;color:#9ca3af;margin-bottom:16px;"><?= ($search||$type_f||$year_f||$month_f)?'Try adjusting your filters.':'No donations recorded yet.' ?></p>
            <?php if (in_array($current_user_role,['admin','clergy','finance'])): ?>
            <a href="/church/modules/finance/add_donation.php" class="toolbar-btn btn-green" style="display:inline-flex;"><i class="fas fa-plus"></i> Add Donation</a>
            <?php endif; ?>
        </div>
        <?php else: ?>
        <div style="overflow-x:auto;">
        <table class="table">
            <colgroup>
                <col style="width:50px;">
                <col style="min-width:170px;">
                <col style="width:120px;">
                <col style="width:160px;">
                <col style="width:110px;">
                <col style="min-width:180px;">
                <col style="width:110px;">
            </colgroup>
            <thead><tr>
                <th>#</th>
                <th>Donor / Requestor</th>
                <th>Amount</th>
                <th>Type</th>
                <th>Date</th>
                <th>Description / Service</th>
                <th style="text-align:center;">Actions</th>
            </tr></thead>
            <tbody>
            <?php foreach ($rows as $i => $row):
                $is_sacr   = $row['donation_type'] === 'sacramental_fee';
                $is_inkind = $row['donation_type'] === 'in-kind';
            ?>
            <tr class="<?= $is_sacr ? 'row-sacr' : '' ?>">
                <td style="font-size:0.75rem;color:#9ca3af;"><?= $offset + $i + 1 ?></td>
                <td>
                    <div style="font-weight:600;color:#0f2044;font-size:0.85rem;"><?= htmlspecialchars($row['donor_name']) ?></div>
                    <?php if ($row['contact_number']): ?>
                    <div style="font-size:0.72rem;color:#9ca3af;"><i class="fas fa-phone" style="font-size:0.55rem;margin-right:3px;"></i><?= htmlspecialchars($row['contact_number']) ?></div>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($is_inkind): ?>
                    <span style="font-size:0.78rem;color:#d97706;font-style:italic;">In-kind</span>
                    <?php else: ?>
                    <span style="font-family:'Playfair Display',serif;font-weight:700;color:<?= $is_sacr?'#7c3aed':'#15803d' ?>;">
                        ₱<?= number_format($row['amount'], 2) ?>
                    </span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if ($row['donation_type']==='cash'): ?>
                    <span class="badge badge-green"><i class="fas fa-peso-sign" style="font-size:0.55rem;"></i> Cash</span>
                    <?php elseif ($is_inkind): ?>
                    <span class="badge badge-amber"><i class="fas fa-box" style="font-size:0.55rem;"></i> In-Kind</span>
                    <?php else: ?>
                    <span class="badge badge-purple"><i class="fas fa-cross" style="font-size:0.55rem;"></i> Sacramental</span>
                    <?php endif; ?>
                </td>
                <td style="font-size:0.8rem;color:#374151;white-space:nowrap;"><?= date('M j, Y', strtotime($row['date'])) ?></td>
                <td style="font-size:0.8rem;color:<?= $is_sacr?'#7c3aed':'#6b7280' ?>;">
                    <?= $row['description'] ? htmlspecialchars(mb_strimwidth($row['description'],0,55,'…')) : '<span style="color:#c4b89a;">—</span>' ?>
                </td>
                <td style="text-align:center;white-space:nowrap;">
                    <?php if (in_array($current_user_role,['admin','clergy','finance'])): ?>
                    <a href="/church/modules/finance/add_donation.php?edit=<?= $row['id'] ?>" class="action-btn" style="color:#b8933a;background:#fdf8ec;"><i class="fas fa-pen"></i></a>
                    <button onclick="delDonation(<?= $row['id'] ?>, '<?= addslashes($row['donor_name']) ?>')" class="action-btn ab-red"><i class="fas fa-trash"></i></button>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
            </tbody>
        </table>
        </div>
        <?php if ($total_pages > 1 || $total > 0): ?>
        <div class="pagination">
            <div class="pagination-info">Showing <?= $offset+1 ?>–<?= min($offset+$per_page,$total) ?> of <?= number_format($total) ?> record<?= $total!==1?'s':'' ?></div>
            <?php if ($total_pages > 1): ?>
            <div style="display:flex;gap:4px;">
                <a href="<?= page_url_d($page_num-1,$search,$type_f,$year_f,$month_f,$sort) ?>" class="page-btn <?= $page_num<=1?'disabled':'' ?>"><i class="fas fa-chevron-left" style="font-size:0.65rem;"></i></a>
                <?php $ws=max(1,$page_num-2);$we=min($total_pages,$page_num+2);
                if($ws>1){echo '<a href="'.page_url_d(1,$search,$type_f,$year_f,$month_f,$sort).'" class="page-btn">1</a>';if($ws>2)echo '<span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span>';}
                for($i=$ws;$i<=$we;$i++) echo '<a href="'.page_url_d($i,$search,$type_f,$year_f,$month_f,$sort).'" class="page-btn '.($i===$page_num?'active':'').'">'.$i.'</a>';
                if($we<$total_pages){if($we<$total_pages-1)echo '<span class="page-btn" style="pointer-events:none;border:none;color:#c4b89a;">…</span>';echo '<a href="'.page_url_d($total_pages,$search,$type_f,$year_f,$month_f,$sort).'" class="page-btn">'.$total_pages.'</a>';}?>
                <a href="<?= page_url_d($page_num+1,$search,$type_f,$year_f,$month_f,$sort) ?>" class="page-btn <?= $page_num>=$total_pages?'disabled':'' ?>"><i class="fas fa-chevron-right" style="font-size:0.65rem;"></i></a>
            </div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
        <?php endif; ?>
    </div>
</div>

<form id="delForm" method="POST" action="/church/modules/finance/delete_finance.php">
    <input type="hidden" name="table" value="donations">
    <input type="hidden" name="id" id="delId">
    <input type="hidden" name="redirect" value="/church/modules/finance/donations.php">
</form>
<script>
function delDonation(id, name) {
    if (confirm('Delete donation from "' + name + '"?\nThis cannot be undone.')) {
        document.getElementById('delId').value = id;
        document.getElementById('delForm').submit();
    }
}
</script>
<?php include $root . '/includes/footer.php'; ?>