<?php
// church/modules/members/archive_member.php
// Soft-archive a member (admin/clergy only)
$root = $_SERVER['DOCUMENT_ROOT'] . '/church';
require_once $root . '/auth/check_session.php';
require_once $root . '/config/db.php';

if (!in_array($current_user_role, ['admin', 'clergy'])) {
    http_response_code(403); exit;
}

$id = (int)($_POST['id'] ?? 0);
if ($id <= 0) { header('Location: /church/modules/members/index.php'); exit; }

$stmt = $conn->prepare("SELECT name FROM members WHERE id = ? AND is_archived = 0");
$stmt->bind_param("i", $id);
$stmt->execute();
$row = $stmt->get_result()->fetch_assoc();
$stmt->close();

if (!$row) {
    $_SESSION['error'] = "Member not found.";
    header('Location: /church/modules/members/index.php');
    exit;
}

$stmt = $conn->prepare("UPDATE members SET is_archived = 1, updated_at = NOW() WHERE id = ?");
$stmt->bind_param("i", $id);
$stmt->execute();
$stmt->close();

$_SESSION['success'] = "Member \"{$row['name']}\" has been archived.";
header('Location: /church/modules/members/index.php');
exit;