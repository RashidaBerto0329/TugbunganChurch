<?php
// church/modules/church_records/funeral/add_record.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    include $root . '/auth/access_denied.php'; exit;
}

$year = (int)($_GET['year'] ?? date('Y'));
if ($year < 1900 || $year > (int)date('Y') + 1) {
    header('Location: /church/modules/church_records/funeral/series_list.php'); exit;
}

$s = $conn->prepare("SELECT id FROM funeral_series WHERE series_year = ?");
$s->bind_param("i", $year); $s->execute();
$series = $s->get_result()->fetch_assoc(); $s->close();
if (!$series) {
    $_SESSION['error'] = "No funeral series for {$year}.";
    header('Location: /church/modules/church_records/funeral/series_list.php'); exit;
}

$rn = $conn->prepare("SELECT COUNT(*) FROM funeral_records WHERE series_year = ? AND is_archived = 0");
$rn->bind_param("i", $year); $rn->execute();
$existing_count = (int)$rn->get_result()->fetch_row()[0]; $rn->close();
$next_record_no = $year . '-F-' . str_pad($existing_count + 1, 3, '0', STR_PAD_LEFT);

$errors = []; $old = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $deceased_name      = trim($_POST['deceased_name']      ?? '');
    $record_no          = trim($_POST['record_no']          ?? '');
    $date_of_death      = trim($_POST['date_of_death']      ?? '') ?: null;
    $date_of_birth      = trim($_POST['date_of_birth']      ?? '') ?: null;
    $date_of_funeral    = trim($_POST['date_of_funeral']    ?? '');
    $address            = trim($_POST['address']            ?? '');
    $place_of_burial    = trim($_POST['place_of_burial']    ?? '');
    $next_of_kin        = trim($_POST['next_of_kin']        ?? '');
    $next_of_kin_name   = trim($_POST['next_of_kin_name']   ?? '');
    $next_of_kin_contact= trim($_POST['next_of_kin_contact']?? '');
    $minister           = trim($_POST['minister']           ?? '');
    $remarks            = trim($_POST['remarks']            ?? '');

    if ($deceased_name === '')   $errors[] = "Deceased name is required.";
    if ($record_no === '')       $errors[] = "Record number is required.";
    if ($date_of_funeral === '') $errors[] = "Date of funeral is required.";

    if ($record_no !== '') {
        $dup = $conn->prepare("SELECT id FROM funeral_records WHERE record_no = ? AND series_year = ? AND is_archived = 0");
        $dup->bind_param("si", $record_no, $year); $dup->execute(); $dup->store_result();
        if ($dup->num_rows > 0) $errors[] = "Record number \"{$record_no}\" already exists in {$year}.";
        $dup->close();
    }

    $document_path = null;
    if (!empty($_FILES['document']['name'])) {
        $file = $_FILES['document'];
        $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
        if (!in_array($file['type'], $allowed))     $errors[] = "Invalid file type.";
        elseif ($file['size'] > 5*1024*1024)        $errors[] = "File too large (max 5MB).";
        elseif ($file['error'] !== UPLOAD_ERR_OK)   $errors[] = "Upload failed.";
        else {
            $dir = $_SERVER['DOCUMENT_ROOT'] . '/church/uploads/funeral/';
            if (!is_dir($dir)) mkdir($dir, 0755, true);
            $ext   = pathinfo($file['name'], PATHINFO_EXTENSION);
            $fname = 'funeral_' . $year . '_' . preg_replace('/[^a-z0-9]/i','_',$record_no) . '_' . time() . '.' . $ext;
            if (move_uploaded_file($file['tmp_name'], $dir . $fname)) {
                $document_path = '/church/uploads/funeral/' . $fname;
            } else { $errors[] = "Failed to save file."; }
        }
    }

    if (empty($errors)) {
        // Fetch series_id from record_series
        $rs = $conn->prepare("SELECT id FROM record_series WHERE type = 'funeral' AND year = ?");
        $rs->bind_param("i", $year); $rs->execute();
        $rs_row = $rs->get_result()->fetch_assoc(); $rs->close();
        if (!$rs_row) {
            $rs_ins = $conn->prepare("INSERT INTO record_series (type, year, created_by) VALUES ('funeral', ?, ?)");
            $rs_ins->bind_param("ii", $year, $current_user_id);
            $rs_ins->execute();
            $series_id = (int)$conn->insert_id;
            $rs_ins->close();
        } else {
            $series_id = (int)$rs_row['id'];
        }

        $stmt = $conn->prepare("
            INSERT INTO funeral_records
                (series_id, series_year, record_no, deceased_name,
                 date_of_birth, date_of_death, date_of_funeral,
                 address, place_of_burial,
                 next_of_kin, next_of_kin_name, next_of_kin_contact,
                 minister, remarks, document_path, created_by, created_at)
            VALUES (?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,NOW())
        ");
        // ii s s s s s s s s s s s s s i = 2i + 13s + i = 16
        $stmt->bind_param("iisssssssssssssi",
            $series_id, $year, $record_no, $deceased_name,
            $date_of_birth, $date_of_death, $date_of_funeral,
            $address, $place_of_burial,
            $next_of_kin, $next_of_kin_name, $next_of_kin_contact,
            $minister, $remarks, $document_path, $current_user_id
        );
        if ($stmt->execute()) {
            $new_id = $conn->insert_id; $stmt->close();
            $_SESSION['success'] = "Funeral record for \"{$deceased_name}\" added successfully.";
            header("Location: /church/modules/church_records/funeral/view_record.php?id={$new_id}"); exit;
        } else { $errors[] = "DB error: " . $conn->error; $stmt->close(); }
    }
}

