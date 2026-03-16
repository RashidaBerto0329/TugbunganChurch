<?php
// church/modules/church_records/wedding/view_record.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /church/modules/church_records/wedding/series_list.php'); exit; }

$stmt = $conn->prepare("SELECT wr.*, u.name AS created_by_name FROM wedding_records wr LEFT JOIN users u ON u.id = wr.created_by WHERE wr.id = ? ");
$stmt->bind_param("i", $id); $stmt->execute();
$rec = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$rec) { $_SESSION['error']="Record not found."; header('Location: /church/modules/church_records/wedding/series_list.php'); exit; }

$year = (int)$rec['series_year'];
$success = $_SESSION['success'] ?? null; $error = $_SESSION['error'] ?? null;
unset($_SESSION['success'], $_SESSION['error']);

$prev_rec = $conn->query("SELECT id, groom_name, bride_name FROM wedding_records WHERE series_year={$year} AND is_archived=0 AND id<{$id} ORDER BY id DESC LIMIT 1")->fetch_assoc();
$next_rec = $conn->query("SELECT id, groom_name, bride_name FROM wedding_records WHERE series_year={$year} AND is_archived=0 AND id>{$id} ORDER BY id ASC  LIMIT 1")->fetch_assoc();

function fv($v) { return $v ? htmlspecialchars($v) : '<span style="color:#d1c9b8;font-style:italic;">—</span>'; }
function fd($d) { return $d ? date('F j, Y', strtotime($d)) : '—'; }

