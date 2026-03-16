<?php
// church/modules/church_records/baptism/add_record.php
// Phase 4 — Step 4.5: Add new baptism record
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Access control ───────────────────────────────────────────
if (!in_array($current_user_role, ['admin', 'clergy'])) {
    http_response_code(403);
    include $root . '/auth/access_denied.php';
    exit;
}

// ── Get & validate year from URL ─────────────────────────────
$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 1900 || $year > (int)date('Y') + 1) {
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

// ── Verify series exists (legacy table) ──────────────────────
$s = $conn->prepare("SELECT id FROM baptism_series WHERE series_year = ?");
$s->bind_param("i", $year);
$s->execute();
$series = $s->get_result()->fetch_assoc();
$s->close();

if (!$series) {
    $_SESSION['error'] = "No baptism series found for {$year}. Please create the series first.";
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

// ── Ensure record_series entry for FK ────────────────────────
$series_id = null;
$rs = $conn->prepare("SELECT id FROM record_series WHERE type = 'baptism' AND year = ?");
$rs->bind_param("i", $year);
$rs->execute();
$rs_row = $rs->get_result()->fetch_assoc();
$rs->close();

if ($rs_row) {
    $series_id = (int)$rs_row['id'];
} else {
    $ins_rs = $conn->prepare("INSERT INTO record_series (type, year, created_by) VALUES ('baptism', ?, ?)");
    $ins_rs->bind_param("ii", $year, $current_user_id);
    if ($ins_rs->execute()) {
        $series_id = $conn->insert_id;
    } else {
        $ins_rs->close();
        $_SESSION['error'] = "Unable to create record series for {$year}. Please try again.";
        header('Location: /church/modules/church_records/baptism/series_list.php');
        exit;
    }
    $ins_rs->close();
}

// ── Pre-fill from booking (Step 4.13 hook) ──────────────────
$booking = null;
$booking_id = (int)($_GET['booking_id'] ?? 0);
if ($booking_id > 0) {
    $bq = $conn->prepare("SELECT * FROM bookings WHERE id = ? AND type = 'baptism'");
    $bq->bind_param("i", $booking_id);
    $bq->execute();
    $booking = $bq->get_result()->fetch_assoc();
    $bq->close();
}

// ── Auto-generate next record number ────────────────────────
$rn = $conn->prepare("SELECT COUNT(*) FROM baptism_records WHERE series_year = ? AND is_archived = 0");
$rn->bind_param("i", $year);
$rn->execute();
$existing_count = (int)$rn->get_result()->fetch_row()[0];
$rn->close();
$next_record_no = $year . '-' . str_pad($existing_count + 1, 3, '0', STR_PAD_LEFT);

// ── Handle POST ──────────────────────────────────────────────
$errors   = [];
$old      = [];   // repopulate form on error

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Collect & sanitize
    $old = $_POST;
    $child_name       = trim($_POST['child_name']       ?? '');
    $record_no        = trim($_POST['record_no']        ?? '');
    $date_of_baptism  = trim($_POST['date_of_baptism']  ?? '');
    $date_of_birth    = trim($_POST['date_of_birth']    ?? '') ?: null;
    $place_of_baptism = trim($_POST['place_of_baptism'] ?? '');
    $father_name      = trim($_POST['father_name']      ?? '');
    $mother_name      = trim($_POST['mother_name']      ?? '');
    $address          = trim($_POST['address']          ?? '');
    $godfather        = trim($_POST['godfather']        ?? '');
    $godmother        = trim($_POST['godmother']        ?? '');
    $minister         = trim($_POST['minister']         ?? '');
    $remarks          = trim($_POST['remarks']          ?? '');
    $post_booking_id  = (int)($_POST['booking_id']      ?? 0) ?: null;

    // New Archdiocesan form fields
    $baptism_type           = trim($_POST['baptism_type']           ?? '') ?: null;
    $time_of_baptism        = trim($_POST['time_of_baptism']        ?? '') ?: null;
    $place_of_birth         = trim($_POST['place_of_birth']         ?? '');
    $father_place_of_birth  = trim($_POST['father_place_of_birth']  ?? '');
    $mother_place_of_birth  = trim($_POST['mother_place_of_birth']  ?? '');
    $godfather_address      = trim($_POST['godfather_address']      ?? '');
    $godmother_address      = trim($_POST['godmother_address']      ?? '');
    $other_sponsors         = trim($_POST['other_sponsors']         ?? '');
    $kind_of_marriage       = trim($_POST['kind_of_marriage']       ?? '') ?: null;
    $kind_of_marriage_other = trim($_POST['kind_of_marriage_other'] ?? '');

    // Validate required
    if ($child_name === '')      $errors[] = "Child's name is required.";
    if ($record_no === '')       $errors[] = "Record number is required.";
    if ($date_of_baptism === '') $errors[] = "Date of baptism is required.";

    // Check duplicate record number in same series
    if ($record_no !== '') {
        $dup = $conn->prepare("SELECT id FROM baptism_records WHERE record_no = ? AND series_year = ? AND is_archived = 0");
        $dup->bind_param("si", $record_no, $year);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) $errors[] = "Record number \"{$record_no}\" already exists in the {$year} series.";
        $dup->close();
    }

    // ── Handle file upload ───────────────────────────────────
    $document_path = null;
    if (!empty($_FILES['document']['name'])) {
        $file      = $_FILES['document'];
        $allowed   = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $max_size  = 5 * 1024 * 1024; // 5MB

        if (!in_array($file['type'], $allowed)) {
            $errors[] = "Invalid file type. Allowed: JPG, PNG, WEBP, PDF.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File too large. Maximum size is 5MB.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed. Please try again.";
        } else {
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/church/uploads/baptism/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext       = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename  = 'baptism_' . $year . '_' . preg_replace('/[^a-z0-9]/i', '_', $record_no)
                         . '_' . time() . '.' . $ext;
            $dest      = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $document_path = '/church/uploads/baptism/' . $filename;
            } else {
                $errors[] = "Failed to save uploaded file.";
            }
        }
    }

    // ── Insert if no errors ──────────────────────────────────
    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO baptism_records
                (series_year, series_id, record_no, child_name, date_of_baptism, date_of_birth,
                 place_of_baptism, place_of_birth,
                 baptism_type, time_of_baptism,
                 father_name, father_place_of_birth,
                 mother_name, mother_place_of_birth,
                 address,
                 godfather, godfather_address,
                 godmother, godmother_address,
                 other_sponsors,
                 kind_of_marriage, kind_of_marriage_other,
                 minister, remarks,
                 document_path, booking_id, created_by, created_at)
            VALUES
                (?, ?, ?, ?, ?, ?,
                 ?, ?,
                 ?, ?,
                 ?, ?,
                 ?, ?,
                 ?,
                 ?, ?,
                 ?, ?,
                 ?,
                 ?, ?,
                 ?, ?,
                 ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "iissssssssssssssssssssssssii",
            $year, $series_id,
            $record_no, $child_name, $date_of_baptism, $date_of_birth,
            $place_of_baptism, $place_of_birth,
            $baptism_type, $time_of_baptism,
            $father_name, $father_place_of_birth,
            $mother_name, $mother_place_of_birth,
            $address,
            $godfather, $godfather_address,
            $godmother, $godmother_address,
            $other_sponsors,
            $kind_of_marriage, $kind_of_marriage_other,
            $minister, $remarks,
            $document_path, $post_booking_id, $current_user_id
        );

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            $_SESSION['success'] = "Baptism record for \"{$child_name}\" added successfully.";
            header("Location: /church/modules/church_records/baptism/view_record.php?id={$new_id}");
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
            $stmt->close();
        }
    }
}