$page_title = "Add Funeral Record — {$year}";
include $root . '/includes/header.php';
?>
<style>
    .form-card{background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:18px;}
    .fch{padding:14px 22px;border-bottom:1px solid #f3ede3;display:flex;align-items:center;gap:10px;}
    .fchi{width:32px;height:32px;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:0.8rem;flex-shrink:0;}
    .fct{font-family:'Playfair Display',serif;font-size:0.88rem;font-weight:600;color:#0f2044;}
    .fcb{padding:20px 22px;}
    .fg2{display:grid;grid-template-columns:1fr 1fr;gap:16px;}
    .fg3{display:grid;grid-template-columns:1fr 1fr 1fr;gap:16px;}
    .cs2{grid-column:span 2;} .cs3{grid-column:span 3;}
    .fg{display:flex;flex-direction:column;gap:5px;}
    .lbl{font-size:0.8rem;font-weight:600;color:#374151;}
    .lbl .req{color:#ef4444;} .lbl .opt{color:#9ca3af;font-weight:400;font-size:0.72rem;margin-left:4px;}
    .inp,.txa{padding:9px 12px;border:1px solid #ede8de;border-radius:8px;font-size:0.865rem;color:#1a1a2e;background:#fff;outline:none;transition:border-color 0.18s,box-shadow 0.18s;width:100%;font-family:'DM Sans',sans-serif;}
    .inp:focus,.txa:focus{border-color:#4b5563;box-shadow:0 0 0 3px rgba(75,85,99,0.12);}
    .inp.error{border-color:#ef4444;}
    .txa{resize:vertical;min-height:70px;}
    .hint{font-size:0.72rem;color:#9ca3af;margin-top:2px;}
    .upload-area{border:2px dashed #d4c9b5;border-radius:10px;padding:24px 20px;text-align:center;cursor:pointer;transition:all 0.2s;background:#faf7f0;position:relative;}
    .upload-area:hover{border-color:#4b5563;background:#f3f4f6;}
    .upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .upload-preview{display:none;align-items:center;gap:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-top:10px;}
    .upload-preview.show{display:flex;}
    .error-list{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px;}
    .error-list ul{margin:6px 0 0 16px;padding:0;}
    .error-list li{font-size:0.82rem;color:#dc2626;margin-bottom:3px;}
    .btn-save{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#4b5563;color:#fff;border:none;cursor:pointer;transition:background 0.15s;}
    .btn-save:hover{background:#374151;}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;}
    .info-row{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f0e8;font-size:0.8rem;}
    .info-row:last-child{border-bottom:none;}
    @media(max-width:900px){#addLayout{grid-template-columns:1fr!important;}}
    @media(max-width:768px){.fg2,.fg3{grid-template-columns:1fr;}.cs2,.cs3{grid-column:span 1;}}
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-plus" style="color:#6b7280;margin-right:8px;font-size:1rem;"></i>Add Funeral Record</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/funeral/series_list.php" style="color:#9ca3af;text-decoration:none;">Funeral</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/funeral/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Add Record</span>
        </p>
    </div>
</div>

<div style="padding:24px 24px 60px;">
    <?php if (!empty($errors)): ?>
    <div class="error-list">
        <div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-circle-exclamation" style="color:#dc2626;"></i><strong style="font-size:0.85rem;color:#dc2626;">Please fix the following:</strong></div>
        <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul>
    </div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="addLayout">
    <div>
    <form method="POST" enctype="multipart/form-data" id="addForm">

        <!-- Record Info -->
        <div class="form-card">
            <div class="fch"><div class="fchi" style="background:#f3f4f6;color:#4b5563;"><i class="fas fa-hashtag"></i></div><div><div class="fct">Record Information</div><div style="font-size:0.72rem;color:#9ca3af;">Series year and record number</div></div></div>
            <div class="fcb"><div class="fg3">
                <div class="fg"><label class="lbl">Series Year</label><input type="text" class="inp" value="<?= $year ?>" readonly style="background:#f9fafb;color:#6b7280;cursor:not-allowed;"></div>
                <div class="fg"><label class="lbl">Record Number <span class="req">*</span></label><input type="text" name="record_no" class="inp" value="<?= htmlspecialchars($old['record_no'] ?? $next_record_no) ?>" required><span class="hint">Suggested: <strong><?= $next_record_no ?></strong></span></div>
                <div class="fg"><label class="lbl">Date of Funeral <span class="req">*</span></label><input type="date" name="date_of_funeral" class="inp" value="<?= htmlspecialchars($old['date_of_funeral'] ?? '') ?>" max="<?= date('Y-m-d') ?>" required></div>
            </div></div>
        </div>

        <!-- Deceased -->
        <div class="form-card">
            <div class="fch"><div class="fchi" style="background:#f3f4f6;color:#374151;"><i class="fas fa-cross"></i></div><div><div class="fct">Deceased Information</div><div style="font-size:0.72rem;color:#9ca3af;">Personal details of the deceased</div></div></div>
            <div class="fcb"><div class="fg3">
                <div class="fg cs3"><label class="lbl">Deceased Full Name <span class="req">*</span></label><input type="text" name="deceased_name" class="inp" value="<?= htmlspecialchars($old['deceased_name'] ?? '') ?>" placeholder="e.g. Juan dela Cruz" required></div>
                <div class="fg"><label class="lbl">Date of Birth <span class="opt">(optional)</span></label><input type="date" name="date_of_birth" class="inp" value="<?= htmlspecialchars($old['date_of_birth'] ?? '') ?>" max="<?= date('Y-m-d') ?>"></div>
                <div class="fg"><label class="lbl">Date of Death <span class="opt">(optional)</span></label><input type="date" name="date_of_death" class="inp" value="<?= htmlspecialchars($old['date_of_death'] ?? '') ?>" max="<?= date('Y-m-d') ?>"></div>
                <div class="fg"><label class="lbl">Officiating Minister <span class="opt">(optional)</span></label><input type="text" name="minister" class="inp" value="<?= htmlspecialchars($old['minister'] ?? '') ?>" placeholder="e.g. Fr. Juan Santos"></div>
                <div class="fg cs2"><label class="lbl">Address <span class="opt">(optional)</span></label><input type="text" name="address" class="inp" value="<?= htmlspecialchars($old['address'] ?? '') ?>" placeholder="e.g. Tugbungan, Zamboanga City"></div>
                <div class="fg cs3"><label class="lbl">Place of Burial <span class="opt">(optional)</span></label><input type="text" name="place_of_burial" class="inp" value="<?= htmlspecialchars($old['place_of_burial'] ?? '') ?>" placeholder="e.g. Lawn Crest Memorial Park, Zamboanga City"></div>
            </div></div>
        </div>

        <!-- Next of Kin -->
        <div class="form-card">
            <div class="fch"><div class="fchi" style="background:#fdf8ec;color:#b8933a;"><i class="fas fa-user-group"></i></div><div><div class="fct">Next of Kin</div><div style="font-size:0.72rem;color:#9ca3af;">Family contact information</div></div></div>
            <div class="fcb"><div class="fg3">
                <div class="fg cs3"><label class="lbl">Relationship / Group <span class="opt">(optional)</span></label><input type="text" name="next_of_kin" class="inp" value="<?= htmlspecialchars($old['next_of_kin'] ?? '') ?>" placeholder="e.g. Spouse, Children, Family of Juan dela Cruz"></div>
                <div class="fg cs2"><label class="lbl">Contact Person Name <span class="opt">(optional)</span></label><input type="text" name="next_of_kin_name" class="inp" value="<?= htmlspecialchars($old['next_of_kin_name'] ?? '') ?>" placeholder="e.g. Maria dela Cruz"></div>
                <div class="fg"><label class="lbl">Contact Number <span class="opt">(optional)</span></label><input type="text" name="next_of_kin_contact" class="inp" value="<?= htmlspecialchars($old['next_of_kin_contact'] ?? '') ?>" placeholder="e.g. 09171234567"></div>
            </div></div>
        </div>

        <!-- Document -->
        <div class="form-card">
            <div class="fch"><div class="fchi" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-file-image"></i></div><div><div class="fct">Scanned Document</div><div style="font-size:0.72rem;color:#9ca3af;">Upload scanned funeral record</div></div></div>
            <div class="fcb">
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

        <!-- Remarks -->
        <div class="form-card">
            <div class="fch"><div class="fchi" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div><div><div class="fct">Remarks</div></div></div>
            <div class="fcb"><textarea name="remarks" class="txa" rows="3" placeholder="Additional notes…"><?= htmlspecialchars($old['remarks'] ?? '') ?></textarea></div>
        </div>

        <!-- Actions -->
        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:18px 22px;background:#faf7f0;border:1px solid #ede8de;border-radius:0 0 14px 14px;">
            <a href="/church/modules/church_records/funeral/series_records.php?year=<?= $year ?>" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn-save" id="submitBtn"><i class="fas fa-floppy-disk"></i> Save Record</button>
        </div>

    </form>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:20px;">
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1f2937,#4b5563);padding:16px 18px;">
                <p style="font-family:'Playfair Display',serif;font-size:0.95rem;color:#fff;font-weight:600;margin-bottom:2px;"><?= $year ?> Series</p>
                <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);">Funeral Records</p>
            </div>
            <div style="padding:14px 18px;">
                <div class="info-row"><span style="color:#9ca3af;font-size:0.8rem;">Records in series</span><span style="font-size:0.85rem;font-weight:600;color:#0f2044;"><?= $existing_count ?></span></div>
                <div class="info-row"><span style="color:#9ca3af;font-size:0.8rem;">Next record no.</span><span style="font-size:0.78rem;font-weight:600;color:#4b5563;font-family:monospace;"><?= $next_record_no ?></span></div>
                <div style="border-top:1px solid #f3ede3;margin-top:10px;padding-top:10px;">
                    <a href="/church/modules/church_records/funeral/series_records.php?year=<?= $year ?>" style="display:flex;align-items:center;gap:7px;font-size:0.78rem;color:#4b5563;text-decoration:none;">
                        <i class="fas fa-arrow-left" style="font-size:0.65rem;"></i> Back to <?= $year ?> series
                    </a>
                </div>
            </div>
        </div>
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.8rem;font-weight:600;color:#0f2044;margin-bottom:10px;display:flex;align-items:center;gap:7px;"><i class="fas fa-circle-info" style="color:#b8933a;"></i> Required Fields</p>
            <div style="display:flex;flex-direction:column;gap:7px;font-size:0.78rem;color:#374151;">
                <?php foreach (['Record Number','Date of Funeral','Deceased Full Name'] as $rf): ?>
                <div style="display:flex;align-items:center;gap:7px;"><i class="fas fa-check-circle" style="color:#4b5563;font-size:0.72rem;"></i><?= $rf ?></div>
                <?php endforeach; ?>
            </div>
        </div>
    </div>
    </div>
</div>

<script>
function previewFile(input) {
    if (input.files && input.files[0]) {
        document.getElementById('previewName').textContent = input.files[0].name;
        document.getElementById('previewSize').textContent = (input.files[0].size/1024).toFixed(1)+' KB';
        document.getElementById('uploadPreview').classList.add('show');
        document.getElementById('uploadIcon').style.color='#4b5563';
        document.getElementById('uploadTitle').textContent='File selected';
    }
}
function clearFile() {
    document.getElementById('documentFile').value='';
    document.getElementById('uploadPreview').classList.remove('show');
    document.getElementById('uploadIcon').style.color='';
    document.getElementById('uploadTitle').textContent='Click to upload or drag & drop';
}
const ua = document.getElementById('uploadArea');
ua.addEventListener('dragover', e=>{e.preventDefault();ua.style.borderColor='#4b5563';});
ua.addEventListener('dragleave', ()=>ua.style.borderColor='');
ua.addEventListener('drop', e=>{e.preventDefault();ua.style.borderColor='';const i=document.getElementById('documentFile');i.files=e.dataTransfer.files;previewFile(i);});
document.getElementById('addForm').addEventListener('submit',function(){
    const b=document.getElementById('submitBtn');b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';
});
</script>
<?php include $root . '/includes/footer.php'; ?>