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
$customer_points_row = mysqli_fetch_assoc($points_result);
$customer_points = $customer_points_row['points'] ?? 0;

// Define rewards
$rewards = [
    ['id' => 1, 'name' => 'Free Cleaning Kit', 'points' => 100, 'description' => 'Eco-friendly cleaning supplies', 'icon' => 'fas fa-broom', 'color' => 'from-green-500 to-emerald-600'],
    ['id' => 2, 'name' => 'Reusable Shopping Bag', 'points' => 150, 'description' => 'Durable eco-friendly bag', 'icon' => 'fas fa-shopping-bag', 'color' => 'from-blue-500 to-cyan-600'],
    ['id' => 3, 'name' => 'Water Bottle', 'points' => 200, 'description' => 'Stainless steel water bottle', 'icon' => 'fas fa-wine-bottle', 'color' => 'from-purple-500 to-pink-600'],
    ['id' => 4, 'name' => 'Garden Kit', 'points' => 300, 'description' => 'Home gardening starter kit', 'icon' => 'fas fa-seedling', 'color' => 'from-green-500 to-lime-600'],
    ['id' => 5, 'name' => 'Eco Warrior T-Shirt', 'points' => 500, 'description' => 'Limited edition eco t-shirt', 'icon' => 'fas fa-tshirt', 'color' => 'from-orange-500 to-red-600'],
    ['id' => 6, 'name' => 'Smart Bin', 'points' => 1000, 'description' => 'Smart waste sorting bin', 'icon' => 'fas fa-trash-alt', 'color' => 'from-gray-500 to-blue-600'],
];

// Get reward redemption history
$redemptions_query = "SELECT * FROM reward_redemptions WHERE customer_id = $customer_id ORDER BY redeemed_at DESC";
$redemptions_result = mysqli_query($conn, $redemptions_query);
$redemptions_count = mysqli_num_rows($redemptions_result);

