<?php
// church/modules/finance/delete_finance.php
// Shared DELETE handler for donations, collections, payments
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy', 'finance'])) {
    header('Location: /church/modules/finance/index.php'); exit;
}
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: /church/modules/finance/index.php'); exit;
}

$allowed_tables = ['donations', 'collections', 'payments'];
$table    = $_POST['table']    ?? '';
$id       = (int)($_POST['id'] ?? 0);
$redirect = $_POST['redirect'] ?? '/church/modules/finance/index.php';

// Validate redirect is internal
if (!str_starts_with($redirect, '/church/')) {
    $redirect = '/church/modules/finance/index.php';
}

if (!in_array($table, $allowed_tables) || $id <= 0) {
    $_SESSION['error'] = "Invalid delete request.";
    header("Location: {$redirect}"); exit;
}

$stmt = $conn->prepare("DELETE FROM `{$table}` WHERE id = ?");
$stmt->bind_param("i", $id);
if ($stmt->execute()) {
    $_SESSION['success'] = ucfirst($table === 'donations' ? 'Donation' : ($table === 'collections' ? 'Collection' : 'Payment')) . " deleted successfully.";
} else {
    $_SESSION['error'] = "Failed to delete record.";
}
$stmt->close();
header("Location: {$redirect}"); exit;