<?php
// church/modules/archive/archive_record.php
// POST handler — archive OR unarchive a single record.
//
// Expected POST fields:
//   action       : 'archive' | 'unarchive'
//   ref_type     : 'baptism' | 'confirmation' | 'wedding' | 'funeral' | 'member' | 'volunteer'
//   ref_id       : int
//   redirect_url : URL to return to after action (optional)

$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Auth ─────────────────────────────────────────────────────
if (!in_array($current_user_role, ['admin', 'clergy'])) {
    $_SESSION['error'] = "You do not have permission to archive records.";
    header('Location: ' . ($_POST['redirect_url'] ?? '/church/dashboard.php'));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /church/modules/archive/index.php');
    exit;
}

// ── Inputs ───────────────────────────────────────────────────
$action       = $_POST['action']       ?? '';
$ref_type     = $_POST['ref_type']     ?? '';
$ref_id       = (int)($_POST['ref_id'] ?? 0);
$redirect_url = $_POST['redirect_url'] ?? '/church/modules/archive/index.php';

$allowed_types = ['baptism','confirmation','wedding','funeral','member','volunteer'];
$table_map = [
    'baptism'      => 'baptism_records',
    'confirmation' => 'confirmation_records',
    'wedding'      => 'wedding_records',
    'funeral'      => 'funeral_records',
    'member'       => 'members',
    'volunteer'    => 'volunteers',
];

// ── Validate ─────────────────────────────────────────────────
if (!in_array($action, ['archive','unarchive'])
    || !in_array($ref_type, $allowed_types)
    || $ref_id <= 0
    || !isset($table_map[$ref_type])) {
    $_SESSION['error'] = "Invalid archive request.";
    header("Location: $redirect_url");
    exit;
}

$table = $table_map[$ref_type];

// ── Verify record exists ─────────────────────────────────────
$check = $conn->prepare("SELECT id, is_archived FROM `{$table}` WHERE id = ?");
$check->bind_param("i", $ref_id);
$check->execute();
$record = $check->get_result()->fetch_assoc();
$check->close();

if (!$record) {
    $_SESSION['error'] = "Record not found.";
    header("Location: $redirect_url");
    exit;
}

// ── Perform action ───────────────────────────────────────────
if ($action === 'archive') {

    if ($record['is_archived']) {
        $_SESSION['error'] = "This record is already archived.";
        header("Location: $redirect_url");
        exit;
    }

    // Mark as archived
    $u = $conn->prepare("UPDATE `{$table}` SET is_archived = 1 WHERE id = ?");
    $u->bind_param("i", $ref_id);
    $u->execute();
    $u->close();

    // Log in archives table (insert only if not already logged)
    $exists = $conn->prepare("SELECT id FROM archives WHERE reference_type = ? AND reference_id = ?");
    $exists->bind_param("si", $ref_type, $ref_id);
    $exists->execute();
    $already = $exists->get_result()->fetch_assoc();
    $exists->close();

    if (!$already) {
        $ins = $conn->prepare("INSERT INTO archives (reference_type, reference_id, archived_by) VALUES (?, ?, ?)");
        $ins->bind_param("sii", $ref_type, $ref_id, $current_user_id);
        $ins->execute();
        $ins->close();
    }

    $_SESSION['success'] = "Record has been archived successfully. You can restore it anytime from the Archive module.";

} elseif ($action === 'unarchive') {

    if (!$record['is_archived']) {
        $_SESSION['error'] = "This record is not currently archived.";
        header("Location: $redirect_url");
        exit;
    }

    // Restore
    $u = $conn->prepare("UPDATE `{$table}` SET is_archived = 0 WHERE id = ?");
    $u->bind_param("i", $ref_id);
    $u->execute();
    $u->close();

    // Remove from archives log
    $d = $conn->prepare("DELETE FROM archives WHERE reference_type = ? AND reference_id = ?");
    $d->bind_param("si", $ref_type, $ref_id);
    $d->execute();
    $d->close();

    $_SESSION['success'] = "Record has been restored from the archive.";
}

header("Location: $redirect_url");
exit;