<?php
require_once 'config.php';

// Check if customer is logged in
if (!isCustomer()) {
    redirect('login_customer.php');
}

$conn = getDBConnection();
$customer_id = $_SESSION['user_id'];

// Get customer profile picture
$profile_query = "SELECT full_name, profile_picture FROM customer WHERE customer_id = $customer_id";
$profile_result = mysqli_query($conn, $profile_query);
$customer_profile = mysqli_fetch_assoc($profile_result);

// Check if profile picture exists
$profile_pic = !empty($customer_profile['profile_picture']) && file_exists($customer_profile['profile_picture'])
    ? $customer_profile['profile_picture']
    : null;

// Get customer points
$points_query = "SELECT points FROM customer WHERE customer_id = $customer_id";
$points_result = mysqli_query($conn, $points_query);
$points_data = mysqli_fetch_assoc($points_result);
$total_points = $points_data['points'] ?? 0;

// Check for auto-status updates via AJAX
if (isset($_GET['check_status']) && isset($_GET['report_id'])) {
    $report_id = intval($_GET['report_id']);
    
    // Check if report is completed
    $check_query = "SELECT r.collection_status, r.points_awarded, ct.team_name
                    FROM reports r
                    LEFT JOIN collection_teams ct ON r.assigned_team_id = ct.team_id
                    WHERE r.report_id = $report_id AND r.customer_id = $customer_id";
    
    $check_result = mysqli_query($conn, $check_query);
    if ($check_result && mysqli_num_rows($check_result) > 0) {
        $report_data = mysqli_fetch_assoc($check_result);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'collection_status' => $report_data['collection_status'],
            'team_name' => $report_data['team_name'],
            'points_awarded' => $report_data['points_awarded']
        ]);
        exit;
    }
}

// Get reports with coordinates and team assignments
// First, let's check what columns exist in team_assignments table
$check_columns_query = "SHOW COLUMNS FROM team_assignments";
$columns_result = mysqli_query($conn, $check_columns_query);
$columns_exist = [];
while ($column = mysqli_fetch_assoc($columns_result)) {
    $columns_exist[$column['Field']] = true;
}

// Build query based on available columns
$reports_query = "SELECT r.*, 
                  ct.team_name,
                  ct.vehicle_number,
                  ct.current_latitude as team_lat,
                  ct.current_longitude as team_lng,
                  ct.last_updated as team_last_update,";

// Add progress_percentage if it exists
if (isset($columns_exist['progress_percentage'])) {
    $reports_query .= "ta.progress_percentage,";
} else {
    $reports_query .= "NULL as progress_percentage,";
}

$reports_query .= "ta.estimated_arrival_time,
                   ta.status as assignment_status,
                   (SELECT GROUP_CONCAT(c.category_name) 
                    FROM report_categories rc 
                    JOIN waste_categories c ON rc.category_id = c.category_id 
                    WHERE rc.report_id = r.report_id) as categories
                   FROM reports r 
                   LEFT JOIN collection_teams ct ON r.assigned_team_id = ct.team_id
                   LEFT JOIN team_assignments ta ON r.report_id = ta.report_id AND ta.team_id = ct.team_id
                   WHERE r.customer_id = $customer_id 
                   ORDER BY r.created_at DESC";

$reports_result = mysqli_query($conn, $reports_query);

// Check if query failed
if (!$reports_result) {
    // Try simpler query without team_assignments join
    $reports_query = "SELECT r.*, 
                      ct.team_name,
                      ct.vehicle_number,
                      ct.current_latitude as team_lat,
                      ct.current_longitude as team_lng,
                      ct.last_updated as team_last_update,
                      NULL as progress_percentage,
                      NULL as estimated_arrival_time,
                      NULL as assignment_status,
                      (SELECT GROUP_CONCAT(c.category_name) 
                       FROM report_categories rc 
                       JOIN waste_categories c ON rc.category_id = c.category_id 
                       WHERE rc.report_id = r.report_id) as categories
                      FROM reports r 
                      LEFT JOIN collection_teams ct ON r.assigned_team_id = ct.team_id
                      WHERE r.customer_id = $customer_id 
                      ORDER BY r.created_at DESC";

    $reports_result = mysqli_query($conn, $reports_query);

    if (!$reports_result) {
        // Try simplest query
        $reports_query = "SELECT r.* 
                         FROM reports r 
                         WHERE r.customer_id = $customer_id 
                         ORDER BY r.created_at DESC";
        $reports_result = mysqli_query($conn, $reports_query);

        if (!$reports_result) {
            die("Database error: " . mysqli_error($conn));
        }
    }
}

// Get assigned teams with their reports
$teams_query = "SELECT DISTINCT ct.*,
                r.report_id,
                r.collection_status,
                r.created_at as report_date
                FROM collection_teams ct
                JOIN reports r ON ct.team_id = r.assigned_team_id
                WHERE r.customer_id = $customer_id 
                AND r.assigned_team_id IS NOT NULL
                AND r.collection_status NOT IN ('collected', 'failed')
                ORDER BY r.created_at DESC";

$teams_result = mysqli_query($conn, $teams_query);

if (!$teams_result) {
    $teams_result = false;
}

