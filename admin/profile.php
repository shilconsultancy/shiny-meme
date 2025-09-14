<?php
// admin/my_profile.php (View profile and change own password)
session_start();
require_once 'auth_check.php'; // Ensure admin is logged in
require_once '../db_connect.php'; // Database connection

// Enable error reporting for debugging (remove or adjust for production)
// ini_set('display_errors', 1);
// ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// For production, it's better to log errors and not display them:
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Ensure error_log path is set in php.ini or here:
// ini_set('error_log', '/path/to/your_project/php-errors.log');


$admin_user = null;
$profile_error_message = ''; // For errors loading profile data

// Password update specific messages
$password_update_success_message = '';
$password_update_error_message = '';
$password_errors = []; // For individual field validation errors during password update

// --- Handle Password Update Request ---
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['change_password_submit'])) {
    if (!isset($_SESSION['admin_id'])) {
        $password_update_error_message = "Session expired. Please log in again to change your password.";
    } else {
        $admin_id = $_SESSION['admin_id'];
        $current_password = $_POST['current_password'];
        $new_password = $_POST['new_password'];
        $confirm_new_password = $_POST['confirm_new_password'];

        // Validation
        if (empty($current_password)) {
            $password_errors[] = "Current password is required.";
        }
        if (empty($new_password)) {
            $password_errors[] = "New password is required.";
        } elseif (strlen($new_password) < 6) { // Basic length check
            $password_errors[] = "New password must be at least 6 characters long.";
        }
        if ($new_password !== $confirm_new_password) {
            $password_errors[] = "New passwords do not match.";
        }

        if (empty($password_errors)) {
            // Establish a new connection for this specific operation or ensure $conn is still valid
            $conn_pw_update = new mysqli($servername, $username, $password, $dbname);
            if ($conn_pw_update->connect_error) {
                $password_update_error_message = "Database connection error.";
                error_log("MyProfile PW Update DB Connect Error: " . $conn_pw_update->connect_error);
            } else {
                $conn_pw_update->set_charset("utf8mb4");
                // Fetch current user's hashed password from DB
                $stmt_check_pass = $conn_pw_update->prepare("SELECT password FROM admin_users WHERE id = ?");
                if ($stmt_check_pass) {
                    $stmt_check_pass->bind_param("i", $admin_id);
                    $stmt_check_pass->execute();
                    $result_check_pass = $stmt_check_pass->get_result();
                    if ($user_data = $result_check_pass->fetch_assoc()) {
                        if (password_verify($current_password, $user_data['password'])) {
                            // Current password is correct, proceed to update
                            $hashed_new_password = password_hash($new_password, PASSWORD_DEFAULT);
                            $stmt_update_pass = $conn_pw_update->prepare("UPDATE admin_users SET password = ? WHERE id = ?");
                            if ($stmt_update_pass) {
                                $stmt_update_pass->bind_param("si", $hashed_new_password, $admin_id);
                                if ($stmt_update_pass->execute()) {
                                    $password_update_success_message = "Password updated successfully!";
                                } else {
                                    $password_update_error_message = "Error updating password: " . $stmt_update_pass->error;
                                    error_log("MyProfile PW Update Execute Error: " . $stmt_update_pass->error);
                                }
                                $stmt_update_pass->close();
                            } else {
                                 $password_update_error_message = "Database error (prepare update): " . $conn_pw_update->error;
                                 error_log("MyProfile PW Update Prepare Error: " . $conn_pw_update->error);
                            }
                        } else {
                            $password_update_error_message = "Incorrect current password.";
                        }
                    } else {
                        $password_update_error_message = "User not found (should not happen if session is valid).";
                    }
                    $stmt_check_pass->close();
                } else {
                    $password_update_error_message = "Database error (prepare check): " . $conn_pw_update->error;
                    error_log("MyProfile PW Check Prepare Error: " . $conn_pw_update->error);
                }
                $conn_pw_update->close();
            }
        } else {
            // Concatenate validation errors
            $password_update_error_message = implode("<br>", $password_errors);
        }
    }
}

