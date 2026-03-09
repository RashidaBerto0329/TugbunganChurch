<?php
// church/modules/church_records/communion/add_record.php
// Communion Module: Add new communion record
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    http_response_code(403);
    include $root . '/auth/access_denied.php';
    exit;
}

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 1900 || $year > (int)date('Y') + 1) {
    header('Location: /church/modules/church_records/communion/series_list.php');
    exit;
}

// Verify communion_series exists
$s = $conn->prepare("SELECT id FROM communion_series WHERE series_year = ?");
$s->bind_param("i", $year);
$s->execute();
$series = $s->get_result()->fetch_assoc();
$s->close();

if (!$series) {
    $_SESSION['error'] = "No communion series found for {$year}. Please create the series first.";
    header('Location: /church/modules/church_records/communion/series_list.php');
    exit;
}

// Ensure record_series entry for FK
$series_id = null;
$rs = $conn->prepare("SELECT id FROM record_series WHERE type = 'communion' AND year = ?");
$rs->bind_param("i", $year);
$rs->execute();
$rs_row = $rs->get_result()->fetch_assoc();
$rs->close();

if ($rs_row) {
    $series_id = (int)$rs_row['id'];
} else {
    $ins_rs = $conn->prepare("INSERT INTO record_series (type, year, created_by) VALUES ('communion', ?, ?)");
    $ins_rs->bind_param("ii", $year, $current_user_id);
    if ($ins_rs->execute()) {
        $series_id = $conn->insert_id;
    } else {
        $ins_rs->close();
        $_SESSION['error'] = "Unable to create record series for {$year}. Please try again.";
        header('Location: /church/modules/church_records/communion/series_list.php');
        exit;
    }
    $ins_rs->close();
}

// Auto-generate next record number
$rn = $conn->prepare("SELECT COUNT(*) FROM communion_records WHERE series_year = ? AND is_archived = 0");
$rn->bind_param("i", $year);
$rn->execute();
$existing_count = (int)$rn->get_result()->fetch_row()[0];
$rn->close();
$next_record_no = $year . '-COM-' . str_pad($existing_count + 1, 3, '0', STR_PAD_LEFT);

$errors = [];
$old    = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;

    $name              = trim($_POST['name']              ?? '');
    $record_no         = trim($_POST['record_no']         ?? '');
    $date_of_communion = trim($_POST['date_of_communion'] ?? '');
    $date_of_birth     = trim($_POST['date_of_birth']     ?? '') ?: null;
    $father_name       = trim($_POST['father_name']       ?? '');
    $mother_name       = trim($_POST['mother_name']       ?? '');
    $address           = trim($_POST['address']           ?? '');
    $sponsor           = trim($_POST['sponsor']           ?? '');
    $minister          = trim($_POST['minister']          ?? '');
    $baptism_date      = trim($_POST['baptism_date']      ?? '') ?: null;
    $baptism_parish    = trim($_POST['baptism_parish']    ?? '');
    $remarks           = trim($_POST['remarks']           ?? '');

    if ($name === '')              $errors[] = "Communicant's name is required.";
    if ($record_no === '')         $errors[] = "Record number is required.";
    if ($date_of_communion === '') $errors[] = "Date of communion is required.";

    if ($record_no !== '') {
        $dup = $conn->prepare("SELECT id FROM communion_records WHERE record_no = ? AND series_year = ? AND is_archived = 0");
        $dup->bind_param("si", $record_no, $year);
        $dup->execute(); $dup->store_result();
        if ($dup->num_rows > 0) $errors[] = "Record number \"{$record_no}\" already exists in the {$year} series.";
        $dup->close();
    }

    $document_path = null;
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
            $upload_dir = $_SERVER['DOCUMENT_ROOT'] . '/church/uploads/communion/';
            if (!is_dir($upload_dir)) mkdir($upload_dir, 0755, true);
            $ext      = pathinfo($file['name'], PATHINFO_EXTENSION);
            $filename = 'communion_' . $year . '_' . preg_replace('/[^a-z0-9]/i', '_', $record_no) . '_' . time() . '.' . $ext;
            $dest     = $upload_dir . $filename;
            if (move_uploaded_file($file['tmp_name'], $dest)) {
                $document_path = '/church/uploads/communion/' . $filename;
            } else {
                $errors[] = "Failed to save uploaded file.";
            }
        }
    }

    if (empty($errors)) {
        $stmt = $conn->prepare("
            INSERT INTO communion_records
                (series_year, series_id, record_no, name, date_of_communion, date_of_birth,
                 father_name, mother_name, address, sponsor, minister,
                 baptism_date, baptism_parish, remarks, document_path,
                 created_by, created_at)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())
        ");
        $stmt->bind_param(
            "iisssssssssssssi",
            $year, $series_id, $record_no, $name, $date_of_communion, $date_of_birth,
            $father_name, $mother_name, $address, $sponsor, $minister,
            $baptism_date, $baptism_parish, $remarks, $document_path,
            $current_user_id
        );

        if ($stmt->execute()) {
            $new_id = $conn->insert_id;
            $stmt->close();
            $_SESSION['success'] = "Communion record for \"{$name}\" added successfully.";
            header("Location: /church/modules/church_records/communion/view_record.php?id={$new_id}");
            exit;
        } else {
            $errors[] = "Database error: " . $conn->error;
            $stmt->close();
        }
    }
}

