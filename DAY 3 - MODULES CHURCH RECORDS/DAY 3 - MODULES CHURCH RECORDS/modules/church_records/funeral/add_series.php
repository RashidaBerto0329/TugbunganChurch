<?php
// church/modules/church_records/funeral/add_series.php
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    header('Location: /church/modules/church_records/funeral/series_list.php');
    exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /church/modules/church_records/funeral/series_list.php');
    exit;
}

$year  = (int)($_POST['series_year'] ?? 0);
$notes = trim($_POST['notes'] ?? '');

if ($year < 1900 || $year > (int)date('Y') + 1) {
    $_SESSION['error'] = "Invalid year.";
    header('Location: /church/modules/church_records/funeral/series_list.php');
    exit;
}

// Check duplicate
$dup = $conn->prepare("SELECT id FROM funeral_series WHERE series_year = ?");
$dup->bind_param("i", $year);
$dup->execute();
$dup->store_result();
if ($dup->num_rows > 0) {
    $dup->close();
    $_SESSION['error'] = "A series for {$year} already exists.";
    header('Location: /church/modules/church_records/funeral/series_list.php');
    exit;
}
$dup->close();

// Insert into funeral_series
$ins = $conn->prepare("INSERT INTO funeral_series (series_year, notes, created_by) VALUES (?, ?, ?)");
$ins->bind_param("isi", $year, $notes, $current_user_id);
$ins->execute();
$ins->close();

// Sync to central record_series registry
$rs_check = $conn->prepare("SELECT id FROM record_series WHERE type = 'funeral' AND year = ?");
$rs_check->bind_param("i", $year);
$rs_check->execute();
$rs_check->store_result();
if ($rs_check->num_rows === 0) {
    $rs_ins = $conn->prepare("INSERT INTO record_series (type, year, created_by) VALUES ('funeral', ?, ?)");
    $rs_ins->bind_param("ii", $year, $current_user_id);
    $rs_ins->execute();
    $rs_ins->close();
}
$rs_check->close();

$_SESSION['success'] = "Funeral series for {$year} created successfully.";
header("Location: /church/modules/church_records/funeral/series_records.php?year={$year}");
exit;