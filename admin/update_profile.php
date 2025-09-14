<?php
// admin/update_profile.php
session_start(); // Must be at the very top

// Production Error Handling
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Consider setting a specific error log path if not using server default
// ini_set('error_log', '/path/to/your/php-error.log');
error_reporting(E_ALL);

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

// 1. Authentication Check (using your existing auth_check.php)
require_once 'auth_check.php'; // This should handle non-authenticated users

// 2. Check for Admin Identifier in Session
if (!isset($_SESSION['admin_id'])) { // <<< ENSURE THIS IS SET AT LOGIN
    $response['message'] = 'Authentication error. Please log in again.';
    http_response_code(401); // Unauthorized
    echo json_encode($response);
    exit;
}
$adminId = $_SESSION['admin_id'];

// 3. Check Request Method
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
    exit;
}

// 4. Get and Validate Inputs
$currentPasswordForm = $_POST['current_password'] ?? '';
$newEmailForm = isset($_POST['new_email']) ? trim($_POST['new_email']) : null;
$newPasswordForm = isset($_POST['new_password']) ? $_POST['new_password'] : null;

if (empty($currentPasswordForm)) {
    $response['message'] = 'Current password is required to make any changes.';
    echo json_encode($response);
    exit;
}

if (empty($newEmailForm) && empty($newPasswordForm)) {
    $response['message'] = 'Please provide a new email or a new password to update.';
    echo json_encode($response);
    exit;
}

// 5. Database Connection (using variables from db_connect.php)
require_once '../db_connect.php'; // Provides $servername, $username, $password, $dbname
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("update_profile.php - DB Connection Error: " . $conn->connect_error);
    $response['message'] = 'Database service is temporarily unavailable.';
    http_response_code(503); // Service Unavailable
    echo json_encode($response);
    exit;
}
$conn->set_charset("utf8mb4");

try {
    // 6. Fetch Current Admin Data
    // !!! ADJUST TABLE `admins` AND COLUMNS `id`, `password`, `email` AS NEEDED !!!
    $stmt = $conn->prepare("SELECT email, password FROM admins WHERE id = ?");
    if (!$stmt) throw new Exception("Prepare failed (fetch admin): " . $conn->error);
    $stmt->bind_param("i", $adminId);
    $stmt->execute();
    $result = $stmt->get_result();
    $admin = $result->fetch_assoc();
    $stmt->close();

    if (!$admin) {
        $response['message'] = 'Admin user not found.';
        http_response_code(404);
        // Optional: Log out user if their session ID is invalid
        // session_unset(); session_destroy();
        echo json_encode($response);
        exit;
    }

    // 7. Verify Current Password
    if (!password_verify($currentPasswordForm, $admin['password'])) {
        $response['message'] = 'Incorrect current password.';
        echo json_encode($response);
        exit;
    }

    $updateFields = [];
    $bindTypes = "";
    $bindValues = [];

    // 8. Process New Email (if provided)
    if ($newEmailForm !== null && $newEmailForm !== '') {
        if (!filter_var($newEmailForm, FILTER_VALIDATE_EMAIL)) {
            $response['message'] = 'Invalid new email format provided.';
            echo json_encode($response);
            exit;
        }
        if ($newEmailForm !== $admin['email']) {
            // Check if the new email is already used by another admin
            // !!! ADJUST TABLE `admins` AND COLUMNS `email`, `id` AS NEEDED !!!
            $stmtCheckEmail = $conn->prepare("SELECT id FROM admins WHERE email = ? AND id != ?");
            if (!$stmtCheckEmail) throw new Exception("Prepare failed (check email): " . $conn->error);
            $stmtCheckEmail->bind_param("si", $newEmailForm, $adminId);
            $stmtCheckEmail->execute();
            $stmtCheckEmail->store_result();
            if ($stmtCheckEmail->num_rows > 0) {
                $response['message'] = 'The new email address is already in use by another account.';
                $stmtCheckEmail->close();
                echo json_encode($response);
                exit;
            }
            $stmtCheckEmail->close();
            
            $updateFields[] = "email = ?";
            $bindTypes .= "s";
            $bindValues[] = $newEmailForm;
        }
    }

    // 9. Process New Password (if provided)
    if ($newPasswordForm !== null && $newPasswordForm !== '') {
        if (strlen($newPasswordForm) < 8) { // Basic length check
            $response['message'] = 'New password must be at least 8 characters long.';
            echo json_encode($response);
            exit;
        }
        $hashedNewPassword = password_hash($newPasswordForm, PASSWORD_DEFAULT);
        if ($hashedNewPassword === false) {
            throw new Exception("Password hashing failed.");
        }
        $updateFields[] = "password = ?"; // !!! ADJUST COLUMN `password` AS NEEDED !!!
        $bindTypes .= "s";
        $bindValues[] = $hashedNewPassword;
    }

    // 10. Execute Update if there are changes
    if (!empty($updateFields)) {
        $bindValues[] = $adminId; // Add adminId for the WHERE clause
        $bindTypes .= "i";

        // !!! ADJUST TABLE `admins` AND COLUMN `id` AS NEEDED !!!
        $sqlUpdate = "UPDATE admins SET " . implode(", ", $updateFields) . " WHERE id = ?";
        $stmtUpdate = $conn->prepare($sqlUpdate);
        if (!$stmtUpdate) throw new Exception("Prepare failed (update admin): " . $conn->error);
        
        $stmtUpdate->bind_param($bindTypes, ...$bindValues); // Spread operator for parameters

        if ($stmtUpdate->execute()) {
            if ($stmtUpdate->affected_rows > 0) {
                $response['success'] = true;
                $response['message'] = 'Profile updated successfully!';
                if (in_array("email = ?", $updateFields)) {
                    $_SESSION['admin_email'] = $newEmailForm; // Update session email
                    $response['new_email_updated'] = $newEmailForm; // Send back for UI update
                }
            } else {
                // This means the query ran but no rows were changed (e.g., submitted same email again)
                $response['success'] = true; // Still a success from user's perspective
                $response['message'] = 'No changes applied to the database (data might be the same).';
            }
        } else {
            throw new Exception("Execute failed (update admin): " . $stmtUpdate->error);
        }
        $stmtUpdate->close();
    } else {
        // No actual fields were different or valid for update
        $response['success'] = true; // Or false depending on how you want to treat "no changes"
        $response['message'] = 'No changes were made to your profile.';
    }

} catch (Exception $e) {
    error_log("update_profile.php - Exception: " . $e->getMessage());
    $response['message'] = 'A server error occurred. Please try again later.';
    http_response_code(500);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}

echo json_encode($response);
exit;
?>