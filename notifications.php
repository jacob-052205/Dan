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

// Handle notification actions
if (isset($_GET['mark_read'])) {
    $notification_id = mysqli_real_escape_string($conn, $_GET['mark_read']);
    $update_query = "UPDATE notifications SET is_read = 1 WHERE notification_id = '$notification_id' AND user_id = '$customer_id' AND user_type = 'customer'";
    mysqli_query($conn, $update_query);
    $_SESSION['success'] = "Notification marked as read!";
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['mark_all_read'])) {
    $update_query = "UPDATE notifications SET is_read = 1 WHERE user_id = '$customer_id' AND user_type = 'customer' AND is_read = 0";
    mysqli_query($conn, $update_query);
    $_SESSION['success'] = "All notifications marked as read!";
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['delete'])) {
    $notification_id = mysqli_real_escape_string($conn, $_GET['delete']);
    $delete_query = "DELETE FROM notifications WHERE notification_id = '$notification_id' AND user_id = '$customer_id' AND user_type = 'customer'";
    mysqli_query($conn, $delete_query);
    $_SESSION['success'] = "Notification deleted!";
    header("Location: notifications.php");
    exit();
}

if (isset($_GET['clear_read'])) {
    $delete_query = "DELETE FROM notifications WHERE user_id = '$customer_id' AND user_type = 'customer' AND is_read = 1";
    mysqli_query($conn, $delete_query);
    $_SESSION['success'] = "All read notifications cleared!";
    header("Location: notifications.php");
    exit();
}

// Fetch notifications
$query = "SELECT * FROM notifications WHERE user_id = '$customer_id' AND user_type = 'customer' ORDER BY created_at DESC";
$result = mysqli_query($conn, $query);

// Count notifications
$total_query = "SELECT COUNT(*) as total_count FROM notifications WHERE user_id = '$customer_id' AND user_type = 'customer'";
$total_result = mysqli_query($conn, $total_query);
$total_data = mysqli_fetch_assoc($total_result);
$total_count = $total_data['total_count'] ?? 0;

$unread_query = "SELECT COUNT(*) as unread_count FROM notifications WHERE user_id = '$customer_id' AND user_type = 'customer' AND is_read = 0";
$unread_result = mysqli_query($conn, $unread_query);
$unread_data = mysqli_fetch_assoc($unread_result);
$unread_count = $unread_data['unread_count'] ?? 0;

