<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login_admin.php');
}

$conn = getDBConnection();

if (!isset($_GET['id'])) {
    redirect('admin_customers.php');
}

$customer_id = (int)$_GET['id'];

// Get customer details
$query = "SELECT * FROM customer WHERE customer_id = $customer_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect('admin_customers.php');
}

$customer = mysqli_fetch_assoc($result);

// Get customer reports
$reports_query = "SELECT r.*, 
                  (SELECT GROUP_CONCAT(c.category_name) 
                   FROM report_categories rc 
                   JOIN waste_categories c ON rc.category_id = c.category_id 
                   WHERE rc.report_id = r.report_id) as categories,
                  ct.team_name,
                  ct.vehicle_number
                  FROM reports r 
                  LEFT JOIN collection_teams ct ON r.assigned_team_id = ct.team_id
                  WHERE r.customer_id = $customer_id 
                  ORDER BY r.created_at DESC";
$reports_result = mysqli_query($conn, $reports_query);

// First, check what columns exist in the reports table
$check_columns_query = "SHOW COLUMNS FROM reports";
$columns_result = mysqli_query($conn, $check_columns_query);
$columns_exist = [];
while ($column = mysqli_fetch_assoc($columns_result)) {
    $columns_exist[$column['Field']] = true;
}

// Build statistics query based on available columns
$stats_query = "SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN collection_status = 'pending' OR collection_status = 'not_assigned' THEN 1 ELSE 0 END) as pending_reports,
                SUM(CASE WHEN collection_status = 'assigned' OR collection_status = 'in_transit' OR collection_status = 'collecting' THEN 1 ELSE 0 END) as in_progress_reports,
                SUM(CASE WHEN collection_status = 'collected' THEN 1 ELSE 0 END) as completed_reports,
                SUM(CASE WHEN collection_status = 'failed' THEN 1 ELSE 0 END) as failed_reports";

// Add points_awarded if column exists
if (isset($columns_exist['points_awarded'])) {
    $stats_query .= ", SUM(points_awarded) as total_points_earned";
} else {
    $stats_query .= ", 0 as total_points_earned";
}

$stats_query .= " FROM reports WHERE customer_id = $customer_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get recent activity
$activity_query = "SELECT 
                   'report_submitted' as type,
                   'Report Submitted' as title,
                   CONCAT('Submitted report: ', title) as description,
                   created_at as date,
                   'fa-flag' as icon,
                   'blue' as color
                   FROM reports 
                   WHERE customer_id = $customer_id 
                   UNION ALL
                   SELECT 
                   'status_updated' as type,
                   'Status Updated' as title,
                   CONCAT('Report status updated to: ', collection_status) as description,
                   created_at as date,
                   'fa-sync-alt' as icon,
                   'purple' as color
                   FROM reports 
                   WHERE customer_id = $customer_id AND collection_status != 'pending'
                   ORDER BY date DESC 
                   LIMIT 10";
$activity_result = mysqli_query($conn, $activity_query);

// Calculate total points earned from completed reports
$points_query = "SELECT COUNT(*) as completed_count 
                 FROM reports 
                 WHERE customer_id = $customer_id AND collection_status = 'collected'";
