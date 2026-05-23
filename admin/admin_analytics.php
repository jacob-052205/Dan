<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('index.php');
}

$conn = getDBConnection();

// Get analytics data
$analytics = [];

// Monthly reports statistics
$monthly_query = "SELECT 
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
$monthly_result = mysqli_query($conn, $monthly_query);
$analytics['monthly'] = [];
while($row = mysqli_fetch_assoc($monthly_result)) {
    $analytics['monthly'][] = $row;
}

// Category statistics
$category_query = "SELECT wc.category_name, COUNT(rc.report_id) as count
                  FROM waste_categories wc 
                  LEFT JOIN report_categories rc ON wc.category_id = rc.category_id
                  GROUP BY wc.category_id 
                  ORDER BY count DESC";
$category_result = mysqli_query($conn, $category_query);
$analytics['categories'] = [];
while($row = mysqli_fetch_assoc($category_result)) {
    $analytics['categories'][] = $row;
}

// Top performing customers
$top_customers_query = "SELECT c.customer_id, c.full_name, 
                       COUNT(r.report_id) as total_reports,
                       SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed,
                       c.points
                       FROM customer c 
                       LEFT JOIN reports r ON c.customer_id = r.customer_id
                       GROUP BY c.customer_id 
                       ORDER BY total_reports DESC 
                       LIMIT 10";
$top_customers_result = mysqli_query($conn, $top_customers_query);
$analytics['top_customers'] = [];
while($row = mysqli_fetch_assoc($top_customers_result)) {
    $analytics['top_customers'][] = $row;
}

// Resolution time statistics
$resolution_query = "SELECT 
                    AVG(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as avg_hours,
                    MIN(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as min_hours,
                    MAX(TIMESTAMPDIFF(HOUR, created_at, updated_at)) as max_hours
                    FROM reports 
                    WHERE status = 'completed' AND updated_at > created_at";
$resolution_result = mysqli_query($conn, $resolution_query);
$analytics['resolution'] = mysqli_fetch_assoc($resolution_result);

// Daily statistics for current month
$daily_query = "SELECT 
               DATE(created_at) as date,
               COUNT(*) as count
               FROM reports 
               WHERE created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
               GROUP BY DATE(created_at)
               ORDER BY date";
$daily_result = mysqli_query($conn, $daily_query);
$analytics['daily'] = [];
while($row = mysqli_fetch_assoc($daily_result)) {
    $analytics['daily'][] = $row;
}

// Performance metrics
$metrics_query = "SELECT 
                 COUNT(*) as total_reports,
                 SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) as completed_reports,
                 ROUND(SUM(CASE WHEN r.status = 'completed' THEN 1 ELSE 0 END) / COUNT(*) * 100, 1) as completion_rate,
                 COUNT(DISTINCT r.customer_id) as active_customers,
                 AVG(c.points) as avg_points_per_customer
                 FROM reports r 
                 JOIN customer c ON r.customer_id = c.customer_id
                 WHERE r.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)";
