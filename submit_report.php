<?php
require_once 'config.php';

// Check if customer is logged in
if (!isCustomer()) {
    redirect('login_customer.php');
}

$conn = getDBConnection();
$customer_id = $_SESSION['user_id'];

// Add this code to get customer profile picture (same as dashboard)
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

// Get waste categories
$categories_query = "SELECT * FROM waste_categories";
$categories_result = mysqli_query($conn, $categories_query);

// Initialize success/error messages
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $report_type = sanitize($_POST['report_type']);
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $address = sanitize($_POST['address']);
    $latitude = isset($_POST['latitude']) ? (float)$_POST['latitude'] : 0.0;
    $longitude = isset($_POST['longitude']) ? (float)$_POST['longitude'] : 0.0;
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $priority = sanitize($_POST['priority']);
    
    // Handle image upload
    $image_path = '';
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/reports/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }
    
    // Insert report with coordinates
    $query = "INSERT INTO reports (customer_id, report_type, title, description, address, latitude, longitude, image_path, priority) 
              VALUES ($customer_id, '$report_type', '$title', '$description', '$address', '$latitude', '$longitude', '$image_path', '$priority')";
    
    if (mysqli_query($conn, $query)) {
        $report_id = mysqli_insert_id($conn);
        
        // Insert categories
        foreach ($categories as $category_id) {
            $category_id = (int)$category_id;
            $category_query = "INSERT INTO report_categories (report_id, category_id) VALUES ($report_id, $category_id)";
            mysqli_query($conn, $category_query);
        }
        
        // Create notification for admin
        $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                              VALUES (1, 'admin', 'New Report Submitted', 
                              'A new report \"$title\" has been submitted by {$_SESSION['user_name']}', 'new_report')";
        mysqli_query($conn, $notification_query);
        
        // Award points to customer
        $points_query = "UPDATE customer SET points = points + 10 WHERE customer_id = $customer_id";
        mysqli_query($conn, $points_query);
        
        $success = "Report submitted successfully! You earned 10 points.";
    } else {
        $error = "Error submitting report: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Submit Report - WasteWise</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
    <link rel="stylesheet" href="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.css" />
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
        
        .category-checkbox:checked + .category-label {
            border-color: #4ade80;
            background: rgba(74, 222, 128, 0.1);
        }
        
        .form-radio:checked {
            background-color: #4ade80;
            border-color: #4ade80;
        }
        
        .form-radio:checked ~ span {
            color: #4ade80;
        }
        
        #map {
            z-index: 10;
        }
        
        .leaflet-control-geocoder-form input {
            background: rgba(0, 0, 0, 0.8) !important;
            color: white !important;
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
        }
        
        .leaflet-popup-content {
            color: #333 !important;
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
            <a href="submit_report.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active">
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
                    <a href="submit_report.php" class="flex items-center space-x-3 text-white p-3 rounded-lg menu-item active hover:bg-white/10 transition-colors">
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
                        <h1 class="text-2xl font-bold text-white">Submit Report</h1>
                        <p class="text-gray-300 text-sm">Report uncollected waste or request pickup from your location</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- Dashboard Button -->
                        <a href="customer_dashboard.php" 
                           class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-tachometer-alt mr-2"></i>
                            Dashboard
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
                    <h1 class="text-2xl font-bold text-white">Submit Report</h1>
                    <p class="text-gray-300 text-sm">Report uncollected waste or request pickup from your location</p>
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
                
                <!-- Form Container -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up" style="animation-delay: 0.1s">
                    <form method="POST" action="" enctype="multipart/form-data" onsubmit="return validateForm()">
                        <!-- Report Type -->
                        <div class="mb-8">
                            <label class="block text-white font-medium mb-3">
                                Report Type <span class="text-red-400">*</span>
                            </label>
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div class="relative">
                                    <input type="radio" id="uncollected" name="report_type" value="uncollected" required 
                                           class="form-radio sr-only peer">
                                    <label for="uncollected" class="block p-5 glass-card rounded-xl cursor-pointer hover:bg-white/5 transition-colors peer-checked:border-green-500 peer-checked:border-2">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 rounded-lg bg-blue-500/20 flex items-center justify-center">
                                                <i class="fas fa-trash text-blue-400 text-xl"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-white">Uncollected Waste Report</h4>
                                                <p class="text-gray-300 text-sm mt-1">Report waste that hasn't been collected</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="relative">
                                    <input type="radio" id="pickup_request" name="report_type" value="pickup_request" required 
                                           class="form-radio sr-only peer">
                                    <label for="pickup_request" class="block p-5 glass-card rounded-xl cursor-pointer hover:bg-white/5 transition-colors peer-checked:border-green-500 peer-checked:border-2">
                                        <div class="flex items-center space-x-4">
                                            <div class="w-12 h-12 rounded-lg bg-green-500/20 flex items-center justify-center">
                                                <i class="fas fa-truck text-green-400 text-xl"></i>
                                            </div>
                                            <div>
                                                <h4 class="font-semibold text-white">Pickup Request</h4>
                                                <p class="text-gray-300 text-sm mt-1">Request waste pickup from your location</p>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Title -->
                        <div class="mb-6">
                            <label class="block text-white font-medium mb-2">
                                Title <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="title" 
                                   class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                                   placeholder="Brief title of the issue" required>
                        </div>
                        
                        <!-- Description -->
                        <div class="mb-6">
                            <label class="block text-white font-medium mb-2">
                                Description <span class="text-red-400">*</span>
                            </label>
                            <textarea name="description" 
                                      class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors min-h-[120px]"
                                      placeholder="Describe the issue in detail (location, type of waste, etc.)" 
                                      required></textarea>
                        </div>
                        
                        <!-- Address Selection with Map -->
                        <div class="mb-6">
                            <label class="block text-white font-medium mb-2">
                                Location <span class="text-red-400">*</span>
                            </label>
                            
                            <!-- Map Container -->
                            <div id="map" class="w-full h-64 rounded-xl mb-4 border-2 border-gray-700"></div>
                            
                            <!-- Address Inputs -->
                            <div class="space-y-4">
                                <input type="text" id="address" name="address" 
                                       class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                                       placeholder="Selected address will appear here" 
                                       required readonly>
                                
                                <!-- Hidden coordinates for tracking -->
                                <input type="hidden" id="latitude" name="latitude">
                                <input type="hidden" id="longitude" name="longitude">
                                
                                <!-- Search Box -->
                                <div class="relative">
                                    <input type="text" id="searchBox" 
                                           class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors"
                                           placeholder="Search for location or enter address">
                                    <div class="absolute right-3 top-3">
                                        <button type="button" onclick="useCurrentLocation()" 
                                                class="px-4 py-2 bg-blue-500 hover:bg-blue-600 text-white rounded-lg text-sm transition-colors">
                                            <i class="fas fa-location-crosshairs mr-1"></i> My Location
                                        </button>
                                    </div>
                                </div>
                                
                                <!-- Selected Location Preview -->
                                <div id="locationPreview" class="p-4 glass-card rounded-xl <?php echo isset($_POST['latitude']) ? '' : 'hidden'; ?>">
                                    <div class="flex items-start space-x-3">
                                        <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center flex-shrink-0">
                                            <i class="fas fa-map-pin text-green-400"></i>
                                        </div>
                                        <div>
                                            <h4 class="font-semibold text-white">Selected Location</h4>
                                            <p id="selectedAddress" class="text-gray-300 text-sm mt-1">
                                                <?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?>
                                            </p>
                                            <div class="flex items-center text-gray-400 text-xs mt-2">
                                                <i class="fas fa-globe mr-1"></i>
                                                <span>Lat: <span id="latValue"><?php echo isset($_POST['latitude']) ? $_POST['latitude'] : '0.0000'; ?></span>, Lng: <span id="lngValue"><?php echo isset($_POST['longitude']) ? $_POST['longitude'] : '0.0000'; ?></span></span>
                                            </div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Waste Categories -->
                        <div class="mb-6">
                            <label class="block text-white font-medium mb-3">Waste Categories</label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php 
                                mysqli_data_seek($categories_result, 0);
                                while($category = mysqli_fetch_assoc($categories_result)): 
                                ?>
                                <div class="relative">
                                    <input type="checkbox" id="category_<?php echo $category['category_id']; ?>" 
                                           name="categories[]" value="<?php echo $category['category_id']; ?>" 
                                           class="category-checkbox sr-only peer">
                                    <label for="category_<?php echo $category['category_id']; ?>" 
                                           class="category-label block p-4 border border-gray-700 rounded-xl cursor-pointer hover:border-green-500 transition-colors peer-checked:border-green-500">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                                                <i class="<?php echo htmlspecialchars($category['icon_class']); ?> text-purple-400"></i>
                                            </div>
                                            <span class="text-white font-medium"><?php echo htmlspecialchars($category['category_name']); ?></span>
                                        </div>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <!-- Priority Level -->
                        <div class="mb-6">
                            <label class="block text-white font-medium mb-2">Priority Level</label>
                            <select name="priority" 
                                    class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors">
                                <option value="low" class="bg-gray-800">Low - Minor issue</option>
                                <option value="medium" selected class="bg-gray-800">Medium - Regular issue</option>
                                <option value="high" class="bg-gray-800">High - Urgent issue</option>
                            </select>
                        </div>
                        
                        <!-- Upload Photo -->
                        <div class="mb-8">
                            <label class="block text-white font-medium mb-3">Upload Photo (Optional)</label>
                            <div class="space-y-4">
                                <input type="file" name="image" 
                                       class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors file:mr-4 file:py-2 file:px-4 file:rounded-full file:border-0 file:text-sm file:font-semibold file:bg-green-500 file:text-white hover:file:bg-green-600"
                                       accept="image/*" 
                                       onchange="previewImage(event)">
                                <div class="image-preview hidden" id="imagePreview">
                                    <div class="relative w-48 h-48 rounded-xl overflow-hidden border-2 border-green-500">
                                        <img id="previewImage" class="w-full h-full object-cover">
                                        <button type="button" onclick="removeImage()" 
                                                class="absolute top-2 right-2 w-8 h-8 bg-red-500/80 text-white rounded-full flex items-center justify-center hover:bg-red-600 transition-colors">
                                            <i class="fas fa-times"></i>
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Submit Button -->
                        <button type="submit" 
                                class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold py-4 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift shadow-lg flex items-center justify-center space-x-2">
                            <i class="fas fa-paper-plane"></i>
                            <span>Submit Report</span>
                        </button>
                        
                        <!-- Points Notice -->
                        <div class="mt-6 text-center text-gray-300 text-sm">
                            <i class="fas fa-gift text-green-400 mr-2"></i>
                            You will earn <span class="text-green-400 font-semibold">10 points</span> for submitting this report!
                        </div>
                    </form>
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

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    <script src="https://unpkg.com/leaflet-control-geocoder/dist/Control.Geocoder.js"></script>
    
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
        
        document.addEventListener('click', function(event) {
            const mobileMenu = document.querySelector('.mobile-menu');
            const menuButton = document.querySelector('[onclick="openMobileMenu()"]');
            
            if (mobileMenu && mobileMenu.classList.contains('active') && 
                !mobileMenu.contains(event.target) && 
                menuButton && !menuButton.contains(event.target)) {
                closeMobileMenu();
            }
        });
        
        // Image Preview Function
        function previewImage(event) {
            var preview = document.getElementById('previewImage');
            var previewContainer = document.getElementById('imagePreview');
            var file = event.target.files[0];
            
            if (file) {
                var reader = new FileReader();
                
                reader.onload = function(e) {
                    preview.src = e.target.result;
                    previewContainer.classList.remove('hidden');
                }
                
                reader.readAsDataURL(file);
            } else {
                preview.src = '';
                previewContainer.classList.add('hidden');
            }
        }
        
        // Remove Image Function
        function removeImage() {
            var preview = document.getElementById('previewImage');
            var previewContainer = document.getElementById('imagePreview');
            var fileInput = document.querySelector('input[name="image"]');
            
            preview.src = '';
            previewContainer.classList.add('hidden');
            fileInput.value = '';
        }
        
        // Map Initialization
        let map;
        let marker;
        let selectedLat = 0;
        let selectedLng = 0;
        
        // Initialize map when page loads
        document.addEventListener('DOMContentLoaded', function() {
            initMap();
            
            // Set initial coordinates if they exist from form submission
            <?php if(isset($_POST['latitude']) && isset($_POST['longitude'])): ?>
            selectedLat = <?php echo (float)$_POST['latitude']; ?>;
            selectedLng = <?php echo (float)$_POST['longitude']; ?>;
            
            // Update map and marker
            if (map && marker) {
                map.setView([selectedLat, selectedLng], 16);
                marker.setLatLng([selectedLat, selectedLng]);
                updateMarker(selectedLat, selectedLng);
            }
            <?php endif; ?>
        });
        
        function initMap() {
            // Default to Philippines center
            map = L.map('map').setView([14.5995, 120.9842], 13);
            
            // Add OpenStreetMap tiles
            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
            }).addTo(map);
            
            // Add geocoder control
            const geocoder = L.Control.geocoder({
                defaultMarkGeocode: false,
                placeholder: 'Search location...'
            }).on('markgeocode', function(e) {
                const center = e.geocode.center;
                map.setView(center, 16);
                updateMarker(center.lat, center.lng);
                updateAddress(e.geocode.name);
            }).addTo(map);
            
            // Add click event to map
            map.on('click', function(e) {
                updateMarker(e.latlng.lat, e.latlng.lng);
                reverseGeocode(e.latlng.lat, e.latlng.lng);
            });
            
            // Initial marker
            marker = L.marker([14.5995, 120.9842], {
                draggable: true,
                icon: L.divIcon({
                    html: '<div class="w-10 h-10 rounded-full bg-green-500 border-2 border-white flex items-center justify-center shadow-lg"><i class="fas fa-map-pin text-white"></i></div>',
                    className: 'custom-marker',
                    iconSize: [40, 40],
                    iconAnchor: [20, 40]
                })
            }).addTo(map);
            
            // Marker drag event
            marker.on('dragend', function(e) {
                const position = marker.getLatLng();
                updateMarker(position.lat, position.lng);
                reverseGeocode(position.lat, position.lng);
            });
            
            // Update form with initial values
            document.getElementById('latitude').value = 14.5995;
            document.getElementById('longitude').value = 120.9842;
            selectedLat = 14.5995;
            selectedLng = 120.9842;
        }
        
        function updateMarker(lat, lng) {
            selectedLat = lat;
            selectedLng = lng;
            
            // Update marker position
            marker.setLatLng([lat, lng]);
            
            // Update hidden inputs
            document.getElementById('latitude').value = lat;
            document.getElementById('longitude').value = lng;
            
            // Update preview display
            document.getElementById('latValue').textContent = lat.toFixed(6);
            document.getElementById('lngValue').textContent = lng.toFixed(6);
            
            // Show location preview
            document.getElementById('locationPreview').classList.remove('hidden');
        }
        
        function updateAddress(address) {
            document.getElementById('address').value = address;
            document.getElementById('selectedAddress').textContent = address;
        }
        
        function reverseGeocode(lat, lng) {
            // Show loading
            document.getElementById('selectedAddress').textContent = 'Getting address...';
            
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data.display_name) {
                        updateAddress(data.display_name);
                    } else {
                        updateAddress(`Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                    }
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    updateAddress(`Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`);
                });
        }
        
        function useCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(
                    function(position) {
                        const lat = position.coords.latitude;
                        const lng = position.coords.longitude;
                        map.setView([lat, lng], 16);
                        updateMarker(lat, lng);
                        reverseGeocode(lat, lng);
                    },
                    function(error) {
                        alert('Unable to get your location. Please allow location access or select manually.');
                    }
                );
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }
        
        // Search functionality
        const searchBox = document.getElementById('searchBox');
        let searchTimeout;
        
        searchBox.addEventListener('input', function(e) {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(function() {
                if (searchBox.value.trim().length > 2) {
                    searchLocation(searchBox.value);
                }
            }, 500);
        });
        
        function searchLocation(query) {
            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=5`)
                .then(response => response.json())
                .then(data => {
                    if (data && data.length > 0) {
                        const result = data[0];
                        map.setView([result.lat, result.lon], 16);
                        updateMarker(parseFloat(result.lat), parseFloat(result.lon));
                        updateAddress(result.display_name);
                    }
                })
                .catch(error => {
                    console.error('Search error:', error);
                });
        }
        
        function validateForm() {
            const address = document.getElementById('address').value;
            const latitude = document.getElementById('latitude').value;
            const longitude = document.getElementById('longitude').value;
            
            if (!address || address.trim() === '') {
                alert('Please select a location on the map.');
                return false;
            }
            
            if (!latitude || !longitude || latitude == 0 || longitude == 0) {
                alert('Please select a valid location on the map.');
                return false;
            }
            
            return true;
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