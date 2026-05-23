<?php
require_once 'config.php';

// Check if customer is logged in
if (!isCustomer()) {
    redirect('login_customer.php');
}

$conn = getDBConnection();
$customer_id = $_SESSION['user_id'];

// Add this code to get customer profile picture
$profile_query = "SELECT full_name, profile_picture FROM customer WHERE customer_id = $customer_id";
$profile_result = mysqli_query($conn, $profile_query);
$customer_profile = mysqli_fetch_assoc($profile_result);

// Check if profile picture exists
$profile_pic = !empty($customer_profile['profile_picture']) && file_exists($customer_profile['profile_picture'])
    ? $customer_profile['profile_picture']
    : null;

// Get customer stats
$stats_queries = [
    'total_reports' => "SELECT COUNT(*) as count FROM reports WHERE customer_id = $customer_id",
    'pending_reports' => "SELECT COUNT(*) as count FROM reports WHERE customer_id = $customer_id AND status = 'pending'",
    'completed_reports' => "SELECT COUNT(*) as count FROM reports WHERE customer_id = $customer_id AND status = 'completed'",
    'total_points' => "SELECT points as count FROM customer WHERE customer_id = $customer_id"
];

foreach ($stats_queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $stats[$key] = $row['count'] ?? 0;
}

// Get recent reports
$recent_reports_query = "SELECT * FROM reports WHERE customer_id = $customer_id ORDER BY created_at DESC LIMIT 5";
$recent_reports = mysqli_query($conn, $recent_reports_query);

// Get notifications
$notifications_query = "SELECT * FROM notifications WHERE user_id = $customer_id AND user_type = 'customer' ORDER BY created_at DESC LIMIT 5";
$notifications = mysqli_query($conn, $notifications_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Dashboard - WasteWise</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }

        html,
        body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
            height: 100%;
        }

        /* Background same as login_customer.php */
        body {
            background-image: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.85)), url('https://media.licdn.com/dms/image/v2/D4E12AQHclfeT7LmcfQ/article-cover_image-shrink_720_1280/article-cover_image-shrink_720_1280/0/1719988994510?e=2147483647&v=beta&t=74SoGzgjkc5M0QlK6kDsskfcy0ID8hIC6AxalcWtps4');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            position: relative;
        }

        @media (max-width: 768px) {
            body::before {
                display: none;
            }

            body {
                background-attachment: scroll;
            }
        }

        @media (min-width: 769px) {
            body::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: inherit;
                filter: blur(8px);
                z-index: -1;
                margin: -20px;
            }
        }

        .text-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }

        .gradient-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }

        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }

        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .pulse-animation {
            animation: pulse 2s infinite;
        }

        @keyframes pulse {
            0% {
                box-shadow: 0 0 0 0 rgba(74, 222, 128, 0.4);
            }

            70% {
                box-shadow: 0 0 0 10px rgba(74, 222, 128, 0);
            }

            100% {
                box-shadow: 0 0 0 0 rgba(74, 222, 128, 0);
            }
        }

        .fade-in-up {
            animation: fadeInUp 0.8s ease-out;
        }

        @keyframes fadeInUp {
            from {
                opacity: 0;
                transform: translateY(30px);
            }

            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        /* Safe area for iPhone */
        .safe-area-top {
            padding-top: env(safe-area-inset-top);
        }

        .safe-area-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }

        /* Status badges */
        .status-pending {
            background: #fef3c7;
            color: #92400e;
        }

        .status-in_progress {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-rejected {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Hidden scrollbar */
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }

        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }

        /* Mobile menu */
        .mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }

        .mobile-menu.active {
            transform: translateX(0);
        }

        /* Active menu item */
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #4ade80;
        }

        /* Notification badge */
        .notification-badge {
            animation: bounce 2s infinite;
        }

        @keyframes bounce {

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateY(0);
            }

            40% {
                transform: translateY(-10px);
            }

            60% {
                transform: translateY(-5px);
            }
        }

        /* Scrollable sidebar */
        .scrollable-sidebar {
            height: 100vh;
            overflow-y: auto;
            overflow-x: hidden;
        }

        .scrollable-sidebar::-webkit-scrollbar {
            width: 0px;
            background: transparent;
        }

        .scrollable-sidebar {
            scrollbar-width: none;
        }
    </style>
</head>