// For demo purposes, let's calculate progress based on time
// In a real system, this would come from actual tracking data
function calculateProgress($report_date) {
    $created_time = strtotime($report_date);
    $current_time = time();
    $elapsed_time = $current_time - $created_time;
    
    // Assume collection takes 1 hour (3600 seconds)
    $total_duration = 3600;
    $progress = min(100, ($elapsed_time / $total_duration) * 100);
    
    return round($progress);
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Track Reports - WasteWise</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.css" />

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

        /* Background same as customer_dashboard.php */
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
        .status-not_assigned {
            background: #f3f4f6;
            color: #374151;
        }

        .status-assigned {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-in_transit {
            background: #c7d2fe;
            color: #3730a3;
        }

        .status-collecting {
            background: #a5f3fc;
            color: #155e75;
        }

        .status-collected {
            background: #dcfce7;
            color: #166534;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

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

        /* Custom styles for tracking */
        .live-indicator {
            width: 8px;
            height: 8px;
            background: #ef4444;
            border-radius: 50%;
            display: inline-block;
            animation: pulse 1.5s infinite;
        }

        @keyframes pulse {
            0% {
                transform: scale(1);
                opacity: 1;
            }

            50% {
                transform: scale(1.5);
                opacity: 0.7;
            }

            100% {
                transform: scale(1);
                opacity: 1;
            }
        }

        .distance-badge {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .team-tracking-card {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 16px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .assignment-status {
            padding: 2px 8px;
            border-radius: 12px;
            font-size: 0.7rem;
            font-weight: 600;
        }

        .status-assigned {
            background: #dbeafe;
            color: #1e40af;
        }

        .status-in_progress {
            background: #fef3c7;
            color: #92400e;
        }

        .status-completed {
            background: #dcfce7;
            color: #166534;
        }

        .status-failed {
            background: #fee2e2;
            color: #991b1b;
        }

        /* Map container */
        #trackingMap {
            border-radius: 16px;
            overflow: hidden;
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }

        .line-clamp-2 {
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            overflow: hidden;
        }

        /* Progress bar */
        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.2);
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }

        .progress-fill {
            height: 100%;
            background: #4ade80;
            border-radius: 4px;
            transition: width 0.3s ease;
        }

        .progress-label {
            display: flex;
            justify-content: space-between;
            margin-bottom: 5px;
            font-size: 0.875rem;
        }

        /* Success animation */
        @keyframes successAnimation {
            0% {
                transform: scale(1);
            }

            50% {
                transform: scale(1.1);
            }

            100% {
                transform: scale(1);
            }
        }

        .success-animation {
            animation: successAnimation 0.5s ease;
        }

        /* Auto-update notification */
        .auto-update-notification {
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 1000;
            animation: slideInRight 0.5s ease;
        }

        @keyframes slideInRight {
            from {
                transform: translateX(100%);
                opacity: 0;
            }

            to {
                transform: translateX(0);
                opacity: 1;
            }
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
                    <div class="text-green-400 text-sm"><?php echo $total_points; ?> Points</div>
                </div>
            </div>
        </div>

        <!-- Mobile Navigation -->
        <nav class="space-y-2">
            <a href="customer_dashboard.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
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
            <a href="track_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
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
                            <div class="text-green-400 text-sm"><?php echo $total_points; ?> Points</div>
                        </div>
                    </div>
                    <div class="text-gray-400 text-sm">
                        <i class="fas fa-envelope mr-2"></i>
                        <?php echo $_SESSION['user_email']; ?>
                    </div>
                </div>

                <!-- Navigation -->
                <nav class="p-4 space-y-2">
                    <a href="customer_dashboard.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
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
                    <a href="track_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
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
                        <h1 class="text-2xl font-bold text-white">Track Reports</h1>
                        <p class="text-gray-300 text-sm">Live tracking of your waste collection reports</p>
                    </div>

                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- New Report Button -->
                        <a href="submit_report.php"
                            class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-plus mr-2"></i>
                            New Report
                        </a>

                        <!-- Tracking Status -->
                        <div class="flex items-center space-x-2">
                            <div id="trackingStatus" class="flex items-center space-x-2 px-3 py-1 bg-green-500/20 rounded-lg">
                                <span class="w-2 h-2 bg-green-500 rounded-full animate-pulse"></span>
                                <span class="text-green-400 text-sm font-medium">Live Tracking Active</span>
                            </div>

                            <!-- User Profile -->
                            <div class="hidden lg:flex items-center space-x-3">
                                <div class="text-right">
                                    <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                    <div class="text-gray-300 text-sm"><?php echo $total_points; ?> Points</div>
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
                </div>

                <!-- Mobile Page Title -->
                <div class="lg:hidden mt-4">
                    <h1 class="text-2xl font-bold text-white">Track Reports</h1>
                    <p class="text-gray-300 text-sm">Live tracking of your waste collection reports</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Team Tracking Section -->
                <?php if ($teams_result && mysqli_num_rows($teams_result) > 0): ?>
                    <div class="mb-6 fade-in-up">
                        <h2 class="text-xl font-bold text-white mb-4">Active Collection Teams</h2>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <?php
                            mysqli_data_seek($teams_result, 0);
                            while ($team = mysqli_fetch_assoc($teams_result)):
                                $progress = calculateProgress($team['report_date']);
                                $collection_status = $team['collection_status'] ?? 'not_assigned';
                                
                                // Determine card color based on status
                                $card_gradient = 'linear-gradient(135deg, #667eea 0%, #764ba2 100%)';
                                if ($progress >= 100 || $collection_status === 'collected') {
                                    $card_gradient = 'linear-gradient(135deg, #10b981 0%, #059669 100%)';
                                } elseif ($collection_status === 'failed') {
                                    $card_gradient = 'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                                }
                            ?>
                                <div class="team-tracking-card hover-lift" style="background: <?php echo $card_gradient; ?>"
                                     data-team-id="<?php echo $team['team_id']; ?>"
                                     data-report-id="<?php echo $team['report_id']; ?>"
                                     data-progress="<?php echo $progress; ?>">
                                    <div class="flex items-center justify-between mb-4">
                                        <div>
                                            <div class="font-bold text-lg"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                            <div class="text-sm opacity-90">Vehicle: <?php echo htmlspecialchars($team['vehicle_number']); ?></div>
                                        </div>
                                        <div class="text-right">
                                            <?php if ($progress < 100 && $collection_status !== 'collected' && $collection_status !== 'failed'): ?>
                                                <div class="px-3 py-1 bg-white/20 rounded-lg text-sm">
                                                    <i class="far fa-clock mr-1"></i> ETA: ~<?php echo ceil((100 - $progress) / 10); ?> min
                                                </div>
                                            <?php endif; ?>
                                            <div class="text-xs mt-2 opacity-70">
                                                <?php if ($progress >= 100 || $collection_status === 'collected'): ?>
                                                    <i class="fas fa-check-circle mr-1"></i> Completed
                                                <?php elseif ($collection_status === 'failed'): ?>
                                                    <i class="fas fa-times-circle mr-1"></i> Failed
                                                <?php else: ?>
                                                    <span class="live-indicator mr-1"></span> Live Tracking
                                                <?php endif; ?>
                                            </div>
                                        </div>
                                    </div>

                                    <div class="progress-container">
                                        <div class="progress-label">
                                            <span>Collection Progress</span>
                                            <span><?php echo $progress; ?>%</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill" style="width: <?php echo min($progress, 100); ?>%"></div>
                                        </div>
                                    </div>

                                    <div class="mt-4 text-sm">
                                        <div class="flex justify-between items-center">
                                            <span>
                                                <i class="fas fa-clipboard-list mr-1"></i>
                                                Status: 
                                                <span class="font-medium">
                                                    <?php 
                                                    if ($progress >= 100 || $collection_status === 'collected') {
                                                        echo 'Completed';
                                                    } elseif ($collection_status === 'failed') {
                                                        echo 'Failed';
                                                    } else {
                                                        echo 'In Progress';
                                                    }
                                                    ?>
                                                </span>
                                            </span>
                                            <?php if ($progress < 100 && $collection_status !== 'collected' && $collection_status !== 'failed'): ?>
                                                <button class="text-sm bg-white/20 hover:bg-white/30 px-3 py-1 rounded-lg transition-colors"
                                                    onclick="focusOnTeam(<?php echo $team['team_id']; ?>)">
                                                    <i class="fas fa-eye mr-1"></i> Track on Map
                                                </button>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Reports List and Map Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-6">
                    <!-- Reports List -->
                    <div class="lg:col-span-1">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold text-white">Your Reports</h2>
                                <span class="text-gray-300 text-sm">
                                    <?php echo mysqli_num_rows($reports_result); ?> reports
                                </span>
                            </div>

                            <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2">
                                <?php
                                if ($reports_result && mysqli_num_rows($reports_result) > 0):
                                    mysqli_data_seek($reports_result, 0);
                                    while ($report = mysqli_fetch_assoc($reports_result)):
                                        $hasCoords = !empty($report['latitude']) && !empty($report['longitude']);
                                        $hasTeam = !empty($report['assigned_team_id']);
                                        $hasTeamLocation = !empty($report['team_lat']) && !empty($report['team_lng']);
                                        $estimated_arrival = isset($report['estimated_arrival_time']) ?
                                            strtotime($report['estimated_arrival_time']) : null;
                                        $teamName = isset($report['team_name']) ? $report['team_name'] : '';
                                        $vehicleNumber = isset($report['vehicle_number']) ? $report['vehicle_number'] : '';
                                        $assignmentStatus = isset($report['assignment_status']) ? $report['assignment_status'] : '';
                                        $collectionStatus = isset($report['collection_status']) ? $report['collection_status'] : 'not_assigned';
                                        $progress = $report['progress_percentage'] ?? calculateProgress($report['created_at']);
                                        
                                        // Check if should be auto-updated
                                        $status_class = 'status-' . $collectionStatus;
                                        $status_text = ucfirst(str_replace('_', ' ', $collectionStatus));
                                        
                                        if ($progress >= 100 && $collectionStatus !== 'collected') {
                                            $status_class = 'status-collected';
                                            $status_text = 'Completed';
                                        } elseif ($collectionStatus === 'failed') {
                                            $status_class = 'status-failed';
                                            $status_text = 'Failed';
                                        }
                                ?>
                                        <div class="p-4 rounded-xl bg-white/5 hover:bg-white/10 transition-colors cursor-pointer hover-lift report-item"
                                            data-id="<?php echo $report['report_id']; ?>"
                                            data-title="<?php echo htmlspecialchars($report['title']); ?>"
                                            data-description="<?php echo htmlspecialchars($report['description']); ?>"
                                            data-address="<?php echo htmlspecialchars($report['address']); ?>"
                                            data-lat="<?php echo isset($report['latitude']) ? $report['latitude'] : ''; ?>"
                                            data-lng="<?php echo isset($report['longitude']) ? $report['longitude'] : ''; ?>"
                                            data-status="<?php echo $collectionStatus; ?>"
                                            data-progress="<?php echo $progress; ?>"
                                            data-date="<?php echo date('M d, Y', strtotime($report['created_at'])); ?>"
                                            data-categories="<?php echo htmlspecialchars($report['categories'] ?? 'N/A'); ?>"
                                            data-team-id="<?php echo $report['assigned_team_id'] ?? ''; ?>"
                                            data-team-name="<?php echo htmlspecialchars($teamName); ?>"
                                            data-team-lat="<?php echo $report['team_lat'] ?? ''; ?>"
                                            data-team-lng="<?php echo $report['team_lng'] ?? ''; ?>"
                                            data-assignment-status="<?php echo $assignmentStatus; ?>"
                                            data-vehicle-number="<?php echo htmlspecialchars($vehicleNumber); ?>">
                                            <div class="flex justify-between items-start">
                                                <div class="flex-1">
                                                    <div class="flex items-center justify-between mb-2">
                                                        <h3 class="font-semibold text-white text-sm"><?php echo htmlspecialchars($report['title']); ?></h3>
                                                        <div class="flex items-center space-x-2">
                                                            <span class="px-2 py-1 rounded-full text-xs font-medium <?php echo $status_class; ?> status-badge"
                                                                  data-original-status="<?php echo $collectionStatus; ?>">
                                                                <?php echo $status_text; ?>
                                                            </span>
                                                            <?php if ($hasTeam && $assignmentStatus && $progress < 100): ?>
                                                                <span class="assignment-status status-<?php echo $assignmentStatus; ?> assignment-badge">
                                                                    <?php echo str_replace('_', ' ', $assignmentStatus); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                    </div>

                                                    <p class="text-gray-300 text-xs mb-3 line-clamp-2"><?php echo htmlspecialchars($report['description']); ?></p>

                                                    <?php if ($hasTeam && $teamName && $progress < 100 && $collectionStatus !== 'collected' && $collectionStatus !== 'failed'): ?>
                                                        <div class="mb-3 p-2 bg-blue-500/10 rounded-lg">
                                                            <div class="flex items-center justify-between">
                                                                <div>
                                                                    <div class="text-blue-300 text-xs font-medium">
                                                                        <i class="fas fa-truck mr-1"></i>
                                                                        <?php echo htmlspecialchars($teamName); ?>
                                                                    </div>
                                                                    <?php if ($vehicleNumber): ?>
                                                                        <div class="text-blue-400 text-xs mt-1">
                                                                            <?php echo htmlspecialchars($vehicleNumber); ?>
                                                                            <?php if ($estimated_arrival): ?>
                                                                                • ETA: <?php echo date('h:i A', $estimated_arrival); ?>
                                                                            <?php endif; ?>
                                                                        </div>
                                                                    <?php endif; ?>
                                                                </div>
                                                                <?php if ($hasTeamLocation): ?>
                                                                    <span class="distance-badge text-xs">
                                                                        <i class="fas fa-location-crosshairs mr-1"></i> Live
                                                                    </span>
                                                                <?php endif; ?>
                                                            </div>
                                                            <?php if ($progress > 0): ?>
                                                                <div class="mt-2">
                                                                    <div class="flex justify-between text-xs text-blue-300 mb-1">
                                                                        <span>Progress</span>
                                                                        <span><?php echo round($progress); ?>%</span>
                                                                    </div>
                                                                    <div class="w-full bg-blue-900/30 rounded-full h-1.5">
                                                                        <div class="bg-blue-500 h-1.5 rounded-full" style="width: <?php echo min($progress, 100); ?>%"></div>
                                                                    </div>
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif ($progress >= 100 || $collectionStatus === 'collected'): ?>
                                                        <div class="mb-3 p-2 bg-green-500/10 rounded-lg">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                                                <span class="text-green-300 text-xs font-medium">Collection completed successfully</span>
                                                            </div>
                                                            <?php if (isset($report['points_awarded']) && $report['points_awarded'] > 0): ?>
                                                                <div class="mt-1 text-green-400 text-xs">
                                                                    <i class="fas fa-trophy mr-1"></i>
                                                                    +<?php echo $report['points_awarded']; ?> points awarded
                                                                </div>
                                                            <?php endif; ?>
                                                        </div>
                                                    <?php elseif ($collectionStatus === 'failed'): ?>
                                                        <div class="mb-3 p-2 bg-red-500/10 rounded-lg">
                                                            <div class="flex items-center">
                                                                <i class="fas fa-times-circle text-red-400 mr-2"></i>
                                                                <span class="text-red-300 text-xs font-medium">Collection failed or was rejected</span>
                                                            </div>
                                                        </div>
                                                    <?php endif; ?>

                                                    <?php if (isset($report['address'])): ?>
                                                        <div class="flex items-center text-gray-400 text-xs mb-2">
                                                            <i class="fas fa-map-marker-alt mr-1"></i>
                                                            <span class="truncate"><?php echo htmlspecialchars($report['address']); ?></span>
                                                        </div>
                                                    <?php endif; ?>

                                                    <div class="flex items-center justify-between text-xs">
                                                        <div class="flex items-center space-x-3">
                                                            <span class="text-gray-400">
                                                                <i class="far fa-clock mr-1"></i>
                                                                <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                            </span>
                                                            <?php if (!empty($report['categories'])): ?>
                                                                <span class="text-gray-400">
                                                                    <i class="fas fa-tags mr-1"></i>
                                                                    <?php echo htmlspecialchars($report['categories']); ?>
                                                                </span>
                                                            <?php endif; ?>
                                                        </div>
                                                        <?php if ($hasCoords && $progress < 100 && $collectionStatus !== 'collected' && $collectionStatus !== 'failed'): ?>
                                                            <button onclick="showReportOnMap(this)"
                                                                class="text-green-400 hover:text-green-300 text-xs font-medium px-2 py-1 bg-green-500/10 rounded-lg hover:bg-green-500/20 transition-colors">
                                                                <i class="fas fa-location-crosshairs mr-1"></i> Show
                                                            </button>
                                                        <?php endif; ?>
                                                    </div>
                                                </div>
                                            </div>
                                        </div>
                                    <?php endwhile; ?>
                                <?php else: ?>
                                    <div class="text-center py-8">
                                        <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/10 flex items-center justify-center">
                                            <i class="fas fa-flag text-gray-400 text-xl"></i>
                                        </div>
                                        <h3 class="text-gray-300 font-medium">No reports yet</h3>
                                        <p class="text-gray-400 text-sm mt-1">Submit your first report to start tracking</p>
                                        <a href="submit_report.php" class="inline-block mt-4 bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all text-sm">
                                            <i class="fas fa-plus mr-2"></i> Report Now
                                        </a>
                                    </div>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>

                    <!-- Live Map -->
                    <div class="lg:col-span-2">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up" style="animation-delay: 0.2s">
                            <div class="flex justify-between items-center mb-6">
                                <h2 class="text-xl font-bold text-white">Live Tracking Map</h2>
                                <div class="flex items-center space-x-2">
                                    <button onclick="useMyLocation()"
                                        class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition-colors hover-lift">
                                        <i class="fas fa-location-crosshairs mr-1"></i> My Location
                                    </button>
                                    <button onclick="resetMap()"
                                        class="px-4 py-2 bg-gray-600 hover:bg-gray-700 text-white rounded-lg text-sm transition-colors hover-lift">
                                        <i class="fas fa-sync-alt mr-1"></i> Reset
                                    </button>
                                </div>
                            </div>

                            <!-- Map Container -->
                            <div id="trackingMap" class="w-full h-[500px] rounded-xl"></div>

                            <!-- Selected Report Info -->
                            <div id="selectedInfo" class="mt-4 p-4 rounded-xl bg-white/5 hidden fade-in-up">
                                <div class="flex justify-between items-start mb-3">
                                    <h3 id="reportTitle" class="font-semibold text-white text-lg"></h3>
                                    <span id="reportStatus" class="px-3 py-1 rounded-full text-xs font-medium"></span>
                                </div>
                                <p id="reportDescription" class="text-gray-300 text-sm mb-3"></p>

                                <!-- Team Info Section -->
                                <div id="teamInfo" class="mb-3 hidden">
                                    <div class="p-3 bg-blue-500/10 rounded-lg">
                                        <div class="flex items-center justify-between mb-2">
                                            <div class="text-blue-300 text-sm font-medium">
                                                <i class="fas fa-truck mr-1"></i>
                                                <span id="assignedTeamName"></span>
                                            </div>
                                            <span id="teamDistance" class="distance-badge"></span>
                                        </div>
                                        <div class="text-blue-400 text-xs">
                                            Vehicle: <span id="teamVehicle"></span>
                                        </div>
                                        <div class="text-blue-400 text-xs mt-1">
                                            Status: <span id="teamAssignmentStatus" class="assignment-status"></span>
                                        </div>
                                        <div class="text-blue-400 text-xs mt-1">
                                            <i class="far fa-clock mr-1"></i>
                                            Estimated Arrival: <span id="teamETA"></span>
                                        </div>
                                        <div id="teamProgress" class="mt-2 hidden">
                                            <div class="flex justify-between text-xs text-blue-300 mb-1">
                                                <span>Progress</span>
                                                <span id="teamProgressPercent">0%</span>
                                            </div>
                                            <div class="w-full bg-blue-900/30 rounded-full h-1.5">
                                                <div id="teamProgressBar" class="bg-blue-500 h-1.5 rounded-full" style="width: 0%"></div>
                                            </div>
                                        </div>
                                    </div>
                                </div>

                                <div class="flex items-center text-gray-400 text-sm mb-2">
                                    <i class="fas fa-map-marker-alt mr-2"></i>
                                    <span id="reportAddress"></span>
                                </div>
                                <div class="flex items-center justify-between text-xs">
                                    <span id="reportDate" class="text-gray-400"></span>
                                    <span id="reportCategories" class="text-gray-400"></span>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 lg:hidden z-40">
        <a href="submit_report.php" class="w-14 h-14 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
            <i class="fas fa-plus text-white text-xl"></i>
        </a>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-routing-machine@latest/dist/leaflet-routing-machine.js"></script>

    <script>
        // Map Variables
        let trackingMap;
        let currentMarkers = [];
        let teamMarkers = [];
        let selectedMarker = null;
        let routingControl = null;
        let statusCheckInterval;
        let completedReports = new Set();
        let failedReports = new Set();

        // Initialize Map
        function initTrackingMap() {
            // Default to Philippines center
            trackingMap = L.map('trackingMap').setView([14.5995, 120.9842], 13);

            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
                maxZoom: 19
            }).addTo(trackingMap);

            // Load reports and teams on map
            loadReportsOnMap();
            loadTeamLocations();

            // Add click event to clear selection
            trackingMap.on('click', function() {
                clearSelection();
            });

            // Start status checking for all reports
            startStatusChecking();
        }

        // Start checking report statuses
        function startStatusChecking() {
            // Check status for all reports every 10 seconds
            statusCheckInterval = setInterval(checkAllReportStatuses, 10000);
            
            // Initial check
            checkAllReportStatuses();
        }

        // Check status for all reports
        function checkAllReportStatuses() {
            const reportItems = document.querySelectorAll('.report-item');
            
            reportItems.forEach(item => {
                const reportId = item.dataset.id;
                const progress = parseInt(item.dataset.progress) || 0;
                const currentStatus = item.dataset.status;
                
                // Skip if already completed or failed
                if (completedReports.has(reportId) || failedReports.has(reportId)) {
                    return;
                }
                
                // Check if progress is 100% or more
                if (progress >= 100) {
                    // Auto-update to completed
                    updateReportStatus(reportId, 'collected', true);
                    completedReports.add(reportId);
                } 
                // Check if should be marked as failed
                else if (currentStatus === 'failed') {
                    // Auto-update to failed
                    updateReportStatus(reportId, 'failed', false);
                    failedReports.add(reportId);
                }
                // Otherwise check server status
                else {
                    checkReportStatus(reportId);
                }
            });
        }

        // Check individual report status from server
        function checkReportStatus(reportId) {
            fetch(`track_reports.php?check_status=1&report_id=${reportId}&t=${Date.now()}`)
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const collectionStatus = data.collection_status;
                        const progress = 0; // Default progress, you might want to calculate this differently
                        
                        // Update progress in dataset
                        const reportItem = document.querySelector(`.report-item[data-id="${reportId}"]`);
                        if (reportItem) {
                            // Check if should auto-update status
                            if (collectionStatus === 'collected' && !completedReports.has(reportId)) {
                                updateReportStatus(reportId, 'collected', true);
                                completedReports.add(reportId);
                            } else if (collectionStatus === 'failed' && !failedReports.has(reportId)) {
                                updateReportStatus(reportId, 'failed', false);
                                failedReports.add(reportId);
                            }
                        }
                    }
                })
                .catch(error => console.error('Error checking report status:', error));
        }

        // Update report status
        function updateReportStatus(reportId, collectionStatus, isSuccess) {
            // Show notification
            showStatusNotification(reportId, isSuccess);
            
            // Find and update the report item
            const reportItem = document.querySelector(`.report-item[data-id="${reportId}"]`);
            if (reportItem) {
                // Update dataset
                reportItem.dataset.status = collectionStatus;
                reportItem.dataset.progress = isSuccess ? 100 : 0;
                
                // Update status badge
                const statusBadge = reportItem.querySelector('.status-badge');
                if (statusBadge) {
                    statusBadge.textContent = isSuccess ? 'Completed' : 'Failed';
                    statusBadge.className = `px-2 py-1 rounded-full text-xs font-medium ${isSuccess ? 'status-collected' : 'status-failed'}`;
                    
                    // Add success animation
                    statusBadge.classList.add('success-animation');
                    setTimeout(() => {
                        statusBadge.classList.remove('success-animation');
                    }, 500);
                }
                
                // Remove assignment badge
                const assignmentBadge = reportItem.querySelector('.assignment-badge');
                if (assignmentBadge) {
                    assignmentBadge.remove();
                }
                
                // Update team info section
                const teamInfo = reportItem.querySelector('.bg-blue-500\\/10');
                if (teamInfo) {
                    if (isSuccess) {
                        teamInfo.className = 'mb-3 p-2 bg-green-500/10 rounded-lg';
                        teamInfo.innerHTML = `
                            <div class="flex items-center">
                                <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                <span class="text-green-300 text-xs font-medium">Collection completed successfully</span>
                            </div>
                            <div class="mt-1 text-green-400 text-xs">
                                <i class="fas fa-trophy mr-1"></i>
                                +10 points awarded
                            </div>
                        `;
                    } else {
                        teamInfo.className = 'mb-3 p-2 bg-red-500/10 rounded-lg';
                        teamInfo.innerHTML = `
                            <div class="flex items-center">
                                <i class="fas fa-times-circle text-red-400 mr-2"></i>
                                <span class="text-red-300 text-xs font-medium">Collection failed or was rejected</span>
                            </div>
                        `;
                    }
                }
                
                // Update Show button
                const showButton = reportItem.querySelector('button[onclick*="showReportOnMap"]');
                if (showButton) {
                    showButton.remove();
                }
                
                // Also update team tracking card if exists
                const teamCard = document.querySelector(`.team-tracking-card[data-report-id="${reportId}"]`);
                if (teamCard) {
                    teamCard.style.background = isSuccess ? 
                        'linear-gradient(135deg, #10b981 0%, #059669 100%)' : 
                        'linear-gradient(135deg, #ef4444 0%, #dc2626 100%)';
                    
                    const liveIndicator = teamCard.querySelector('.live-indicator');
                    if (liveIndicator) {
                        liveIndicator.parentElement.innerHTML = isSuccess ? 
                            '<i class="fas fa-check-circle mr-1"></i> Completed' : 
                            '<i class="fas fa-times-circle mr-1"></i> Failed';
                    }
                    
                    const statusSpan = teamCard.querySelector('.font-medium');
                    if (statusSpan) {
                        statusSpan.textContent = isSuccess ? 'Completed' : 'Failed';
                    }
                    
                    const trackButton = teamCard.querySelector('button[onclick*="focusOnTeam"]');
                    if (trackButton) {
                        trackButton.remove();
                    }
                    
                    // Update progress bar
                    const progressBar = teamCard.querySelector('.progress-fill');
                    if (progressBar) {
                        progressBar.style.width = '100%';
                    }
                    
                    const progressLabel = teamCard.querySelector('.progress-label span:last-child');
                    if (progressLabel) {
                        progressLabel.textContent = '100%';
                    }
                }
            }
            
            // Update map markers
            updateMapMarkers(reportId, collectionStatus);
        }

        // Show status update notification
        function showStatusNotification(reportId, isSuccess) {
            const reportItem = document.querySelector(`.report-item[data-id="${reportId}"]`);
            if (!reportItem) return;
            
            const title = reportItem.dataset.title;
            
            // Create notification
            const notification = document.createElement('div');
            notification.className = `auto-update-notification glass-card p-4 rounded-xl ${isSuccess ? 'border-l-4 border-green-500' : 'border-l-4 border-red-500'}`;
            notification.innerHTML = `
                <div class="flex items-center">
                    <div class="w-10 h-10 rounded-full ${isSuccess ? 'bg-green-500/20' : 'bg-red-500/20'} flex items-center justify-center mr-3">
                        <i class="fas ${isSuccess ? 'fa-check-circle text-green-400' : 'fa-times-circle text-red-400'}"></i>
                    </div>
                    <div>
                        <h4 class="font-semibold text-white">Report ${isSuccess ? 'Completed' : 'Failed'}</h4>
                        <p class="text-gray-300 text-sm">"${title}" has been marked as ${isSuccess ? 'completed' : 'failed'}</p>
                    </div>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            // Remove notification after 5 seconds
            setTimeout(() => {
                notification.style.opacity = '0';
                notification.style.transition = 'opacity 0.5s ease';
                setTimeout(() => notification.remove(), 500);
            }, 5000);
        }

        // Update map markers for completed/failed reports
        function updateMapMarkers(reportId, status) {
            currentMarkers.forEach(marker => {
                if (marker.reportItem && marker.reportItem.dataset.id === reportId) {
                    let iconColor = status === 'collected' ? 'green' : 'red';
                    
                    marker.setIcon(L.divIcon({
                        html: `<div class="w-10 h-10 rounded-full bg-${iconColor}-500/80 border-2 border-white flex items-center justify-center shadow-lg">
                                 <i class="fas ${status === 'collected' ? 'fa-check' : 'fa-times'} text-white text-sm"></i>
                               </div>`,
                        className: 'custom-marker',
                        iconSize: [40, 40],
                        iconAnchor: [20, 40]
                    }));
                    
                    // Update popup
                    marker.bindPopup(`
                        <div class="p-2">
                            <strong class="text-gray-800">${marker.reportItem.dataset.title}</strong><br>
                            <small class="text-gray-600">${marker.reportItem.dataset.address}</small><br>
                            <small class="${status === 'collected' ? 'text-green-600' : 'text-red-600'} font-medium">
                                ${status === 'collected' ? '✓ Completed' : '✗ Failed'}
                            </small>
                        </div>
                    `);
                }
            });
            
            // Remove team markers for completed/failed reports
            teamMarkers = teamMarkers.filter(marker => {
                if (marker.options.teamId) {
                    const reportItem = document.querySelector(`.report-item[data-team-id="${marker.options.teamId}"]`);
                    if (reportItem && reportItem.dataset.id === reportId) {
                        trackingMap.removeLayer(marker);
                        return false;
                    }
                }
                return true;
            });
        }

        // Load reports on map
        function loadReportsOnMap() {
            // Clear existing markers
            currentMarkers.forEach(marker => trackingMap.removeLayer(marker));
            currentMarkers = [];

            // Get all report items
            const reportItems = document.querySelectorAll('.report-item');

            reportItems.forEach(item => {
                const lat = parseFloat(item.dataset.lat);
                const lng = parseFloat(item.dataset.lng);
                const status = item.dataset.status;
                const progress = parseInt(item.dataset.progress) || 0;

                if (!isNaN(lat) && !isNaN(lng)) {
                    // Choose icon color based on status
                    let iconColor = 'gray';
                    let icon = 'fa-trash';
                    
                    if (status === 'collected' || progress >= 100) {
                        iconColor = 'green';
                        icon = 'fa-check';
                        completedReports.add(item.dataset.id);
                    } else if (status === 'failed') {
                        iconColor = 'red';
                        icon = 'fa-times';
                        failedReports.add(item.dataset.id);
                    } else {
                        switch (status) {
                            case 'not_assigned':
                                iconColor = 'gray';
                                break;
                            case 'assigned':
                                iconColor = 'blue';
                                break;
                            case 'in_transit':
                                iconColor = 'orange';
                                break;
                            case 'collecting':
                                iconColor = 'yellow';
                                break;
                            default:
                                iconColor = 'gray';
                        }
                    }

                    // Create custom icon
                    const markerIcon = L.divIcon({
                        html: `<div class="w-10 h-10 rounded-full bg-${iconColor}-500/80 border-2 border-white flex items-center justify-center shadow-lg">
                                 <i class="fas ${icon} text-white text-sm"></i>
                               </div>`,
                        className: 'custom-marker',
                        iconSize: [40, 40],
                        iconAnchor: [20, 40]
                    });

                    // Create marker
                    const marker = L.marker([lat, lng], {
                            icon: markerIcon
                        })
                        .addTo(trackingMap)
                        .bindPopup(`
                            <div class="p-2">
                                <strong class="text-gray-800">${item.dataset.title}</strong><br>
                                <small class="text-gray-600">${item.dataset.address}</small><br>
                                <small class="text-gray-500">${item.dataset.date}</small><br>
                                <small class="${status === 'collected' ? 'text-green-600' : status === 'failed' ? 'text-red-600' : 'text-gray-600'} font-medium">
                                    ${status === 'collected' ? '✓ Completed' : status === 'failed' ? '✗ Failed' : `Progress: ${progress}%`}
                                </small>
                            </div>
                        `);

                    // Store reference to report item
                    marker.reportItem = item;

                    // Add click event
                    marker.on('click', function(e) {
                        e.originalEvent.stopPropagation();
                        showReportDetails(item);
                        highlightMarker(marker);
                        trackingMap.setView([lat, lng], 16);

                        // If report has a team and is not completed/failed, show team route
                        if (item.dataset.teamId && item.dataset.teamLat && item.dataset.teamLng && 
                            status !== 'collected' && status !== 'failed' && progress < 100) {
                            showTeamRoute(item);
                        }
                    });

                    currentMarkers.push(marker);
                }
            });
        }

        // Load team locations on map
        function loadTeamLocations() {
            // Clear existing team markers
            teamMarkers.forEach(marker => trackingMap.removeLayer(marker));
            teamMarkers = [];

            // Get team data from report items
            const teamItems = document.querySelectorAll('.report-item[data-team-lat]');
            const processedTeams = new Set();

            teamItems.forEach(item => {
                const teamId = item.dataset.teamId;
                const teamLat = parseFloat(item.dataset.teamLat);
                const teamLng = parseFloat(item.dataset.teamLng);
                const progress = parseInt(item.dataset.progress) || 0;
                const status = item.dataset.status;

                // Don't show team markers for completed/failed reports
                if (status === 'collected' || status === 'failed' || progress >= 100) {
                    return;
                }

                if (!isNaN(teamLat) && !isNaN(teamLng) && teamId && !processedTeams.has(teamId)) {
                    processedTeams.add(teamId);

                    // Create team marker
                    const icon = L.divIcon({
                        html: `<div class="w-10 h-10 rounded-full bg-blue-500 border-2 border-white flex items-center justify-center shadow-lg animate-pulse">
                                 <i class="fas fa-truck text-white text-sm"></i>
                               </div>`,
                        className: 'team-marker',
                        iconSize: [40, 40],
                        iconAnchor: [20, 40]
                    });

                    const marker = L.marker([teamLat, teamLng], {
                            icon: icon,
                            teamId: teamId
                        }).addTo(trackingMap)
                        .bindPopup(`
                        <div class="p-2">
                            <strong class="text-gray-800">${item.dataset.teamName}</strong><br>
                            <small class="text-gray-600">Vehicle: ${item.dataset.vehicleNumber || 'N/A'}</small><br>
                            <small class="text-gray-500">Progress: ${progress}%</small>
                        </div>
                    `);

                    teamMarkers.push(marker);
                }
            });
        }

        // Show report details
        function showReportDetails(item) {
            document.getElementById('selectedInfo').classList.remove('hidden');
            document.getElementById('reportTitle').textContent = item.dataset.title;
            document.getElementById('reportDescription').textContent = item.dataset.description;
            document.getElementById('reportAddress').textContent = item.dataset.address;
            document.getElementById('reportDate').textContent = 'Submitted: ' + item.dataset.date;
            document.getElementById('reportCategories').textContent = 'Categories: ' + item.dataset.categories;

            const progress = parseInt(item.dataset.progress) || 0;
            const status = item.dataset.status;
            
            // Set status badge
            const statusEl = document.getElementById('reportStatus');
            if (progress >= 100 || status === 'collected') {
                statusEl.textContent = 'Completed';
                statusEl.className = 'px-3 py-1 rounded-full text-xs font-medium status-collected';
            } else if (status === 'failed') {
                statusEl.textContent = 'Failed';
                statusEl.className = 'px-3 py-1 rounded-full text-xs font-medium status-failed';
            } else {
                statusEl.textContent = status.replace('_', ' ');
                statusEl.className = 'px-3 py-1 rounded-full text-xs font-medium status-' + status;
            }

            // Show team info if assigned and not completed
            if (item.dataset.teamId && item.dataset.teamId !== '' && progress < 100 && status !== 'collected' && status !== 'failed') {
                document.getElementById('teamInfo').classList.remove('hidden');
                document.getElementById('assignedTeamName').textContent = item.dataset.teamName || 'Unknown Team';
                document.getElementById('teamVehicle').textContent = item.dataset.vehicleNumber || 'N/A';

                // Show progress
                document.getElementById('teamProgress').classList.remove('hidden');
                document.getElementById('teamProgressPercent').textContent = progress + '%';
                document.getElementById('teamProgressBar').style.width = Math.min(progress, 100) + '%';

                // Calculate distance if team has location
                if (item.dataset.teamLat && item.dataset.teamLng &&
                    item.dataset.teamLat !== '' && item.dataset.teamLng !== '' &&
                    item.dataset.lat && item.dataset.lng) {

                    const reportLat = parseFloat(item.dataset.lat);
                    const reportLng = parseFloat(item.dataset.lng);
                    const teamLat = parseFloat(item.dataset.teamLat);
                    const teamLng = parseFloat(item.dataset.teamLng);

                    if (!isNaN(reportLat) && !isNaN(reportLng) &&
                        !isNaN(teamLat) && !isNaN(teamLng)) {

                        const distance = calculateDistance(reportLat, reportLng, teamLat, teamLng);
                        document.getElementById('teamDistance').textContent = distance.toFixed(1) + ' km away';
                    }
                }

                // Set assignment status
                const assignmentStatusEl = document.getElementById('teamAssignmentStatus');
                if (item.dataset.assignmentStatus && item.dataset.assignmentStatus !== '') {
                    assignmentStatusEl.textContent = item.dataset.assignmentStatus.replace('_', ' ');
                    assignmentStatusEl.className = 'assignment-status status-' + item.dataset.assignmentStatus;
                }
            } else {
                document.getElementById('teamInfo').classList.add('hidden');
            }
        }

        // Show team route on map
        function showTeamRoute(item) {
            const teamLat = parseFloat(item.dataset.teamLat);
            const teamLng = parseFloat(item.dataset.teamLng);
            const reportLat = parseFloat(item.dataset.lat);
            const reportLng = parseFloat(item.dataset.lng);

            if (!isNaN(teamLat) && !isNaN(teamLng) && !isNaN(reportLat) && !isNaN(reportLng)) {
                calculateRoute(teamLat, teamLng, reportLat, reportLng);

                // Add team marker highlight
                teamMarkers.forEach(marker => {
                    if (marker.options.teamId == item.dataset.teamId) {
                        // Highlight team marker
                        marker.setIcon(L.divIcon({
                            html: `<div class="w-12 h-12 rounded-full bg-blue-500 border-4 border-white flex items-center justify-center shadow-lg">
                                     <i class="fas fa-truck text-white text-sm"></i>
                                   </div>`,
                            className: 'team-marker-highlighted',
                            iconSize: [48, 48],
                            iconAnchor: [24, 48]
                        }));
                    }
                });
            }
        }

        // Calculate distance between two coordinates (in km)
        function calculateDistance(lat1, lon1, lat2, lon2) {
            const R = 6371; // Radius of the earth in km
            const dLat = deg2rad(lat2 - lat1);
            const dLon = deg2rad(lon2 - lon1);
            const a =
                Math.sin(dLat / 2) * Math.sin(dLat / 2) +
                Math.cos(deg2rad(lat1)) * Math.cos(deg2rad(lat2)) *
                Math.sin(dLon / 2) * Math.sin(dLon / 2);
            const c = 2 * Math.atan2(Math.sqrt(a), Math.sqrt(1 - a));
            const d = R * c; // Distance in km
            return d;
        }

        function deg2rad(deg) {
            return deg * (Math.PI / 180);
        }

        // Calculate route
        function calculateRoute(startLat, startLng, endLat, endLng) {
            // Remove previous route
            if (routingControl) {
                trackingMap.removeControl(routingControl);
            }

            // Create new route
            routingControl = L.Routing.control({
                waypoints: [
                    L.latLng(startLat, startLng),
                    L.latLng(endLat, endLng)
                ],
                routeWhileDragging: false,
                showAlternatives: false,
                lineOptions: {
                    styles: [{
                        color: '#4ade80',
                        opacity: 0.8,
                        weight: 4
                    }]
                },
                createMarker: function(i, wp) {
                    if (i === 0) {
                        return L.marker(wp.latLng, {
                            icon: L.divIcon({
                                html: '<div class="w-10 h-10 rounded-full bg-blue-500 border-2 border-white flex items-center justify-center shadow-lg"><i class="fas fa-truck text-white text-sm"></i></div>',
                                className: 'route-team-marker',
                                iconSize: [40, 40],
                                iconAnchor: [20, 40]
                            })
                        });
                    } else if (i === 1) {
                        return L.marker(wp.latLng, {
                            icon: L.divIcon({
                                html: '<div class="w-10 h-10 rounded-full bg-green-500 border-2 border-white flex items-center justify-center shadow-lg"><i class="fas fa-trash text-white text-sm"></i></div>',
                                className: 'route-report-marker',
                                iconSize: [40, 40],
                                iconAnchor: [20, 40]
                            })
                        });
                    }
                    return null;
                }
            }).addTo(trackingMap);

            // Get route information
            routingControl.on('routesfound', function(e) {
                const routes = e.routes;
                if (routes && routes.length > 0) {
                    const route = routes[0];
                    const time = Math.round(route.summary.totalTime / 60); // Convert to minutes

                    // Update ETA display
                    const eta = new Date(Date.now() + route.summary.totalTime * 1000);
                    document.getElementById('teamETA').textContent =
                        `${eta.toLocaleTimeString([], {hour: '2-digit', minute:'2-digit'})} (${time} min)`;
                }
            });
        }

        // Focus on specific team
        function focusOnTeam(teamId) {
            // Find team marker
            teamMarkers.forEach(marker => {
                if (marker.options.teamId == teamId) {
                    trackingMap.setView(marker.getLatLng(), 15);
                    marker.openPopup();
                    return;
                }
            });
        }

        // Show report on map
        function showReportOnMap(button) {
            const item = button.closest('.report-item');
            const lat = parseFloat(item.dataset.lat);
            const lng = parseFloat(item.dataset.lng);
            const progress = parseInt(item.dataset.progress) || 0;
            const status = item.dataset.status;

            if (!isNaN(lat) && !isNaN(lng)) {
                showReportDetails(item);
                trackingMap.setView([lat, lng], 16);

                // Find and highlight the corresponding marker
                currentMarkers.forEach(marker => {
                    if (marker.reportItem === item) {
                        highlightMarker(marker);
                    }
                });

                // If report has a team and is not completed/failed, show team route
                if (item.dataset.teamId && item.dataset.teamLat && item.dataset.teamLng && 
                    status !== 'collected' && status !== 'failed' && progress < 100) {
                    showTeamRoute(item);
                }
            }
        }

        // Highlight marker
        function highlightMarker(marker) {
            // Reset previous marker
            if (selectedMarker) {
                const item = selectedMarker.reportItem;
                const status = item.dataset.status;
                const progress = parseInt(item.dataset.progress) || 0;
                let iconColor = 'gray';
                let icon = 'fa-trash';
                
                if (status === 'collected' || progress >= 100) {
                    iconColor = 'green';
                    icon = 'fa-check';
                } else if (status === 'failed') {
                    iconColor = 'red';
                    icon = 'fa-times';
                } else {
                    switch (status) {
                        case 'not_assigned':
                            iconColor = 'gray';
                            break;
                        case 'assigned':
                            iconColor = 'blue';
                            break;
                        case 'in_transit':
                            iconColor = 'orange';
                            break;
                        case 'collecting':
                            iconColor = 'yellow';
                            break;
                    }
                }
                
                selectedMarker.setIcon(L.divIcon({
                    html: `<div class="w-10 h-10 rounded-full bg-${iconColor}-500/80 border-2 border-white flex items-center justify-center shadow-lg">
                             <i class="fas ${icon} text-white text-sm"></i>
                           </div>`,
                    className: 'custom-marker',
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                }));
            }

            // Highlight new marker
            selectedMarker = marker;
            marker.setIcon(L.divIcon({
                html: `<div class="w-12 h-12 rounded-full bg-green-500 border-4 border-white flex items-center justify-center shadow-lg">
                         <i class="fas fa-trash text-white text-sm"></i>
                       </div>`,
                className: 'custom-marker-highlighted',
                iconSize: [48, 48],
                iconAnchor: [24, 48]
            }));
            marker.openPopup();
        }

        // Clear selection
        function clearSelection() {
            document.getElementById('selectedInfo').classList.add('hidden');
            if (selectedMarker) {
                selectedMarker.closePopup();
                selectedMarker = null;
            }
        }

        // Use my location
        function useMyLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    trackingMap.setView([position.coords.latitude, position.coords.longitude], 15);
                }, function() {
                    alert('Unable to get your location. Please enable location services.');
                });
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }

        // Reset map
        function resetMap() {
            trackingMap.setView([14.5995, 120.9842], 13);
            clearSelection();
            if (routingControl) {
                trackingMap.removeControl(routingControl);
                routingControl = null;
            }
        }

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

        // Prevent zoom on double-tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);

        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initTrackingMap();

            // Add click events to report items
            document.querySelectorAll('.report-item').forEach(item => {
                item.addEventListener('click', function(e) {
                    if (!e.target.closest('button')) {
                        const lat = parseFloat(this.dataset.lat);
                        const lng = parseFloat(this.dataset.lng);
                        const progress = parseInt(this.dataset.progress) || 0;
                        const status = this.dataset.status;

                        if (!isNaN(lat) && !isNaN(lng)) {
                            showReportDetails(this);
                            trackingMap.setView([lat, lng], 16);

                            // Find and highlight the corresponding marker
                            currentMarkers.forEach(marker => {
                                if (marker.reportItem === this) {
                                    highlightMarker(marker);
                                }
                            });

                            // If report has a team and is not completed/failed, show team route
                            if (this.dataset.teamId && this.dataset.teamLat && this.dataset.teamLng && 
                                status !== 'collected' && status !== 'failed' && progress < 100) {
                                showTeamRoute(this);
                            }
                        }
                    }
                });
            });

            // Auto-refresh every 30 seconds for live updates
            setInterval(function() {
                refreshLiveData();
            }, 30000);
        });

        // Refresh live data
        function refreshLiveData() {
            // Only update team locations for active reports
            teamMarkers.forEach(marker => {
                // Simulate movement for demo (only if not completed/failed)
                const lat = marker.getLatLng().lat + (Math.random() - 0.5) * 0.001;
                const lng = marker.getLatLng().lng + (Math.random() - 0.5) * 0.001;
                marker.setLatLng([lat, lng]);
            });

            console.log('Live data updated');
            
            // Check statuses again
            checkAllReportStatuses();
        }
    </script>
</body>

</html>