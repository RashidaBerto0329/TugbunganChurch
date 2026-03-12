<?php
// church/auth/check_parishioner.php
// ─────────────────────────────────────────────────────────────
// Include this at the TOP of every Parishioner Portal page.
// It ensures the visitor is logged in AND has the 'parishioner' role.
//
// USAGE (portal pages that require login):
//   require_once '../../auth/check_parishioner.php';
//
// For portal pages that are PUBLIC (no login required, e.g. book_baptism.php
// which also accepts guest submissions), do NOT include this file.
// Use it only for pages like my_bookings.php that require an account.
// ─────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in → send to login page with a return hint
if (empty($_SESSION['user_id'])) {
    header('Location: /church/login.php');
    exit;
}

// Logged in but not a parishioner (e.g. staff accidentally hits portal URL)
// → redirect staff to their dashboard instead of showing access denied
if ($_SESSION['user_role'] !== 'parishioner') {
    header('Location: /church/dashboard.php');
    exit;
}

// Convenience variables available to every portal page after this include
$current_user_id   = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'];
$current_user_role = $_SESSION['user_role'];