$page_title = htmlspecialchars($rec['groom_name']) . ' & ' . htmlspecialchars($rec['bride_name']) . ' — Wedding Record';
include $root . '/includes/header.php';
?>
<style>
    .dc { background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:18px; }
    .dch { padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px; }
    .dchi { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0; }
    .dct { font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;color:#0f2044; }
    .dcb { padding:20px 22px; }
    .dg2 { display:grid;grid-template-columns:repeat(2,1fr);gap:18px; }
    .dg3 { display:grid;grid-template-columns:repeat(3,1fr);gap:18px; }
    .df { display:flex;flex-direction:column;gap:3px; }
    .dl { font-size:0.68rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a; }
    .dv { font-size:0.88rem;color:#1a1a2e;font-weight:500;line-height:1.4; }
    .ir { display:flex;justify-content:space-between;align-items:flex-start;gap:8px;padding:9px 0;border-bottom:1px solid #f5f0e8;font-size:0.8rem; }
    .ir:last-child { border-bottom:none;padding-bottom:0; }
    .ab { display:inline-flex;align-items:center;gap:6px;padding:8px 16px;border-radius:8px;font-size:0.82rem;font-weight:600;text-decoration:none;border:none;cursor:pointer;transition:all 0.18s; }
    .ab-gold  { background:#fdf8ec;color:#b8933a;border:1px solid #e8d99a; } .ab-gold:hover  { background:#b8933a;color:#fff;border-color:#b8933a; }
    .ab-out   { background:#fff;color:#6b7280;border:1px solid #ede8de; } .ab-out:hover   { background:#faf7f0;border-color:#c4b89a;color:#0f2044; }
    .ab-amber { background:#fffbeb;color:#d97706;border:1px solid #fde68a; } .ab-amber:hover { background:#d97706;color:#fff;border-color:#d97706; }
    .ab-red   { background:#fef2f2;color:#dc2626;border:1px solid #fecaca; } .ab-red:hover   { background:#dc2626;color:#fff;border-color:#dc2626; }
    .nav-btn { display:inline-flex;align-items:center;gap:7px;padding:7px 14px;border-radius:8px;font-size:0.78rem;font-weight:500;text-decoration:none;border:1px solid #ede8de;background:#fff;color:#6b7280;transition:all 0.15s;max-width:220px; }
    .nav-btn:hover { border-color:#d97706;color:#d97706;background:#fffbeb; }
    .nav-btn.dis { opacity:0.3;pointer-events:none; }
    .section-divider { display:flex;align-items:center;gap:12px;margin:0 0 16px; }
    .section-divider-line { flex:1;height:1px;background:#f3ede3; }
    .section-divider-label { font-family:'Playfair Display',serif;font-size:0.82rem;font-weight:600;color:#0f2044;white-space:nowrap; }
    @media(max-width:900px){ #viewLayout{grid-template-columns:1fr!important;} .dg3{grid-template-columns:repeat(2,1fr);} }
    @media(max-width:560px){ .dg2,.dg3{grid-template-columns:1fr;} }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-ring" style="color:#d97706;margin-right:8px;font-size:1rem;"></i>Wedding Record</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/wedding/series_list.php" style="color:#9ca3af;text-decoration:none;">Wedding</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/wedding/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current"><?= htmlspecialchars($rec['groom_name']) ?> & <?= htmlspecialchars($rec['bride_name']) ?></span>
        </p>
    </div>
    <div style="display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
        <a href="/church/modules/church_records/wedding/print_certificate.php?id=<?= $id ?>" class="ab ab-out" target="_blank"><i class="fas fa-print"></i> Print</a>
        <?php if (in_array($current_user_role,['admin','clergy'])): ?>
        <a href="/church/modules/church_records/wedding/edit_record.php?id=<?= $id ?>" class="ab ab-gold"><i class="fas fa-pen"></i> Edit</a>
        <?php endif; ?>
    </div>
</div>

<div style="padding:24px 24px 60px;">

    <?php if ($success): ?>
    <div style="display:flex;align-items:center;gap:10px;padding:12px 16px;border-radius:10px;font-size:0.83rem;margin-bottom:18px;background:#f0fdf4;border:1px solid #bbf7d0;color:#15803d;">
        <i class="fas fa-circle-check"></i><?= htmlspecialchars($success) ?>
    </div>
    <?php endif; ?>

    <!-- Prev/Next nav -->
    <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:20px;flex-wrap:wrap;">
        <a href="<?= $prev_rec ? '/church/modules/church_records/wedding/view_record.php?id='.$prev_rec['id']:'#' ?>"
           class="nav-btn <?= !$prev_rec?'dis':'' ?>">
            <i class="fas fa-chevron-left" style="font-size:0.65rem;flex-shrink:0;"></i>
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;">
                <?= $prev_rec ? htmlspecialchars($prev_rec['groom_name']).' & '.htmlspecialchars($prev_rec['bride_name']) : 'No previous' ?>
            </span>
        </a>
        <a href="/church/modules/church_records/wedding/series_records.php?year=<?= $year ?>"
           style="font-size:0.78rem;color:#9ca3af;text-decoration:none;display:flex;align-items:center;gap:5px;">
            <i class="fas fa-layer-group" style="font-size:0.7rem;"></i><?= $year ?> Series
        </a>
        <a href="<?= $next_rec ? '/church/modules/church_records/wedding/view_record.php?id='.$next_rec['id']:'#' ?>"
           class="nav-btn <?= !$next_rec?'dis':'' ?>" style="justify-content:flex-end;">
            <span style="white-space:nowrap;overflow:hidden;text-overflow:ellipsis;flex:1;text-align:right;">
                <?= $next_rec ? htmlspecialchars($next_rec['groom_name']).' & '.htmlspecialchars($next_rec['bride_name']) : 'No next' ?>
            </span>
            <i class="fas fa-chevron-right" style="font-size:0.65rem;flex-shrink:0;"></i>
        </a>
    </div>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="viewLayout">

    <!-- LEFT -->
    <div>

        <!-- Hero -->
        <div style="background:linear-gradient(135deg,#b45309 0%,#d97706 60%,#f59e0b 100%);border-radius:14px;padding:24px 28px;margin-bottom:18px;position:relative;overflow:hidden;display:flex;align-items:center;justify-content:space-between;gap:20px;flex-wrap:wrap;">
            <div style="position:absolute;right:20px;top:50%;transform:translateY(-50%);font-family:'Playfair Display',serif;font-size:4rem;font-weight:700;color:rgba(255,255,255,0.05);pointer-events:none;letter-spacing:-2px;">
                <?= htmlspecialchars($rec['record_no']) ?>
            </div>
            <div style="display:flex;align-items:center;gap:18px;flex:1;min-width:0;">
                <div style="width:54px;height:54px;border-radius:14px;background:rgba(255,255,255,0.12);display:flex;align-items:center;justify-content:center;color:#fff;font-size:1.4rem;flex-shrink:0;">
                    <i class="fas fa-ring"></i>
                </div>
                <div style="min-width:0;">
                    <div style="font-family:'Playfair Display',serif;font-size:1.25rem;font-weight:700;color:#fff;margin-bottom:4px;line-height:1.2;">
                        <?= htmlspecialchars($rec['groom_name']) ?>
                        <span style="font-size:0.9rem;font-weight:400;opacity:0.6;margin:0 8px;">&</span>
                        <?= htmlspecialchars($rec['bride_name']) ?>
                    </div>
                    <div style="font-size:0.78rem;color:rgba(255,255,255,0.5);display:flex;align-items:center;gap:8px;flex-wrap:wrap;">
                        <?php if ($rec['date_of_wedding']): ?>
                        <span><i class="fas fa-calendar" style="font-size:0.65rem;margin-right:3px;"></i><?= date('F j, Y',strtotime($rec['date_of_wedding'])) ?></span>
                        <span style="color:rgba(255,255,255,0.2);">·</span>
                        <?php endif; ?>
                        <span><?= $year ?> Wedding Series</span>
                    </div>
                </div>
            </div>
            <?php if ($rec['record_no']): ?>
            <div style="display:inline-flex;align-items:center;gap:5px;background:rgba(255,255,255,0.12);color:#fcd34d;font-size:0.75rem;font-weight:600;padding:3px 10px;border-radius:6px;font-family:monospace;flex-shrink:0;">
                <i class="fas fa-hashtag" style="font-size:0.65rem;"></i><?= htmlspecialchars($rec['record_no']) ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Record Details -->
        <div class="dc">
            <div class="dch">
                <div class="dchi" style="background:#fef3c7;color:#d97706;"><i class="fas fa-hashtag"></i></div>
                <div class="dct">Record Details</div>
            </div>
            <div class="dcb">
                <div class="dg3">
                    <div class="df"><span class="dl">Record No.</span><span class="dv" style="font-family:monospace;color:#d97706;"><?= fv($rec['record_no']) ?></span></div>
                    <div class="df"><span class="dl">Series Year</span><span class="dv"><?= $year ?></span></div>
                    <div class="df"><span class="dl">Wedding Date</span><span class="dv"><?= fd($rec['date_of_wedding']) ?></span></div>
                    <div class="df"><span class="dl">Minister</span><span class="dv"><?= fv($rec['minister']) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Groom -->
        <div class="dc">
            <div class="dch">
                <div class="dchi" style="background:#eff6ff;color:#3b82f6;"><i class="fas fa-mars"></i></div>
                <div class="dct">Groom</div>
            </div>
            <div class="dcb">
                <div class="dg2">
                    <div class="df" style="grid-column:span 2;">
                        <span class="dl">Full Name</span>
                        <span class="dv" style="font-size:1rem;font-family:'Playfair Display',serif;"><?= htmlspecialchars($rec['groom_name']) ?></span>
                    </div>
                    <div class="df"><span class="dl">Date of Birth</span><span class="dv"><?= fd($rec['groom_dob']) ?></span></div>
                    <div class="df"><span class="dl">Address</span><span class="dv"><?= fv($rec['groom_address']) ?></span></div>
                    <div class="df"><span class="dl">Father's Name</span><span class="dv"><?= fv($rec['groom_father']) ?></span></div>
                    <div class="df"><span class="dl">Mother's Name</span><span class="dv"><?= fv($rec['groom_mother']) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Bride -->
        <div class="dc">
            <div class="dch">
                <div class="dchi" style="background:#fdf2f8;color:#ec4899;"><i class="fas fa-venus"></i></div>
                <div class="dct">Bride</div>
            </div>
            <div class="dcb">
                <div class="dg2">
                    <div class="df" style="grid-column:span 2;">
                        <span class="dl">Full Name</span>
                        <span class="dv" style="font-size:1rem;font-family:'Playfair Display',serif;"><?= htmlspecialchars($rec['bride_name']) ?></span>
                    </div>
                    <div class="df"><span class="dl">Date of Birth</span><span class="dv"><?= fd($rec['bride_dob']) ?></span></div>
                    <div class="df"><span class="dl">Address</span><span class="dv"><?= fv($rec['bride_address']) ?></span></div>
                    <div class="df"><span class="dl">Father's Name</span><span class="dv"><?= fv($rec['bride_father']) ?></span></div>
                    <div class="df"><span class="dl">Mother's Name</span><span class="dv"><?= fv($rec['bride_mother']) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Sponsors & Witnesses -->
        <div class="dc">
            <div class="dch">
                <div class="dchi" style="background:#fdf8ec;color:#b8933a;"><i class="fas fa-hands-praying"></i></div>
                <div class="dct">Principal Sponsors & Witnesses</div>
            </div>
            <div class="dcb">
                <div class="dg2">
                    <div class="df"><span class="dl">Principal Sponsor (Male)</span><span class="dv"><?= fv($rec['principal_sponsor_male']) ?></span></div>
                    <div class="df"><span class="dl">Principal Sponsor (Female)</span><span class="dv"><?= fv($rec['principal_sponsor_female']) ?></span></div>
                    <div class="df"><span class="dl">Witness 1</span><span class="dv"><?= fv($rec['witness1_name']) ?></span></div>
                    <div class="df"><span class="dl">Witness 2</span><span class="dv"><?= fv($rec['witness2_name']) ?></span></div>
                </div>
            </div>
        </div>

        <!-- Document -->
        <div class="dc">
            <div class="dch">
                <div class="dchi" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-file-image"></i></div>
                <div class="dct">Scanned Document</div>
                <?php if (!empty($rec['document_path'])): ?>
                <div style="margin-left:auto;display:flex;gap:6px;">
                    <a href="<?= htmlspecialchars($rec['document_path']) ?>" download class="ab ab-out" style="padding:5px 10px;font-size:0.73rem;"><i class="fas fa-download"></i></a>
                    <a href="<?= htmlspecialchars($rec['document_path']) ?>" target="_blank" class="ab ab-out" style="padding:5px 10px;font-size:0.73rem;"><i class="fas fa-external-link"></i></a>
                </div>
                <?php endif; ?>
            </div>
            <div class="dcb" style="padding:16px;">
                <?php if (!empty($rec['document_path'])):
                    $ext = strtolower(pathinfo($rec['document_path'], PATHINFO_EXTENSION));
                ?>
                <div style="border:1px solid #ede8de;border-radius:10px;overflow:hidden;">
                    <div style="padding:9px 14px;background:#f3f4f6;border-bottom:1px solid #ede8de;display:flex;align-items:center;gap:6px;font-size:0.75rem;color:#374151;">
                        <i class="fas fa-file-image" style="color:#16a34a;"></i><?= basename($rec['document_path']) ?>
                    </div>
                    <?php if ($ext==='pdf'): ?>
                    <iframe src="<?= htmlspecialchars($rec['document_path']) ?>" style="width:100%;height:480px;border:none;display:block;"></iframe>
                    <?php else: ?>
                    <a href="<?= htmlspecialchars($rec['document_path']) ?>" target="_blank">
                        <img src="<?= htmlspecialchars($rec['document_path']) ?>" style="width:100%;max-height:420px;object-fit:contain;display:block;background:#fff;" loading="lazy">
                    </a>
                    <?php endif; ?>
                </div>
                <?php else: ?>
                <div style="text-align:center;padding:36px 20px;color:#c4b89a;">
                    <i class="fas fa-file-slash" style="font-size:1.8rem;opacity:0.3;display:block;margin-bottom:10px;"></i>
                    <p style="font-size:0.83rem;color:#9ca3af;margin-bottom:10px;">No document uploaded</p>
                    <?php if (in_array($current_user_role,['admin','clergy'])): ?>
                    <a href="/church/modules/church_records/wedding/edit_record.php?id=<?= $id ?>" class="ab ab-out" style="font-size:0.77rem;display:inline-flex;margin-top:4px;">
                        <i class="fas fa-upload"></i> Upload Document
                    </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
            </div>
        </div>

        <?php if (!empty($rec['remarks'])): ?>
        <div class="dc">
            <div class="dch"><div class="dchi" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div><div class="dct">Remarks</div></div>
            <div class="dcb"><p style="font-size:0.875rem;color:#374151;line-height:1.65;white-space:pre-wrap;"><?= htmlspecialchars($rec['remarks']) ?></p></div>
        </div>
        <?php endif; ?>

    </div>

    <!-- SIDEBAR -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:20px;">

        <!-- Actions -->
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Actions</p>
            <div style="display:flex;flex-direction:column;gap:7px;">
                <a href="/church/modules/church_records/wedding/print_certificate.php?id=<?= $id ?>" class="ab ab-amber" target="_blank" style="justify-content:center;"><i class="fas fa-print"></i> Print Certificate</a>
                <?php if (in_array($current_user_role,['admin','clergy'])): ?>
                <a href="/church/modules/church_records/wedding/edit_record.php?id=<?= $id ?>" class="ab ab-gold" style="justify-content:center;"><i class="fas fa-pen"></i> Edit Record</a>
                <button onclick="confirmArchive()" class="ab ab-red" style="justify-content:center;width:100%;"><i class="fas fa-box-archive"></i> Archive Record</button>
                <?php endif; ?>
            </div>
        </div>

        <!-- Record meta -->
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.08em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Record Info</p>
            <div>
                <div class="ir"><span style="color:#9ca3af;">Record No.</span><span style="font-family:monospace;color:#d97706;font-weight:600;"><?= htmlspecialchars($rec['record_no']) ?></span></div>
                <div class="ir"><span style="color:#9ca3af;">Series Year</span><span style="font-weight:500;color:#0f2044;"><?= $year ?></span></div>
                <div class="ir"><span style="color:#9ca3af;">Document</span>
                    <span><?php if (!empty($rec['document_path'])): ?><span style="color:#16a34a;font-size:0.75rem;"><i class="fas fa-check-circle"></i> Uploaded</span><?php else: ?><span style="color:#c4b89a;font-size:0.75rem;">None</span><?php endif; ?></span>
                </div>
                <div class="ir"><span style="color:#9ca3af;">Added by</span><span style="font-weight:500;color:#0f2044;"><?= htmlspecialchars($rec['created_by_name'] ?? 'Unknown') ?></span></div>
                <div class="ir"><span style="color:#9ca3af;">Date Added</span><span style="font-weight:500;color:#0f2044;"><?= $rec['created_at'] ? date('M j, Y',strtotime($rec['created_at'])) : '—' ?></span></div>
            </div>
        </div>

        <a href="/church/modules/church_records/wedding/series_records.php?year=<?= $year ?>"
           style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:#fff;border:1px solid #ede8de;border-radius:10px;text-decoration:none;font-size:0.8rem;color:#6b7280;transition:all 0.15s;"
           onmouseover="this.style.borderColor='#d97706';this.style.color='#d97706'"
           onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
            <i class="fas fa-arrow-left" style="font-size:0.7rem;color:#d97706;"></i> Back to <?= $year ?> Series
        </a>

    </div>

    </div>

</div>

<form id="archiveForm" method="POST" action="/church/modules/archive/archive_record.php">
    <input type="hidden" name="action"       value="archive">
    <input type="hidden" name="ref_type"     value="wedding">
    <input type="hidden" name="ref_id"       value="<?= $id ?>">
    <input type="hidden" name="redirect_url" value="/church/modules/church_records/wedding/view_record.php?id=<?= $id ?>">
</form>
<script>
function confirmArchive() {
    if (confirm('Archive the record for "<?= addslashes($rec['groom_name']) ?> & <?= addslashes($rec['bride_name']) ?>"?\n\nIt can be restored from the Archive module.')) {
        document.getElementById('archiveForm').submit();
    }
}
if (window.innerWidth >= 900) {
    document.getElementById('viewLayout').querySelector('div:last-child').style.position = 'sticky';
    document.getElementById('viewLayout').querySelector('div:last-child').style.top = '20px';
}
</script>
<?php include $root . '/includes/footer.php'; ?>