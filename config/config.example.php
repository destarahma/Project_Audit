<?php
// General configuration
date_default_timezone_set('Asia/Jakarta');

// BASE_URL - Update this based on your environment
// For production, change to your actual domain
define('BASE_URL', 'http://localhost/Project_Audit/');
define('SITE_NAME', 'Self Audit System');

// Session configuration
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Auto include database
require_once __DIR__ . '/database.php';
?>
