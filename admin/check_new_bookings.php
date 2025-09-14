<?php
// admin/check_new_bookings.php

// Ensure this script is only accessible to logged-in admins
require_once 'auth_check.php';

// Include database connection
require_once '../db_connect.php';

// Set header to return JSON response
header('Content-Type: application/json');

// Enable error reporting for debugging
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$newBookings = [];

try {
    // 1. Fetch new bookings (where notified = 0)
    $stmt = $conn->prepare("SELECT id, name, email, phone, vehicle, pickuplocation, pickupdate, pickuptime, dropofflocation, notes, created_at FROM bookings WHERE notified = 0 ORDER BY created_at ASC");
    
    if ($stmt === false) {
        throw new Exception("Database prepare failed: " . $conn->error);
    }

    if ($stmt->execute()) {
        $result = $stmt->get_result();
        if ($result->num_rows > 0) {
            $bookingIdsToUpdate = [];
            while ($row = $result->fetch_assoc()) {
                $newBookings[] = $row;
                $bookingIdsToUpdate[] = $row['id'];
            }

            // 2. Mark these bookings as notified
            if (!empty($bookingIdsToUpdate)) {
                $placeholders = implode(',', array_fill(0, count($bookingIdsToUpdate), '?'));
                $types = str_repeat('i', count($bookingIdsToUpdate)); // 'i' for integer

                $updateStmt = $conn->prepare("UPDATE bookings SET notified = 1 WHERE id IN ($placeholders)");
                if ($updateStmt === false) {
                    throw new Exception("Database update prepare failed: " . $conn->error);
                }
                $updateStmt->bind_param($types, ...$bookingIdsToUpdate);
                $updateStmt->execute();
                $updateStmt->close();
            }
        }
    } else {
        throw new Exception("Database execute failed: " . $stmt->error);
    }

    $stmt->close();
    echo json_encode(['success' => true, 'bookings' => $newBookings]);

} catch (Exception $e) {
    // Log the error in a real application, don't expose sensitive info
    error_log("Error in check_new_bookings.php: " . $e->getMessage());
    echo json_encode(['success' => false, 'message' => 'Server error while checking for new bookings.']);
} finally {
    if (isset($conn) && $conn instanceof mysqli) {
        $conn->close();
    }
}
?>