<?php
// church/modules/church_records/prefill_record.php
// 4.13 — Pre-fill a new sacramental record from a confirmed booking
//
// Usage:  prefill_record.php?booking_id=123
//
// Flow:
//   1. Load the booking + its booking_details rows
//   2. Detect type (baptism / wedding / funeral)
//   3. Redirect to the correct add_record.php with GET params pre-populated
//      so the form opens with all known fields already filled in.
//
// The add_record.php pages read these GET params at the top of their form
// using a helper:  $pre = fn($k) => htmlspecialchars($_GET[$k] ?? '');

$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Auth: staff only ─────────────────────────────────────────
if (!in_array($current_user_role, ['admin', 'clergy'])) {
    header('Location: /church/auth/access_denied.php');
    exit;
}

$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id <= 0) {
    header('Location: /church/bookings/index.php');
    exit;
}

// ── Load booking ─────────────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM bookings WHERE id = ?");
$stmt->bind_param("i", $booking_id);
$stmt->execute();
$booking = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$booking) {
    $_SESSION['error'] = "Booking #$booking_id not found.";
    header('Location: /church/bookings/index.php');
    exit;
}

// ── Load booking_details (key → value) ───────────────────────
$details = [];
$dq = $conn->prepare("SELECT field_key, field_value FROM booking_details WHERE booking_id = ?");
$dq->bind_param("i", $booking_id);
$dq->execute();
$dr = $dq->get_result();
while ($row = $dr->fetch_assoc()) {
    $details[$row['field_key']] = $row['field_value'];
}
$dq->close();

$type = $booking['type']; // baptism | wedding | funeral

// ── Build pre-fill GET params per type ───────────────────────
$params = ['from_booking' => $booking_id];

// Helpers
$d  = fn($key) => $details[$key] ?? '';           // from booking_details
$b  = fn($col) => $booking[$col] ?? '';            // from bookings table
$dt = fn($v)   => ($v && $v !== '0000-00-00') ? $v : '';  // safe date

switch ($type) {

    // ── BAPTISM ─────────────────────────────────────────────
    case 'baptism':
        $params = array_merge($params, [
            // Child
            'child_name'    => $d('child_name'),
            'date_of_birth' => $dt($d('date_of_birth')),
            // Parents (from booking_details)
            'father_name'   => $d('father_name'),
            'mother_name'   => $d('mother_name'),
            // Godparents
            'godfather'     => $d('godfather_name'),
            'godmother'     => $d('godmother_name'),
            // Address from booking
            'address'       => $b('address'),
            // Preferred date → date of baptism
            'date_of_baptism' => $dt($b('confirmed_date') ?: $b('preferred_date')),
            'time_of_baptism' => $b('confirmed_time') ?: $b('preferred_time'),
            // Remarks
            'remarks'       => $b('notes'),
        ]);
        $redirect = '/church/modules/church_records/baptism/series_list.php'
                  . '?prefill=1&' . http_build_query($params);
        // We go to series_list first so staff picks the correct year series,
        // then the "Add Record" button inside carries the prefill params forward.
        // Alternatively redirect straight to add if series is known.
        // For simplicity: redirect to add_record with booking params; staff
        // will have to choose series on that page (or we go via series_list).
        $redirect = '/church/modules/church_records/baptism/add_record.php'
                  . '?' . http_build_query($params);
        break;

    // ── WEDDING ─────────────────────────────────────────────
    case 'wedding':
        $params = array_merge($params, [
            'groom_name'              => $d('groom_name'),
            'bride_name'              => $d('bride_name'),
            'principal_sponsor_male'  => $d('principal_sponsor_male'),
            'principal_sponsor_female'=> $d('principal_sponsor_female'),
            'date_of_wedding'         => $dt($b('confirmed_date') ?: $b('preferred_date')),
            'groom_address'           => $b('address'),
            'remarks'                 => $b('notes'),
        ]);
        $redirect = '/church/modules/church_records/wedding/add_record.php'
                  . '?' . http_build_query($params);
        break;

    // ── FUNERAL ─────────────────────────────────────────────
    case 'funeral':
        $params = array_merge($params, [
            'deceased_name'      => $d('deceased_name'),
            'date_of_death'      => $dt($d('date_of_death')),
            'date_of_funeral'    => $dt($b('confirmed_date') ?: $b('preferred_date')),
            'address'            => $b('address'),
            'next_of_kin'        => $d('next_of_kin'),
            'next_of_kin_name'   => $d('next_of_kin'),
            'next_of_kin_contact'=> $b('contact_number'),
            'remarks'            => $b('notes'),
        ]);
        $redirect = '/church/modules/church_records/funeral/add_record.php'
                  . '?' . http_build_query($params);
        break;

    default:
        $_SESSION['error'] = "Cannot pre-fill: unsupported booking type \"{$type}\".";
        header("Location: /church/bookings/view_booking.php?id=$booking_id");
        exit;
}

header("Location: $redirect");
exit;