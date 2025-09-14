<?php
// admin/auth_check.php
session_start();
require_once 'auth_check.php';
// Start the session. This is crucial for accessing session variables.
// It must be called before any HTML output.
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Check if the 'admin_logged_in' session variable is not set or is not true.
// If the admin is not logged in, redirect them to the login page.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to the admin login page
    header('Location: index.php'); // Assuming index.php is your login page in the admin folder
    exit(); // Terminate the script to prevent further execution
}

// If the script reaches this point, the admin is logged in, and the page can proceed.

// Optional: You can also include db_connect.php here if every protected
// page needs a database connection, but it's often better to include it
// on each page that specifically needs it for better modularity.
// require_once '../db_connect.php'; // Uncomment if needed universally
?>