<?php
// find_path.php
// Place this file in the SAME directory as all-bookings.php (the 'admin' folder) and run it.

ini_set('display_errors', 1);
error_reporting(E_ALL);

echo "<h1>Path Diagnostic Tool</h1>";
echo "<p>This tool will help us find the correct file paths on your server.</p>";

$admin_dir = __DIR__;
echo "<p>Your admin directory is located at: <strong>" . htmlspecialchars($admin_dir) . "</strong></p>";
echo "<hr>";

echo "<h2>1. Checking for PHPMailer Location</h2>";
echo "<p>We need to find the 'vendor' folder. Let's test the most common locations:</p>";

// Test Path 1: ../../../vendor/
$path1 = $admin_dir . '/../../../vendor/PHPMailer/PHPMailer/PHPMailer.php';
echo "<p><strong>Test 1:</strong> Checking <code>" . htmlspecialchars($path1) . "</code><br>";
if (file_exists($path1)) {
    echo "<strong><span style='color:green; font-size: 1.2em;'>SUCCESS!</span> This is the correct path.</strong></p>";
} else {
    echo "<span style='color:red;'>FAILED. File not found.</span></p>";
}

// Test Path 2: ../../vendor/
$path2 = $admin_dir . '/../../vendor/PHPMailer/PHPMailer/PHPMailer.php';
echo "<p><strong>Test 2:</strong> Checking <code>" . htmlspecialchars($path2) . "</code><br>";
if (file_exists($path2)) {
    echo "<strong><span style='color:green; font-size: 1.2em;'>SUCCESS!</span> This is the correct path.</strong></p>";
} else {
    echo "<span style='color:red;'>FAILED. File not found.</span></p>";
}

// Test Path 3: ../vendor/
$path3 = $admin_dir . '/../vendor/PHPMailer/PHPMailer/PHPMailer.php';
echo "<p><strong>Test 3:</strong> Checking <code>" . htmlspecialchars($path3) . "</code><br>";
if (file_exists($path3)) {
    echo "<strong><span style='color:green; font-size: 1.2em;'>SUCCESS!</span> This is the correct path.</strong></p>";
} else {
    echo "<span style='color:red;'>FAILED. File not found.</span></p>";
}
echo "<hr>";

echo "<h2>2. Checking for db_connect.php Location</h2>";
$db_path = $admin_dir . '/../db_connect.php';
echo "<p>Checking for db_connect.php at: <code>" . htmlspecialchars($db_path) . "</code><br>";
if (file_exists($db_path)) {
    echo "<strong><span style='color:green;'>SUCCESS!</span> db_connect.php was found.</strong></p>";
} else {
    echo "<span style='color:red;'>FAILED. db_connect.php was NOT found at this path.</span> This will cause an error.</p>";
}

echo "<hr>";
echo "<p>Please copy all the text on this page and send it back to me.</p>";

?>