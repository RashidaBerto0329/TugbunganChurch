<?php
// church/modules/church_records/baptism/edit_record.php
// Phase 4 — Step 4.7: Edit an existing baptism record
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Access control ───────────────────────────────────────────
if (!in_array($current_user_role, ['admin', 'clergy'])) {
    http_response_code(403);
    include $root . '/auth/access_denied.php';
    exit;
}

// ── Get & validate record ID ─────────────────────────────────
$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) {
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

// ── Fetch existing record ────────────────────────────────────
$stmt = $conn->prepare("SELECT * FROM baptism_records WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$rec = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$rec) {
    $_SESSION['error'] = "Record not found or has been archived.";
    header('Location: /church/modules/church_records/baptism/series_list.php');
    exit;
}

$year = (int)$rec['series_year'];

// ── Handle POST ──────────────────────────────────────────────
$errors = [];
$old    = $rec; // default to existing values

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
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
    $remove_document  = isset($_POST['remove_document']);

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

    // Check duplicate record_no — exclude current record
    if ($record_no !== '') {
        $dup = $conn->prepare("
            SELECT id FROM baptism_records
            WHERE record_no = ? AND series_year = ? AND is_archived = 0 AND id != ?
        ");
        $dup->bind_param("sii", $record_no, $year, $id);
        $dup->execute();
        $dup->store_result();
        if ($dup->num_rows > 0) {
            $errors[] = "Record number \"{$record_no}\" already exists in the {$year} series.";
        }
        $dup->close();
    }

    // ── Handle file upload ───────────────────────────────────
    $document_path = $rec['document_path']; // keep existing by default

    if ($remove_document) {
        // Delete existing file
        if (!empty($rec['document_path'])) {
            $full_path = $_SERVER['DOCUMENT_ROOT'] . $rec['document_path'];
            if (file_exists($full_path)) @unlink($full_path);
        }
        $document_path = null;
    }

    if (!empty($_FILES['document']['name'])) {
        $file     = $_FILES['document'];
        $allowed  = ['image/jpeg', 'image/png', 'image/webp', 'application/pdf'];
        $max_size = 5 * 1024 * 1024;

        if (!in_array($file['type'], $allowed)) {
            $errors[] = "Invalid file type. Allowed: JPG, PNG, WEBP, PDF.";
        } elseif ($file['size'] > $max_size) {
            $errors[] = "File too large. Maximum size is 5MB.";
        } elseif ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "File upload failed. Please try again.";
        } else {
            // Delete old file if replacing
            if (!empty($rec['document_path'])) {
                $old_full = $_SERVER['DOCUMENT_ROOT'] . $rec['document_path'];
                if (file_exists($old_full)) @unlink($old_full);
            }

            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/church/uploads/baptism/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);

            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'baptism_' . $year . '_' . preg_replace('/[^a-z0-9]/i', '_', $record_no)
                        . '_' . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;

            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $document_path = '/church/uploads/baptism/' . $filename;
            } else {
                $errors[] = "Failed to save uploaded file.";
            }
        }
    }

    // ── Update if no errors ──────────────────────────────────
    if (empty($errors)) {
        $upd = $conn->prepare("
            UPDATE baptism_records SET
                record_no        = ?,
                child_name       = ?,
                date_of_baptism  = ?,
                date_of_birth    = ?,
                place_of_baptism = ?,
                place_of_birth   = ?,
                baptism_type     = ?,
                time_of_baptism  = ?,
                father_name      = ?,
                father_place_of_birth = ?,
                mother_name      = ?,
                mother_place_of_birth = ?,
                address          = ?,
                godfather        = ?,
                godfather_address = ?,
                godmother        = ?,
                godmother_address = ?,
                other_sponsors   = ?,
                kind_of_marriage = ?,
                kind_of_marriage_other = ?,
                minister         = ?,
                remarks          = ?,
                document_path    = ?,
                updated_by       = ?,
                updated_at       = NOW()
            WHERE id = ?
        ");
        $upd->bind_param(
            "sssssssssssssssssssssssii",
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
            $document_path, $current_user_id, $id
        );

        if ($upd->execute()) {
            $upd->close();
            $_SESSION['success'] = "Record for \"{$child_name}\" updated successfully.";
            header("Location: /church/modules/church_records/baptism/view_record.php?id={$id}");
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
            $upd->close();
        }
    }
}

