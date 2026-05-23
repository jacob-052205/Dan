<?php
require_once 'config.php';

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

// Get customer points
$points_query = "SELECT points FROM customer WHERE customer_id = $customer_id";
$points_result = mysqli_query($conn, $points_query);
$customer_points = mysqli_fetch_assoc($points_result)['points'] ?? 0;

// Get community statistics
$stats_query = "SELECT 
                COUNT(DISTINCT c.customer_id) as active_users,
                COUNT(r.report_id) as total_reports,
                SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_reports,
                SUM(c.points) as total_points_earned
                FROM customer c 
                LEFT JOIN reports r ON c.customer_id = r.customer_id
                WHERE c.status = 'active'";
$stats_result = mysqli_query($conn, $stats_query);
$community_stats = mysqli_fetch_assoc($stats_result);

// Get top contributors
$top_contributors_query = "SELECT c.customer_id, c.full_name, c.profile_picture, 
                          COUNT(r.report_id) as reports_count,
                          c.points
                          FROM customer c 
                          LEFT JOIN reports r ON c.customer_id = r.customer_id
                          WHERE c.status = 'active'
                          GROUP BY c.customer_id 
                          ORDER BY reports_count DESC, c.points DESC 
                          LIMIT 10";
$top_contributors = mysqli_query($conn, $top_contributors_query);

// Get recent community reports
$recent_reports_query = "SELECT r.*, c.full_name as customer_name 
                        FROM reports r 
                        JOIN customer c ON r.customer_id = c.customer_id 
                        WHERE c.status = 'active'
                        ORDER BY r.created_at DESC 
                        LIMIT 10";
$recent_reports = mysqli_query($conn, $recent_reports_query);

