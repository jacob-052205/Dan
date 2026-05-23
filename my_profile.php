<?php
require_once 'config.php';

if (!isCustomer()) {
    redirect('login_customer.php');
}

$conn = getDBConnection();
$customer_id = $_SESSION['user_id'];

// Add this code to get customer profile picture
$profile_query = "SELECT * FROM customer WHERE customer_id = $customer_id";
$profile_result = mysqli_query($conn, $profile_query);
$customer = mysqli_fetch_assoc($profile_result);

// Check if profile picture exists
$profile_pic = !empty($customer['profile_picture']) && file_exists($customer['profile_picture']) 
    ? $customer['profile_picture'] 
    : null;

// Get customer stats for display
$stats_query = "SELECT 
                COUNT(*) as total_reports,
                SUM(CASE WHEN status = 'completed' THEN 1 ELSE 0 END) as completed_reports,
                SUM(CASE WHEN status = 'pending' THEN 1 ELSE 0 END) as pending_reports
                FROM reports WHERE customer_id = $customer_id";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Handle profile update
$success = '';
$error = '';
$password_success = '';
$password_error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['update_profile'])) {
        $full_name = sanitize($_POST['full_name']);
        $phone = sanitize($_POST['phone']);
        $address = sanitize($_POST['address']);
        
        // Handle profile picture upload
        $profile_picture = $customer['profile_picture'];
        if (isset($_FILES['profile_picture']) && $_FILES['profile_picture']['error'] == 0) {
            $upload_dir = 'uploads/profiles/';
            if (!file_exists($upload_dir)) {
                mkdir($upload_dir, 0777, true);
            }
            
            // Delete old profile picture if exists
            if ($profile_picture && file_exists($profile_picture)) {
                unlink($profile_picture);
            }
            
            $filename = 'customer_' . $customer_id . '_' . time() . '_' . basename($_FILES['profile_picture']['name']);
            $target_path = $upload_dir . $filename;
            
            // Check if uploaded file is an image
            $image_info = getimagesize($_FILES['profile_picture']['tmp_name']);
            if ($image_info !== false) {
                if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $target_path)) {
                    $profile_picture = $target_path;
                }
            }
        }
        
        $update_query = "UPDATE customer SET 
                        full_name = '$full_name',
                        phone = '$phone',
                        address = '$address',
                        profile_picture = '$profile_picture'
                        WHERE customer_id = $customer_id";
        
        if (mysqli_query($conn, $update_query)) {
            $_SESSION['user_name'] = $full_name;
            $success = "Profile updated successfully!";
            // Refresh customer data
            $profile_result = mysqli_query($conn, $profile_query);
            $customer = mysqli_fetch_assoc($profile_result);
            $profile_pic = !empty($customer['profile_picture']) && file_exists($customer['profile_picture']) 
                ? $customer['profile_picture'] 
                : null;
        } else {
            $error = "Error updating profile: " . mysqli_error($conn);
        }
    }
    
    // Handle password change
    if (isset($_POST['change_password'])) {
        $current_password = sanitize($_POST['current_password']);
        $new_password = sanitize($_POST['new_password']);
        $confirm_password = sanitize($_POST['confirm_password']);
        
        if (password_verify($current_password, $customer['password'])) {
            if ($new_password == $confirm_password) {
                if (strlen($new_password) >= 6) {
                    $hashed_password = password_hash($new_password, PASSWORD_DEFAULT);
                    $password_query = "UPDATE customer SET password = '$hashed_password' WHERE customer_id = $customer_id";
                    
                    if (mysqli_query($conn, $password_query)) {
                        $password_success = "Password changed successfully!";
                    } else {
                        $password_error = "Error changing password!";
                    }
                } else {
                    $password_error = "New password must be at least 6 characters!";
                }
            } else {
                $password_error = "New passwords do not match!";
            }
        } else {
            $password_error = "Current password is incorrect!";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>My Profile - WasteWise</title>
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
        
        /* Custom file upload */
        .file-upload input[type="file"] {
            display: none;
        }
        
        .file-upload-label {
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .file-upload-label:hover {
            background: rgba(255, 255, 255, 0.15);
        }
        
        /* Stats card colors */
        .stats-total { background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); }
        .stats-points { background: linear-gradient(135deg, #10b981 0%, #059669 100%); }
        .stats-completed { background: linear-gradient(135deg, #3b82f6 0%, #1d4ed8 100%); }
        
        /* Profile avatar */
        .profile-avatar {
            border: 4px solid rgba(255, 255, 255, 0.3);
            box-shadow: 0 10px 25px rgba(0, 0, 0, 0.2);
        }
        
        /* Form focus styles */
        .form-input:focus {
            border-color: #4ade80;
            ring-color: rgba(74, 222, 128, 0.2);
        }
        
        /* Section divider */
        .section-divider {
            position: relative;
        }
        
        .section-divider::before {
            content: '';
            position: absolute;
            left: 0;
            top: 50%;
            width: 100%;
            height: 1px;
            background: linear-gradient(90deg, transparent, rgba(255, 255, 255, 0.2), transparent);
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
                     alt="<?php echo htmlspecialchars($customer['full_name']); ?>"
                     class="w-12 h-12 rounded-full object-cover border-2 border-white">
                <?php else: ?>
                <div class="w-12 h-12 rounded-full gradient-bg flex items-center justify-center">
                    <span class="text-white font-bold text-lg">
                        <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                    </span>
                </div>
                <?php endif; ?>
                <div>
                    <div class="text-white font-semibold"><?php echo $customer['full_name']; ?></div>
                    <div class="text-green-400 text-sm"><?php echo $customer['points']; ?> Points</div>
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
            <a href="my_profile.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
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
                             alt="<?php echo htmlspecialchars($customer['full_name']); ?>"
                             class="w-12 h-12 rounded-full object-cover border-2 border-white">
                        <?php else: ?>
                        <div class="w-12 h-12 rounded-full gradient-bg flex items-center justify-center">
                            <span class="text-white font-bold text-lg">
                                <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                            </span>
                        </div>
                        <?php endif; ?>
                        <div>
                            <div class="font-semibold"><?php echo $customer['full_name']; ?></div>
                            <div class="text-green-400 text-sm"><?php echo $customer['points']; ?> Points</div>
                        </div>
                    </div>
                    <div class="text-gray-400 text-sm">
                        <i class="fas fa-envelope mr-2"></i>
                        <?php echo $customer['customer_email']; ?>
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
                    <a href="my_profile.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
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
                        <h1 class="text-2xl font-bold text-white">My Profile</h1>
                        <p class="text-gray-300 text-sm">Manage your account information and settings</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Back to Dashboard -->
                        <a href="customer_dashboard.php" 
                           class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Dashboard
                        </a>
                        
                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $customer['full_name']; ?></div>
                                <div class="text-gray-300 text-sm"><?php echo $customer['points']; ?> Points</div>
                            </div>
                            <?php if($profile_pic): ?>
                            <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                 alt="<?php echo htmlspecialchars($customer['full_name']); ?>"
                                 class="w-10 h-10 rounded-full object-cover border-2 border-green-500">
                            <?php else: ?>
                            <div class="w-10 h-10 rounded-full gradient-bg flex items-center justify-center">
                                <span class="text-white font-bold">
                                    <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                                </span>
                            </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>
                
                <!-- Mobile Page Title -->
                <div class="lg:hidden mt-4">
                    <h1 class="text-2xl font-bold text-white">My Profile</h1>
                    <p class="text-gray-300 text-sm">Manage your account information and settings</p>
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

                <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                    <!-- Profile Sidebar -->
                    <div class="lg:col-span-1">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg hover-lift">
                            <!-- Profile Avatar -->
                            <div class="flex flex-col items-center">
                                <div class="relative">
                                    <div class="w-32 h-32 rounded-full profile-avatar overflow-hidden mb-4">
                                        <?php if($profile_pic): ?>
                                        <img src="<?php echo htmlspecialchars($profile_pic); ?>" 
                                             alt="<?php echo htmlspecialchars($customer['full_name']); ?>"
                                             class="w-full h-full object-cover">
                                        <?php else: ?>
                                        <div class="w-full h-full gradient-bg flex items-center justify-center">
                                            <span class="text-white font-bold text-4xl">
                                                <?php echo strtoupper(substr($customer['full_name'], 0, 1)); ?>
                                            </span>
                                        </div>
                                        <?php endif; ?>
                                    </div>
                                    
                                    <!-- Upload Button -->
                                    <label for="profile-picture-upload" 
                                           class="absolute bottom-0 right-0 w-10 h-10 bg-green-500 rounded-full flex items-center justify-center cursor-pointer hover:bg-green-600 transition-colors shadow-lg">
                                        <i class="fas fa-camera text-white text-sm"></i>
                                    </label>
                                </div>
                                
                                <h2 class="text-2xl font-bold text-white mb-2"><?php echo htmlspecialchars($customer['full_name']); ?></h2>
                                <p class="text-gray-300 mb-4"><?php echo htmlspecialchars($customer['customer_email']); ?></p>
                                
                                <!-- Member Since -->
                                <div class="flex items-center text-gray-400 text-sm mb-6">
                                    <i class="fas fa-calendar-alt mr-2"></i>
                                    <span>Member since <?php echo date('F Y', strtotime($customer['created_at'])); ?></span>
                                </div>
                            </div>
                            
                            <!-- Profile Stats -->
                            <div class="grid grid-cols-3 gap-3 mb-6">
                                <div class="stats-total rounded-xl p-4 text-white text-center">
                                    <div class="text-2xl font-bold"><?php echo $stats['total_reports']; ?></div>
                                    <div class="text-sm opacity-90">Reports</div>
                                </div>
                                <div class="stats-points rounded-xl p-4 text-white text-center">
                                    <div class="text-2xl font-bold"><?php echo $customer['points']; ?></div>
                                    <div class="text-sm opacity-90">Points</div>
                                </div>
                                <div class="stats-completed rounded-xl p-4 text-white text-center">
                                    <div class="text-2xl font-bold"><?php echo $stats['completed_reports']; ?></div>
                                    <div class="text-sm opacity-90">Completed</div>
                                </div>
                            </div>
                            
                            <!-- Quick Info -->
                            <div class="space-y-3">
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-phone w-6 text-green-400"></i>
                                    <span class="ml-2"><?php echo $customer['phone'] ? htmlspecialchars($customer['phone']) : 'Not set'; ?></span>
                                </div>
                                <div class="flex items-start text-gray-300">
                                    <i class="fas fa-map-marker-alt w-6 text-blue-400 mt-1"></i>
                                    <span class="ml-2 flex-1"><?php echo $customer['address'] ? htmlspecialchars(substr($customer['address'], 0, 50)) . '...' : 'Not set'; ?></span>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Profile Content -->
                    <div class="lg:col-span-2">
                        <!-- Profile Information Form -->
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-bold text-white">Profile Information</h2>
                                <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                                    <i class="fas fa-user-edit text-blue-400"></i>
                                </div>
                            </div>
                            
                            <form method="POST" action="" enctype="multipart/form-data">
                                <!-- Hidden file input for profile picture -->
                                <input type="file" id="profile-picture-upload" name="profile_picture" accept="image/*" class="hidden" onchange="previewProfilePicture(event)">
                                
                                <!-- Image Preview -->
                                <div id="avatar-preview" class="hidden mb-6">
                                    <div class="flex items-center space-x-4">
                                        <div class="w-20 h-20 rounded-full overflow-hidden border-2 border-green-500">
                                            <img id="preview-image" class="w-full h-full object-cover">
                                        </div>
                                        <div>
                                            <p class="text-green-400 text-sm font-medium">New profile picture selected</p>
                                            <button type="button" onclick="removeProfilePicture()" class="text-red-400 text-sm hover:text-red-300 mt-1">
                                                <i class="fas fa-times mr-1"></i> Remove
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <!-- Full Name -->
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            Full Name <span class="text-red-400">*</span>
                                        </label>
                                        <input type="text" name="full_name" 
                                               class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input"
                                               value="<?php echo htmlspecialchars($customer['full_name']); ?>" 
                                               required>
                                    </div>
                                    
                                    <!-- Email (Disabled) -->
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            Email Address
                                        </label>
                                        <input type="email" 
                                               class="w-full px-4 py-3 bg-white/10 border border-gray-700 rounded-xl text-gray-400 cursor-not-allowed"
                                               value="<?php echo htmlspecialchars($customer['customer_email']); ?>" 
                                               disabled>
                                        <p class="text-gray-500 text-xs mt-2">
                                            <i class="fas fa-info-circle mr-1"></i> Email cannot be changed
                                        </p>
                                    </div>
                                </div>
                                
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <!-- Phone Number -->
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            Phone Number
                                        </label>
                                        <input type="tel" name="phone" 
                                               class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input"
                                               value="<?php echo htmlspecialchars($customer['phone']); ?>"
                                               placeholder="+63 912 345 6789">
                                    </div>
                                    
                                    <!-- Address -->
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            Address <span class="text-red-400">*</span>
                                        </label>
                                        <input type="text" name="address" 
                                               class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input"
                                               value="<?php echo htmlspecialchars($customer['address']); ?>" 
                                               required>
                                    </div>
                                </div>
                                
                                <!-- Profile Picture Upload -->
                                <div class="mb-8">
                                    <label class="block text-gray-300 text-sm font-medium mb-3">
                                        Profile Picture
                                    </label>
                                    <div class="file-upload">
                                        <label for="profile-picture-upload" 
                                               class="file-upload-label block w-full px-4 py-4 bg-white/5 border border-gray-700 border-dashed rounded-xl text-gray-400 text-center cursor-pointer hover:bg-white/10 transition-colors">
                                            <i class="fas fa-cloud-upload-alt text-xl mb-2 block"></i>
                                            <span class="font-medium">Click to upload profile picture</span>
                                            <p class="text-sm mt-1">PNG, JPG up to 5MB</p>
                                        </label>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <button type="submit" name="update_profile" 
                                        class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold py-4 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-lg flex items-center justify-center space-x-2">
                                    <i class="fas fa-save"></i>
                                    <span>Update Profile</span>
                                </button>
                            </form>
                        </div>
                        
                        <!-- Change Password Form -->
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                            <div class="flex items-center justify-between mb-6">
                                <h2 class="text-xl font-bold text-white">Change Password</h2>
                                <div class="w-10 h-10 rounded-xl bg-purple-500/20 flex items-center justify-center">
                                    <i class="fas fa-key text-purple-400"></i>
                                </div>
                            </div>
                            
                            <?php if($password_success): ?>
                            <div class="mb-6 bg-green-900/20 border border-green-800 text-green-300 px-4 py-3 rounded-xl">
                                <div class="flex items-center">
                                    <i class="fas fa-check-circle text-green-400 mr-3"></i>
                                    <span class="text-sm"><?php echo $password_success; ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <?php if($password_error): ?>
                            <div class="mb-6 bg-red-900/20 border border-red-800 text-red-300 px-4 py-3 rounded-xl">
                                <div class="flex items-center">
                                    <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                                    <span class="text-sm"><?php echo $password_error; ?></span>
                                </div>
                            </div>
                            <?php endif; ?>
                            
                            <form method="POST" action="">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                                    <!-- Current Password -->
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            Current Password <span class="text-red-400">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="password" name="current_password" id="current-password"
                                                   class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input pr-12"
                                                   required>
                                            <button type="button" onclick="togglePassword('current-password')" 
                                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                    
                                    <!-- New Password -->
                                    <div>
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            New Password <span class="text-red-400">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="password" name="new_password" id="new-password"
                                                   class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input pr-12"
                                                   required>
                                            <button type="button" onclick="togglePassword('new-password')" 
                                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                    </div>
                                </div>
                                
                                <div class="mb-8">
                                    <!-- Confirm New Password -->
                                    <div class="max-w-md">
                                        <label class="block text-gray-300 text-sm font-medium mb-2">
                                            Confirm New Password <span class="text-red-400">*</span>
                                        </label>
                                        <div class="relative">
                                            <input type="password" name="confirm_password" id="confirm-password"
                                                   class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input pr-12"
                                                   required>
                                            <button type="button" onclick="togglePassword('confirm-password')" 
                                                    class="absolute right-3 top-3 text-gray-400 hover:text-gray-300">
                                                <i class="fas fa-eye"></i>
                                            </button>
                                        </div>
                                        <p class="text-gray-500 text-xs mt-2">
                                            <i class="fas fa-shield-alt mr-1"></i> Password must be at least 6 characters long
                                        </p>
                                    </div>
                                </div>
                                
                                <!-- Submit Button -->
                                <button type="submit" name="change_password" 
                                        class="w-full bg-gradient-to-r from-purple-500 to-pink-600 text-white font-semibold py-4 rounded-xl hover:from-purple-600 hover:to-pink-700 transition-all duration-300 hover-lift shadow-lg flex items-center justify-center space-x-2">
                                    <i class="fas fa-key"></i>
                                    <span>Change Password</span>
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 lg:hidden z-40">
        <a href="customer_dashboard.php" class="w-14 h-14 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110">
            <i class="fas fa-tachometer-alt text-white text-xl"></i>
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
        
        // Profile Picture Preview
        function previewProfilePicture(event) {
            const preview = document.getElementById('preview-image');
            const previewContainer = document.getElementById('avatar-preview');
            const file = event.target.files[0];
            
            if (file) {
                const reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                }
                
                reader.readAsDataURL(file);
            }
        }
        
        // Remove Profile Picture
        function removeProfilePicture() {
            const preview = document.getElementById('preview-image');
            const previewContainer = document.getElementById('avatar-preview');
            const fileInput = document.getElementById('profile-picture-upload');
            
            preview.src = '';
            previewContainer.classList.add('hidden');
            fileInput.value = '';
        }
        
        // Toggle Password Visibility
        function togglePassword(inputId) {
            const input = document.getElementById(inputId);
            const button = input.nextElementSibling;
            
            if (input.type === 'password') {
                input.type = 'text';
                button.innerHTML = '<i class="fas fa-eye-slash"></i>';
            } else {
                input.type = 'password';
                button.innerHTML = '<i class="fas fa-eye"></i>';
            }
        }
        
        // Form Validation
        document.addEventListener('DOMContentLoaded', function() {
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
                form.addEventListener('submit', function(e) {
                    const passwordForm = this.querySelector('[name="new_password"]');
                    const confirmPassword = this.querySelector('[name="confirm_password"]');
                    
                    if (passwordForm && confirmPassword) {
                        if (passwordForm.value !== confirmPassword.value) {
                            e.preventDefault();
                            showNotification('New passwords do not match!', 'error');
                            return false;
                        }
                        
                        if (passwordForm.value.length < 6) {
                            e.preventDefault();
                            showNotification('Password must be at least 6 characters long!', 'error');
                            return false;
                        }
                    }
                });
            });
        });
        
        // Show Notification
        function showNotification(message, type) {
            const notification = document.createElement('div');
            notification.className = `fixed top-4 right-4 z-50 px-4 py-3 rounded-xl shadow-lg ${
                type === 'success' ? 'bg-green-900/90 text-green-100 border border-green-700' :
                type === 'error' ? 'bg-red-900/90 text-red-100 border border-red-700' :
                'bg-gray-900/90 text-gray-100 border border-gray-700'
            }`;
            notification.innerHTML = `
                <div class="flex items-center space-x-3">
                    <i class="fas ${
                        type === 'success' ? 'fa-check-circle' :
                        type === 'error' ? 'fa-exclamation-circle' :
                        'fa-info-circle'
                    }"></i>
                    <span>${message}</span>
                </div>
            `;
            
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.classList.add('opacity-0', 'transition-opacity', 'duration-300');
                setTimeout(() => notification.remove(), 300);
            }, 3000);
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
    </script>
</body>
</html>