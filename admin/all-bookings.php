<?php
// all-bookings.php
session_start();

// -------------------------------------------------------------------------------------
// IMPORTANT: VERIFY THIS PATH IS CORRECT!
require_once 'auth_check.php'; 
// -------------------------------------------------------------------------------------

// --- BEGIN: AJAX Booking Status Update Handler (for status dropdowns) ---
// This AJAX handler is part of all-bookings.php and processes POST requests to this same file.
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'update_booking_status') {
    header('Content-Type: application/json');
    
    // --- BEGIN: ADDED FOR EMAIL FUNCTIONALITY ---
    // Include our new email function file and the configuration file.
    // Assumes config.php is in the root 'BOOKING' folder, and email_functions.php is in 'admin'.
    require_once __DIR__ . '/../config.php';
    require_once __DIR__ . '/email_functions.php';
    // --- END: ADDED FOR EMAIL FUNCTIONALITY ---
    
    // -------------------------------------------------------------------------------------
    // IMPORTANT: VERIFY THIS PATH IS CORRECT!
    require_once '../db_connect.php'; 
    // -------------------------------------------------------------------------------------

    $response = ['success' => false, 'message' => 'An error occurred processing your request.'];
    $booking_id = isset($_POST['id']) ? filter_var($_POST['id'], FILTER_VALIDATE_INT) : null;
    $new_status = $_POST['status'] ?? null;
    
    $valid_statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

    if ($booking_id === null || $booking_id === false) {
        $response['message'] = 'Invalid Booking ID provided.';
        error_log("AJAX Update (all-bookings.php): Invalid Booking ID. Received: " . ($_POST['id'] ?? 'NULL'));
    } elseif ($new_status === null || !in_array($new_status, $valid_statuses)) {
        $response['message'] = 'Invalid Status value provided.';
        error_log("AJAX Update (all-bookings.php): Invalid Status. Received: " . ($new_status ?? 'NULL') . " for Booking ID: " . $booking_id);
    } else {
        $conn_update = new mysqli($servername, $username, $password, $dbname);

        if ($conn_update->connect_error) {
            $response['message'] = "Database connection failed. Please try again later.";
            error_log("AJAX Update (all-bookings.php): DB connection error - " . $conn_update->connect_error);
        } else {
            $conn_update->set_charset("utf8mb4");
            try {
                $sql_update = "UPDATE bookings SET status = ? WHERE id = ?";
                $stmt_update = $conn_update->prepare($sql_update);

                if ($stmt_update === false) {
                    $response['message'] = "Failed to prepare the update statement. Please contact support.";
                    error_log("AJAX Update (all-bookings.php): SQL prepare error - " . $conn_update->error . " (Query: " . $sql_update . ")");
                } else {
                    $stmt_update->bind_param("si", $new_status, $booking_id);
                    if ($stmt_update->execute()) {
                        if ($stmt_update->affected_rows > 0) {
                            
                            // --- BEGIN: MODIFIED FOR EMAIL FUNCTIONALITY ---
                            
                            // The status was updated. Now, fetch the full booking details to send the email.
                            $stmt_fetch = $conn_update->prepare("SELECT * FROM bookings WHERE id = ?");
                            $booking_details = null;
                            if ($stmt_fetch) {
                                $stmt_fetch->bind_param("i", $booking_id);
                                $stmt_fetch->execute();
                                $result_fetch = $stmt_fetch->get_result();
                                if ($result_fetch->num_rows > 0) {
                                    // We have the details, let's use them
                                    $booking_details = $result_fetch->fetch_assoc();
                                }
                                $stmt_fetch->close();
                            }

                            $response['success'] = true;
                            // Set the base success message for the admin
                            $response['message'] = "Booking #{$booking_id} status successfully updated to {$new_status}.";

                            if ($booking_details) {
                                // Attempt to send the email using our function
                                $emailSent = sendBookingStatusEmail($booking_details);
                                
                                if ($emailSent) {
                                    // If email sent, add a note to the admin's success message
                                    $response['message'] .= " A confirmation email was sent.";
                                } else {
                                    // If email failed, add a note and log the error for debugging
                                    $response['message'] .= " However, the confirmation email failed to send.";
                                    error_log("AJAX Email Error (all-bookings.php): Failed to send status update email for Booking ID: {$booking_id}");
                                }
                            } else {
                                // This is unlikely if the UPDATE worked, but a good safeguard
                                $response['message'] .= " Could not retrieve details to send an email.";
                                error_log("AJAX Email Error (all-bookings.php): Could not fetch details for Booking ID: {$booking_id} after successful update.");
                            }
                            
                            // --- END: MODIFIED FOR EMAIL FUNCTIONALITY ---

                        } else {
                            // This 'else' block handles the case where the status was already set, no changes needed.
                            $stmt_check = $conn_update->prepare("SELECT status FROM bookings WHERE id = ?");
                            if($stmt_check) {
                                $stmt_check->bind_param("i", $booking_id);
                                $stmt_check->execute();
                                $result_check = $stmt_check->get_result();
                                if ($result_check->num_rows > 0) {
                                    $current_booking = $result_check->fetch_assoc();
                                    if ($current_booking['status'] == $new_status) {
                                        $response['success'] = true;
                                        $response['message'] = "Booking #{$booking_id} status was already {$new_status}.";
                                    } else {
                                        $response['message'] = "Booking #{$booking_id} status could not be updated (unknown reason, booking exists).";
                                        error_log("AJAX Update (all-bookings.php): Booking ID {$booking_id} exists but status not changed from {$current_booking['status']} to {$new_status} and affected_rows was 0.");
                                    }
                                } else {
                                    $response['message'] = "Booking #{$booking_id} not found. Status update failed.";
                                    error_log("AJAX Update (all-bookings.php): Booking ID {$booking_id} not found during update attempt.");
                                }
                                $stmt_check->close();
                            } else {
                                 $response['message'] = "Failed to verify update. Please check the booking.";
                                 error_log("AJAX Update (all-bookings.php): Failed to prepare check statement for Booking ID {$booking_id}. Error: " . $conn_update->error);
                            }
                        }
                    } else {
                        $response['message'] = "Failed to execute the status update. Please try again.";
                        error_log("AJAX Update (all-bookings.php): SQL execute error for Booking ID {$booking_id} - " . $stmt_update->error);
                    }
                    $stmt_update->close();
                }
            } catch (Exception $e) {
                $response['message'] = "A server error occurred during status update: " . $e->getMessage();
                error_log("AJAX Update (all-bookings.php): Exception for Booking ID {$booking_id} - " . $e->getMessage());
            }
            $conn_update->close();
        }
    }
    echo json_encode($response);
    exit; 
}
// --- END: AJAX Booking Status Update Handler ---

ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your_project/php-page-errors.log'); // Set a valid path

