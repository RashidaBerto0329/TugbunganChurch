<?php
// church/modules/church_records/wedding/add_record.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    include $root . '/auth/access_denied.php'; exit;
}

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 1900 || $year > (int)date('Y') + 1) {
    header('Location: /church/modules/church_records/wedding/series_list.php'); exit;
}

$s = $conn->prepare("SELECT id FROM wedding_series WHERE series_year = ?");
$s->bind_param("i", $year); $s->execute();
$series = $s->get_result()->fetch_assoc(); $s->close();
if (!$series) {
    $_SESSION['error'] = "No wedding series for {$year}.";
    header('Location: /church/modules/church_records/wedding/series_list.php'); exit;
}

$rn = $conn->prepare("SELECT COUNT(*) FROM wedding_records WHERE series_year = ? AND is_archived = 0");
$rn->bind_param("i", $year); $rn->execute();
$existing_count = (int)$rn->get_result()->fetch_row()[0]; $rn->close();
$next_record_no = $year . '-W-' . str_pad($existing_count + 1, 3, '0', STR_PAD_LEFT);

$errors = []; $old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $groom_name              = trim($_POST['groom_name']              ?? '');
    $bride_name              = trim($_POST['bride_name']              ?? '');
    $record_no               = trim($_POST['record_no']               ?? '');
    $date_of_wedding         = trim($_POST['date_of_wedding']         ?? '');
    $groom_dob               = trim($_POST['groom_dob']               ?? '') ?: null;
    $bride_dob               = trim($_POST['bride_dob']               ?? '') ?: null;
    $groom_address           = trim($_POST['groom_address']           ?? '');
    $bride_address           = trim($_POST['bride_address']           ?? '');
    $groom_father            = trim($_POST['groom_father']            ?? '');
    $groom_mother            = trim($_POST['groom_mother']            ?? '');
    $bride_father            = trim($_POST['bride_father']            ?? '');
    $bride_mother            = trim($_POST['bride_mother']            ?? '');
    $principal_sponsor_male  = trim($_POST['principal_sponsor_male']  ?? '');
    $principal_sponsor_female= trim($_POST['principal_sponsor_female']?? '');
    $witness1_name           = trim($_POST['witness1_name']           ?? '');
    $witness2_name           = trim($_POST['witness2_name']           ?? '');
    $minister                = trim($_POST['minister']                ?? '');
    $remarks                 = trim($_POST['remarks']                 ?? '');

    if ($groom_name === '')      $errors[] = "Groom's name is required.";
    if ($bride_name === '')      $errors[] = "Bride's name is required.";
    if ($record_no === '')       $errors[] = "Record number is required.";
    if ($date_of_wedding === '') $errors[] = "Date of wedding is required.";

    if ($record_no !== '') {
        $dup = $conn->prepare("SELECT id FROM wedding_records WHERE record_no = ? AND series_year = ? AND is_archived = 0");
        $dup->bind_param("si", $record_no, $year); $dup->execute(); $dup->store_result();
        if ($dup->num_rows > 0) $errors[] = "Record number \"{$record_no}\" already exists in {$year}.";
        $dup->close();
    }

    $document_path = null;
    if (!empty($_FILES['document']['name'])) {
        $file = $_FILES['document'];
        $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
        if (!in_array($file['type'], $allowed))        $errors[] = "Invalid file type.";
        elseif ($file['size'] > 5*1024*1024)           $errors[] = "File too large (max 5MB).";
        elseif ($file['error'] !== UPLOAD_ERR_OK)      $errors[] = "Upload failed.";
        else {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/church/uploads/wedding/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext  = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fname= 'wedding_' . $year . '_' . preg_replace('/[^a-z0-9]/i','_',$record_no) . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                $document_path = '/church/uploads/wedding/' . $fname;
            } else { $errors[] = "Failed to save file."; }
        }
    }

    if (empty($errors)) {
        // Fetch series_id from central record_series table
        $rs = $conn->prepare("SELECT id FROM record_series WHERE type = 'wedding' AND year = ?");
        $rs->bind_param("i", $year);
        $rs->execute();
        $rs_row = $rs->get_result()->fetch_assoc();
        $rs->close();

        if (!$rs_row) {
            // Auto-create the record_series entry if missing (safety net)
            $rs_ins = $conn->prepare("INSERT INTO record_series (type, year, created_by) VALUES ('wedding', ?, ?)");
            $rs_ins->bind_param("ii", $year, $current_user_id);
            $rs_ins->execute();
            $series_id = (int)$conn->insert_id;
            $rs_ins->close();
        } else {
            $series_id = (int)$rs_row['id'];
        }

        $stmt = $conn->prepare("
            INSERT INTO wedding_records
                (series_id, series_year, record_no, groom_name, bride_name, date_of_wedding,
                 groom_dob, bride_dob, groom_address, bride_address,
                 groom_father, groom_mother, bride_father, bride_mother,
                 principal_sponsor_male, principal_sponsor_female,
                 witness1_name, witness2_name, minister, remarks,
                 document_path, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        $stmt->bind_param("iisssssssssssssssssssi",
            $series_id, $year, $record_no, $groom_name, $bride_name, $date_of_wedding,
            $groom_dob, $bride_dob, $groom_address, $bride_address,
            $groom_father, $groom_mother, $bride_father, $bride_mother,
            $principal_sponsor_male, $principal_sponsor_female,
            $witness1_name, $witness2_name, $minister, $remarks,
            $document_path, $current_user_id
        );
        if ($stmt->execute()) {
            $new_id = $conn->insert_id; $stmt->close();
            $_SESSION['success'] = "Wedding record for {$groom_name} & {$bride_name} added successfully.";
            header("Location: /church/modules/church_records/wedding/view_record.php?id={$new_id}"); exit;
        } else { $errors[] = "DB error: " . $conn->error; $stmt->close(); }
    }
}

$page_title = "Add Wedding Record — {$year}";
include $root . '/includes/header.php';
?>
<style>
    .form-card { background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:18px; }
    .form-card-header { padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px; }
    .fch-icon { width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0; }
    .form-card-title { font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;color:#0f2044; }
    .form-card-body { padding:20px 22px; }
    .fg2 { display:grid;grid-template-columns:1fr 1fr;gap:16px; }
    .fg3 { display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px; }
    .cs2 { grid-column:span 2; } .cs3 { grid-column:span 3; }
    .fg { display:flex;flex-direction:column;gap:5px; }
    .lbl { font-size:0.8rem;font-weight:600;color:#374151; }
    .lbl .req { color:#ef4444; } .lbl .opt { color:#9ca3af;font-weight:400;font-size:0.72rem;margin-left:4px; }
    .inp,.sel,.txa { padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.865rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif; }
    .inp:focus,.sel:focus,.txa:focus { border-color:#d97706;box-shadow:0 0 0 3px rgba(217,119,6,0.1); }
    .inp.error { border-color:#ef4444; }
    .txa { resize:vertical;min-height:70px; }
    .hint { font-size:0.72rem;color:#9ca3af;margin-top:2px; }
    .upload-area { border:2px dashed #d4c9b5;border-radius:10px;padding:24px 20px;text-align:center;cursor:pointer;transition:all 0.2s;background:#faf7f0;position:relative; }
    .upload-area:hover { border-color:#d97706;background:#fef3c7; }
    .upload-area input[type="file"] { position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%; }
    .upload-preview { display:none;align-items:center;gap:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-top:10px; }
    .upload-preview.show { display:flex; }
    .error-list { background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px; }
    .error-list ul { margin:6px 0 0 16px;padding:0; }
    .error-list li { font-size:0.82rem;color:#dc2626;margin-bottom:3px; }
    .btn-save { display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#d97706;color:#fff;border:none;cursor:pointer;transition:background 0.15s; }
    .btn-save:hover { background:#b45309; }
    .btn-cancel { display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none; }
    .info-row { display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f0e8;font-size:0.8rem; }
    .info-row:last-child { border-bottom:none; }
    /* Couple divider */
    .couple-header { display:flex;align-items:center;gap:12px;margin-bottom:16px; }
    .couple-label { font-family:'Playfair Display',serif;font-size:0.85rem;font-weight:600;color:#0f2044;white-space:nowrap; }
    .couple-line { flex:1;height:1px;background:#f3ede3; }
    @media(max-width:900px){ #addLayout{grid-template-columns:1fr!important;} }
    @media(max-width:768px){ .fg2,.fg3{grid-template-columns:1fr;} .cs2,.cs3{grid-column:span 1;} }
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-plus" style="color:#d97706;margin-right:8px;font-size:1rem;"></i>Add Wedding Record</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/wedding/series_list.php" style="color:#9ca3af;text-decoration:none;">Wedding</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/wedding/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Add Record</span>
        </p>
    </div>
</div>

<div style="padding:24px 24px 60px;">

    <?php if (!empty($errors)): ?>
    <div class="error-list">
        <div style="display:flex;align-items:center;gap:8px;">
            <i class="fas fa-circle-exclamation" style="color:#dc2626;"></i>
            <strong style="font-size:0.85rem;color:#dc2626;">Please fix the following:</strong>
        </div>
        <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="addLayout">
    <div>
    <form method="POST" enctype="multipart/form-data" id="addForm">

        <!-- Section 1: Record Info -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="fch-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-hashtag"></i></div>
                <div><div class="form-card-title">Record Information</div><div style="font-size:0.72rem;color:#9ca3af;">Series year and record number</div></div>
            </div>
            <div class="form-card-body">
                <div class="fg3">
                    <div class="fg">
                        <label class="lbl">Series Year</label>
                        <input type="text" class="inp" value="<?= $year ?>" readonly style="background:#f9fafb;color:#6b7280;cursor:not-allowed;">
                    </div>
                    <div class="fg">
                        <label class="lbl">Record Number <span class="req">*</span></label>
                        <input type="text" name="record_no" class="inp <?= str_contains(implode('',$errors),'Record number') ? 'error':'' ?>"
                               value="<?= htmlspecialchars($old['record_no'] ?? $next_record_no) ?>" required>
                        <span class="hint">Suggested: <strong><?= $next_record_no ?></strong></span>
                    </div>
                    <div class="fg">
                        <label class="lbl">Date of Wedding <span class="req">*</span></label>
                        <input type="date" name="date_of_wedding" class="inp <?= in_array('Date of wedding is required.',$errors)?'error':'' ?>"
                               value="<?= htmlspecialchars($old['date_of_wedding'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required>
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 2: Couple -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="fch-icon" style="background:#fef3c7;color:#d97706;"><i class="fas fa-ring"></i></div>
                <div><div class="form-card-title">The Couple</div><div style="font-size:0.72rem;color:#9ca3af;">Groom and bride information</div></div>
            </div>
            <div class="form-card-body">

                <!-- GROOM -->
                <div class="couple-header">
                    <span class="couple-label"><i class="fas fa-mars" style="color:#3b82f6;margin-right:5px;font-size:0.8rem;"></i>Groom</span>
                    <div class="couple-line"></div>
                </div>
                <div class="fg3" style="margin-bottom:20px;">
                    <div class="fg cs3">
                        <label class="lbl">Groom's Full Name <span class="req">*</span></label>
                        <input type="text" name="groom_name" class="inp <?= in_array("Groom's name is required.",$errors)?'error':'' ?>"
                               value="<?= htmlspecialchars($old['groom_name'] ?? '') ?>" placeholder="e.g. Juan dela Cruz" required>
                    </div>
                    <div class="fg">
                        <label class="lbl">Date of Birth <span class="opt">(optional)</span></label>
                        <input type="date" name="groom_dob" class="inp" value="<?= htmlspecialchars($old['groom_dob'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="fg cs2">
                        <label class="lbl">Address <span class="opt">(optional)</span></label>
                        <input type="text" name="groom_address" class="inp" value="<?= htmlspecialchars($old['groom_address'] ?? '') ?>" placeholder="e.g. Tugbungan, Zamboanga City">
                    </div>
                    <div class="fg">
                        <label class="lbl">Father's Name <span class="opt">(optional)</span></label>
                        <input type="text" name="groom_father" class="inp" value="<?= htmlspecialchars($old['groom_father'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label class="lbl">Mother's Name <span class="opt">(optional)</span></label>
                        <input type="text" name="groom_mother" class="inp" value="<?= htmlspecialchars($old['groom_mother'] ?? '') ?>">
                    </div>
                </div>

                <!-- BRIDE -->
                <div class="couple-header">
                    <span class="couple-label"><i class="fas fa-venus" style="color:#ec4899;margin-right:5px;font-size:0.8rem;"></i>Bride</span>
                    <div class="couple-line"></div>
                </div>
                <div class="fg3">
                    <div class="fg cs3">
                        <label class="lbl">Bride's Full Name <span class="req">*</span></label>
                        <input type="text" name="bride_name" class="inp <?= in_array("Bride's name is required.",$errors)?'error':'' ?>"
                               value="<?= htmlspecialchars($old['bride_name'] ?? '') ?>" placeholder="e.g. Maria Santos" required>
                    </div>
                    <div class="fg">
                        <label class="lbl">Date of Birth <span class="opt">(optional)</span></label>
                        <input type="date" name="bride_dob" class="inp" value="<?= htmlspecialchars($old['bride_dob'] ?? '') ?>" max="<?= date('Y-m-d') ?>">
                    </div>
                    <div class="fg cs2">
                        <label class="lbl">Address <span class="opt">(optional)</span></label>
                        <input type="text" name="bride_address" class="inp" value="<?= htmlspecialchars($old['bride_address'] ?? '') ?>" placeholder="e.g. Zamboanga City">
                    </div>
                    <div class="fg">
                        <label class="lbl">Father's Name <span class="opt">(optional)</span></label>
                        <input type="text" name="bride_father" class="inp" value="<?= htmlspecialchars($old['bride_father'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label class="lbl">Mother's Name <span class="opt">(optional)</span></label>
                        <input type="text" name="bride_mother" class="inp" value="<?= htmlspecialchars($old['bride_mother'] ?? '') ?>">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 3: Sponsors & Witnesses -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="fch-icon" style="background:#fdf8ec;color:#b8933a;"><i class="fas fa-hands-praying"></i></div>
                <div><div class="form-card-title">Principal Sponsors & Witnesses</div><div style="font-size:0.72rem;color:#9ca3af;">Sponsors and officiating minister</div></div>
            </div>
            <div class="form-card-body">
                <div class="fg2" style="margin-bottom:16px;">
                    <div class="fg">
                        <label class="lbl">Principal Sponsor (Male) <span class="opt">(optional)</span></label>
                        <input type="text" name="principal_sponsor_male" class="inp" value="<?= htmlspecialchars($old['principal_sponsor_male'] ?? '') ?>" placeholder="e.g. Jose Reyes">
                    </div>
                    <div class="fg">
                        <label class="lbl">Principal Sponsor (Female) <span class="opt">(optional)</span></label>
                        <input type="text" name="principal_sponsor_female" class="inp" value="<?= htmlspecialchars($old['principal_sponsor_female'] ?? '') ?>" placeholder="e.g. Ana Reyes">
                    </div>
                    <div class="fg">
                        <label class="lbl">Witness 1 <span class="opt">(optional)</span></label>
                        <input type="text" name="witness1_name" class="inp" value="<?= htmlspecialchars($old['witness1_name'] ?? '') ?>">
                    </div>
                    <div class="fg">
                        <label class="lbl">Witness 2 <span class="opt">(optional)</span></label>
                        <input type="text" name="witness2_name" class="inp" value="<?= htmlspecialchars($old['witness2_name'] ?? '') ?>">
                    </div>
                    <div class="fg cs2">
                        <label class="lbl">Officiating Minister <span class="opt">(optional)</span></label>
                        <input type="text" name="minister" class="inp" value="<?= htmlspecialchars($old['minister'] ?? '') ?>" placeholder="e.g. Fr. Juan Santos">
                    </div>
                </div>
            </div>
        </div>

        <!-- Section 4: Document -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="fch-icon" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-file-image"></i></div>
                <div><div class="form-card-title">Scanned Document</div><div style="font-size:0.72rem;color:#9ca3af;">Upload scanned marriage record</div></div>
            </div>
            <div class="form-card-body">
                <div class="upload-area" id="uploadArea">
                    <input type="file" name="document" id="documentFile" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="previewFile(this)">
                    <i class="fas fa-cloud-arrow-up" style="font-size:1.6rem;color:#c4b89a;display:block;margin-bottom:8px;" id="uploadIcon"></i>
                    <div style="font-size:0.82rem;font-weight:600;color:#374151;margin-bottom:3px;" id="uploadTitle">Click to upload or drag & drop</div>
                    <div style="font-size:0.73rem;color:#9ca3af;">JPG, PNG, WEBP or PDF — max 5MB</div>
                </div>
                <div class="upload-preview" id="uploadPreview">
                    <i class="fas fa-file-check" style="color:#16a34a;font-size:1rem;flex-shrink:0;"></i>
                    <span style="font-size:0.82rem;font-weight:500;color:#15803d;flex:1;" id="previewName"></span>
                    <span style="font-size:0.72rem;color:#9ca3af;" id="previewSize"></span>
                    <button type="button" style="background:none;border:none;color:#dc2626;cursor:pointer;" onclick="clearFile()"><i class="fas fa-times"></i></button>
                </div>
            </div>
        </div>

        <!-- Section 5: Remarks -->
        <div class="form-card">
            <div class="form-card-header">
                <div class="fch-icon" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div>
                <div><div class="form-card-title">Remarks</div></div>
            </div>
            <div class="form-card-body">
                <textarea name="remarks" class="txa" rows="3" placeholder="Additional notes…"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea>
            </div>
        </div>

        <!-- Actions -->
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;
                    padding:18px 22px;background:#faf7f0;border:1px solid #ede8de;border-radius:0 0 14px 14px;">
            <a href="/church/modules/church_records/wedding/series_records.php?year=<?= $year ?>" class="btn-cancel">
                <i class="fas fa-times"></i> Cancel
            </a>
            <button type="submit" class="btn-save" id="submitBtn">
                <i class="fas fa-floppy-disk"></i> Save Record
            </button>
        </div>

    </form>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:20px;">
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#b45309,#d97706);padding:16px 18px;">
                <p style="font-family:'Playfair Display',serif;font-size:0.95rem;color:#fff;font-weight:600;margin-bottom:2px;"><?= $year ?> Series</p>
                <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Wedding Records</p>
            </div>
            <div style="padding:14px 18px;">
                <div class="info-row"><span style="color:#9ca3af;font-size:0.8rem;">Records in series</span><span style="font-size:0.85rem;font-weight:600;color:#0f2044;"><?= $existing_count ?></span></div>
                <div class="info-row"><span style="color:#9ca3af;font-size:0.8rem;">Next record no.</span><span style="font-size:0.78rem;font-weight:600;color:#d97706;font-family:monospace;"><?= $next_record_no ?></span></div>
                <div style="border-top:1px solid #f3ede3;margin-top:10px;padding-top:10px;">
                    <a href="/church/modules/church_records/wedding/series_records.php?year=<?= $year ?>"
                       style="display:flex;align-items:center;gap:7px;font-size:0.78rem;color:#d97706;text-decoration:none;">
                        <i class="fas fa-arrow-left" style="font-size:0.65rem;"></i> Back to <?= $year ?> series
                    </a>
                </div>
            </div>
        </div>
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.8rem;font-weight:600;color:#0f2044;margin-bottom:10px;display:flex;align-items:center;gap:7px;">
                <i class="fas fa-circle-info" style="color:#b8933a;"></i> Required Fields
            </p>
            <div style="display:flex;flex-direction:column;gap:7px;font-size:0.78rem;color:#374151;">
                <?php foreach (['Record Number','Date of Wedding','Groom\'s Name','Bride\'s Name'] as $rf): ?>
                <div style="display:flex;align-items:center;gap:7px;">
                    <i class="fas fa-check-circle" style="color:#d97706;font-size:0.72rem;"></i><?= $rf ?>
                </div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>

    </div>
</div>

<script>
function previewFile(input) {
    const p = document.getElementById('uploadPreview');
    if (input.files && input.files[0]) {
        document.getElementById('previewName').textContent = input.files[0].name;
        document.getElementById('previewSize').textContent = (input.files[0].size/1024).toFixed(1)+' KB';
        p.classList.add('show');
        document.getElementById('uploadIcon').style.color = '#d97706';
        document.getElementById('uploadTitle').textContent = 'File selected';
    }
}
function clearFile() {
    document.getElementById('documentFile').value='';
    document.getElementById('uploadPreview').classList.remove('show');
    document.getElementById('uploadIcon').style.color='';
    document.getElementById('uploadTitle').textContent='Click to upload or drag & drop';
}
const ua = document.getElementById('uploadArea');
ua.addEventListener('dragover',e=>{e.preventDefault();ua.style.borderColor='#d97706';});
ua.addEventListener('dragleave',()=>ua.style.borderColor='');
ua.addEventListener('drop',e=>{e.preventDefault();ua.style.borderColor='';const i=document.getElementById('documentFile');i.files=e.dataTransfer.files;previewFile(i);});
document.getElementById('addForm').addEventListener('submit',function(){
    const b=document.getElementById('submitBtn');b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>
<?php include $root . '/includes/footer.php'; ?>