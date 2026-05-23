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

if (!isset($_GET['id'])) {
    redirect('my_reports.php');
}

$report_id = (int)$_GET['id'];

// Get report details
$query = "SELECT r.*, c.full_name as customer_name, c.customer_email, c.phone as customer_phone 
          FROM reports r 
          JOIN customer c ON r.customer_id = c.customer_id 
          WHERE r.report_id = $report_id AND r.customer_id = $customer_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect('my_reports.php');
}

$report = mysqli_fetch_assoc($result);

// Get assigned admin if any
$assigned_admin = null;
if ($report['assigned_to']) {
    $admin_query = "SELECT full_name FROM admin WHERE admin_id = {$report['assigned_to']}";
    $admin_result = mysqli_query($conn, $admin_query);
    $assigned_admin = mysqli_fetch_assoc($admin_result);
}

// Get waste categories for this report
$categories_query = "SELECT wc.* FROM waste_categories wc 
                    JOIN report_categories rc ON wc.category_id = rc.category_id 
                    WHERE rc.report_id = $report_id";
$categories_result = mysqli_query($conn, $categories_query);

// Get status history
$history_query = "SELECT * FROM report_status_history WHERE report_id = $report_id ORDER BY created_at DESC";
$history_result = mysqli_query($conn, $history_query);

// Status colors
$status_colors = [
    'pending' => ['bg' => 'bg-yellow-500/20', 'text' => 'text-yellow-400', 'border' => 'border-yellow-500/30'],
    'in_progress' => ['bg' => 'bg-blue-500/20', 'text' => 'text-blue-400', 'border' => 'border-blue-500/30'],
    'completed' => ['bg' => 'bg-green-500/20', 'text' => 'text-green-400', 'border' => 'border-green-500/30'],
    'rejected' => ['bg' => 'bg-red-500/20', 'text' => 'text-red-400', 'border' => 'border-red-500/30']
];

