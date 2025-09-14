<?php
// admin/edit_booking.php
session_start();
require_once 'auth_check.php';
require_once '../db_connect.php';

header('Content-Type: application/json');
$response = ['success' => false, 'message' => 'An unexpected error occurred.'];

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    $response['message'] = 'Invalid request method.';
    http_response_code(405); // Method Not Allowed
    echo json_encode($response);
    exit;
}

// Basic validation for presence
$booking_id = $_POST['booking_id'] ?? null;
if (empty($booking_id) || !filter_var($booking_id, FILTER_VALIDATE_INT)) {
    $response['message'] = 'Invalid Booking ID.';
    echo json_encode($response);
    exit;
}

// Sanitize and retrieve all form fields
$name = htmlspecialchars(trim($_POST['name'] ?? ''));
$email = htmlspecialchars(trim($_POST['email'] ?? ''));
$phone = htmlspecialchars(trim($_POST['phone'] ?? ''));
$vehicle = htmlspecialchars(trim($_POST['vehicle'] ?? ''));
$pickupLocation = htmlspecialchars(trim($_POST['pickupLocation'] ?? ''));
$pickupDate = htmlspecialchars(trim($_POST['pickupDate'] ?? ''));
$pickupTime = htmlspecialchars(trim($_POST['pickupTime'] ?? ''));
$dropoffLocation = htmlspecialchars(trim($_POST['dropoffLocation'] ?? ''));
$notes = htmlspecialchars(trim($_POST['notes'] ?? ''));
$status = htmlspecialchars(trim($_POST['status'] ?? ''));


$conn = new mysqli($servername, $username, $password, $dbname);

if ($conn->connect_error) {
    error_log("edit_booking.php - DB Connection Error: " . $conn->connect_error);
    $response['message'] = 'Database connection failed.';
    http_response_code(503);
    echo json_encode($response);
    exit;
}
$conn->set_charset("utf8mb4");

try {
    $stmt = $conn->prepare(
        "UPDATE bookings SET 
            name = ?, 
            email = ?, 
            phone = ?, 
            vehicle = ?, 
            pickupLocation = ?, 
            pickupDate = ?, 
            pickupTime = ?, 
            dropoffLocation = ?, 
            notes = ?,
            status = ?
        WHERE id = ?"
    );

    if (!$stmt) {
        throw new Exception("Prepare statement failed: " . $conn->error);
    }

    $stmt->bind_param(
        "ssssssssssi",
        $name,
        $email,
        $phone,
        $vehicle,
        $pickupLocation,
        $pickupDate,
        $pickupTime,
        $dropoffLocation,
        $notes,
        $status,
        $booking_id
    );

    if ($stmt->execute()) {
        if ($stmt->affected_rows > 0) {
            $response['success'] = true;
            $response['message'] = 'Booking details updated successfully!';
        } else {
            $response['success'] = true; // It's not an error if no data was changed
            $response['message'] = 'No changes were made to the booking.';
        }
    } else {
        throw new Exception("Execute statement failed: " . $stmt->error);
    }

    $stmt->close();
} catch (Exception $e) {
    error_log("edit_booking.php - Exception: " . $e->getMessage());
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