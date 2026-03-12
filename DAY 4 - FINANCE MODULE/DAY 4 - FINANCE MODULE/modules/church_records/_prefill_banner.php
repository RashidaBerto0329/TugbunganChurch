<?php
// church/modules/church_records/_prefill_banner.php
// Include at the top of add_record.php pages.
//
// Provides:
//   $pre(string $field): string   — returns htmlspecialchars($_GET[$field] ?? '')
//   $prefill_booking_id: int|null — the source booking ID (or null)
//   Renders a dismissable notice banner if coming from a booking.
//
// Usage in add_record.php:
//   require_once dirname(__DIR__) . '/_prefill_banner.php';
//   ... in form inputs: value="<?= $pre('child_name') ?>"

$prefill_booking_id = isset($_GET['from_booking']) ? (int)$_GET['from_booking'] : null;

// Helper — safely read a pre-fill GET param
$pre = function(string $field): string {
    return htmlspecialchars($_GET[$field] ?? '', ENT_QUOTES, 'UTF-8');
};  
?>
<?php if ($prefill_booking_id): ?>
<?php
// Load the booking reference for display
$_pf_stmt = $conn->prepare("SELECT reference_number, requestor_name, type FROM bookings WHERE id = ?");
$_pf_stmt->bind_param("i", $prefill_booking_id);
$_pf_stmt->execute();
$_pf_booking = $_pf_stmt->get_result()->fetch_assoc();
$_pf_stmt->close();
?>
<div style="margin:0 0 20px;padding:14px 18px;background:#eff6ff;border:1px solid #bfdbfe;
            border-radius:10px;display:flex;align-items:flex-start;gap:12px;">
    <div style="width:32px;height:32px;border-radius:8px;background:#dbeafe;
                display:flex;align-items:center;justify-content:center;
                color:#2563eb;font-size:0.82rem;flex-shrink:0;margin-top:1px;">
        <i class="fas fa-wand-magic-sparkles"></i>
    </div>
    <div style="flex:1;">
        <p style="font-size:0.83rem;font-weight:600;color:#1e40af;margin-bottom:3px;">
            Pre-filled from Booking
            <?= $_pf_booking ? '— Ref# ' . htmlspecialchars($_pf_booking['reference_number']) : '#' . $prefill_booking_id ?>
        </p>
        <p style="font-size:0.75rem;color:#3b82f6;margin-bottom:0;">
            Fields were pre-filled from the booking submitted by
            <strong><?= $_pf_booking ? htmlspecialchars($_pf_booking['requestor_name']) : 'requestor' ?></strong>.
            Please review and complete any missing information before saving.
        </p>
    </div>
    <a href="/church/bookings/view_booking.php?id=<?= $prefill_booking_id ?>"
       style="font-size:0.75rem;color:#2563eb;text-decoration:none;white-space:nowrap;
              display:inline-flex;align-items:center;gap:5px;padding:5px 10px;
              border:1px solid #bfdbfe;border-radius:6px;background:#fff;transition:all 0.15s;flex-shrink:0;"
       onmouseover="this.style.background='#2563eb';this.style.color='#fff'"
       onmouseout="this.style.background='#fff';this.style.color='#2563eb'">
        <i class="fas fa-arrow-left" style="font-size:0.65rem;"></i> Back to Booking
    </a>
</div>
<?php endif; ?>