$metrics_result = mysqli_query($conn, $metrics_query);
$analytics['metrics'] = mysqli_fetch_assoc($metrics_result);

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
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Analytics Dashboard - WasteWise</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
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
        
        /* Analytics specific styles */
        .chart-container {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 20px;
            height: 300px;
            position: relative;
        }
        
        .analytics-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .analytics-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .progress-bar-bg {
            background: rgba(255, 255, 255, 0.1);
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            margin-top: 8px;
        }
        
        .progress-bar-fill {
            height: 100%;
            background: linear-gradient(90deg, #4ade80, #22d3ee);
            border-radius: 4px;
        }
        
        .time-badge {
            padding: 4px 10px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .time-fast { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .time-medium { background: rgba(254, 243, 199, 0.2); color: #fef3c7; border: 1px solid rgba(254, 243, 199, 0.3); }
        .time-slow { background: rgba(254, 226, 226, 0.2); color: #fee2e2; border: 1px solid rgba(254, 226, 226, 0.3); }
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
            <a href="admin_analytics.php" class="flex items-center space-x-3 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-white rounded-xl border border-purple-500/30">
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
                
                <a href="admin_analytics.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
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
                <h1 class="text-white text-2xl font-bold">Analytics Dashboard</h1>
                <p class="text-gray-400 text-sm">System performance metrics and reporting analytics</p>
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
                    Analytics Dashboard
                </h1>
                
                <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                    Comprehensive analytics and performance metrics for waste management system.
                </p>
                
                <!-- Filter Bar -->
                <div class="glass-card rounded-2xl p-4 md:p-6 mb-6 fade-in-up">
                    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4">
                        <div class="flex flex-wrap gap-3">
                            <select class="bg-white/5 border border-white/10 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-green-400 transition-colors">
                                <option class="bg-gray-900">Last 30 Days</option>
                                <option class="bg-gray-900">Last 90 Days</option>
                                <option class="bg-gray-900">Last 12 Months</option>
                                <option class="bg-gray-900">Year to Date</option>
                                <option class="bg-gray-900">Custom Range</option>
                            </select>
                            
                            <select class="bg-white/5 border border-white/10 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-green-400 transition-colors">
                                <option class="bg-gray-900">All Report Types</option>
                                <option class="bg-gray-900">Uncollected Waste</option>
                                <option class="bg-gray-900">Pickup Requests</option>
                            </select>
                            
                            <select class="bg-white/5 border border-white/10 text-white rounded-xl px-4 py-3 focus:outline-none focus:border-green-400 transition-colors">
                                <option class="bg-gray-900">All Status</option>
                                <option class="bg-gray-900">Pending Only</option>
                                <option class="bg-gray-900">Completed Only</option>
                            </select>
                        </div>
                        
                        <a href="admin_export.php?export=analytics" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transition-all flex items-center gap-2">
                            <i class="fas fa-download"></i>
                            <span>Export Data</span>
                        </a>
                    </div>
                </div>
            </div>

            <!-- Key Metrics -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8 md:mb-12 fade-in-up">
                <?php 
                $metricCards = [
                    [
                        'icon' => 'fas fa-flag', 
                        'value' => $analytics['metrics']['total_reports'] ?: 0, 
                        'label' => 'Total Reports (30 days)', 
                        'color' => 'from-blue-500 to-purple-600',
                        'trend' => 'positive',
                        'trend_text' => '12% from last month'
                    ],
                    [
                        'icon' => 'fas fa-check-circle', 
                        'value' => ($analytics['metrics']['completion_rate'] ?: 0) . '%', 
                        'label' => 'Completion Rate', 
                        'color' => 'from-green-500 to-emerald-600',
                        'trend' => 'positive',
                        'trend_text' => '5% improvement'
                    ],
                    [
                        'icon' => 'fas fa-users', 
                        'value' => $analytics['metrics']['active_customers'] ?: 0, 
                        'label' => 'Active Customers', 
                        'color' => 'from-indigo-500 to-purple-600',
                        'trend' => 'positive',
                        'trend_text' => '8% growth'
                    ],
                    [
                        'icon' => 'fas fa-clock', 
                        'value' => ($analytics['resolution']['avg_hours'] ? round($analytics['resolution']['avg_hours']) : 'N/A') . 'h', 
                        'label' => 'Avg Resolution Time', 
                        'color' => 'from-yellow-500 to-orange-500',
                        'trend' => 'negative',
                        'trend_text' => '2h slower'
                    ],
                ];
                
                foreach ($metricCards as $card): ?>
                <div class="glass-card rounded-2xl p-6 hover-lift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r <?php echo $card['color']; ?> flex items-center justify-center">
                            <i class="<?php echo $card['icon']; ?> text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-white text-2xl font-bold"><?php echo $card['value']; ?></span>
                            <div class="flex items-center gap-1 mt-1">
                                <i class="fas fa-arrow-<?php echo $card['trend']; ?> text-<?php echo $card['trend'] == 'positive' ? 'green' : 'red'; ?>-400 text-xs"></i>
                                <span class="text-<?php echo $card['trend'] == 'positive' ? 'green' : 'red'; ?>-400 text-sm"><?php echo $card['trend_text']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-gray-300 text-sm"><?php echo $card['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Reports Trend -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 md:mb-8 fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-chart-line mr-3 text-blue-400"></i>
                        Reports Trend - Last 12 Months
                    </h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="reportsChart"></canvas>
                </div>
            </div>

            <!-- Category Distribution -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 md:mb-8 fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-trash mr-3 text-green-400"></i>
                        Waste Category Distribution
                    </h2>
                </div>
                
                <div class="table-container">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Category</th>
                                <th>Reports Count</th>
                                <th>Percentage</th>
                                <th>Trend</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_category_reports = array_sum(array_column($analytics['categories'], 'count'));
                            foreach($analytics['categories'] as $category): 
                                $percentage = $total_category_reports > 0 ? round(($category['count'] / $total_category_reports) * 100, 1) : 0;
                            ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td>
                                    <div class="flex items-center gap-2">
                                        <div class="w-8 h-8 rounded-lg bg-gradient-to-r from-green-500 to-blue-500 flex items-center justify-center">
                                            <i class="fas fa-trash text-white text-xs"></i>
                                        </div>
                                        <span class="text-white font-medium"><?php echo htmlspecialchars($category['category_name']); ?></span>
                                    </div>
                                </td>
                                <td class="text-white font-bold"><?php echo $category['count']; ?></td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="text-white"><?php echo $percentage; ?>%</span>
                                        <div class="flex-1 progress-bar-bg">
                                            <div class="progress-bar-fill" style="width: <?php echo $percentage; ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                                <td>
                                    <?php if($percentage > 20): ?>
                                        <span class="time-badge time-slow">High Volume</span>
                                    <?php elseif($percentage > 10): ?>
                                        <span class="time-badge time-medium">Medium</span>
                                    <?php else: ?>
                                        <span class="time-badge time-fast">Low</span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Top Contributors -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 md:mb-8 fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-trophy mr-3 text-yellow-400"></i>
                        Top Contributors
                    </h2>
                    <a href="admin_customers.php" class="text-blue-400 hover:text-blue-300 text-sm font-semibold">
                        View All <i class="fas fa-arrow-right ml-1"></i>
                    </a>
                </div>
                
                <div class="table-container">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Customer</th>
                                <th>Total Reports</th>
                                <th>Completed</th>
                                <th>Points</th>
                                <th>Contribution %</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php 
                            $total_reports_all = $analytics['metrics']['total_reports'] ?: 1;
                            foreach($analytics['top_customers'] as $customer): 
                                $contribution = round(($customer['total_reports'] / $total_reports_all) * 100, 1);
                            ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td>
                                    <div class="flex items-center gap-3">
                                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-indigo-500 to-purple-600 flex items-center justify-center text-white font-bold">
                                            <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                                        </div>
                                        <div>
                                            <div class="text-white font-semibold"><?php echo htmlspecialchars($customer['full_name']); ?></div>
                                        </div>
                                    </div>
                                </td>
                                <td class="text-white font-bold"><?php echo $customer['total_reports']; ?></td>
                                <td class="text-green-400 font-bold"><?php echo $customer['completed']; ?></td>
                                <td>
                                    <span class="flex items-center gap-2 text-yellow-400 font-bold">
                                        <i class="fas fa-trophy"></i>
                                        <?php echo $customer['points']; ?>
                                    </span>
                                </td>
                                <td>
                                    <div class="flex items-center gap-3">
                                        <span class="text-white"><?php echo $contribution; ?>%</span>
                                        <div class="flex-1 progress-bar-bg">
                                            <div class="progress-bar-fill" style="width: <?php echo min($contribution, 100); ?>%"></div>
                                        </div>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Performance Statistics -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8 mb-6 md:mb-8 fade-in-up">
                <!-- Resolution Time -->
                <div class="analytics-card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-clock text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-white text-lg font-bold">Resolution Time</h3>
                            <p class="text-gray-400 text-sm">Report completion metrics</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Average</span>
                            <span class="text-white font-bold text-lg"><?php echo $analytics['resolution']['avg_hours'] ? round($analytics['resolution']['avg_hours']) : 'N/A'; ?>h</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Fastest</span>
                            <span class="text-green-400 font-semibold"><?php echo $analytics['resolution']['min_hours'] ?: 'N/A'; ?>h</span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Slowest</span>
                            <span class="text-red-400 font-semibold"><?php echo $analytics['resolution']['max_hours'] ?: 'N/A'; ?>h</span>
                        </div>
                    </div>
                </div>

                <!-- Customer Engagement -->
                <div class="analytics-card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-green-500 to-emerald-600 flex items-center justify-center">
                            <i class="fas fa-users text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-white text-lg font-bold">Customer Engagement</h3>
                            <p class="text-gray-400 text-sm">Points and activity metrics</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Avg Points/Customer</span>
                            <span class="text-white font-bold text-lg"><?php echo $analytics['metrics']['avg_points_per_customer'] ? round($analytics['metrics']['avg_points_per_customer']) : 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Active Customers</span>
                            <span class="text-blue-400 font-semibold"><?php echo $analytics['metrics']['active_customers'] ?: 0; ?></span>
                        </div>
                        <div class="flex justify-between items-center">
                            <span class="text-gray-300">Engagement Score</span>
                            <span class="text-green-400 font-semibold">High</span>
                        </div>
                    </div>
                </div>

                <!-- Report Types -->
                <div class="analytics-card">
                    <div class="flex items-center gap-3 mb-6">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-yellow-500 to-orange-500 flex items-center justify-center">
                            <i class="fas fa-chart-pie text-white text-xl"></i>
                        </div>
                        <div>
                            <h3 class="text-white text-lg font-bold">Report Types</h3>
                            <p class="text-gray-400 text-sm">Distribution by type</p>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-300">Uncollected Waste</span>
                                <span class="text-white">65%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: 65%"></div>
                            </div>
                        </div>
                        <div class="space-y-2">
                            <div class="flex justify-between">
                                <span class="text-gray-300">Pickup Requests</span>
                                <span class="text-white">35%</span>
                            </div>
                            <div class="progress-bar-bg">
                                <div class="progress-bar-fill" style="width: 35%"></div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Daily Activity -->
            <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-calendar-day mr-3 text-purple-400"></i>
                        Daily Activity - Last 30 Days
                    </h2>
                </div>
                
                <div class="chart-container">
                    <canvas id="dailyChart"></canvas>
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
                            Advanced analytics portal for monitoring waste management system performance.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Admin Links</h4>
                            <ul class="space-y-2">
                                <li><a href="admin_dashboard.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Dashboard</a></li>
                                <li><a href="admin_reports.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Reports</a></li>
                                <li><a href="admin_analytics.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Analytics</a></li>
                                <li><a href="admin_customers.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Customers</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">Analytics Support</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-chart-bar mr-2 text-sm mt-1"></i> Advanced Analytics Dashboard
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-download mr-2 text-sm mt-1"></i> Data Export Available
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-shield-alt mr-2 text-sm mt-1"></i> Real-time Monitoring
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise Analytics Portal. Advanced monitoring and insights.</p>
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
        
        // Initialize Charts
        document.addEventListener('DOMContentLoaded', function() {
            // Reports Trend Chart
            var reportsCtx = document.getElementById('reportsChart').getContext('2d');
            var reportsChart = new Chart(reportsCtx, {
                type: 'line',
                data: {
                    labels: [
                        <?php foreach($analytics['monthly'] as $month): ?>
                            '<?php echo date('M Y', strtotime($month['month'] . '-01')); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Total Reports',
                        data: [
                            <?php foreach($analytics['monthly'] as $month): ?>
                                <?php echo $month['total']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#3b82f6',
                        backgroundColor: 'rgba(59, 130, 246, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }, {
                        label: 'Completed',
                        data: [
                            <?php foreach($analytics['monthly'] as $month): ?>
                                <?php echo $month['completed']; ?>,
                            <?php endforeach; ?>
                        ],
                        borderColor: '#10b981',
                        backgroundColor: 'rgba(16, 185, 129, 0.1)',
                        borderWidth: 2,
                        tension: 0.4,
                        fill: true
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)'
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'rgba(255, 255, 255, 0.8)',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });

            // Daily Activity Chart
            var dailyCtx = document.getElementById('dailyChart').getContext('2d');
            var dailyChart = new Chart(dailyCtx, {
                type: 'bar',
                data: {
                    labels: [
                        <?php foreach($analytics['daily'] as $day): ?>
                            '<?php echo date('d M', strtotime($day['date'])); ?>',
                        <?php endforeach; ?>
                    ],
                    datasets: [{
                        label: 'Reports per Day',
                        data: [
                            <?php foreach($analytics['daily'] as $day): ?>
                                <?php echo $day['count']; ?>,
                            <?php endforeach; ?>
                        ],
                        backgroundColor: 'rgba(139, 92, 246, 0.5)',
                        borderColor: '#8b5cf6',
                        borderWidth: 1
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)'
                            }
                        },
                        x: {
                            grid: {
                                color: 'rgba(255, 255, 255, 0.1)'
                            },
                            ticks: {
                                color: 'rgba(255, 255, 255, 0.7)',
                                maxRotation: 45
                            }
                        }
                    },
                    plugins: {
                        legend: {
                            labels: {
                                color: 'rgba(255, 255, 255, 0.8)',
                                font: {
                                    size: 12
                                }
                            }
                        }
                    }
                }
            });
            
            // Add active class to current nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage) || 
                    (currentPage === '' && href.includes('analytics'))) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>