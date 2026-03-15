<?php
// church/modules/church_records/confirmation/add_series.php
// Phase 4 — Step 4.9: Handle new year series form submission for Confirmation
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

// ── Access control ───────────────────────────────────────────
if (!in_array($current_user_role, ['admin', 'clergy'])) {
    $_SESSION['error'] = 'You do not have permission to create a new series.';
    header('Location: /church/modules/church_records/confirmation/series_list.php');
    exit;
}

// ── Only accept POST ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /church/modules/church_records/confirmation/series_list.php');
    exit;
}

$series_year  = (int)trim($_POST['series_year'] ?? 0);
$notes        = trim($_POST['notes'] ?? '');
$current_year = (int)date('Y');

// ── Validate ─────────────────────────────────────────────────
if ($series_year < 1900 || $series_year > $current_year + 1) {
    $_SESSION['error'] = "Invalid year. Please enter a year between 1900 and " . ($current_year + 1) . ".";
    header('Location: /church/modules/church_records/confirmation/series_list.php');
    exit;
}

// ── Check if series already exists ───────────────────────────
$check = $conn->prepare("SELECT id FROM confirmation_series WHERE series_year = ?");
$check->bind_param("i", $series_year);
$check->execute();
$check->store_result();

if ($check->num_rows > 0) {
    $_SESSION['error'] = "A series for {$series_year} already exists.";
    header('Location: /church/modules/church_records/confirmation/series_list.php');
    exit;
}
$check->close();

// ── Insert new series ─────────────────────────────────────────
$stmt = $conn->prepare("
    INSERT INTO confirmation_series (series_year, notes, created_by, created_at)
    VALUES (?, ?, ?, NOW())
");
$stmt->bind_param("isi", $series_year, $notes, $_SESSION['user_id']);

if ($stmt->execute()) {
    $_SESSION['success'] = "Confirmation series for {$series_year} created successfully.";
    header("Location: /church/modules/church_records/confirmation/series_records.php?year={$series_year}");
} else {
    $_SESSION['error'] = "Failed to create series. Please try again.";
    header('Location: /church/modules/church_records/confirmation/series_list.php');
}

$stmt->close();
exit;