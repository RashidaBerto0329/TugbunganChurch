<?php
// church/modules/church_records/funeral/edit_record.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) { include $root . '/auth/access_denied.php'; exit; }

$id = (int)($_GET['id'] ?? 0);
if ($id <= 0) { header('Location: /church/modules/church_records/funeral/series_list.php'); exit; }

$stmt = $conn->prepare("SELECT * FROM funeral_records WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id); $stmt->execute();
$rec = $stmt->get_result()->fetch_assoc(); $stmt->close();
if (!$rec) { $_SESSION['error']="Record not found."; header('Location: /church/modules/church_records/funeral/series_list.php'); exit; }

$year = (int)$rec['series_year'];
$errors = []; $old = $rec;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $old = $_POST;
    $deceased_name       = trim($_POST['deceased_name']       ?? '');
    $record_no           = trim($_POST['record_no']           ?? '');
    $date_of_death       = trim($_POST['date_of_death']       ?? '') ?: null;
    $date_of_birth       = trim($_POST['date_of_birth']       ?? '') ?: null;
    $date_of_funeral     = trim($_POST['date_of_funeral']     ?? '');
    $address             = trim($_POST['address']             ?? '');
    $place_of_burial     = trim($_POST['place_of_burial']     ?? '');
    $next_of_kin         = trim($_POST['next_of_kin']         ?? '');
    $next_of_kin_name    = trim($_POST['next_of_kin_name']    ?? '');
    $next_of_kin_contact = trim($_POST['next_of_kin_contact'] ?? '');
    $minister            = trim($_POST['minister']            ?? '');
    $remarks             = trim($_POST['remarks']             ?? '');
    $remove_document     = isset($_POST['remove_document']);

    if ($deceased_name === '')   $errors[] = "Deceased name is required.";
    if ($record_no === '')       $errors[] = "Record number is required.";
    if ($date_of_funeral === '') $errors[] = "Date of funeral is required.";

    if ($record_no !== '') {
        $dup = $conn->prepare("SELECT id FROM funeral_records WHERE record_no=? AND series_year=? AND is_archived=0 AND id!=?");
        $dup->bind_param("sii",$record_no,$year,$id); $dup->execute(); $dup->store_result();
        if ($dup->num_rows > 0) $errors[] = "Record number \"{$record_no}\" already exists in {$year}.";
        $dup->close();
    }

    $document_path = $rec['document_path'];
    if ($remove_document) {
        if (!empty($rec['document_path'])) { $fp=$_SERVER['DOCUMENT_ROOT'].$rec['document_path']; if(file_exists($fp)) @unlink($fp); }
        $document_path = null;
    }
    if (!empty($_FILES['document']['name'])) {
        $file = $_FILES['document'];
        $allowed = ['image/jpeg','image/png','image/webp','application/pdf'];
        if (!in_array($file['type'],$allowed))      $errors[]="Invalid file type.";
        elseif ($file['size']>5*1024*1024)          $errors[]="File too large.";
        elseif ($file['error']!==UPLOAD_ERR_OK)     $errors[]="Upload failed.";
        else {
            if (!empty($rec['document_path'])) { $op=$_SERVER['DOCUMENT_ROOT'].$rec['document_path']; if(file_exists($op)) @unlink($op); }
            $dir=$_SERVER['DOCUMENT_ROOT'].'/church/uploads/funeral/';
            if(!is_dir($dir)) mkdir($dir,0755,true);
            $ext=pathinfo($file['name'],PATHINFO_EXTENSION);
            $fname='funeral_'.$year.'_'.preg_replace('/[^a-z0-9]/i','_',$record_no).'_'.time().'.'.$ext;
            if(move_uploaded_file($file['tmp_name'],$dir.$fname)) $document_path='/church/uploads/funeral/'.$fname;
            else $errors[]="Failed to save file.";
        }
    }

    if (empty($errors)) {
        $upd = $conn->prepare("
            UPDATE funeral_records SET
                record_no=?, deceased_name=?,
                date_of_birth=?, date_of_death=?, date_of_funeral=?,
                address=?, place_of_burial=?,
                next_of_kin=?, next_of_kin_name=?, next_of_kin_contact=?,
                minister=?, remarks=?,
                document_path=?, updated_by=?, updated_at=NOW()
            WHERE id=?
        ");
        // s s s s s s s s s s s s s i i = 13s + 2i
        $upd->bind_param("sssssssssssssii",
            $record_no, $deceased_name,
            $date_of_birth, $date_of_death, $date_of_funeral,
            $address, $place_of_burial,
            $next_of_kin, $next_of_kin_name, $next_of_kin_contact,
            $minister, $remarks,
            $document_path, $current_user_id, $id
        );
        if ($upd->execute()) {
            $upd->close();
            $_SESSION['success']="Funeral record updated successfully.";
            header("Location: /church/modules/church_records/funeral/view_record.php?id={$id}"); exit;
        } else { $errors[]="DB error: ".$conn->error; $upd->close(); }
    }
}

function ov($old, $rec, $key) { return htmlspecialchars($old[$key] ?? $rec[$key] ?? ''); }

$page_title = "Edit — " . htmlspecialchars($rec['deceased_name']);
include $root . '/includes/header.php';
?>
<style>
    .fc{background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;margin-bottom:18px;}
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
    .inp.changed,.txa.changed{border-color:#f59e0b;box-shadow:0 0 0 3px rgba(245,158,11,0.1);}
    .txa{resize:vertical;min-height:70px;}
    .existing-doc{display:flex;align-items:center;gap:10px;padding:11px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-bottom:10px;font-size:0.82rem;}
    .upload-area{border:2px dashed #d4c9b5;border-radius:10px;padding:22px 20px;text-align:center;cursor:pointer;transition:all 0.2s;background:#faf7f0;position:relative;}
    .upload-area:hover{border-color:#4b5563;background:#f3f4f6;}
    .upload-area input[type="file"]{position:absolute;inset:0;opacity:0;cursor:pointer;width:100%;height:100%;}
    .upload-preview{display:none;align-items:center;gap:10px;padding:10px 14px;background:#f0fdf4;border:1px solid #bbf7d0;border-radius:8px;margin-top:10px;}
    .upload-preview.show{display:flex;}
    .error-list{background:#fef2f2;border:1px solid #fecaca;border-radius:10px;padding:14px 18px;margin-bottom:20px;}
    .error-list ul{margin:6px 0 0 16px;padding:0;}
    .error-list li{font-size:0.82rem;color:#dc2626;margin-bottom:3px;}
    .btn-save{display:inline-flex;align-items:center;gap:7px;padding:10px 24px;border-radius:8px;font-size:0.85rem;font-weight:600;background:#4b5563;color:#fff;border:none;cursor:pointer;} .btn-save:hover{background:#374151;}
    .btn-cancel{display:inline-flex;align-items:center;gap:6px;padding:10px 18px;border-radius:8px;font-size:0.85rem;font-weight:500;border:1px solid #ede8de;background:#fff;color:#6b7280;text-decoration:none;}
    .ir{display:flex;justify-content:space-between;align-items:center;padding:8px 0;border-bottom:1px solid #f5f0e8;font-size:0.8rem;}
    .ir:last-child{border-bottom:none;}
    @media(max-width:900px){#editLayout{grid-template-columns:1fr!important;}}
    @media(max-width:768px){.fg2,.fg3{grid-template-columns:1fr;}.cs2,.cs3{grid-column:span 1;}}
</style>

<div class="page-header" style="display:flex;align-items:flex-start;justify-content:space-between;gap:16px;flex-wrap:wrap;">
    <div>
        <h1><i class="fas fa-pen" style="color:#6b7280;margin-right:8px;font-size:1rem;"></i>Edit Funeral Record</h1>
        <p class="breadcrumb">
            <a href="/church/dashboard.php" style="color:#9ca3af;text-decoration:none;">Dashboard</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/index.php" style="color:#9ca3af;text-decoration:none;">Church Records</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/funeral/series_list.php" style="color:#9ca3af;text-decoration:none;">Funeral</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/funeral/series_records.php?year=<?= $year ?>" style="color:#9ca3af;text-decoration:none;"><?= $year ?> Series</a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <a href="/church/modules/church_records/funeral/view_record.php?id=<?= $id ?>" style="color:#9ca3af;text-decoration:none;"><?= htmlspecialchars($rec['deceased_name']) ?></a>
            <span style="margin:0 6px;color:#d1c9b8;">›</span>
            <span class="current">Edit</span>
        </p>
    </div>
    <a href="/church/modules/church_records/funeral/view_record.php?id=<?= $id ?>" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
</div>

<div style="padding:24px 24px 60px;">
    <?php if (!empty($errors)): ?>
    <div class="error-list"><div style="display:flex;align-items:center;gap:8px;"><i class="fas fa-circle-exclamation" style="color:#dc2626;"></i><strong style="font-size:0.85rem;color:#dc2626;">Please fix:</strong></div>
    <ul><?php foreach($errors as $e): ?><li><?= htmlspecialchars($e) ?></li><?php endforeach; ?></ul></div>
    <?php endif; ?>

    <div style="display:grid;grid-template-columns:1fr 280px;gap:20px;align-items:start;" id="editLayout">
    <div>
    <form method="POST" enctype="multipart/form-data" id="editForm">

        <!-- Record Info -->
        <div class="fc"><div class="fch"><div class="fchi" style="background:#f3f4f6;color:#4b5563;"><i class="fas fa-hashtag"></i></div><div><div class="fct">Record Information</div></div></div>
        <div class="fcb"><div class="fg3">
            <div class="fg"><label class="lbl">Series Year</label><input type="text" class="inp" value="<?= $year ?>" readonly style="background:#f9fafb;color:#6b7280;cursor:not-allowed;"></div>
            <div class="fg"><label class="lbl">Record Number <span class="req">*</span></label><input type="text" name="record_no" class="inp" value="<?= ov($old,$rec,'record_no') ?>" required data-original="<?= htmlspecialchars($rec['record_no']) ?>"></div>
            <div class="fg"><label class="lbl">Date of Funeral <span class="req">*</span></label><input type="date" name="date_of_funeral" class="inp" value="<?= ov($old,$rec,'date_of_funeral') ?>" max="<?= date('Y-m-d') ?>" required data-original="<?= htmlspecialchars($rec['date_of_funeral'] ?? '') ?>"></div>
        </div></div></div>

        <!-- Deceased -->
        <div class="fc"><div class="fch"><div class="fchi" style="background:#f3f4f6;color:#374151;"><i class="fas fa-cross"></i></div><div><div class="fct">Deceased Information</div></div></div>
        <div class="fcb"><div class="fg3">
            <div class="fg cs3"><label class="lbl">Deceased Full Name <span class="req">*</span></label><input type="text" name="deceased_name" class="inp" value="<?= ov($old,$rec,'deceased_name') ?>" required data-original="<?= htmlspecialchars($rec['deceased_name']) ?>"></div>
            <div class="fg"><label class="lbl">Date of Birth <span class="opt">(optional)</span></label><input type="date" name="date_of_birth" class="inp" value="<?= ov($old,$rec,'date_of_birth') ?>" data-original="<?= htmlspecialchars($rec['date_of_birth'] ?? '') ?>"></div>
            <div class="fg"><label class="lbl">Date of Death <span class="opt">(optional)</span></label><input type="date" name="date_of_death" class="inp" value="<?= ov($old,$rec,'date_of_death') ?>" data-original="<?= htmlspecialchars($rec['date_of_death'] ?? '') ?>"></div>
            <div class="fg"><label class="lbl">Officiating Minister <span class="opt">(optional)</span></label><input type="text" name="minister" class="inp" value="<?= ov($old,$rec,'minister') ?>" data-original="<?= htmlspecialchars($rec['minister'] ?? '') ?>"></div>
            <div class="fg cs2"><label class="lbl">Address <span class="opt">(optional)</span></label><input type="text" name="address" class="inp" value="<?= ov($old,$rec,'address') ?>" data-original="<?= htmlspecialchars($rec['address'] ?? '') ?>"></div>
            <div class="fg cs3"><label class="lbl">Place of Burial <span class="opt">(optional)</span></label><input type="text" name="place_of_burial" class="inp" value="<?= ov($old,$rec,'place_of_burial') ?>" data-original="<?= htmlspecialchars($rec['place_of_burial'] ?? '') ?>"></div>
        </div></div></div>

        <!-- Next of Kin -->
        <div class="fc"><div class="fch"><div class="fchi" style="background:#fdf8ec;color:#b8933a;"><i class="fas fa-user-group"></i></div><div><div class="fct">Next of Kin</div></div></div>
        <div class="fcb"><div class="fg3">
            <div class="fg cs3"><label class="lbl">Relationship / Group</label><input type="text" name="next_of_kin" class="inp" value="<?= ov($old,$rec,'next_of_kin') ?>" data-original="<?= htmlspecialchars($rec['next_of_kin'] ?? '') ?>"></div>
            <div class="fg cs2"><label class="lbl">Contact Person Name</label><input type="text" name="next_of_kin_name" class="inp" value="<?= ov($old,$rec,'next_of_kin_name') ?>" data-original="<?= htmlspecialchars($rec['next_of_kin_name'] ?? '') ?>"></div>
            <div class="fg"><label class="lbl">Contact Number</label><input type="text" name="next_of_kin_contact" class="inp" value="<?= ov($old,$rec,'next_of_kin_contact') ?>" data-original="<?= htmlspecialchars($rec['next_of_kin_contact'] ?? '') ?>"></div>
        </div></div></div>

        <!-- Document -->
        <div class="fc"><div class="fch"><div class="fchi" style="background:#f0fdf4;color:#16a34a;"><i class="fas fa-file-image"></i></div><div><div class="fct">Scanned Document</div></div></div>
        <div class="fcb">
            <?php if (!empty($rec['document_path'])): ?>
            <div class="existing-doc">
                <i class="fas fa-file-check" style="color:#16a34a;flex-shrink:0;"></i>
                <span style="flex:1;color:#15803d;font-weight:500;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;"><?= basename($rec['document_path']) ?></span>
                <a href="<?= htmlspecialchars($rec['document_path']) ?>" target="_blank" style="font-size:0.73rem;color:#16a34a;text-decoration:none;">View</a>
                <label style="display:inline-flex;align-items:center;gap:5px;font-size:0.73rem;color:#dc2626;cursor:pointer;white-space:nowrap;">
                    <input type="checkbox" name="remove_document" onchange="toggleRemoveDoc(this)"> Remove
                </label>
            </div>
            <p style="font-size:0.73rem;color:#9ca3af;margin-bottom:10px;">Upload below to replace, or check "Remove" to delete.</p>
            <?php endif; ?>
            <div class="upload-area" id="uploadArea">
                <input type="file" name="document" id="documentFile" accept=".jpg,.jpeg,.png,.webp,.pdf" onchange="previewFile(this)">
                <i class="fas fa-cloud-arrow-up" style="font-size:1.5rem;color:#c4b89a;display:block;margin-bottom:7px;" id="uploadIcon"></i>
                <div style="font-size:0.8rem;font-weight:600;color:#374151;margin-bottom:2px;" id="uploadTitle"><?= !empty($rec['document_path']) ? 'Upload replacement' : 'Click to upload or drag & drop' ?></div>
                <div style="font-size:0.72rem;color:#9ca3af;">JPG, PNG, WEBP or PDF — max 5MB</div>
            </div>
            <div class="upload-preview" id="uploadPreview">
                <i class="fas fa-file-check" style="color:#16a34a;font-size:1rem;flex-shrink:0;"></i>
                <span style="font-size:0.82rem;font-weight:500;color:#15803d;flex:1;" id="previewName"></span>
                <span style="font-size:0.72rem;color:#9ca3af;" id="previewSize"></span>
                <button type="button" style="background:none;border:none;color:#dc2626;cursor:pointer;" onclick="clearFile()"><i class="fas fa-times"></i></button>
            </div>
        </div></div>

        <!-- Remarks -->
        <div class="fc"><div class="fch"><div class="fchi" style="background:#f9fafb;color:#6b7280;"><i class="fas fa-note-sticky"></i></div><div><div class="fct">Remarks</div></div></div>
        <div class="fcb"><textarea name="remarks" class="txa" rows="3" data-original="<?= htmlspecialchars($rec['remarks'] ?? '') ?>"><?= ov($old,$rec,'remarks') ?></textarea></div></div>

        <div style="display:flex;align-items:center;justify-content:flex-end;gap:10px;padding:18px 22px;background:#faf7f0;border:1px solid #ede8de;border-radius:0 0 14px 14px;">
            <a href="/church/modules/church_records/funeral/view_record.php?id=<?= $id ?>" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
            <button type="submit" class="btn-save" id="submitBtn"><i class="fas fa-floppy-disk"></i> Save Changes</button>
        </div>
    </form>
    </div>

    <!-- Sidebar -->
    <div style="display:flex;flex-direction:column;gap:16px;position:sticky;top:20px;">
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;overflow:hidden;">
            <div style="background:linear-gradient(135deg,#1f2937,#4b5563);padding:16px 18px;">
                <p style="font-family:'Playfair Display',serif;font-size:0.92rem;color:#fff;font-weight:600;margin-bottom:2px;">Editing Record</p>
                <p style="font-size:0.72rem;color:rgba(255,255,255,0.45);"><?= $year ?> Funeral Series</p>
            </div>
            <div style="padding:14px 18px;">
                <div class="ir"><span style="color:#9ca3af;font-size:0.78rem;">Record No.</span><span style="font-family:monospace;color:#4b5563;font-weight:600;font-size:0.8rem;"><?= htmlspecialchars($rec['record_no']) ?></span></div>
                <div class="ir"><span style="color:#9ca3af;font-size:0.78rem;">Deceased</span><span style="font-weight:500;color:#0f2044;font-size:0.78rem;"><?= htmlspecialchars($rec['deceased_name']) ?></span></div>
            </div>
        </div>
        <div style="background:#fff;border:1px solid #ede8de;border-radius:14px;padding:16px 18px;">
            <p style="font-size:0.72rem;font-weight:700;letter-spacing:0.07em;text-transform:uppercase;color:#c4b89a;margin-bottom:10px;">Unsaved Changes</p>
            <div id="changeList" style="font-size:0.78rem;color:#9ca3af;font-style:italic;">No changes yet</div>
        </div>
        <a href="/church/modules/church_records/funeral/view_record.php?id=<?= $id ?>"
           style="display:flex;align-items:center;gap:8px;padding:12px 16px;background:#fff;border:1px solid #ede8de;border-radius:10px;text-decoration:none;font-size:0.8rem;color:#6b7280;transition:all 0.15s;"
           onmouseover="this.style.borderColor='#4b5563';this.style.color='#4b5563'"
           onmouseout="this.style.borderColor='#ede8de';this.style.color='#6b7280'">
            <i class="fas fa-eye" style="font-size:0.7rem;color:#6b7280;"></i> View Original Record
        </a>
    </div>
    </div>
</div>

<script>
const fieldLabels = {record_no:"Record No.",deceased_name:"Deceased Name",date_of_funeral:"Funeral Date",date_of_birth:"Date of Birth",date_of_death:"Date of Death",minister:"Minister",address:"Address",place_of_burial:"Place of Burial",next_of_kin:"Relationship",next_of_kin_name:"Contact Person",next_of_kin_contact:"Contact Number",remarks:"Remarks"};
const tracked = document.querySelectorAll('[data-original]');
const cl = document.getElementById('changeList');
function updateChanges(){
    const changed=[];
    tracked.forEach(el=>{ if((el.value||'').trim()!==(el.getAttribute('data-original')||'').trim()) changed.push(fieldLabels[el.name]||el.name); });
    if(!changed.length){cl.textContent='No changes yet';cl.style.fontStyle='italic';cl.style.color='#9ca3af';}
    else{cl.style.fontStyle='normal';cl.style.color='#374151';cl.innerHTML=changed.map(f=>`<div style="display:flex;align-items:center;gap:6px;margin-bottom:5px;"><i class="fas fa-circle" style="color:#f59e0b;font-size:0.4rem;"></i><span>${f}</span></div>`).join('');}
}
tracked.forEach(el=>{el.addEventListener('input',updateChanges);el.addEventListener('change',updateChanges);});
tracked.forEach(el=>{el.addEventListener('input',function(){if((this.value||'').trim()!==(this.getAttribute('data-original')||'').trim()) this.classList.add('changed'); else this.classList.remove('changed');});});
function previewFile(input){const p=document.getElementById('uploadPreview');if(input.files&&input.files[0]){document.getElementById('previewName').textContent=input.files[0].name;document.getElementById('previewSize').textContent=(input.files[0].size/1024).toFixed(1)+' KB';p.classList.add('show');document.getElementById('uploadIcon').style.color='#4b5563';document.getElementById('uploadTitle').textContent='File selected';}}
function clearFile(){document.getElementById('documentFile').value='';document.getElementById('uploadPreview').classList.remove('show');document.getElementById('uploadIcon').style.color='';document.getElementById('uploadTitle').textContent=document.getElementById('removeDocCheck')?'Upload replacement':'Click to upload or drag & drop';}
function toggleRemoveDoc(cb){const a=document.getElementById('uploadArea');a.style.opacity=cb.checked?'0.4':'';a.style.pointerEvents=cb.checked?'none':'';}
const ua=document.getElementById('uploadArea');
ua.addEventListener('dragover',e=>{e.preventDefault();ua.style.borderColor='#4b5563';});
ua.addEventListener('dragleave',()=>ua.style.borderColor='');
ua.addEventListener('drop',e=>{e.preventDefault();ua.style.borderColor='';const i=document.getElementById('documentFile');i.files=e.dataTransfer.files;previewFile(i);});
let formDirty=false;
tracked.forEach(el=>el.addEventListener('input',()=>formDirty=true));
window.addEventListener('beforeunload',e=>{if(formDirty){e.preventDefault();e.returnValue='';}});
document.getElementById('editForm').addEventListener('submit',function(){formDirty=false;const b=document.getElementById('submitBtn');b.disabled=true;b.innerHTML='<i class="fas fa-spinner fa-spin"></i> Saving…';});
</script>
<?php include $root . '/includes/footer.php'; ?>