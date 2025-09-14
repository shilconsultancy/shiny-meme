<?php
// admin/admin_header.php
// Ensure session is active. Most pages that include this will likely have
// started a session already (e.g., via auth_check.php if you use one).
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}
?>
<head>
    <link rel="apple-touch-icon" sizes="180x180" href="https://images.luxurylimousine.dk/favicons/apple-touch-icon.png">
    <link rel="icon" type="image/png" sizes="32x32" href="https://images.luxurylimousine.dk/favicons/favicon-32x32.png">
    <link rel="icon" type="image/png" sizes="16x16" href="https://images.luxurylimousine.dk/favicons/favicon-16x16.png">
    <link rel="manifest" href="https://images.luxurylimousine.dk/favicons/site.webmanifest">
    <link rel="mask-icon" href="https://images.luxurylimousine.dk/favicons/safari-pinned-tab.svg" color="#A68D5a"> <meta name="msapplication-TileColor" content="#A68D5a"> <meta name="theme-color" content="#ffffff"> <link rel="shortcut icon" href="https://images.luxurylimousine.dk/favicons/favicon.ico"> </head>
<style>
    /* Essential Styles for this Header Component */
    /* Ensure Montserrat font is loaded globally on the page via @import or <link> */
    .monument { /* For the "Luxury Limousine" title */
        font-family: 'Montserrat', sans-serif;
        font-weight: 800;
        letter-spacing: -0.5px;
    }
    .gold { color: #D4AF37; } /* For gold colored text */

    /* Styles for the Dropdown Menu functionality */
    .dropdown { /* Container for the button and dropdown content */
        position: relative; /* Establishes a positioning context */
        display: inline-block; /* Allows it to sit nicely in the nav */
    }
    .dropdown-content { /* The actual dropdown box */
        display: none; /* Hidden by default, shown by JS */
        position: absolute; /* Positioned relative to .dropdown */
        right: 0; /* Aligns to the right of the .dropdown container */
        background-color: #1a1a1a; /* Dark background for the dropdown */
        min-width: 220px; /* Adjusted width for potentially longer items */
        box-shadow: 0px 8px 16px 0px rgba(0,0,0,0.6);
        z-index: 100; /* Ensures it appears above other page content */
        border-radius: 0.25rem; /* Tailwind: rounded-md */
        border: 1px solid rgba(212, 175, 55, 0.3); /* Gold accent border */
        margin-top: 0.5rem; /* Tailwind: mt-2. Space between button and dropdown */
        overflow: hidden; /* Important for border-radius on children */
    }
    .dropdown-content a { /* Styling for links within the dropdown */
        color: #ccc; /* Light gray text for links */
        padding: 12px 16px; /* Tailwind: px-4 py-3 or similar */
        text-decoration: none; /* Removes underline from links */
        display: flex; /* Aligns icon and text. Tailwind: flex */
        align-items: center; /* Tailwind: items-center */
        font-size: 0.875rem; /* Tailwind: text-sm */
        transition: background-color 0.2s ease, color 0.2s ease;
        white-space: nowrap; /* Prevents text from wrapping to a new line */
    }
    .dropdown-content a:hover {
        background-color: rgba(212, 175, 55, 0.15); /* Goldish hover */
        color: #D4AF37; /* Gold text on hover */
    }
    .dropdown-content a i.fa-fw { /* Styling for FontAwesome icons in links */
        margin-right: 0.75rem; /* Tailwind: mr-3. Space between icon and text */
        width: 1.25em; /* Gives icons a consistent width */
        text-align: center; /* Centers icons if they have varying actual widths */
    }
    .dropdown-content.show-dropdown { /* Class added by JavaScript to show the dropdown */
        display: block; /* Makes the dropdown visible. Tailwind: block */
    }
</style>
<header class="bg-black py-4 shadow-lg sticky top-0 z-50 border-b border-gray-800">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex justify-between items-center">
        <a href="dashboard.php" class="flex-shrink-0">
            <img src="https://images.luxurylimousine.dk/luxurylimosine-logo-v3.png"
                 alt="Luxury Limousine Logo"
                 class="h-10 md:h-12 w-auto object-contain"/>
        </a>        <nav class="flex items-center">
            <span class="text-gray-400 mr-4 hidden md:inline">Welcome, <span class="gold"><?php echo isset($_SESSION['admin_username']) ? htmlspecialchars($_SESSION['admin_username']) : 'Admin'; ?></span>!</span>
            <div class="dropdown ml-2">
                <button id="optionsDropdownBtn" class="btn btn-secondary text-sm px-3 py-2 flex items-center">
                    <i class="fas fa-bars mr-2"></i> Options <i class="fas fa-chevron-down fa-xs ml-2 opacity-75"></i>
                </button>
                <div id="optionsDropdownContent" class="dropdown-content">
                    <a href="dashboard.php"><i class="fas fa-home fa-fw"></i> Home</a>
                    <a href="profile.php"><i class="fas fa-user-circle fa-fw"></i> Profile</a>
                    <a href="all-bookings.php"><i class="fas fa-list-alt fa-fw"></i> All Bookings</a>
                    <a href="https://booking.luxurylimousine.dk/" target="_blank"><i class="fas fa-calendar-plus fa-fw"></i> Add New Booking</a>
                    <a href="add_user.php"><i class="fas fa-user-plus fa-fw"></i> Add New User</a>
                    <a href="reports.php"><i class="fas fa-chart-line fa-fw"></i> Reports</a>
                    <a href="logout.php"><i class="fas fa-sign-out-alt fa-fw"></i> Logout</a>
                </div>
            </div>
        </nav>
    </div>
</header>
<script>
// JavaScript for the header dropdown menu
document.addEventListener('DOMContentLoaded', function() {
    const optionsDropdownBtn = document.getElementById('optionsDropdownBtn');
    const optionsDropdownContent = document.getElementById('optionsDropdownContent');

    if (optionsDropdownBtn && optionsDropdownContent) {
        optionsDropdownBtn.addEventListener('click', function(event) {
            event.stopPropagation(); // Prevent the window click event from firing immediately
            optionsDropdownContent.classList.toggle('show-dropdown');
        });

        // Close the dropdown if clicked outside
        window.addEventListener('click', function(event) {
            // Check if the dropdown is visible
            if (optionsDropdownContent.classList.contains('show-dropdown')) {
                // Check if the click was outside the button and outside the dropdown content
                if (!optionsDropdownBtn.contains(event.target) && !optionsDropdownContent.contains(event.target)) {
                    optionsDropdownContent.classList.remove('show-dropdown');
                }
            }
        });
    }
});
</script>