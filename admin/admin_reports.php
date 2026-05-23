<?php
require_once '../config.php';

// Check if admin is logged in
if (!isAdmin()) {
    redirect('index.php');
}

$conn = getDBConnection();

// Search functionality
$search = '';
$where = '1=1';

if (isset($_GET['search'])) {
    $search = sanitize($_GET['search']);
    $where = "(r.title LIKE '%$search%' OR r.description LIKE '%$search%' OR c.full_name LIKE '%$search%')";
}

// Filter by status
if (isset($_GET['status']) && $_GET['status'] != 'all') {
    $status = sanitize($_GET['status']);
    $where .= " AND r.status = '$status'";
}

// Get reports with pagination
$limit = 20;
$page = isset($_GET['page']) ? (int)$_GET['page'] : 1;
$offset = ($page - 1) * $limit;

$query = "SELECT SQL_CALC_FOUND_ROWS r.*, c.full_name as customer_name 
          FROM reports r 
          JOIN customer c ON r.customer_id = c.customer_id 
          WHERE $where 
          ORDER BY r.created_at DESC 
          LIMIT $limit OFFSET $offset";

$reports = mysqli_query($conn, $query);
$total_rows = mysqli_fetch_assoc(mysqli_query($conn, "SELECT FOUND_ROWS() as total"))['total'];
$total_pages = ceil($total_rows / $limit);

// Delete report
if (isset($_GET['delete'])) {
    $report_id = (int)$_GET['delete'];
    $delete_query = "DELETE FROM reports WHERE report_id = $report_id";
    if (mysqli_query($conn, $delete_query)) {
        $_SESSION['success'] = "Report deleted successfully!";
    } else {
        $_SESSION['error'] = "Error deleting report!";
    }
    redirect('admin_reports.php');
}

// Update status
if (isset($_POST['update_status'])) {
    $report_id = (int)$_POST['report_id'];
    $status = sanitize($_POST['status']);
    
    $update_query = "UPDATE reports SET status = '$status', updated_at = NOW() WHERE report_id = $report_id";
    
    if (mysqli_query($conn, $update_query)) {
        // Create notification
        $report_query = "SELECT customer_id, title FROM reports WHERE report_id = $report_id";
        $report_result = mysqli_query($conn, $report_query);
        $report_data = mysqli_fetch_assoc($report_result);
        
        $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                              VALUES ({$report_data['customer_id']}, 'customer', 'Report Status Updated', 
                              'Your report \"{$report_data['title']}\" status has been updated to $status', 'status_update')";
        mysqli_query($conn, $notification_query);
        
        $_SESSION['success'] = "Status updated successfully!";
    } else {
        $_SESSION['error'] = "Error updating status!";
    }
    
    redirect('admin_reports.php');
}

