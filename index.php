<?php
// index.php
require_once 'db_connect.php'; // Your database connection
require_once 'config.php';      // Your new config file for credentials

// Include PHPMailer classes
require_once 'vendor/autoload.php';

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Enable all error reporting for debugging (consider turning off in production)
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

$success_message = '';
$error_message = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {
    // Collect and sanitize form data
    $name = htmlspecialchars(trim($_POST['name'] ?? ''));
    $email = htmlspecialchars(trim($_POST['email'] ?? ''));
    // Get the country code and combine with the phone number
    $countryCode = htmlspecialchars(trim($_POST['countryCode'] ?? ''));
    $phoneNumber = htmlspecialchars(trim($_POST['phoneNumber'] ?? ''));
    $phone = $countryCode . $phoneNumber; // Combine for database and email

    $vehicle = htmlspecialchars(trim($_POST['vehicle'] ?? ''));
    $pickupLocation = htmlspecialchars(trim($_POST['pickupLocation'] ?? ''));
    $pickupDate = htmlspecialchars(trim($_POST['pickupDate'] ?? ''));
    $pickupTime = htmlspecialchars(trim($_POST['pickupTime'] ?? ''));
    $dropoffLocation = htmlspecialchars(trim($_POST['dropoffLocation'] ?? ''));
    $notes = htmlspecialchars(trim($_POST['notes'] ?? ''));

    // Basic validation
    if (empty($name) || empty($email) || empty($phoneNumber) || empty($vehicle) || empty($pickupLocation) || empty($pickupDate) || empty($pickupTime) || empty($dropoffLocation)) {
        $error_message = "Please fill in all required fields, including selecting a vehicle and your full phone number.";
    } else {
        try {
            // --- DUPLICATE SUBMISSION CHECK ---
            $checkStmt = $conn->prepare("SELECT submission_time FROM bookings WHERE email = ? OR phone = ? ORDER BY submission_time DESC LIMIT 1");
            if ($checkStmt === false) {
                throw new Exception("Duplicate check prepare failed: " . $conn->error);
            }
            $checkStmt->bind_param("ss", $email, $phone);
            $checkStmt->execute();
            $checkResult = $checkStmt->get_result();

            if ($checkResult->num_rows > 0) {
                $lastBooking = $checkResult->fetch_assoc();
                $lastSubmissionTime = strtotime($lastBooking['submission_time']);
                $currentTime = time();
                $timeDiff = $currentTime - $lastSubmissionTime; // Difference in seconds

                // 2 hours in seconds = 2 * 60 * 60 = 7200
                if ($timeDiff < 7200) {
                    $error_message = "A booking with this email or phone number was recently submitted. Please wait before submitting another request.";
                }
            }
            $checkStmt->close();

            // If there's an error message from the duplicate check, stop here
            if (!empty($error_message)) {
                // Skip the booking insertion and email sending
            } else {
                $stmt = $conn->prepare("INSERT INTO bookings (name, email, phone, vehicle, pickuplocation, pickupdate, pickuptime, dropofflocation, notes) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)");

                if ($stmt === false) {
                    throw new Exception("SQL prepare failed: " . $conn->error);
                }

                $stmt->bind_param("sssssssss", $name, $email, $phone, $vehicle, $pickupLocation, $pickupDate, $pickupTime, $dropoffLocation, $notes);

                if ($stmt->execute()) {
                    $success_message = "Thank you for your booking! We have received your request and will contact you shortly to confirm your reservation details.";
                    $bookingId = $stmt->insert_id; // Get the ID of the newly inserted booking

                    // --- PHPMailer Email Notification Logic for CUSTOMER ---
                    try {
                        $mailCustomer = new PHPMailer(true); // Enable exceptions for customer email
                        // Server settings from config.php
                        // $mailCustomer->SMTPDebug = SMTP::DEBUG_SERVER; // Re-enable for debugging if issues
                        $mailCustomer->isSMTP();
                        $mailCustomer->Host        = SMTP_HOST;
                        $mailCustomer->SMTPAuth    = true;
                        $mailCustomer->Username    = SMTP_USERNAME;
                        $mailCustomer->Password    = SMTP_PASSWORD; // Using constant from config.php
                        $mailCustomer->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mailCustomer->Port        = SMTP_PORT;

                        // Recipients for customer email
                        $mailCustomer->setFrom(SMTP_USERNAME, 'Luxury Limousine');
                        $mailCustomer->addAddress($email, $name); // Customer's email
                        $mailCustomer->addReplyTo(SMTP_USERNAME, 'Luxury Limousine');

                        // Content for customer email
                        $mailCustomer->isHTML(true);
                        $mailCustomer->Subject = "Your Luxury Transfer Booking Confirmation - Ref: #" . $bookingId;
                        $mailCustomer->Body    = "
                        <html>
                        <head>
                            <title>Your Booking Confirmation - Luxury Limousine</title>
                            <style>
                                body { font-family: 'Montserrat', sans-serif; background-color: #f4f4f4; color: #333; margin: 0; padding: 0; -webkit-text-size-adjust: 100%; -ms-text-size-adjust: 100%; }
                                .container { width: 100%; max-width: 600px; margin: 0 auto; background-color: #ffffff; padding: 20px; border-radius: 8px; box-shadow: 0 0 10px rgba(0, 0, 0, 0.1); }
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
                                    <p>Dear <span class='gold-text'>" . $name . "</span>,</p>
                                    <p>Thank you for choosing Luxury Limousine for your transfer needs! Your booking request has been successfully received.</p>
                                    <div class='booking-details'>
                                        <p style='margin-top:0; font-size: 16px; font-weight: bold;'>Your Booking Details:</p>
                                        <ul>
                                            <li><strong>Booking ID:</strong> <span class='gold-text'>#" . $bookingId . "</span></li>
                                            <li><strong>Vehicle:</strong> " . ucfirst($vehicle) . "</li>
                                            <li><strong>Pickup:</strong> " . $pickupLocation . " on " . date('M d, Y', strtotime($pickupDate)) . " at " . date('h:i A', strtotime($pickupTime)) . "</li>
                                            <li><strong>Dropoff:</strong> " . $dropoffLocation . "</li>
                                            <li><strong>Notes:</strong> " . (!empty($notes) ? $notes : 'N/A') . "</li>
                                        </ul>
                                    </div>
                                    <p>We are currently reviewing your request and will send you a final confirmation with all reservation details within **2 hours**.</p>
                                    <p>Should you have any urgent questions, please don't hesitate to reply to this email or call us directly at <a href='tel:+4527218077'><span class='gold-text'>+45 27 21 80 77</span></a>.</p>
                                    <p>We look forward to providing you with a comfortable and luxurious transfer experience.</p>
                                    <p>Sincerely,<br>The Luxury Limousine Team</p>
                                </div>
                                <div class='footer'>
                                    <p>&copy; " . date('Y') . " Luxury Limousine. All rights reserved.</p>
                                    <p><a href='mailto:" . SMTP_USERNAME . "'>" . SMTP_USERNAME . "</a> | +45 27 21 80 77</p>
                                </div>
                            </div>
                        </body>
                        </html>";
                        $mailCustomer->AltBody = "Dear " . $name . ",\n\n"
                                                 . "Thank you for choosing Luxury Limousine! Your booking request has been successfully received.\n\n"
                                                 . "Your Booking Details:\n"
                                                 . "Booking ID: #" . $bookingId . "\n"
                                                 . "Vehicle: " . ucfirst($vehicle) . "\n"
                                                 . "Pickup: " . $pickupLocation . " on " . date('M d, Y', strtotime($pickupDate)) . " at " . date('h:i A', strtotime($pickupTime)) . "\n"
                                                 . "Dropoff: " . $dropoffLocation . "\n"
                                                 . "Notes: " . (!empty($notes) ? $notes : 'N/A') . "\n\n"
                                                 . "We are currently reviewing your request and will send you a final confirmation with all reservation details within 2 hours.\n"
                                                 . "Should you have any urgent questions, please don't hesitate to reply to this email or call us directly at +45 27 21 80 77.\n\n"
                                                 . "Sincerely,\nThe Luxury Limousine Team";

                        $mailCustomer->send();
                    } catch (Exception $mailException) {
                        error_log("PHPMailer Error (Customer): " . $mailException->getMessage());
                        $error_message .= " <br>Could not send confirmation email."; // User-friendly message
                    }


                    // --- PHPMailer Email Notification Logic for ADMIN ---
                    try {
                        $mailAdmin = new PHPMailer(true); // Enable exceptions for admin email
                        // Server settings from config.php
                        // $mailAdmin->SMTPDebug = SMTP::DEBUG_SERVER; // Re-enable for debugging if issues
                        $mailAdmin->isSMTP();
                        $mailAdmin->Host        = SMTP_HOST;
                        $mailAdmin->SMTPAuth    = true;
                        $mailAdmin->Username    = SMTP_USERNAME;
                        $mailAdmin->Password    = SMTP_PASSWORD;
                        $mailAdmin->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
                        $mailAdmin->Port        = SMTP_PORT;

                        // Recipients for admin email
                        $mailAdmin->setFrom(SMTP_USERNAME, 'Luxury Limousine Booking System');
                        $mailAdmin->addAddress(ADMIN_EMAIL, 'Luxury Limousine Admin'); 
                        $mailAdmin->addReplyTo($email, $name); 
                        
                        // Content for admin email
                        $mailAdmin->isHTML(true);
                        $mailAdmin->Subject = "NEW Booking Received - ID: #" . $bookingId . " from " . $name;
                        $mailAdmin->Body    = "
                        <html>
                        <body>
                            <div style='font-family: Arial, sans-serif; max-width: 600px; margin: auto; border: 1px solid #ddd; padding: 20px;'>
                                <h2 style='color: #D4AF37;'>New Booking Alert</h2>
                                <p>A new booking has been submitted.</p>
                                <h3>Details:</h3>
                                <ul>
                                    <li><strong>Booking ID:</strong> #" . $bookingId . "</li>
                                    <li><strong>Customer:</strong> " . $name . "</li>
                                    <li><strong>Email:</strong> <a href='mailto:" . $email . "'>" . $email . "</a></li>
                                    <li><strong>Phone:</strong> <a href='tel:" . $phone . "'>" . $phone . "</a></li>
                                    <li><strong>Vehicle:</strong> " . ucfirst($vehicle) . "</li>
                                    <li><strong>Pickup:</strong> " . $pickupLocation . "</li>
                                    <li><strong>Date & Time:</strong> " . date('M d, Y', strtotime($pickupDate)) . " at " . date('h:i A', strtotime($pickupTime)) . "</li>
                                    <li><strong>Dropoff:</strong> " . $dropoffLocation . "</li>
                                    <li><strong>Notes:</strong> " . (!empty($notes) ? $notes : 'N/A') . "</li>
                                </ul>
                                <p style='text-align: center; margin-top: 20px;'>
                                    <a href='" . ADMIN_DASHBOARD_URL . "' style='background-color: #D4AF37; color: #000; padding: 12px 25px; text-decoration: none; border-radius: 5px; font-weight: bold;'>Go to Admin Dashboard</a>
                                </p>
                            </div>
                        </body>
                        </html>";
                        $mailAdmin->AltBody = "New Booking Received!\n\n"
                                              . "Booking ID: #" . $bookingId . "\n"
                                              . "Customer: " . $name . "\n"
                                              . "Email: " . $email . "\n"
                                              . "Phone: " . $phone . "\n"
                                              . "Vehicle: " . ucfirst($vehicle) . "\n"
                                              . "Details: View in dashboard: " . ADMIN_DASHBOARD_URL;

                        $mailAdmin->send();
                    } catch (Exception $mailException) {
                        error_log("PHPMailer Error (Admin): " . $mailException->getMessage());
                        // Don't show this error to the customer, just log it.
                    }

                } else {
                    throw new Exception("SQL execute failed: " . $stmt->error);
                }

                $stmt->close();
            } // End of else for duplicate check error
        } catch (Exception $e) {
            $error_message = "Booking failed. Please try again later. " . $e->getMessage(); // Added for more detailed debug
            error_log("Booking Error: " . $e->getMessage()); // Log detailed error for admin
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
    <title>Luxury Transfer Booking</title>
    <link rel="preconnect" href="https://cdn.tailwindcss.com">
    <link rel="preconnect" href="https://cdnjs.cloudflare.com">
    
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #000; /* Keep the overall body background black for contrast */
            color: #fff;
        }
        
        .monument {
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        
        .gold {
            color: #A68D5a;
        }
        
        .bg-gold {
            background-color: #A68D5a;
        }
        
        .booking-section {
            background: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), 
                        url('https://images.pexels.com/photos/28380943/pexels-photo-28380943/free-photo-of-luxury-cars-showroom-black-and-white-photo.jpeg?auto=compress&cs=tinysrgb&w=1260&h=750&dpr=2');
            background-size: cover;
            background-position: center;
            min-height: 100vh;
        }
        
        /* White Theme for Form */
        .form-container-white {
            background-color: rgba(255, 255, 255, 0.95); /* Semi-transparent white */
            color: #333; /* Dark text for contrast on white */
            box-shadow: 0 0 20px rgba(0, 0, 0, 0.2);
        }

        .form-input-white {
            background-color: #f0f0f0; /* Light gray input background */
            border: 1px solid #ccc; /* Lighter border */
            color: #333; /* Dark text in inputs */
            padding: 0.75rem 1rem; /* Consistent padding */
            border-radius: 0.25rem; /* Consistent border radius */
        }
        
        .form-input-white:focus {
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            background-color: #fff; /* White background on focus */
        }
        
        .form-label-white {
            color: #A68D5a; /* Keep gold for labels */
            font-weight: 600;
        }
        
        /* Custom styles for the country code select */
        .country-code-select {
            -webkit-appearance: none;
            -moz-appearance: none;
            appearance: none;
            background-repeat: no-repeat;
            background-position: right 0.75rem center;
            background-size: 1.5em 1.5em;
            background-color: #f0f0f0; /* Light gray input background */
            border: 1px solid #ccc; /* Lighter border */
            color: #333; /* Dark text in inputs */
            padding: 0.75rem 1rem;
            padding-right: 2.5rem; 
            border-radius: 0.25rem;
            font-family: 'Montserrat', sans-serif;
            cursor: pointer;
        }
        .country-code-select:focus {
            border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            background-color: #fff;
        }
        .country-code-select::-webkit-scrollbar {
            width: 8px;
        }
        .country-code-select::-webkit-scrollbar-track {
            background: #f1f1f1;
            border-radius: 10px;
        }
        .country-code-select::-webkit-scrollbar-thumb {
            background: #888;
            border-radius: 10px;
        }
        .country-code-select::-webkit-scrollbar-thumb:hover {
            background: #555;
        }

        .btn-hover {
            transition: all 0.3s ease;
        }
        
        .btn-hover:hover {
            transform: translateY(-2px);
        }
        
        .vehicle-option-white {
            background-color: #f8f8f8; /* Light background for options */
            border: 1px solid #ddd;
            transition: all 0.3s ease;
            cursor: pointer;
            border-radius: 0.25rem;
        }
        
        .vehicle-option-white:hover {
            border-color: #D4AF37;
            transform: translateY(-3px);
            background-color: #f0f0f0;
        }
        
        .vehicle-option-white.selected {
            border-color: #D4AF37;
            background-color: rgba(212, 175, 55, 0.1);
            box-shadow: 0 0 10px rgba(212, 175, 55, 0.3);
        }

        .vehicle-option-white .vehicle-icon-bg {
            background-color: #e0e0e0;
            border-radius: 0.25rem;
        }
        
        .datetime-input::-webkit-calendar-picker-indicator {
            filter: invert(0.5); 
        }

        .section-title-white {
            color: #A68D5a;
            border-bottom-color: #e0e0e0;
        }

        /* Success/Error Message Styles */
        .message-container {
            position: fixed;
            top: 20px;
            right: 20px;
            z-index: 1000;
            max-width: 400px;
            width: 90%;
        }

        .message-toast {
            background: #fff;
            color: #333;
            border-left-width: 4px;
            border-radius: 0.25rem;
            padding: 1.5rem;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.1);
            display: flex;
            align-items: flex-start;
            transform: translateX(120%);
            transition: transform 0.4s cubic-bezier(0.68, -0.55, 0.265, 1.55);
            opacity: 0;
            pointer-events: none;
        }
        
        .message-toast.success {
            border-left-color: #28a745;
        }
        .message-toast.error {
            border-left-color: #dc3545;
        }

        .message-toast.active {
            transform: translateX(0);
            opacity: 1;
            pointer-events: auto;
        }

        .message-icon {
            font-size: 1.5rem;
            margin-right: 1rem;
        }
        
        .message-icon.success { color: #28a745; }
        .message-icon.error { color: #dc3545; }

        .message-content { flex: 1; }

        .message-title {
            font-size: 1.25rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .message-title.success { color: #28a745; }
        .message-title.error { color: #dc3545; }

        .message-text {
            color: #555;
            line-height: 1.5;
            margin-bottom: 0.5rem;
        }

        .message-close {
            color: #aaa;
            cursor: pointer;
            transition: color 0.2s ease;
            margin-left: 0.5rem;
        }
        .message-close:hover { color: #333; }
    </style>
</head>
<body>
    <main>
        <section class="booking-section flex items-center justify-center py-20">
            <div class="max-w-6xl mx-auto px-6 w-full">
                <div class="form-container-white p-8 md:p-12 rounded-lg shadow-xl">
                    <div class="text-center mb-10">
                        <h1 class="monument text-3xl md:text-5xl font-bold mb-4 tracking-tight text-gray-800">BOOK YOUR <span class="gold">LUXURY TRANSFER</span></h1>
                        <p class="text-gray-600 max-w-2xl mx-auto">Complete the form below to reserve your premium Mercedes-Benz electric vehicle for a seamless Copenhagen experience</p>
                    </div>
                    
                    <form id="bookingForm" method="POST" action="" class="grid grid-cols-1 md:grid-cols-2 gap-8">
                        <div class="space-y-6">
                            <h2 class="monument text-xl font-bold mb-6 section-title-white border-b pb-2">PERSONAL INFORMATION</h2>
                            
                            <div>
                                <label for="name" class="form-label-white block mb-2">Your Name</label>
                                <input type="text" id="name" name="name" required class="form-input-white w-full">
                            </div>
                            
                            <div>
                                <label for="email" class="form-label-white block mb-2">Your Email</label>
                                <input type="email" id="email" name="email" required class="form-input-white w-full">
                            </div>
                            
                            <div>
                                <label for="phoneNumber" class="form-label-white block mb-2">Your Phone</label>
                                <div class="flex items-center space-x-2">
                                    <select id="country-code" name="countryCode" class="country-code-select flex-shrink-0 w-32">
                                        </select>
                                    <input type="tel" id="phoneNumber" name="phoneNumber" placeholder="e.g., 12345678" required class="form-input-white w-full">
                                </div>
                            </div>
                            
                            <div>
                                <label class="form-label-white block mb-2">Select Car</label>
                                <div class="space-y-4">
                                    <div class="vehicle-option-white p-4" data-vehicle="Mercedes-Benz EQE 350+">
                                        <div class="flex items-center">
                                            <div class="w-16 h-16 vehicle-icon-bg flex items-center justify-center mr-4">
                                                <i class="fas fa-car text-xl gold"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-800">Mercedes-Benz EQE 350+</h4>
                                                <p class="text-gray-600 text-sm">Executive Sedan (4 passengers)</p>
                                            </div>
                                        </div>
                                    </div>
                                    
                                    <div class="vehicle-option-white p-4" data-vehicle="Mercedes-Benz EQV 300">
                                        <div class="flex items-center">
                                            <div class="w-16 h-16 vehicle-icon-bg flex items-center justify-center mr-4">
                                                <i class="fas fa-van-shuttle text-xl gold"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-bold text-gray-800">Mercedes-Benz EQV 300</h4>
                                                <p class="text-gray-600 text-sm">VIP Van (7 passengers)</p>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                                <input type="hidden" id="selectedVehicle" name="vehicle" required>
                            </div>
                        </div>
                        
                        <div class="space-y-6">
                            <div>
                                <h2 class="monument text-xl font-bold mb-6 section-title-white border-b pb-2">PICKUP INFORMATION</h2>
                                
                                <div class="mb-4">
                                    <label for="pickupLocation" class="form-label-white block mb-2">Pickup Location</label>
                                    <input type="text" id="pickupLocation" name="pickupLocation" required class="form-input-white w-full" placeholder="Address, airport terminal, etc.">
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                    <div>
                                        <label for="pickupDate" class="form-label-white block mb-2">Pickup Date</label>
                                        <input type="date" id="pickupDate" name="pickupDate" required class="form-input-white w-full datetime-input">
                                    </div>
                                    
                                    <div>
                                        <label for="pickupTime" class="form-label-white block mb-2">Pickup Time</label>
                                        <input type="time" id="pickupTime" name="pickupTime" required class="form-input-white w-full datetime-input">
                                    </div>
                                </div>
                            </div>
                            
                            <div>
                                <h2 class="monument text-xl font-bold mb-6 section-title-white border-b pb-2">DROPOFF INFORMATION</h2>
                                <div class="mb-4">
                                    <label for="dropoffLocation" class="form-label-white block mb-2">Dropoff Location</label>
                                    <input type="text" id="dropoffLocation" name="dropoffLocation" required class="form-input-white w-full" placeholder="Address, airport terminal, etc.">
                                </div>
                            </div>
                            
                            <div>
                                <label for="notes" class="form-label-white block mb-2">Additional Notes</label>
                                <textarea id="notes" name="notes" rows="3" class="form-input-white w-full" placeholder="Special requests, flight number, etc."></textarea>
                            </div>
                            
                            <div class="pt-4">
                                <button type="submit" class="bg-gold text-black px-8 py-4 rounded-sm font-bold text-lg hover:bg-opacity-90 transition btn-hover uppercase tracking-wider w-full">
                                    <i class="fas fa-paper-plane mr-2"></i> CONFIRM BOOKING
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>
        </section>

        <div class="message-container">
            <div id="successMessage" class="message-toast success">
                <div class="message-icon success"><i class="fas fa-check-circle"></i></div>
                <div class="message-content">
                    <h4 class="message-title success">Booking Confirmed!</h4>
                    <p class="message-text" id="successMessageText"></p>
                </div>
                <div class="message-close" id="closeSuccessMessage"><i class="fas fa-times"></i></div>
            </div>
        </div>

        <div class="message-container" style="top: 150px;"> <div id="errorMessage" class="message-toast error">
                <div class="message-icon error"><i class="fas fa-times-circle"></i></div>
                <div class="message-content">
                    <h4 class="message-title error">Booking Failed!</h4>
                    <p class="message-text" id="errorMessageText"></p>
                </div>
                <div class="message-close" id="closeErrorMessage"><i class="fas fa-times"></i></div>
            </div>
        </div>

        <script type="module">
            import { countries as rawCountries } from './countriesData.js';

            document.addEventListener('DOMContentLoaded', () => {
                // 1. Clean the data
                const uniqueAndValidCountries = Object.values(rawCountries.reduce((acc, country) => {
                    // Only add countries that have a name and a code
                    if (country.name && country.code) {
                        acc[country.name] = country;
                    }
                    return acc;
                }, {}));

                // 2. Sort the clean data
                uniqueAndValidCountries.sort((a, b) => a.name.localeCompare(b.name));

                // 3. Populate the dropdown
                const countryCodeSelect = document.getElementById('country-code');
                uniqueAndValidCountries.forEach(country => {
                    const option = document.createElement('option');
                    option.value = country.code;
                    option.textContent = `${country.flag} ${country.name} (${country.code})`;
                    countryCodeSelect.appendChild(option);
                });

                // Set default to Denmark
                countryCodeSelect.value = "+45";

                // Vehicle selection
                document.querySelectorAll('.vehicle-option-white').forEach(option => {
                    option.addEventListener('click', function() {
                        document.querySelectorAll('.vehicle-option-white').forEach(opt => opt.classList.remove('selected'));
                        this.classList.add('selected');
                        document.getElementById('selectedVehicle').value = this.dataset.vehicle;
                    });
                });

                // Set minimum date to today
                const today = new Date().toISOString().split('T')[0];
                document.getElementById('pickupDate').min = today;

                // Toast message handlers
                const successToast = document.getElementById('successMessage');
                const errorToast = document.getElementById('errorMessage');
                const successText = document.getElementById('successMessageText');
                const errorText = document.getElementById('errorMessageText');

                function showToast(toastEl, textEl, message) {
                    textEl.innerHTML = message; // Use innerHTML to render <br> tags from PHP
                    toastEl.classList.add('active');
                    setTimeout(() => {
                        toastEl.classList.remove('active');
                    }, 6000); // Hide after 6 seconds
                }

                document.getElementById('closeSuccessMessage').addEventListener('click', () => successToast.classList.remove('active'));
                document.getElementById('closeErrorMessage').addEventListener('click', () => errorToast.classList.remove('active'));


                // Display messages from PHP on page load
                <?php if (!empty($success_message)): ?>
                    showToast(successToast, successText, "<?php echo addslashes($success_message); ?>");
                <?php elseif (!empty($error_message)): ?>
                    showToast(errorToast, errorText, "<?php echo addslashes($error_message); ?>");
                <?php endif; ?>
            });
        </script>
    </main>
</body>
</html>