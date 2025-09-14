<?php
// admin/set_password.php
session_start(); // Optional, but good practice
require_once '../db_connect.php'; // Database connection

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$token_from_url = '';
$display_form = false;
$error_message = '';
$success_message = '';
$user_id_for_reset = null;

// --- STAGE 1: Handle GET request to verify token and display form ---
if ($_SERVER["REQUEST_METHOD"] == "GET") {
    if (!isset($_GET['token']) || empty(trim($_GET['token']))) {
        $error_message = "No token provided. This password set link is invalid.";
    } else {
        $token_from_url = trim($_GET['token']);

        $stmt = $conn->prepare("SELECT id, reset_token_expires_at FROM admin_users WHERE password_reset_token = ?");
        if ($stmt) {
            $stmt->bind_param("s", $token_from_url);
            $stmt->execute();
            $result = $stmt->get_result();

            if ($user = $result->fetch_assoc()) {
                // Check if token has expired
                if (strtotime($user['reset_token_expires_at']) > time()) {
                    $display_form = true;
                    $user_id_for_reset = $user['id']; // Store for use in form or POST (though re-validating token is better)
                } else {
                    $error_message = "This password set link has expired. Please contact an administrator or try the registration process again if applicable.";
                    // Optionally, clear the expired token here
                    $stmt_clear_expired = $conn->prepare("UPDATE admin_users SET password_reset_token = NULL, reset_token_expires_at = NULL WHERE password_reset_token = ?");
                    if($stmt_clear_expired) {
                        $stmt_clear_expired->bind_param("s", $token_from_url);
                        $stmt_clear_expired->execute();
                        $stmt_clear_expired->close();
                    }
                }
            } else {
                $error_message = "Invalid or already used password set link. Please contact an administrator if you believe this is an error.";
            }
            $stmt->close();
        } else {
            $error_message = "Database error validating your link. Please try again later. (" . $conn->error . ")";
        }
    }
}

// --- STAGE 2: Handle POST request to set the new password ---
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $token_from_form = trim($_POST['token'] ?? '');
    $new_password = $_POST['new_password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $validation_errors = [];

    if (empty($token_from_form)) {
        $validation_errors[] = "Token is missing. Cannot process request.";
    }
    if (empty($new_password)) {
        $validation_errors[] = "New password cannot be empty.";
    } elseif (strlen($new_password) < 6) { // Enforce a minimum length
        $validation_errors[] = "Password must be at least 6 characters long.";
    }
    if ($new_password !== $confirm_password) {
        $validation_errors[] = "Passwords do not match.";
    }

    if (empty($validation_errors)) {
        // Re-validate token from form before updating password
        $stmt_validate = $conn->prepare("SELECT id, reset_token_expires_at FROM admin_users WHERE password_reset_token = ?");
        if ($stmt_validate) {
            $stmt_validate->bind_param("s", $token_from_form);
            $stmt_validate->execute();
            $result_validate = $stmt_validate->get_result();

            if ($user_to_update = $result_validate->fetch_assoc()) {
                if (strtotime($user_to_update['reset_token_expires_at']) > time()) {
                    // Token is valid, proceed with password update
                    $user_id_to_update = $user_to_update['id'];
                    $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);

                    // Update password and clear the token fields
                    $stmt_update = $conn->prepare("UPDATE admin_users SET password = ?, password_reset_token = NULL, reset_token_expires_at = NULL WHERE id = ?");
                    if ($stmt_update) {
                        $stmt_update->bind_param("si", $hashed_new_password, $user_id_to_update);
                        if ($stmt_update->execute()) {
                            $success_message = "Your password has been successfully set! You can now log in using your new password.";
                            $display_form = false; // Hide form after success
                        } else {
                            $error_message = "Error updating password: " . $stmt_update->error;
                            $display_form = true; // Keep form displayed to allow retry
                            $token_from_url = $token_from_form; // Ensure token is available for form re-display
                        }
                        $stmt_update->close();
                    } else {
                         $error_message = "Database error preparing update: " . $conn->error;
                         $display_form = true;
                         $token_from_url = $token_from_form;
                    }
                } else {
                    $error_message = "Password set link has expired. Please try the process again or contact an administrator.";
                    $display_form = false;
                }
            } else {
                $error_message = "Invalid or already used password set link.";
                $display_form = false;
            }
            $stmt_validate->close();
        } else {
            $error_message = "Database error re-validating link: " . $conn->error;
            $display_form = true;
            $token_from_url = $token_from_form;
        }
    } else {
        $error_message = implode("<br>", $validation_errors);
        $display_form = true; // Keep form displayed if validation errors
        $token_from_url = $token_from_form; // Ensure token is available for form re-display
    }
}