require_once '../db_connect.php'; 

$bookings = [];
$message = '';
$errorMessage = '';
$statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

$search_query = '';
if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $search_query = trim($_GET['q']);
}

$items_per_page = 15;
$current_page = isset($_GET['page']) && is_numeric($_GET['page']) ? (int)$_GET['page'] : 1;
if ($current_page < 1) {
    $current_page = 1;
}
$offset = ($current_page - 1) * $items_per_page;
$total_bookings = 0;
$total_pages = 1;

$searchable_fields = ['id', 'name', 'email', 'phone', 'vehicle', 'pickuplocation', 'dropofflocation', 'status'];

$db_conn_page_load = new mysqli($servername, $username, $password, $dbname);

if ($db_conn_page_load->connect_error) {
    $errorMessage = "Database connection failed for page load: " . $db_conn_page_load->connect_error;
    error_log("Page Load DB Connection Error (all-bookings.php): " . $errorMessage); 
    $message = "The page is currently unable to retrieve booking data. Please try again later.";
} else {
    $db_conn_page_load->set_charset("utf8mb4");
    try {
        $where_conditions_sql = "";
        $search_params = [];
        $search_param_types = "";

        if (!empty($search_query)) {
            $search_query_like = "%" . $search_query . "%";
            $temp_where_conditions = [];
            foreach ($searchable_fields as $field) {
                $temp_where_conditions[] = "`$field` LIKE ?";
                $search_params[] = $search_query_like;
                $search_param_types .= "s";
            }
            if (!empty($temp_where_conditions)) {
                $where_conditions_sql = " WHERE (" . implode(" OR ", $temp_where_conditions) . ")";
            }
        }

        $sql_count = "SELECT COUNT(*) as total FROM bookings" . $where_conditions_sql;
        $stmt_count = $db_conn_page_load->prepare($sql_count);

        if ($stmt_count === false) {
            throw new Exception("SQL count prepare failed: " . $db_conn_page_load->error . " (Query: " . $sql_count . ")");
        }

        if (!empty($search_params)) {
            $bind_count_names = [$search_param_types];
            for ($i = 0; $i < count($search_params); $i++) {
                $bind_name_count = 'param_count' . $i;
                $$bind_name_count = $search_params[$i];
                $bind_count_names[] = &$$bind_name_count;
            }
            call_user_func_array([$stmt_count, 'bind_param'], $bind_count_names);
        }

        if (!$stmt_count->execute()) {
             throw new Exception("SQL count execute failed: " . $stmt_count->error);
        }
        $result_count = $stmt_count->get_result();
        $total_bookings_row = $result_count->fetch_assoc();
        $total_bookings = $total_bookings_row ? $total_bookings_row['total'] : 0;
        $stmt_count->close();
        
        $total_pages = $items_per_page > 0 ? ceil($total_bookings / $items_per_page) : 0;
        if ($total_pages == 0 && $total_bookings > 0) $total_pages = 1;
        if ($total_pages == 0) $total_pages = 1;


        if ($current_page > $total_pages && $total_pages > 0) {
            $current_page = $total_pages;
            $offset = ($current_page - 1) * $items_per_page;
        }
         if ($offset < 0) $offset = 0;


        $sql_select = "SELECT id, name, email, phone, vehicle, pickuplocation, pickupdate, pickuptime, dropofflocation, notes, created_at, status FROM bookings";
        $sql_select .= $where_conditions_sql;
        $sql_select .= " ORDER BY created_at DESC LIMIT ? OFFSET ?";

        $final_params = $search_params;
        $final_param_types = $search_param_types;

        $final_params[] = $items_per_page; 
        $final_param_types .= "i";
        $final_params[] = $offset;        
        $final_param_types .= "i";

        $stmt = $db_conn_page_load->prepare($sql_select);

        if ($stmt === false) {
            throw new Exception("SQL select prepare failed: " . $db_conn_page_load->error . " (Query: " . $sql_select . ")");
        }
        
        if (!empty($final_param_types)) { 
            $bind_names = [$final_param_types];
            for ($i = 0; $i < count($final_params); $i++) {
                $bind_name = 'param' . $i;
                $$bind_name = $final_params[$i];
                $bind_names[] = &$$bind_name;
            }
            call_user_func_array([$stmt, 'bind_param'], $bind_names);
        }

        if ($stmt->execute()) {
            $result = $stmt->get_result();
            if ($result->num_rows > 0) {
                while ($row = $result->fetch_assoc()) {
                    $bookings[] = $row;
                }
            } else {
                 if ($total_bookings == 0 && empty($search_query)) {
                    $message = "No bookings found yet.";
                } elseif ($total_bookings == 0 && !empty($search_query)) {
                    $message = "No bookings found matching your search for '" . htmlspecialchars($search_query) . "'.";
                } elseif ($current_page > 1 && $total_bookings > 0) {
                     $message = "No bookings found for this page. Try a previous page.";
                }
            }
            $stmt->close();
        } else {
            throw new Exception("SQL select execute failed: " . $stmt->error);
        }
    } catch (Exception $e) { 
        $errorMessage = "Error fetching bookings for page display: " . $e->getMessage();
        error_log("Page Load Data Fetch Error (all-bookings.php): " . $errorMessage); 
        $message = "There was an error retrieving booking information for the page. Please try again.";
    }
    $db_conn_page_load->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>All Bookings - Admin Panel</title> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        body { font-family: 'Montserrat', sans-serif; background-color: #000; color: #fff; min-height: 100vh; }
        .monument { font-family: 'Montserrat', sans-serif; font-weight: 800; letter-spacing: -0.5px; }
        .gold { color: #D4AF37; } .bg-gold { background-color: #D4AF37; }
        
        .btn { padding: 0.75rem 1.5rem; border-radius: 0.25rem; font-weight: 600; letter-spacing: 0.5px; transition: all 0.3s ease; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; }
        .btn-primary { background-color: #D4AF37; color: #000; }
        .btn-primary:hover { background-color: rgba(212, 175, 55, 0.9); transform: translateY(-2px); }
        .btn-primary[disabled] { background-color: #7d671f; color: #333; cursor: not-allowed; opacity: 0.6; }
        .btn-secondary { background-color: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-secondary:hover { background-color: rgba(255, 255, 255, 0.2); transform: translateY(-2px); }
        .btn-secondary[disabled] { opacity: 0.5; cursor: default; }


        .form-input { width: 100%; padding: 0.75rem 1rem; border-radius: 0.25rem; background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); color: white; transition: all 0.3s ease; margin-bottom: 1rem; }
        .form-input:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); background-color: rgba(255, 255, 255, 0.15); }
        .table-auto { border-collapse: collapse; width: 100%; }
        .table-auto th, .table-auto td { padding: 12px 15px; text-align: left; border-bottom: 1px solid rgba(255, 255, 255, 0.1); }
        .table-auto th { background-color: rgba(212, 175, 55, 0.1); color: #D4AF37; font-weight: 700; text-transform: uppercase; font-size: 0.85rem; }
        .table-auto tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .table-auto td { color: rgba(255, 255, 255, 0.8); font-size: 0.9rem; }
        .booking-card { background-color: #1a1a1a; border: 1px solid rgba(212, 175, 55, 0.2); }
        .booking-card p { word-break: break-all; }
        .status-Pending { color: orange; } .status-Confirmed { color: #28a745; } .status-Completed { color: #17a2b8; } .status-Cancelled { color: #dc3545; }
        
        select.status-select.status-Pending { color: orange !important; } 
        select.status-select.status-Confirmed { color: #28a745 !important; } 
        select.status-select.status-Completed { color: #17a2b8 !important; } 
        select.status-select.status-Cancelled { color: #dc3545 !important; }

        .status-select { background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2); padding: 6px 10px; border-radius: 0.25rem; cursor: pointer; -webkit-appearance: none; -moz-appearance: none; appearance: none; background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23D4AF37%22%20d%3D%22M287%2C197.8L156.4%2C67.2c-4.4-4.4-11.5-4.4-15.9%2C0L5.4%2C197.8c-4.4%2C4.4-4.4%2C11.5%2C0%2C15.9l16.1%2C16.1c4.4%2C4.4%2C11.5%2C4.4%2C15.9%2C0L148.5%2C128l111%2C111c4.4%2C4.4%2C11.5%2C4.4%2C15.9%2C0l16.1-16.1c4.4-4.4%2C4.4-11.6%2C0-16z%22%2F%3E%3C%2Fsvg%3E'); background-repeat: no-repeat; background-position: right 0.7em top 50%, 0 0; background-size: 0.65em auto, 100%; width: 100%; }
        .status-select:focus { outline: none; border-color: #D4AF37; box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2); }
        .status-select option { background-color: #1a1a1a; color: #fff; }
        .status-select option.status-Pending { color: orange; } 
        .status-select option.status-Confirmed { color: #28a745; } 
        .status-select option.status-Completed { color: #17a2b8; } 
        .status-select option.status-Cancelled { color: #dc3545; }

        .notification-container { position: fixed; top: 20px; right: 20px; z-index: 1050; max-width: 350px; width: 90%; }
        .notification { background-color: #1a1a1a; border-radius: 0.25rem; padding: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.3); display: flex; align-items: center; opacity: 0; transform: translateX(120%); transition: transform 0.4s ease-out, opacity 0.4s ease-out; margin-bottom: 10px; }
        .notification.active { opacity: 1; transform: translateX(0); }
        .notification.success { border-left: 4px solid #28a745; } .notification.error { border-left: 4px solid #dc3545; } .notification.info { border-left: 4px solid #17a2b8; }
        .notification-icon { font-size: 1.5rem; margin-right: 0.75rem; } .notification-content { flex-grow: 1; } .notification-title { font-weight: 700; margin-bottom: 0.25rem; } .notification-message { font-size: 0.875rem; color: #ccc; }
        .notification-close { margin-left: 0.75rem; cursor: pointer; color: #aaa; } .notification-close:hover { color: #fff; }
        
        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.7); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background-color: #1a1a1a; padding: 2.5rem; border-radius: 0.5rem; max-width: 600px; width: 90%; max-height: 90vh; overflow-y: auto; transform: translateY(-20px); transition: transform 0.3s ease-in-out; border: 1px solid rgba(212, 175, 55, 0.3); position: relative; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-close-btn { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; color: #aaa; cursor: pointer; transition: color 0.2s ease; }
        .modal-close-btn:hover { color: #fff; }
        .modal-detail-row { display: flex; align-items: flex-start; margin-bottom: 0.75rem; line-height: 1.4; }
        .modal-detail-row span:first-child { font-weight: 600; color: #D4AF37; flex-shrink: 0; width: 120px; margin-right: 1rem; }
        .copyable-field { color: rgba(255, 255, 255, 0.9); flex-grow: 1; word-break: break-word; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: background-color 0.2s ease; }
        .copyable-field:hover { background-color: rgba(212, 175, 55, 0.1); }
        .copyable-field.notes-content { white-space: pre-wrap; }
        
        #newBookingAlertModal .modal-content { max-width: 450px; padding: 2.5rem; border: 2px solid #D4AF37; box-shadow: 0 0 30px rgba(212, 175, 55, 0.5); }
        
        .pagination-container a, .pagination-container span { margin: 0 0.25rem; min-width: 40px; text-align: center; }
        .pagination-container span.current-page-indicator { background-color: #D4AF37; color: #000; padding: 0.75rem 1rem; border-radius: 0.25rem; font-weight: 600; letter-spacing: 0.5px; display: inline-flex; align-items: center; justify-content: center; }
    </style>
</head>
<body class="flex flex-col">
    
    <?php require_once 'admin_header.php'; // ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h2 class="monument text-3xl font-bold mb-6 text-center">All <span class="gold">Bookings</span></h2>

        <form action="all-bookings.php" method="GET" class="mb-8 max-w-2xl mx-auto"> <div class="flex shadow-md rounded-md">
                <input type="text" name="q" placeholder="Search ID, Name, Email, Phone, Vehicle, Location, Status..."
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       class="form-input !mb-0 flex-grow !rounded-r-none">
                <button type="submit" class="btn btn-primary !rounded-l-none">
                    <i class="fas fa-search mr-1 md:mr-2"></i><span class="hidden md:inline">Search</span>
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="all-bookings.php" class="btn btn-secondary ml-2 !px-3 md:!px-6" title="Clear Search"> <i class="fas fa-times"></i> <span class="hidden md:inline ml-1">Clear</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>

        <?php if (!empty($message) && empty($bookings) && empty($errorMessage)): ?>
             <div class="bg-gray-800 p-4 rounded-md text-center text-gray-300 mb-6">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php elseif (!empty($errorMessage)): ?> <div class="bg-red-700 bg-opacity-50 border border-red-700 p-4 rounded-md text-white mb-6">
                <p class="font-semibold">An Error Occurred:</p>
                <p><?php echo htmlspecialchars($message); ?></p>
                <?php if (ini_get('display_errors') === '0'): ?>
                     <p class="text-xs mt-2">Further details have been logged for the administrator.</p>
                <?php endif; ?>
            </div>
        <?php endif; ?>

        <?php if (!empty($bookings)): ?>
            <div class="hidden md:block overflow-x-auto bg-gray-900 rounded-lg shadow-md mb-8">
                <table class="table-auto min-w-full">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Vehicle</th><th>Pickup Location</th><th>Dropoff Location</th>
                            <th>Date</th><th>Time</th><th>Notes</th><th class="min-w-[170px]">Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
                                <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($booking['vehicle'])); ?></td>
                                <td class="max-w-[200px] overflow-hidden overflow-ellipsis whitespace-nowrap" title="<?php echo htmlspecialchars($booking['pickuplocation']); ?>"><?php echo htmlspecialchars($booking['pickuplocation']); ?></td>
                                <td class="max-w-[200px] overflow-hidden overflow-ellipsis whitespace-nowrap" title="<?php echo htmlspecialchars($booking['dropofflocation']); ?>"><?php echo htmlspecialchars($booking['dropofflocation']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['pickupdate'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($booking['pickuptime'])); ?></td>
                                <td class="whitespace-nowrap overflow-hidden overflow-ellipsis max-w-xs" title="<?php echo htmlspecialchars($booking['notes']); ?>">
                                    <?php echo !empty($booking['notes']) ? htmlspecialchars(substr($booking['notes'], 0, 30)) . (strlen($booking['notes']) > 30 ? '...' : '') : '-'; ?>
                                </td>
                                <td class="min-w-[170px]">
                                    <select class="status-select w-full status-<?php echo htmlspecialchars(str_replace(' ', '-', $booking['status'])); ?>" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
                                        <?php foreach ($statuses as $status_option): ?>
                                            <option value="<?php echo htmlspecialchars($status_option); ?>"
                                                <?php echo ($booking['status'] == $status_option) ? 'selected' : ''; ?>
                                                class="status-<?php echo htmlspecialchars(str_replace(' ', '-', $status_option)); ?>">
                                                <?php echo htmlspecialchars($status_option); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                </td>
                                <td class="whitespace-nowrap">
                                    <a href="#" class="text-blue-400 hover:text-blue-600 mr-2 view-details-btn" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>" title="View Details"><i class="fas fa-eye"></i></a>
                                    <a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>" class="text-yellow-400 hover:text-yellow-600 mr-2" title="Send Email"><i class="fas fa-envelope"></i></a>
                                    <a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>" class="text-green-400 hover:text-green-600" title="Call Customer"><i class="fas fa-phone"></i></a>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <div class="md:hidden grid grid-cols-1 gap-4">
                <?php foreach ($bookings as $booking): ?>
                    <div class="booking-card p-4 rounded-lg shadow-md" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
                        <div class="flex items-center justify-between mb-3 border-b border-gray-700 pb-2">
                            <h3 class="text-xl font-bold gold"><?php echo htmlspecialchars($booking['name']); ?></h3>
                            <span class="text-sm text-gray-400"><i class="fas fa-calendar-alt mr-1"></i><?php echo date('M d, Y H:i', strtotime($booking['created_at'])); ?></span>
                        </div>
                        <div class="space-y-2 text-gray-300 text-sm">
                            <p><i class="fas fa-id-badge mr-2 gold w-5 text-center"></i>Booking ID: <span class="font-bold"><?php echo htmlspecialchars($booking['id']); ?></span></p>
                            <p><i class="fas fa-envelope mr-2 gold w-5 text-center"></i><a href="mailto:<?php echo htmlspecialchars($booking['email']); ?>" class="hover:text-gold"><?php echo htmlspecialchars($booking['email']); ?></a></p>
                            <p><i class="fas fa-phone mr-2 gold w-5 text-center"></i><a href="tel:<?php echo htmlspecialchars($booking['phone']); ?>" class="hover:text-gold"><?php echo htmlspecialchars($booking['phone']); ?></a></p>
                            <p><i class="fas fa-car mr-2 gold w-5 text-center"></i>Vehicle: <span class="font-bold"><?php echo htmlspecialchars(ucfirst($booking['vehicle'])); ?></span></p>
                            <div class="flex items-start"><i class="fas fa-location-arrow mr-2 gold w-5 text-center mt-1"></i><p>Pickup: <span class="font-semibold"><?php echo htmlspecialchars($booking['pickuplocation']); ?></span></p></div>
                            <div class="flex items-start"><i class="fas fa-map-marker-alt mr-2 gold w-5 text-center mt-1"></i><p>Dropoff: <span class="font-semibold"><?php echo htmlspecialchars($booking['dropofflocation']); ?></span></p></div>
                            <p><i class="fas fa-calendar-day mr-2 gold w-5 text-center"></i>Date: <span class="font-semibold"><?php echo date('M d, Y', strtotime($booking['pickupdate'])); ?></span></p>
                            <p><i class="fas fa-clock mr-2 gold w-5 text-center"></i>Time: <span class="font-semibold"><?php echo date('h:i A', strtotime($booking['pickuptime'])); ?></span></p>
                            <?php if (!empty($booking['notes'])): ?>
                                <p class="text-gray-400 text-xs pt-2 border-t border-gray-800"><i class="fas fa-info-circle mr-2 gold"></i>Notes: <span class="italic"><?php echo htmlspecialchars($booking['notes']); ?></span></p>
                            <?php endif; ?>
                            <div class="mt-2 flex items-center pt-2 border-t border-gray-800">
                                <span class="font-bold mr-2 gold"><i class="fas fa-info-circle mr-1"></i>Status:</span>
                                <select class="status-select flex-grow status-<?php echo htmlspecialchars(str_replace(' ', '-', $booking['status'])); ?>" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
                                    <?php foreach ($statuses as $status_option): ?>
                                        <option value="<?php echo htmlspecialchars($status_option); ?>"
                                            <?php echo ($booking['status'] == $status_option) ? 'selected' : ''; ?>
                                            class="status-<?php echo htmlspecialchars(str_replace(' ', '-', $status_option)); ?>">
                                            <?php echo htmlspecialchars($status_option); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        </div>
                        <div class="mt-4 pt-3 flex justify-end gap-2 border-t border-gray-700">
                            <button class="btn btn-primary text-xs px-3 py-2 view-details-btn" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>"><i class="fas fa-eye mr-1"></i> View Details</button>
                            <a href="https://maps.google.com/maps?q=<?php echo urlencode($booking['pickuplocation']); ?>" target="_blank" class="btn btn-secondary text-xs px-3 py-2"><i class="fas fa-directions mr-1"></i> Get Directions</a>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if (!empty($bookings) && $total_pages > 1): ?>
            <div class="mt-8 mb-4 flex flex-wrap justify-center items-center space-x-1 sm:space-x-2 text-white pagination-container">
                <?php 
                $query_params = $_GET; 
                unset($query_params['page']); 
                $base_query_string = http_build_query($query_params); 
                if (!empty($base_query_string)) { 
                    $base_query_string .= '&'; 
                } 
                $num_links_around_current = 2; 
                ?>
                <?php if ($current_page > 1): ?>
                    <a href="?<?php echo $base_query_string; ?>page=<?php echo $current_page - 1; ?>" class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm">« Prev</a>
                <?php else: ?>
                    <span class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm" style="opacity:0.5; cursor:default;">« Prev</span>
                <?php endif; ?>
                <?php if ($current_page > $num_links_around_current + 1): ?>
                    <a href="?<?php echo $base_query_string; ?>page=1" class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm">1</a>
                    <?php if ($current_page > $num_links_around_current + 2): ?>
                        <span class="px-2 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm">...</span>
                    <?php endif; ?>
                <?php endif; ?>
                <?php for ($i = max(1, $current_page - $num_links_around_current); $i <= min($total_pages, $current_page + $num_links_around_current); $i++): ?>
                    <?php if ($i == $current_page): ?>
                        <span class="current-page-indicator px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm"><?php echo $i; ?></span>
                    <?php else: ?>
                        <a href="?<?php echo $base_query_string; ?>page=<?php echo $i; ?>" class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm"><?php echo $i; ?></a>
                    <?php endif; ?>
                <?php endfor; ?>
                <?php if ($current_page < $total_pages - $num_links_around_current): ?>
                     <?php if ($current_page < $total_pages - $num_links_around_current - 1): ?>
                        <span class="px-2 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm">...</span>
                    <?php endif; ?>
                    <a href="?<?php echo $base_query_string; ?>page=<?php echo $total_pages; ?>" class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm"><?php echo $total_pages; ?></a>
                <?php endif; ?>
                <?php if ($current_page < $total_pages): ?>
                    <a href="?<?php echo $base_query_string; ?>page=<?php echo $current_page + 1; ?>" class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm">Next »</a>
                <?php else: ?>
                     <span class="btn btn-secondary px-3 py-2 text-xs sm:px-4 sm:py-2 sm:text-sm" style="opacity:0.5; cursor:default;">Next »</span>
                <?php endif; ?>
            </div>
            <?php endif; ?>

        <?php endif; ?> 
    </main>

    <footer class="bg-black py-4 text-center text-gray-600 text-sm border-t border-gray-800">
        <p>© <?php echo date('Y'); ?> Luxury Limousine. All rights reserved.</p> <p class="text-xs mt-1">Page loaded in <?php echo round(microtime(true) - ($_SERVER["REQUEST_TIME_FLOAT"] ?? microtime(true)), 4); ?> seconds.</p>
    </footer>

    <div class="notification-container" id="notificationContainer"></div>

    <div id="newBookingAlertModal" class="modal-overlay">
        <div class="modal-content text-center max-w-sm" style="padding: 2.5rem; border: 2px solid #D4AF37;">
             <h3 class="monument text-3xl font-bold gold mb-4">Congratulations!</h3>
            <p class="text-xl text-white mb-6">You have a new ride booking.</p>
            <button id="viewNewBookingBtn" class="btn btn-primary px-8 py-3 text-lg">
                <i class="fas fa-times mr-2"></i> Close 
            </button>
        </div>
    </div>

    <div id="bookingDetailsModal" class="modal-overlay">
        <div class="modal-content">
            <button class="modal-close-btn" id="closeBookingDetailsModalBtn"><i class="fas fa-times"></i></button>
            <h3 class="monument text-2xl font-bold gold mb-6 text-center">Booking <span class="text-white">Details</span></h3>
            <div class="space-y-4 text-sm" id="modalBookingDetailsContent">
                <div class="modal-detail-row"><span>ID:</span><span id="detailId" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Name:</span><span id="detailName" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Email:</span><span id="detailEmail" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Phone:</span><span id="detailPhone" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Vehicle:</span><span id="detailVehicle" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Pickup:</span><span id="detailPickupLocation" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Dropoff:</span><span id="detailDropoffLocation" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Date:</span><span id="detailPickupDate" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Time:</span><span id="detailPickupTime" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Status:</span><span id="detailStatus" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Booked On:</span><span id="detailCreatedAt" class="copyable-field"></span></div>
                <div class="modal-detail-row"><span>Notes:</span><span id="detailNotes" class="copyable-field notes-content"></span></div>
            </div>
        </div>
    </div>

    <audio id="newBookingSound" src="../assets/sounds/notification.mp3" preload="auto"></audio>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const statusSelects = document.querySelectorAll('.status-select');
    const notificationContainer = document.getElementById('notificationContainer');
    const newBookingAlertModal = document.getElementById('newBookingAlertModal');
    const viewNewBookingBtn = document.getElementById('viewNewBookingBtn'); 
    const newBookingSound = document.getElementById('newBookingSound');
    const bookingDetailsModal = document.getElementById('bookingDetailsModal');
    const closeBookingDetailsModalBtn = document.getElementById('closeBookingDetailsModalBtn');
    const copyableFields = document.querySelectorAll('.copyable-field'); 

    let currentBookingIdForModal = null;
    let currentBookingIdForDetailsModal = null;
    let isUpdatingStatus = false; 

    function showNotification(type, title, message) {
        const notification = document.createElement('div');
        notification.classList.add('notification', type); 
        notification.innerHTML = `
            <div class="notification-icon">${type === 'success' ? '<i class="fas fa-check-circle"></i>' : (type === 'error' ? '<i class="fas fa-times-circle"></i>' : '<i class="fas fa-info-circle"></i>')}</div>
            <div class="notification-content">
                <h4 class="notification-title">${title}</h4>
                <p class="notification-message">${message}</p>
            </div>
            <div class="notification-close"><i class="fas fa-times"></i></div>
        `; 
        if(notificationContainer) {
            notificationContainer.appendChild(notification);
        } else {
            alert(`${title}: ${message}`);
            return;
        }

        void notification.offsetWidth; 
        notification.classList.add('active');

        const timeoutId = setTimeout(() => hideNotification(notification), 5000);
        
        const closeButton = notification.querySelector('.notification-close');
        if (closeButton) {
            closeButton.addEventListener('click', () => {
                clearTimeout(timeoutId); 
                hideNotification(notification);
            });
        }
    }

    function hideNotification(notificationElement) {
        if (!notificationElement || !notificationElement.classList.contains('active')) return; 
        notificationElement.classList.remove('active'); 
        notificationElement.addEventListener('transitionend', () => {
            if (notificationElement.parentNode) { 
                 notificationElement.remove();
            }
        }, { once: true }); 
    }

    function openBookingDetailsModal(bookingId) {
        currentBookingIdForDetailsModal = bookingId;

        ['detailId', 'detailName', 'detailEmail', 'detailPhone', 'detailVehicle', 'detailPickupLocation', 'detailDropoffLocation', 'detailPickupDate', 'detailPickupTime', 'detailStatus', 'detailCreatedAt', 'detailNotes']
        .forEach(id => {
            const el = document.getElementById(id);
            if(el) el.textContent = 'Loading...';
        });

        fetch(`fetch_booking_details.php?id=${bookingId}`)
            .then(response => {
                if (!response.ok) {
                    return response.json().then(errData => {
                        throw new Error(errData.message || `HTTP error! Status: ${response.status}`);
                    }).catch(() => {
                        return response.text().then(text => { throw new Error(`HTTP error! Status: ${response.status} - ${text.substring(0, 200)}`); });
                    });
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.booking) {
                    const booking = data.booking;
                    document.getElementById('detailId').textContent = booking.id || '-';
                    document.getElementById('detailName').textContent = booking.name || '-';
                    document.getElementById('detailEmail').textContent = booking.email || '-';
                    document.getElementById('detailPhone').textContent = booking.phone || '-';
                    document.getElementById('detailVehicle').textContent = booking.vehicle ? booking.vehicle.charAt(0).toUpperCase() + booking.vehicle.slice(1) : '-';
                    document.getElementById('detailPickupLocation').textContent = booking.pickuplocation || '-';
                    document.getElementById('detailDropoffLocation').textContent = booking.dropofflocation || '-';
                    document.getElementById('detailPickupDate').textContent = booking.pickupdate ? new Date(booking.pickupdate + 'T00:00:00Z').toLocaleDateString(undefined, { month: 'short', day: 'numeric', year: 'numeric', timeZone: 'UTC' }) : '-'; 
                    document.getElementById('detailPickupTime').textContent = booking.pickuptime ? new Date('1970-01-01T' + booking.pickuptime + 'Z').toLocaleTimeString(undefined, { hour: 'numeric', minute: '2-digit', hour12: true, timeZone: 'UTC' }) : '-'; 
                    document.getElementById('detailStatus').textContent = booking.status || '-';
                    document.getElementById('detailCreatedAt').textContent = booking.created_at ? new Date(booking.created_at).toLocaleString(undefined, { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : '-';
                    document.getElementById('detailNotes').textContent = booking.notes || '-';

                    if(bookingDetailsModal) {
                        bookingDetailsModal.classList.add('active');
                    }
                } else {
                    showNotification('error', 'Error!', data.message || 'Could not fetch booking details.');
                }
            })
            .catch(error => {
                console.error('Fetch booking details error:', error);
                showNotification('error', 'Network Error!', `Could not fetch booking details: ${error.message}`);
            });
    }

    function updateBookingStatus(bookingId, newStatus) {
        if (isUpdatingStatus) {
            showNotification('info', 'Working...', 'Previous update is still in progress.');
            return;
        }
        isUpdatingStatus = true;

fetch('update_booking_status.php', { 
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=update_booking_status&id=${bookingId}&status=${encodeURIComponent(newStatus)}`
        })
        .then(response => {
            return response.text().then(text => {
                try {
                    const jsonData = JSON.parse(text);
                    if (!response.ok) {
                         throw new Error(jsonData.message || `Server error: ${response.status}`);
                    }
                    return jsonData;
                } catch (e) {
                    console.error("Failed to parse server response as JSON in updateBookingStatus:", text);
                    throw new Error(`Unexpected server response. Check console for details. Response start: ${text.substring(0,100)}`);
                }
            });
        })
        .then(data => {
            isUpdatingStatus = false;
            if (data.success) {
                showNotification('success', 'Success!', data.message); // The message from PHP now contains email status
                const selectElement = document.querySelector(`.status-select[data-booking-id="${bookingId}"]`);
                if (selectElement) {
                    selectElement.className = 'status-select w-full status-' + newStatus.replace(/\s+/g, '-');
                }
            } else {
                showNotification('error', 'Update Failed!', data.message || `Failed to update booking ${bookingId} status.`);
            }
        })
        .catch(error => {
            isUpdatingStatus = false;
            console.error('Update status error:', error);
            showNotification('error', 'Network Error!', `Could not process status update: ${error.message}`);
        });
    }

    statusSelects.forEach(select => {
        const currentStatus = select.value;
        select.classList.add(`status-${currentStatus.replace(/\s+/g, '-')}`);

        select.addEventListener('change', function() {
            this.className.split(' ').forEach(cls => {
                if (cls.startsWith('status-') && cls !== 'status-select') {
                    this.classList.remove(cls);
                }
            });
            const newStatusVal = this.value;
            this.classList.add(`status-${newStatusVal.replace(/\s+/g, '-')}`);
            updateBookingStatus(this.dataset.bookingId, newStatusVal);
        });
    });

    document.querySelectorAll('.view-details-btn').forEach(button => {
        button.addEventListener('click', function(event) {
            event.preventDefault();
            openBookingDetailsModal(this.dataset.bookingId);
        });
    });

    if (closeBookingDetailsModalBtn) {
        closeBookingDetailsModalBtn.addEventListener('click', function() {
            if(bookingDetailsModal) {
                bookingDetailsModal.classList.remove('active');
            }
        });
    }
    
    if (bookingDetailsModal) {
        bookingDetailsModal.addEventListener('click', function(event) {
            if (event.target === bookingDetailsModal) { 
                bookingDetailsModal.classList.remove('active');
            }
        });
    }

    copyableFields.forEach(field => {
        field.addEventListener('click', function() {
            const textToCopy = this.textContent;
            if (!textToCopy || textToCopy === '-' || textToCopy === 'Loading...') return;
            navigator.clipboard.writeText(textToCopy)
                .then(() => { showNotification('info', 'Copied!', `'${textToCopy.substring(0,30).replace(/\n/g, ' ')}...' copied.`); })
                .catch(err => { 
                    console.warn('Async clipboard copy failed, trying fallback:', err);
                    const textArea = document.createElement('textarea');
                    textArea.value = textToCopy;
                    textArea.style.position = 'fixed'; textArea.style.left = '-999999px'; 
                    document.body.appendChild(textArea);
                    textArea.focus(); textArea.select();
                    try {
                        document.execCommand('copy');
                        showNotification('info', 'Copied!', `'${textToCopy.substring(0,30).replace(/\n/g, ' ')}...' copied (fallback).`);
                    } catch (fallbackErr) {
                        console.error('Fallback copy failed: ', fallbackErr);
                        showNotification('error', 'Copy Failed!', 'Could not copy text.');
                    } finally {
                        document.body.removeChild(textArea);
                    }
                });
        });
    });

    if (viewNewBookingBtn && newBookingAlertModal) {
        viewNewBookingBtn.addEventListener('click', function() {
            newBookingAlertModal.classList.remove('active'); 
        });
    }

    let lastAlertedBookingId_allBookings = sessionStorage.getItem('lastAlertedBookingId_allBookingsPage');

    function checkNewBookingsOnAllBookingsPage() {
        fetch('check_new_bookings.php') 
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                if (data.success && data.bookings.length > 0) {
                    data.bookings.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)); 
                    const latestBooking = data.bookings[0];

                    if (latestBooking.id && latestBooking.id.toString() !== lastAlertedBookingId_allBookings) {
                        if (newBookingSound) {
                            newBookingSound.play().catch(error => console.warn('Audio play failed:', error));
                        }
                        currentBookingIdForModal = latestBooking.id;
                        if (newBookingAlertModal) {
                            newBookingAlertModal.classList.add('active');
                        }
                        lastAlertedBookingId_allBookings = latestBooking.id.toString();
                        sessionStorage.setItem('lastAlertedBookingId_allBookingsPage', lastAlertedBookingId_allBookings);
                        showNotification('info', 'New Booking!', `Booking ID ${latestBooking.id} has arrived.`);
                    }
                }
            })
            .catch(error => {
                // console.warn('Error checking for new bookings on all-bookings page:', error); 
            });
    }

    const pollingInterval_allBookings = 11000;
    let newBookingCheckInterval_allBookings = setInterval(checkNewBookingsOnAllBookingsPage, pollingInterval_allBookings);

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "hidden") {
            clearInterval(newBookingCheckInterval_allBookings);
        } else {
            checkNewBookingsOnAllBookingsPage(); 
            clearInterval(newBookingCheckInterval_allBookings); 
            newBookingCheckInterval_allBookings = setInterval(checkNewBookingsOnAllBookingsPage, pollingInterval_allBookings);
        }
    });
    checkNewBookingsOnAllBookingsPage(); 
});
</script>
</body>
</html>