$page_title = "Edit — " . htmlspecialchars($rec['child_name']);
include $root . '/includes/header.php';
?>

<style>
    .form-card {
        background: #fff;
        border: 1px solid #ede8de;
        border-radius: 14px;
        overflow: hidden;
        margin-bottom: 18px;
    }
    .form-card-header {
        padding: 14px 22px;
        border-bottom: 1px solid #f3ede3;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .form-card-header-icon {
        width: 32px; height: 32px;
        border-radius: 8px;
        display: flex; align-items: center; justify-content: center;
        font-size: 0.8rem; flex-shrink: 0;
    }
    .form-card-title {
        font-family: 'Playfair Display', serif;
        font-size: 0.88rem; font-weight: 600; color: #0f2044;
    }
    .form-card-body { padding: 20px 22px; }

    .form-grid-2 { display: grid; grid-template-columns: 1fr 1fr; gap: 16px; }
    .form-grid-3 { display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 16px; }
    .col-span-2  { grid-column: span 2; }
    .col-span-3  { grid-column: span 3; }

    .form-group { display: flex; flex-direction: column; gap: 5px; }
    .form-label { font-size: 0.8rem; font-weight: 600; color: #374151; }
    .form-label .req { color: #ef4444; margin-left: 2px; }
    .form-label .opt { color: #9ca3af; font-weight: 400; font-size: 0.72rem; margin-left: 4px; }

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
    .form-input.error { border-color: #ef4444; box-shadow: 0 0 0 3px rgba(239,68,68,0.08); }
    .form-input::placeholder { color: #c4b89a; }
    .form-textarea { resize: vertical; min-height: 80px; }
    .form-hint { font-size: 0.72rem; color: #9ca3af; margin-top: 2px; }

    /* Existing document preview */
    .existing-doc {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 12px 14px;
        background: #f0fdf4;
        border: 1px solid #bbf7d0;
        border-radius: 8px;
        margin-bottom: 12px;
    }
    .existing-doc-name {
        font-size: 0.82rem; font-weight: 500; color: #15803d; flex: 1;
        white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
    }

    /* Upload area */
    .upload-area {
        border: 2px dashed #d4c9b5;
        border-radius: 10px;
        padding: 24px 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.2s;
        background: #faf7f0;
        position: relative;
    }
    .upload-area:hover { border-color: #3b82f6; background: #eff6ff; }
    .upload-area input[type="file"] {
        position: absolute; inset: 0; opacity: 0;
        cursor: pointer; width: 100%; height: 100%;
    }
    .upload-preview {
        display: none; align-items: center; gap: 10px;
        padding: 10px 14px;
        background: #f0fdf4; border: 1px solid #bbf7d0;
        border-radius: 8px; margin-top: 10px;
    }
    .upload-preview.show { display: flex; }

    /* Error list */
    .error-list {
        background: #fef2f2; border: 1px solid #fecaca;
        border-radius: 10px; padding: 14px 18px; margin-bottom: 20px;
    }
    .error-list ul { margin: 6px 0 0 16px; padding: 0; }
    .error-list li { font-size: 0.82rem; color: #dc2626; margin-bottom: 3px; }

    /* Changed indicator */
    .form-input.changed, .form-textarea.changed {
        border-color: #f59e0b;
        box-shadow: 0 0 0 3px rgba(245,158,11,0.1);
    }

    /* Buttons */
    .btn-save {
        display: inline-flex; align-items: center; gap: 7px;
        padding: 10px 24px; border-radius: 8px;
        font-size: 0.85rem; font-weight: 600;
        background: #2563eb; color: #fff;
        border: none; cursor: pointer; transition: background 0.15s;
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

    .info-row {
        display: flex; justify-content: space-between; align-items: center;
        padding: 8px 0; border-bottom: 1px solid #f5f0e8;
        font-size: 0.8rem;
    }
    .info-row:last-child { border-bottom: none; padding-bottom: 0; }
    .info-row-label { color: #9ca3af; }
    .info-row-value { color: #0f2044; font-weight: 500; }

    @media (max-width: 900px) {
        #editLayout { grid-template-columns: 1fr !important; }
    }
    @media (max-width: 768px) {
        .form-grid-2, .form-grid-3 { grid-template-columns: 1fr; }
        .col-span-2, .col-span-3 { grid-column: span 1; }
    }
</style>

<!-- ── Page header ───────────────────────────────────────── -->
<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-pen" style="color:#b8933a;margin-right:8px;font-size:1rem;"></i>
            Edit Baptism Record
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
            <a href="/church/modules/church_records/baptism/view_record.php?id=<?= $id ?>" style="color:#9ca3af;text-decoration:none;"><?= htmlspecialchars($rec['child_name']) ?></a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Edit</span>
        </p>
    </div>
    <a href="/church/modules/church_records/baptism/view_record.php?id=<?= $id ?>"
       class="btn-cancel-link">
        <i class="fas fa-times"></i> Cancel
    </a>
</div>

<!-- ── Main content ──────────────────────────────────────── -->
<div style="padding:24px 24px 60px;">

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

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;"
         id="editLayout">

        <!-- ── LEFT: Form ──────────────────────────────── -->
        <div>
        <form method="POST" enctype="multipart/form-data" id="editForm">

            <!-- SECTION 1: Record Info -->
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
                            <label class="form-label">Series Year</label>
                            <input type="text" class="form-input"
                                   value="<?= $year ?>" readonly
                                   style="background:#f9fafb;color:#6b7280;cursor:not-allowed;">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Record Number <span class="req">*</span></label>
                            <input type="text" name="record_no"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['record_no'] ?? '') ?>"
                                   required
                                   data-original="<?= htmlspecialchars($rec['record_no']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date of Baptism <span class="req">*</span></label>
                            <input type="date" name="date_of_baptism"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['date_of_baptism'] ?? '') ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   required
                                   data-original="<?= htmlspecialchars($rec['date_of_baptism'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Baptism Type <span class="opt">(optional)</span></label>
                            <select name="baptism_type" class="form-select"
                                    data-original="<?= htmlspecialchars($rec['baptism_type'] ?? '') ?>">
                                <option value="">— Select —</option>
                                <option value="weekday"     <?= ($old['baptism_type'] ?? '') === 'weekday'     ? 'selected' : '' ?>>Tuesday – Saturday</option>
                                <option value="sunday_mass" <?= ($old['baptism_type'] ?? '') === 'sunday_mass' ? 'selected' : '' ?>>Sunday Mass</option>
                            </select>
                            <span class="form-hint">Per parish schedule</span>
                        </div>

                        <div class="form-group">
                            <label class="form-label">Time of Baptism <span class="opt">(optional)</span></label>
                            <input type="time" name="time_of_baptism"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['time_of_baptism'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($rec['time_of_baptism'] ?? '') ?>">
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION 2: Child Info -->
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
                    <div class="form-grid-2">

                        <div class="form-group col-span-2">
                            <label class="form-label">Child's Full Name <span class="req">*</span></label>
                            <input type="text" name="child_name"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['child_name'] ?? '') ?>"
                                   required
                                   data-original="<?= htmlspecialchars($rec['child_name']) ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Date of Birth <span class="opt">(optional)</span></label>
                            <input type="date" name="date_of_birth"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>"
                                   max="<?= date('Y-m-d') ?>"
                                   data-original="<?= htmlspecialchars($rec['date_of_birth'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Place of Birth <span class="opt">(optional)</span></label>
                            <input type="text" name="place_of_birth"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['place_of_birth'] ?? '') ?>"
                                   placeholder="e.g. Zamboanga City"
                                   data-original="<?= htmlspecialchars($rec['place_of_birth'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Place of Baptism <span class="opt">(optional)</span></label>
                            <input type="text" name="place_of_baptism"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['place_of_baptism'] ?? '') ?>"
                                   data-original="<?= htmlspecialchars($rec['place_of_baptism'] ?? '') ?>">
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION 3: Parents -->
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
                            <input type="text" name="father_name"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['father_name'] ?? '') ?>"
                                   placeholder="e.g. Pedro dela Cruz"
                                   data-original="<?= htmlspecialchars($rec['father_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Father's Place of Birth <span class="opt">(optional)</span></label>
                            <input type="text" name="father_place_of_birth"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['father_place_of_birth'] ?? '') ?>"
                                   placeholder="e.g. Zamboanga City"
                                   data-original="<?= htmlspecialchars($rec['father_place_of_birth'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mother's Name <span class="opt">(optional)</span></label>
                            <input type="text" name="mother_name"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['mother_name'] ?? '') ?>"
                                   placeholder="e.g. Maria dela Cruz (maiden name)"
                                   data-original="<?= htmlspecialchars($rec['mother_name'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Mother's Place of Birth <span class="opt">(optional)</span></label>
                            <input type="text" name="mother_place_of_birth"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['mother_place_of_birth'] ?? '') ?>"
                                   placeholder="e.g. Zamboanga City"
                                   data-original="<?= htmlspecialchars($rec['mother_place_of_birth'] ?? '') ?>">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Home Address <span class="opt">(optional)</span></label>
                            <input type="text" name="address"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['address'] ?? '') ?>"
                                   placeholder="e.g. Purok 3, Tugbungan, Zamboanga City"
                                   data-original="<?= htmlspecialchars($rec['address'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Kind of Marriage <span class="opt">(optional)</span></label>
                            <select name="kind_of_marriage" class="form-select"
                                    id="marriageSelect" onchange="toggleMarriageOther(this)"
                                    data-original="<?= htmlspecialchars($rec['kind_of_marriage'] ?? '') ?>">
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
                            <input type="text" name="kind_of_marriage_other"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['kind_of_marriage_other'] ?? '') ?>"
                                   placeholder="Specify kind of marriage"
                                   data-original="<?= htmlspecialchars($rec['kind_of_marriage_other'] ?? '') ?>">
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION 4: Godparents & Minister -->
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
                            <input type="text" name="godfather"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['godfather'] ?? '') ?>"
                                   placeholder="e.g. Jose Reyes"
                                   data-original="<?= htmlspecialchars($rec['godfather'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Godfather's Address <span class="opt">(optional)</span></label>
                            <input type="text" name="godfather_address"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['godfather_address'] ?? '') ?>"
                                   placeholder="e.g. Zamboanga City"
                                   data-original="<?= htmlspecialchars($rec['godfather_address'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Godmother (Ninang) <span class="opt">(optional)</span></label>
                            <input type="text" name="godmother"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['godmother'] ?? '') ?>"
                                   placeholder="e.g. Ana Reyes"
                                   data-original="<?= htmlspecialchars($rec['godmother'] ?? '') ?>">
                        </div>

                        <div class="form-group">
                            <label class="form-label">Godmother's Address <span class="opt">(optional)</span></label>
                            <input type="text" name="godmother_address"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['godmother_address'] ?? '') ?>"
                                   placeholder="e.g. Zamboanga City"
                                   data-original="<?= htmlspecialchars($rec['godmother_address'] ?? '') ?>">
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Other Sponsors <span class="opt">(optional)</span></label>
                            <textarea name="other_sponsors"
                                      class="form-textarea"
                                      rows="2"
                                      placeholder="Names of additional sponsors not listed above…"
                                      data-original="<?= htmlspecialchars($rec['other_sponsors'] ?? '') ?>"><?= htmlspecialchars($old['other_sponsors'] ?? '') ?></textarea>
                        </div>

                        <div class="form-group col-span-2">
                            <label class="form-label">Officiating Minister <span class="opt">(optional)</span></label>
                            <input type="text" name="minister"
                                   class="form-input"
                                   value="<?= htmlspecialchars($old['minister'] ?? '') ?>"
                                   placeholder="e.g. Fr. Juan Santos"
                                   data-original="<?= htmlspecialchars($rec['minister'] ?? '') ?>">
                        </div>

                    </div>
                </div>
            </div>

            <!-- SECTION 5: Document -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;">
                        <i class="fas fa-file-image"></i>
                    </div>
                    <div>
                        <div class="form-card-title">Scanned Document</div>
                        <div style="font-size:0.72rem;color:#9ca3af;">Replace or remove the scanned document</div>
                    </div>
                </div>
                <div class="form-card-body">

                    <?php if (!empty($rec['document_path'])): ?>
                    <!-- Existing document -->
                    <div class="existing-doc" id="existingDoc">
                        <i class="fas fa-file-check" style="color:#16a34a;font-size:1rem;flex-shrink:0;"></i>
                        <span class="existing-doc-name">
                            <?= basename($rec['document_path']) ?>
                        </span>
                        <a href="<?= htmlspecialchars($rec['document_path']) ?>"
                           target="_blank"
                           style="font-size:0.75rem;color:#16a34a;text-decoration:none;white-space:nowrap;">
                            <i class="fas fa-external-link"></i> View
                        </a>
                        <label style="display:inline-flex;align-items:center;gap:5px;
                                      font-size:0.75rem;color:#dc2626;cursor:pointer;white-space:nowrap;">
                            <input type="checkbox" name="remove_document"
                                   id="removeDocCheck"
                                   onchange="toggleRemoveDoc(this)">
                            Remove
                        </label>
                    </div>
                    <p style="font-size:0.75rem;color:#9ca3af;margin-bottom:10px;">
                        Upload a new file below to <strong>replace</strong> the existing document,
                        or check "Remove" to delete it without replacement.
                    </p>
                    <?php endif; ?>

                    <div class="upload-area" id="uploadArea">
                        <input type="file"
                               name="document"
                               id="documentFile"
                               accept=".jpg,.jpeg,.png,.webp,.pdf"
                               onchange="previewFile(this)">
                        <i class="fas fa-cloud-arrow-up"
                           style="font-size:1.6rem;color:#c4b89a;display:block;margin-bottom:8px;"
                           id="uploadIcon"></i>
                        <div style="font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:3px;"
                             id="uploadTitle">
                            <?= !empty($rec['document_path']) ? 'Upload replacement file' : 'Click to upload or drag & drop' ?>
                        </div>
                        <div style="font-size:0.73rem;color:#9ca3af;">JPG, PNG, WEBP or PDF — max 5MB</div>
                    </div>

                    <div class="upload-preview" id="uploadPreview">
                        <i class="fas fa-file-check" style="color:#16a34a;font-size:1rem;flex-shrink:0;"></i>
                        <span style="font-size:0.82rem;font-weight:500;color:#15803d;flex:1;"
                              id="previewName"></span>
                        <span style="font-size:0.72rem;color:#9ca3af;" id="previewSize"></span>
                        <button type="button"
                                style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:0.8rem;"
                                onclick="clearFile()">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>

                </div>
            </div>

            <!-- SECTION 6: Remarks -->
            <div class="form-card">
                <div class="form-card-header">
                    <div class="form-card-header-icon" style="background:#f9fafb;color:#6b7280;">
                        <i class="fas fa-note-sticky"></i>
                    </div>
                    <div>
                        <div class="form-card-title">Remarks</div>
                        <div style="font-size:0.72rem;color:#9ca3af;">Additional notes</div>
                    </div>
                </div>
                <div class="form-card-body">
                    <textarea name="remarks"
                              class="form-textarea"
                              rows="3"
                              placeholder="Any additional notes…"
                              data-original="<?= htmlspecialchars($rec['remarks'] ?? '') ?>"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                </div>
            </div>

            <!-- Form actions -->
            <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;
                        padding:18px 22px;background:#faf7f0;border:1px solid #ede8de;
                        border-radius:0 0 14px 14px;border-top:1px solid #f3ede3;">
                <a href="/church/modules/church_records/baptism/view_record.php?id=<?= $id ?>"
                   class="btn-cancel-link">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-save" id="submitBtn">
                    <i class="fas fa-floppy-disk"></i> Save Changes
                </button>
            </div>

        </form>
        </div>
        <!-- /left -->

        <!-- ── RIGHT SIDEBAR ──────────────────────────── -->
        <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:20px;">

            <!-- Record being edited -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
                <div style="background:linear-gradient(135deg,#1e3a8a,#2563eb);padding:16px 18px;">
                    <p style="font-family:'Playfair Display',serif;font-size:0.95rem;color:#fff;
                               font-weight:600;margin-bottom:2px;">
                        Editing Record
                    </p>
                    <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">
                        <?= $year ?> Baptism Series
                    </p>
                </div>
                <div style="padding:14px 18px;">
                    <div class="info-row">
                        <span class="info-row-label">Record No.</span>
                        <span class="info-row-value" style="font-family:monospace;color:#3b82f6;">
                            <?= htmlspecialchars($rec['record_no']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Child's Name</span>
                        <span class="info-row-value" style="max-width:140px;text-align:right;
                              white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">
                            <?= htmlspecialchars($rec['child_name']) ?>
                        </span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Series Year</span>
                        <span class="info-row-value"><?= $year ?></span>
                    </div>
                    <div class="info-row">
                        <span class="info-row-label">Document</span>
                        <span class="info-row-value">
                            <?php if (!empty($rec['document_path'])): ?>
                            <span style="color:#16a34a;font-size:0.75rem;">
                                <i class="fas fa-check-circle"></i> Uploaded
                            </span>
                            <?php else: ?>
                            <span style="color:#c4b89a;font-size:0.75rem;">None</span>
                            <?php endif; ?>
                        </span>
                    </div>
                </div>
            </div>

            <!-- Change tracker -->
            <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
                <p style="font-size:0.78rem;font-weight:700;letter-spacing:0.07em;
                           text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">
                    Unsaved Changes
                </p>
                <div id="changeList" style="font-size:0.78rem;color:#9ca3af;font-style:italic;">
                    No changes yet
                </div>
            </div>

            <!-- View original -->
            <a href="/church/modules/church_records/baptism/view_record.php?id=<?= $id ?>"
               style="display:flex;align-items:center;gap:8px;padding:12px 16px;
                      background:#fff;border:1px solid #ede8de;border-radius:10px;
                      text-decoration:none;font-size:0.8rem;color:#6b7280;transition:all 0.15s;"
               onmouseover="this.style.borderColor='#b8933a';this.style.color='#b8933a'"
               onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
                <i class="fas fa-eye" style="font-size:0.7rem;color:#b8933a;"></i>
                View Original Record
            </a>

        </div>
        <!-- /sidebar -->

    </div>
    <!-- /editLayout -->

</div>

<script>
// ── Marriage type toggle ─────────────────────────────────────
function toggleMarriageOther(sel) {
    const wrap = document.getElementById('marriageOtherWrap');
    wrap.style.display = sel.value === 'others' ? '' : 'none';
}
(function() {
    const sel = document.getElementById('marriageSelect');
    if (sel) toggleMarriageOther(sel);
})();

// ── File upload preview ──────────────────────────────────────
function previewFile(input) {
    const preview = document.getElementById('uploadPreview');
    const name    = document.getElementById('previewName');
    const size    = document.getElementById('previewSize');
    if (input.files && input.files[0]) {
        const file = input.files[0];
        name.textContent = file.name;
        size.textContent = (file.size / 1024).toFixed(1) + ' KB';
        preview.classList.add('show');
        document.getElementById('uploadIcon').style.color = '#3b82f6';
        document.getElementById('uploadTitle').textContent = 'File selected';
    }
}

function clearFile() {
    document.getElementById('documentFile').value = '';
    document.getElementById('uploadPreview').classList.remove('show');
    document.getElementById('uploadIcon').style.color = '';
    document.getElementById('uploadTitle').textContent =
        document.getElementById('existingDoc') ? 'Upload replacement file' : 'Click to upload or drag & drop';
}

function toggleRemoveDoc(cb) {
    const area = document.getElementById('uploadArea');
    if (cb.checked) {
        area.style.opacity = '0.4';
        area.style.pointerEvents = 'none';
    } else {
        area.style.opacity = '';
        area.style.pointerEvents = '';
    }
}

// ── Change tracker ───────────────────────────────────────────
const changeList = document.getElementById('changeList');
const tracked    = document.querySelectorAll('[data-original]');
const labels = {
    child_name: "Child's Name", record_no: "Record No.",
    date_of_baptism: "Baptism Date", date_of_birth: "Date of Birth",
    place_of_baptism: "Place of Baptism", place_of_birth: "Place of Birth",
    baptism_type: "Baptism Type", time_of_baptism: "Time of Baptism",
    father_name: "Father's Name", father_place_of_birth: "Father's POB",
    mother_name: "Mother's Name", mother_place_of_birth: "Mother's POB",
    address: "Address",
    godfather: "Godfather", godfather_address: "Godfather Address",
    godmother: "Godmother", godmother_address: "Godmother Address",
    other_sponsors: "Other Sponsors",
    kind_of_marriage: "Kind of Marriage", kind_of_marriage_other: "Marriage (Other)",
    minister: "Minister", remarks: "Remarks"
};

function updateChangeTracker() {
    const changed = [];
    tracked.forEach(el => {
        const name = el.getAttribute('name');
        const orig = el.getAttribute('data-original') || '';
        const curr = el.value || '';
        if (curr.trim() !== orig.trim()) {
            changed.push(labels[name] || name);
        }
    });

    if (changed.length === 0) {
        changeList.textContent = 'No changes yet';
        changeList.style.fontStyle = 'italic';
        changeList.style.color = '#9ca3af';
    } else {
        changeList.style.fontStyle = 'normal';
        changeList.style.color = '#374151';
        changeList.innerHTML = changed.map(f =>
            `<div style="display:flex;align-items:center;gap:6px;margin-bottom:5px;">
                <i class="fas fa-circle" style="color:#f59e0b;font-size:0.4rem;"></i>
                <span>${f}</span>
            </div>`
        ).join('');
    }
}

tracked.forEach(el => {
    el.addEventListener('input', updateChangeTracker);
    el.addEventListener('change', updateChangeTracker);
});

// Highlight changed fields
tracked.forEach(el => {
    el.addEventListener('input', function() {
        const orig = this.getAttribute('data-original') || '';
        if (this.value.trim() !== orig.trim()) {
            this.classList.add('changed');
        } else {
            this.classList.remove('changed');
        }
    });
});

// ── Drag & drop ──────────────────────────────────────────────
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.style.borderColor = '#3b82f6'; });
uploadArea.addEventListener('dragleave', () => { uploadArea.style.borderColor = ''; });
uploadArea.addEventListener('drop',      e => {
    e.preventDefault();
    uploadArea.style.borderColor = '';
    const input = document.getElementById('documentFile');
    input.files = e.dataTransfer.files;
    previewFile(input);
});

// ── Warn on unsaved changes when navigating away ─────────────
let formDirty = false;
tracked.forEach(el => el.addEventListener('input', () => formDirty = true));
window.addEventListener('beforeunload', e => {
    if (formDirty) {
        e.preventDefault();
        e.returnValue = '';
    }
});
document.getElementById('editForm').addEventListener('submit', () => formDirty = false);

// ── Prevent double-submit ────────────────────────────────────
document.getElementById('editForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled  = true;
    btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>

<?php include $root . '/includes/footer.php'; ?>