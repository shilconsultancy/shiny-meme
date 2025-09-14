<?php
// admin/update_booking_status.php

// Ensure this script is only accessible to logged-in admins
require_once 'auth_check.php';

// Include database connection
require_once '../db_connect.php'; // $conn should be available from here

// Include PHPMailer classes
require_once '../vendor/autoload.php'; // Adjusted path for admin folder

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Set header to return JSON response
header('Content-Type: application/json');

// Define possible booking statuses for validation
$allowed_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];
// Define statuses that should trigger an email notification
$email_trigger_statuses = ['Confirmed', 'Completed', 'Cancelled'];

$response = ['success' => false, 'message' => 'An unknown error occurred.'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $booking_id = $_POST['id'] ?? null;
    $new_status = $_POST['status'] ?? null;

    if ($booking_id === null || $new_status === null) {
        $response['message'] = 'Missing booking ID or status.';
        echo json_encode($response);
        exit();
    }

    $booking_id = (int)$booking_id;
    $new_status = htmlspecialchars(trim($new_status));

    if (!in_array($new_status, $allowed_statuses)) {
        $response['message'] = 'Invalid status provided.';
        echo json_encode($response);
        exit();
    }

    try {
        $stmt_update = $conn->prepare("UPDATE bookings SET status = ? WHERE id = ?");
        if ($stmt_update === false) {
            throw new Exception("Database prepare failed for update: " . $conn->error);
        }
        $stmt_update->bind_param("si", $new_status, $booking_id);

        if ($stmt_update->execute()) {
            if ($stmt_update->affected_rows > 0) {
                $response = ['success' => true, 'message' => 'Booking status updated successfully.'];

                if (in_array($new_status, $email_trigger_statuses)) {
                    $stmt_fetch = $conn->prepare("SELECT name, email, phone, vehicle, pickuplocation, pickupdate, pickuptime, dropofflocation, notes FROM bookings WHERE id = ?");
                    if ($stmt_fetch === false) {
                        error_log("Database prepare failed for fetching booking details (for email): " . $conn->error);
                        $response['email_status'] = 'Could not fetch booking details for email.';
                    } else {
                        $stmt_fetch->bind_param("i", $booking_id);
                        $stmt_fetch->execute();
                        $result = $stmt_fetch->get_result();
                        $booking_details = $result->fetch_assoc();
                        $stmt_fetch->close();

                        if ($booking_details && !empty($booking_details['email'])) {
                            $mail = new PHPMailer(true);
                            try {
                                // Server settings
                                $mail->isSMTP();
                                $mail->Host       = 'smtp.hostinger.com';
                                $mail->SMTPAuth   = true;
                                $mail->Username   = 'info@luxurylimousine.dk';
                                $mail->Password   = 'rW5?fa^IG^xB';
                                $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                                $mail->Port       = 465;
                                $mail->CharSet    = 'UTF-8';

                                // Recipients
                                $mail->setFrom('info@luxurylimousine.dk', 'Luxury Limousine');
                                $mail->addAddress($booking_details['email'], $booking_details['name']);
                                $mail->addReplyTo('info@luxurylimousine.dk', 'Luxury Limousine');

                                // Email Content
                                $mail->isHTML(true);
                                $email_subject = '';
                                $email_greeting = "Dear <span class='gold-text'>" . htmlspecialchars($booking_details['name']) . "</span>,";
                                $email_main_message = '';
                                
                                // --- BEGIN: UPDATED PROFESSIONAL CONTENT ---
                                switch ($new_status) {
                                    case 'Confirmed':
                                        $email_subject = "Your Booking is Confirmed - Ref #" . $booking_id;
                                        $email_main_message = "<p>We are pleased to confirm your booking. Your ride is scheduled and our chauffeur will meet you at the specified time and location.</p>" .
                                                               "<p>Please review the details below to ensure they are correct. We look forward to providing you with a comfortable and luxurious transfer experience.</p>";
                                        break;
                                    case 'Completed':
                                        $email_subject = "Your Journey is Complete - Ref #" . $booking_id;
                                        $email_main_message = "<p>This is a notification that your ride with us (ID: #{$booking_id}) has been successfully <strong>completed</strong>. We hope you enjoyed your journey!</p>" .
                                                               "<p>We would be grateful if you could take a moment to share your experience. Your feedback is valuable to us and helps us to continually improve our service.</p>";
                                        break;
                                    case 'Cancelled':
                                        $email_subject = "Your Booking has been Cancelled - Ref #" . $booking_id;
                                        $email_main_message = "<p>This email is to confirm that your booking (ID: #{$booking_id}) has been <strong>cancelled</strong> as per your request.</p>" .
                                                               "<p>If you did not request this cancellation or if you wish to re-book in the future, please contact our support team. We hope to serve you again soon.</p>";
                                        break;
                                }
                                // --- END: UPDATED PROFESSIONAL CONTENT ---

                                $formatted_pickup_date = date('M d, Y', strtotime($booking_details['pickupdate']));
                                $formatted_pickup_time = date('h:i A', strtotime($booking_details['pickuptime']));
                                $notes_display = !empty($booking_details['notes']) ? htmlspecialchars($booking_details['notes']) : 'N/A';
                                
                                $mail->Subject = $email_subject;
                                $mail->Body = "
                                <html>
                                <head>
                                    <title>{$email_subject}</title>
                                    <style>
                                        body { font-family: 'Montserrat', sans-serif; background-color: #f4f4f4; color: #333; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                                        .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 0; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
                                        .header { background-color: #000; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
                                        .header h1 { color: #D4AF37; margin: 0; font-size: 28px; font-weight: 800; line-height: 1.2; }
                                        .content { padding: 20px 30px; line-height: 1.6; color: #555; }
                                        .content p { margin-bottom: 15px; }
                                        .booking-details { background-color: #f9f9f9; border-left: 4px solid #D4AF37; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; }
                                        .booking-details ul { list-style: none; padding: 0; margin: 0; }
                                        .booking-details li { margin-bottom: 8px; }
                                        .footer { background-color: #eee; padding: 20px; text-align: center; font-size: 12px; color: #777; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
                                        .gold-text { color: #D4AF37; font-weight: bold; }
                                        strong { color: #444; }
                                        a { color: #D4AF37; text-decoration: none; }
                                        a:hover { text-decoration: underline; }
                                    </style>
                                </head>
                                <body>
                                    <div class='container'>
                                        <div class='header'>
                                            <h1>LUXURY LIMOUSINE</h1>
                                        </div>
                                        <div class='content'>
                                            <p>{$email_greeting}</p>
                                            {$email_main_message}
                                            
                                            <div class='booking-details'>
                                                <p style='margin-top:0; font-size: 16px; font-weight: bold;'>Your Booking Summary:</p>
                                                <ul>
                                                    <li><strong>Booking ID:</strong> <span class='gold-text'>#" . $booking_id . "</span></li>
                                                    <li><strong>Vehicle:</strong> " . htmlspecialchars(ucfirst($booking_details['vehicle'])) . "</li>
                                                    <li><strong>Pickup:</strong> " . htmlspecialchars($booking_details['pickuplocation']) . " on " . $formatted_pickup_date . " at " . $formatted_pickup_time . "</li>
                                                    <li><strong>Dropoff:</strong> " . htmlspecialchars($booking_details['dropofflocation']) . "</li>
                                                    <li><strong>Notes:</strong> " . $notes_display . "</li>
                                                </ul>
                                            </div>
                                            
                                            <p>Sincerely,<br>The Luxury Limousine DK Team</p>
                                        </div>
                                        <div class='footer'>
                                            <p>&copy; " . date('Y') . " Luxury Limousine DK. All rights reserved.</p>
                                            <p><a href='mailto:info@luxurylimousine.dk'>info@luxurylimousine.dk</a> | <a href='tel:+4527218077'>+45 27 21 80 77</a></p>
                                            <p style='margin-top: 10px; font-size: 11px;'>Developed by <a href='https://shilconsultancy.co.uk/' target='_blank' style='color: #777;'>Shil Consultancy Services</a></p>
                                        </div>
                                    </div>
                                </body>
                                </html>";
                                
                                $mail->AltBody = strip_tags($mail->Body);
                                $mail->send();
                                $response['email_status'] = 'Email notification sent successfully.';
                            } catch (Exception $e_mail) {
                                error_log("PHPMailer Error for Booking ID {$booking_id} (Status: {$new_status}): " . $mail->ErrorInfo);
                                $response['email_status'] = 'Failed to send email notification. Error has been logged.';
                            }
                        } else {
                             error_log("Email not sent for Booking ID {$booking_id}: Customer email not found or booking details missing after status update to {$new_status}.");
                            $response['email_status'] = 'Email not sent: Customer email/details not found.';
                        }
                    }
                }
            } else {
                $response = ['success' => true, 'message' => 'Booking status was already set to ' . $new_status . '. No update needed.'];
            }
        } else {
            throw new Exception("Database execute failed for update: " . $stmt_update->error);
        }
        $stmt_update->close();

    } catch (Exception $e) {
        error_log("Error in update_booking_status.php (Booking ID: {$booking_id}, New Status: {$new_status}): " . $e->getMessage());
        $response = ['success' => false, 'message' => 'A server error occurred. Details have been logged.'];
    }

} else {
    $response['message'] = 'Invalid request method.';
}

$conn->close();
echo json_encode($response);
?>