<?php
// admin/reports.php
session_start();
require_once 'auth_check.php';
require_once '../db_connect.php'; // Assumes this sets $servername, $username, $password, $dbname

ini_set('display_errors', 0);
ini_set('log_errors', 1);
// ini_set('error_log', '/path/to/your_project/php-error.log'); // Ensure this path is configured

// --- Data Storage ---
$report_data = [
    'summary' => ['total_bookings' => 0],
    'bookings_by_status' => [],
    'bookings_by_vehicle' => [],
    'booking_trends_monthly' => [], // For chart: labels and data
    'popular_pickup_locations' => [],
    'popular_dropoff_locations' => [],
    'common_routes' => [],
    'peak_pickup_times' => [], // HH => count
    'upcoming_pickups' => [],
    'bookings_with_notes' => []
];
$report_message = '';
$report_error_message = '';

$db_conn = new mysqli($servername, $username, $password, $dbname);

if ($db_conn->connect_error) {
    $report_error_message = "Database connection failed: " . $db_conn->connect_error;
    error_log("Reports Page DB Connection Error: " . $report_error_message);
    $report_message = "Unable to generate reports. Please try again later.";
} else {
    $db_conn->set_charset("utf8mb4");
    try {
        // 1. Summary: Total Bookings
        $result = $db_conn->query("SELECT COUNT(*) as total FROM bookings");
        if ($result) $report_data['summary']['total_bookings'] = $result->fetch_assoc()['total'];
        else error_log("Reports Query Error (Total Bookings): " . $db_conn->error);


        // 2. Bookings by Status
        $result = $db_conn->query("SELECT status, COUNT(*) as count FROM bookings GROUP BY status ORDER BY status");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $report_data['bookings_by_status'][$row['status']] = $row['count'];
            }
        } else error_log("Reports Query Error (Bookings by Status): " . $db_conn->error);

        // 3. Bookings by Vehicle
        $result = $db_conn->query("SELECT vehicle, COUNT(*) as count FROM bookings GROUP BY vehicle ORDER BY count DESC LIMIT 10");
        if ($result) {
            while ($row = $result->fetch_assoc()) {
                $report_data['bookings_by_vehicle'][ucfirst($row['vehicle'])] = $row['count'];
            }
        } else error_log("Reports Query Error (Bookings by Vehicle): " . $db_conn->error);

        // 4. Booking Trends: Monthly for the last 12 months
        $result = $db_conn->query("SELECT DATE_FORMAT(pickupdate, '%Y-%m') as month, COUNT(*) as count 
                                     FROM bookings 
                                     WHERE pickupdate >= DATE_SUB(CURDATE(), INTERVAL 12 MONTH) AND pickupdate IS NOT NULL
                                     GROUP BY month 
                                     ORDER BY month ASC");
        if ($result) {
            $trend_labels = [];
            $trend_data = [];
            while ($row = $result->fetch_assoc()) {
                $trend_labels[] = date("M Y", strtotime($row['month'] . "-01"));
                $trend_data[] = $row['count'];
            }
            $report_data['booking_trends_monthly'] = ['labels' => $trend_labels, 'data' => $trend_data];
        } else error_log("Reports Query Error (Monthly Trends): " . $db_conn->error);
        
        // 5. Popular Pickup Locations (Top 5)
        $result = $db_conn->query("SELECT pickuplocation, COUNT(*) as count FROM bookings WHERE pickuplocation IS NOT NULL AND pickuplocation != '' GROUP BY pickuplocation ORDER BY count DESC LIMIT 5");
        if($result) {
            while($row = $result->fetch_assoc()){ $report_data['popular_pickup_locations'][] = $row; }
        } else error_log("Reports Query Error (Popular Pickup): " . $db_conn->error);


        // 6. Popular Dropoff Locations (Top 5)
        $result = $db_conn->query("SELECT dropofflocation, COUNT(*) as count FROM bookings WHERE dropofflocation IS NOT NULL AND dropofflocation != '' GROUP BY dropofflocation ORDER BY count DESC LIMIT 5");
        if($result) {
            while($row = $result->fetch_assoc()){ $report_data['popular_dropoff_locations'][] = $row; }
        } else error_log("Reports Query Error (Popular Dropoff): " . $db_conn->error);

        // 7. Common Routes (Top 5)
        $result = $db_conn->query("SELECT pickuplocation, dropofflocation, COUNT(*) as count 
                                     FROM bookings 
                                     WHERE pickuplocation IS NOT NULL AND pickuplocation != '' AND dropofflocation IS NOT NULL AND dropofflocation != ''
                                     GROUP BY pickuplocation, dropofflocation 
                                     ORDER BY count DESC LIMIT 5");
        if($result) {
            while($row = $result->fetch_assoc()){ $report_data['common_routes'][] = $row; }
        } else error_log("Reports Query Error (Common Routes): " . $db_conn->error);
        
        // 8. Peak Pickup Times (by hour)
        $result = $db_conn->query("SELECT HOUR(pickuptime) as hour, COUNT(*) as count FROM bookings WHERE pickuptime IS NOT NULL GROUP BY HOUR(pickuptime) ORDER BY hour ASC");
        if($result){
            $peak_times_temp = [];
            for ($h = 0; $h < 24; $h++) { // Initialize all hours to 0
                $peak_times_temp[date("H", strtotime("$h:00:00"))] = 0;
            }
            while($row = $result->fetch_assoc()){
                 $hour_24_format = str_pad($row['hour'], 2, '0', STR_PAD_LEFT); // Ensure 2 digits like 09, 13
                 $peak_times_temp[$hour_24_format] = (int)$row['count'];
            }
            ksort($peak_times_temp); // Sort by 24hr numeric key
            foreach($peak_times_temp as $hour_key => $count_val){
                $report_data['peak_pickup_times'][date("h A", strtotime("{$hour_key}:00:00"))] = $count_val; // Format as 01 AM, 02 PM etc. for display label
            }
        } else error_log("Reports Query Error (Peak Times): " . $db_conn->error);

        // 9. Upcoming Pickups (Next 7 days)
        $result = $db_conn->query("SELECT id, name, pickuplocation, pickupdate, pickuptime, vehicle 
                                     FROM bookings 
                                     WHERE pickupdate BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 7 DAY) 
                                     AND status NOT IN ('Completed', 'Cancelled')
                                     ORDER BY pickupdate ASC, pickuptime ASC");
        if($result) {
            while($row = $result->fetch_assoc()){ $report_data['upcoming_pickups'][] = $row; }
        } else error_log("Reports Query Error (Upcoming Pickups): " . $db_conn->error);
        
        // 10. Bookings with Notes (Recent 10 with notes)
        $result = $db_conn->query("SELECT id, name, notes, created_at FROM bookings WHERE notes IS NOT NULL AND notes != '' ORDER BY created_at DESC LIMIT 10");
        if($result){
            while($row = $result->fetch_assoc()){ $report_data['bookings_with_notes'][] = $row; }
        } else error_log("Reports Query Error (Bookings with Notes): " . $db_conn->error);


    } catch (Exception | mysqli_sql_exception $e) {
        $report_error_message = "Error generating reports: " . $e->getMessage();
        error_log("Reports Page Exception: " . $report_error_message);
        $report_message = "There was an error generating report data.";
    }
    $db_conn->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Reports - Admin Panel</title> <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
    <style>
        @import url('https://fonts.googleapis.com/css2?family=Montserrat:wght@300;400;500;600;700;800&display=swap');
        body { font-family: 'Montserrat', sans-serif; background-color: #000; color: #fff; min-height: 100vh; }
        /* .monument and .gold are defined in admin_header.php for header use */
        /* Define them here if used by other elements on this page specifically */
        .monument { font-family: 'Montserrat', sans-serif; font-weight: 800; letter-spacing: -0.5px; }
        .gold { color: #D4AF37; }
        .bg-gold { background-color: #D4AF37; }

        /* Global Button Styles - used by header "Options" and page "Back to Dashboard" button */
        .btn { padding: 0.75rem 1.5rem; border-radius: 0.25rem; font-weight: 600; letter-spacing: 0.5px; transition: all 0.3s ease; cursor: pointer; display: inline-flex; align-items: center; justify-content: center; text-decoration: none; }
        .btn-secondary { background-color: rgba(255, 255, 255, 0.1); color: white; border: 1px solid rgba(255, 255, 255, 0.2); }
        .btn-secondary:hover { background-color: rgba(255, 255, 255, 0.2); }
        
        .report-card {
            background-color: #1a1a1a; 
            border: 1px solid rgba(212, 175, 55, 0.2); 
            border-radius: 0.5rem; 
            padding: 1.5rem; 
            box-shadow: 0 4px 15px rgba(0,0,0,0.2); 
        }
        .report-card-title {
            font-family: 'Montserrat', sans-serif;
            font-weight: 700; 
            color: #D4AF37; 
            font-size: 1.25rem; 
            margin-bottom: 1rem; 
            padding-bottom: 0.5rem; 
            border-bottom: 1px solid rgba(212, 175, 55, 0.3);
        }
        .report-list-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 0.5rem 0; 
            font-size: 0.875rem; 
            border-bottom: 1px solid rgba(255,255,255,0.07);
        }
        .report-list-item:last-child { border-bottom: none; }
        .report-list-item .label { color: rgba(255,255,255,0.7); }
        .report-list-item .value { font-weight: 600; color: #fff; }
        .chart-container { position: relative; height:300px; width:100%; }

        /* Dropdown styles are now in admin_header.php. REMOVED from here. */
        
        .text-small {font-size: 0.8rem;}
        .text-muted {color: rgba(255,255,255,0.5);}
    </style>
</head>
<body class="flex flex-col">

    <?php require_once 'admin_header.php'; // ?>

    <main class="flex-grow max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10">
        <h2 class="monument text-4xl font-bold mb-10 text-center">Reporting <span class="gold">Dashboard</span></h2>

        <?php if (!empty($report_message) && !empty($report_error_message)): /* Only show if there's an error message */ ?>
            <div class="bg-red-800 border-red-600 text-red-100 p-4 rounded-lg text-center mb-8 shadow-lg">
                <p class="text-lg font-semibold">Error Loading Reports</p>
                <p><?php echo htmlspecialchars($report_message); ?></p>
            </div>
        <?php elseif (!empty($report_message)): /* For general messages like no data, if no error */ ?>
             <div class="bg-gray-800 text-gray-300 p-4 rounded-lg text-center mb-8 shadow-lg">
                <p class="text-lg"><?php echo htmlspecialchars($report_message); ?></p>
            </div>
        <?php endif; ?>


        <?php if (empty($report_error_message)) : ?>
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-book-open mr-2"></i>Total Bookings</h3>
                <p class="text-5xl font-extrabold gold text-center"><?php echo $report_data['summary']['total_bookings']; ?></p>
            </div>

            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-check-circle mr-2"></i>Bookings by Status</h3>
                <?php if (!empty($report_data['bookings_by_status'])): ?>
                    <div class="chart-container">
                        <canvas id="statusChart"></canvas>
                    </div>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No status data available.</p>'; endif; ?>
            </div>
            
            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-car mr-2"></i>Bookings by Vehicle</h3>
                <?php if (!empty($report_data['bookings_by_vehicle'])): ?>
                    <ul>
                    <?php foreach ($report_data['bookings_by_vehicle'] as $vehicle => $count): ?>
                        <li class="report-list-item"><span class="label"><?php echo htmlspecialchars($vehicle); ?>:</span> <span class="value"><?php echo $count; ?></span></li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No vehicle data available.</p>'; endif; ?>
            </div>
        </div>

        <div class="grid grid-cols-1 lg:grid-cols-1 gap-6 mb-8"> <div class="report-card lg:col-span-1"> <h3 class="report-card-title"><i class="fas fa-chart-line mr-2"></i>Monthly Booking Trends (Last 12 Months)</h3>
                <?php if (!empty($report_data['booking_trends_monthly']['labels']) && !empty($report_data['booking_trends_monthly']['data'])): ?>
                     <div class="chart-container" style="height:350px;">
                        <canvas id="monthlyTrendsChart"></canvas>
                    </div>
                <?php else: echo '<p class="text-gray-400 text-center py-10">Not enough data for monthly trends.</p>'; endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 mb-8">
            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-map-marker-alt mr-2"></i>Top Pickup Locations</h3>
                <?php if (!empty($report_data['popular_pickup_locations'])): ?>
                    <ul>
                    <?php foreach ($report_data['popular_pickup_locations'] as $loc): ?>
                        <li class="report-list-item"><span class="label truncate" title="<?php echo htmlspecialchars($loc['pickuplocation']); ?>"><?php echo htmlspecialchars($loc['pickuplocation']); ?>:</span> <span class="value"><?php echo $loc['count']; ?></span></li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No pickup location data.</p>'; endif; ?>
            </div>

            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-map-pin mr-2"></i>Top Dropoff Locations</h3>
                <?php if (!empty($report_data['popular_dropoff_locations'])): ?>
                    <ul>
                    <?php foreach ($report_data['popular_dropoff_locations'] as $loc): ?>
                        <li class="report-list-item"><span class="label truncate" title="<?php echo htmlspecialchars($loc['dropofflocation']); ?>"><?php echo htmlspecialchars($loc['dropofflocation']); ?>:</span> <span class="value"><?php echo $loc['count']; ?></span></li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No dropoff location data.</p>'; endif; ?>
            </div>
            
            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-route mr-2"></i>Most Common Routes</h3>
                <?php if (!empty($report_data['common_routes'])): ?>
                    <ul>
                    <?php foreach ($report_data['common_routes'] as $route): ?>
                        <li class="report-list-item">
                            <span class="label text-small truncate" title="<?php echo htmlspecialchars($route['pickuplocation']) . ' to ' . htmlspecialchars($route['dropofflocation']); ?>"><?php echo htmlspecialchars($route['pickuplocation']); ?> <i class="fas fa-long-arrow-alt-right text-muted mx-1"></i> <?php echo htmlspecialchars($route['dropofflocation']); ?></span> 
                            <span class="value"><?php echo $route['count']; ?></span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No common route data.</p>'; endif; ?>
            </div>
        </div>
        
        <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-8">
            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-clock mr-2"></i>Peak Pickup Times (By Hour)</h3>
                 <?php if (!empty($report_data['peak_pickup_times'])): ?>
                    <div class="chart-container" style="height:350px;">
                        <canvas id="peakTimesChart"></canvas>
                    </div>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No pickup time data.</p>'; endif; ?>
            </div>

            <div class="report-card">
                <h3 class="report-card-title"><i class="fas fa-calendar-check mr-2"></i>Upcoming Pickups (Next 7 Days)</h3>
                <?php if (!empty($report_data['upcoming_pickups'])): ?>
                    <ul class="max-h-96 overflow-y-auto">
                    <?php foreach ($report_data['upcoming_pickups'] as $booking): ?>
                        <li class="report-list-item">
                            <span class="label">
                                <?php echo htmlspecialchars($booking['name']); ?> (ID: <?php echo $booking['id']; ?>) <br>
                                <span class="text-muted text-small truncate" title="<?php echo htmlspecialchars($booking['pickuplocation']); ?>"><?php echo htmlspecialchars($booking['pickuplocation']); ?></span>
                            </span> 
                            <span class="value text-small text-right">
                                <?php echo date("M d, Y", strtotime($booking['pickupdate'])); ?><br>
                                <span class="gold"><?php echo date("h:i A", strtotime($booking['pickuptime'])); ?></span>
                            </span>
                        </li>
                    <?php endforeach; ?>
                    </ul>
                <?php else: echo '<p class="text-gray-400 text-center py-10">No upcoming pickups in the next 7 days.</p>'; endif; ?>
            </div>
        </div>
        
        <div class="report-card mb-8">
            <h3 class="report-card-title"><i class="fas fa-sticky-note mr-2"></i>Recent Bookings with Notes</h3>
            <?php if (!empty($report_data['bookings_with_notes'])): ?>
                <ul class="max-h-80 overflow-y-auto">
                <?php foreach ($report_data['bookings_with_notes'] as $booking): ?>
                    <li class="report-list-item flex-col items-start">
                        <div class="w-full flex justify-between items-center mb-1">
                            <span class="label font-semibold"><?php echo htmlspecialchars($booking['name']); ?> - ID: <?php echo $booking['id']; ?></span>
                            <span class="value text-small text-muted"><?php echo date("M d, Y, H:i", strtotime($booking['created_at'])); ?></span>
                        </div>
                        <p class="text-gray-300 text-sm mt-1 p-2 bg-gray-700 rounded w-full whitespace-pre-wrap break-words">"<?php echo htmlspecialchars($booking['notes']); ?>"</p>
                    </li>
                <?php endforeach; ?>
                </ul>
            <?php else: echo '<p class="text-gray-400 text-center py-10">No recent bookings with notes found.</p>'; endif; ?>
        </div>

        <?php endif; // end if no report error ?>

        <div class="text-center mt-10">
            <a href="dashboard.php" class="btn btn-secondary"><i class="fas fa-arrow-left mr-2"></i> Back to Dashboard</a>
        </div>
    </main>

    <footer class="bg-black py-4 text-center text-gray-600 text-sm border-t border-gray-800 mt-auto">
        <p>&copy; <?php echo date('Y'); ?> Luxury Limousine. All rights reserved.</p> </footer>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // MODIFIED: Removed optionsDropdownBtn and optionsDropdownContent JS as it's now in admin_header.php

    // Chart.js Initializations
    <?php if (empty($report_error_message)): ?>
    const chartFontColor = '#ccc'; // For ticks and legend labels
    const chartGridColor = 'rgba(255,255,255,0.1)';

    // 1. Status Chart (Pie Chart)
    const statusCtx = document.getElementById('statusChart');
    if (statusCtx && <?php echo !empty($report_data['bookings_by_status']) ? 'true' : 'false'; ?>) {
        const statusLabels = <?php echo json_encode(array_keys($report_data['bookings_by_status'])); ?>;
        const statusData = <?php echo json_encode(array_values($report_data['bookings_by_status'])); ?>;
        
        // Define a consistent color map for statuses
        const statusColorMap = {
            'Pending': '#FFA500',    // Orange
            'Confirmed': '#28a745',  // Green
            'Completed': '#17a2b8',  // Cyan/Blue
            'Cancelled': '#dc3545',  // Red
            // Add more statuses and their colors if they exist
        };
        const statusBackgroundColors = statusLabels.map(label => statusColorMap[label] || '#6c757d'); // Default to gray

        new Chart(statusCtx, {
            type: 'doughnut',
            data: {
                labels: statusLabels,
                datasets: [{
                    label: 'Bookings by Status',
                    data: statusData,
                    backgroundColor: statusBackgroundColors,
                    borderColor: '#111827', // Darker border for segments, matches report card bg potentially
                    borderWidth: 2
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { color: chartFontColor, boxWidth: 15, padding: 20 }
                    },
                    tooltip: {
                        callbacks: {
                            label: function(context) {
                                let label = context.label || '';
                                if (label) {
                                    label += ': ';
                                }
                                if (context.parsed !== null) {
                                    label += context.parsed;
                                }
                                const total = context.dataset.data.reduce((acc, val) => acc + val, 0);
                                const percentage = total > 0 ? ((context.parsed / total) * 100).toFixed(1) + '%' : '0%';
                                label += ` (${percentage})`;
                                return label;
                            }
                        }
                    }
                }
            }
        });
    }

    // 2. Monthly Trends Chart (Line Chart)
    const monthlyTrendsCtx = document.getElementById('monthlyTrendsChart');
    if (monthlyTrendsCtx && <?php echo !empty($report_data['booking_trends_monthly']['labels']) && !empty($report_data['booking_trends_monthly']['data']) ? 'true' : 'false'; ?>) {
        new Chart(monthlyTrendsCtx, {
            type: 'line',
            data: {
                labels: <?php echo json_encode($report_data['booking_trends_monthly']['labels']); ?>,
                datasets: [{
                    label: 'Bookings',
                    data: <?php echo json_encode($report_data['booking_trends_monthly']['data']); ?>,
                    borderColor: '#D4AF37',
                    backgroundColor: 'rgba(212, 175, 55, 0.15)', // More subtle fill
                    tension: 0.3,
                    fill: true,
                    pointBackgroundColor: '#D4AF37',
                    pointBorderColor: '#fff',
                    pointHoverBackgroundColor: '#fff',
                    pointHoverBorderColor: '#D4AF37'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true, ticks: { color: chartFontColor, stepSize: 1 }, grid: { color: chartGridColor, drawBorder: false } },
                    x: { ticks: { color: chartFontColor }, grid: { color: chartGridColor, display: false } }
                },
                plugins: { legend: { display: true, labels: {color: chartFontColor} } }
            }
        });
    }
    
    // 3. Peak Pickup Times (Bar Chart)
    const peakTimesCtx = document.getElementById('peakTimesChart');
    if (peakTimesCtx && <?php echo !empty($report_data['peak_pickup_times']) ? 'true' : 'false'; ?>) {
        const peakTimeLabels = <?php echo json_encode(array_keys($report_data['peak_pickup_times'])); ?>;
        const peakTimeData = <?php echo json_encode(array_values($report_data['peak_pickup_times'])); ?>;
        const maxPeakTimeData = peakTimeData.length > 0 ? Math.max(...peakTimeData) : 1;

        new Chart(peakTimesCtx, {
            type: 'bar',
            data: {
                labels: peakTimeLabels,
                datasets: [{
                    label: 'Bookings by Hour',
                    data: peakTimeData,
                    backgroundColor: 'rgba(212, 175, 55, 0.7)', 
                    borderColor: '#D4AF37',
                    borderWidth: 1,
                    borderRadius: 4, // Rounded bars
                    hoverBackgroundColor: 'rgba(212, 175, 55, 0.9)'
                }]
            },
            options: {
                indexAxis: 'x', 
                responsive: true,
                maintainAspectRatio: false,
                 scales: {
                    y: { beginAtZero: true, ticks: { color: chartFontColor, stepSize: Math.max(1, Math.ceil(maxPeakTimeData / 5)) }, grid: { color: chartGridColor, drawBorder: false } },
                    x: { ticks: { color: chartFontColor }, grid: { color: chartGridColor, display: false } }
                },
                plugins: {
                    legend: { display: false } // Legend can be redundant if only one dataset
                }
            }
        });
    }
    <?php endif; ?>
});
</script>
</body>
</html>