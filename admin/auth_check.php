<?php
// admin/auth_check.php

/**
 * This script ensures that a session is active and checks if an admin is logged in.
 * If the admin is not logged in, it redirects them to the login page.
 * This file should be included at the top of every secure admin page.
 */

// Start the session ONLY if one has not already been started.
// This makes the script safe to include on any page without causing errors.
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Check if the 'admin_logged_in' session variable is set and is true.
// If not, redirect the user to the login page and stop the script.
if (!isset($_SESSION['admin_logged_in']) || $_SESSION['admin_logged_in'] !== true) {
    // Redirect to the admin login page (assuming it's 'index.php' in the same admin folder)
    header('Location: index.php');
    // Terminate the script to ensure no further code is executed.
    exit();
}

// If the script reaches this point, the admin is successfully authenticated.