<body class="min-h-screen">
    <!-- Mobile Menu -->
    <div class="mobile-menu fixed top-0 left-0 w-64 h-full bg-gray-900 z-50 shadow-2xl p-6 safe-area-top safe-area-bottom no-scrollbar">
        <!-- Mobile Logo -->
        <div class="flex items-center justify-between mb-8">
            <a href="index.php" class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center">
                    <i class="fas fa-recycle text-white text-xl"></i>
                </div>
                <span class="text-white text-xl font-bold">Waste<span class="text-green-400">Wise</span></span>
            </a>
            <button onclick="closeMobileMenu()" class="text-white text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>

        <!-- Mobile User Info -->
        <div class="mb-6 p-4 glass-card rounded-xl">
            <div class="flex items-center space-x-3">
                <?php if ($profile_pic): ?>
                    <img src="<?php echo htmlspecialchars($profile_pic); ?>"
                        alt="<?php echo htmlspecialchars($_SESSION['user_name']); ?>"
                        class="w-12 h-12 rounded-full object-cover border-2 border-white">
                <?php else: ?>
                    <div class="w-12 h-12 rounded-full gradient-bg flex items-center justify-center">
                        <span class="text-white font-bold text-lg">
                            <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                        </span>
                    </div>
                <?php endif; ?>
                <div>
                    <div class="text-white font-semibold"><?php echo $_SESSION['user_name']; ?></div>
                    <div class="text-green-400 text-sm"><?php echo $stats['total_points']; ?> Points</div>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <nav class="space-y-2">
            <a href="customer_dashboard.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
                <i class="fas fa-tachometer-alt w-6 text-center"></i>
                <span>Dashboard</span>
            </a>
            <a href="submit_report.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-plus-circle w-6 text-center"></i>
                <span>New Report</span>
            </a>
            <a href="my_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-flag w-6 text-center"></i>
                <span>My Reports</span>
            </a>
            <a href="track_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-map-marker-alt w-6 text-center"></i>
                <span>Track Reports</span>
            </a>
            <a href="my_profile.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-user w-6 text-center"></i>
                <span>My Profile</span>
            </a>
            <a href="rewards.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-trophy w-6 text-center"></i>
                <span>Rewards</span>
            </a>
            <a href="community.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-users w-6 text-center"></i>
                <span>Community</span>
            </a>
            <a href="logout.php" class="flex items-center space-x-3 text-red-400 p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-sign-out-alt w-6 text-center"></i>
                <span>Logout</span>
            </a>
        </nav>
    </div>

    <!-- Main Layout -->
    <div class="flex min-h-screen">
        <!-- Desktop Sidebar -->
        <aside class="hidden lg:block w-64 bg-gray-900 text-white fixed left-0 top-0 h-screen">
            <div class="scrollable-sidebar safe-area-top safe-area-bottom no-scrollbar">
                <!-- Logo -->
                <div class="p-6 border-b border-gray-800">
                    <a href="index.php" class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center">
                            <i class="fas fa-recycle text-white"></i>
                        </div>
                        <span class="text-xl font-bold">Waste<span class="text-green-400">Wise</span></span>
                    </a>
                </div>

                <!-- User Info -->
                <div class="p-6 border-b border-gray-800">
                    <div class="flex items-center space-x-3 mb-4">
                        <?php if ($profile_pic): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>"
                                alt="<?php echo htmlspecialchars($_SESSION['user_name']); ?>"
                                class="w-12 h-12 rounded-full object-cover border-2 border-white">
                        <?php else: ?>
                            <div class="w-12 h-12 rounded-full gradient-bg flex items-center justify-center">
                                <span class="text-white font-bold text-lg">
                                    <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                </span>
                            </div>
                        <?php endif; ?>
                        <div>
                            <div class="font-semibold"><?php echo $_SESSION['user_name']; ?></div>
                            <div class="text-green-400 text-sm"><?php echo $stats['total_points']; ?> Points</div>
                        </div>
                    </div>
                    <div class="text-gray-400 text-sm">
                        <i class="fas fa-envelope mr-2"></i>
                        <?php echo $_SESSION['user_email']; ?>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="p-4 space-y-2">
                    <a href="customer_dashboard.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
                        <i class="fas fa-tachometer-alt w-6 text-center"></i>
                        <span>Dashboard</span>
                    </a>
                    <a href="submit_report.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-plus-circle w-6 text-center"></i>
                        <span>New Report</span>
                    </a>
                    <a href="my_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-flag w-6 text-center"></i>
                        <span>My Reports</span>
                    </a>
                    <a href="track_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-map-marker-alt w-6 text-center"></i>
                        <span>Track Reports</span>
                    </a>
                    <a href="my_profile.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-user w-6 text-center"></i>
                        <span>My Profile</span>
                    </a>
                    <a href="rewards.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-trophy w-6 text-center"></i>
                        <span>Rewards</span>
                    </a>
                    <a href="community.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span>Community</span>
                    </a>
                </nav>

                <!-- Footer -->
                <div class="p-6 border-t border-gray-800">
                    <a href="logout.php" class="flex items-center space-x-3 text-red-400 p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-sign-out-alt w-6 text-center"></i>
                        <span>Logout</span>
                    </a>
                </div>
            </div>
        </aside>

        <!-- Main Content -->
        <div class="flex-1 lg:ml-64">
            <!-- Top Header -->
            <header class="glass-card px-6 py-4 safe-area-top mx-6 mt-6 rounded-2xl backdrop-blur-lg">
                <div class="flex items-center justify-between">
                    <!-- Mobile Menu Button -->
                    <button onclick="openMobileMenu()" class="lg:hidden text-white text-2xl">
                        <i class="fas fa-bars"></i>
                    </button>

                    <!-- Page Title -->
                    <div class="hidden lg:block">
                        <h1 class="text-2xl font-bold text-white">Dashboard</h1>
                        <p class="text-gray-300 text-sm">Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Quick Report Button -->
                        <a href="submit_report.php"
                            class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-plus mr-2"></i>
                            Report Waste
                        </a>

                        <!-- Notifications -->
                        <div class="relative">
                            <button onclick="toggleNotifications()" class="w-10 h-10 rounded-full bg-white/10 flex items-center justify-center hover:bg-white/20 transition-colors">
                                <i class="fas fa-bell text-white"></i>
                                <?php if (mysqli_num_rows($notifications) > 0): ?>
                                    <span class="absolute -top-1 -right-1 w-5 h-5 bg-red-500 text-white text-xs rounded-full flex items-center justify-center notification-badge">
                                        <?php echo mysqli_num_rows($notifications); ?>
                                    </span>
                                <?php endif; ?>
                            </button>

                            <!-- Notifications Dropdown -->
                            <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-80 bg-gray-900 rounded-xl shadow-lg border border-gray-700 z-50">
                                <div class="p-4 border-b border-gray-700">
                                    <div class="flex justify-between items-center">
                                        <h3 class="font-bold text-white">Notifications</h3>
                                        <a href="notifications.php" class="text-sm text-green-400 hover:text-green-300">View All</a>
                                    </div>
                                </div>
                                <div class="max-h-96 overflow-y-auto">
                                    <?php
                                    mysqli_data_seek($notifications, 0);
                                    while ($notification = mysqli_fetch_assoc($notifications)):
                                    ?>
                                        <div class="p-4 border-b border-gray-800 hover:bg-gray-800 transition-colors <?php echo !$notification['is_read'] ? 'bg-blue-900/20' : ''; ?>">
                                            <div class="flex items-start space-x-3">
                                                <div class="w-10 h-10 rounded-full <?php echo !$notification['is_read'] ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-800 text-gray-400'; ?> flex items-center justify-center">
                                                    <?php
                                                    switch ($notification['type']) {
                                                        case 'status_update':
                                                            echo '<i class="fas fa-sync-alt"></i>';
                                                            break;
                                                        case 'new_report':
                                                            echo '<i class="fas fa-flag"></i>';
                                                            break;
                                                        default:
                                                            echo '<i class="fas fa-bell"></i>';
                                                    }
                                                    ?>
                                                </div>
                                                <div class="flex-1">
                                                    <h4 class="font-semibold text-white text-sm"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                                    <p class="text-gray-300 text-sm mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                                    <div class="text-gray-500 text-xs mt-2">
                                                        <?php echo date('M d, Y h:i A', strtotime($notification['created_at'])); ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                </div>
                            </div>
                        </div>

                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                <div class="text-gray-300 text-sm">Customer</div>
                            </div>
                            <?php if ($profile_pic): ?>
                                <img src="<?php echo htmlspecialchars($profile_pic); ?>"
                                    alt="<?php echo htmlspecialchars($_SESSION['user_name']); ?>"
                                    class="w-10 h-10 rounded-full object-cover border-2 border-green-500">
                            <?php else: ?>
                                <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center">
                                    <span class="text-white font-bold">
                                        <?php echo strtoupper(substr($_SESSION['user_name'], 0, 1)); ?>
                                    </span>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Mobile Page Title -->
                <div class="lg:hidden mt-4">
                    <h1 class="text-2xl font-bold text-white">Dashboard</h1>
                    <p class="text-gray-300 text-sm">Welcome back, <?php echo $_SESSION['user_name']; ?>!</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Stats Grid -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Reports Card -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Total Reports</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $stats['total_reports']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                                <i class="fas fa-flag text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-chart-line text-green-400 mr-1"></i>
                            Your contribution to cleaner community
                        </div>
                    </div>
                    <!-- Add this to your dashboard -->
                    <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6">
                        <div class="flex justify-between items-center mb-4">
                            <h2 class="text-xl font-bold text-white">Active Tracking</h2>
                            <div id="dashboardTrackingStatus" class="flex items-center space-x-2">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                <span class="text-green-400 text-sm">Live</span>
                            </div>
                        </div>

                        <div id="activeTrackings" class="space-y-3">
                            <!-- Will be populated by JavaScript -->
                            <div class="text-center py-4 text-gray-400" id="noActiveTracking">
                                <i class="fas fa-truck text-2xl mb-2"></i>
                                <p>No active collections being tracked</p>
                            </div>
                        </div>

                        <div class="mt-4">
                            <a href="track_reports.php" class="inline-flex items-center text-green-400 hover:text-green-300">
                                <i class="fas fa-external-link-alt mr-2"></i>
                                Go to Tracking Map
                            </a>
                        </div>
                    </div>

                    <script>
                        // Load active tracking data on dashboard
                        document.addEventListener('DOMContentLoaded', function() {
                            loadActiveTrackings();

                            // Check for updates every 10 seconds
                            setInterval(loadActiveTrackings, 10000);

                            // Listen for storage updates from tracking page
                            window.addEventListener('storage', function(event) {
                                if (event.key === 'last_tracking_data') {
                                    loadActiveTrackings();
                                }
                            });
                        });

                        function loadActiveTrackings() {
                            const trackingData = localStorage.getItem('last_tracking_data');
                            const container = document.getElementById('activeTrackings');
                            const noTracking = document.getElementById('noActiveTracking');

                            if (trackingData) {
                                const data = JSON.parse(trackingData);

                                if (data.teams && data.teams.length > 0) {
                                    noTracking.style.display = 'none';

                                    // Clear existing items
                                    container.querySelectorAll('.tracking-item').forEach(item => item.remove());

                                    // Add active trackings
                                    data.teams.forEach(team => {
                                        const item = document.createElement('div');
                                        item.className = 'tracking-item p-3 bg-white/5 rounded-lg';
                                        item.innerHTML = `
                        <div class="flex justify-between items-center">
                            <div>
                                <div class="font-medium text-white">${team.team_name}</div>
                                <div class="text-gray-300 text-sm">${team.vehicle_number}</div>
                            </div>
                            <div class="text-right">
                                <div class="text-green-400 text-sm font-medium">${team.distance_to_report} km</div>
                                <div class="text-gray-400 text-xs">${team.status}</div>
                            </div>
                        </div>
                        <div class="mt-2">
                            <div class="w-full bg-gray-700 rounded-full h-2">
                                <div class="bg-green-500 h-2 rounded-full" 
                                     style="width: ${Math.max(0, 100 - (team.distance_to_report * 100))}%"></div>
                            </div>
                        </div>
                    `;
                                        container.appendChild(item);
                                    });
                                } else {
                                    noTracking.style.display = 'block';
                                }
                            }
                        }
                    </script>

                    <!-- Pending Reports Card -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Pending Reports</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $stats['pending_reports']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-yellow-500/20 flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-hourglass-half text-yellow-400 mr-1"></i>
                            Currently being reviewed
                        </div>
                    </div>

                    <!-- Completed Reports Card -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Completed</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $stats['completed_reports']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center">
                                <i class="fas fa-check-circle text-green-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-check text-green-400 mr-1"></i>
                            Successfully resolved issues
                        </div>
                    </div>

                    <!-- Reward Points Card -->
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white hover-lift">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm">Reward Points</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo $stats['total_points']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
                                <i class="fas fa-trophy text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-green-100">
                            <i class="fas fa-gift mr-1"></i>
                            Redeem for exciting rewards
                        </div>
                    </div>
                </div>

                <!-- Quick Actions & Recent Reports Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
                    <!-- Quick Actions -->
                    <div class="lg:col-span-1">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg">
                            <h2 class="text-xl font-bold text-white mb-6">Quick Actions</h2>
                            <div class="space-y-4">
                                <a href="submit_report.php" class="flex items-center justify-between p-4 bg-white/5 rounded-xl hover:bg-gradient-to-r hover:from-green-500/10 hover:to-blue-500/10 transition-all hover-lift group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-green-500 to-blue-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <i class="fas fa-plus-circle text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-white">New Report</h4>
                                            <p class="text-gray-300 text-sm">Report waste issue</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-green-400"></i>
                                </a>

                                <a href="track_reports.php" class="flex items-center justify-between p-4 bg-white/5 rounded-xl hover:bg-gradient-to-r hover:from-green-500/10 hover:to-blue-500/10 transition-all hover-lift group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-blue-500 to-purple-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <i class="fas fa-map-marker-alt text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-white">Track Reports</h4>
                                            <p class="text-gray-300 text-sm">Monitor progress</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-blue-400"></i>
                                </a>

                                <a href="my_reports.php" class="flex items-center justify-between p-4 bg-white/5 rounded-xl hover:bg-gradient-to-r hover:from-green-500/10 hover:to-blue-500/10 transition-all hover-lift group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-purple-500 to-pink-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <i class="fas fa-history text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-white">Report History</h4>
                                            <p class="text-gray-300 text-sm">View past reports</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-purple-400"></i>
                                </a>

                                <a href="rewards.php" class="flex items-center justify-between p-4 bg-white/5 rounded-xl hover:bg-gradient-to-r hover:from-green-500/10 hover:to-blue-500/10 transition-all hover-lift group">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-yellow-500 to-orange-500 flex items-center justify-center group-hover:scale-110 transition-transform">
                                            <i class="fas fa-gift text-white"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-white">Redeem Points</h4>
                                            <p class="text-gray-300 text-sm">Claim rewards</p>
                                        </div>
                                    </div>
                                    <i class="fas fa-chevron-right text-gray-400 group-hover:text-yellow-400"></i>
                                </a>
                            </div>
                        </div>
                    </div>

                    <!-- Recent Reports -->
                    <div class="lg:col-span-2">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold text-white">Recent Reports</h2>
                                <a href="my_reports.php" class="text-green-400 hover:text-green-300 font-medium text-sm">
                                    View All <i class="fas fa-arrow-right ml-1"></i>
                                </a>
                            </div>

                            <div class="overflow-x-auto">
                                <table class="w-full">
                                    <thead>
                                        <tr class="border-b border-gray-700">
                                            <th class="text-left py-3 px-4 text-gray-300 font-medium text-sm">Report ID</th>
                                            <th class="text-left py-3 px-4 text-gray-300 font-medium text-sm">Title</th>
                                            <th class="text-left py-3 px-4 text-gray-300 font-medium text-sm">Status</th>
                                            <th class="text-left py-3 px-4 text-gray-300 font-medium text-sm">Date</th>
                                            <th class="text-left py-3 px-4 text-gray-300 font-medium text-sm">Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php
                                        mysqli_data_seek($recent_reports, 0);
                                        while ($report = mysqli_fetch_assoc($recent_reports)):
                                        ?>
                                            <tr class="border-b border-gray-800 hover:bg-white/5 transition-colors">
                                                <td class="py-4 px-4">
                                                    <span class="font-mono text-white">#<?php echo $report['report_id']; ?></span>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <div class="font-medium text-white"><?php echo htmlspecialchars($report['title']); ?></div>
                                                    <div class="text-gray-400 text-xs mt-1">
                                                        <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
                                                    </div>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <span class="px-3 py-1 rounded-full text-xs font-medium status-<?php echo $report['status']; ?>">
                                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                                    </span>
                                                </td>
                                                <td class="py-4 px-4 text-gray-300 text-sm">
                                                    <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                </td>
                                                <td class="py-4 px-4">
                                                    <a href="view_my_report.php?id=<?php echo $report['report_id']; ?>"
                                                        class="text-green-400 hover:text-green-300 text-sm font-medium">
                                                        <i class="fas fa-eye mr-1"></i> View
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>

                            <?php if (mysqli_num_rows($recent_reports) == 0): ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/10 flex items-center justify-center">
                                        <i class="fas fa-flag text-gray-400 text-xl"></i>
                                    </div>
                                    <h3 class="text-gray-300 font-medium">No reports yet</h3>
                                    <p class="text-gray-400 text-sm mt-1">Start by reporting your first waste issue</p>
                                    <a href="submit_report.php" class="inline-block mt-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all">
                                        <i class="fas fa-plus mr-2"></i> Report Now
                                    </a>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifications -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-white">Recent Notifications</h2>
                        <a href="notifications.php" class="text-green-400 hover:text-green-300 font-medium text-sm">
                            View All <i class="fas fa-arrow-right ml-1"></i>
                        </a>
                    </div>

                    <div class="space-y-4">
                        <?php
                        mysqli_data_seek($notifications, 0);
                        while ($notification = mysqli_fetch_assoc($notifications)):
                        ?>
                            <div class="flex items-start space-x-4 p-4 rounded-xl <?php echo !$notification['is_read'] ? 'bg-blue-900/20 border border-blue-800' : 'bg-white/5'; ?> hover:bg-white/10 transition-colors">
                                <div class="w-10 h-10 rounded-full <?php echo !$notification['is_read'] ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-800 text-gray-400'; ?> flex items-center justify-center flex-shrink-0">
                                    <?php
                                    switch ($notification['type']) {
                                        case 'status_update':
                                            echo '<i class="fas fa-sync-alt"></i>';
                                            break;
                                        case 'new_report':
                                            echo '<i class="fas fa-flag"></i>';
                                            break;
                                        default:
                                            echo '<i class="fas fa-bell"></i>';
                                    }
                                    ?>
                                </div>
                                <div class="flex-1">
                                    <div class="flex justify-between items-start">
                                        <h4 class="font-semibold text-white"><?php echo htmlspecialchars($notification['title']); ?></h4>
                                        <span class="text-gray-400 text-xs">
                                            <?php echo date('h:i A', strtotime($notification['created_at'])); ?>
                                        </span>
                                    </div>
                                    <p class="text-gray-300 text-sm mt-1"><?php echo htmlspecialchars($notification['message']); ?></p>
                                    <div class="text-gray-500 text-xs mt-2">
                                        <?php echo date('M d, Y', strtotime($notification['created_at'])); ?>
                                    </div>
                                </div>
                                <?php if (!$notification['is_read']): ?>
                                    <span class="w-2 h-2 bg-blue-500 rounded-full flex-shrink-0 mt-2"></span>
                                <?php endif; ?>
                            </div>
                        <?php endwhile; ?>

                        <?php if (mysqli_num_rows($notifications) == 0): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/10 flex items-center justify-center">
                                    <i class="fas fa-bell text-gray-400 text-xl"></i>
                                </div>
                                <h3 class="text-gray-300 font-medium">No notifications yet</h3>
                                <p class="text-gray-400 text-sm mt-1">You'll see notifications here when you have updates</p>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 lg:hidden z-40">
        <a href="submit_report.php" class="w-14 h-14 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
            <i class="fas fa-plus text-white text-xl"></i>
        </a>
    </div>

    <script>
        // Mobile Menu Functions
        function openMobileMenu() {
            document.querySelector('.mobile-menu').classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeMobileMenu() {
            document.querySelector('.mobile-menu').classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close mobile menu when clicking outside
        document.addEventListener('click', function(event) {
            const mobileMenu = document.querySelector('.mobile-menu');
            const menuButton = document.querySelector('[onclick="openMobileMenu()"]');

            if (mobileMenu.classList.contains('active') &&
                !mobileMenu.contains(event.target) &&
                !menuButton.contains(event.target)) {
                closeMobileMenu();
            }
        });

        // Toggle Notifications Dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
        }

        // Close notifications when clicking outside
        document.addEventListener('click', function(event) {
            const dropdown = document.getElementById('notificationsDropdown');
            const button = document.querySelector('[onclick="toggleNotifications()"]');

            if (dropdown && !dropdown.classList.contains('hidden') &&
                !dropdown.contains(event.target) &&
                !button.contains(event.target)) {
                dropdown.classList.add('hidden');
            }
        });

        // Prevent zoom on double-tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
</body>

</html>