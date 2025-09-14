<?php
// admin/add_user.php

session_start();

// --- PHPMailer Inclusion via Composer ---
require_once __DIR__ . '/../vendor/autoload.php'; // Path: admin -> project_root/vendor
// --- End PHPMailer Inclusion ---

// Your existing require_once lines for auth_check.php and db_connect.php
require_once 'auth_check.php';
require_once '../db_connect.php'; // Path: admin -> project_root/db_connect.php

// PHPMailer use statements
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Error reporting for development - consider adjusting for production
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
// error_reporting(E_ALL);
// For production, it's better to log errors and not display them:
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// Ensure error_log path is set in php.ini or here:
// ini_set('error_log', '/path/to/your_project/php-errors.log');

$username_input = "";
$email_input = "";
$success_message = "";
$error_message = ""; // General error message
$errors = []; // Specific validation errors

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    $username_input = trim($_POST['username']);
    $email_input = trim($_POST['email']);
    
    // Generate a temporary placeholder password hash. User will set their own.
    $initial_password_placeholder = bin2hex(random_bytes(16)); // Not stored, just for hashing
    $hashed_password = password_hash($initial_password_placeholder, PASSWORD_DEFAULT);

    // Validation
    if (empty($username_input)) {
        $errors[] = "Username is required.";
    } elseif (strlen($username_input) < 3) {
        $errors[] = "Username must be at least 3 characters long.";
    } elseif (!preg_match("/^[a-zA-Z0-9_]+$/", $username_input)) {
        $errors[] = "Username can only contain letters, numbers, and underscores.";
    }

    if (empty($email_input)) {
        $errors[] = "Email is required.";
    } elseif (!filter_var($email_input, FILTER_VALIDATE_EMAIL)) {
        $errors[] = "Invalid email format.";
    }

    // Check if username or email already exists
    if (empty($errors)) {
        $conn_check = new mysqli($servername, $username, $password, $dbname);
        if ($conn_check->connect_error) {
            $errors[] = "Database connection error during pre-check.";
            error_log("AddUser Pre-check DB Connect Error: " . $conn_check->connect_error);
        } else {
            $conn_check->set_charset("utf8mb4");
            $stmt_check = $conn_check->prepare("SELECT id, username, email FROM admin_users WHERE username = ? OR email = ?");
            if ($stmt_check) {
                $stmt_check->bind_param("ss", $username_input, $email_input);
                $stmt_check->execute();
                $result_check = $stmt_check->get_result();
                if ($result_check->num_rows > 0) {
                    $existing_user = $result_check->fetch_assoc();
                    if ($existing_user['username'] === $username_input) {
                        $errors[] = "Username already taken. Please choose another.";
                    }
                    if ($existing_user['email'] === $email_input) {
                        $errors[] = "Email already registered. Please use another.";
                    }
                }
                $stmt_check->close();
            } else {
                $errors[] = "Database error (check existing): " . $conn_check->error;
                error_log("AddUser Check Existing Prepare Error: " . $conn_check->error);
            }
            $conn_check->close();
        }
    }

    if (empty($errors)) {
        $conn_insert_user = new mysqli($servername, $username, $password, $dbname);
        if ($conn_insert_user->connect_error) {
            $error_message = "Database connection error for user creation.";
            error_log("AddUser Insert DB Connect Error: " . $conn_insert_user->connect_error);
        } else {
            $conn_insert_user->set_charset("utf8mb4");
            $stmt_insert = $conn_insert_user->prepare("INSERT INTO admin_users (username, email, password) VALUES (?, ?, ?)");
            if ($stmt_insert) {
                $stmt_insert->bind_param("sss", $username_input, $email_input, $hashed_password);
                if ($stmt_insert->execute()) {
                    $new_user_id = $conn_insert_user->insert_id;

                    // Generate password set token
                    $token = bin2hex(random_bytes(32));
                    $expires_at = date("Y-m-d H:i:s", strtotime("+1 hour"));

                    $stmt_token = $conn_insert_user->prepare("UPDATE admin_users SET password_reset_token = ?, reset_token_expires_at = ? WHERE id = ?");
                    if ($stmt_token) {
                        $stmt_token->bind_param("ssi", $token, $expires_at, $new_user_id);
                        if ($stmt_token->execute()) {
                            $success_message = "New admin user '$username_input' created successfully!";

                            // Send email with password set link
                            $set_password_link = "https://booking.luxurylimousine.dk/admin/set_password.php?token=" . $token; // Ensure this URL is correct
                            $message_body = "Hello " . htmlspecialchars($username_input) . ",\n\n";
                            $message_body .= "An admin account has been created for you at Luxury Limousine.\n";
                            $message_body .= "Please set your password by clicking the link below. This link is valid for 1 hour.\n\n";
                            $message_body .= "Set your password: " . $set_password_link . "\n\n";
                            $message_body .= "If you did not request this account or have concerns, please contact support.\n\n";
                            $message_body .= "Regards,\n";
                            $message_body .= "Luxury Limousine Admin Team";

                            $mail = new PHPMailer(true);
                            try {
                                $mail->SMTPDebug = SMTP::DEBUG_OFF; // SMTP::DEBUG_SERVER for verbose debug output
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.hostinger.com'; 
                                $mail->SMTPAuth   = true;                     
                                $mail->Username   = 'info@luxurylimousine.dk'; // Your Hostinger email
                                $mail->Password   = 'd:zSi3wV>';              // Your Hostinger email password - **IMPORTANT: MOVE TO A SECURE CONFIG/ENV VARIABLE**
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS; 
                                $mail->Port       = 465;                      

                                $mail->setFrom('info@luxurylimousine.dk', 'Luxury Limousine Admin');
                                $mail->addAddress($email_input, $username_input);

                                $mail->isHTML(false); // Send as plain text
                                $mail->Subject = 'Set Your Password - Luxury Limousine Admin Account';
                                $mail->Body    = $message_body;

                                $mail->send();
                                $success_message .= " A link to set the password has been sent to the user's email.";
                            } catch (Exception $e) {
                                $success_message .= " However, the email with the password set link could not be sent. Please check mail configuration or contact the user manually. Mailer Error: {$mail->ErrorInfo}";
                                error_log("AddUser PHPMailer Error: {$mail->ErrorInfo}");
                            }
                        } else {
                             $error_message = "User created, but failed to update password set token: " . $stmt_token->error;
                             error_log("AddUser Token Update Execute Error: " . $stmt_token->error);
                        }
                        $stmt_token->close();
                    } else {
                        $error_message = "User created, but failed to prepare token update statement: " . $conn_insert_user->error;
                        error_log("AddUser Token Update Prepare Error: " . $conn_insert_user->error);
                    }
                    
                    // Clear inputs on full success
                    $username_input = "";
                    $email_input = "";
                } else {
                    $error_message = "Failed to create user: " . $stmt_insert->error;
                    error_log("AddUser Insert Execute Error: " . $stmt_insert->error);
                }
                $stmt_insert->close();
            } else {
                $error_message = "Database error (prepare insert): " . $conn_insert_user->error;
                error_log("AddUser Insert Prepare Error: " . $conn_insert_user->error);
            }
            $conn_insert_user->close();
        }
    } else {
        $error_message = implode("<br>", $errors);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add New Admin User - Admin Panel</title> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #000; color: #fff; min-height: 100vh; display: flex; flex-direction: column; }
        /* .monument and .gold are defined in admin_header.php for header use */
        /* Define them here if used by other elements on this page specifically */
        .monument { font-family: 'Montserrat', sans-serif; font-weight: 800; letter-spacing: -0.5px; }
        .gold { color: #D4AF37; }
        .bg-gold { background-color: #D4AF37; }

        /* Global Button Styles - these are used by header and this page's form */
        .btn { padding: 0.75rem 1.5rem; border-radius: 0.25rem; font-weight: 600; letter-spacing: 0.5px; transition: all 0.3s ease; cursor: pointer; display:inline-flex; align-items:center; justify-content:center; text-decoration: none; }
        .btn-primary { background-color: #D4AF37; color: #000; }
        .btn-primary:hover { background-color: rgba(212, 175, 55, 0.9); transform: translateY(-2px); }
        .btn-secondary { background-color: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-secondary:hover { background-color: rgba(255, 255, 255, 0.2); }

        .form-input { background-color: rgba(255, 255, 255, 0.05); border: 1px solid rgba(255, 255, 255, 0.2); color: #fff; padding: 0.75rem 1rem; border-radius: 0.25rem; width: 100%; transition: border-color 0.3s ease, box-shadow 0.3s ease; }
        .form-input:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.3); }
        .form-label { display: block; margin-bottom: 0.5rem; font-weight: 600; color: #ccc; }
        
        .alert { padding: 1rem; margin-bottom: 1.5rem; border-radius: 0.25rem; font-size: 0.9rem; display:flex; align-items:center; }
        .alert-success { background-color: rgba(40, 167, 69, 0.2); border: 1px solid rgba(40, 167, 69, 0.5); color: #a3d9b1; }
        .alert-error { background-color: rgba(220, 53, 69, 0.2); border: 1px solid rgba(220, 53, 69, 0.5); color: #f5c6cb; }
        .alert i { margin-right: 0.5rem; }
    </style>
</head>
<body class="flex flex-col">

    <?php require_once 'admin_header.php'; // ?>

    <main class="flex-grow container mx-auto px-4 sm:px-6 lg:px-8 py-10 max-w-2xl">
        <h2 class="monument text-3xl font-bold mb-8 text-center">Add <span class="gold">New Admin User</span></h2>

        <?php if (!empty($success_message)): ?>
            <div class="alert alert-success" role="alert">
                <i class="fas fa-check-circle"></i> <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($error_message)): ?>
            <div class="alert alert-error" role="alert">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error_message; // Error message may contain <br>, so not htmlspecialchars here
                                                          // Ensure $errors array elements are sanitized if directly outputted,
                                                          // but implode("<br>", $errors) is generally fine if errors are just text.
                                                     ?>
            </div>
        <?php endif; ?>

        <form action="<?php echo htmlspecialchars($_SERVER["PHP_SELF"]); ?>" method="POST" class="bg-gray-900 p-6 sm:p-8 rounded-lg shadow-xl space-y-6">
            <div>
                <label for="username" class="form-label">Username</label>
                <input type="text" id="username" name="username" class="form-input" value="<?php echo htmlspecialchars($username_input); ?>" required>
            </div>
            <div>
                <label for="email" class="form-label">Email Address</label>
                <input type="email" id="email" name="email" class="form-input" value="<?php echo htmlspecialchars($email_input); ?>" required>
            </div>
            <div>
                <label for="password_info" class="form-label">Password</label>
                <div class="form-input bg-gray-800 text-gray-500 italic cursor-not-allowed">User will set their password via an email link.</div>
            </div>
            
            <div class="pt-4">
                <button type="submit" class="btn btn-primary w-full py-3 text-base">
                    <i class="fas fa-user-plus mr-2"></i> Create Admin User
                </button>
            </div>
        </form>
    </main>

    <footer class="bg-black py-4 text-center text-gray-600 text-sm border-t border-gray-800 mt-auto">
        <p>&copy; <?php echo date('Y'); ?> Luxury Limousine. All rights reserved.</p> </footer>
</body>
</html>