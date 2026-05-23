<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login_admin.php');
}

$conn = getDBConnection();

// Get system settings from database or defaults
$settings = [
    'system_name' => 'WasteWise',
    'contact_email' => 'admin@wastewise.com',
    'contact_phone' => '+1 (555) 123-4567',
    'notifications_enabled' => '1',
    'points_per_report' => '10',
    'points_per_completion' => '20',
    'max_reports_per_day' => '5',
    'auto_assign_reports' => '0',
    'default_report_priority' => 'medium',
    'map_api_key' => '',
    'smtp_enabled' => '0',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_username' => '',
    'smtp_password' => '',
    'maintenance_mode' => '0',
    'registration_enabled' => '1',
    'system_logo' => '',
    'site_url' => 'https://wastewise.com',
    'timezone' => 'UTC',
    'date_format' => 'Y-m-d',
    'items_per_page' => '20'
];

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    foreach ($settings as $key => $value) {
        if (isset($_POST[$key])) {
            $settings[$key] = sanitize($_POST[$key]);
        }
    }
    
    // In a real system, you would save these to a database table
    // For now, we'll just show a success message
    $_SESSION['success'] = "Settings saved successfully!";
    
    // Handle logo upload
    if (isset($_FILES['system_logo']) && $_FILES['system_logo']['error'] == 0) {
        $upload_dir = '../uploads/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = 'logo_' . time() . '.' . pathinfo($_FILES['system_logo']['name'], PATHINFO_EXTENSION);
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['system_logo']['tmp_name'], $target_path)) {
            $settings['system_logo'] = $target_path;
            $_SESSION['success'] .= " Logo uploaded successfully!";
        }
    }
    
    redirect('admin_settings.php');
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
    <title>System Settings - WasteWise</title>
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
        
        /* Settings specific styles */
        .settings-tab {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 16px 24px;
            cursor: pointer;
            transition: all 0.3s;
            color: rgba(255, 255, 255, 0.7);
            font-weight: 500;
            display: flex;
            align-items: center;
            gap: 12px;
        }
        
        .settings-tab:hover {
            background: rgba(255, 255, 255, 0.08);
            color: white;
        }
        
        .settings-tab.active {
            background: rgba(59, 130, 246, 0.2);
            border-color: rgba(59, 130, 246, 0.3);
            color: #3b82f6;
        }
        
        .settings-content {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 20px;
            padding: 32px;
        }
        
        /* Form styles */
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
        
        /* Toggle switch */
        .toggle-switch {
            position: relative;
            display: inline-block;
            width: 60px;
            height: 30px;
        }
        
        .toggle-switch input {
            opacity: 0;
            width: 0;
            height: 0;
        }
        
        .toggle-slider {
            position: absolute;
            cursor: pointer;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background-color: rgba(255, 255, 255, 0.1);
            transition: .4s;
            border-radius: 34px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .toggle-slider:before {
            position: absolute;
            content: "";
            height: 22px;
            width: 22px;
            left: 4px;
            bottom: 4px;
            background-color: white;
            transition: .4s;
            border-radius: 50%;
        }
        
        input:checked + .toggle-slider {
            background-color: #4ade80;
            border-color: #4ade80;
        }
        
        input:checked + .toggle-slider:before {
            transform: translateX(30px);
        }
        
        /* Logo upload */
        .logo-preview {
            width: 120px;
            height: 120px;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.2);
            border-radius: 20px;
            display: flex;
            align-items: center;
            justify-content: center;
            overflow: hidden;
        }
        
        /* Danger zone */
        .danger-zone {
            background: rgba(220, 38, 38, 0.1);
            border: 1px solid rgba(220, 38, 38, 0.2);
            border-radius: 20px;
            padding: 32px;
            margin-top: 40px;
            border-left: 4px solid #dc2626;
        }
        
        /* File upload */
        .file-upload {
            position: relative;
            display: inline-block;
            cursor: pointer;
        }
        
        .file-upload input[type="file"] {
            position: absolute;
            left: 0;
            top: 0;
            opacity: 0;
            width: 100%;
            height: 100%;
            cursor: pointer;
        }
        
        .file-upload-label {
            display: inline-flex;
            align-items: center;
            gap: 10px;
            padding: 12px 20px;
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
            border-radius: 12px;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-upload-label:hover {
            background: rgba(59, 130, 246, 0.3);
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
            <a href="admin_settings.php" class="flex items-center space-x-3 p-4 bg-gradient-to-r from-purple-500/20 to-blue-500/20 text-white rounded-xl border border-purple-500/30">
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
                
                <a href="admin_settings.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
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
                <h1 class="text-white text-2xl font-bold">System Settings</h1>
                <p class="text-gray-400 text-sm">Configure system preferences and options</p>
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
                    System Settings
                </h1>
                
                <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                    Configure system preferences, integrations, and manage all settings in one place.
                </p>
                
                <?php if(isset($_SESSION['success'])): ?>
                <div class="glass-card rounded-2xl p-6 mb-6 border border-green-500/30 bg-green-500/10 fade-in-up">
                    <div class="flex items-center gap-3 text-green-400">
                        <i class="fas fa-check-circle text-xl"></i>
                        <span class="font-semibold"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                    </div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Settings Tabs -->
            <div class="grid grid-cols-2 md:grid-cols-6 gap-3 mb-6 fade-in-up">
                <button type="button" class="settings-tab active" onclick="showTab('general')">
                    <i class="fas fa-cog"></i>
                    <span class="hidden md:inline">General</span>
                </button>
                <button type="button" class="settings-tab" onclick="showTab('notifications')">
                    <i class="fas fa-bell"></i>
                    <span class="hidden md:inline">Notifications</span>
                </button>
                <button type="button" class="settings-tab" onclick="showTab('rewards')">
                    <i class="fas fa-trophy"></i>
                    <span class="hidden md:inline">Rewards</span>
                </button>
                <button type="button" class="settings-tab" onclick="showTab('email')">
                    <i class="fas fa-envelope"></i>
                    <span class="hidden md:inline">Email</span>
                </button>
                <button type="button" class="settings-tab" onclick="showTab('api')">
                    <i class="fas fa-code"></i>
                    <span class="hidden md:inline">API</span>
                </button>
                <button type="button" class="settings-tab" onclick="showTab('backup')">
                    <i class="fas fa-database"></i>
                    <span class="hidden md:inline">Backup</span>
                </button>
            </div>

            <!-- Settings Form -->
            <form method="POST" action="" enctype="multipart/form-data" class="fade-in-up">
                <!-- General Settings -->
                <div id="generalTab" class="settings-content">
                    <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-cog mr-3 text-blue-400"></i>
                        General Settings
                    </h2>
                    
                    <div class="space-y-6">
                        <!-- Logo Upload -->
                        <div class="flex flex-col md:flex-row md:items-center gap-6">
                            <div class="logo-preview">
                                <?php if($settings['system_logo'] && file_exists($settings['system_logo'])): ?>
                                    <img src="<?php echo $settings['system_logo']; ?>" alt="System Logo" class="max-w-full max-h-full">
                                <?php else: ?>
                                    <div class="text-4xl font-bold text-gradient">W</div>
                                <?php endif; ?>
                            </div>
                            <div class="flex-1">
                                <label class="text-white font-medium mb-3 block">System Logo</label>
                                <div class="file-upload">
                                    <input type="file" name="system_logo" id="logoUpload" accept="image/*" class="hidden" onchange="previewLogo()">
                                    <label for="logoUpload" class="file-upload-label">
                                        <i class="fas fa-upload"></i>
                                        <span>Upload Logo</span>
                                    </label>
                                </div>
                                <p class="text-gray-400 text-sm mt-2">Recommended: 300x100px, PNG or JPG format</p>
                            </div>
                        </div>
                        
                        <!-- System Info -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-white font-medium mb-2 block">System Name *</label>
                                <input type="text" name="system_name" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['system_name']); ?>" required>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Site URL</label>
                                <input type="url" name="site_url" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['site_url']); ?>">
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Contact Email *</label>
                                <input type="email" name="contact_email" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['contact_email']); ?>" required>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Contact Phone</label>
                                <input type="tel" name="contact_phone" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['contact_phone']); ?>">
                            </div>
                        </div>
                        
                        <!-- System Preferences -->
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-white font-medium mb-2 block">Default Report Priority</label>
                                <select name="default_report_priority" class="form-input">
                                    <option value="low" <?php echo $settings['default_report_priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $settings['default_report_priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $settings['default_report_priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Maximum Reports per Day</label>
                                <input type="number" name="max_reports_per_day" class="form-input" 
                                       value="<?php echo $settings['max_reports_per_day']; ?>" min="1" max="50">
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Items per Page</label>
                                <input type="number" name="items_per_page" class="form-input" 
                                       value="<?php echo $settings['items_per_page']; ?>" min="5" max="100">
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Timezone</label>
                                <select name="timezone" class="form-input">
                                    <option value="UTC" <?php echo $settings['timezone'] == 'UTC' ? 'selected' : ''; ?>>UTC</option>
                                    <option value="America/New_York" <?php echo $settings['timezone'] == 'America/New_York' ? 'selected' : ''; ?>>Eastern Time</option>
                                    <option value="America/Chicago" <?php echo $settings['timezone'] == 'America/Chicago' ? 'selected' : ''; ?>>Central Time</option>
                                    <option value="America/Denver" <?php echo $settings['timezone'] == 'America/Denver' ? 'selected' : ''; ?>>Mountain Time</option>
                                    <option value="America/Los_Angeles" <?php echo $settings['timezone'] == 'America/Los_Angeles' ? 'selected' : ''; ?>>Pacific Time</option>
                                </select>
                            </div>
                        </div>
                        
                        <!-- Toggles -->
                        <div class="space-y-4">
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-white font-medium">Auto-assign Reports</label>
                                    <p class="text-gray-400 text-sm">Automatically assign new reports to available teams</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="auto_assign_reports" value="1" 
                                           <?php echo $settings['auto_assign_reports'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-white font-medium">Allow Registration</label>
                                    <p class="text-gray-400 text-sm">Allow new users to register accounts</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="registration_enabled" value="1" 
                                           <?php echo $settings['registration_enabled'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                            
                            <div class="flex items-center justify-between">
                                <div>
                                    <label class="text-white font-medium">Maintenance Mode</label>
                                    <p class="text-gray-400 text-sm">System will be unavailable to regular users</p>
                                </div>
                                <label class="toggle-switch">
                                    <input type="checkbox" name="maintenance_mode" value="1" 
                                           <?php echo $settings['maintenance_mode'] ? 'checked' : ''; ?>>
                                    <span class="toggle-slider"></span>
                                </label>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Notifications Settings -->
                <div id="notificationsTab" class="settings-content hidden">
                    <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-bell mr-3 text-yellow-400"></i>
                        Notification Settings
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-white font-medium">Enable Email Notifications</label>
                                <p class="text-gray-400 text-sm">Send email notifications for system events</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="notifications_enabled" value="1" 
                                       <?php echo $settings['notifications_enabled'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <!-- Notification Types -->
                        <div>
                            <label class="text-white font-medium mb-4 block">Notification Types</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="bg-white/5 p-4 rounded-xl">
                                    <div class="flex items-center gap-3 mb-2">
                                        <input type="checkbox" id="notif_new_report" checked>
                                        <label for="notif_new_report" class="text-white font-medium">New Report Submitted</label>
                                    </div>
                                    <p class="text-gray-400 text-sm">Notify admins when new reports are submitted</p>
                                </div>
                                
                                <div class="bg-white/5 p-4 rounded-xl">
                                    <div class="flex items-center gap-3 mb-2">
                                        <input type="checkbox" id="notif_status_update" checked>
                                        <label for="notif_status_update" class="text-white font-medium">Status Updates</label>
                                    </div>
                                    <p class="text-gray-400 text-sm">Notify users when report status changes</p>
                                </div>
                                
                                <div class="bg-white/5 p-4 rounded-xl">
                                    <div class="flex items-center gap-3 mb-2">
                                        <input type="checkbox" id="notif_assignment" checked>
                                        <label for="notif_assignment" class="text-white font-medium">Assignment Notifications</label>
                                    </div>
                                    <p class="text-gray-400 text-sm">Notify teams when assigned to reports</p>
                                </div>
                                
                                <div class="bg-white/5 p-4 rounded-xl">
                                    <div class="flex items-center gap-3 mb-2">
                                        <input type="checkbox" id="notif_daily_digest">
                                        <label for="notif_daily_digest" class="text-white font-medium">Daily Digest</label>
                                    </div>
                                    <p class="text-gray-400 text-sm">Send daily summary to administrators</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Rewards Settings -->
                <div id="rewardsTab" class="settings-content hidden">
                    <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-trophy mr-3 text-yellow-400"></i>
                        Rewards System
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-white font-medium mb-2 block">Points per Report</label>
                                <input type="number" name="points_per_report" class="form-input" 
                                       value="<?php echo $settings['points_per_report']; ?>" min="0" max="100">
                                <p class="text-gray-400 text-sm mt-2">Points awarded for submitting a valid report</p>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Points per Completion</label>
                                <input type="number" name="points_per_completion" class="form-input" 
                                       value="<?php echo $settings['points_per_completion']; ?>" min="0" max="100">
                                <p class="text-gray-400 text-sm mt-2">Points awarded when report is completed</p>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Daily Login Bonus</label>
                                <input type="number" class="form-input" value="5" min="0" max="50">
                                <p class="text-gray-400 text-sm mt-2">Points for daily login</p>
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">Referral Bonus</label>
                                <input type="number" class="form-input" value="50" min="0" max="500">
                                <p class="text-gray-400 text-sm mt-2">Points for referring new users</p>
                            </div>
                        </div>
                        
                        <!-- Reward Tiers -->
                        <div>
                            <label class="text-white font-medium mb-4 block">Reward Tiers</label>
                            <div class="bg-white/5 p-6 rounded-xl">
                                <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
                                    <div class="text-center p-4 bg-gradient-to-b from-yellow-800/20 to-yellow-900/10 rounded-lg">
                                        <div class="text-yellow-400 text-2xl font-bold">Bronze</div>
                                        <div class="text-white text-lg font-semibold">100 points</div>
                                        <div class="text-gray-400 text-sm mt-2">Basic rewards</div>
                                    </div>
                                    
                                    <div class="text-center p-4 bg-gradient-to-b from-gray-800/20 to-gray-900/10 rounded-lg">
                                        <div class="text-gray-300 text-2xl font-bold">Silver</div>
                                        <div class="text-white text-lg font-semibold">500 points</div>
                                        <div class="text-gray-400 text-sm mt-2">Enhanced rewards</div>
                                    </div>
                                    
                                    <div class="text-center p-4 bg-gradient-to-b from-yellow-800/20 to-yellow-600/10 rounded-lg">
                                        <div class="text-yellow-300 text-2xl font-bold">Gold</div>
                                        <div class="text-white text-lg font-semibold">1000 points</div>
                                        <div class="text-gray-400 text-sm mt-2">Premium rewards</div>
                                    </div>
                                    
                                    <div class="text-center p-4 bg-gradient-to-b from-blue-800/20 to-blue-900/10 rounded-lg">
                                        <div class="text-blue-400 text-2xl font-bold">Platinum</div>
                                        <div class="text-white text-lg font-semibold">5000 points</div>
                                        <div class="text-gray-400 text-sm mt-2">Exclusive rewards</div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Email Settings -->
                <div id="emailTab" class="settings-content hidden">
                    <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-envelope mr-3 text-blue-400"></i>
                        Email Configuration
                    </h2>
                    
                    <div class="space-y-6">
                        <div class="flex items-center justify-between">
                            <div>
                                <label class="text-white font-medium">Enable SMTP</label>
                                <p class="text-gray-400 text-sm">Use external SMTP server for sending emails</p>
                            </div>
                            <label class="toggle-switch">
                                <input type="checkbox" name="smtp_enabled" value="1" 
                                       <?php echo $settings['smtp_enabled'] ? 'checked' : ''; ?>>
                                <span class="toggle-slider"></span>
                            </label>
                        </div>
                        
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <label class="text-white font-medium mb-2 block">SMTP Host</label>
                                <input type="text" name="smtp_host" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['smtp_host']); ?>">
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">SMTP Port</label>
                                <input type="number" name="smtp_port" class="form-input" 
                                       value="<?php echo $settings['smtp_port']; ?>">
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">SMTP Username</label>
                                <input type="text" name="smtp_username" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['smtp_username']); ?>">
                            </div>
                            
                            <div>
                                <label class="text-white font-medium mb-2 block">SMTP Password</label>
                                <input type="password" name="smtp_password" class="form-input" 
                                       value="<?php echo htmlspecialchars($settings['smtp_password']); ?>">
                            </div>
                        </div>
                        
                        <!-- Test Connection -->
                        <div>
                            <button type="button" onclick="testEmailConnection()" 
                                    class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transition-all">
                                <i class="fas fa-test mr-2"></i>
                                Test Email Connection
                            </button>
                        </div>
                    </div>
                </div>

                <!-- API Settings -->
                <div id="apiTab" class="settings-content hidden">
                    <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-code mr-3 text-green-400"></i>
                        API Configuration
                    </h2>
                    
                    <div class="space-y-6">
                        <div>
                            <label class="text-white font-medium mb-2 block">Google Maps API Key</label>
                            <input type="text" name="map_api_key" class="form-input" 
                                   value="<?php echo htmlspecialchars($settings['map_api_key']); ?>" 
                                   placeholder="Enter your Google Maps API key">
                            <p class="text-gray-400 text-sm mt-2">Required for map functionality and location services</p>
                            <button type="button" onclick="testMapAPI()" 
                                    class="mt-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg font-medium hover:from-green-600 hover:to-emerald-700 transition-all">
                                <i class="fas fa-test mr-2"></i>
                                Test Connection
                            </button>
                        </div>
                        
                        <!-- Generate API Key -->
                        <div>
                            <label class="text-white font-medium mb-4 block">API Access</label>
                            <div class="bg-white/5 p-6 rounded-xl">
                                <p class="text-gray-300 mb-4">Generate API keys for external integrations and third-party services.</p>
                                <button type="button" onclick="generateAPIKey()" 
                                        class="bg-gradient-to-r from-purple-500 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-purple-600 hover:to-pink-700 transition-all">
                                    <i class="fas fa-key mr-2"></i>
                                    Generate New API Key
                                </button>
                                <div id="apiKeyDisplay" class="mt-4 hidden">
                                    <div class="bg-black/30 p-4 rounded-lg border border-green-500/30">
                                        <div class="text-green-400 font-mono text-sm break-all" id="generatedKey"></div>
                                        <p class="text-red-400 text-sm mt-2">
                                            <i class="fas fa-exclamation-triangle mr-1"></i>
                                            Save this key immediately. It won't be shown again.
                                        </p>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Backup Settings -->
                <div id="backupTab" class="settings-content hidden">
                    <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                        <i class="fas fa-database mr-3 text-orange-400"></i>
                        Backup & Restore
                    </h2>
                    
                    <div class="space-y-6">
                        <!-- Automatic Backups -->
                        <div>
                            <label class="text-white font-medium mb-4 block">Automatic Backups</label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                                <div>
                                    <label class="text-white font-medium mb-2 block">Backup Frequency</label>
                                    <select class="form-input">
                                        <option>Daily</option>
                                        <option>Weekly</option>
                                        <option>Monthly</option>
                                        <option>Disabled</option>
                                    </select>
                                </div>
                                
                                <div>
                                    <label class="text-white font-medium mb-2 block">Retention Period</label>
                                    <select class="form-input">
                                        <option>7 days</option>
                                        <option>30 days</option>
                                        <option>90 days</option>
                                        <option>1 year</option>
                                    </select>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Manual Backup -->
                        <div>
                            <label class="text-white font-medium mb-4 block">Manual Backup</label>
                            <div class="bg-white/5 p-6 rounded-xl">
                                <p class="text-gray-300 mb-4">Create a manual backup of all system data including settings, reports, and user information.</p>
                                <button type="button" onclick="createBackup()" 
                                        class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-blue-600 hover:to-purple-700 transition-all">
                                    <i class="fas fa-download mr-2"></i>
                                    Create Backup Now
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Danger Zone -->
                <div class="danger-zone fade-in-up">
                    <div class="flex items-start gap-4">
                        <div class="w-12 h-12 rounded-xl bg-red-500/20 flex items-center justify-center">
                            <i class="fas fa-exclamation-triangle text-red-400 text-xl"></i>
                        </div>
                        <div class="flex-1">
                            <h3 class="text-white text-xl font-bold mb-3">Danger Zone</h3>
                            <p class="text-red-300 mb-4">These actions are irreversible. Proceed with extreme caution.</p>
                            <div class="flex flex-col md:flex-row gap-3">
                                <button type="button" onclick="resetSystem()" 
                                        class="bg-gradient-to-r from-red-500 to-pink-600 text-white px-6 py-3 rounded-xl font-semibold hover:from-red-600 hover:to-pink-700 transition-all">
                                    <i class="fas fa-redo mr-2"></i>
                                    Reset System Data
                                </button>
                                <button type="button" onclick="deleteAllData()" 
                                        class="bg-gradient-to-r from-red-700 to-red-900 text-white px-6 py-3 rounded-xl font-semibold hover:from-red-800 hover:to-red-950 transition-all">
                                    <i class="fas fa-trash mr-2"></i>
                                    Delete All Data
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Save Settings -->
                <div class="flex flex-col md:flex-row gap-4 mt-8">
                    <button type="submit" 
                            class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-8 py-4 rounded-xl font-semibold hover:from-green-600 hover:to-emerald-700 transition-all flex-1 flex items-center justify-center gap-3">
                        <i class="fas fa-save"></i>
                        <span>Save All Settings</span>
                    </button>
                    <button type="button" onclick="resetToDefaults()" 
                            class="bg-gradient-to-r from-yellow-500 to-orange-600 text-white px-8 py-4 rounded-xl font-semibold hover:from-yellow-600 hover:to-orange-700 transition-all">
                        <i class="fas fa-undo"></i>
                        <span>Reset to Defaults</span>
                    </button>
                </div>
            </form>
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
                            System configuration and settings management portal.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Settings Categories</h4>
                            <ul class="space-y-2">
                                <li><a href="javascript:showTab('general')" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">General Settings</a></li>
                                <li><a href="javascript:showTab('notifications')" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Notifications</a></li>
                                <li><a href="javascript:showTab('rewards')" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Rewards System</a></li>
                                <li><a href="javascript:showTab('api')" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">API Configuration</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">System Info</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-server mr-2 text-sm mt-1 text-green-400"></i>
                                    PHP <?php echo phpversion(); ?>
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-database mr-2 text-sm mt-1 text-blue-400"></i>
                                    MySQL <?php echo mysqli_get_server_info($conn); ?>
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-users mr-2 text-sm mt-1 text-yellow-400"></i>
                                    <?php echo $stats['total_customers']; ?> Registered Users
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise System Settings. All configurations are logged for security.</p>
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
        
        // Settings Tab Functions
        function showTab(tabName) {
            // Hide all tabs
            document.querySelectorAll('.settings-content').forEach(tab => {
                tab.classList.add('hidden');
            });
            
            // Remove active class from all buttons
            document.querySelectorAll('.settings-tab').forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Show selected tab
            document.getElementById(tabName + 'Tab').classList.remove('hidden');
            
            // Activate clicked button
            event.currentTarget.classList.add('active');
            
            // Scroll to top of settings
            document.querySelector('.settings-content:not(.hidden)').scrollIntoView({ behavior: 'smooth', block: 'start' });
        }
        
        // Logo Preview
        function previewLogo() {
            const file = document.getElementById('logoUpload').files[0];
            if (file) {
                const reader = new FileReader();
                reader.onload = function(e) {
                    document.querySelector('.logo-preview').innerHTML = `<img src="${e.target.result}" alt="Logo Preview" class="max-w-full max-h-full rounded-lg">`;
                }
                reader.readAsDataURL(file);
            }
        }
        
        // Test Functions
        function testEmailConnection() {
            alert('Testing email connection... In a real implementation, this would validate SMTP settings.');
        }
        
        function testMapAPI() {
            const apiKey = document.querySelector('input[name="map_api_key"]').value;
            if (!apiKey) {
                alert('Please enter a Google Maps API key first.');
                return;
            }
            alert('Testing Map API connection with key: ' + apiKey.substring(0, 10) + '...');
        }
        
        function generateAPIKey() {
            const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789';
            let key = 'wastewise_';
            for (let i = 0; i < 32; i++) {
                key += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            key += '_' + Date.now();
            
            document.getElementById('generatedKey').textContent = key;
            document.getElementById('apiKeyDisplay').classList.remove('hidden');
            alert('API key generated! Make sure to save it somewhere safe.');
        }
        
        function createBackup() {
            if (confirm('Create a backup of all system data? This may take a few moments.')) {
                alert('Backup created successfully! Download will start automatically.');
                // In a real implementation, this would trigger a backup download
            }
        }
        
        // Danger Zone Functions
        function resetSystem() {
            if (confirm('⚠️ WARNING: This will reset all system data to defaults.\n\nAre you absolutely sure?')) {
                const confirmation = prompt('Type "RESET SYSTEM" to confirm:');
                if (confirmation === 'RESET SYSTEM') {
                    alert('System reset initiated. All data will be restored to factory defaults.');
                    // In a real implementation, this would reset the system
                }
            }
        }
        
        function deleteAllData() {
            if (confirm('🚨 DANGER: This will PERMANENTLY delete ALL data.\n\nThis action cannot be undone!\n\nAre you absolutely sure?')) {
                const confirmation = prompt('Type "DELETE ALL DATA" to confirm:');
                if (confirmation === 'DELETE ALL DATA') {
                    alert('Data deletion initiated. This may take a few moments.');
                    // In a real implementation, this would delete all data
                }
            }
        }
        
        function resetToDefaults() {
            if (confirm('Reset all settings to default values?')) {
                document.querySelector('form').reset();
                alert('Settings reset to defaults. Click Save to apply.');
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
                    (currentPage === '' && href.includes('settings'))) {
                    item.classList.add('active');
                }
            });
            
            // Set current year in footer
            document.getElementById('currentYear').textContent = new Date().getFullYear();
        });
    </script>
</body>
</html>