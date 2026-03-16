<?php
// church/modules/archive/_archive_btn.php
//
// Drop this include into any view_record.php or view_member.php.
// Renders an Archive / Restore button depending on current is_archived state.
//
// Required variables (set BEFORE including this file):
//   $arc_type      : string  — 'baptism' | 'confirmation' | 'wedding' | 'funeral' | 'member' | 'volunteer'
//   $arc_id        : int     — the record's id
//   $arc_is_archived : bool  — current is_archived value
//   $arc_redirect  : string  — URL to return to after action (usually current page URL)
//
// Example usage in baptism/view_record.php:
//
//   $arc_type        = 'baptism';
//   $arc_id          = $record['id'];
//   $arc_is_archived = (bool)$record['is_archived'];
//   $arc_redirect    = "/church/modules/church_records/baptism/view_record.php?id={$arc_id}";
//   include dirname(__DIR__, 2) . '/archive/_archive_btn.php';

$_arc_can_manage = in_array($current_user_role, ['admin', 'clergy']);
if (!$_arc_can_manage) return;  // Finance role sees no button
?>

<?php if ($arc_is_archived): ?>
<!-- ── Archived notice + Restore button ── -->
<div style="display:flex;align-items:center;gap:12px;padding:12px 16px;
            background:#fef9f0;border:1px solid #fde68a;border-radius:10px;
            margin-bottom:16px;flex-wrap:wrap;">
    <div style="display:flex;align-items:center;gap:8px;flex:1;">
        <div style="width:30px;height:30px;border-radius:8px;background:#fef3c7;
                    display:flex;align-items:center;justify-content:center;
                    color:#d97706;font-size:0.8rem;flex-shrink:0;">
            <i class="fas fa-box-archive"></i>
        </div>
        <div>
            <p style="font-size:0.82rem;font-weight:600;color:#92400e;margin-bottom:1px;">
                This record is archived
            </p>
            <p style="font-size:0.72rem;color:#b45309;">
                It is hidden from active views. Restore it to make it visible again.
            </p>
        </div>
    </div>
    <form method="POST" action="/church/modules/archive/archive_record.php" style="margin:0;"
          onsubmit="return confirm('Restore this record to active status?')">
        <input type="hidden" name="action"       value="unarchive">
        <input type="hidden" name="ref_type"     value="<?= htmlspecialchars($arc_type) ?>">
        <input type="hidden" name="ref_id"       value="<?= (int)$arc_id ?>">
        <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($arc_redirect) ?>">
        <button type="submit"
                style="display:inline-flex;align-items:center;gap:7px;
                       padding:8px 16px;border-radius:8px;font-size:0.8rem;font-weight:600;
                       background:#16a34a;color:#fff;border:none;cursor:pointer;
                       transition:background 0.14s;font-family:'DM Sans',sans-serif;"
                onmouseover="this.style.background='#15803d'"
                onmouseout="this.style.background='#16a34a'">
            <i class="fas fa-rotate-left"></i> Restore Record
        </button>
    </form>
</div>

<?php else: ?>
<!-- ── Archive button (normal state) ── -->
<form method="POST" action="/church/modules/archive/archive_record.php" style="margin:0;display:inline;"
      onsubmit="return confirm('Archive this record? It will be hidden from active views but can be restored anytime.')">
    <input type="hidden" name="action"       value="archive">
    <input type="hidden" name="ref_type"     value="<?= htmlspecialchars($arc_type) ?>">
    <input type="hidden" name="ref_id"       value="<?= (int)$arc_id ?>">
    <input type="hidden" name="redirect_url" value="<?= htmlspecialchars($arc_redirect) ?>">
    <button type="submit"
            style="display:inline-flex;align-items:center;gap:7px;
                   padding:8px 16px;border-radius:8px;font-size:0.8rem;font-weight:500;
                   background:#fff;color:#6b7280;
                   border:1px solid #e5e7eb;cursor:pointer;
                   transition:all 0.14s;font-family:'DM Sans',sans-serif;"
            onmouseover="this.style.background='#fef2f2';this.style.color='#dc2626';this.style.borderColor='#fecaca'"
            onmouseout="this.style.background='#fff';this.style.color='#6b7280';this.style.borderColor='#e5e7eb'">
        <i class="fas fa-box-archive"></i> Archive Record
    </button>
</form>
<?php endif; ?>