// Priority colors
$priority_colors = [
    'low' => ['bg' => 'bg-green-500/20', 'text' => 'text-green-400'],
    'medium' => ['bg' => 'bg-yellow-500/20', 'text' => 'text-yellow-400'],
    'high' => ['bg' => 'bg-red-500/20', 'text' => 'text-red-400']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Report Details - WasteWise</title>
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
        
        /* Timeline styling */
        .timeline-item {
            position: relative;
            padding-left: 30px;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: 0;
            top: 8px;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: #4ade80;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }
        
        .timeline-item::after {
            content: '';
            position: absolute;
            left: 5px;
            top: 20px;
            bottom: -30px;
            width: 2px;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .timeline-item:last-child::after {
            display: none;
        }
        
        /* Category badges */
        .category-badge {
            transition: all 0.3s ease;
        }
        
        .category-badge:hover {
            transform: translateY(-2px);
        }
        
        /* Report image */
        .report-image-container {
            transition: all 0.3s ease;
        }
        
        .report-image-container:hover {
            transform: scale(1.02);
        }
        
        /* Status animation */
        @keyframes statusPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
        }
        
        .status-animate {
            animation: statusPulse 2s infinite;
        }
        
        /* Print styles */
        @media print {
            .no-print {
                display: none !important;
            }
            
            body {
                background: white !important;
                color: black !important;
            }
            
            .glass-card {
                background: white !important;
                border: 1px solid #ddd !important;
                box-shadow: none !important;
            }
            
            .text-white {
                color: black !important;
            }
            
            .text-gray-300, .text-gray-400 {
                color: #666 !important;
            }
        }
    </style>
</head>
<body class="min-h-screen">
    <!-- Mobile Menu -->
    <div class="mobile-menu fixed top-0 left-0 w-64 h-full bg-gray-900 z-50 shadow-2xl p-6 safe-area-top safe-area-bottom no-scrollbar no-print">
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
            <a href="my_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
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
        <aside class="hidden lg:block w-64 bg-gray-900 text-white fixed left-0 top-0 h-screen no-print">
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
                    <a href="my_reports.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
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
            <header class="glass-card px-6 py-4 safe-area-top mx-6 mt-6 rounded-2xl backdrop-blur-lg no-print">
                <div class="flex items-center justify-between">
                    <!-- Mobile Menu Button -->
                    <button onclick="openMobileMenu()" class="lg:hidden text-white text-2xl">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Page Title -->
                    <div class="hidden lg:block">
                        <h1 class="text-2xl font-bold text-white">Report Details</h1>
                        <p class="text-gray-300 text-sm">View complete information about your report</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Back Button -->
                        <a href="my_reports.php" 
                           class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Reports
                        </a>
                        
                        <!-- Print Button -->
                        <button onclick="window.print()" 
                                class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-print mr-2"></i>
                            Print
                        </button>
                        
                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                <div class="text-gray-300 text-sm">Report Viewer</div>
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
                    <h1 class="text-2xl font-bold text-white">Report Details</h1>
                    <p class="text-gray-300 text-sm">View complete information about your report</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Report Header -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-6">
                        <div>
                            <div class="flex items-center space-x-3 mb-2">
                                <div class="w-12 h-12 rounded-xl <?php echo $status_colors[$report['status']]['bg']; ?> flex items-center justify-center">
                                    <i class="fas fa-flag <?php echo $status_colors[$report['status']]['text']; ?> text-xl"></i>
                                </div>
                                <div>
                                    <h2 class="text-2xl font-bold text-white"><?php echo htmlspecialchars($report['title']); ?></h2>
                                    <p class="text-gray-300 text-sm">
                                        Report ID: #<?php echo $report['report_id']; ?> • 
                                        Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($report['created_at'])); ?>
                                    </p>
                                </div>
                            </div>
                        </div>
                        
                        <div class="flex flex-wrap gap-3">
                            <!-- Status Badge -->
                            <div class="px-4 py-2 rounded-xl <?php echo $status_colors[$report['status']]['bg']; ?> <?php echo $status_colors[$report['status']]['text']; ?> border <?php echo $status_colors[$report['status']]['border']; ?> font-semibold flex items-center space-x-2 status-animate">
                                <i class="fas fa-circle text-xs"></i>
                                <span><?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?></span>
                            </div>
                            
                            <!-- Priority Badge -->
                            <div class="px-4 py-2 rounded-xl <?php echo $priority_colors[$report['priority']]['bg']; ?> <?php echo $priority_colors[$report['priority']]['text']; ?> font-semibold flex items-center space-x-2">
                                <i class="fas fa-exclamation-circle"></i>
                                <span><?php echo ucfirst($report['priority']); ?> Priority</span>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Quick Actions -->
                    <?php if($report['status'] == 'pending'): ?>
                    <div class="flex flex-wrap gap-3 pt-4 border-t border-gray-700">
                        <a href="edit_my_report.php?id=<?php echo $report['report_id']; ?>" 
                           class="px-4 py-2 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift flex items-center space-x-2">
                            <i class="fas fa-edit"></i>
                            <span>Edit Report</span>
                        </a>
                        
                        <button onclick="shareReport()"
                                class="px-4 py-2 bg-gradient-to-r from-blue-500 to-cyan-600 text-white rounded-lg hover:from-blue-600 hover:to-cyan-700 transition-all duration-300 hover-lift flex items-center space-x-2">
                            <i class="fas fa-share-alt"></i>
                            <span>Share</span>
                        </button>
                        
                        <a href="track_reports.php?focus=<?php echo $report['report_id']; ?>" 
                           class="px-4 py-2 bg-gradient-to-r from-purple-500 to-pink-600 text-white rounded-lg hover:from-purple-600 hover:to-pink-700 transition-all duration-300 hover-lift flex items-center space-x-2">
                            <i class="fas fa-map-marker-alt"></i>
                            <span>Track on Map</span>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Report Information Grid -->
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 mb-6">
                    <!-- Report Information Card -->
                    <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-white">Report Information</h3>
                            <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                                <i class="fas fa-info-circle text-blue-400"></i>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-file-alt text-blue-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-gray-300 text-sm mb-1">Report Type</div>
                                    <div class="text-white font-medium"><?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-calendar text-green-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-gray-300 text-sm mb-1">Submitted On</div>
                                    <div class="text-white font-medium"><?php echo date('F d, Y', strtotime($report['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-purple-500/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-sync-alt text-purple-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-gray-300 text-sm mb-1">Last Updated</div>
                                    <div class="text-white font-medium"><?php echo date('F d, Y', strtotime($report['updated_at'])); ?></div>
                                </div>
                            </div>
                            
                            <?php if($assigned_admin): ?>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-yellow-500/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-user-tie text-yellow-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-gray-300 text-sm mb-1">Assigned To</div>
                                    <div class="text-white font-medium"><?php echo htmlspecialchars($assigned_admin['full_name']); ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                    
                    <!-- Location Details Card -->
                    <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                        <div class="flex items-center justify-between mb-6">
                            <h3 class="text-xl font-bold text-white">Location Details</h3>
                            <div class="w-10 h-10 rounded-xl bg-green-500/20 flex items-center justify-center">
                                <i class="fas fa-map-marker-alt text-green-400"></i>
                            </div>
                        </div>
                        
                        <div class="space-y-4">
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-green-500/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-location-dot text-green-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-gray-300 text-sm mb-1">Address</div>
                                    <div class="text-white font-medium"><?php echo htmlspecialchars($report['address']); ?></div>
                                </div>
                            </div>
                            
                            <?php if($report['latitude'] && $report['longitude']): ?>
                            <div class="flex items-start space-x-3">
                                <div class="w-8 h-8 rounded-lg bg-blue-500/10 flex items-center justify-center flex-shrink-0">
                                    <i class="fas fa-crosshairs text-blue-400 text-sm"></i>
                                </div>
                                <div class="flex-1">
                                    <div class="text-gray-300 text-sm mb-1">Coordinates</div>
                                    <div class="text-white font-medium font-mono"><?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?></div>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <div class="mt-6">
                                <a href="track_reports.php?focus=<?php echo $report['report_id']; ?>" 
                                   class="inline-flex items-center space-x-2 text-green-400 hover:text-green-300 transition-colors">
                                    <i class="fas fa-map"></i>
                                    <span>View on Map</span>
                                </a>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Description Card -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white">Description</h3>
                        <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center">
                            <i class="fas fa-align-left text-purple-400"></i>
                        </div>
                    </div>
                    
                    <div class="bg-white/5 rounded-xl p-6">
                        <div class="text-gray-300 leading-relaxed whitespace-pre-line">
                            <?php echo htmlspecialchars($report['description']); ?>
                        </div>
                    </div>
                </div>

                <!-- Waste Categories -->
                <?php if(mysqli_num_rows($categories_result) > 0): ?>
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white">Waste Categories</h3>
                        <div class="w-10 h-10 rounded-xl bg-yellow-500/20 flex items-center justify-center">
                            <i class="fas fa-tags text-yellow-400"></i>
                        </div>
                    </div>
                    
                    <div class="flex flex-wrap gap-3">
                        <?php while($category = mysqli_fetch_assoc($categories_result)): ?>
                        <div class="category-badge px-4 py-2 bg-gradient-to-r from-blue-500/20 to-purple-500/20 text-white rounded-lg border border-blue-500/30 flex items-center space-x-2">
                            <i class="<?php echo $category['icon_class']; ?> text-blue-400"></i>
                            <span><?php echo htmlspecialchars($category['category_name']); ?></span>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Attached Image -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white">Attached Image</h3>
                        <div class="w-10 h-10 rounded-xl bg-pink-500/20 flex items-center justify-center">
                            <i class="fas fa-image text-pink-400"></i>
                        </div>
                    </div>
                    
                    <?php if($report['image_path'] && file_exists($report['image_path'])): ?>
                    <div class="report-image-container max-w-2xl mx-auto">
                        <img src="<?php echo $report['image_path']; ?>" 
                             alt="Report Image" 
                             class="w-full rounded-xl shadow-lg border-2 border-white/10">
                    </div>
                    <?php else: ?>
                    <div class="text-center py-12">
                        <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/10 flex items-center justify-center">
                            <i class="fas fa-image text-gray-400 text-3xl"></i>
                        </div>
                        <h3 class="text-gray-300 font-medium text-xl mb-2">No image attached</h3>
                        <p class="text-gray-400">No image was attached to this report</p>
                    </div>
                    <?php endif; ?>
                </div>

                <!-- Status Timeline -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white">Status Timeline</h3>
                        <div class="w-10 h-10 rounded-xl bg-emerald-500/20 flex items-center justify-center">
                            <i class="fas fa-history text-emerald-400"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-8">
                        <!-- Timeline Item: Report Submitted -->
                        <div class="timeline-item">
                            <div class="timeline-content ml-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-white font-semibold">Report Submitted</div>
                                    <div class="text-gray-400 text-sm">
                                        <?php echo date('F d, Y h:i A', strtotime($report['created_at'])); ?>
                                    </div>
                                </div>
                                <p class="text-gray-300 text-sm">
                                    Report was created and submitted to the system. Our team will review it shortly.
                                </p>
                            </div>
                        </div>
                        
                        <!-- Timeline Item: In Progress (if applicable) -->
                        <?php if($report['status'] == 'in_progress' || $report['status'] == 'completed'): ?>
                        <div class="timeline-item">
                            <div class="timeline-content ml-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-white font-semibold">In Progress</div>
                                    <div class="text-gray-400 text-sm">
                                        <?php echo date('F d, Y h:i A', strtotime($report['updated_at'])); ?>
                                    </div>
                                </div>
                                <p class="text-gray-300 text-sm">
                                    Report is being processed by our team. We're working on resolving the issue.
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Timeline Item: Completed (if applicable) -->
                        <?php if($report['status'] == 'completed'): ?>
                        <div class="timeline-item">
                            <div class="timeline-content ml-4">
                                <div class="flex items-center justify-between mb-2">
                                    <div class="text-white font-semibold">Completed</div>
                                    <div class="text-gray-400 text-sm">
                                        <?php echo date('F d, Y h:i A', strtotime($report['updated_at'])); ?>
                                    </div>
                                </div>
                                <p class="text-gray-300 text-sm">
                                    Report has been successfully resolved and completed. Thank you for your contribution!
                                </p>
                            </div>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>

                <!-- Additional History -->
                <?php if(mysqli_num_rows($history_result) > 0): ?>
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white">Additional History</h3>
                        <div class="w-10 h-10 rounded-xl bg-cyan-500/20 flex items-center justify-center">
                            <i class="fas fa-stream text-cyan-400"></i>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <?php while($history = mysqli_fetch_assoc($history_result)): ?>
                        <div class="bg-white/5 rounded-xl p-4">
                            <div class="flex items-center justify-between mb-2">
                                <div class="text-white font-medium">
                                    <?php echo ucfirst(str_replace('_', ' ', $history['status'])); ?>
                                </div>
                                <div class="text-gray-400 text-sm">
                                    <?php echo date('M d, Y h:i A', strtotime($history['created_at'])); ?>
                                </div>
                            </div>
                            <?php if($history['notes']): ?>
                            <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($history['notes']); ?></p>
                            <?php endif; ?>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>
                <?php endif; ?>
            </main>
        </div>
    </div>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 lg:hidden z-40 no-print">
        <div class="flex flex-col items-end space-y-4">
            <!-- Back Button -->
            <a href="my_reports.php" 
               class="w-14 h-14 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110">
                <i class="fas fa-arrow-left text-white text-xl"></i>
            </a>
            
            <!-- Print Button -->
            <button onclick="window.print()"
                    class="w-14 h-14 bg-gradient-to-r from-green-500 to-emerald-600 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110">
                <i class="fas fa-print text-white text-xl"></i>
            </button>
        </div>
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
        
        // Share Report Function
        function shareReport() {
            if (navigator.share) {
                navigator.share({
                    title: 'Waste Report: <?php echo htmlspecialchars(addslashes($report['title'])); ?>',
                    text: 'Check out this waste report I submitted on WasteWise',
                    url: window.location.href
                })
                .then(() => console.log('Successful share'))
                .catch((error) => console.log('Error sharing:', error));
            } else {
                // Fallback: Copy to clipboard
                navigator.clipboard.writeText(window.location.href)
                    .then(() => {
                        alert('Report link copied to clipboard!');
                    })
                    .catch(err => {
                        console.error('Failed to copy: ', err);
                        prompt('Copy this link:', window.location.href);
                    });
            }
        }
        
        // Image Zoom Functionality
        document.addEventListener('DOMContentLoaded', function() {
            const reportImage = document.querySelector('.report-image-container img');
            if (reportImage) {
                reportImage.addEventListener('click', function() {
                    const modal = document.createElement('div');
                    modal.className = 'fixed inset-0 bg-black/90 z-50 flex items-center justify-center p-4';
                    modal.innerHTML = `
                        <div class="relative max-w-4xl max-h-[90vh]">
                            <img src="${this.src}" 
                                 alt="Full size image" 
                                 class="max-w-full max-h-[80vh] object-contain rounded-lg">
                            <button onclick="this.parentElement.parentElement.remove()" 
                                    class="absolute top-4 right-4 w-10 h-10 bg-red-500 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors">
                                <i class="fas fa-times"></i>
                            </button>
                        </div>
                    `;
                    document.body.appendChild(modal);
                    modal.addEventListener('click', function(e) {
                        if (e.target === this) {
                            this.remove();
                        }
                    });
                });
            }
            
            // Add print functionality for images
            const printButton = document.querySelector('button[onclick="window.print()"]');
            if (printButton) {
                printButton.addEventListener('click', function() {
                    // Add print-specific styles
                    const style = document.createElement('style');
                    style.textContent = `
                        @media print {
                            .report-image-container img {
                                max-height: 400px !important;
                                page-break-inside: avoid;
                            }
                            .timeline-item {
                                page-break-inside: avoid;
                            }
                        }
                    `;
                    document.head.appendChild(style);
                    
                    // Wait a moment then remove the style
                    setTimeout(() => style.remove(), 1000);
                });
            }
        });
        
        // Status update animation
        const statusElement = document.querySelector('.status-animate');
        if (statusElement) {
            setInterval(() => {
                statusElement.classList.toggle('opacity-70');
            }, 2000);
        }
        
        // Prevent zoom on double-tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Add scroll animation for sections
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in-up');
                }
            });
        }, observerOptions);
        
        // Observe sections for fade-in
        document.querySelectorAll('.fade-in-up').forEach(section => {
            observer.observe(section);
        });
    </script>
</body>
</html>