// Get customer points
$points_query = "SELECT points FROM customer WHERE customer_id = $customer_id";
$points_result = mysqli_query($conn, $points_query);
$points_data = mysqli_fetch_assoc($points_result);
$total_points = $points_data['points'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Notifications - WasteWise</title>
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
        
        .safe-area-top {
            padding-top: env(safe-area-inset-top);
        }
        
        .safe-area-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
        
        .mobile-menu {
            transform: translateX(-100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-menu.active {
            transform: translateX(0);
        }
        
        .menu-item.active {
            background: rgba(255, 255, 255, 0.2);
            border-left: 4px solid #4ade80;
        }
        
        .notification-unread {
            background: rgba(59, 130, 246, 0.15);
            border-left: 4px solid #3b82f6;
        }
        
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
            <a href="community.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                <i class="fas fa-users w-6 text-center"></i>
                <span>Community</span>
            </a>
            <a href="notifications.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
                <i class="fas fa-bell w-6 text-center"></i>
                <span>Notifications</span>
                <?php if($unread_count > 0): ?>
                <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">
                    <?php echo $unread_count; ?>
                </span>
                <?php endif; ?>
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
                    <a href="community.php" class="flex items-center space-x-3 text-white p-3 rounded-lg hover:bg-white/10 transition-colors">
                        <i class="fas fa-users w-6 text-center"></i>
                        <span>Community</span>
                    </a>
                    <a href="notifications.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
                        <i class="fas fa-bell w-6 text-center"></i>
                        <span>Notifications</span>
                        <?php if($unread_count > 0): ?>
                        <span class="ml-auto bg-red-500 text-white text-xs px-2 py-1 rounded-full animate-pulse">
                            <?php echo $unread_count; ?>
                        </span>
                        <?php endif; ?>
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
                        <h1 class="text-2xl font-bold text-white">Notifications</h1>
                        <p class="text-gray-300 text-sm">Stay updated with your waste management activities</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Back Button -->
                        <a href="customer_dashboard.php" 
                           class="bg-white/10 text-white px-4 py-2 rounded-lg hover:bg-white/20 transition-all duration-300 hover-lift">
                            <i class="fas fa-arrow-left mr-2"></i>
                            Back to Dashboard
                        </a>
                        
                        <!-- Report Button -->
                        <a href="submit_report.php" 
                           class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-4 py-2 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-plus mr-2"></i>
                            Report Waste
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
                    <h1 class="text-2xl font-bold text-white">Notifications</h1>
                    <p class="text-gray-300 text-sm">Stay updated with your activities</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Success Message -->
                <?php if (isset($_SESSION['success'])): ?>
                <div class="glass-card p-4 rounded-xl mb-6 bg-green-500/20 border border-green-500/30 fade-in-up">
                    <div class="flex items-center">
                        <div class="w-8 h-8 rounded-full bg-green-500/20 flex items-center justify-center mr-3">
                            <i class="fas fa-check text-green-400"></i>
                        </div>
                        <span class="text-white"><?php echo $_SESSION['success']; unset($_SESSION['success']); ?></span>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Stats Cards -->
                <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6 mb-8">
                    <!-- Unread Notifications -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg fade-in-up" style="animation-delay: 0.1s">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Unread</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $unread_count; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-blue-500/20 flex items-center justify-center">
                                <i class="fas fa-envelope text-blue-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-eye-slash text-blue-400 mr-1"></i>
                            Need your attention
                        </div>
                    </div>
                    
                    <!-- Total Notifications -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg fade-in-up" style="animation-delay: 0.2s">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Total</p>
                                <h3 class="text-3xl font-bold text-white mt-2"><?php echo $total_count; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-purple-500/20 flex items-center justify-center">
                                <i class="fas fa-bell text-purple-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-gray-300">
                            <i class="fas fa-history text-purple-400 mr-1"></i>
                            All time notifications
                        </div>
                    </div>
                    
                    <!-- Points Card -->
                    <div class="bg-gradient-to-r from-green-500 to-emerald-600 rounded-2xl shadow-lg p-6 text-white hover-lift fade-in-up" style="animation-delay: 0.3s">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-green-100 text-sm">Reward Points</p>
                                <h3 class="text-3xl font-bold mt-2"><?php echo $total_points; ?></h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-white/20 flex items-center justify-center">
                                <i class="fas fa-trophy text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 text-sm text-green-100">
                            <i class="fas fa-gift mr-1"></i>
                            Redeem for rewards
                        </div>
                    </div>
                    
                    <!-- Action Card -->
                    <div class="glass-card rounded-2xl p-6 hover-lift backdrop-blur-lg fade-in-up" style="animation-delay: 0.4s">
                        <div class="flex items-center justify-between">
                            <div>
                                <p class="text-gray-300 text-sm">Quick Actions</p>
                                <h3 class="text-2xl font-bold text-white mt-2">Manage</h3>
                            </div>
                            <div class="w-12 h-12 rounded-xl bg-yellow-500/20 flex items-center justify-center">
                                <i class="fas fa-cog text-yellow-400 text-xl"></i>
                            </div>
                        </div>
                        <div class="mt-4 flex space-x-2">
                            <?php if ($unread_count > 0): ?>
                            <a href="?mark_all_read=1" class="flex-1 bg-blue-500 hover:bg-blue-600 text-white text-sm px-3 py-2 rounded-lg text-center transition-colors">
                                Mark All Read
                            </a>
                            <?php endif; ?>
                            <?php if ($total_count > $unread_count): ?>
                            <a href="?clear_read=1" onclick="return confirm('Clear all read notifications? This cannot be undone.')"
                               class="flex-1 bg-gray-500 hover:bg-gray-600 text-white text-sm px-3 py-2 rounded-lg text-center transition-colors">
                                Clear Read
                            </a>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Notifications List -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up" style="animation-delay: 0.5s">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-white">All Notifications</h2>
                        <div class="flex items-center space-x-2">
                            <a href="customer_dashboard.php" class="text-gray-300 hover:text-white text-sm font-medium">
                                <i class="fas fa-tachometer-alt mr-1"></i> Dashboard
                            </a>
                            <span class="text-gray-500">•</span>
                            <a href="my_reports.php" class="text-gray-300 hover:text-white text-sm font-medium">
                                <i class="fas fa-flag mr-1"></i> My Reports
                            </a>
                        </div>
                    </div>
                    
                    <?php if ($result && mysqli_num_rows($result) > 0): ?>
                        <div class="space-y-4 max-h-[600px] overflow-y-auto pr-2 no-scrollbar">
                            <?php while ($notification = mysqli_fetch_assoc($result)): 
                                $is_unread = $notification['is_read'] == 0;
                                $message = htmlspecialchars_decode($notification['message']);
                                $message = nl2br(htmlspecialchars($message));
                            ?>
                            <div class="p-4 rounded-xl transition-all duration-300 hover-lift <?php echo $is_unread ? 'notification-unread' : 'bg-white/5'; ?>">
                                <div class="flex items-start space-x-4">
                                    <!-- Notification Icon -->
                                    <div class="flex-shrink-0">
                                        <div class="w-12 h-12 rounded-full <?php echo $is_unread ? 'bg-blue-500/20 text-blue-400' : 'bg-gray-800 text-gray-400'; ?> flex items-center justify-center">
                                            <?php 
                                            switch($notification['type']) {
                                                case 'status_update': 
                                                    echo '<i class="fas fa-sync-alt"></i>';
                                                    break;
                                                case 'new_report': 
                                                    echo '<i class="fas fa-flag"></i>';
                                                    break;
                                                case 'reward': 
                                                    echo '<i class="fas fa-trophy"></i>';
                                                    break;
                                                default: 
                                                    echo '<i class="fas fa-bell"></i>';
                                            }
                                            ?>
                                        </div>
                                    </div>
                                    
                                    <!-- Notification Content -->
                                    <div class="flex-1">
                                        <div class="flex justify-between items-start">
                                            <h4 class="font-semibold text-white text-lg mb-1">
                                                <?php echo htmlspecialchars($notification['title']); ?>
                                            </h4>
                                            <div class="flex items-center space-x-2">
                                                <?php if ($is_unread): ?>
                                                <a href="?mark_read=<?php echo $notification['notification_id']; ?>" 
                                                   class="text-blue-400 hover:text-blue-300 text-sm px-3 py-1 rounded-lg bg-blue-500/10 hover:bg-blue-500/20 transition-colors">
                                                    <i class="fas fa-check mr-1"></i> Mark Read
                                                </a>
                                                <?php endif; ?>
                                                <a href="?delete=<?php echo $notification['notification_id']; ?>" 
                                                   onclick="return confirm('Delete this notification?')"
                                                   class="text-red-400 hover:text-red-300 text-sm px-3 py-1 rounded-lg bg-red-500/10 hover:bg-red-500/20 transition-colors">
                                                    <i class="fas fa-trash-alt mr-1"></i> Delete
                                                </a>
                                            </div>
                                        </div>
                                        
                                        <div class="text-gray-300 mt-2 leading-relaxed">
                                            <?php echo $message; ?>
                                        </div>
                                        
                                        <div class="flex items-center justify-between mt-4">
                                            <div class="flex items-center text-sm text-gray-400">
                                                <i class="far fa-clock mr-1"></i>
                                                <span><?php echo date('F j, Y g:i A', strtotime($notification['created_at'])); ?></span>
                                                <span class="mx-2">•</span>
                                                <span class="px-2 py-1 bg-gray-700/50 rounded-full text-xs capitalize">
                                                    <?php echo str_replace('_', ' ', $notification['type']); ?>
                                                </span>
                                            </div>
                                            <?php if ($is_unread): ?>
                                            <span class="w-3 h-3 bg-blue-500 rounded-full animate-pulse"></span>
                                            <?php endif; ?>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-20 h-20 mx-auto mb-6 rounded-full bg-white/10 flex items-center justify-center">
                                <i class="fas fa-bell-slash text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-2xl font-semibold text-white mb-3">No Notifications</h3>
                            <p class="text-gray-300 mb-8 max-w-md mx-auto">
                                When you receive notifications about your reports or system updates, they will appear here.
                            </p>
                            <div class="flex flex-col sm:flex-row gap-4 justify-center">
                                <a href="customer_dashboard.php" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-3 rounded-lg hover:from-green-600 hover:to-emerald-700 transition-all hover-lift">
                                    <i class="fas fa-tachometer-alt mr-2"></i> Go to Dashboard
                                </a>
                                <a href="submit_report.php" class="glass-card text-white px-6 py-3 rounded-lg hover:bg-white/10 transition-colors hover-lift">
                                    <i class="fas fa-plus mr-2"></i> Report Waste
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
        <a href="submit_report.php" class="w-14 h-14 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
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
        
        // Auto-refresh every 60 seconds if there are unread notifications
        <?php if ($unread_count > 0): ?>
        setTimeout(function() {
            location.reload();
        }, 60000);
        <?php endif; ?>
        
        // Prevent zoom on double-tap for iOS
        let lastTouchEnd = 0;
        document.addEventListener('touchend', function(event) {
            const now = (new Date()).getTime();
            if (now - lastTouchEnd <= 300) {
                event.preventDefault();
            }
            lastTouchEnd = now;
        }, false);
    </script>
</body>
</html>