// --- Fetch and Display Profile Information (Always runs) ---
// Use a new connection or ensure $conn is valid if it was closed after POST handling
$conn_profile_fetch = new mysqli($servername, $username, $password, $dbname);
if ($conn_profile_fetch->connect_error) {
    $profile_error_message = "Database connection error for profile.";
    error_log("MyProfile Fetch DB Connect Error: " . $conn_profile_fetch->connect_error);
} else {
    $conn_profile_fetch->set_charset("utf8mb4");
    if (!isset($_SESSION['admin_id'])) {
        $profile_error_message = "Admin session not found. Please log in again.";
    } else {
        $admin_id = $_SESSION['admin_id'];
        $stmt_profile = $conn_profile_fetch->prepare("SELECT id, username, email, created_at FROM admin_users WHERE id = ?");
        if ($stmt_profile) {
            $stmt_profile->bind_param("i", $admin_id);
            $stmt_profile->execute();
            $result_profile = $stmt_profile->get_result();
            if ($result_profile->num_rows === 1) {
                $admin_user = $result_profile->fetch_assoc();
            } else {
                $profile_error_message = "Admin profile not found in the database.";
            }
            $stmt_profile->close();
        } else {
            $profile_error_message = "Database error preparing profile statement: " . $conn_profile_fetch->error;
            error_log("MyProfile Fetch Prepare Error: " . $conn_profile_fetch->error);
        }
    }
    $conn_profile_fetch->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Profile - Admin Panel</title> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #000;
            color: #fff;
            min-height: 100vh;
            display: flex;
            flex-direction: column;
        }
        /* .monument and .gold are defined in admin_header.php for header use */
        /* Define them here if used by other elements on this page specifically */
        .monument {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .gold { color: #D4AF37; }
        .bg-gold { background-color: #D4AF37; }
        .border-gold { border-color: #D4AF37; }

        .btn {
            padding: 0.75rem 1.5rem; 
            border-radius: 0.25rem;
            font-weight: 600;
            letter-spacing: 0.5px;
            transition: all 0.3s ease;
            cursor: pointer;
            text-decoration: none; 
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        .btn-primary {
            background-color: #D4AF37;
            color: #000;
        }
        .btn-primary:hover {
            background-color: rgba(212, 175, 55, 0.9);
            transform: translateY(-1px); /* Subtle lift */
        }
        .btn-secondary { /* Used by admin_header.php Options button */
            background-color: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover {
            background-color: rgba(255, 255, 255, 0.2);
        }
        
        .content-container { 
            background-color: #1a1a1a; 
            padding: 2rem;
            border-radius: 0.5rem;
            box-shadow: 0 4px 15px rgba(0,0,0,0.2);
            max-width: 600px;
            margin-left: auto;
            margin-right: auto;
            margin-bottom: 2rem; 
        }
        .profile-detail {
            margin-bottom: 1.25rem;
            font-size: 1rem;
            display: flex; /* For better alignment of label and value */
            align-items: flex-start;
        }
        .profile-detail strong {
            color: #D4AF37;
            display: inline-block;
            width: 120px; /* Fixed width for labels */
            font-weight: 600;
            flex-shrink: 0; /* Prevent label from shrinking */
        }
        .profile-detail span {
            color: #ccc;
            word-break: break-word; /* Ensure long values wrap */
        }
        .alert {
            padding: 1rem;
            margin-bottom: 1.5rem;
            border-radius: 0.25rem;
            font-size: 0.9rem;
            /* max-width: 600px; */ /* Handled by content-container for password form alerts */
            /* margin-left: auto; */
            /* margin-right: auto; */
            display: flex; /* For icon alignment */
            align-items: center;
        }
        .alert-success {
            background-color: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #a3d9b1;
        }
        .alert-error {
            background-color: rgba(220, 53, 69, 0.2); 
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #f5c6cb;
        }
        .alert i { margin-right: 0.5rem;} /* Space for icon */

        .form-label {
            display: block;
            margin-bottom: 0.5rem;
            font-weight: 600;
            color: #ccc;
        }
        .form-input {
            background-color: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: #fff;
            padding: 0.75rem 1rem;
            border-radius: 0.25rem;
            width: 100%;
            transition: border-color 0.3s ease, box-shadow 0.3s ease;
        }
        .form-input:focus {
            outline: none;
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3);
        }
    </style>
</head>
<body class="flex flex-col">

    <?php require_once 'admin_header.php'; // ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h2 class="monument text-3xl font-bold mb-8 text-center">My <span class="gold">Profile</span></h2>

        <?php if (!empty($profile_error_message)): ?>
            <div class="max-w-xl mx-auto"> <div class="alert alert-error" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo htmlspecialchars($profile_error_message); ?>
                </div>
            </div>
        <?php endif; ?>

        <?php if ($admin_user && empty($profile_error_message)): ?>
            <div class="content-container">
                <h3 class="monument text-xl text-white mb-6 border-b border-gray-700 pb-3">Profile Details</h3>
                <div class="profile-detail">
                    <strong>ID:</strong>
                    <span><?php echo htmlspecialchars($admin_user['id']); ?></span>
                </div>
                <div class="profile-detail">
                    <strong>Username:</strong>
                    <span><?php echo htmlspecialchars($admin_user['username']); ?></span>
                </div>
                <div class="profile-detail">
                    <strong>Email:</strong>
                    <span><?php echo htmlspecialchars($admin_user['email']); ?></span>
                </div>
                <div class="profile-detail">
                    <strong>Joined:</strong>
                    <span><?php echo htmlspecialchars(date("F j, Y, g:i a", strtotime($admin_user['created_at']))); ?></span>
                </div>
            </div>
        <?php elseif (empty($profile_error_message)): ?>
             <p class="text-center text-gray-500 py-4">Loading profile information...</p>
        <?php endif; ?>

        <?php if ($admin_user && empty($profile_error_message)): // Only show password form if profile loaded ?>
        <div class="content-container">
            <h3 class="monument text-xl text-white mb-6 border-b border-gray-700 pb-3">Change Password</h3>

            <?php if (!empty($password_update_success_message)): ?>
                <div class="alert alert-success mb-4" role="alert">
                    <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($password_update_success_message); ?>
                </div>
            <?php endif; ?>
            <?php if (!empty($password_update_error_message)): ?>
                <div class="alert alert-error mb-4" role="alert">
                    <i class="fas fa-exclamation-triangle"></i> <?php echo $password_update_error_message; /* Already HTML if multiple errors, else htmlspecialchars needed if plain db error */ ?>
                </div>
            <?php endif; ?>

            <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="space-y-6">
                <div>
                    <label for="current_password" class="form-label">Current Password</label>
                    <input type="password" id="current_password" name="current_password" class="form-input" required>
                </div>
                <div>
                    <label for="new_password" class="form-label">New Password (min. 6 characters)</label>
                    <input type="password" id="new_password" name="new_password" class="form-input" required>
                </div>
                <div>
                    <label for="confirm_new_password" class="form-label">Confirm New Password</label>
                    <input type="password" id="confirm_new_password" name="confirm_new_password" class="form-input" required>
                </div>
                <div class="pt-2">
                    <button type="submit" name="change_password_submit" class="btn btn-primary w-full py-3 text-base">
                        <i class="fas fa-key mr-2"></i> Update Password
                    </button>
                </div>
            </form>
        </div>
        <?php endif; ?>

    </main>

    <footer class="bg-black py-4 text-center text-gray-600 text-sm border-t border-gray-800 mt-auto">
        <p>&copy; <?php echo date('Y'); ?> Luxury Limousine. All rights reserved.</p> </footer>
</body>
</html>