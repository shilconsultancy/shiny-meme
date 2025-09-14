<?php
// /admin/email_functions.php

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Helper function to generate the common HTML email template
function generateEmailHtml(string $title, string $mainContent, array $bookingDetails): string {
    // Extract details for easier use in the template
    $name = htmlspecialchars($bookingDetails['name']);
    $bookingId = htmlspecialchars($bookingDetails['id']);
    $vehicle = htmlspecialchars(ucfirst($bookingDetails['vehicle']));
    $pickupLocation = htmlspecialchars($bookingDetails['pickuplocation']);
    $pickupDate = date('l, F j, Y', strtotime($bookingDetails['pickupdate']));
    $pickupTime = date('h:i A', strtotime($bookingDetails['pickuptime']));
    $dropoffLocation = htmlspecialchars($bookingDetails['dropofflocation']);
    $notes = !empty($bookingDetails['notes']) ? htmlspecialchars($bookingDetails['notes']) : 'N/A';
    $currentYear = date('Y');
    $companyEmail = SMTP_FROM_EMAIL; // Using constants from config.php

    // This is the full HTML structure adapted from your booking form email
    $html = "
    <html>
    <head>
        <title>{$title}</title>
        <style>
            body { font-family: 'Montserrat', sans-serif; background-color: #f4f4f4; color: #333; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
            .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
            .header { background-color: #000; padding: 20px; text-align: center; border-top-left-radius: 8px; border-top-right-radius: 8px; }
            .header h1 { color: #D4AF37; margin: 0; font-size: 28px; font-weight: 800; line-height: 1.2; }
            .content { padding: 20px 30px; line-height: 1.6; color: #555; }
            .content p { margin-bottom: 15px; }
            .booking-details { background-color: #f9f9f9; border-left: 4px solid #D4AF37; padding: 15px 20px; margin-bottom: 20px; border-radius: 4px; }
            .booking-details ul { list-style: none; padding: 0; margin: 0; }
            .booking-details li { margin-bottom: 10px; }
            .footer { background-color: #eee; padding: 20px; text-align: center; font-size: 12px; color: #777; border-bottom-left-radius: 8px; border-bottom-right-radius: 8px; }
            .gold-text { color: #D4AF37; font-weight: bold; }
            strong { color: #444; }
            a { color: #D4AF37; text-decoration: none; }
            a:hover { text-decoration: underline; }
            h3 { font-size: 18px; color: #333; margin-top: 25px; margin-bottom: 10px; border-bottom: 1px solid #eee; padding-bottom: 5px;}
        </style>
    </head>
    <body>
        <div class='container'>
            <div class='header'>
                <h1>LUXURY LIMOUSINE</h1>
            </div>
            <div class='content'>
                <p>Dear <span class='gold-text'>{$name}</span>,</p>
                {$mainContent}
                
                <div class='booking-details'>
                    <h3 style='margin-top:0; border:none;'>Your Booking Summary</h3>
                    <ul>
                        <li><strong>Reference ID:</strong> <span class='gold-text'>#{$bookingId}</span></li>
                        <li><strong>Vehicle:</strong> {$vehicle}</li>
                        <li><strong>Pickup:</strong> {$pickupLocation}<br>On {$pickupDate} at {$pickupTime}</li>
                        <li><strong>Drop-off:</strong> {$dropoffLocation}</li>
                        <li><strong>Notes:</strong> {$notes}</li>
                    </ul>
                </div>
                
                <h3>Need to Make a Change?</h3>
                <p>If you need to amend your booking or have any questions, please reply directly to this email or call our concierge team at <a href='tel:+4527218077'><span class='gold-text'>+45 27 21 80 77</span></a>.</p>
                
                <p>We look forward to providing you with a seamless and elegant travel experience.</p>
                <p>Sincerely,<br>The Luxury Limousine Team</p>
            </div>
            <div class='footer'>
                <p>&copy; {$currentYear} Luxury Limousine. All rights reserved.</p>
                <p><a href='mailto:{$companyEmail}'>{$companyEmail}</a> | <a href='tel:+4527218077'>+45 27 21 80 77</a></p>
                <p style='margin-top: 10px; font-size: 11px;'>Developed by <a href='https://shilconsultancy.co.uk/' target='_blank' style='color: #777;'>Shil Consultancy Services</a></p>
            </div>
        </div>
    </body>
    </html>";
    
    return $html;
}


/**
 * Sends a booking status update email to the customer.
 *
 * @param array $booking The booking details array from the database.
 * @return bool True if the email was sent successfully, false otherwise.
 */
function sendBookingStatusEmail(array $booking): bool {
    require_once __DIR__ . '/../vendor/autoload.php';
    $mail = new PHPMailer(true);

    try {
        // --- Server Settings from config.php ---
        $mail->isSMTP();
        $mail->Host       = SMTP_HOST;
        $mail->SMTPAuth   = true;
        $mail->Username   = SMTP_USER;
        $mail->Password   = SMTP_PASS;
        $mail->SMTPSecure = SMTP_ENCRYPTION;
        $mail->Port       = SMTP_PORT;

        // --- Recipients ---
        $mail->setFrom(SMTP_FROM_EMAIL, SMTP_FROM_NAME);
        $mail->addAddress($booking['email'], $booking['name']);

        // --- Content ---
        $mail->isHTML(true);
        $status = $booking['status'];
        $subject = '';
        $mainContent = ''; // The unique paragraphs for each status

        switch ($status) {
            case 'Confirmed':
                $subject = "Your Booking is Confirmed - Ref #" . $booking['id'];
                $mainContent = "<p>We are pleased to confirm your booking. Your ride is scheduled and our chauffeur will meet you at the specified time and location.</p>" .
                               "<p>Below is a summary of your confirmed booking. Please review the details to ensure they are correct.</p>";
                break;

            case 'Completed':
                $subject = "Your Journey is Complete - Ref #" . $booking['id'];
                $mainContent = "<p>This is a notification that your ride with us has been successfully completed. We hope you enjoyed your journey!</p>" .
                               "<p>We would be grateful if you could take a moment to share your experience. Your feedback is valuable to us and helps us to continually improve our service.</p>";
                break;

            case 'Cancelled':
                $subject = "Your Booking has been Cancelled - Ref #" . $booking['id'];
                $mainContent = "<p>This email is to confirm that your booking has been cancelled as per your request.</p>" .
                               "<p>If you did not request this cancellation or if you wish to re-book in the future, please contact our support team. We hope to serve you again soon.</p>";
                break;

            default:
                // For 'Pending' or other unhandled statuses, we don't send an email.
                return true;
        }

        // Generate the full HTML body using our new template function
        $fullHtmlBody = generateEmailHtml($subject, $mainContent, $booking);

        $mail->Subject = $subject;
        $mail->Body    = $fullHtmlBody;
        $mail->AltBody = strip_tags($mainContent); // Simple plain text fallback for old email clients

        $mail->send();
        return true; // Email was sent successfully

    } catch (Exception $e) {
        error_log("PHPMailer Error for Booking ID {$booking['id']}: {$mail->ErrorInfo}");
        return false; // Email failed to send
    }
}