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

// Get customer stats for points display
$stats_query = "SELECT points as count FROM customer WHERE customer_id = $customer_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats_row = mysqli_fetch_assoc($stats_result);
$total_points = $stats_row['count'] ?? 0;

// Search functionality
$search = '';
$where = "customer_id = $customer_id";

if (isset($_GET['search'])) {
    $search = sanitize($_GET['search']);
    $where .= " AND (title LIKE '%$search%' OR description LIKE '%$search%')";
}

if (isset($_GET['status']) && $_GET['status'] != 'all') {
    $status = sanitize($_GET['status']);
    $where .= " AND status = '$status'";
}

// Get customer reports
$reports_query = "SELECT * FROM reports WHERE $where ORDER BY created_at DESC";
$reports = mysqli_query($conn, $reports_query);

// Get total counts for stats
$total_query = "SELECT COUNT(*) as total FROM reports WHERE customer_id = $customer_id";
$total_result = mysqli_query($conn, $total_query);
$total_row = mysqli_fetch_assoc($total_result);
$total_reports = $total_row['total'] ?? 0;

// Get counts by status
$status_counts = [
    'pending' => 0,
    'in_progress' => 0,
    'completed' => 0,
    'rejected' => 0
];

foreach ($status_counts as $status => $count) {
    $query = "SELECT COUNT(*) as count FROM reports WHERE customer_id = $customer_id AND status = '$status'";
    $result = mysqli_query($conn, $query);
    $row = mysqli_fetch_assoc($result);
    $status_counts[$status] = $row['count'] ?? 0;
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Reports - WasteWise</title>
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
        .status-rejected { background: #fee2e2; color: #991b1b; }
        
        /* Priority badges */
        .priority-low { background: #dcfce7; color: #166534; }
        .priority-medium { background: #fef3c7; color: #92400e; }
        .priority-high { background: #fee2e2; color: #991b1b; }
        
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
        
        /* Table styles */
        .table-header {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .table-row:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Custom scrollbar for table */
        .table-scroll {
            max-height: 500px;
            overflow-y: auto;
        }
        
        .table-scroll::-webkit-scrollbar {
            width: 6px;
        }
        
        .table-scroll::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 10px;
        }
        
        .table-scroll::-webkit-scrollbar-thumb {
            background: rgba(74, 222, 128, 0.5);
            border-radius: 10px;
        }
        
        .table-scroll::-webkit-scrollbar-thumb:hover {
            background: rgba(74, 222, 128, 0.8);
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
            <header class="glass-card px-6 py-4 safe-area-top mx-6 mt-6 rounded-2xl backdrop-blur-lg">
                <div class="flex items-center justify-between">
                    <!-- Mobile Menu Button -->
                    <button onclick="openMobileMenu()" class="lg:hidden text-white text-2xl">
                        <i class="fas fa-bars"></i>
                    </button>
                    
                    <!-- Page Title -->
                    <div class="hidden lg:block">
                        <h1 class="text-2xl font-bold text-white">My Reports</h1>
                        <p class="text-gray-300 text-sm">View and manage all your submitted reports</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- New Report Button -->
                        <a href="submit_report.php" 
                           class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-plus-circle mr-2"></i>
                            New Report
                        </a>
                        
                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                <div class="text-gray-300 text-sm"><?php echo $total_points; ?> Points</div>
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
                    <h1 class="text-2xl font-bold text-white">My Reports</h1>
                    <p class="text-gray-300 text-sm">View and manage all your submitted reports</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Total Reports -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Total Reports</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $total_reports; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                                <i class="fas fa-flag text-blue-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Pending -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Pending</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $status_counts['pending']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-yellow-500/20 flex items-center justify-center">
                                <i class="fas fa-clock text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- In Progress -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">In Progress</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $status_counts['in_progress']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center">
                                <i class="fas fa-sync-alt text-purple-400 text-xl"></i>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Completed -->
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white hover-lift">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm">Completed</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo $status_counts['completed']; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
                                <i class="fas fa-check-circle text-xl"></i>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Search and Filter Section -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-8 fade-in-up">
                    <h2 class="text-xl font-bold text-white mb-6">Filter Reports</h2>
                    <form method="GET" action="">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                            <!-- Search Box -->
                            <div>
                                <label class="block text-gray-300 text-sm mb-2">Search Reports</label>
                                <div class="relative">
                                    <input type="text" 
                                           name="search" 
                                           class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors pl-12"
                                           placeholder="Search by title or description..."
                                           value="<?php echo htmlspecialchars($search); ?>">
                                    <i class="fas fa-search absolute left-4 top-3.5 text-gray-400"></i>
                                </div>
                            </div>
                            
                            <!-- Status Filter -->
                            <div>
                                <label class="block text-gray-300 text-sm mb-2">Status Filter</label>
                                <select name="status" 
                                        class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors">
                                    <option value="all" class="bg-gray-800" <?php echo (!isset($_GET['status']) || $_GET['status'] == 'all') ? 'selected' : ''; ?>>All Status</option>
                                    <option value="pending" class="bg-gray-800" <?php echo (isset($_GET['status']) && $_GET['status'] == 'pending') ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" class="bg-gray-800" <?php echo (isset($_GET['status']) && $_GET['status'] == 'in_progress') ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" class="bg-gray-800" <?php echo (isset($_GET['status']) && $_GET['status'] == 'completed') ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" class="bg-gray-800" <?php echo (isset($_GET['status']) && $_GET['status'] == 'rejected') ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <!-- Action Buttons -->
                            <div class="flex items-end space-x-3">
                                <button type="submit" 
                                        class="flex-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold py-3 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                                    <i class="fas fa-search mr-2"></i>
                                    Apply Filters
                                </button>
                                
                                <?php if($search || (isset($_GET['status']) && $_GET['status'] != 'all')): ?>
                                <a href="my_reports.php" 
                                   class="flex-1 bg-gradient-to-r from-red-500 to-pink-600 text-white font-semibold py-3 rounded-xl hover:from-red-600 hover:to-pink-700 transition-all duration-300 hover-lift shadow-md text-center">
                                    <i class="fas fa-times mr-2"></i>
                                    Clear Filters
                                </a>
                                <?php endif; ?>
                            </div>
                        </div>
                    </form>
                </div>

                <!-- Reports Table -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-white">Your Report History</h2>
                        <div class="text-gray-300 text-sm">
                            Showing <?php echo mysqli_num_rows($reports); ?> report(s)
                        </div>
                    </div>
                    
                    <?php if(mysqli_num_rows($reports) == 0): ?>
                        <!-- Empty State -->
                        <div class="text-center py-12">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/10 flex items-center justify-center">
                                <i class="fas fa-flag text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-gray-300 font-medium text-xl mb-2">No reports found</h3>
                            <p class="text-gray-400 mb-6">You haven't submitted any reports yet.</p>
                            <a href="submit_report.php" 
                               class="inline-block bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-3 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all hover-lift shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Submit Your First Report
                            </a>
                        </div>
                    <?php else: ?>
                        <!-- Table Container with Scroll -->
                        <div class="table-scroll">
                            <table class="w-full">
                                <thead>
                                    <tr class="table-header border-b border-gray-700">
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Report ID</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Title</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Type</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Status</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Priority</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Date</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    mysqli_data_seek($reports, 0);
                                    while($report = mysqli_fetch_assoc($reports)): 
                                    ?>
                                    <tr class="table-row border-b border-gray-800 hover:bg-white/5 transition-colors">
                                        <td class="py-4 px-4">
                                            <span class="font-mono text-white">#<?php echo $report['report_id']; ?></span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="font-medium text-white"><?php echo htmlspecialchars($report['title']); ?></div>
                                            <div class="text-gray-400 text-xs mt-1 truncate max-w-xs">
                                                <?php echo htmlspecialchars(substr($report['description'], 0, 50)); ?>...
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="text-gray-300 text-sm">
                                                <?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium status-<?php echo $report['status']; ?>">
                                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium priority-<?php echo $report['priority']; ?>">
                                                <?php echo ucfirst($report['priority']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="text-gray-300 text-sm">
                                                <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                                            </div>
                                            <div class="text-gray-500 text-xs">
                                                <?php echo date('h:i A', strtotime($report['created_at'])); ?>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex space-x-2">
                                                <a href="view_my_report.php?id=<?php echo $report['report_id']; ?>" 
                                                   class="w-8 h-8 bg-blue-500/20 text-blue-400 rounded-lg flex items-center justify-center hover:bg-blue-500/30 transition-colors"
                                                   title="View Report">
                                                    <i class="fas fa-eye text-sm"></i>
                                                </a>
                                                <?php if($report['status'] == 'pending'): ?>
                                                <a href="edit_my_report.php?id=<?php echo $report['report_id']; ?>" 
                                                   class="w-8 h-8 bg-green-500/20 text-green-400 rounded-lg flex items-center justify-center hover:bg-green-500/30 transition-colors"
                                                   title="Edit Report">
                                                    <i class="fas fa-edit text-sm"></i>
                                                </a>
                                                <?php endif; ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Quick Stats Footer -->
                        <div class="mt-6 pt-6 border-t border-gray-700 flex flex-wrap justify-between items-center text-sm text-gray-400">
                            <div class="flex items-center space-x-4">
                                <div class="flex items-center space-x-1">
                                    <div class="w-3 h-3 rounded-full bg-yellow-400"></div>
                                    <span>Pending: <?php echo $status_counts['pending']; ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <div class="w-3 h-3 rounded-full bg-purple-400"></div>
                                    <span>In Progress: <?php echo $status_counts['in_progress']; ?></span>
                                </div>
                                <div class="flex items-center space-x-1">
                                    <div class="w-3 h-3 rounded-full bg-green-400"></div>
                                    <span>Completed: <?php echo $status_counts['completed']; ?></span>
                                </div>
                            </div>
                            <div>
                                <a href="customer_dashboard.php" 
                                   class="text-green-400 hover:text-green-300 transition-colors">
                                    <i class="fas fa-tachometer-alt mr-1"></i>
                                    Back to Dashboard
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
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
        
        // Prevent zoom on double-tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
        
        // Auto-submit form when status filter changes on mobile
        document.addEventListener('DOMContentLoaded', function() {
            const statusSelect = document.querySelector('select[name="status"]');
            if (window.innerWidth < 768 && statusSelect) {
                statusSelect.addEventListener('change', function() {
                    this.form.submit();
                });
            }
        });
    </script>
</body>
</html>