$points_result = mysqli_query($conn, $points_query);
$points_data = mysqli_fetch_assoc($points_result);
$estimated_points = $points_data['completed_count'] * 10; // Assuming 10 points per completed report
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customer Details - WasteWise Admin</title>
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
        
        /* Swipe indicator for mobile */
        .swipe-indicator {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }
        
        /* Input styles */
        .input-focus:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: rgba(254, 243, 199, 0.2); color: #fef3c7; border: 1px solid rgba(254, 243, 199, 0.3); }
        .status-in_progress { background: rgba(219, 234, 254, 0.2); color: #dbeafe; border: 1px solid rgba(219, 234, 254, 0.3); }
        .status-completed { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .status-rejected { background: rgba(254, 226, 226, 0.2); color: #fee2e2; border: 1px solid rgba(254, 226, 226, 0.3); }
        
        /* Priority badges */
        .priority-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .priority-low { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .priority-medium { background: rgba(254, 243, 199, 0.2); color: #fef3c7; border: 1px solid rgba(254, 243, 199, 0.3); }
        .priority-high { background: rgba(254, 226, 226, 0.2); color: #fee2e2; border: 1px solid rgba(254, 226, 226, 0.3); }
        
        /* Action buttons */
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-view { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .btn-view:hover { background: rgba(59, 130, 246, 0.3); }
        .btn-edit { background: rgba(74, 222, 128, 0.2); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
        .btn-edit:hover { background: rgba(74, 222, 128, 0.3); }
        .btn-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.3); }
        
        /* Table styles */
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table th {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.7);
        }
        
        .table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Customer card */
        .customer-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .customer-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
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
        
        /* Additional styles for customer details */
        .avatar-initial {
            width: 80px;
            height: 80px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-weight: bold;
            font-size: 32px;
            color: white;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        }
        
        .progress-bar {
            height: 8px;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 4px;
            overflow: hidden;
            margin: 8px 0;
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            transition: width 0.3s ease;
        }
        
        .info-item {
            display: flex;
            align-items: center;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .info-item:last-child {
            border-bottom: none;
        }
        
        .info-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 16px;
            flex-shrink: 0;
        }
        
        .activity-item {
            display: flex;
            align-items: flex-start;
            padding: 12px;
            border-radius: 12px;
            margin-bottom: 8px;
            transition: all 0.3s;
        }
        
        .activity-item:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .activity-icon {
            width: 40px;
            height: 40px;
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            margin-right: 12px;
            flex-shrink: 0;
        }
        
        .stats-card {
            transition: all 0.3s;
        }
        
        .stats-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15) !important;
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
            <a href="admin_customers.php" class="flex items-center space-x-3 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-white rounded-xl border border-purple-500/30">
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
                </a>
                
                <a href="admin_customers.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
                    <i class="fas fa-users text-green-400 w-6"></i>
                    <span>Customers</span>
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
                <h1 class="text-white text-2xl font-bold">Customer Details</h1>
                <p class="text-gray-400 text-sm">Manage customer information and activity</p>
            </div>
            
            <!-- Desktop Right Actions -->
            <div class="flex items-center space-x-4">
                <!-- Back Button -->
                <a href="admin_customers.php" class="text-white hover:text-green-400 transition-colors hidden md:flex items-center">
                    <i class="fas fa-arrow-left mr-2"></i>
                    Back to Customers
                </a>
                
                <!-- Notifications -->
                <div class="relative">
                    <button onclick="toggleNotifications()" class="text-white hover:text-green-400 transition-colors">
                        <i class="fas fa-bell text-xl"></i>
                    </button>
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
                <div class="flex flex-col md:flex-row md:items-center justify-between">
                    <div>
                        <h1 class="text-4xl sm:text-5xl md:text-6xl font-bold mb-4 md:mb-6 animate-title leading-tight">
                            Customer Profile
                        </h1>
                        
                        <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                            Viewing details for <span class="text-green-400 font-semibold"><?php echo htmlspecialchars($customer['full_name']); ?></span>
                        </p>
                    </div>
                    
                    <!-- Mobile Back Button -->
                    <a href="admin_customers.php" class="md:hidden flex items-center text-white hover:text-green-400 mb-6">
                        <i class="fas fa-arrow-left mr-2"></i>
                        Back to Customers
                    </a>
                </div>
            </div>

            <!-- Customer Header Card -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-8 fade-in-up">
                <div class="flex flex-col md:flex-row md:items-center">
                    <div class="flex items-center mb-6 md:mb-0 md:mr-8">
                        <div class="avatar-initial">
                            <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                        </div>
                        <div class="ml-6">
                            <h2 class="text-2xl md:text-3xl font-bold text-white"><?php echo htmlspecialchars($customer['full_name']); ?></h2>
                            <div class="flex items-center mt-2">
                                <i class="fas fa-envelope text-gray-400 mr-2"></i>
                                <span class="text-gray-300"><?php echo htmlspecialchars($customer['customer_email']); ?></span>
                            </div>
                            <div class="flex items-center mt-1">
                                <i class="fas fa-calendar text-gray-400 mr-2"></i>
                                <span class="text-gray-300">Joined <?php echo date('M d, Y', strtotime($customer['created_at'])); ?></span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-2 md:grid-cols-3 gap-4 md:gap-8 flex-1">
                        <div class="text-center">
                            <div class="text-3xl font-bold text-green-400"><?php echo $customer['points']; ?></div>
                            <div class="text-gray-400 text-sm mt-1">Points</div>
                        </div>
                        <div class="text-center">
                            <div class="text-3xl font-bold text-blue-400"><?php echo $stats['total_reports'] ?? 0; ?></div>
                            <div class="text-gray-400 text-sm mt-1">Total Reports</div>
                        </div>
                        <div class="text-center col-span-2 md:col-span-1">
                            <div class="text-3xl font-bold text-purple-400"><?php echo ($stats['total_reports'] ?? 0) > 0 ? round(($stats['completed_reports'] / $stats['total_reports']) * 100) : 0; ?>%</div>
                            <div class="text-gray-400 text-sm mt-1">Success Rate</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8 md:mb-12 stagger-animation">
                <?php 
                $statCards = [
                    ['icon' => 'fas fa-flag', 'value' => $stats['total_reports'] ?? 0, 'label' => 'Total Reports', 'color' => 'from-blue-500 to-purple-600', 'bg' => 'rgba(59, 130, 246, 0.1)'],
                    ['icon' => 'fas fa-clock', 'value' => $stats['pending_reports'] ?? 0, 'label' => 'Pending Reports', 'color' => 'from-yellow-500 to-orange-500', 'bg' => 'rgba(234, 179, 8, 0.1)'],
                    ['icon' => 'fas fa-sync-alt', 'value' => $stats['in_progress_reports'] ?? 0, 'label' => 'In Progress', 'color' => 'from-purple-500 to-pink-600', 'bg' => 'rgba(168, 85, 247, 0.1)'],
                    ['icon' => 'fas fa-check-circle', 'value' => $stats['completed_reports'] ?? 0, 'label' => 'Completed', 'color' => 'from-green-500 to-emerald-600', 'bg' => 'rgba(34, 197, 94, 0.1)'],
                ];
                
                foreach ($statCards as $card): ?>
                <div class="glass-card rounded-2xl p-6 stats-card hover-lift" style="background: <?php echo $card['bg']; ?>">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r <?php echo $card['color']; ?> flex items-center justify-center">
                            <i class="<?php echo $card['icon']; ?> text-white text-xl"></i>
                        </div>
                        <span class="text-white text-2xl font-bold"><?php echo $card['value']; ?></span>
                    </div>
                    <div class="text-gray-300 text-sm"><?php echo $card['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8">
                <!-- Customer Information -->
                <div class="lg:col-span-1">
                    <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 md:mb-0 fade-in-up">
                        <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                            <i class="fas fa-info-circle mr-3 text-blue-400"></i>
                            Customer Information
                        </h2>
                        
                        <div class="space-y-4">
                            <div class="info-item">
                                <div class="info-icon bg-blue-500/20">
                                    <i class="fas fa-user text-blue-400"></i>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm">Full Name</div>
                                    <div class="text-white font-medium"><?php echo htmlspecialchars($customer['full_name']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon bg-green-500/20">
                                    <i class="fas fa-envelope text-green-400"></i>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm">Email Address</div>
                                    <div class="text-white font-medium"><?php echo htmlspecialchars($customer['customer_email']); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon bg-purple-500/20">
                                    <i class="fas fa-trophy text-purple-400"></i>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm">Reward Points</div>
                                    <div class="text-white font-medium"><?php echo $customer['points']; ?> points</div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon bg-yellow-500/20">
                                    <i class="fas fa-calendar text-yellow-400"></i>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm">Member Since</div>
                                    <div class="text-white font-medium"><?php echo date('F d, Y', strtotime($customer['created_at'])); ?></div>
                                </div>
                            </div>
                            
                            <div class="info-item">
                                <div class="info-icon bg-red-500/20">
                                    <i class="fas fa-map-marker-alt text-red-400"></i>
                                </div>
                                <div>
                                    <div class="text-gray-400 text-sm">Location</div>
                                    <div class="text-white font-medium">
                                        <?php 
                                        if (!empty($customer['address'])) {
                                            echo htmlspecialchars($customer['address']);
                                        } else {
                                            echo '<span class="text-gray-400">Not specified</span>';
                                        }
                                        ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Action Buttons -->
                        <div class="mt-8 pt-6 border-t border-white/10">
                            <div class="grid grid-cols-2 gap-3">
                                <a href="mailto:<?php echo htmlspecialchars($customer['customer_email']); ?>" 
                                   class="flex items-center justify-center space-x-2 bg-blue-500/20 text-blue-400 py-3 px-4 rounded-lg hover:bg-blue-500/30 transition-colors border border-blue-500/30">
                                    <i class="fas fa-envelope"></i>
                                    <span>Send Email</span>
                                </a>
                                <a href="edit_customer.php?id=<?php echo $customer_id; ?>" 
                                   class="flex items-center justify-center space-x-2 bg-green-500/20 text-green-400 py-3 px-4 rounded-lg hover:bg-green-500/30 transition-colors border border-green-500/30">
                                    <i class="fas fa-edit"></i>
                                    <span>Edit</span>
                                </a>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Recent Activity -->
                    <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up" style="animation-delay: 0.2s">
                        <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                            <i class="fas fa-history mr-3 text-purple-400"></i>
                            Recent Activity
                        </h2>
                        
                        <div class="space-y-4 max-h-80 overflow-y-auto custom-scrollbar pr-2">
                            <?php if ($activity_result && mysqli_num_rows($activity_result) > 0): ?>
                                <?php while($activity = mysqli_fetch_assoc($activity_result)): ?>
                                    <div class="activity-item">
                                        <div class="activity-icon <?php echo 'bg-' . $activity['color'] . '-500/20'; ?>">
                                            <i class="fas <?php echo $activity['icon']; ?> <?php echo 'text-' . $activity['color'] . '-400'; ?>"></i>
                                        </div>
                                        <div class="flex-1 min-w-0">
                                            <h4 class="font-medium text-white"><?php echo $activity['title']; ?></h4>
                                            <p class="text-gray-400 text-sm mt-1"><?php echo htmlspecialchars($activity['description']); ?></p>
                                            <div class="text-gray-500 text-xs mt-2">
                                                <?php echo date('M d, Y h:i A', strtotime($activity['date'])); ?>
                                            </div>
                                        </div>
                                    </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <div class="text-center py-8">
                                    <div class="w-16 h-16 mx-auto mb-4 rounded-full bg-gray-800/50 flex items-center justify-center">
                                        <i class="fas fa-history text-gray-400 text-xl"></i>
                                    </div>
                                    <p class="text-gray-400">No recent activity</p>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Customer Reports & Analytics -->
                <div class="lg:col-span-2">
                    <!-- Customer Reports -->
                    <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 fade-in-up">
                        <div class="flex justify-between items-center mb-6">
                            <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                                <i class="fas fa-flag mr-3 text-blue-400"></i>
                                Customer Reports
                            </h2>
                            <span class="text-gray-400 text-sm">
                                <?php echo mysqli_num_rows($reports_result); ?> reports
                            </span>
                        </div>
                        
                        <?php if (mysqli_num_rows($reports_result) > 0): ?>
                            <div class="table-container">
                                <table class="table w-full">
                                    <thead>
                                        <tr>
                                            <th>Report ID</th>
                                            <th>Title</th>
                                            <th>Status</th>
                                            <th>Date</th>
                                            <th>Actions</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php mysqli_data_seek($reports_result, 0); ?>
                                        <?php while($report = mysqli_fetch_assoc($reports_result)): ?>
                                            <tr class="hover:bg-white/5 transition-colors">
                                                <td class="font-mono">#<?php echo $report['report_id']; ?></td>
                                                <td>
                                                    <div class="text-white font-medium"><?php echo htmlspecialchars($report['title']); ?></div>
                                                    <?php if (!empty($report['categories'])): ?>
                                                        <div class="text-gray-400 text-xs mt-1">
                                                            <i class="fas fa-tags mr-1"></i>
                                                            <?php echo htmlspecialchars($report['categories']); ?>
                                                        </div>
                                                    <?php endif; ?>
                                                </td>
                                                <td>
                                                    <?php 
                                                    $status_class = 'status-' . ($report['collection_status'] ?? 'pending');
                                                    $status_text = ucfirst(str_replace('_', ' ', $report['collection_status'] ?? 'pending'));
                                                    ?>
                                                    <span class="status-badge <?php echo $status_class; ?>">
                                                        <?php echo $status_text; ?>
                                                    </span>
                                                </td>
                                                <td class="text-gray-400 text-sm">
                                                    <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                                </td>
                                                <td>
                                                    <a href="view_report.php?id=<?php echo $report['report_id']; ?>" 
                                                       class="btn-action btn-view">
                                                        <i class="fas fa-eye"></i>
                                                    </a>
                                                </td>
                                            </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <!-- Report Statistics Summary -->
                            <div class="mt-6 p-4 bg-white/5 rounded-lg">
                                <h3 class="text-white font-medium mb-3">Report Statistics</h3>
                                <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-blue-400"><?php echo $stats['total_reports'] ?? 0; ?></div>
                                        <div class="text-gray-400 text-sm">Total</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-yellow-400"><?php echo $stats['pending_reports'] ?? 0; ?></div>
                                        <div class="text-gray-400 text-sm">Pending</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-purple-400"><?php echo $stats['in_progress_reports'] ?? 0; ?></div>
                                        <div class="text-gray-400 text-sm">In Progress</div>
                                    </div>
                                    <div class="text-center">
                                        <div class="text-lg font-bold text-green-400"><?php echo $stats['completed_reports'] ?? 0; ?></div>
                                        <div class="text-gray-400 text-sm">Completed</div>
                                    </div>
                                </div>
                            </div>
                        <?php else: ?>
                            <div class="text-center py-12">
                                <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-gray-800/50 flex items-center justify-center">
                                    <i class="fas fa-flag text-gray-400 text-2xl"></i>
                                </div>
                                <h3 class="text-lg font-medium text-white mb-2">No Reports Yet</h3>
                                <p class="text-gray-400 mb-6">This customer hasn't submitted any reports yet.</p>
                            </div>
                        <?php endif; ?>
                    </div>
                    
                    <!-- Performance Metrics -->
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <!-- Points Summary -->
                        <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up" style="animation-delay: 0.3s">
                            <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                                <i class="fas fa-trophy mr-3 text-yellow-400"></i>
                                Points Summary
                            </h2>
                            <div class="space-y-6">
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 rounded-lg bg-green-500/20 flex items-center justify-center">
                                            <i class="fas fa-trophy text-green-400"></i>
                                        </div>
                                        <div>
                                            <p class="text-gray-400 text-sm">Estimated Points Earned</p>
                                            <p class="font-bold text-lg text-white"><?php echo $estimated_points; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="flex items-center justify-between">
                                    <div class="flex items-center space-x-3">
                                        <div class="w-12 h-12 rounded-lg bg-blue-500/20 flex items-center justify-center">
                                            <i class="fas fa-star text-blue-400"></i>
                                        </div>
                                        <div>
                                            <p class="text-gray-400 text-sm">Current Points Balance</p>
                                            <p class="font-bold text-lg text-white"><?php echo $customer['points']; ?></p>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="pt-4 border-t border-white/10">
                                    <p class="text-gray-400 text-sm mb-2">Points Distribution</p>
                                    <div class="space-y-2">
                                        <div class="flex justify-between text-xs text-gray-400">
                                            <span>Completed Reports</span>
                                            <span class="font-medium text-white"><?php echo $stats['completed_reports'] ?? 0; ?> reports</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-green-500" 
                                                 style="width: <?php echo ($stats['total_reports'] ?? 0) > 0 ? ($stats['completed_reports'] / $stats['total_reports'] * 100) : 0; ?>%"></div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Performance Insights -->
                        <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up" style="animation-delay: 0.4s">
                            <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                                <i class="fas fa-chart-line mr-3 text-purple-400"></i>
                                Performance Insights
                            </h2>
                            <div class="space-y-6">
                                <div>
                                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                                        <span>Report Completion Rate</span>
                                        <span class="font-medium text-white">
                                            <?php echo ($stats['total_reports'] ?? 0) > 0 ? round(($stats['completed_reports'] / $stats['total_reports']) * 100) : 0; ?>%
                                        </span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill bg-blue-500" 
                                             style="width: <?php echo ($stats['total_reports'] ?? 0) > 0 ? ($stats['completed_reports'] / $stats['total_reports'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                                        <span>Active Engagement</span>
                                        <span class="font-medium text-white">
                                            <?php 
                                            $active_reports = ($stats['pending_reports'] ?? 0) + ($stats['in_progress_reports'] ?? 0);
                                            echo ($stats['total_reports'] ?? 0) > 0 ? round(($active_reports / $stats['total_reports']) * 100) : 0;
                                            ?>%
                                        </span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill bg-yellow-500" 
                                             style="width: <?php echo ($stats['total_reports'] ?? 0) > 0 ? ($active_reports / $stats['total_reports'] * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                                
                                <div>
                                    <div class="flex justify-between text-sm text-gray-400 mb-1">
                                        <span>Success Rate</span>
                                        <span class="font-medium text-white">
                                            <?php 
                                            $total_processed = ($stats['completed_reports'] ?? 0) + ($stats['failed_reports'] ?? 0);
                                            echo $total_processed > 0 ? round(($stats['completed_reports'] / $total_processed) * 100) : 0;
                                            ?>%
                                        </span>
                                    </div>
                                    <div class="progress-bar">
                                        <div class="progress-fill bg-green-500" 
                                             style="width: <?php echo $total_processed > 0 ? ($stats['completed_reports'] / $total_processed * 100) : 0; ?>%"></div>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="mt-6 pt-6 border-t border-white/10">
                                <p class="text-gray-400 text-sm mb-2">Customer Level</p>
                                <div class="flex items-center">
                                    <div class="flex-1">
                                        <div class="flex justify-between text-xs text-gray-500 mb-1">
                                            <span>Beginner</span>
                                            <span>Expert</span>
                                        </div>
                                        <div class="progress-bar">
                                            <div class="progress-fill bg-gradient-to-r from-yellow-500 to-red-500" 
                                                 style="width: <?php echo min(100, ($stats['total_reports'] ?? 0) * 10); ?>%"></div>
                                        </div>
                                    </div>
                                    <span class="ml-4 px-3 py-1 bg-blue-500/20 text-blue-400 text-xs font-medium rounded-full border border-blue-500/30">
                                        Level <?php echo min(10, ceil(($stats['total_reports'] ?? 0) / 5) + 1); ?>
                                    </span>
                                </div>
                            </div>
                        </div>
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
                            Administrative portal for managing waste management systems and community contributions.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Admin Links</h4>
                            <ul class="space-y-2">
                                <li><a href="admin_dashboard.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Dashboard</a></li>
                                <li><a href="admin_reports.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Reports</a></li>
                                <li><a href="admin_customers.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Customers</a></li>
                                <li><a href="admin_teams.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Teams</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">Admin Support</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-envelope mr-2 text-sm mt-1"></i> admin@wastewise.com
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-phone mr-2 text-sm mt-1"></i> +1 (555) 987-6543
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-shield-alt mr-2 text-sm mt-1"></i> Secure Admin Portal
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise Admin Portal. Restricted access only.</p>
                    <p class="mt-2">Viewing customer: <span class="text-green-400"><?php echo htmlspecialchars($customer['full_name']); ?></span></p>
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
            if (dropdown) {
                dropdown.classList.toggle('hidden');
            }
            
            // Close user menu if open
            const userMenu = document.getElementById('userMenuDropdown');
            if (userMenu) {
                userMenu.classList.add('hidden');
            }
        }
        
        // Toggle User Menu Dropdown
        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            dropdown.classList.toggle('hidden');
            
            // Close notifications if open
            const notifications = document.getElementById('notificationsDropdown');
            if (notifications) {
                notifications.classList.add('hidden');
            }
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
        
        // Improve mobile performance by disabling some animations on scroll
        let scrollTimer;
        window.addEventListener('scroll', function() {
            document.body.classList.add('disable-animations');
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                document.body.classList.remove('disable-animations');
            }, 100);
        });
        
        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.hover-lift');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
            
            // Add active class to current nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage) || 
                    (currentPage === '' && href.includes('dashboard'))) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>