// Handle reward redemption
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['redeem_reward'])) {
    $reward_id = (int)$_POST['reward_id'];
    
    // Find the selected reward
    $selected_reward = null;
    foreach ($rewards as $reward) {
        if ($reward['id'] == $reward_id) {
            $selected_reward = $reward;
            break;
        }
    }
    
    if ($selected_reward && $customer_points >= $selected_reward['points']) {
        // Insert redemption record
        $insert_query = "INSERT INTO reward_redemptions (customer_id, reward_name, points_used, status) 
                        VALUES ($customer_id, '{$selected_reward['name']}', {$selected_reward['points']}, 'pending')";
        
        if (mysqli_query($conn, $insert_query)) {
            // Update customer points
            $update_points = "UPDATE customer SET points = points - {$selected_reward['points']} WHERE customer_id = $customer_id";
            mysqli_query($conn, $update_points);
            
            // Create notification
            $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                                  VALUES ($customer_id, 'customer', 'Reward Redeemed', 
                                  'You have successfully redeemed {$selected_reward['name']} for {$selected_reward['points']} points.', 'system')";
            mysqli_query($conn, $notification_query);
            
            $success = "Successfully redeemed {$selected_reward['name']}! Your reward will be processed soon.";
            $customer_points -= $selected_reward['points'];
            
            // Refresh points
            $points_result = mysqli_query($conn, $points_query);
            $customer_points_row = mysqli_fetch_assoc($points_result);
            $customer_points = $customer_points_row['points'] ?? 0;
            
            // Refresh redemptions
            $redemptions_result = mysqli_query($conn, $redemptions_query);
            $redemptions_count = mysqli_num_rows($redemptions_result);
        } else {
            $error = "Error processing redemption. Please try again.";
        }
    } else {
        $error = "Insufficient points for this reward!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Rewards - WasteWise</title>
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
        .status-processing { background: #dbeafe; color: #1e40af; }
        .status-delivered { background: #dcfce7; color: #166534; }
        .status-cancelled { background: #fee2e2; color: #991b1b; }
        
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
        
        /* Reward card animations */
        .reward-card:hover .reward-icon {
            transform: scale(1.1) rotate(5deg);
        }
        
        .reward-icon {
            transition: transform 0.3s ease;
        }
        
        /* Progress bar */
        .progress-bar {
            height: 8px;
            border-radius: 4px;
            overflow: hidden;
            background: rgba(255, 255, 255, 0.1);
        }
        
        .progress-fill {
            height: 100%;
            border-radius: 4px;
            background: linear-gradient(90deg, #4ade80, #22c55e);
            transition: width 0.5s ease;
        }
        
        /* Glow effect for high-value rewards */
        .reward-glow {
            animation: glow 2s ease-in-out infinite alternate;
        }
        
        @keyframes glow {
            from {
                box-shadow: 0 0 10px rgba(139, 92, 246, 0.5);
            }
            to {
                box-shadow: 0 0 20px rgba(139, 92, 246, 0.8), 0 0 30px rgba(139, 92, 246, 0.4);
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
            <a href="rewards.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
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
                    <a href="rewards.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
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
                        <h1 class="text-2xl font-bold text-white">Rewards Center</h1>
                        <p class="text-gray-300 text-sm">Earn points by reporting waste and redeem exciting rewards!</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Points Display -->
                        <div class="bg-gradient-to-r from-yellow-500 to-orange-600 text-white px-4 py-2 rounded-lg shadow-lg">
                            <div class="flex items-center space-x-2">
                                <i class="fas fa-coins"></i>
                                <span class="font-bold"><?php echo $customer_points; ?> Points</span>
                            </div>
                        </div>
                        
                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                <div class="text-gray-300 text-sm">Customer</div>
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
                    <h1 class="text-2xl font-bold text-white">Rewards Center</h1>
                    <p class="text-gray-300 text-sm">Earn points by reporting waste and redeem exciting rewards!</p>
                </div>
            </header>

            <!-- Main Content Area -->
            <main class="p-6 safe-area-bottom">
                <!-- Success/Error Messages -->
                <?php if($success): ?>
                <div class="mb-6 bg-green-900/20 border border-green-800 text-green-300 px-6 py-4 rounded-xl fade-in-up">
                    <div class="flex items-center">
                        <i class="fas fa-check-circle text-green-400 text-xl mr-3"></i>
                        <div>
                            <h4 class="font-semibold">Success!</h4>
                            <p class="text-sm mt-1"><?php echo $success; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>
                
                <?php if($error): ?>
                <div class="mb-6 bg-red-900/20 border border-red-800 text-red-300 px-6 py-4 rounded-xl fade-in-up">
                    <div class="flex items-center">
                        <i class="fas fa-exclamation-circle text-red-400 text-xl mr-3"></i>
                        <div>
                            <h4 class="font-semibold">Error!</h4>
                            <p class="text-sm mt-1"><?php echo $error; ?></p>
                        </div>
                    </div>
                </div>
                <?php endif; ?>

                <!-- Points Display Card -->
                <div class="mb-8">
                    <div class="bg-gradient-to-r from-yellow-500 via-orange-500 to-yellow-600 rounded-2xl shadow-xl p-8 text-white hover-lift">
                        <div class="flex flex-col lg:flex-row items-center justify-between">
                            <div class="text-center lg:text-left mb-6 lg:mb-0">
                                <div class="text-5xl lg:text-6xl font-bold mb-2"><?php echo $customer_points; ?></div>
                                <div class="text-xl opacity-90">Reward Points Available</div>
                                <p class="text-yellow-100 text-sm mt-3 max-w-lg">
                                    <i class="fas fa-lightbulb mr-2"></i>
                                    Keep reporting waste issues to earn more points and unlock amazing rewards!
                                </p>
                            </div>
                            <div class="relative">
                                <div class="w-32 h-32 lg:w-40 lg:h-40 rounded-full bg-white/20 flex items-center justify-center">
                                    <i class="fas fa-trophy text-white text-5xl lg:text-6xl"></i>
                                </div>
                                <div class="absolute -top-2 -right-2 w-12 h-12 bg-red-500 rounded-full flex items-center justify-center animate-bounce">
                                    <i class="fas fa-gift text-white text-lg"></i>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Points Progress -->
                        <div class="mt-8">
                            <div class="flex justify-between items-center mb-2">
                                <span class="text-yellow-100 text-sm">Progress to next tier</span>
                                <span class="text-white font-semibold">
                                    <?php 
                                    $nextTierPoints = 500; // Example next tier
                                    $progress = min(($customer_points / $nextTierPoints) * 100, 100);
                                    echo round($progress, 1); ?>%
                                </span>
                            </div>
                            <div class="progress-bar">
                                <div class="progress-fill" style="width: <?php echo $progress; ?>%"></div>
                            </div>
                        </div>
                    </div>
                </div>

                <!-- How to Earn Points -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-xl font-bold text-white">How to Earn Points</h2>
                        <div class="text-green-400 text-sm">
                            <i class="fas fa-bolt mr-1"></i>
                            Quick & Easy
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                        <div class="bg-white/5 rounded-xl p-5 hover:bg-white/10 transition-colors hover-lift">
                            <div class="w-12 h-12 rounded-lg bg-green-500/20 flex items-center justify-center mb-4">
                                <i class="fas fa-flag text-green-400 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-white mb-2">Submit Report</h4>
                            <div class="text-green-400 font-bold text-lg mb-2">+10 points</div>
                            <p class="text-gray-300 text-sm">For every valid waste report or pickup request</p>
                        </div>
                        
                        <div class="bg-white/5 rounded-xl p-5 hover:bg-white/10 transition-colors hover-lift">
                            <div class="w-12 h-12 rounded-lg bg-blue-500/20 flex items-center justify-center mb-4">
                                <i class="fas fa-check-circle text-blue-400 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-white mb-2">Report Completion</h4>
                            <div class="text-blue-400 font-bold text-lg mb-2">+20 points</div>
                            <p class="text-gray-300 text-sm">When your report is marked as completed</p>
                        </div>
                        
                        <div class="bg-white/5 rounded-xl p-5 hover:bg-white/10 transition-colors hover-lift">
                            <div class="w-12 h-12 rounded-lg bg-purple-500/20 flex items-center justify-center mb-4">
                                <i class="fas fa-calendar text-purple-400 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-white mb-2">Daily Login</h4>
                            <div class="text-purple-400 font-bold text-lg mb-2">+5 points</div>
                            <p class="text-gray-300 text-sm">Login daily to earn bonus points</p>
                        </div>
                        
                        <div class="bg-white/5 rounded-xl p-5 hover:bg-white/10 transition-colors hover-lift">
                            <div class="w-12 h-12 rounded-lg bg-yellow-500/20 flex items-center justify-center mb-4">
                                <i class="fas fa-share-alt text-yellow-400 text-xl"></i>
                            </div>
                            <h4 class="font-semibold text-white mb-2">Refer Friends</h4>
                            <div class="text-yellow-400 font-bold text-lg mb-2">+50 points</div>
                            <p class="text-gray-300 text-sm">For each friend who joins and submits a report</p>
                        </div>
                    </div>
                </div>

                <!-- Available Rewards -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-white">Available Rewards</h2>
                            <p class="text-gray-300 text-sm">Redeem your points for exciting eco-friendly rewards</p>
                        </div>
                        <div class="text-green-400 text-sm font-medium">
                            <?php echo count($rewards); ?> Rewards Available
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                        <?php foreach($rewards as $reward): 
                            $canRedeem = $customer_points >= $reward['points'];
                            $neededPoints = $reward['points'] - $customer_points;
                        ?>
                        <div class="bg-gradient-to-br <?php echo $reward['color']; ?> rounded-2xl shadow-lg overflow-hidden hover-lift <?php echo $reward['points'] >= 500 ? 'reward-glow' : ''; ?>">
                            <div class="p-6 text-white">
                                <div class="flex justify-between items-start mb-4">
                                    <div class="w-14 h-14 rounded-xl bg-white/20 flex items-center justify-center reward-icon">
                                        <i class="<?php echo $reward['icon']; ?> text-2xl"></i>
                                    </div>
                                    <div class="bg-white/20 px-3 py-1 rounded-full text-sm font-semibold">
                                        <?php echo $reward['points']; ?> points
                                    </div>
                                </div>
                                
                                <h3 class="text-xl font-bold mb-2"><?php echo $reward['name']; ?></h3>
                                <p class="text-white/80 text-sm mb-6"><?php echo $reward['description']; ?></p>
                                
                                <form method="POST" action="" class="space-y-4">
                                    <input type="hidden" name="reward_id" value="<?php echo $reward['id']; ?>">
                                    
                                    <?php if($canRedeem): ?>
                                    <button type="submit" name="redeem_reward" 
                                            class="w-full bg-white text-gray-900 font-semibold py-3 rounded-xl hover:bg-gray-100 transition-all duration-300 transform hover:scale-[1.02] shadow-lg flex items-center justify-center space-x-2">
                                        <i class="fas fa-gift"></i>
                                        <span>Redeem Now</span>
                                    </button>
                                    <?php else: ?>
                                    <button type="button" 
                                            class="w-full bg-white/20 text-white font-semibold py-3 rounded-xl cursor-not-allowed flex items-center justify-center space-x-2">
                                        <i class="fas fa-lock"></i>
                                        <span>Need <?php echo $neededPoints; ?> more points</span>
                                    </button>
                                    <div class="text-center text-white/70 text-sm">
                                        <i class="fas fa-info-circle mr-1"></i>
                                        You have <?php echo $customer_points; ?> of <?php echo $reward['points']; ?> points
                                    </div>
                                    <?php endif; ?>
                                </form>
                            </div>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>

                <!-- Redemption History -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <div>
                            <h2 class="text-xl font-bold text-white">Redemption History</h2>
                            <p class="text-gray-300 text-sm">Track your reward redemptions and their status</p>
                        </div>
                        <div class="text-green-400 text-sm font-medium">
                            <?php echo $redemptions_count; ?> Redemption<?php echo $redemptions_count != 1 ? 's' : ''; ?>
                        </div>
                    </div>
                    
                    <?php if($redemptions_count == 0): ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/10 flex items-center justify-center">
                                <i class="fas fa-gift text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-gray-300 font-medium text-xl mb-2">No redemptions yet</h3>
                            <p class="text-gray-400 mb-6">You haven't redeemed any rewards yet. Start earning points!</p>
                            <a href="submit_report.php" 
                               class="inline-block bg-gradient-to-r from-green-500 to-emerald-600 text-white px-6 py-3 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all hover-lift shadow-lg">
                                <i class="fas fa-plus mr-2"></i>
                                Submit Report to Earn Points
                            </a>
                        </div>
                    <?php else: ?>
                        <div class="overflow-x-auto">
                            <table class="w-full">
                                <thead>
                                    <tr class="border-b border-gray-700">
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Reward</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Points Used</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Status</th>
                                        <th class="text-left py-4 px-4 text-gray-300 font-medium">Date Redeemed</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php 
                                    mysqli_data_seek($redemptions_result, 0);
                                    while($redemption = mysqli_fetch_assoc($redemptions_result)): 
                                    ?>
                                    <tr class="border-b border-gray-800 hover:bg-white/5 transition-colors">
                                        <td class="py-4 px-4">
                                            <div class="flex items-center space-x-3">
                                                <div class="w-10 h-10 rounded-lg bg-gradient-to-r from-purple-500/20 to-pink-500/20 flex items-center justify-center">
                                                    <i class="fas fa-gift text-purple-400"></i>
                                                </div>
                                                <div>
                                                    <div class="font-medium text-white"><?php echo htmlspecialchars($redemption['reward_name']); ?></div>
                                                </div>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="flex items-center">
                                                <i class="fas fa-coins text-yellow-400 mr-2"></i>
                                                <span class="text-white font-medium"><?php echo $redemption['points_used']; ?> points</span>
                                            </div>
                                        </td>
                                        <td class="py-4 px-4">
                                            <span class="px-3 py-1 rounded-full text-xs font-medium status-<?php echo $redemption['status']; ?>">
                                                <?php echo ucfirst($redemption['status']); ?>
                                            </span>
                                        </td>
                                        <td class="py-4 px-4">
                                            <div class="text-gray-300 text-sm">
                                                <?php echo date('M d, Y', strtotime($redemption['redeemed_at'])); ?>
                                            </div>
                                            <div class="text-gray-500 text-xs">
                                                <?php echo date('h:i A', strtotime($redemption['redeemed_at'])); ?>
                                            </div>
                                        </td>
                                    </tr>
                                    <?php endwhile; ?>
                                </tbody>
                            </table>
                        </div>
                        
                        <!-- Quick Stats -->
                        <div class="mt-6 pt-6 border-t border-gray-700 flex flex-wrap justify-between items-center text-sm text-gray-400">
                            <div>
                                <i class="fas fa-history mr-1"></i>
                                Showing <?php echo $redemptions_count; ?> redemption<?php echo $redemptions_count != 1 ? 's' : ''; ?>
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
        
        // Reward redemption confirmation
        document.addEventListener('DOMContentLoaded', function() {
            const redeemButtons = document.querySelectorAll('button[name="redeem_reward"]');
            redeemButtons.forEach(button => {
                button.addEventListener('click', function(e) {
                    if (!this.disabled) {
                        const rewardName = this.closest('.bg-gradient-to-br').querySelector('h3').textContent;
                        const rewardPoints = this.closest('.bg-gradient-to-br').querySelector('.bg-white\\/20').textContent;
                        
                        if (!confirm(`Are you sure you want to redeem "${rewardName}" for ${rewardPoints}?`)) {
                            e.preventDefault();
                        }
                    }
                });
            });
        });
        
        // Animate points counter
        function animatePointsCounter() {
            const pointsElement = document.querySelector('.text-5xl.font-bold');
            if (pointsElement) {
                const points = parseInt(pointsElement.textContent);
                let current = 0;
                const increment = points / 50;
                const timer = setInterval(() => {
                    current += increment;
                    if (current >= points) {
                        current = points;
                        clearInterval(timer);
                    }
                    pointsElement.textContent = Math.floor(current);
                }, 20);
            }
        }
        
        // Initialize on page load
        document.addEventListener('DOMContentLoaded', function() {
            // Don't animate points if there was a recent redemption (page refresh)
            if (!window.location.hash.includes('redeemed')) {
                animatePointsCounter();
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
        
        // Add scroll animation for reward cards
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
        
        // Observe reward cards
        document.querySelectorAll('.bg-gradient-to-br').forEach(card => {
            observer.observe(card);
        });
    </script>
</body>
</html>