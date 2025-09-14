<?php
// admin/dashboard.php
session_start(); // Ensure session is started for $_SESSION variables

// Include the authentication check first to protect this page
require_once 'auth_check.php';

// Include your database connection file
require_once '../db_connect.php';

// --- Production Error Handling ---
ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your_project/php-error.log'); // Recommended: Uncomment and set a valid path

$bookings = []; // For today/tomorrow's bookings list
$message = '';
$errorMessage = '';
$statuses = ['Pending', 'Confirmed', 'Completed', 'Cancelled'];

// Stats variables
$stats = [
    'new_today' => 0,
    'pending' => 0,
    'confirmed' => 0,
    'completed' => 0,
    'total' => 0
];

$search_query = '';
if (isset($_GET['q']) && !empty(trim($_GET['q']))) {
    $search_query = trim($_GET['q']);
}

$db_conn_page_load = new mysqli($servername, $username, $password, $dbname);

if ($db_conn_page_load->connect_error) {
    $errorMessage = "Database connection failed for page load: " . $db_conn_page_load->connect_error;
    error_log("Dashboard DB Connect Error: " . $errorMessage);
    $message = "The dashboard is currently unable to retrieve booking data. Please try again later.";
} else {
    $db_conn_page_load->set_charset("utf8mb4");
    try {
        // --- Fetch Stats ---
        $stmt_new_today = $db_conn_page_load->prepare("SELECT COUNT(*) as count FROM bookings WHERE DATE(created_at) = CURDATE()");
        if ($stmt_new_today) {
            if ($stmt_new_today->execute()) {
                $result_new_today = $stmt_new_today->get_result()->fetch_assoc();
                $stats['new_today'] = $result_new_today['count'] ?? 0;
            } else { error_log("Dashboard Stats Error (New Today Exec): " . $stmt_new_today->error); }
            $stmt_new_today->close();
        } else { error_log("Dashboard Stats Error (New Today Prepare): " . $db_conn_page_load->error); }

        $stmt_pending = $db_conn_page_load->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'Pending'");
        if ($stmt_pending) {
            if ($stmt_pending->execute()) {
                $result_pending = $stmt_pending->get_result()->fetch_assoc();
                $stats['pending'] = $result_pending['count'] ?? 0;
            } else { error_log("Dashboard Stats Error (Pending Exec): " . $stmt_pending->error); }
            $stmt_pending->close();
        } else { error_log("Dashboard Stats Error (Pending Prepare): " . $db_conn_page_load->error); }

        $stmt_confirmed = $db_conn_page_load->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'Confirmed'");
        if ($stmt_confirmed) {
            if ($stmt_confirmed->execute()) {
                $result_confirmed = $stmt_confirmed->get_result()->fetch_assoc();
                $stats['confirmed'] = $result_confirmed['count'] ?? 0;
            } else { error_log("Dashboard Stats Error (Confirmed Exec): " . $stmt_confirmed->error); }
            $stmt_confirmed->close();
        } else { error_log("Dashboard Stats Error (Confirmed Prepare): " . $db_conn_page_load->error); }

        $stmt_completed = $db_conn_page_load->prepare("SELECT COUNT(*) as count FROM bookings WHERE status = 'Completed'");
        if ($stmt_completed) {
            if ($stmt_completed->execute()) {
                $result_completed = $stmt_completed->get_result()->fetch_assoc();
                $stats['completed'] = $result_completed['count'] ?? 0;
            } else { error_log("Dashboard Stats Error (Completed Exec): " . $stmt_completed->error); }
            $stmt_completed->close();
        } else { error_log("Dashboard Stats Error (Completed Prepare): " . $db_conn_page_load->error); }

        $stmt_total = $db_conn_page_load->prepare("SELECT COUNT(*) as count FROM bookings");
        if ($stmt_total) {
            if ($stmt_total->execute()) {
                $result_total = $stmt_total->get_result()->fetch_assoc();
                $stats['total'] = $result_total['count'] ?? 0;
            } else { error_log("Dashboard Stats Error (Total Exec): " . $stmt_total->error); }
            $stmt_total->close();
        } else { error_log("Dashboard Stats Error (Total Prepare): " . $db_conn_page_load->error); }
        // --- End Fetch Stats ---

        // --- Fetch Bookings for Today/Tomorrow (Main List) ---
        $today = date('Y-m-d');
        $tomorrow = date('Y-m-d', strtotime('+1 day'));

        $sql_select = "SELECT id, name, email, phone, vehicle, pickuplocation, pickupdate, pickuptime, dropofflocation, notes, created_at, status FROM bookings";
        
        $all_where_conditions = [];
        $params = [];
        $param_types = "";

        if (!empty($search_query)) {
            $search_query_like = "%" . $search_query . "%";
            $searchable_fields = ['id', 'name', 'email', 'phone', 'vehicle', 'pickuplocation', 'dropofflocation', 'status'];
            $search_field_conditions = [];
            
            foreach($searchable_fields as $field) {
                $search_field_conditions[] = "`$field` LIKE ?";
                $params[] = $search_query_like;
                $param_types .= "s";
            }

            if (!empty($search_field_conditions)) {
                $all_where_conditions[] = "(" . implode(" OR ", $search_field_conditions) . ")";
            }
        }
        
        $all_where_conditions[] = "(pickupdate = ? OR pickupdate = ?)";
        $params[] = $today;
        $params[] = $tomorrow;
        $param_types .= "ss"; 

        if (!empty($all_where_conditions)) {
            $sql_select .= " WHERE " . implode(" AND ", $all_where_conditions);
        }
        
        $sql_select .= " ORDER BY pickupdate ASC, pickuptime ASC, created_at DESC"; 

        $stmt = $db_conn_page_load->prepare($sql_select);
        
        if ($stmt === false) {
            throw new Exception("SQL prepare failed for main bookings list: " . $db_conn_page_load->error . " (Query: " . $sql_select . ")");
        }

        if (!empty($params)) {
            $bind_names = [$param_types];
            for ($i = 0; $i < count($params); $i++) {
                ${"param_val_" . $i} = $params[$i];
                $bind_names[] = &${"param_val_" . $i};
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
                if (!empty($search_query)) {
                    $message = "No bookings found for today or tomorrow matching your search for '" . htmlspecialchars($search_query) . "'.";
                } else {
                    $message = "No bookings scheduled for today or tomorrow.";
                }
            }
            $stmt->close();
        } else {
            throw new Exception("SQL execute failed for main bookings list: " . $stmt->error);
        }
    } catch (Exception | mysqli_sql_exception $e) {
        $errorMessage = "Error fetching data for dashboard: " . $e->getMessage();
        error_log("Dashboard Data Fetch Exception: " . $errorMessage); 
        $message = "There was an error retrieving dashboard information. Please try again.";
    }
    $db_conn_page_load->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Luxury Limousine Bookings</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        
        body {
            font-family: 'Montserrat', sans-serif;
            background-color: #000;
            color: #fff;
            min-height: 100vh;
        }
        
        .monument { 
            font-family: 'Montserrat', sans-serif;
            font-weight: 800;
            letter-spacing: -0.5px;
        }
        .gold { color: #D4AF37; }
        .bg-gold { background-color: #D4AF37; }

        .btn {
            padding: 0.75rem 1.5rem; border-radius: 0.25rem; font-weight: 600;
            letter-spacing: 0.5px; transition: all 0.3s ease; cursor: pointer;
            display: inline-flex; align-items: center; justify-content: center;
        }
        .btn-primary { background-color: #D4AF37; color: #000; }
        .btn-primary:hover { background-color: rgba(212, 175, 55, 0.9); transform: translateY(-2px); }
        .btn-secondary { 
            background-color: rgba(255, 255, 255, 0.1); color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .btn-secondary:hover { background-color: rgba(255, 255, 255, 0.2); transform: translateY(-2px); }
        
        .form-input { 
            width: 100%; padding: 0.75rem 1rem; border-radius: 0.25rem;
            background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);
            color: white; transition: all 0.3s ease; margin-bottom: 1rem;
        }
        .form-input:focus {
            outline: none; border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
            background-color: rgba(255, 255, 255, 0.15);
        }

        .table-auto { border-collapse: collapse; width: 100%; }
        .table-auto th, .table-auto td {
            padding: 12px 15px; text-align: left;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .table-auto th {
            background-color: rgba(212, 175, 55, 0.1); color: #D4AF37; font-weight: 700;
            text-transform: uppercase; font-size: 0.85rem;
        }
        .table-auto tbody tr:hover { background-color: rgba(255, 255, 255, 0.05); }
        .table-auto td { color: rgba(255, 255, 255, 0.8); font-size: 0.9rem; }

        .booking-card { 
            background-color: #1a1a1a; border: 1px solid rgba(212, 175, 55, 0.2);
        }
        .booking-card p { word-break: break-word; }

        .status-Pending { color: orange; }
        .status-Confirmed { color: #28a745; }
        .status-Completed { color: #17a2b8; }
        .status-Cancelled { color: #dc3545; }

        .status-select {
            background-color: rgba(255, 255, 255, 0.1); border: 1px solid rgba(255, 255, 255, 0.2);
            color: white; padding: 6px 10px; border-radius: 0.25rem; cursor: pointer;
            -webkit-appearance: none; -moz-appearance: none; appearance: none;
            background-image: url('data:image/svg+xml;charset=US-ASCII,%3Csvg%20xmlns%3D%22http%3A%2F%2Fwww.w3.org%2F2000%2Fsvg%22%20width%3D%22292.4%22%20height%3D%22292.4%22%3E%3Cpath%20fill%3D%22%23D4AF37%22%20d%3D%22M287%2C197.8L156.4%2C67.2c-4.4-4.4-11.5-4.4-15.9%2C0L5.4%2C197.8c-4.4%2C4.4-4.4%2C11.5%2C0%2C15.9l16.1%2C16.1c4.4%2C4.4%2C11.5%2C4.4%2C15.9%2C0L148.5%2C128l111%2C111c4.4%2C4.4%2C11.5%2C4.4%2C15.9%2C0l16.1-16.1c4.4-4.4%2C4.4-11.6%2C0-16z%22%2F%3E%3C%2Fsvg%3E');
            background-repeat: no-repeat; background-position: right 0.7em top 50%, 0 0;
            background-size: 0.65em auto, 100%;
        }
        .status-select:focus {
            outline: none; border-color: #D4AF37;
            box-shadow: 0 0 0 3px rgba(212, 175, 55, 0.2);
        }

        .notification-container { position: fixed; top: 20px; right: 20px; z-index: 1050; max-width: 350px; width: 90%;}
        .notification { background-color: #1a1a1a; border-radius: 0.25rem; padding: 1rem; box-shadow: 0 5px 15px rgba(0,0,0,0.3); display: flex; align-items: center; opacity: 0; transform: translateX(120%); transition: transform 0.4s ease-out, opacity 0.4s ease-out; margin-bottom: 10px; }
        .notification.active { opacity: 1; transform: translateX(0); }
        .notification.success { border-left: 4px solid #28a745; } .notification.error { border-left: 4px solid #dc3545; } .notification.info { border-left: 4px solid #17a2b8; }
        .notification-icon { font-size: 1.5rem; margin-right: 0.75rem; } .notification-content { flex-grow: 1; } .notification-title { font-weight: 700; margin-bottom: 0.25rem; } .notification-message { font-size: 0.875rem; color: #ccc; }
        .notification-close { margin-left: 0.75rem; cursor: pointer; color: #aaa; } .notification-close:hover { color: #fff; }

        .modal-overlay { position: fixed; top: 0; left: 0; right: 0; bottom: 0; background-color: rgba(0, 0, 0, 0.7); display: flex; justify-content: center; align-items: center; z-index: 1000; opacity: 0; visibility: hidden; transition: opacity 0.3s ease-in-out, visibility 0.3s ease-in-out; }
        .modal-overlay.active { opacity: 1; visibility: visible; }
        .modal-content { background-color: #1a1a1a; padding: 2.5rem; border-radius: 0.5rem; width: 90%; max-height: 90vh; overflow-y: auto; transform: translateY(-20px); transition: transform 0.3s ease-in-out; border: 1px solid rgba(212, 175, 55, 0.3); position: relative; }
        #bookingDetailsModal .modal-content { max-width: 600px; }
        .modal-overlay.active .modal-content { transform: translateY(0); }
        .modal-close-btn { position: absolute; top: 15px; right: 15px; background: none; border: none; font-size: 1.5rem; color: #aaa; cursor: pointer; transition: color 0.2s ease; }
        .modal-close-btn:hover { color: #fff; }
        .modal-detail-row { display: flex; align-items: flex-start; margin-bottom: 0.75rem; line-height: 1.4; }
        .modal-detail-row span:first-child { font-weight: 600; color: #D4AF37; flex-shrink: 0; width: 120px; margin-right: 1rem; }
        .copyable-field { color: rgba(255, 255, 255, 0.9); flex-grow: 1; word-break: break-word; cursor: pointer; padding: 2px 4px; border-radius: 4px; transition: background-color 0.2s ease; }
        .copyable-field:hover { background-color: rgba(212, 175, 55, 0.1); }
        .copyable-field.notes-content { white-space: pre-wrap; }
        #newBookingAlertModal .modal-content {
            max-width: 450px; 
            border: 2px solid #D4AF37;
            box-shadow: 0 0 30px rgba(212, 175, 55, 0.5);
        }
        .stat-box {
            background-color: #1f2937; 
            padding: 1rem; 
            border-radius: 0.5rem; 
            box-shadow: 0 4px 6px -1px rgba(0, 0, 0, 0.1), 0 2px 4px -1px rgba(0, 0, 0, 0.06); 
            text-align: center;
            border: 1px solid #374151; 
            transition: transform 0.2s ease-in-out, box-shadow 0.2s ease-in-out; 
        }
        .stat-box h4 {
            font-size: 0.875rem; 
            color: #9ca3af; 
            text-transform: uppercase;
            font-weight: 600; 
        }
        .stat-box p {
            font-size: 1.875rem; 
            font-weight: 700; 
            margin-top: 0.25rem; 
        }
    </style>
</head>
<body class="flex flex-col">

    <?php require_once 'admin_header.php'; ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h2 class="monument text-3xl font-bold mb-6 text-center">
            Welcome Back, <span class="gold"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?></span>!
        </h2>
        
        <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-5 gap-4 mb-8">
            <a href="all-bookings.php?filter=new_today" class="block text-white no-underline rounded-lg transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-D4AF37 focus:ring-opacity-50">
                <div class="stat-box h-full">
                    <h4>New Today</h4>
                    <p class="gold"><?php echo $stats['new_today']; ?></p>
                </div>
            </a>
            <a href="all-bookings.php?filter=pending" class="block text-white no-underline rounded-lg transform hover:scale-105 focus:outline-none focus:ring-2 focus:ring-D4AF37 focus:ring-opacity-50">
                <div class="stat-box h-full">
                    <h4>Pending</h4>
                    <p class="text-orange-400"><?php echo $stats['pending']; ?></p>
                </div>
            </a>
            <div class="stat-box">
                <h4>Confirmed</h4>
                <p class="text-green-400"><?php echo $stats['confirmed']; ?></p>
            </div>
            <div class="stat-box">
                <h4>Completed</h4>
                <p class="text-blue-400"><?php echo $stats['completed']; ?></p>
            </div>
            <div class="stat-box">
                <h4>Total Bookings</h4>
                <p class="text-gray-200"><?php echo $stats['total']; ?></p>
            </div>
        </div>
        
        <form action="dashboard.php" method="GET" class="mb-8 max-w-2xl mx-auto">
            <div class="flex shadow-md rounded-md">
                <input type="text" name="q" placeholder="Search Bookings (Today/Tomorrow)..."
                       value="<?php echo htmlspecialchars($search_query); ?>"
                       class="form-input !mb-0 flex-grow !rounded-r-none"> 
                <button type="submit" class="btn btn-primary !rounded-l-none">
                    <i class="fas fa-search mr-1 md:mr-2"></i><span class="hidden md:inline">Search</span>
                </button>
                <?php if (!empty($search_query)): ?>
                    <a href="dashboard.php" class="btn btn-secondary ml-2 !px-3 md:!px-6" title="Clear Search">
                        <i class="fas fa-times"></i> <span class="hidden md:inline ml-1">Clear</span>
                    </a>
                <?php endif; ?>
            </div>
        </form>
        
        <?php if (!empty($errorMessage)): ?>
            <div class="bg-red-700 bg-opacity-50 border border-red-700 p-4 rounded-md text-center text-white mb-6">
                <p class="font-semibold">Error:</p>
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php elseif (empty($bookings) && !empty($message)): ?>
            <div class="bg-gray-800 p-4 rounded-md text-center text-gray-300 mb-6">
                <p><?php echo htmlspecialchars($message); ?></p>
            </div>
        <?php endif; ?>
        
        <?php if (!empty($bookings)): ?>
            <div class="hidden md:block overflow-x-auto bg-gray-900 rounded-lg shadow-md mb-8">
                <table class="table-auto min-w-full">
                    <thead>
                        <tr>
                            <th>ID</th><th>Name</th><th>Vehicle</th><th>Pickup Location</th><th>Dropoff Location</th>
                            <th>Date</th><th>Time</th><th>Notes</th><th class="min-w-[150px]">Status</th><th>Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($bookings as $booking): ?>
                            <tr data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
                                <td><?php echo htmlspecialchars($booking['id']); ?></td>
                                <td><?php echo htmlspecialchars($booking['name']); ?></td>
                                <td><?php echo htmlspecialchars(ucfirst($booking['vehicle'])); ?></td>
                                <td class="max-w-xs truncate" title="<?php echo htmlspecialchars($booking['pickuplocation']); ?>"><?php echo htmlspecialchars($booking['pickuplocation']); ?></td>
                                <td class="max-w-xs truncate" title="<?php echo htmlspecialchars($booking['dropofflocation']); ?>"><?php echo htmlspecialchars($booking['dropofflocation']); ?></td>
                                <td><?php echo date('M d, Y', strtotime($booking['pickupdate'])); ?></td>
                                <td><?php echo date('h:i A', strtotime($booking['pickuptime'])); ?></td>
                                <td class="whitespace-nowrap overflow-hidden overflow-ellipsis max-w-xs" title="<?php echo htmlspecialchars($booking['notes']); ?>">
                                    <?php echo !empty($booking['notes']) ? htmlspecialchars(substr($booking['notes'], 0, 30)) . (strlen($booking['notes']) > 30 ? '...' : '') : '-'; ?>
                                </td>
                                <td class="min-w-[150px]">
                                    <select class="status-select w-full" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
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
                                <select class="status-select flex-grow" data-booking-id="<?php echo htmlspecialchars($booking['id']); ?>">
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
        <?php endif; ?>
    </main>

    <footer class="bg-black py-4 text-center text-gray-600 text-sm border-t border-gray-800">
        <p>© <?php echo date('Y'); ?> Luxury Limousine. All rights reserved.</p>
    </footer>

    <div class="notification-container" id="notificationContainer"></div>

    <div id="newBookingAlertModal" class="modal-overlay">
        <div class="modal-content text-center">
             <h3 class="monument text-3xl font-bold gold mb-4">New Booking!</h3>
            <p class="text-xl text-white mb-6">A new ride has been booked.</p>
            <div class="mt-6 flex flex-col sm:flex-row justify-center gap-3 sm:gap-4">
                <button id="closeNewBookingAlertBtn" class="btn btn-secondary px-6 py-3 text-lg w-full sm:w-auto">
                    <i class="fas fa-times mr-2"></i> Close
                </button>
                <button id="viewAllBookingsBtn" class="btn btn-primary px-6 py-3 text-lg w-full sm:w-auto">
                    <i class="fas fa-list-alt mr-2"></i> View All Bookings
                </button>
            </div>
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
    const closeNewBookingAlertBtn = document.getElementById('closeNewBookingAlertBtn');
    const viewAllBookingsBtn = document.getElementById('viewAllBookingsBtn');

    const newBookingSound = document.getElementById('newBookingSound');
    const bookingDetailsModal = document.getElementById('bookingDetailsModal');
    const closeBookingDetailsModalBtn = document.getElementById('closeBookingDetailsModalBtn');
    const copyableFields = document.querySelectorAll('.copyable-field');
    
    // const acceptBookingBtn = document.getElementById('acceptBookingBtn'); // Button removed from modal
    // const cancelBookingBtn = document.getElementById('cancelBookingBtn'); // Button removed from modal

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
            console.error("Notification container (notificationContainer) not found.");
            alert(`${title}: ${message}`); 
            return;
        }
        void notification.offsetWidth;
        notification.classList.add('active');
        const timeoutId = setTimeout(() => hideNotification(notification), 5000);
        notification.querySelector('.notification-close').addEventListener('click', () => {
            clearTimeout(timeoutId);
            hideNotification(notification);
        });
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

    let currentBookingIdForModalDetails = null; 

    function openBookingDetailsModal(bookingId) {
        currentBookingIdForModalDetails = bookingId; 
        // Since accept/cancel buttons are removed, these lines are not needed:
        // if(acceptBookingBtn) acceptBookingBtn.dataset.bookingId = bookingId;
        // if(cancelBookingBtn) cancelBookingBtn.dataset.bookingId = bookingId;

        const detailIds = ['detailId', 'detailName', 'detailEmail', 'detailPhone', 'detailVehicle', 'detailPickupLocation', 'detailDropoffLocation', 'detailPickupDate', 'detailPickupTime', 'detailStatus', 'detailCreatedAt', 'detailNotes'];
        detailIds.forEach(id => {
            const el = document.getElementById(id);
            if (el) {
                el.textContent = 'Loading...';
            } else {
                console.error(`Modal detail element with ID '${id}' not found! Please check dashboard.php HTML.`);
            }
        });

        fetch(`fetch_booking_details.php?id=${bookingId}`)
            .then(response => {
                if (!response.ok) {
                    return response.json()
                        .then(errData => { throw new Error(errData.message || `HTTP error! Status: ${response.status}`); })
                        .catch(() => response.text().then(text => { throw new Error(`HTTP error! Status: ${response.status} - Response: ${text.substring(0,100)}...`); }));
                }
                return response.json();
            })
            .then(data => {
                if (data.success && data.booking) {
                    const booking = data.booking;
                    const setDetail = (id, value) => { const el = document.getElementById(id); if (el) el.textContent = value || '-'; };
                    
                    setDetail('detailId', booking.id);
                    setDetail('detailName', booking.name);
                    setDetail('detailEmail', booking.email);
                    setDetail('detailPhone', booking.phone);
                    setDetail('detailVehicle', booking.vehicle ? booking.vehicle.charAt(0).toUpperCase() + booking.vehicle.slice(1) : null);
                    setDetail('detailPickupLocation', booking.pickuplocation);
                    setDetail('detailDropoffLocation', booking.dropofflocation);
                    setDetail('detailPickupDate', booking.pickupdate ? new Date(booking.pickupdate + 'T00:00:00').toLocaleDateString('en-US', { month: 'short', day: 'numeric', year: 'numeric' }) : null);
                    setDetail('detailPickupTime', booking.pickuptime ? new Date('1970-01-01T' + booking.pickuptime).toLocaleTimeString('en-US', { hour: 'numeric', minute: '2-digit', hour12: true }) : null);
                    setDetail('detailStatus', booking.status);
                    setDetail('detailCreatedAt', booking.created_at ? new Date(booking.created_at).toLocaleString('en-US', { month: 'short', day: 'numeric', year: 'numeric', hour: 'numeric', minute: '2-digit', hour12: true }) : null);
                    setDetail('detailNotes', booking.notes);

                    if(bookingDetailsModal) bookingDetailsModal.classList.add('active');
                } else {
                    showNotification('error', 'Error!', data.message || 'Could not fetch booking details.');
                    detailIds.forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '-';});
                }
            })
            .catch(error => {
                console.error('Error fetching or processing details:', error); 
                showNotification('error', 'Network/Data Error!', `Could not fetch booking details. ${error.message}`);
                detailIds.forEach(id => { const el = document.getElementById(id); if (el) el.textContent = '-';});
            });
    }

    function updateBookingStatus(bookingId, newStatus) {
        fetch('update_booking_status.php', { 
            method: 'POST',
            headers: { 'Content-Type': 'application/x-www-form-urlencoded', },
            body: `id=${bookingId}&status=${encodeURIComponent(newStatus)}`
        })
        .then(response => {
             if (!response.ok) {
                return response.text().then(text => {throw new Error(`Server error: ${response.status} - ${text}`)});
            }
            return response.json();
        })
        .then(data => {
            if (data.success) {
                showNotification('success', 'Success!', data.message || `Booking ${bookingId} status updated to ${newStatus}.`);
                const allSelectsForBooking = document.querySelectorAll(`.status-select[data-booking-id="${bookingId}"]`);
                allSelectsForBooking.forEach(sel => { sel.value = newStatus; });
                if (bookingDetailsModal && bookingDetailsModal.classList.contains('active') && currentBookingIdForModalDetails == bookingId) {
                    const detailStatusEl = document.getElementById('detailStatus');
                    if(detailStatusEl) detailStatusEl.textContent = newStatus;
                }
            } else {
                showNotification('error', 'Update Failed!', data.message || `Failed to update booking ${bookingId} status.`);
            }
        })
        .catch(error => {
            console.error('Error updating status:', error);
            showNotification('error', 'Network Error!', `Could not connect for status update: ${error.message}`);
        });
    }

    statusSelects.forEach(select => {
        select.addEventListener('change', function() {
            updateBookingStatus(this.dataset.bookingId, this.value);
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
            if(bookingDetailsModal) bookingDetailsModal.classList.remove('active');
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
            if (!textToCopy || textToCopy === '-' || textToCopy.includes('Loading...')) return;
            navigator.clipboard.writeText(textToCopy)
                .then(() => { showNotification('info', 'Copied!', `'${textToCopy.substring(0,30).replace(/\n/g, ' ')}...' copied.`); })
                .catch(err => {
                    console.warn('Async clipboard copy failed, trying fallback:', err);
                    const textArea = document.createElement('textarea');
                    textArea.value = textToCopy;
                    textArea.style.position = 'fixed'; textArea.style.left = '-999999px';
                    document.body.appendChild(textArea);
                    textArea.focus(); textArea.select();
                    try { document.execCommand('copy'); showNotification('info', 'Copied!', `'${textToCopy.substring(0,30).replace(/\n/g, ' ')}...' (fallback).`); }
                    catch (fallbackErr) { console.error('Fallback copy failed: ', fallbackErr); showNotification('error', 'Copy Failed!', 'Could not copy text.'); }
                    finally { document.body.removeChild(textArea); }
                });
        });
    });

    if (closeNewBookingAlertBtn) {
        closeNewBookingAlertBtn.addEventListener('click', function() {
            if(newBookingAlertModal) newBookingAlertModal.classList.remove('active');
        });
    }

    if (viewAllBookingsBtn) {
        viewAllBookingsBtn.addEventListener('click', function() {
            window.location.href = 'all-bookings.php';
        });
    }

    // JavaScript for acceptBookingBtn and cancelBookingBtn is removed as the buttons are removed from this modal.
    // If you re-add them, you would need to get their elements and add event listeners like before.

    let lastAlertedBookingId = sessionStorage.getItem('lastAlertedBookingId_dashboard');
    let currentBookingIdForAlert = null; 
    let isFirstSuccessfulCheckDone = false; 

    function checkNewBookings() {
        fetch('check_new_bookings.php')
            .then(response => {
                if (!response.ok) { throw new Error(`HTTP error! status: ${response.status}`); }
                return response.json();
            })
            .then(data => {
                if (data.success && data.bookings.length > 0) {
                    data.bookings.sort((a, b) => new Date(b.created_at) - new Date(a.created_at)); 
                    const latestBooking = data.bookings[0];

                    if (latestBooking.id) {
                        const latestBookingIdStr = latestBooking.id.toString();

                        if (!isFirstSuccessfulCheckDone) {
                            lastAlertedBookingId = latestBookingIdStr;
                            sessionStorage.setItem('lastAlertedBookingId_dashboard', lastAlertedBookingId);
                            isFirstSuccessfulCheckDone = true;
                        } else {
                            if (latestBookingIdStr !== lastAlertedBookingId) {
                                if (newBookingSound) {
                                    newBookingSound.play().catch(error => console.warn('Audio play failed:', error));
                                }
                                currentBookingIdForAlert = latestBooking.id; 
                                if (newBookingAlertModal) {
                                    newBookingAlertModal.classList.add('active');
                                }
                                showNotification('info', 'New Booking!', `Booking ID ${latestBooking.id} has arrived.`);
                                
                                lastAlertedBookingId = latestBookingIdStr;
                                sessionStorage.setItem('lastAlertedBookingId_dashboard', lastAlertedBookingId);
                            }
                        }
                    }
                } else if (data.success && data.bookings.length === 0) { 
                    if (!isFirstSuccessfulCheckDone) {
                        isFirstSuccessfulCheckDone = true; 
                    }
                }
            })
            .catch(error => {
                console.error('Error checking for new bookings:', error);
                if (!isFirstSuccessfulCheckDone) { 
                    isFirstSuccessfulCheckDone = true; 
                }
            });
    }

    checkNewBookings(); 
    const pollingInterval = 10000; 
    let newBookingCheckInterval = setInterval(checkNewBookings, pollingInterval);

    document.addEventListener("visibilitychange", () => {
        if (document.visibilityState === "hidden") {
            clearInterval(newBookingCheckInterval);
        } else {
            checkNewBookings(); 
            newBookingCheckInterval = setInterval(checkNewBookings, pollingInterval);
        }
    });
});
    </script>
</body>
</html>