// Get waste reduction tips
$tips = [
    ['icon' => 'fas fa-recycle', 'title' => 'Separate Your Waste', 'description' => 'Properly separate recyclables, organics, and landfill waste.', 'color' => 'from-green-500 to-emerald-600'],
    ['icon' => 'fas fa-compress-alt', 'title' => 'Reduce Food Waste', 'description' => 'Plan meals and store food properly to minimize waste.', 'color' => 'from-blue-500 to-cyan-600'],
    ['icon' => 'fas fa-seedling', 'title' => 'Compost Organic Waste', 'description' => 'Start composting kitchen scraps for your garden.', 'color' => 'from-green-400 to-lime-500'],
    ['icon' => 'fas fa-shopping-bag', 'title' => 'Use Reusable Bags', 'description' => 'Carry reusable bags when shopping to reduce plastic waste.', 'color' => 'from-yellow-500 to-orange-600'],
    ['icon' => 'fas fa-wine-bottle', 'title' => 'Use Reusable Bottles', 'description' => 'Carry a reusable water bottle instead of buying plastic ones.', 'color' => 'from-blue-400 to-purple-500'],
    ['icon' => 'fas fa-box-open', 'title' => 'Avoid Overpackaging', 'description' => 'Choose products with minimal packaging whenever possible.', 'color' => 'from-purple-500 to-pink-600']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Community - WasteWise</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        
        html, body {
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
        .status-pending { background: #fef3c7; color: #92400e; }
        .status-in_progress { background: #dbeafe; color: #1e40af; }
        .status-completed { background: #dcfce7; color: #166534; }
        
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
        
        /* Rank badges */
        .rank-1 { background: linear-gradient(135deg, #fbbf24 0%, #d97706 100%); }
        .rank-2 { background: linear-gradient(135deg, #9ca3af 0%, #6b7280 100%); }
        .rank-3 { background: linear-gradient(135deg, #b45309 0%, #92400e 100%); }
        .rank-other { background: linear-gradient(135deg, #4b5563 0%, #374151 100%); }
        
        /* Contributor card hover */
        .contributor-card:hover .contributor-avatar {
            transform: scale(1.1);
            border-color: #4ade80;
        }
        
        .contributor-avatar {
            transition: transform 0.3s ease, border-color 0.3s ease;
        }
        
        /* Custom scrollbar */
        .custom-scrollbar::-webkit-scrollbar {
            width: 6px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb {
            background: rgba(74, 222, 128, 0.5);
            border-radius: 10px;
        }
        
        .custom-scrollbar::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 222, 128, 0.8);
        }
        
        /* Report item hover */
        .report-item:hover {
            border-color: #4ade80;
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Community stats pulse */
        .stat-card:hover .stat-icon {
            animation: bounce 0.5s;
        }
        
        @keyframes bounce {
            0%, 100% { transform: translateY(0); }
            50% { transform: translateY(-10px); }
        }
        
        /* Tip card hover */
        .tip-card:hover {
            transform: translateX(5px);
        }
        
        /* Community call to action */
        .community-cta {
            background: linear-gradient(135deg, rgba(102, 126, 234, 0.9) 0%, rgba(118, 75, 162, 0.9) 100%);
            border: 1px solid rgba(255, 255, 255, 0.3);
        }
        
        /* Leaderboard animation */
        @keyframes slideIn {
            from {
                opacity: 0;
                transform: translateX(-20px);
            }
            to {
                opacity: 1;
                transform: translateX(0);
            }
        }
        
        .leaderboard-item {
            animation: slideIn 0.5s ease-out;
            animation-fill-mode: both;
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
                <?php if($profile_pic): ?>
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
                    <div class="text-green-400 text-sm"><?php echo $customer_points; ?> Points</div>
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
            <a href="community.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
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
                        <?php if($profile_pic): ?>
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
                            <div class="text-green-400 text-sm"><?php echo $customer_points; ?> Points</div>
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
                    <a href="community.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
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
                        <h1 class="text-2xl font-bold text-white">Community Hub</h1>
                        <p class="text-gray-300 text-sm">Join forces with neighbors to keep our community clean and green</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Submit Report Button -->
                        <a href="submit_report.php" 
                           class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-plus-circle mr-2"></i>
                            Submit Report
                        </a>
                        
                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                <div class="text-gray-300 text-sm">Community Member</div>
                            </div>
                            <?php if($profile_pic): ?>
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
                    <h1 class="text-2xl font-bold text-white">Community Hub</h1>
                    <p class="text-gray-300 text-sm">Join forces with neighbors to keep our community clean and green</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Community Statistics -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Active Users -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Active Members</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $community_stats['active_users']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center stat-icon">
                                <i class="fas fa-users text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-user-plus text-green-400 mr-1"></i>
                            Join our growing community
                        </div>
                    </div>
                    
                    <!-- Total Reports -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Total Reports</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $community_stats['total_reports']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-green-500/20 flex items-center justify-center stat-icon">
                                <i class="fas fa-flag text-green-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-chart-line text-blue-400 mr-1"></i>
                            Community impact in numbers
                        </div>
                    </div>
                    
                    <!-- Issues Resolved -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Issues Resolved</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $community_stats['completed_reports']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-emerald-500/20 flex items-center justify-center stat-icon">
                                <i class="fas fa-check-circle text-emerald-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-star text-yellow-400 mr-1"></i>
                            Successful cleanups
                        </div>
                    </div>
                    
                    <!-- Total Points Earned -->
                    <div class="bg-gradient-to-r from-yellow-500 to-orange-600 rounded-2xl shadow-lg p-6 text-white hover-lift stat-card">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-yellow-100 text-sm">Total Points Earned</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo $community_stats['total_points_earned']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center stat-icon">
                                <i class="fas fa-trophy text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-yellow-100">
                            <i class="fas fa-coins mr-1"></i>
                            Community rewards earned
                        </div>
                    </div>
                </div>

                <!-- Community Leaders -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-white">Community Leaders</h2>
                            <p class="text-gray-300 text-sm">Top contributors making our community cleaner</p>
                        </div>
                        <div class="text-green-400 text-sm font-medium">
                            <i class="fas fa-crown mr-1"></i>
                            Top 10 Contributors
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4">
                        <?php 
                        $rank = 1;
                        mysqli_data_seek($top_contributors, 0);
                        while($contributor = mysqli_fetch_assoc($top_contributors)): 
                            $rankClass = $rank <= 3 ? "rank-$rank" : "rank-other";
                        ?>
                        <div class="contributor-card bg-white/5 rounded-xl p-4 text-center hover-lift leaderboard-item" style="animation-delay: <?php echo ($rank - 1) * 0.1; ?>s;">
                            <!-- Rank Badge -->
                            <div class="absolute -top-2 -left-2 w-8 h-8 rounded-full flex items-center justify-center text-white font-bold text-sm <?php echo $rankClass; ?>">
                                <?php echo $rank; ?>
                            </div>
                            
                            <!-- Contributor Avatar -->
                            <div class="relative mb-3">
                                <div class="w-16 h-16 mx-auto rounded-full border-3 border-white/30 overflow-hidden contributor-avatar">
                                    <?php if($contributor['profile_picture'] && file_exists($contributor['profile_picture'])): ?>
                                    <img src="<?php echo htmlspecialchars($contributor['profile_picture']); ?>" 
                                         alt="<?php echo htmlspecialchars($contributor['full_name']); ?>"
                                         class="w-full h-full object-cover">
                                    <?php else: ?>
                                    <div class="w-full h-full gradient-bg flex items-center justify-center">
                                        <span class="text-white font-bold text-xl">
                                            <?php echo strtoupper(substr($contributor['full_name'], 0, 1)); ?>
                                        </span>
                                    </div>
                                    <?php endif; ?>
                                </div>
                            </div>
                            
                            <!-- Contributor Name -->
                            <h4 class="font-semibold text-white text-sm mb-2 truncate" title="<?php echo htmlspecialchars($contributor['full_name']); ?>">
                                <?php echo htmlspecialchars($contributor['full_name']); ?>
                            </h4>
                            
                            <!-- Contributor Stats -->
                            <div class="grid grid-cols-2 gap-2">
                                <div class="text-center">
                                    <div class="text-green-400 font-bold text-lg"><?php echo $contributor['reports_count']; ?></div>
                                    <div class="text-gray-400 text-xs">Reports</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-yellow-400 font-bold text-lg"><?php echo $contributor['points']; ?></div>
                                    <div class="text-gray-400 text-xs">Points</div>
                                </div>
                            </div>
                        </div>
                        <?php 
                        $rank++;
                        endwhile; 
                        ?>
                    </div>
                </div>

                <!-- Recent Community Activity -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-white">Recent Community Activity</h2>
                            <p class="text-gray-300 text-sm">Latest reports from our community members</p>
                        </div>
                        <div class="text-green-400 text-sm font-medium">
                            <i class="fas fa-bolt mr-1"></i>
                            Live Updates
                        </div>
                    </div>
                    
                    <div class="space-y-4 max-h-96 overflow-y-auto custom-scrollbar">
                        <?php 
                        mysqli_data_seek($recent_reports, 0);
                        if(mysqli_num_rows($recent_reports) == 0): ?>
                            <div class="text-center py-8">
                                <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-white/10 flex items-center justify-center">
                                    <i class="fas fa-flag text-gray-400 text-xl"></i>
                                </div>
                                <h3 class="text-gray-300 font-medium">No recent activity</h3>
                                <p class="text-gray-400 text-sm mt-1">Be the first to submit a community report!</p>
                            </div>
                        <?php else: 
                            while($report = mysqli_fetch_assoc($recent_reports)): 
                        ?>
                        <div class="report-item p-4 border border-gray-700 rounded-xl hover-lift transition-all duration-300">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                                        <i class="fas fa-flag text-white"></i>
                                    </div>
                                </div>
                                
                                <div class="flex-1 min-w-0">
                                    <h4 class="font-semibold text-white mb-1"><?php echo htmlspecialchars($report['title']); ?></h4>
                                    <p class="text-gray-300 text-sm mb-2">
                                        <?php echo htmlspecialchars(substr($report['description'], 0, 80)); ?>...
                                    </p>
                                    
                                    <div class="flex flex-wrap items-center gap-3 mt-3">
                                        <div class="flex items-center text-gray-400 text-sm">
                                            <i class="fas fa-user mr-2 text-blue-400"></i>
                                            <span><?php echo htmlspecialchars($report['customer_name']); ?></span>
                                        </div>
                                        <div class="flex items-center text-gray-400 text-sm">
                                            <i class="fas fa-calendar mr-2 text-green-400"></i>
                                            <span><?php echo date('M d, Y', strtotime($report['created_at'])); ?></span>
                                        </div>
                                        <div class="flex items-center">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium status-<?php echo $report['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                            </span>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex-shrink-0">
                                    <a href="view_my_report.php?id=<?php echo $report['report_id']; ?>" 
                                       class="text-green-400 hover:text-green-300 transition-colors"
                                       title="View Report">
                                        <i class="fas fa-external-link-alt"></i>
                                    </a>
                                </div>
                            </div>
                        </div>
                        <?php endwhile; ?>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Waste Reduction Tips -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-white">Waste Reduction Tips</h2>
                            <p class="text-gray-300 text-sm">Simple ways to reduce waste and help the environment</p>
                        </div>
                        <div class="text-green-400 text-sm font-medium">
                            <i class="fas fa-lightbulb mr-1"></i>
                            Eco Tips
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($tips as $index => $tip): ?>
                        <div class="tip-card bg-gradient-to-br <?php echo $tip['color']; ?> rounded-xl p-5 text-white hover-lift transition-all duration-300">
                            <div class="flex items-start space-x-4">
                                <div class="flex-shrink-0">
                                    <div class="w-12 h-12 rounded-lg bg-white/20 flex items-center justify-center">
                                        <i class="<?php echo $tip['icon']; ?> text-xl"></i>
                                    </div>
                                </div>
                                
                                <div class="flex-1">
                                    <h4 class="font-semibold text-lg mb-2"><?php echo $tip['title']; ?></h4>
                                    <p class="text-white/80 text-sm"><?php echo $tip['description']; ?></p>
                                </div>
                            </div>
                            
                            <!-- Tip Number -->
                            <div class="mt-4 text-white/60 text-xs font-medium">
                                Tip #<?php echo $index + 1; ?>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Community Call to Action -->
                <div class="community-cta rounded-2xl shadow-xl p-8 fade-in-up">
                    <div class="flex flex-col lg:flex-row items-center justify-between">
                        <div class="text-center lg:text-left mb-6 lg:mb-0">
                            <h3 class="text-2xl lg:text-3xl font-bold text-white mb-4">Together We Can Make a Difference!</h3>
                            <p class="text-white/80 text-lg mb-6 max-w-2xl">
                                Join our community efforts to create a cleaner, greener neighborhood. 
                                Every report counts towards making our community a better place!
                            </p>
                            
                            <div class="flex flex-col sm:flex-row gap-4">
                                <a href="submit_report.php" 
                                   class="bg-white text-gray-900 font-semibold px-6 py-3 rounded-xl hover:bg-gray-100 transition-all duration-300 transform hover:scale-[1.02] shadow-lg flex items-center justify-center space-x-2">
                                    <i class="fas fa-plus-circle"></i>
                                    <span>Submit a Report Now</span>
                                </a>
                                
                                <a href="rewards.php" 
                                   class="bg-white/20 text-white font-semibold px-6 py-3 rounded-xl hover:bg-white/30 transition-all duration-300 border border-white/30 flex items-center justify-center space-x-2">
                                    <i class="fas fa-gift"></i>
                                    <span>Earn Rewards</span>
                                </a>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <div class="w-32 h-32 lg:w-40 lg:h-40 rounded-full bg-white/20 flex items-center justify-center">
                                <i class="fas fa-hands-helping text-white text-5xl lg:text-6xl"></i>
                            </div>
                            <div class="absolute -top-2 -right-2 w-12 h-12 bg-green-500 rounded-full flex items-center justify-center animate-pulse">
                                <i class="fas fa-heart text-white text-lg"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Community Stats Footer -->
                    <div class="mt-8 pt-8 border-t border-white/20">
                        <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                            <div class="text-center">
                                <div class="text-white text-2xl font-bold"><?php echo $community_stats['active_users']; ?>+</div>
                                <div class="text-white/70 text-sm">Active Members</div>
                            </div>
                            <div class="text-center">
                                <div class="text-white text-2xl font-bold"><?php echo $community_stats['total_reports']; ?>+</div>
                                <div class="text-white/70 text-sm">Reports Submitted</div>
                            </div>
                            <div class="text-center">
                                <div class="text-white text-2xl font-bold"><?php echo $community_stats['completed_reports']; ?>+</div>
                                <div class="text-white/70 text-sm">Issues Resolved</div>
                            </div>
                            <div class="text-center">
                                <div class="text-white text-2xl font-bold">24/7</div>
                                <div class="text-white/70 text-sm">Community Support</div>
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
        
        // Animate stats counters
        function animateStats() {
            const statValues = document.querySelectorAll('.text-3xl.font-bold.text-white, .text-3xl.font-bold.mt-2');
            
            statValues.forEach(element => {
                const finalValue = parseInt(element.textContent);
                let currentValue = 0;
                const increment = finalValue / 50;
                const duration = 1000; // 1 second
                const steps = 50;
                const stepDuration = duration / steps;
                
                const timer = setInterval(() => {
                    currentValue += increment;
                    if (currentValue >= finalValue) {
                        currentValue = finalValue;
                        clearInterval(timer);
                    }
                    element.textContent = Math.floor(currentValue);
                }, stepDuration);
            });
        }
        
        // Initialize animations
        document.addEventListener('DOMContentLoaded', function() {
            // Animate stats on page load
            setTimeout(animateStats, 500);
            
            // Add hover effects to contributor cards
            const contributorCards = document.querySelectorAll('.contributor-card');
            contributorCards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-8px) scale(1.02)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0) scale(1)';
                });
            });
            
            // Add parallax effect to community CTA
            const communityCTA = document.querySelector('.community-cta');
            if (communityCTA) {
                window.addEventListener('scroll', function() {
                    const scrolled = window.pageYOffset;
                    const rate = scrolled * -0.5;
                    communityCTA.style.transform = `translate3d(0, ${rate}px, 0)`;
                });
            }
        });
        
        // Tooltip for contributor names
        document.querySelectorAll('.truncate').forEach(element => {
            element.addEventListener('mouseenter', function() {
                if (this.offsetWidth < this.scrollWidth) {
                    const tooltip = document.createElement('div');
                    tooltip.className = 'fixed bg-gray-900 text-white px-3 py-2 rounded-lg text-sm z-50';
                    tooltip.textContent = this.getAttribute('title');
                    tooltip.style.top = `${event.clientY - 40}px`;
                    tooltip.style.left = `${event.clientX}px`;
                    document.body.appendChild(tooltip);
                    
                    this._tooltip = tooltip;
                }
            });
            
            element.addEventListener('mouseleave', function() {
                if (this._tooltip) {
                    this._tooltip.remove();
                    delete this._tooltip;
                }
            });
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
        
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const fadeObserver = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);
        
        // Observe sections for fade-in
        document.querySelectorAll('.fade-in-up').forEach(section => {
            fadeObserver.observe(section);
        });
    </script>
</body>
</html>