$page_title = "Add Communion Record — {$year}";
include $root . '/includes/header.php';
?>

<style>
    .form-card { background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:20px; }
    .form-card-header { padding:16px 24px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px; }
    .form-card-header-icon { width:34px;height:34px;border-radius:9px;display:flex;align-items:center;justify-content:center;font-size:0.85rem;flex-shrink:0; }
    .form-card-title { font-family:'Playfair Display',serif;font-size:0.92rem;font-weight:600;color:#0f2044; }
    .form-card-body { padding:22px 24px; }
    .form-grid-2 { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
    .form-grid-3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px; }
    .col-span-2 { grid-column:span 2; }
    .col-span-3 { grid-column:span 3; }
    .form-group { display:flex;flex-direction:column;gap:5px; }
    .form-label { font-size:0.8rem;font-weight:600;color:#374151; }
    .form-label .req { color:#ef4444;margin-left:2px; }
    .form-label .opt { color:#9ca3af;font-weight:400;font-size:0.72rem;margin-left:4px; }
    .form-input,.form-textarea { padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.865rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif; }
    .form-input:focus,.form-textarea:focus { border-color:#059669;box-shadow:0 0 0 3px rgba(5,150,105,0.1); }
    .form-input::placeholder { color:#c4b89a; }
    .form-textarea { resize:vertical;min-height:80px; }
    .form-hint { font-size:0.72rem;color:#9ca3af;margin-top:2px; }
    .upload-area { border:2px dashed #d4c9b5;border-radius:10px;padding:28px 20px;text-align:center;cursor:pointer;transition:all 0.2s ease;background:#faf7f0;position:relative; }
    .upload-area:hover,.upload-area.dragover { border-color:#059669;background:#f0fdf4; }
    .upload-area input[type="file"] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
    .upload-preview { display:none;align-items:center;gap:12px;padding:12px 16px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-top:12px; }
    .upload-preview.show { display:flex; }
    .error-list { background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px; }
    .error-list ul { margin:6px 0 0 16px;padding:0; }
    .error-list li { font-size:0.82rem;color:#dc2626;margin-bottom:3px; }
    .btn-save { display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#059669;color:#fff;border:none;cursor:pointer;transition:background 0.15s; }
    .btn-save:hover { background:#047857; }
    .btn-save:disabled { background:#f9a8d4;cursor:not-allowed; }
    .btn-cancel-link { display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;transition:all 0.15s; }
    .btn-cancel-link:hover { background:#faf7f0;border-color:#c4b89a;color:#0f2044; }
    @media (max-width:768px) {
        .form-grid-2,.form-grid-3 { grid-template-columns:1fr; }
        .col-span-2,.col-span-3 { grid-column:span 1; }
        #formLayout { grid-template-columns:1fr !important; }
    }
    @media (min-width:769px) { #formLayout > div:last-child { position:sticky;top:20px; } }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div class="page-header-left">
        <h1>
            <i class="fas fa-plus" style="color:#059669;margin-right:8px;font-size:1rem;"></i>
            Add Communion Record
        </h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/communion/series_list.php" style="color:#9ca3af;text-decoration:none;">Communion</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/communion/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Add Record</span>
        </p>
    </div>
</div>

<div style="padding:24px 24px 60px;">
<div style="display:grid;grid-template-columns:1fr 300px;gap:20px;align-items:start;" id="formLayout">

    <?php if (!empty($errors)): ?>
    <div class="error-list" style="grid-column:span 2;">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-circle-exclamation" style="color:#dc2626;"></i>
            <strong style="font-size:0.85rem;color:#dc2626;">Please fix the following errors:</strong>
        </div>
        <ul><?php foreach ($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <form method="POST" enctype="multipart/form-data" id="addRecordForm">

        <!-- SECTION 1: Record Info -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#059669;"><i class="fas fa-hashtag"></i></div>
                <div><div class="form-card-title">Record Information</div><div style="font-size:0.72rem;color:#9ca3af;">Series year and record number</div></div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-3">
                    <div class="form-group">
                        <label class="form-label">Series Year <span class="req">*</span></label>
                        <input type="text" class="form-input" value="<?= $year ?>" readonly style="background:#f9fafb;color:#6b7280;cursor:not-allowed;">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Record Number <span class="req">*</span></label>
                        <input type="text" name="record_no" class="form-input"
                               value="<?= htmlspecialchars($old['record_no'] ?? $next_record_no) ?>"
                               placeholder="e.g. <?= $next_record_no ?>" required>
                        <span class="form-hint">Auto-suggested: <strong><?= $next_record_no ?></strong></span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Communion <span class="req">*</span></label>
                        <input type="date" name="date_of_communion" class="form-input"
                               value="<?= htmlspecialchars($old['date_of_communion'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 2: Communicant Information -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#059669;"><i class="fas fa-bread-slice"></i></div>
                <div><div class="form-card-title">Communicant Information</div><div style="font-size:0.72rem;color:#9ca3af;">Details of the person receiving First Communion</div></div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2" style="margin-bottom:16px;">
                    <div class="form-group col-span-2">
                        <label class="form-label">Full Name <span class="req">*</span></label>
                        <input type="text" name="name" class="form-input"
                               value="<?= htmlspecialchars($old['name'] ?? '') ?>"
                               placeholder="e.g. Maria dela Cruz" required>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Date of Birth <span class="opt">(optional)</span></label>
                        <input type="date" name="date_of_birth" class="form-input"
                               value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Home Address <span class="opt">(optional)</span></label>
                        <input type="text" name="address" class="form-input"
                               value="<?= htmlspecialchars($old['address'] ?? '') ?>"
                               placeholder="e.g. Purok 3, Tugbungan, Zamboanga City">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 3: Parents -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#059669;"><i class="fas fa-users"></i></div>
                <div><div class="form-card-title">Parents</div><div style="font-size:0.72rem;color:#9ca3af;">Father and mother information</div></div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Father's Name <span class="opt">(optional)</span></label>
                        <input type="text" name="father_name" class="form-input"
                               value="<?= htmlspecialchars($old['father_name'] ?? '') ?>"
                               placeholder="e.g. Pedro dela Cruz">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Mother's Name <span class="opt">(optional)</span></label>
                        <input type="text" name="mother_name" class="form-input"
                               value="<?= htmlspecialchars($old['mother_name'] ?? '') ?>"
                               placeholder="e.g. Maria dela Cruz">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 4: Sponsor & Minister -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#fdf8ec;color:#b8933a;"><i class="fas fa-hands-praying"></i></div>
                <div><div class="form-card-title">Sponsor & Minister</div><div style="font-size:0.72rem;color:#9ca3af;">Communion sponsor and officiating minister</div></div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Sponsor <span class="opt">(optional)</span></label>
                        <input type="text" name="sponsor" class="form-input"
                               value="<?= htmlspecialchars($old['sponsor'] ?? '') ?>"
                               placeholder="e.g. Jose Reyes">
                        <span class="form-hint">The sponsor (ninong/ninang) who accompanied the communicant.</span>
                    </div>
                    <div class="form-group">
                        <label class="form-label">Officiating Minister <span class="opt">(optional)</span></label>
                        <input type="text" name="minister" class="form-input"
                               value="<?= htmlspecialchars($old['minister'] ?? '') ?>"
                               placeholder="e.g. Fr. Juan Santos">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 5: Baptism Reference -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#eff6ff;color:#3b82f6;"><i class="fas fa-droplet"></i></div>
                <div><div class="form-card-title">Baptism Reference</div><div style="font-size:0.72rem;color:#9ca3af;">Baptismal details of the communicant (optional)</div></div>
            </div>
            <div class="form-card-body">
                <div class="form-grid-2">
                    <div class="form-group">
                        <label class="form-label">Date of Baptism <span class="opt">(optional)</span></label>
                        <input type="date" name="baptism_date" class="form-input"
                               value="<?= htmlspecialchars($old['baptism_date'] ?? '') ?>"
                               max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="form-group">
                        <label class="form-label">Baptism Parish <span class="opt">(optional)</span></label>
                        <input type="text" name="baptism_parish" class="form-input"
                               value="<?= htmlspecialchars($old['baptism_parish'] ?? 'Our Lady of Peace and Good Voyage Parish') ?>"
                               placeholder="e.g. Our Lady of Peace and Good Voyage Parish">
                    </div>
                </div>
            </div>
        </div>

        <!-- SECTION 6: Document Upload -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-file-image"></i></div>
                <div><div class="form-card-title">Scanned Document</div><div style="font-size:0.72rem;color:#9ca3af;">Upload a photo or scan of the communion record</div></div>
            </div>
            <div class="form-card-body">
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="document" id="documentFile"
                           accept=".jpg,.jpeg,.png,.webp,.pdf"
                           onchange="previewFile(this)">
                    <i class="fas fa-cloud-arrow-up" style="font-size:1.8rem;color:#c4b89a;display:block;margin-bottom:10px;" id="uploadIcon"></i>
                    <div style="font-size:0.85rem;font-weight:600;color:#374151;margin-bottom:4px;" id="uploadTitle">Click to upload or drag & drop</div>
                    <div style="font-size:0.75rem;color:#9ca3af;">JPG, PNG, WEBP or PDF — max 5MB</div>
                </div>
                <div class="upload-preview" id="uploadPreview">
                    <i class="fas fa-file-check" style="color:#16a34a;font-size:1.1rem;flex-shrink:0;"></i>
                    <span style="font-size:0.82rem;font-weight:500;color:#15803d;flex:1;" id="previewName"></span>
                    <span style="font-size:0.72rem;color:#9ca3af;" id="previewSize"></span>
                    <button type="button" style="background:none;border:none;color:#dc2626;cursor:pointer;font-size:0.8rem;" onclick="clearFile()">
                        <i class="fas fa-times"></i>
                    </button>
                </div>
            </div>
        </div>

        <!-- SECTION 7: Remarks -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="form-card-header-icon" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div>
                <div><div class="form-card-title">Remarks</div><div style="font-size:0.72rem;color:#9ca3af;">Additional notes about this record</div></div>
            </div>
            <div class="form-card-body">
                <div class="form-group">
                    <textarea name="remarks" class="form-textarea" rows="3"
                              placeholder="Any additional notes or remarks about this communion record…"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
                </div>
            </div>
        </div>

        <!-- Form Actions -->
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;
                    padding:18px 24px;background:#faf7f0;border:1px solid #ede8de;
                    border-radius:0 0 14px 14px;border-top:1px solid #f3ede3;">
            <a href="/church/modules/church_records/communion/series_records.php?year=<?= $year ?>"
               class="btn-cancel-link"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn-save" id="submitBtn">
                <i class="fas fa-floppy-disk"></i> Save Record
            </button>
        </div>

    </form>

    <!-- RIGHT SIDEBAR -->
    <div style="display:flex;flex-direction:column;gap:16px;">
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#065f46,#059669);padding:16px 18px;">
                <p style="font-family:'Playfair Display',serif;font-size:0.95rem;color:#fff;font-weight:600;margin-bottom:2px;"><?= $year ?> Series</p>
                <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Communion Records</p>
            </div>
            <div style="padding:16px 18px;display:flex;flex-direction:column;gap:12px;">
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.75rem;color:#9ca3af;">Records in series</span>
                    <span style="font-size:0.85rem;font-weight:600;color:#0f2044;"><?= $existing_count ?></span>
                </div>
                <div style="display:flex;justify-content:space-between;align-items:center;">
                    <span style="font-size:0.75rem;color:#9ca3af;">Next record no.</span>
                    <span style="font-size:0.78rem;font-weight:600;color:#059669;font-family:monospace;"><?= $next_record_no ?></span>
                </div>
                <hr style="border:none;border-top:1px solid #f3ede3;margin:0;">
                <a href="/church/modules/church_records/communion/series_records.php?year=<?= $year ?>"
                   style="display:flex;align-items:center;gap:7px;font-size:0.78rem;color:#059669;text-decoration:none;">
                    <i class="fas fa-arrow-left" style="font-size:0.65rem;"></i> Back to <?= $year ?> series
                </a>
            </div>
        </div>

        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.8rem;font-weight:600;color:#0f2044;margin-bottom:12px;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-circle-info" style="color:#b8933a;font-size:0.8rem;"></i> Required Fields
            </p>
            <div style="display:flex;flex-direction:column;gap:8px;">
                <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;"><i class="fas fa-check-circle" style="color:#059669;font-size:0.75rem;"></i> Record Number</div>
                <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;"><i class="fas fa-check-circle" style="color:#059669;font-size:0.75rem;"></i> Date of First Communion</div>
                <div style="display:flex;align-items:center;gap:8px;font-size:0.78rem;color:#374151;"><i class="fas fa-check-circle" style="color:#059669;font-size:0.75rem;"></i> Communicant's Full Name</div>
            </div>
            <hr style="border:none;border-top:1px solid #f3ede3;margin:12px 0;">
            <p style="font-size:0.72rem;color:#9ca3af;line-height:1.5;">All other fields are optional but recommended for complete records.</p>
        </div>

        <div style="background:#f0fdf4;border:1px solid #bbf7d0;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.8rem;font-weight:600;color:#065f46;margin-bottom:8px;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-bread-slice" style="font-size:0.8rem;"></i> Communion Note
            </p>
            <p style="font-size:0.75rem;color:#047857;line-height:1.55;">
                First Holy Communion records are managed by the Katalista and not available
                for online booking. Add records here after the ceremony is completed.
            </p>
        </div>
    </div>

</div>
</div>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        document.getElementById('previewName').textContent = file.name;
        document.getElementById('previewSize').textContent = (file.size / 1024).toFixed(1) + ' KB';
        document.getElementById('uploadPreview').classList.add('show');
        document.getElementById('uploadIcon').style.color = '#059669';
        document.getElementById('uploadTitle').textContent = 'File selected';
    }
}
function clearFile() {
    document.getElementById('documentFile').value = '';
    document.getElementById('uploadPreview').classList.remove('show');
    document.getElementById('uploadIcon').style.color = '';
    document.getElementById('uploadTitle').textContent = 'Click to upload or drag & drop';
}
const uploadArea = document.getElementById('uploadArea');
uploadArea.addEventListener('dragover',  e => { e.preventDefault(); uploadArea.classList.add('dragover'); });
uploadArea.addEventListener('dragleave', () => uploadArea.classList.remove('dragover'));
uploadArea.addEventListener('drop',      e => {
    e.preventDefault(); uploadArea.classList.remove('dragover');
    const input = document.getElementById('documentFile');
    input.files = e.dataTransfer.files; previewFile(input);
});
document.getElementById('addRecordForm').addEventListener('submit', function() {
    const btn = document.getElementById('submitBtn');
    btn.disabled = true; btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>

<?php include $root . '/includes/footer.php'; ?>