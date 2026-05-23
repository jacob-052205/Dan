<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login_admin.php');
}

$conn = getDBConnection();

// Get statistics for sidebar
$stats = [];
$queries = [
    'total_reports' => "SELECT COUNT(*) as count FROM reports",
    'pending_reports' => "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'",
    'completed_reports' => "SELECT COUNT(*) as count FROM reports WHERE status = 'completed'",
    'total_customers' => "SELECT COUNT(*) as count FROM customer",
    'today_reports' => "SELECT COUNT(*) as count FROM reports WHERE DATE(created_at) = CURDATE()"
];

foreach ($queries as $key => $query) {
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $stats[$key] = $row['count'];
}

// Handle export request
if (isset($_GET['export'])) {
    $export_type = sanitize($_GET['export']);
    
    // Set headers for CSV download
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=waste_management_' . $export_type . '_' . date('Y-m-d') . '.csv');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    switch ($export_type) {
        case 'reports':
            // Export reports
            fputcsv($output, ['Report ID', 'Title', 'Customer', 'Type', 'Status', 'Priority', 'Address', 'Created At', 'Updated At']);
            
            $query = "SELECT r.report_id, r.title, c.full_name as customer_name, r.report_type, r.status, 
                     r.priority, r.address, r.created_at, r.updated_at
                     FROM reports r 
                     JOIN customer c ON r.customer_id = c.customer_id 
                     ORDER BY r.created_at DESC";
            $result = mysqli_query($conn, $query);
            
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['report_id'],
                    $row['title'],
                    $row['customer_name'],
                    $row['report_type'],
                    $row['status'],
                    $row['priority'],
                    $row['address'],
                    $row['created_at'],
                    $row['updated_at']
                ]);
            }
            break;
            
        case 'customers':
            // Export customers
            fputcsv($output, ['Customer ID', 'Name', 'Email', 'Phone', 'Address', 'Points', 'Status', 'Created At']);
            
            $query = "SELECT customer_id, full_name, customer_email, phone, address, points, status, created_at 
                     FROM customer ORDER BY created_at DESC";
            $result = mysqli_query($conn, $query);
            
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['customer_id'],
                    $row['full_name'],
                    $row['customer_email'],
                    $row['phone'],
                    $row['address'],
                    $row['points'],
                    $row['status'],
                    $row['created_at']
                ]);
            }
            break;
            
        case 'analytics':
            // Export analytics data
            fputcsv($output, ['Month', 'Total Reports', 'Pending', 'In Progress', 'Completed', 'Rejected']);
            
            $query = "SELECT 
                     DATE_FORMAT(created_at, '%Y-%m') as month,
                     COUNT(*) as total,
                     SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending,
                     SUM(CASE WHEN status = 'in_progress' THEN 1 ELSE 0 END) as in_progress,
                     SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed,
                     SUM(CASE WHEN status = 'rejected' THEN 1 ELSE 0 END) as rejected
                     FROM reports 
                     WHERE created_at >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                     GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                     ORDER BY month";
            $result = mysqli_query($conn, $query);
            
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['month'],
                    $row['total'],
                    $row['pending'],
                    $row['in_progress'],
                    $row['completed'],
                    $row['rejected']
                ]);
            }
            break;
            
        case 'categories':
            // Export categories
            fputcsv($output, ['Category ID', 'Category Name', 'Description', 'Icon', 'Created At']);
            
            $query = "SELECT category_id, category_name, description, icon_class, created_at 
                     FROM waste_categories ORDER BY category_name";
            $result = mysqli_query($conn, $query);
            
            while ($row = mysqli_fetch_assoc($result)) {
                fputcsv($output, [
                    $row['category_id'],
                    $row['category_name'],
                    $row['description'],
                    $row['icon_class'],
                    $row['created_at']
                ]);
            }
            break;
    }
    
    fclose($output);
    exit;
}
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Export Data - WasteWise</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        
        /* Prevent horizontal scroll on mobile */
        html, body {
            overflow-x: hidden;
            width: 100%;
            position: relative;
        }
        
        .dashboard-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.85)), url('https://media.licdn.com/dms/image/v2/D4E12AQHclfeT7LmcfQ/article-cover_image-shrink_720_1280/article-cover_image-shrink_720_1280/0/1719988994510?e=2147483647&v=beta&t=74SoGzgjkc5M0QlK6kDsskfcy0ID8hIC6AxalcWtps4');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            position: relative;
            min-height: 100vh;
        }
        
        /* Remove blur on mobile for better performance */
        @media (max-width: 768px) {
            .dashboard-bg::before {
                display: none;
            }
            .dashboard-bg {
                background-attachment: scroll;
            }
        }
        
        @media (min-width: 769px) {
            .dashboard-bg::before {
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
        
        .glass-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .sidebar-glass {
            background: rgba(15, 23, 42, 0.9);
            backdrop-filter: blur(10px);
            border-right: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .text-gradient {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
        }
        
        .animate-title {
            background: linear-gradient(
                90deg,
                #4ade80,
                #22d3ee,
                #4ade80
            );
            background-size: 200% auto;
            background-clip: text;
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            animation: shimmer 3s linear infinite;
        }
        
        @keyframes shimmer {
            0% {
                background-position: 0% center;
            }
            100% {
                background-position: 200% center;
            }
        }
        
        .hover-lift {
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }
        
        .hover-lift:hover {
            transform: translateY(-5px);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        .feature-icon-bg {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
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
        
        .stagger-animation > * {
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }
        
        .stagger-animation > *:nth-child(1) { animation-delay: 0.1s; }
        .stagger-animation > *:nth-child(2) { animation-delay: 0.2s; }
        .stagger-animation > *:nth-child(3) { animation-delay: 0.3s; }
        .stagger-animation > *:nth-child(4) { animation-delay: 0.4s; }
        
        /* Mobile Navigation */
        .mobile-nav {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-nav.active {
            transform: translateX(0);
        }
        
        /* Sidebar */
        .sidebar {
            width: 280px;
            transition: all 0.3s ease;
            height: 100vh;
            position: fixed;
            left: 0;
            top: 0;
            z-index: 40;
            overflow-y: auto;
        }
        
        .sidebar-collapsed {
            transform: translateX(-280px);
        }
        
        @media (max-width: 1024px) {
            .sidebar {
                transform: translateX(-280px);
            }
            
            .sidebar.active {
                transform: translateX(0);
            }
            
            .main-content {
                margin-left: 0 !important;
            }
        }
        
        /* Touch-friendly buttons */
        .btn-touch {
            padding: 16px 24px;
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        /* Mobile-specific adjustments */
        @media (max-width: 640px) {
            .text-5xl {
                font-size: 2.5rem !important;
            }
            .text-6xl {
                font-size: 3rem !important;
            }
            .text-7xl {
                font-size: 3.5rem !important;
            }
            .text-4xl {
                font-size: 2rem !important;
            }
            .text-3xl {
                font-size: 1.75rem !important;
            }
            
            /* Improve touch targets */
            a, button {
                min-height: 44px;
                min-width: 44px;
            }
        }
        
        /* Safe area for iPhone X and newer */
        .safe-area-top {
            padding-top: env(safe-area-inset-top);
        }
        
        .safe-area-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        /* Sidebar navigation */
        .nav-item {
            transition: all 0.3s;
        }
        
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #4ade80;
        }
        
        .nav-item.active {
            background: rgba(255, 255, 255, 0.1);
            border-left: 4px solid #4ade80;
        }
        
        /* Custom scrollbar */
        ::-webkit-scrollbar {
            width: 6px;
            height: 6px;
        }
        
        ::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.05);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.2);
            border-radius: 10px;
        }
        
        ::-webkit-scrollbar-thumb:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        
        .sidebar::-webkit-scrollbar {
            width: 4px;
        }
        
        .sidebar::-webkit-scrollbar-track {
            background: transparent;
        }
        
        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.1);
        }
        
        /* Main content area */
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 280px;
            }
        }
        
        /* Export specific styles */
        .export-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 32px;
            transition: all 0.3s;
            text-align: center;
            height: 100%;
            display: flex;
            flex-direction: column;
            border-top: 4px solid #4ade80;
        }
        
        .export-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .export-icon {
            font-size: 48px;
            margin-bottom: 24px;
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 24px;
        }
        
        .export-info {
            background: rgba(59, 130, 246, 0.1);
            border: 1px solid rgba(59, 130, 246, 0.2);
            border-left: 4px solid #3b82f6;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }
        
        .privacy-notice {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-left: 4px solid #dc2626;
            border-radius: 12px;
            padding: 24px;
            margin-top: 32px;
        }
    </style>
</head>
<body class="dashboard-bg">
    <!-- Mobile Navigation Menu -->
    <div class="mobile-nav fixed top-0 right-0 w-64 h-full bg-gray-900 z-50 shadow-2xl p-6 safe-area-top safe-area-bottom">
        <div class="flex justify-between items-center mb-8">
            <div class="flex items-center space-x-3">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                    <i class="fas fa-recycle text-white"></i>
                </div>
                <span class="text-white text-xl font-bold">Waste<span class="text-green-400">Wise</span></span>
            </div>
            <button onclick="closeMobileNav()" class="text-white text-2xl">
                <i class="fas fa-times"></i>
            </button>
        </div>
        
        <div class="user-info mb-8 p-4 bg-gray-800/50 rounded-xl">
            <div class="flex items-center space-x-3">
                <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center">
                    <i class="fas fa-user-shield text-white text-xl"></i>
                </div>
                <div>
                    <div class="text-white font-semibold"><?php echo $_SESSION['user_name']; ?></div>
                    <div class="text-green-400 text-sm">Administrator</div>
                </div>
            </div>
        </div>
        
        <nav class="space-y-2 mb-8">
            <a href="admin_dashboard.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-tachometer-alt text-purple-400"></i>
                <span>Dashboard</span>
            </a>
            <a href="admin_reports.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-flag text-blue-400"></i>
                <span>Reports</span>
            </a>
            <a href="admin_customers.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-users text-green-400"></i>
                <span>Customers</span>
            </a>
            <a href="admin_teams.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-truck text-yellow-400"></i>
                <span>Teams</span>
            </a>
            <a href="admin_categories.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-trash text-red-400"></i>
                <span>Categories</span>
            </a>
            <a href="admin_analytics.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-chart-bar text-purple-400"></i>
                <span>Analytics</span>
            </a>
            <a href="admin_export.php" class="flex items-center space-x-3 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-white rounded-xl border border-purple-500/30">
                <i class="fas fa-download text-blue-400"></i>
                <span>Export Data</span>
            </a>
            <a href="admin_settings.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
                <i class="fas fa-cog text-gray-400"></i>
                <span>Settings</span>
            </a>
        </nav>
        
        <div class="absolute bottom-6 left-6 right-6">
            <a href="logout.php" class="flex items-center justify-center space-x-2 bg-red-500/20 text-red-400 p-4 rounded-xl font-semibold hover:bg-red-500/30 transition-colors border border-red-500/30">
                <i class="fas fa-sign-out-alt"></i>
                <span>Logout</span>
            </a>
        </div>
    </div>

    <!-- Sidebar -->
    <aside class="sidebar sidebar-glass safe-area-top">
        <div class="p-6">
            <!-- Logo -->
            <div class="flex items-center space-x-3 mb-8">
                <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                    <i class="fas fa-recycle text-white"></i>
                </div>
                <span class="text-white text-xl font-bold">Waste<span class="text-green-400">Wise</span></span>
            </div>
            
            <!-- User Info -->
            <div class="glass-card rounded-2xl p-4 mb-6">
                <div class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center">
                        <i class="fas fa-user-shield text-white text-xl"></i>
                    </div>
                    <div>
                        <div class="text-white font-semibold"><?php echo $_SESSION['user_name']; ?></div>
                        <div class="text-green-400 text-sm">Administrator</div>
                        <div class="text-gray-400 text-xs mt-1"><?php echo $_SESSION['user_email']; ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Navigation -->
            <nav class="space-y-1">
                <a href="admin_dashboard.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-tachometer-alt text-purple-400 w-6"></i>
                    <span>Dashboard</span>
                </a>
                
                <a href="admin_reports.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-flag text-blue-400 w-6"></i>
                    <span>Reports Management</span>
                    <?php if($stats['pending_reports'] > 0): ?>
                    <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $stats['pending_reports']; ?></span>
                    <?php endif; ?>
                </a>
                
                <a href="admin_customers.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users text-green-400 w-6"></i>
                    <span>Customers</span>
                    <span class="ml-auto bg-blue-500 text-white text-xs px-2 py-1 rounded-full"><?php echo $stats['total_customers']; ?></span>
                </a>
                
                <a href="admin_teams.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-truck text-yellow-400 w-6"></i>
                    <span>Collection Teams</span>
                </a>
                
                <a href="admin_categories.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-trash text-red-400 w-6"></i>
                    <span>Waste Categories</span>
                </a>
                
                <a href="admin_analytics.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-chart-bar text-purple-400 w-6"></i>
                    <span>Analytics</span>
                </a>
                
                <a href="admin_export.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
                    <i class="fas fa-download text-blue-400 w-6"></i>
                    <span>Export Data</span>
                </a>
                
                <a href="admin_settings.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-cog text-gray-400 w-6"></i>
                    <span>Settings</span>
                </a>
            </nav>
            
            <!-- Logout Button -->
            <div class="mt-8 pt-6 border-t border-white/10">
                <a href="logout.php" class="flex items-center justify-center space-x-2 bg-red-500/20 text-red-400 p-4 rounded-xl font-semibold hover:bg-red-500/30 transition-colors border border-red-500/30">
                    <i class="fas fa-sign-out-alt"></i>
                    <span>Logout</span>
                </a>
            </div>
            
            <!-- Quick Stats -->
            <div class="mt-8 pt-6 border-t border-white/10">
                <div class="text-gray-400 text-sm mb-3">Quick Stats</div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white/5 p-3 rounded-lg">
                        <div class="text-green-400 font-bold text-lg"><?php echo $stats['today_reports']; ?></div>
                        <div class="text-gray-400 text-xs">Today</div>
                    </div>
                    <div class="bg-white/5 p-3 rounded-lg">
                        <div class="text-blue-400 font-bold text-lg"><?php echo $stats['completed_reports']; ?></div>
                        <div class="text-gray-400 text-xs">Completed</div>
                    </div>
                </div>
            </div>
        </div>
    </aside>

    <!-- Main Content -->
    <main class="main-content">
        <!-- Top Navigation -->
        <nav class="flex justify-between items-center py-4 md:py-6 px-4 md:px-6">
            <!-- Mobile Menu Button -->
            <button onclick="toggleSidebar()" class="lg:hidden text-white text-2xl">
                <i class="fas fa-bars"></i>
            </button>
            
            <!-- Page Title -->
            <div class="hidden md:block">
                <h1 class="text-white text-2xl font-bold">Export Data</h1>
                <p class="text-gray-400 text-sm">Export system data in various formats for analysis and reporting</p>
            </div>
            
            <!-- Desktop Right Actions -->
            <div class="flex items-center space-x-4">
                <!-- Notifications -->
                <div class="relative">
                    <button onclick="toggleNotifications()" class="text-white hover:text-green-400 transition-colors">
                        <i class="fas fa-bell text-xl"></i>
                        <?php if($stats['pending_reports'] > 0): ?>
                        <span class="absolute -top-1 -right-1 bg-red-500 text-white text-xs w-5 h-5 rounded-full flex items-center justify-center"><?php echo min($stats['pending_reports'], 9); ?></span>
                        <?php endif; ?>
                    </button>
                    <!-- Notifications Dropdown -->
                    <div id="notificationsDropdown" class="hidden absolute right-0 mt-2 w-80 glass-card rounded-xl p-4 shadow-xl z-50">
                        <div class="flex justify-between items-center mb-3">
                            <h3 class="text-white font-semibold">Notifications</h3>
                            <span class="text-gray-400 text-sm"><?php echo $stats['pending_reports']; ?> pending</span>
                        </div>
                        <div class="space-y-2 max-h-60 overflow-y-auto">
                            <?php if($stats['pending_reports'] > 0): ?>
                            <div class="p-3 bg-yellow-500/10 border border-yellow-500/20 rounded-lg">
                                <div class="text-yellow-300 text-sm font-semibold">Pending Reports</div>
                                <div class="text-gray-300 text-xs">You have <?php echo $stats['pending_reports']; ?> reports awaiting review</div>
                            </div>
                            <?php endif; ?>
                            <div class="p-3 bg-blue-500/10 border border-blue-500/20 rounded-lg">
                                <div class="text-blue-300 text-sm font-semibold">Today's Activity</div>
                                <div class="text-gray-300 text-xs"><?php echo $stats['today_reports']; ?> new reports today</div>
                            </div>
                        </div>
                        <a href="admin_reports.php?filter=pending" class="block text-center text-blue-400 text-sm mt-3 hover:text-blue-300">
                            View all notifications →
                        </a>
                    </div>
                </div>
                
                <!-- User Menu -->
                <div class="relative">
                    <button onclick="toggleUserMenu()" class="flex items-center space-x-3 text-white hover:text-green-400 transition-colors">
                        <div class="w-8 h-8 rounded-full bg-gradient-to-r from-purple-500 to-blue-500 flex items-center justify-center">
                            <i class="fas fa-user-shield text-white text-sm"></i>
                        </div>
                        <span class="hidden md:inline"><?php echo explode(' ', $_SESSION['user_name'])[0]; ?></span>
                        <i class="fas fa-chevron-down text-xs"></i>
                    </button>
                    <!-- User Menu Dropdown -->
                    <div id="userMenuDropdown" class="hidden absolute right-0 mt-2 w-48 glass-card rounded-xl p-2 shadow-xl z-50">
                        <div class="p-3 border-b border-white/10">
                            <div class="text-white text-sm font-semibold"><?php echo $_SESSION['user_name']; ?></div>
                            <div class="text-gray-400 text-xs">Administrator</div>
                        </div>
                        <a href="admin_settings.php" class="flex items-center space-x-2 p-3 text-white hover:bg-white/5 rounded-lg">
                            <i class="fas fa-cog text-gray-400"></i>
                            <span>Settings</span>
                        </a>
                        <a href="logout.php" class="flex items-center space-x-2 p-3 text-red-400 hover:bg-red-500/10 rounded-lg">
                            <i class="fas fa-sign-out-alt"></i>
                            <span>Logout</span>
                        </a>
                    </div>
                </div>
            </div>
        </nav>

        <!-- Dashboard Content -->
        <div class="p-4 md:p-6">
            <!-- Dashboard Header -->
            <div class="mb-8 md:mb-12 stagger-animation">
                <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold mb-4 md:mb-6 animate-title leading-tight">
                    Export Data
                </h1>
                
                <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                    Export system data in CSV format for analysis, reporting, and backup purposes.
                </p>
            </div>

            <!-- Export Cards Grid -->
            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6 md:gap-8 mb-8 md:mb-12 fade-in-up">
                <!-- Reports Export -->
                <div class="export-card hover-lift">
                    <div class="export-icon bg-gradient-to-r from-blue-500/20 to-purple-600/20">
                        <i class="fas fa-flag text-blue-400 text-4xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white text-xl font-bold mb-3">Reports Data</h3>
                        <p class="text-gray-300 mb-6">
                            Export all reports with details including customer information, status, priority, and dates.
                        </p>
                    </div>
                    <div class="mt-auto">
                        <a href="?export=reports" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white w-full py-4 rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-download"></i>
                            <span>Export Reports (CSV)</span>
                        </a>
                        <div class="text-gray-400 text-sm mt-3">
                            <i class="fas fa-database mr-2"></i>
                            <?php echo $stats['total_reports']; ?> records available
                        </div>
                    </div>
                </div>

                <!-- Customers Export -->
                <div class="export-card hover-lift">
                    <div class="export-icon bg-gradient-to-r from-green-500/20 to-emerald-600/20">
                        <i class="fas fa-users text-green-400 text-4xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white text-xl font-bold mb-3">Customers Data</h3>
                        <p class="text-gray-300 mb-6">
                            Export customer information including contact details, points, status, and registration dates.
                        </p>
                    </div>
                    <div class="mt-auto">
                        <a href="?export=customers" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white w-full py-4 rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-download"></i>
                            <span>Export Customers (CSV)</span>
                        </a>
                        <div class="text-gray-400 text-sm mt-3">
                            <i class="fas fa-database mr-2"></i>
                            <?php echo $stats['total_customers']; ?> records available
                        </div>
                    </div>
                </div>

                <!-- Analytics Export -->
                <div class="export-card hover-lift">
                    <div class="export-icon bg-gradient-to-r from-purple-500/20 to-pink-600/20">
                        <i class="fas fa-chart-bar text-purple-400 text-4xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white text-xl font-bold mb-3">Analytics Data</h3>
                        <p class="text-gray-300 mb-6">
                            Export monthly statistics and analytics data for reporting, analysis, and presentations.
                        </p>
                    </div>
                    <div class="mt-auto">
                        <a href="?export=analytics" class="bg-gradient-to-r from-purple-500 to-pink-600 text-white w-full py-4 rounded-xl font-semibold hover:from-purple-600 hover:to-pink-700 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-download"></i>
                            <span>Export Analytics (CSV)</span>
                        </a>
                        <div class="text-gray-400 text-sm mt-3">
                            <i class="fas fa-calendar-alt mr-2"></i>
                            Last 12 months data
                        </div>
                    </div>
                </div>
                
                <!-- Categories Export -->
                <div class="export-card hover-lift">
                    <div class="export-icon bg-gradient-to-r from-red-500/20 to-orange-600/20">
                        <i class="fas fa-trash text-red-400 text-4xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white text-xl font-bold mb-3">Categories Data</h3>
                        <p class="text-gray-300 mb-6">
                            Export waste categories information including names, descriptions, and associated icons.
                        </p>
                    </div>
                    <div class="mt-auto">
                        <a href="?export=categories" class="bg-gradient-to-r from-red-500 to-orange-600 text-white w-full py-4 rounded-xl font-semibold hover:from-red-600 hover:to-orange-700 transition-all flex items-center justify-center gap-3">
                            <i class="fas fa-download"></i>
                            <span>Export Categories (CSV)</span>
                        </a>
                        <div class="text-gray-400 text-sm mt-3">
                            <i class="fas fa-tags mr-2"></i>
                            All waste categories
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Information -->
            <div class="export-info fade-in-up">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                        <i class="fas fa-info-circle text-blue-400 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white text-xl font-bold mb-3">Export Information</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                            <div class="text-gray-300">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-file-csv text-green-400"></i>
                                    <span class="font-medium">CSV Format</span>
                                </div>
                                <p class="text-sm">All exports are generated in CSV (Comma Separated Values) format</p>
                            </div>
                            <div class="text-gray-300">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-sort-amount-down text-blue-400"></i>
                                    <span class="font-medium">Sort Order</span>
                                </div>
                                <p class="text-sm">Data is sorted by date (newest first) for easier analysis</p>
                            </div>
                            <div class="text-gray-300">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-calendar-alt text-purple-400"></i>
                                    <span class="font-medium">Data Range</span>
                                </div>
                                <p class="text-sm">Includes all records from the beginning of the system</p>
                            </div>
                            <div class="text-gray-300">
                                <div class="flex items-center gap-2 mb-2">
                                    <i class="fas fa-desktop text-yellow-400"></i>
                                    <span class="font-medium">Compatibility</span>
                                </div>
                                <p class="text-sm">Compatible with Excel, Google Sheets, and any text editor</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Data Privacy Notice -->
            <div class="privacy-notice fade-in-up">
                <div class="flex items-start gap-4">
                    <div class="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center">
                        <i class="fas fa-shield-alt text-red-400 text-xl"></i>
                    </div>
                    <div class="flex-1">
                        <h3 class="text-white text-xl font-bold mb-3">Data Privacy & Security Notice</h3>
                        <div class="space-y-3">
                            <p class="text-gray-300">
                                <i class="fas fa-lock mr-2 text-red-400"></i>
                                All exported data contains sensitive information and should be handled in accordance with data protection regulations.
                            </p>
                            <p class="text-gray-300">
                                <i class="fas fa-user-shield mr-2 text-red-400"></i>
                                Customer information is confidential and should only be used for authorized purposes.
                            </p>
                            <p class="text-gray-300">
                                <i class="fas fa-hdd mr-2 text-red-400"></i>
                                Ensure proper data security measures are in place when storing exported files.
                            </p>
                            <p class="text-gray-300">
                                <i class="fas fa-trash-alt mr-2 text-red-400"></i>
                                Securely delete exported files after use to prevent unauthorized access.
                            </p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Export Tips -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mt-8 fade-in-up">
                <h3 class="text-white text-xl font-bold mb-6 flex items-center">
                    <i class="fas fa-lightbulb text-yellow-400 mr-3"></i>
                    Export Tips & Best Practices
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                    <div class="bg-white/5 p-5 rounded-xl">
                        <div class="text-yellow-400 text-xl mb-3">
                            <i class="fas fa-clock"></i>
                        </div>
                        <h4 class="text-white font-semibold mb-2">Timing</h4>
                        <p class="text-gray-300 text-sm">
                            Export data during off-peak hours for faster processing and to avoid system slowdowns.
                        </p>
                    </div>
                    <div class="bg-white/5 p-5 rounded-xl">
                        <div class="text-green-400 text-xl mb-3">
                            <i class="fas fa-folder-open"></i>
                        </div>
                        <h4 class="text-white font-semibold mb-2">Organization</h4>
                        <p class="text-gray-300 text-sm">
                            Create dedicated folders for exported data and maintain clear naming conventions for easy retrieval.
                        </p>
                    </div>
                    <div class="bg-white/5 p-5 rounded-xl">
                        <div class="text-blue-400 text-xl mb-3">
                            <i class="fas fa-sync-alt"></i>
                        </div>
                        <h4 class="text-white font-semibold mb-2">Regular Backups</h4>
                        <p class="text-gray-300 text-sm">
                            Schedule regular data exports as part of your backup strategy to prevent data loss.
                        </p>
                    </div>
                </div>
            </div>
        </div>

        <!-- Footer -->
        <footer class="bg-gray-900/50 backdrop-blur-lg text-white py-8 md:py-12 mt-12 safe-area-bottom">
            <div class="container mx-auto px-4">
                <div class="flex flex-col md:flex-row justify-between items-start">
                    <div class="mb-8 md:mb-0 md:w-1/3">
                        <a href="admin_dashboard.php" class="flex items-center space-x-3 mb-4">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                                <i class="fas fa-recycle text-white"></i>
                            </div>
                            <span class="text-2xl font-bold">Waste<span class="text-green-400">Wise</span> <span class="text-sm bg-gradient-to-r from-purple-500 to-blue-500 px-2 py-1 rounded-full">Admin</span></span>
                        </a>
                        <p class="text-gray-400 text-sm md:text-base">
                            Data export portal for backup, analysis, and reporting purposes.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Export Options</h4>
                            <ul class="space-y-2">
                                <li><a href="?export=reports" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Reports Data</a></li>
                                <li><a href="?export=customers" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Customers Data</a></li>
                                <li><a href="?export=analytics" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Analytics Data</a></li>
                                <li><a href="?export=categories" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Categories Data</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">Quick Stats</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-flag mr-2 text-sm mt-1 text-blue-400"></i>
                                    <?php echo $stats['total_reports']; ?> Total Reports
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-users mr-2 text-sm mt-1 text-green-400"></i>
                                    <?php echo $stats['total_customers']; ?> Total Customers
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-clock mr-2 text-sm mt-1 text-yellow-400"></i>
                                    <?php echo $stats['pending_reports']; ?> Pending Reports
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise Data Export Portal. All exports are timestamped and logged for security.</p>
                    <p class="mt-2">Logged in as: <span class="text-green-400"><?php echo $_SESSION['user_name']; ?></span> (Administrator)</p>
                </div>
            </div>
        </footer>
    </main>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 md:hidden z-40">
        <button onclick="toggleSidebar()" class="w-14 h-14 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
            <i class="fas fa-bars text-white text-xl"></i>
        </button>
    </div>

    <script>
        // Mobile Navigation Functions
        function openMobileNav() {
            document.querySelector('.mobile-nav').classList.add('active');
            document.body.style.overflow = 'hidden';
        }
        
        function closeMobileNav() {
            document.querySelector('.mobile-nav').classList.remove('active');
            document.body.style.overflow = 'auto';
        }
        
        // Toggle Sidebar on mobile/tablet
        function toggleSidebar() {
            const sidebar = document.querySelector('.sidebar');
            sidebar.classList.toggle('active');
        }
        
        // Toggle Notifications Dropdown
        function toggleNotifications() {
            const dropdown = document.getElementById('notificationsDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close user menu if open
            document.getElementById('userMenuDropdown').classList.add('hidden');
        }
        
        // Toggle User Menu Dropdown
        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close notifications if open
            document.getElementById('notificationsDropdown').classList.add('hidden');
        }
        
        // Close dropdowns when clicking outside
        document.addEventListener('click', function(event) {
            const mobileNav = document.querySelector('.mobile-nav');
            const menuButton = document.querySelector('[onclick="openMobileNav()"]');
            
            // Close mobile nav when clicking outside
            if (mobileNav && mobileNav.classList.contains('active') && 
                !mobileNav.contains(event.target) && 
                menuButton && !menuButton.contains(event.target)) {
                closeMobileNav();
            }
            
            // Close notifications dropdown
            const notificationsBtn = document.querySelector('[onclick="toggleNotifications()"]');
            const notificationsDropdown = document.getElementById('notificationsDropdown');
            if (notificationsDropdown && !notificationsDropdown.classList.contains('hidden') &&
                !notificationsDropdown.contains(event.target) && 
                notificationsBtn && !notificationsBtn.contains(event.target)) {
                notificationsDropdown.classList.add('hidden');
            }
            
            // Close user menu dropdown
            const userMenuBtn = document.querySelector('[onclick="toggleUserMenu()"]');
            const userMenuDropdown = document.getElementById('userMenuDropdown');
            if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden') &&
                !userMenuDropdown.contains(event.target) && 
                userMenuBtn && !userMenuBtn.contains(event.target)) {
                userMenuDropdown.classList.add('hidden');
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage) || 
                    (currentPage === '' && href.includes('export'))) {
                    item.classList.add('active');
                }
            });
            
            // Add loading indicators to export buttons
            const exportLinks = document.querySelectorAll('a[href*="export="]');
            exportLinks.forEach(link => {
                link.addEventListener('click', function(e) {
                    const originalText = this.innerHTML;
                    this.innerHTML = '<i class="fas fa-spinner fa-spin"></i><span> Preparing Export...</span>';
                    this.classList.add('opacity-75', 'cursor-wait');
                    
                    // Reset after 5 seconds if still on page
                    setTimeout(() => {
                        this.innerHTML = originalText;
                        this.classList.remove('opacity-75', 'cursor-wait');
                    }, 5000);
                });
            });
        });
    </script>
</body>
</html>