// Get statistics for sidebar
$stats = [];
$queries = [
    'total_reports' => "SELECT COUNT(*) as count FROM reports",
    'pending_reports' => "SELECT COUNT(*) as count FROM reports WHERE status = 'pending'",
    'in_progress_reports' => "SELECT COUNT(*) as count FROM reports WHERE status = 'in_progress'",
    'completed_reports' => "SELECT COUNT(*) as count FROM reports WHERE status = 'completed'",
    'rejected_reports' => "SELECT COUNT(*) as count FROM reports WHERE status = 'rejected'",
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
    <title>Reports Management - WasteWise</title>
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
        
        /* Status badges */
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .status-pending { background: rgba(254, 243, 199, 0.2); color: #fef3c7; border: 1px solid rgba(254, 243, 199, 0.3); }
        .status-pending:hover { background: rgba(254, 243, 199, 0.3); }
        .status-in_progress { background: rgba(219, 234, 254, 0.2); color: #dbeafe; border: 1px solid rgba(219, 234, 254, 0.3); }
        .status-in_progress:hover { background: rgba(219, 234, 254, 0.3); }
        .status-completed { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .status-completed:hover { background: rgba(220, 252, 231, 0.3); }
        .status-rejected { background: rgba(254, 226, 226, 0.2); color: #fee2e2; border: 1px solid rgba(254, 226, 226, 0.3); }
        .status-rejected:hover { background: rgba(254, 226, 226, 0.3); }
        
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
        
        /* Form inputs */
        .form-input {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 12px;
            color: white;
            padding: 14px 16px;
            width: 100%;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        /* Modal */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
        }
        
        .modal-content {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            margin: 10% auto;
            padding: 30px;
            width: 90%;
            max-width: 500px;
            animation: modalFadeIn 0.3s ease-out;
        }
        
        @keyframes modalFadeIn {
            from {
                opacity: 0;
                transform: translateY(-30px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Pagination */
        .pagination-link {
            padding: 8px 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 8px;
            color: rgba(255, 255, 255, 0.7);
            text-decoration: none;
            transition: all 0.3s;
        }
        
        .pagination-link:hover {
            background: rgba(255, 255, 255, 0.1);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .pagination-link.active {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border-color: rgba(59, 130, 246, 0.3);
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
            <a href="admin_reports.php" class="flex items-center space-x-3 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-white rounded-xl border border-purple-500/30">
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
                
                <a href="admin_reports.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
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
                <h1 class="text-white text-2xl font-bold">Reports Management</h1>
                <p class="text-gray-400 text-sm">Manage and track all waste management reports</p>
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
                        <a href="admin_reports.php?status=pending" class="block text-center text-blue-400 text-sm mt-3 hover:text-blue-300">
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
                    Reports Management
                </h1>
                
                <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                    Manage, review, and track all waste management reports submitted by customers.
                </p>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 border border-green-500/30 bg-green-500/10 fade-in-up">
                    <div class="flex items-center gap-3 text-green-400">
                        <i class="fas fa-check-circle text-xl"></i>
                        <span class="font-semibold"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if(isset($_SESSION['error'])): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 border border-red-500/30 bg-red-500/10 fade-in-up">
                    <div class="flex items-center gap-3 text-red-400">
                        <i class="fas fa-exclamation-circle text-xl"></i>
                        <span class="font-semibold"><?php echo $_SESSION['error']; unset($_SESSION['error']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
                
                <!-- Search Bar -->
                <div class="glass-card rounded-2xl p-6 mb-6 fade-in-up">
                    <form method="GET" action="">
                        <div class="flex flex-col md:flex-row gap-4">
                            <div class="flex-1">
                                <input type="text" 
                                       name="search" 
                                       class="form-input"
                                       placeholder="Search reports by title, description, or customer..."
                                       value="<?php echo htmlspecialchars($search); ?>">
                            </div>
                            
                            <select name="status" class="form-input" style="width: auto; min-width: 180px;">
                                <option value="all" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : ''; ?>>All Status</option>
                                <option value="pending" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                <option value="in_progress" <?php echo (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                <option value="completed" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                <option value="rejected" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                            </select>
                            
                            <button type="submit" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-8 py-3 rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transition-all flex items-center gap-2">
                                <i class="fas fa-search"></i>
                                <span>Search</span>
                            </button>
                            
                            <?php if($search || (isset($_GET['status']) && $_GET['status'] != 'all')): ?>
                                <a href="admin_reports.php" class="bg-gradient-to-r from-red-500 to-pink-600 text-white px-8 py-3 rounded-xl font-semibold hover:from-red-600 hover:to-pink-700 transition-all flex items-center gap-2">
                                    <i class="fas fa-times"></i>
                                    <span>Clear</span>
                                </a>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Report Statistics -->
            <div class="grid grid-cols-2 md:grid-cols-5 gap-4 md:gap-6 mb-8 md:mb-12 fade-in-up">
                <?php 
                $statCards = [
                    [
                        'icon' => 'fas fa-flag', 
                        'value' => $stats['total_reports'], 
                        'label' => 'Total Reports', 
                        'color' => 'from-blue-500 to-purple-600',
                        'trend' => 'positive',
                        'trend_text' => '+12% this month'
                    ],
                    [
                        'icon' => 'fas fa-clock', 
                        'value' => $stats['pending_reports'], 
                        'label' => 'Pending', 
                        'color' => 'from-yellow-500 to-orange-500',
                        'trend' => $stats['pending_reports'] > 5 ? 'warning' : 'positive',
                        'trend_text' => $stats['pending_reports'] > 5 ? 'Needs attention' : 'Under control'
                    ],
                    [
                        'icon' => 'fas fa-spinner', 
                        'value' => $stats['in_progress_reports'], 
                        'label' => 'In Progress', 
                        'color' => 'from-indigo-500 to-purple-600',
                        'trend' => 'positive',
                        'trend_text' => 'Active'
                    ],
                    [
                        'icon' => 'fas fa-check-circle', 
                        'value' => $stats['completed_reports'], 
                        'label' => 'Completed', 
                        'color' => 'from-green-500 to-emerald-600',
                        'trend' => 'positive',
                        'trend_text' => '85% success rate'
                    ],
                    [
                        'icon' => 'fas fa-times-circle', 
                        'value' => $stats['rejected_reports'], 
                        'label' => 'Rejected', 
                        'color' => 'from-red-500 to-pink-600',
                        'trend' => 'negative',
                        'trend_text' => '2% rejection rate'
                    ],
                ];
                
                foreach ($statCards as $card): 
                    $trendColor = $card['trend'] == 'positive' ? 'green' : ($card['trend'] == 'warning' ? 'yellow' : 'red');
                ?>
                <div class="glass-card rounded-2xl p-6 hover-lift">
                    <div class="flex items-center justify-between mb-4">
                        <div class="w-12 h-12 rounded-xl bg-gradient-to-r <?php echo $card['color']; ?> flex items-center justify-center">
                            <i class="<?php echo $card['icon']; ?> text-white text-xl"></i>
                        </div>
                        <div class="text-right">
                            <span class="text-white text-2xl font-bold"><?php echo $card['value']; ?></span>
                            <div class="flex items-center gap-1 mt-1">
                                <i class="fas fa-<?php echo $card['trend'] == 'warning' ? 'exclamation-triangle' : 'arrow-' . $card['trend']; ?> text-<?php echo $trendColor; ?>-400 text-xs"></i>
                                <span class="text-<?php echo $trendColor; ?>-400 text-sm"><?php echo $card['trend_text']; ?></span>
                            </div>
                        </div>
                    </div>
                    <div class="text-gray-300 text-sm"><?php echo $card['label']; ?></div>
                </div>
                <?php endforeach; ?>
            </div>

            <!-- Reports Table -->
            <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                <div class="flex flex-col md:flex-row md:items-center justify-between mb-6 gap-4">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-list mr-3 text-blue-400"></i>
                        All Reports
                        <span class="ml-3 text-gray-400 text-sm">(<?php echo $total_rows; ?> reports found)</span>
                    </h2>
                    
                    <div class="flex items-center gap-3">
                        <a href="admin_export.php?export=reports" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transition-all flex items-center gap-2">
                            <i class="fas fa-download"></i>
                            <span>Export Data</span>
                        </a>
                        <a href="view_report.php" class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transition-all flex items-center gap-2">
                            <i class="fas fa-plus"></i>
                            <span>View Report</span>
                        </a>
                    </div>
                </div>
                
                <!-- Reports Table -->
                <div class="table-container">
                    <table class="table w-full">
                        <thead>
                            <tr>
                                <th>Report ID</th>
                                <th>Title</th>
                                <th>Customer</th>
                                <th>Type</th>
                                <th>Status</th>
                                <th>Priority</th>
                                <th>Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php mysqli_data_seek($reports, 0); ?>
                            <?php while($report = mysqli_fetch_assoc($reports)): ?>
                            <tr class="hover:bg-white/5 transition-colors">
                                <td class="font-mono text-gray-300">#<?php echo $report['report_id']; ?></td>
                                <td>
                                    <div class="text-white font-medium"><?php echo htmlspecialchars($report['title']); ?></div>
                                </td>
                                <td class="text-gray-300"><?php echo htmlspecialchars($report['customer_name']); ?></td>
                                <td>
                                    <span class="text-gray-300">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="status-badge status-<?php echo $report['status']; ?>" 
                                          onclick="openStatusModal(<?php echo $report['report_id']; ?>, '<?php echo $report['status']; ?>')">
                                        <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                    </span>
                                </td>
                                <td>
                                    <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                        <?php echo ucfirst($report['priority']); ?>
                                    </span>
                                </td>
                                <td class="text-gray-300">
                                    <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                </td>
                                <td>
                                    <div class="flex gap-2">
                                        <a href="view_report.php?id=<?php echo $report['report_id']; ?>" 
                                           class="btn-action btn-view" title="View">
                                            <i class="fas fa-eye"></i>
                                        </a>

                                        <button onclick="confirmDelete(<?php echo $report['report_id']; ?>)" 
                                                class="btn-action btn-delete" title="Delete">
                                            <i class="fas fa-trash"></i>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endwhile; ?>
                        </tbody>
                    </table>
                    
                    <?php if(mysqli_num_rows($reports) == 0): ?>
                    <div class="text-center py-12">
                        <div class="text-gray-400 text-lg mb-4">
                            <i class="fas fa-flag text-4xl mb-4"></i>
                            <p>No reports found</p>
                        </div>
                        <?php if($search): ?>
                            <p class="text-gray-400">Try a different search term</p>
                        <?php else: ?>
                            <p class="text-gray-400">No reports match the selected filters</p>
                        <?php endif; ?>
                    </div>
                    <?php endif; ?>
                </div>
                
                <!-- Pagination -->
                <?php if($total_pages > 1): ?>
                <div class="flex justify-center gap-2 mt-8">
                    <?php if($page > 1): ?>
                        <a href="?page=1<?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" class="pagination-link">
                            <i class="fas fa-angle-double-left"></i>
                        </a>
                        <a href="?page=<?php echo $page - 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" class="pagination-link">
                            <i class="fas fa-angle-left"></i>
                        </a>
                    <?php endif; ?>
                    
                    <?php 
                    $start_page = max(1, $page - 2);
                    $end_page = min($total_pages, $page + 2);
                    
                    for($i = $start_page; $i <= $end_page; $i++): ?>
                        <a href="?page=<?php echo $i; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" 
                           class="pagination-link <?php echo $i == $page ? 'active' : ''; ?>">
                            <?php echo $i; ?>
                        </a>
                    <?php endfor; ?>
                    
                    <?php if($page < $total_pages): ?>
                        <a href="?page=<?php echo $page + 1; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" class="pagination-link">
                            <i class="fas fa-angle-right"></i>
                        </a>
                        <a href="?page=<?php echo $total_pages; ?><?php echo $search ? '&search=' . urlencode($search) : ''; ?><?php echo isset($_GET['status']) ? '&status=' . $_GET['status'] : ''; ?>" class="pagination-link">
                            <i class="fas fa-angle-double-right"></i>
                        </a>
                    <?php endif; ?>
                </div>
                <?php endif; ?>
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
                            Comprehensive reports management system for waste collection and disposal tracking.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Report Management</h4>
                            <ul class="space-y-2">
                                <li><a href="admin_reports.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">All Reports</a></li>
                                <li><a href="admin_reports.php?status=pending" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Pending Reports</a></li>
                                <li><a href="admin_reports.php?status=in_progress" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">In Progress</a></li>
                                <li><a href="add_report.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Create Report</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">Report Stats</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-flag mr-2 text-sm mt-1 text-blue-400"></i>
                                    <?php echo $stats['total_reports']; ?> Total Reports
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-clock mr-2 text-sm mt-1 text-yellow-400"></i>
                                    <?php echo $stats['pending_reports']; ?> Pending
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-check-circle mr-2 text-sm mt-1 text-green-400"></i>
                                    <?php echo $stats['completed_reports']; ?> Completed
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise Reports Management. Showing page <?php echo $page; ?> of <?php echo $total_pages; ?>.</p>
                    <p class="mt-2">Logged in as: <span class="text-green-400"><?php echo $_SESSION['user_name']; ?></span> (Administrator)</p>
                </div>
            </div>
        </footer>
    </main>

    <!-- Status Update Modal -->
    <div id="statusModal" class="modal">
        <div class="modal-content">
            <div class="flex justify-between items-center mb-6">
                <h2 class="text-white text-xl font-bold">Update Report Status</h2>
                <button onclick="closeModal()" class="text-gray-400 hover:text-white text-2xl">
                    <i class="fas fa-times"></i>
                </button>
            </div>
            <form method="POST" action="">
                <input type="hidden" name="report_id" id="modalReportId">
                <div class="mb-6">
                    <label class="text-white font-medium mb-3 block">Select Status</label>
                    <select name="status" id="modalStatus" class="form-input" required>
                        <option value="pending">Pending</option>
                        <option value="in_progress">In Progress</option>
                        <option value="completed">Completed</option>
                        <option value="rejected">Rejected</option>
                    </select>
                </div>
                <button type="submit" name="update_status" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white w-full py-4 rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transition-all">
                    Update Status
                </button>
            </form>
        </div>
    </div>

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
        
        // Status Modal Functions
        function openStatusModal(reportId, currentStatus) {
            const modal = document.getElementById('statusModal');
            modal.style.display = 'block';
            document.getElementById('modalReportId').value = reportId;
            document.getElementById('modalStatus').value = currentStatus;
        }

        function closeModal() {
            document.getElementById('statusModal').style.display = 'none';
        }

        function confirmDelete(reportId) {
            if (confirm('Are you sure you want to delete this report? This action cannot be undone.')) {
                window.location.href = '?delete=' + reportId;
            }
        }

        // Close modal when clicking outside
        window.onclick = function(event) {
            const modal = document.getElementById('statusModal');
            if (event.target == modal) {
                closeModal();
            }
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
        
        // Initialize
        document.addEventListener('DOMContentLoaded', function() {
            // Add active class to current nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage) || 
                    (currentPage === '' && href.includes('reports'))) {
                    item.classList.add('active');
                }
            });
        });
    </script>
</body>
</html>