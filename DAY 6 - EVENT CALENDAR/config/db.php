<?php

// ============================================================
//  Database Configuration
//  To deploy: just change the 4 values below
// ============================================================

define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'church');

// ============================================================
//  Connection — no need to touch anything below this line
// ============================================================

$conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

if ($conn->connect_error) {
    die("Database connection failed: " . $conn->connect_error);
}

$conn->set_charset("utf8mb4");