$page_title = "Add Baptism Record — {$year}";
include $root . '/includes/header.php';
?>

<!-- ── Page-specific styles ─────────────────────────────── -->
<style>
    /* ── Form layout ── */
    .form-card {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 20px;
    }
    .form-card-header {
        padding: 16px 24px;
        border-bottom: 1px solid #f3ede3;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-card-header-icon {
        width: 34px; height: 34px;
        border-radius: 9px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.85rem;
        flex-shrink: 0;
    }
    .form-card-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.92rem;
        font-weight: 600;
        color: #0f2044;
    }
    .form-card-body {
        padding: 22px 24px;
    }

    /* ── Form grid ── */
    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
    .col-span-2  { grid-column: span 2; }
    .col-span-3  { grid-column: span 3; }

    /* ── Form controls ── */
    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-label {
        font-size: 0.8rem;
        font-weight: 600;
        color: #374151;
    }
    .form-label .req { color: #ef4444; margin-left: 2px; }
    .form-label .opt {
        color: #9ca3af;
        font-weight: 400;
        font-size: 0.72rem;
        margin-left: 4px;
    }
    .form-input, .form-select, .form-textarea {
        padding: 9px 12px;
        border: 1px solid #ede8de;
        border-radius: 8px;
        font-size: 0.865rem;
        color: #1a1a2e;
        background: #fff;
        outline: none;
        transition: border-color 0.18s, box-shadow 0.18s;
        width: 100%;
        font-family: 'DM Sans', sans-serif;
    }
    .form-input:focus, .form-select:focus, .form-textarea:focus {
        border-color: #3b82f6;
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }
    .form-input.error, .form-select.error {
        border-color: #ef4444;
        box-shadow: 0 0 0 3px rgba(239,68,68,0.08);
    }
    .form-input::placeholder { color: #c4b89a; }
    .form-textarea { resize: vertical; min-height: 80px; }
    .form-hint { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }

    /* ── File upload area ── */
    .upload-area {
        border: 2px dashed #d4c9b5;
        border-radius: 10px;
        padding: 28px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s ease;
        background: #faf7f0;
        position: relative;
    }
    .upload-area:hover, .upload-area.dragover {
        border-color: #3b82f6;
        background: #eff6ff;
    }
    .upload-area input[type="file"] {
        position: absolute;
        inset: 0;
        opacity: 0;
        cursor: pointer;
        width: 100%;
        height: 100%;
    }
    .upload-icon {
        font-size: 1.8rem;
        color: #c4b89a;
        margin-bottom: 10px;
        display: block;
        transition: color 0.2s;
    }
    .upload-area:hover .upload-icon { color: #3b82f6; }
    .upload-title {
        font-size: 0.85rem;
        font-weight: 600;
        color: #374151;
        margin-bottom: 4px;
    }
    .upload-sub { font-size: 0.75rem; color: #9ca3af; }
    .upload-preview {
        display: none;
        align-items: center;
        gap: 12px;
        padding: 12px 16px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        margin-top: 12px;
    }
    .upload-preview.show { display: flex; }
    .upload-preview-name { font-size: 0.82rem; font-weight: 500; color: #15803d; flex: 1; }
    .upload-preview-size { font-size: 0.72rem; color: #9ca3af; }
    .upload-remove {
        background: none; border: none; color: #dc2626;
        cursor: pointer; font-size: 0.8rem; padding: 2px 4px;
    }

    /* ── Record number badge ── */
    .record-no-preview {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        padding: 4px 10px;
        background: #eff6ff;
        border: 1px solid #bfdbfe;
        border-radius: 6px;
        font-size: 0.75rem;
        font-weight: 600;
        color: #3b82f6;
        font-family: monospace;
        margin-top: 6px;
    }

    /* ── Booking pre-fill banner ── */
    .booking-prefill-banner {
        background: linear-gradient(135deg, #fef3c7, #fffbeb);
        border: 1px solid #fde68a;
        border-radius: 10px;
        padding: 14px 18px;
        display: flex;
        align-items: center;
        gap: 12px;
        margin-bottom: 20px;
    }

    /* ── Error list ── */
    .error-list {
        background: #fef2f2;
        border: 1px solid #fecaca;
        border-radius: 10px;
        padding: 14px 18px;
        margin-bottom: 20px;
    }
    .error-list ul {
        margin: 6px 0 0 16px;
        padding: 0;
    }
    .error-list li { font-size: 0.82rem; color: #dc2626; margin-bottom: 3px; }

    /* ── Action buttons ── */
    .form-actions {
        display: flex;
        align-items: center;
        justify-content: flex-end;
        gap: 10px;
        padding: 18px 24px;
        background: #faf7f0;
        border-top: 1px solid #f3ede3;
        flex-wrap: wrap;
    }
    .btn-save {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 10px 24px; border-radius: 8px;
        font-size: 0.85rem; font-weight: 600;
        background: #2563eb; color: #fff;
        border: none; cursor: pointer;
        transition: background 0.15s;
    }
    .btn-save:hover { background: #1d4ed8; }
    .btn-save:disabled { background: #93c5fd; cursor: not-allowed; }
    .btn-cancel-link {
        display: inline-flex; align-items: center; gap: 6px;
        padding: 10px 18px; border-radius: 8px;
        font-size: 0.85rem; font-weight: 500;
        border: 1px solid #ede8de; background: #fff; color: #6b7280;
        text-decoration: none; transition: all 0.15s;
    }
    .btn-cancel-link:hover { background: #faf7f0; border-color: #c4b89a; color: #0f2044; }

    @media (max-width: 768px) {
        .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        .col-span-2, .col-span-3 { grid-column: span 1; }
        #formLayout { grid-template-columns: 1fr !important; }
    }
    @media (min-width: 769px) {
        #formLayout > div:last-child { position: sticky; top: 20px; }
    }
</style>

<!-- ── Page header ───────────────────────────────────────── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-plus" style="color:#3b82f6;margin-right:8px;font-size:1rem;"></i>
            Add Baptism Record
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/baptism/series_list.php" style="color:#9ca3af;text-decoration:none;">Baptism</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/baptism/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Add Record</span>
        </p>
    </div>
</div>

<!-- ── Main content ──────────────────────────────────────── -->
<div style="padding:24px 24px 60px;">
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;" id="formLayout">

    <?php if ($booking): ?>
    <!-- Booking pre-fill banner -->
    <div class="booking-prefill-banner">
        <i class="fas fa-calendar-check" style="color:#d97706;font-size:1.1rem;flex-shrink:0;"></i>
        <div>
            <p style="font-size:0.83rem;font-weight:600;color:#92400e;margin-bottom:2px;">
                Pre-filled from Booking #<?= $booking['id'] ?>
            </p>
            <p style="font-size:0.75rem;color:#b45309;">
                Requestor: <?= htmlspecialchars($booking['requestor_name']) ?> ·
                Preferred date: <?= $booking['preferred_date'] ? date('M j, Y', strtotime($booking['preferred_date'])) : '—' ?>
            </p>
        </div>
        <a href="?year=<?= $year ?>" style="margin-left:auto;font-size:0.75rem;color:#b45309;text-decoration:none;">
            Clear <i class="fas fa-times"></i>
        </a>
    </div>
    <?php endif; ?>

    <?php if (!empty($errors)): ?>
    <div class="error-list">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-circle-exclamation" style="color:#dc2626;"></i>
            <strong style="font-size:0.85rem;color:#dc2626;">Please fix the following errors:</strong>
        </div>
        <ul>
            <?php foreach ($errors as $e): ?>
            <li><?= htmlspecialchars($e) ?></li>
            <?php endforeach; ?>
        </ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="addRecordForm">
        <?php if ($booking_id): ?>
        <input type="hidden" name="booking_id" value="<?= $booking_id ?>">
        <?php endif; ?>

        <!-- ── SECTION 1: Record Info ──────────────────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#eff6ff;color:#3b82f6;">
                    <i class="fas fa-hashtag"></i>
                </div>
                <div>
                    <div class="form-card-title">Record Information</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Series year, record number, and baptism schedule</div>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-3">

                    <div class="form-group">
                        <label class="form-label">
                            Series Year <span class="req">*</span>
                        </label>
                        <input type="text" class="form-input" value="<?= $year ?>" readonly
                               style="background:#f9fafb;color:#6b7280;cursor:not-allowed;">
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Record Number <span class="req">*</span>
                        </label>
                        <input type="text"
                               name="record_no"
                               class="form-input <?= in_array('Record number is required.', $errors) || str_contains(implode('', $errors), 'Record number') ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($old['record_no'] ?? $next_record_no) ?>"
                               placeholder="e.g. <?= $next_record_no ?>"
                               required>
                        <span class="form-hint">Auto-suggested: <strong><?= $next_record_no ?></strong></span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">
                            Date of Baptism <span class="req">*</span>
                        </label>
                        <input type="date"
                               name="date_of_baptism"
                               class="form-input <?= in_array('Date of baptism is required.', $errors) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($old['date_of_baptism'] ?? ($booking['preferred_date'] ?? '')) ?>"
                               max="<?= date('Y-m-d') ?>"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Baptism Type <span class="opt">(optional)</span></label>
                        <select name="baptism_type" class="form-select">
                            <option value="">— Select —</option>
                            <option value="weekday"     <?= ($old['baptism_type'] ?? '') === 'weekday'     ? 'selected' : '' ?>>Tuesday – Saturday</option>
                            <option value="sunday_mass" <?= ($old['baptism_type'] ?? '') === 'sunday_mass' ? 'selected' : '' ?>>Sunday Mass</option>
                        </select>
                        <span class="form-hint">Per parish schedule</span>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Time of Baptism <span class="opt">(optional)</span></label>
                        <input type="time"
                               name="time_of_baptism"
                               class="form-input"
                               value="<?= htmlspecialchars($old['time_of_baptism'] ?? '') ?>">
                    </div>

                </div>
            </div>
        </div>

        <!-- ── SECTION 2: Child Information ───────────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#eff6ff;color:#3b82f6;">
                    <i class="fas fa-droplet"></i>
                </div>
                <div>
                    <div class="form-card-title">Child Information</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Details of the baptized child</div>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2" style="margin-bottom:16px;">

                    <div class="form-group col-span-2">
                        <label class="form-label">
                            Child's Full Name <span class="req">*</span>
                        </label>
                        <input type="text"
                               name="child_name"
                               class="form-input <?= in_array("Child's name is required.", $errors) ? 'error' : '' ?>"
                               value="<?= htmlspecialchars($old['child_name'] ?? '') ?>"
                               placeholder="e.g. Juan dela Cruz"
                               required>
                    </div>

                    <div class="form-group">
                        <label class="form-label">Date of Birth <span class="opt">(optional)</span></label>
                        <input type="date"
                               name="date_of_birth"
                               class="form-input"
                               value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Place of Birth <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="place_of_birth"
                               class="form-input"
                               value="<?= htmlspecialchars($old['place_of_birth'] ?? '') ?>"
                               placeholder="e.g. Zamboanga City">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Place of Baptism <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="place_of_baptism"
                               class="form-input"
                               value="<?= htmlspecialchars($old['place_of_baptism'] ?? 'Our Lady of Peace and Good Voyage Parish') ?>"
                               placeholder="e.g. Our Lady of Peace and Good Voyage Parish">
                    </div>

                </div>
            </div>
        </div>

        <!-- ── SECTION 3: Parents ─────────────────────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f5f3ff;color:#8b5cf6;">
                    <i class="fas fa-users"></i>
                </div>
                <div>
                    <div class="form-card-title">Parents</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Father, mother, and kind of marriage</div>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2">

                    <div class="form-group">
                        <label class="form-label">Father's Name <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="father_name"
                               class="form-input"
                               value="<?= htmlspecialchars($old['father_name'] ?? '') ?>"
                               placeholder="e.g. Pedro dela Cruz">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Father's Place of Birth <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="father_place_of_birth"
                               class="form-input"
                               value="<?= htmlspecialchars($old['father_place_of_birth'] ?? '') ?>"
                               placeholder="e.g. Zamboanga City">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mother's Name <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="mother_name"
                               class="form-input"
                               value="<?= htmlspecialchars($old['mother_name'] ?? '') ?>"
                               placeholder="e.g. Maria dela Cruz (maiden name)">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Mother's Place of Birth <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="mother_place_of_birth"
                               class="form-input"
                               value="<?= htmlspecialchars($old['mother_place_of_birth'] ?? '') ?>"
                               placeholder="e.g. Zamboanga City">
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Home Address <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="address"
                               class="form-input"
                               value="<?= htmlspecialchars($old['address'] ?? ($booking['address'] ?? '')) ?>"
                               placeholder="e.g. Purok 3, Tugbungan, Zamboanga City">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Kind of Marriage <span class="opt">(optional)</span></label>
                        <select name="kind_of_marriage" class="form-select" id="marriageSelect" onchange="toggleMarriageOther(this)">
                            <option value="">— Select —</option>
                            <option value="catholic"   <?= ($old['kind_of_marriage'] ?? '') === 'catholic'   ? 'selected' : '' ?>>Catholic</option>
                            <option value="civil"      <?= ($old['kind_of_marriage'] ?? '') === 'civil'      ? 'selected' : '' ?>>Civil</option>
                            <option value="protestant" <?= ($old['kind_of_marriage'] ?? '') === 'protestant' ? 'selected' : '' ?>>Protestant</option>
                            <option value="aglipay"    <?= ($old['kind_of_marriage'] ?? '') === 'aglipay'    ? 'selected' : '' ?>>Aglipay</option>
                            <option value="others"     <?= ($old['kind_of_marriage'] ?? '') === 'others'     ? 'selected' : '' ?>>Others</option>
                        </select>
                    </div>

                    <div class="form-group" id="marriageOtherWrap" style="display:none;">
                        <label class="form-label">Please Specify <span class="req">*</span></label>
                        <input type="text"
                               name="kind_of_marriage_other"
                               class="form-input"
                               value="<?= htmlspecialchars($old['kind_of_marriage_other'] ?? '') ?>"
                               placeholder="Specify kind of marriage">
                    </div>

                </div>
            </div>
        </div>

        <!-- ── SECTION 4: Godparents & Minister ───────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#fdf8ec;color:#b8933a;">
                    <i class="fas fa-hands-praying"></i>
                </div>
                <div>
                    <div class="form-card-title">Godparents & Minister</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Sponsors, addresses, other sponsors, and officiating minister</div>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2" style="margin-bottom:16px;">

                    <div class="form-group">
                        <label class="form-label">Godfather (Ninong) <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="godfather"
                               class="form-input"
                               value="<?= htmlspecialchars($old['godfather'] ?? '') ?>"
                               placeholder="e.g. Jose Reyes">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Godfather's Address <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="godfather_address"
                               class="form-input"
                               value="<?= htmlspecialchars($old['godfather_address'] ?? '') ?>"
                               placeholder="e.g. Zamboanga City">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Godmother (Ninang) <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="godmother"
                               class="form-input"
                               value="<?= htmlspecialchars($old['godmother'] ?? '') ?>"
                               placeholder="e.g. Ana Reyes">
                    </div>

                    <div class="form-group">
                        <label class="form-label">Godmother's Address <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="godmother_address"
                               class="form-input"
                               value="<?= htmlspecialchars($old['godmother_address'] ?? '') ?>"
                               placeholder="e.g. Zamboanga City">
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Other Sponsors <span class="opt">(optional)</span></label>
                        <textarea name="other_sponsors"
                                  class="form-textarea"
                                  rows="2"
                                  placeholder="Names of additional sponsors not listed above…"><?= htmlspecialchars($old['other_sponsors'] ?? '') ?></textarea>
                    </div>

                    <div class="form-group col-span-2">
                        <label class="form-label">Officiating Minister <span class="opt">(optional)</span></label>
                        <input type="text"
                               name="minister"
                               class="form-input"
                               value="<?= htmlspecialchars($old['minister'] ?? '') ?>"
                               placeholder="e.g. Fr. Juan Santos">
                    </div>

                </div>
            </div>
        </div>

        <!-- ── SECTION 5: Document Upload ─────────────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                    <i class="fas fa-file-image"></i>
                </div>
                <div>
                    <div class="form-card-title">Scanned Document</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Upload a photo or scan of the baptismal record</div>
                </div>
            </div>
            <div class="form-card-body">

                <div class="upload-area" id="uploadArea">
                    <input type="file"
                           name="document"
                           id="documentFile"
                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                           onchange="previewFile(this)">
                    <i class="fas fa-cloud-arrow-up upload-icon" id="uploadIcon"></i>
                    <div class="upload-title" id="uploadTitle">Click to upload or drag & drop</div>
                    <div class="upload-sub">JPG, PNG, WEBP or PDF — max 5MB</div>
                </div>

                <div class="upload-preview" id="uploadPreview">
                    <i class="fas fa-file-check" style="color:#16a34a;font-size:1.1rem;flex-shrink:0;"></i>
                    <span class="upload-preview-name" id="previewName"></span>
                    <span class="upload-preview-size" id="previewSize"></span>
                    <button type="button" class="upload-remove" onclick="clearFile()" title="Remove file">
                        <i class="fas fa-times"></i>
                    </button>
                </div>

            </div>
        </div>

        <!-- ── SECTION 6: Remarks ─────────────────────────── -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f9fafb;color:#6b7280;">
                    <i class="fas fa-note-sticky"></i>
                </div>
                <div>
                    <div class="form-card-title">Remarks</div>
                    <div style="font-size:0.72rem;color:#9ca3af;">Additional notes about this record</div>
                </div>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <textarea name="remarks"
                              class="form-textarea"
                              rows="3"
                              placeholder="Any additional notes or remarks about this baptism record…"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- ── FORM ACTIONS ──────────────────────────────── -->
        <div class="form-actions" style="border-radius:0 0 14px 14px;border:1px solid #ede8de;
                                         border-top:1px solid #f3ede3;background:#faf7f0;
                                         display:flex;align-items:center;justify-content:flex-end;
                                         gap:10px;padding:18px 24px;">
            <a href="/church/modules/church_records/baptism/series_records.php?year=<?= $year ?>"
               class="btn-cancel-link">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn-save" id="submitBtn">
                <i class="fas fa-floppy-disk"></i> Save Record
            </button>
        </div>

    </form>

    <!-- ── RIGHT SIDEBAR ──────────────────────────────────── -->
    <div style="display:flex;flex-direction:column;gap:16px;">

        <!-- Series info card -->
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:16px 18px;">
                <p style="font-family:'Playfair Display',serif;font-size:0.95rem;color:#fff;
                           font-weight:600;margin-bottom:2px;">
                    <?= $year ?> Series
                </p>
                <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Baptism Records</p>
            </div>
            <div style="padding:16px 18px;display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.75rem;color:#9ca3af;">Records in series</span>
                    <span style="font-size:0.85rem;font-weight:600;color:#0f2044;"><?= $existing_count ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.75rem;color:#9ca3af;">Next record no.</span>
                    <span style="font-size:0.78rem;font-weight:600;color:#3b82f6;font-family:monospace;"><?= $next_record_no ?></span>
                </div>
                <hr style="border:none;border-top:1px solid #f3ede3;margin:0;">
                <a href="/church/modules/church_records/baptism/series_records.php?year=<?= $year ?>"
                   style="display:flex;align-items:center;gap:7px;font-size:0.78rem;
                          color:#3b82f6;text-decoration:none;">
                    <i class="fas fa-arrow-left" style="font-size:0.65rem;"></i>
                    Back to <?= $year ?> series
                </a>
            </div>
        </div>

        <!-- Required fields guide -->
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.8rem;font-weight:600;color:#0f2044;margin-bottom:12px;
                      display:flex;align-items:center;gap:7px;">
                <i class="fas fa-circle-info" style="color:#b8933a;font-size:0.8rem;"></i>
                Required Fields
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;">
                    <i class="fas fa-check-circle" style="color:#3b82f6;font-size:0.75rem;"></i>
                    Record Number
                </div>
                <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;">
                    <i class="fas fa-check-circle" style="color:#3b82f6;font-size:0.75rem;"></i>
                    Date of Baptism
                </div>
                <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;">
                    <i class="fas fa-check-circle" style="color:#3b82f6;font-size:0.75rem;"></i>
                    Child's Full Name
                </div>
            </div>
            <hr style="border:none;border-top:1px solid #f3ede3;margin:12px 0;">
            <p style="font-size:0.72rem;color:#9ca3af;line-height:1.5;">
                All other fields are optional but recommended for complete records.
            </p>
        </div>

        <!-- Document tip -->
        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.8rem;font-weight:600;color:#15803d;margin-bottom:8px;
                      display:flex;align-items:center;gap:7px;">
                <i class="fas fa-file-image" style="font-size:0.8rem;"></i>
                Document Upload
            </p>
            <p style="font-size:0.75rem;color:#166534;line-height:1.55;">
                Upload a scanned copy or photo of the original baptismal record.
                Accepted formats: <strong>JPG, PNG, WEBP, PDF</strong> — max 5MB.
            </p>
        </div>

    </div>
    <!-- /right sidebar -->

</div>
<!-- /form layout grid -->
</div>
<!-- /padding wrapper -->

<script>
// ── Marriage type toggle ─────────────────────────────────────
function toggleMarriageOther(sel) {
    const wrap = document.getElementById('marriageOtherWrap');
    wrap.style.display = sel.value === 'others' ? '' : 'none';
}
// Init on page load (handles repopulate-on-error case)
(function() {
    const sel = document.getElementById('marriageSelect');
    if (sel) toggleMarriageOther(sel);
})();

// ── File upload preview ──────────────────────────────────────
function previewFile(input) {
    const preview = document.getElementById('uploadPreview');
    const name    = document.getElementById('previewName');
    const size    = document.getElementById('previewSize');
    const icon    = document.getElementById('uploadIcon');
    const title   = document.getElementById('uploadTitle');

    if (input.files && input.files[0]) {
        const file = input.files[0];
        name.textContent  = file.name;
        size.textContent  = (file.size / 1024).toFixed(1) + ' KB';
        preview.classList.add('show');
        icon.style.color  = '#3b82f6';
        title.textContent = 'File selected';
    }
}

function clearFile() {
    const input   = document.getElementById('documentFile');
    const preview = document.getElementById('uploadPreview');
    const icon    = document.getElementById('uploadIcon');
    const title   = document.getElementById('uploadTitle');

    input.value   = '';
    preview.classList.remove('show');
    icon.style.color  = '';
    title.textContent = 'Click to upload or drag & drop';
}

// ── Drag & drop visual ───────────────────────────────────────
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop',      e => {
    e.preventDefault();
    uploadArea.classList.remove('dragover');
    const input = document.getElementById('documentFile');
    input.files = e.dataTransfer.files;
    previewFile(input);
});

// ── Prevent double-submit ────────────────────────────────────
document.getElementById('addRecordForm').addEventListener('submit', function () {
    const btn = document.getElementById('submitBtn');
    btn.disabled    = true;
    btn.innerHTML   = '<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>

<?php include $root . '/includes/footer.php'; ?>