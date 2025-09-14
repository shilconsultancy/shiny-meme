<?php
// admin/fetch_booking_details.php

// Start session if not already started (important for auth_check.php)
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Ensure this script is only accessible to logged-in admins
require_once 'auth_check.php';

// Include database connection
// Adjust this path if db_connect.php is not in the parent directory
require_once '../db_connect.php';

// Set header to return JSON response
header('Content-Type: application/json');

// --- Production Error Handling (recommended for server-side scripts) ---
ini_set('display_errors', 0); // Do not display errors to the user
ini_set('log_errors', 1);     // Log errors
// ini_set('error_log', '/path/to/your_project/php-error.log'); // Specify a writable log file path

$response = ['success' => false, 'message' => '', 'booking' => null];

// Get the booking ID from the GET request
$booking_id = $_GET['id'] ?? null;

// Validate the booking ID
if ($booking_id === null || !filter_var($booking_id, FILTER_VALIDATE_INT)) {
    $response['message'] = 'Invalid booking ID provided.';
    error_log("fetch_booking_details.php: Invalid booking ID received: " . ($booking_id ?? 'NULL'));
    echo json_encode($response);
    exit();
}

// Establish database connection
// Use a new connection for this script if $conn from db_connect.php isn't guaranteed to be open
$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    $response['message'] = 'Database connection failed.';
    error_log("fetch_booking_details.php: Database connection error: " . $conn->connect_error);
    echo json_encode($response);
    exit();
}

$conn->set_charset("utf8mb4");

try {
    // Prepare statement to fetch all details for the given ID
    $sql = "SELECT id, name, email, phone, vehicle, pickuplocation, pickupdate, pickuptime, dropofflocation, notes, created_at, status FROM bookings WHERE id = ?";
    $stmt = $conn->prepare($sql);
    
    if ($stmt === false) {
        $response['message'] = 'Database prepare failed.';
        error_log("fetch_booking_details.php: SQL prepare failed for ID {$booking_id}: " . $conn->error . " Query: " . $sql);
        throw new Exception("Database prepare failed: " . $conn->error); // Re-throw to be caught by the outer catch
    }

    $stmt->bind_param("i", $booking_id); // "i" for integer

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $booking_details = $result->fetch_assoc();
            $response['success'] = true;
            $response['booking'] = $booking_details;
        } else {
            $response['message'] = 'Booking not found.';
            error_log("fetch_booking_details.php: Booking ID {$booking_id} not found in database.");
        }
    } else {
        $response['message'] = 'Database query execution failed.';
        error_log("fetch_booking_details.php: SQL execute failed for ID {$booking_id}: " . $stmt->error);
        throw new Exception("Database execute failed: " . $stmt->error); // Re-throw to be caught by the outer catch
    }

    $stmt->close();

} catch (Exception $e) {
    // This catches any exceptions thrown within the try block
    // The specific error_log messages above will provide more detail,
    // but this ensures a graceful JSON error response for unexpected issues.
    if (empty($response['message'])) { // Only set if not already set by more specific error_log
        $response['message'] = 'An unexpected server error occurred.';
    }
    error_log("fetch_booking_details.php: Caught exception for ID {$booking_id}: " . $e->getMessage());
} finally {
    // Ensure database connection is closed
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
    // Always echo the response array as JSON at the end
    echo json_encode($response);
}
?>