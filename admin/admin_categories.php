<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('index.php');
}

$conn = getDBConnection();

// Get waste categories
$query = "SELECT * FROM waste_categories ORDER BY category_name";
$categories = mysqli_query($conn, $query);

// Handle category actions
if (isset($_GET['action'])) {
    $category_id = (int)$_GET['id'];
    
    switch ($_GET['action']) {
        case 'delete':
            // Check if category is in use
            $check_query = "SELECT COUNT(*) as count FROM report_categories WHERE category_id = $category_id";
            $check_result = mysqli_query($conn, $check_query);
            $in_use = mysqli_fetch_assoc($check_result)['count'];
            
            if ($in_use > 0) {
                $_SESSION['error'] = "Cannot delete category. It is being used in " . $in_use . " reports.";
            } else {
                $delete_query = "DELETE FROM waste_categories WHERE category_id = $category_id";
                if (mysqli_query($conn, $delete_query)) {
                    $_SESSION['success'] = "Category deleted successfully!";
                } else {
                    $_SESSION['error'] = "Error deleting category!";
                }
            }
            break;
    }
    
    redirect('admin_categories.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_category'])) {
        $category_name = sanitize($_POST['category_name']);
        $description = sanitize($_POST['description']);
        $icon_class = sanitize($_POST['icon_class']);
        
        $insert_query = "INSERT INTO waste_categories (category_name, description, icon_class) 
                        VALUES ('$category_name', '$description', '$icon_class')";
        
        if (mysqli_query($conn, $insert_query)) {
            $_SESSION['success'] = "Category added successfully!";
            redirect('admin_categories.php');
        } else {
            $_SESSION['error'] = "Error adding category: " . mysqli_error($conn);
        }
    }
    
    if (isset($_POST['update_category'])) {
        $category_id = (int)$_POST['category_id'];
        $category_name = sanitize($_POST['category_name']);
        $description = sanitize($_POST['description']);
        $icon_class = sanitize($_POST['icon_class']);
        
        $update_query = "UPDATE waste_categories SET 
                        category_name = '$category_name',
                        description = '$description',
                        icon_class = '$icon_class'
                        WHERE category_id = $category_id";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['success'] = "Category updated successfully!";
            redirect('admin_categories.php');
        } else {
            $_SESSION['error'] = "Error updating category: " . mysqli_error($conn);
        }
    }
}

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
    <title>Waste Categories - WasteWise</title>
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
        }
        
        .status-pending { background: rgba(254, 243, 199, 0.2); color: #fef3c7; border: 1px solid rgba(254, 243, 199, 0.3); }
        .status-in_progress { background: rgba(219, 234, 254, 0.2); color: #dbeafe; border: 1px solid rgba(219, 234, 254, 0.3); }
        .status-completed { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .status-rejected { background: rgba(254, 226, 226, 0.2); color: #fee2e2; border: 1px solid rgba(254, 226, 226, 0.3); }
        
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
        
        /* Categories specific styles */
        .category-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            border-left: 4px solid #4ade80;
        }
        
        .category-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .icon-preview {
            width: 80px;
            height: 80px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 32px;
            color: #4ade80;
            border: 2px dashed rgba(255, 255, 255, 0.2);
        }
        
        .icon-option {
            padding: 12px;
            text-align: center;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .icon-option:hover {
            border-color: #4ade80;
            background: rgba(74, 222, 128, 0.1);
            transform: translateY(-2px);
        }
        
        .icon-option.selected {
            border-color: #4ade80;
            background: rgba(74, 222, 128, 0.2);
            box-shadow: 0 5px 15px rgba(74, 222, 128, 0.1);
        }
        
        .icon-item {
            text-align: center;
            padding: 16px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .icon-item:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: #4ade80;
            transform: translateY(-3px);
        }
        
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
        
        textarea.form-input {
            min-height: 120px;
            resize: vertical;
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
            <a href="admin_categories.php" class="flex items-center space-x-3 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-white rounded-xl border border-purple-500/30">
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
                
                <a href="admin_categories.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
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
                <h1 class="text-white text-2xl font-bold">Waste Categories Management</h1>
                <p class="text-gray-400 text-sm">Define and manage different types of waste for reporting</p>
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
                    Waste Categories
                </h1>
                
                <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                    Manage and categorize different types of waste for better reporting and analysis.
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
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8 mb-8 md:mb-12">
                <!-- Existing Categories -->
                <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                            <i class="fas fa-list-alt mr-3 text-blue-400"></i>
                            Existing Categories
                        </h2>
                        <span class="text-white text-sm">
                            <?php 
                            mysqli_data_seek($categories, 0);
                            $category_count = mysqli_num_rows($categories);
                            echo $category_count . ' categories';
                            ?>
                        </span>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 max-h-[600px] overflow-y-auto p-2">
                        <?php mysqli_data_seek($categories, 0); ?>
                        <?php while($category = mysqli_fetch_assoc($categories)): 
                            // Get usage count
                            $usage_query = "SELECT COUNT(*) as count FROM report_categories WHERE category_id = {$category['category_id']}";
                            $usage_result = mysqli_query($conn, $usage_query);
                            $usage_count = mysqli_fetch_assoc($usage_result)['count'];
                        ?>
                        <div class="category-card hover-lift">
                            <div class="flex items-center gap-4 mb-4">
                                <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-red-500 to-orange-500 flex items-center justify-center">
                                    <i class="<?php echo $category['icon_class']; ?> text-white text-lg"></i>
                                </div>
                                <div>
                                    <div class="text-white font-bold text-lg"><?php echo htmlspecialchars($category['category_name']); ?></div>
                                    <div class="text-gray-400 text-sm">Used in <?php echo $usage_count; ?> reports</div>
                                </div>
                            </div>
                            
                            <div class="text-gray-300 text-sm mb-4 line-clamp-2">
                                <?php echo htmlspecialchars($category['description']); ?>
                            </div>
                            
                            <div class="flex gap-2">
                                <button type="button" onclick="editCategory(<?php echo $category['category_id']; ?>, '<?php echo addslashes($category['category_name']); ?>', '<?php echo addslashes($category['description']); ?>', '<?php echo addslashes($category['icon_class']); ?>')" 
                                        class="btn-action btn-edit flex-1">
                                    <i class="fas fa-edit mr-2"></i> Edit
                                </button>
                                <a href="?action=delete&id=<?php echo $category['category_id']; ?>" 
                                   class="btn-action btn-delete"
                                   onclick="return confirm('Are you sure you want to delete this category?')">
                                    <i class="fas fa-trash"></i>
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Category Form -->
                <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                            <i class="fas fa-plus-circle mr-3 text-green-400"></i>
                            <span id="formTitle">Add New Category</span>
                        </h2>
                        <button type="button" id="cancelBtn" class="text-red-400 hover:text-red-300 text-sm font-semibold hidden" onclick="resetForm()">
                            <i class="fas fa-times mr-1"></i> Cancel
                        </button>
                    </div>
                    
                    <form method="POST" action="" id="categoryForm">
                        <input type="hidden" name="category_id" id="category_id" value="">
                        
                        <div class="space-y-6">
                            <div>
                                <label class="text-white font-medium mb-2 block">Category Name *</label>
                                <input type="text" name="category_name" id="category_name" class="form-input" placeholder="Enter category name" required>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Description</label>
                                <textarea name="description" id="description" class="form-input" placeholder="Enter category description"></textarea>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-4 block">Select Icon</label>
                                <div class="flex flex-col items-center mb-6">
                                    <div class="icon-preview mb-4">
                                        <i class="fas fa-trash" id="iconPreview"></i>
                                    </div>
                                    <input type="hidden" name="icon_class" id="icon_class" value="fas fa-trash" required>
                                    <div class="text-gray-400 text-sm">Selected Icon</div>
                                </div>
                                
                                <div class="grid grid-cols-4 gap-3 mb-6">
                                    <?php
                                    $default_icons = [
                                        ['fas fa-trash', 'Trash'],
                                        ['fas fa-recycle', 'Recycle'],
                                        ['fas fa-leaf', 'Leaf'],
                                        ['fas fa-radiation', 'Hazard'],
                                        ['fas fa-home', 'Home'],
                                        ['fas fa-couch', 'Furniture'],
                                        ['fas fa-hard-hat', 'Construction'],
                                        ['fas fa-battery-full', 'Battery']
                                    ];
                                    
                                    foreach ($default_icons as $icon): ?>
                                    <div class="icon-option" onclick="selectIcon('<?php echo $icon[0]; ?>')">
                                        <i class="<?php echo $icon[0]; ?> text-lg mb-2 text-blue-400"></i>
                                        <span class="text-gray-300 text-xs"><?php echo $icon[1]; ?></span>
                                    </div>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                            
                            <div class="flex gap-4">
                                <button type="submit" name="add_category" id="submitBtn" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-8 py-4 rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transition-all flex-1 flex items-center justify-center gap-2">
                                    <i class="fas fa-plus"></i>
                                    <span>Add Category</span>
                                </button>
                            </div>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Available Icons -->
            <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-icons mr-3 text-purple-400"></i>
                        Available Icons
                    </h2>
                    <span class="text-gray-400 text-sm">Click any icon to select it</span>
                </div>
                
                <div class="grid grid-cols-3 sm:grid-cols-4 md:grid-cols-6 lg:grid-cols-8 gap-4 p-2">
                    <?php
                    $common_icons = [
                        'fa-trash', 'fa-recycle', 'fa-leaf', 'fa-radiation', 'fa-home', 'fa-couch',
                        'fa-hard-hat', 'fa-battery-full', 'fa-bolt', 'fa-flask', 'fa-medkit',
                        'fa-shield-alt', 'fa-exclamation-triangle', 'fa-skull-crossbones',
                        'fa-fire', 'fa-smog', 'fa-water', 'fa-wind', 'fa-sun', 'fa-cloud',
                        'fa-box', 'fa-pallet', 'fa-dumpster', 'fa-truck-loading', 'fa-conveyor-belt',
                        'fa-compress-alt', 'fa-expand-alt', 'fa-weight-hanging', 'fa-balance-scale',
                        'fa-wine-bottle', 'fa-paper', 'fa-plug', 'fa-microchip', 'fa-car-battery',
                        'fa-prescription-bottle', 'fa-pills', 'fa-thermometer', 'fa-band-aid'
                    ];
                    
                    foreach ($common_icons as $icon): ?>
                    <div class="icon-item hover-lift" onclick="selectIcon('fas <?php echo $icon; ?>')">
                        <i class="fas <?php echo $icon; ?> text-lg text-blue-400 mb-2"></i>
                        <span class="text-gray-300 text-xs"><?php echo str_replace('fa-', '', $icon); ?></span>
                    </div>
                    <?php endforeach; ?>
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
                            Manage waste categories for better classification and reporting.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Category Management</h4>
                            <ul class="space-y-2">
                                <li><a href="admin_dashboard.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Dashboard</a></li>
                                <li><a href="admin_categories.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Categories</a></li>
                                <li><a href="admin_reports.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Reports</a></li>
                                <li><a href="admin_analytics.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Analytics</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">Quick Actions</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-plus-circle mr-2 text-sm mt-1 text-green-400"></i> Add New Category
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-edit mr-2 text-sm mt-1 text-blue-400"></i> Edit Existing Categories
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-trash-alt mr-2 text-sm mt-1 text-red-400"></i> Delete Unused Categories
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise Category Management. <?php echo $category_count; ?> categories defined.</p>
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
        
        // Category Form Functions
        function selectIcon(iconClass) {
            document.getElementById('icon_class').value = iconClass;
            document.getElementById('iconPreview').className = iconClass;
            
            // Update selected state
            document.querySelectorAll('.icon-option').forEach(function(option) {
                option.classList.remove('selected');
                if (option.getAttribute('onclick').includes(iconClass.replace(/"/g, '&quot;'))) {
                    option.classList.add('selected');
                }
            });
        }
        
        function editCategory(categoryId, categoryName, description, iconClass) {
            document.getElementById('formTitle').textContent = 'Edit Category';
            document.getElementById('category_id').value = categoryId;
            document.getElementById('category_name').value = categoryName;
            document.getElementById('description').value = description;
            document.getElementById('submitBtn').name = 'update_category';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save"></i><span>Update Category</span>';
            document.getElementById('cancelBtn').classList.remove('hidden');
            document.getElementById('category_name').focus();
            
            selectIcon(iconClass);
        }
        
        function resetForm() {
            document.getElementById('formTitle').textContent = 'Add New Category';
            document.getElementById('categoryForm').reset();
            document.getElementById('category_id').value = '';
            document.getElementById('submitBtn').name = 'add_category';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-plus"></i><span>Add Category</span>';
            document.getElementById('cancelBtn').classList.add('hidden');
            selectIcon('fas fa-trash');
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
            selectIcon('fas fa-trash');
            
            // Add active class to current nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage) || 
                    (currentPage === '' && href.includes('categories'))) {
                    item.classList.add('active');
                }
            });
            
            // Form validation
            const form = document.getElementById('categoryForm');
            form.addEventListener('submit', function(e) {
                const categoryName = document.getElementById('category_name').value.trim();
                if (!categoryName) {
                    e.preventDefault();
                    alert('Please enter a category name');
                    document.getElementById('category_name').focus();
                }
            });
        });
    </script>
</body>
</html>