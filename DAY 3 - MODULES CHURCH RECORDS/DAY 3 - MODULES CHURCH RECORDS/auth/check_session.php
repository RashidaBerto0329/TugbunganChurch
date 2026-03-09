<?php
// church/auth/check_session.php
// ─────────────────────────────────────────────────────────────
// Include this at the TOP of every protected page (after session_start).
// It checks if the user is logged in and optionally enforces role access.
//
// BASIC USAGE (any logged-in user can access):
//   require_once '../../auth/check_session.php';
//
// ROLE-RESTRICTED USAGE (only specific roles allowed):
//   $allowed_roles = ['admin'];
//   require_once '../../auth/check_session.php';
//
//   $allowed_roles = ['admin', 'clergy'];
//   require_once '../../auth/check_session.php';
// ─────────────────────────────────────────────────────────────

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Not logged in → kick to login page
if (empty($_SESSION['user_id'])) {
    header('Location: /church/login.php');
    exit;
}

// Role check (only runs if $allowed_roles was set before this include)
if (!empty($allowed_roles)) {
    if (!in_array($_SESSION['user_role'], $allowed_roles, true)) {
        // Logged in but wrong role → show access denied page
        http_response_code(403);
        include __DIR__ . '/access_denied.php';
        exit;
    }
}

// Convenience variables available to every protected page after this include
$current_user_id   = $_SESSION['user_id'];
$current_user_name = $_SESSION['user_name'];
$current_user_role = $_SESSION['user_role'];