if (isset($conn) && $conn instanceof mysqli) {
    // $conn->close(); // Optional: PHP closes connection at end of script
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Set Your Admin Password</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #000; color: #fff; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding-top: 2rem; }
        .monument { font-family: 'Montserrat', sans-serif; font-weight: 800; letter-spacing: -0.5px; }
        .gold { color: #D4AF37; }
        .btn { padding: 0.75rem 1.5rem; border-radius: 0.25rem; font-weight: 600; letter-spacing: 0.5px; transition: all 0.3s ease; cursor: pointer; }
        .btn-primary { background-color: #D4AF37; color: #000; }
        .btn-primary:hover { background-color: rgba(212, 175, 55, 0.9); transform: translateY(-2px); }
        .form-container { background-color: #1a1a1a; padding: 2rem; border-radius: 0.5rem; box-shadow: 0 4px 15px rgba(0,0,0,0.2); width: 100%; max-width: 450px; }
        .form-input { background-color: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; padding: 0.75rem 1rem; border-radius: 0.25rem; width: 100%; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
        .form-input:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3); }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #ccc; }
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.25rem; font-size: 0.9rem; width: 100%; max-width: 450px; }
        .alert-success { background-color: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.5); color: #a3d9b1; text-align: center; }
        .alert-error { background-color: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5); color: #f5c6cb; text-align: center; }
        .login-link { display: inline-block; margin-top: 1rem; color: #D4AF37; text-decoration: underline; }
        .login-link:hover { color: #fff; }
    </style>
</head>
<body>
    <div class="text-center mb-8">
        <h1 class="monument text-3xl font-bold gold">Luxury Limousine</h1>
        <p class="text-lg text-gray-400">Admin Password Setup</p>
    </div>

    <?php if (!empty($success_message)): ?>
        <div class="alert alert-success" role="alert">
            <i class="fas fa-check-circle mr-2"></i> <?php echo htmlspecialchars($success_message); ?>
            <p><a href="index.php" class="login-link">Go to Login Page</a></p>
        </div>
    <?php endif; ?>

    <?php if (!empty($error_message)): ?>
        <div class="alert alert-error" role="alert">
            <i class="fas fa-exclamation-triangle mr-2"></i> <?php echo $error_message; // Contains <br> sometimes, or DB error messages ?>
        </div>
    <?php endif; ?>

    <?php if ($display_form && empty($success_message)): ?>
        <div class="form-container">
            <h2 class="monument text-2xl text-white mb-6 text-center">Set Your New Password</h2>
            <form action="set_password.php" method="POST" class="space-y-6">
                <input type="hidden" name="token" value="<?php echo htmlspecialchars($token_from_url); ?>">
                <div>
                    <label for="new_password" class="form-label">New Password</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required>
                </div>
                <div>
                    <label for="confirm_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_password" name="confirm_password" class="form-input" required>
                </div>
                <div class="pt-2">
                    <button type="submit" name="set_password_submit" class="btn btn-primary w-full py-3 text-base">
                        <i class="fas fa-key mr-2"></i> Set New Password
                    </button>
                </div>
            </form>
        </div>
    <?php endif; ?>

    <footer class="text-center text-gray-600 text-sm mt-auto pb-4">
        <p>&copy; <?php echo date('Y'); ?> Luxury Transfer. All rights reserved.</p>
    </footer>
</body>
</html>
