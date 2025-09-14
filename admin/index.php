<?php
// admin/index.php - Admin Login Page

require_once '../db_connect.php'; // Include your database connection file
session_start(); // Start the session. This is crucial for maintaining login state.

// Enable all error reporting for debugging purposes.
// REMOVE OR DISABLE IN PRODUCTION ENVIRONMENT FOR SECURITY.
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$error_message = ''; // Variable to store login error messages

// Check if the form has been submitted
if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Sanitize and collect form data
    $username = htmlspecialchars(trim($_POST['username'] ?? ''));
    $password = htmlspecialchars(trim($_POST['password'] ?? ''));

    // Basic validation: Check if fields are empty
    if (empty($username) || empty($password)) {
        $error_message = "Please enter both username and password.";
    } else {
        // Prepare SQL statement to fetch admin user by username
        $stmt = $conn->prepare("SELECT id, username, password FROM admin_users WHERE username = ?");

        // Check if the prepare statement failed
        if ($stmt === false) {
            $error_message = "Database error: Unable to prepare statement. " . $conn->error;
        } else {
            // Bind the username parameter
            $stmt->bind_param("s", $username);

            // Execute the statement
            if ($stmt->execute()) {
                // Get the result
                $result = $stmt->get_result();

                // Check if a user with the given username exists
                if ($result->num_rows === 1) {
                    $user = $result->fetch_assoc();
                    $hashed_password_from_db = $user['password'];

                    // Verify the provided password against the hashed password from the database
                    if (password_verify($password, $hashed_password_from_db)) {
                        // Password is correct, set session variables
                        $_SESSION['admin_logged_in'] = true;
                        $_SESSION['admin_username'] = $user['username'];
                        $_SESSION['admin_id'] = $user['id'];

                        // Redirect to the admin dashboard
                        header("Location: dashboard.php");
                        exit(); // Stop script execution after redirection
                    } else {
                        // Invalid password
                        $error_message = "Invalid username or password.";
                    }
                } else {
                    // User not found
                    $error_message = "Invalid username or password.";
                }
            } else {
                // Error executing the statement
                $error_message = "Database error: Could not execute query. " . $stmt->error;
            }

            // Close the statement
            $stmt->close();
        }
    }
}

// Close database connection
if (isset($conn) && $conn instanceof mysqli) {
    $conn->close();
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Login - Luxury Transfer</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #000;
            color: #fff;
            display: flex;
            justify-content: center;
            align-items: center;
            min-height: 100vh;
            margin: 0;
            flex-direction: column;
        }
        
        .monument {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .gold {
            color: #D4AF37;
        }
        
        .bg-gold {
            background-color: #D4AF37;
        }
        
        .form-input {
            background-color: rgba(255, 255, 255, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: white;
            transition: all 0.3s ease;
        }
        
        .form-input:focus {
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            background-color: rgba(255, 255, 255, 0.15);
        }
        
        .form-label {
            color: #D4AF37;
            font-weight: 600;
        }
        
        .btn-hover {
            transition: all 0.3s ease;
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
        }

        /* Error Message Styles */
        .error-message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            width: 90%;
            pointer-events: none; /* Allows clicks through when not active */
        }

        .error-message {
            background: linear-gradient(145deg, rgba(15, 15, 15, 0.95), rgba(20, 20, 20, 0.95));
            border-left: 4px solid #dc3545; /* Red for error */
            border-radius: 0.25rem;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.5);
            display: flex;
            align-items: flex-start;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55), opacity 0.3s ease;
            opacity: 0;
            pointer-events: auto; /* Re-enables clicks when active */
        }

        .error-message.active {
            transform: translateX(0);
            opacity: 1;
        }

        .error-icon {
            font-size: 1.5rem;
            color: #dc3545;
            margin-right: 1rem;
            animation: shake 0.5s;
        }

        @keyframes shake {
            0%, 100% { transform: translateX(0); }
            10%, 30%, 50%, 70%, 90% { transform: translateX(-5px); }
            20%, 40%, 60%, 80% { transform: translateX(5px); }
        }

        .error-content {
            flex: 1;
        }

        .error-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
            color: #dc3545;
        }

        .error-text {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }

        .error-close {
            color: rgba(255, 255, 255, 0.6);
            cursor: pointer;
            transition: color 0.2s ease;
            margin-left: 0.5rem;
        }

        .error-close:hover {
            color: #D4AF37;
        }
    </style>
</head>
<body>
    <div class="max-w-md w-full p-8 md:p-10 bg-black bg-opacity-80 rounded-lg shadow-xl text-center">
        <h1 class="monument text-3xl font-bold mb-6 tracking-tight">ADMIN <span class="gold">LOGIN</span></h1>
        <p class="text-gray-300 mb-8">Access your booking management dashboard.</p>

        <form method="POST" action="index.php" class="space-y-6">
            <div>
                <label for="username" class="form-label block text-left mb-2">Username</label>
                <input type="text" id="username" name="username" required class="form-input w-full px-4 py-3 rounded-sm focus:outline-none focus:ring-1 focus:ring-gold" autocomplete="username">
            </div>
            <div>
                <label for="password" class="form-label block text-left mb-2">Password</label>
                <input type="password" id="password" name="password" required class="form-input w-full px-4 py-3 rounded-sm focus:outline-none focus:ring-1 focus:ring-gold" autocomplete="current-password">
            </div>
            <div class="pt-4">
                <button type="submit" class="bg-gold text-black px-8 py-4 rounded-sm font-bold text-sm hover:bg-opacity-90 transition btn-hover uppercase tracking-wider w-full">
                    <i class="fas fa-sign-in-alt mr-2"></i> LOGIN
                </button>
            </div>
        </form>
    </div>

    <div class="error-message-container">
        <div id="errorMessage" class="error-message">
            <div class="error-icon">
                <i class="fas fa-times-circle"></i>
            </div>
            <div class="error-content">
                <h4 class="error-title">Login Failed!</h4>
                <p class="error-text" id="errorMessageText"></p>
            </div>
            <div class="error-close" id="closeErrorMessage">
                <i class="fas fa-times"></i>
            </div>
        </div>
    </div>

    <script>
        // JavaScript for displaying and hiding the error message
        function showErrorMessage(message) {
            const errorMessageDiv = document.getElementById('errorMessage');
            const errorMessageText = document.getElementById('errorMessageText');
            errorMessageText.textContent = message;
            errorMessageDiv.classList.add('active');
            
            // Auto-hide after 5 seconds
            setTimeout(() => {
                hideErrorMessage();
            }, 5000);
        }

        function hideErrorMessage() {
            const errorMessageDiv = document.getElementById('errorMessage');
            errorMessageDiv.classList.remove('active');
        }

        // Close button for the error message
        document.getElementById('closeErrorMessage').addEventListener('click', function() {
            hideErrorMessage();
        });

        // Display PHP error message on page load if present
        window.onload = function() {
            <?php if (!empty($error_message)): ?>
                showErrorMessage("<?php echo $error_message; ?>");
            <?php endif; ?>
        };
    </script>
</body>
</html>