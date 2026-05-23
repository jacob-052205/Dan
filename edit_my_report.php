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
$customer_points = mysqli_fetch_assoc($points_result)['points'] ?? 0;

if (!isset($_GET['id'])) {
    redirect('my_reports.php');
}

$report_id = $_GET['id'];
$user_id = $_SESSION['user_id'];

// Fetch the report details
$query = "SELECT * FROM reports WHERE report_id = '$report_id' AND customer_id = '$user_id'";
$result = mysqli_query($conn, $query);

// Initialize $report as null first
$report = null;

if ($result && mysqli_num_rows($result) > 0) {
    $report = mysqli_fetch_assoc($result);
} else {
    // Report not found or doesn't belong to user
    header("Location: my_reports.php");
    exit();
}

// Get waste categories
$categories_query = "SELECT * FROM waste_categories";
$categories_result = mysqli_query($conn, $categories_query);

// Get selected categories
$selected_categories_query = "SELECT category_id FROM report_categories WHERE report_id = $report_id";
$selected_result = mysqli_query($conn, $selected_categories_query);
$selected_categories = [];
while($row = mysqli_fetch_assoc($selected_result)) {
    $selected_categories[] = $row['category_id'];
}

// Handle form submission
$success = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $address = sanitize($_POST['address']);
    $categories = isset($_POST['categories']) ? $_POST['categories'] : [];
    $priority = sanitize($_POST['priority']);
    
    // Handle image upload
    $image_path = $report['image_path'];
    if (isset($_FILES['image']) && $_FILES['image']['error'] == 0) {
        $upload_dir = 'uploads/reports/';
        if (!file_exists($upload_dir)) {
            mkdir($upload_dir, 0777, true);
        }
        
        // Delete old image if exists
        if ($image_path && file_exists($image_path)) {
            unlink($image_path);
        }
        
        $filename = time() . '_' . basename($_FILES['image']['name']);
        $target_path = $upload_dir . $filename;
        
        if (move_uploaded_file($_FILES['image']['tmp_name'], $target_path)) {
            $image_path = $target_path;
        }
    }
    
    // Update report
    $update_query = "UPDATE reports SET 
                     title = '$title',
                     description = '$description',
                     address = '$address',
                     image_path = '$image_path',
                     priority = '$priority',
                     updated_at = NOW()
                     WHERE report_id = $report_id AND customer_id = $customer_id";
    
    if (mysqli_query($conn, $update_query)) {
        // Update categories
        mysqli_query($conn, "DELETE FROM report_categories WHERE report_id = $report_id");
        
        foreach ($categories as $category_id) {
            $category_id = (int)$category_id;
            $category_query = "INSERT INTO report_categories (report_id, category_id) VALUES ($report_id, $category_id)";
            mysqli_query($conn, $category_query);
        }
        
        $success = "Report updated successfully!";
        // Refresh report data
        $result = mysqli_query($conn, $query);
        $report = mysqli_fetch_assoc($result);
        
        // Refresh selected categories
        $selected_result = mysqli_query($conn, $selected_categories_query);
        $selected_categories = [];
        while($row = mysqli_fetch_assoc($selected_result)) {
            $selected_categories[] = $row['category_id'];
        }
    } else {
        $error = "Error updating report: " . mysqli_error($conn);
    }
}

// Status colors
$status_colors = [
    'pending' => ['bg' => 'bg-yellow-500/20', 'text' => 'text-yellow-400', 'border' => 'border-yellow-500/30'],
    'in_progress' => ['bg' => 'bg-blue-500/20', 'text' => 'text-blue-400', 'border' => 'border-blue-500/30'],
    'completed' => ['bg' => 'bg-green-500/20', 'text' => 'text-green-400', 'border' => 'border-green-500/30'],
    'rejected' => ['bg' => 'bg-red-500/20', 'text' => 'text-red-400', 'border' => 'border-red-500/30']
];
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Report - WasteWise</title>
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
        
        /* Category checkbox styling */
        .category-checkbox:checked + .category-label {
            border-color: #4ade80;
            background: rgba(74, 222, 128, 0.1);
            transform: scale(1.02);
        }
        
        .category-label {
            transition: all 0.3s ease;
        }
        
        .category-label:hover {
            border-color: #4ade80;
            background: rgba(255, 255, 255, 0.05);
        }
        
        /* Image preview */
        .image-preview-container {
            transition: all 0.3s ease;
        }
        
        .image-preview-container:hover {
            transform: scale(1.02);
        }
        
        /* Form focus styles */
        .form-input:focus {
            border-color: #4ade80;
            ring-color: rgba(74, 222, 128, 0.2);
        }
        
        /* File upload styling */
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
        
        /* Status animation */
        .status-badge {
            animation: statusPulse 2s infinite;
        }
        
        @keyframes statusPulse {
            0%, 100% { opacity: 1; }
            50% { opacity: 0.7; }
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
                        <h1 class="text-2xl font-bold text-white">Edit Report</h1>
                        <p class="text-gray-300 text-sm">Update your report details and information</p>
                    </div>
                    
                    <!-- Header Actions -->
                    <div class="flex items-center space-x-4">
                        <!-- View Report Button -->
                        <a href="view_my_report.php?id=<?php echo $report_id; ?>" 
                           class="bg-gradient-to-r from-blue-500 to-purple-600 text-white px-4 py-2 rounded-lg hover:from-blue-600 hover:to-purple-700 transition-all duration-300 hover-lift shadow-md">
                            <i class="fas fa-eye mr-2"></i>
                            View Report
                        </a>
                        
                        <!-- User Profile -->
                        <div class="hidden lg:flex items-center space-x-3">
                            <div class="text-right">
                                <div class="font-semibold text-white"><?php echo $_SESSION['user_name']; ?></div>
                                <div class="text-gray-300 text-sm">Editor</div>
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
                    <h1 class="text-2xl font-bold text-white">Edit Report</h1>
                    <p class="text-gray-300 text-sm">Update your report details and information</p>
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

                <!-- Report Summary -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex flex-col lg:flex-row lg:items-center justify-between gap-4 mb-4">
                        <div>
                            <h2 class="text-xl font-bold text-white mb-2">Editing Report</h2>
                            <div class="flex items-center space-x-4">
                                <div class="text-gray-300">
                                    <i class="fas fa-hashtag text-blue-400 mr-1"></i>
                                    <span class="font-mono">#<?php echo $report['report_id']; ?></span>
                                </div>
                                <div class="px-3 py-1 rounded-full <?php echo $status_colors[$report['status']]['bg']; ?> <?php echo $status_colors[$report['status']]['text']; ?> border <?php echo $status_colors[$report['status']]['border']; ?> text-sm font-medium status-badge">
                                    <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                </div>
                            </div>
                        </div>
                        
                        <div class="text-gray-300 text-sm">
                            <i class="fas fa-calendar-alt mr-1"></i>
                            Last updated: <?php echo date('M d, Y', strtotime($report['updated_at'])); ?>
                        </div>
                    </div>
                    
                    <div class="text-gray-300 text-sm">
                        <i class="fas fa-info-circle text-blue-400 mr-1"></i>
                        You can edit most details of this report while it's still pending.
                    </div>
                </div>

                <!-- Edit Form -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h2 class="text-xl font-bold text-white">Edit Report Details</h2>
                        <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                            <i class="fas fa-edit text-blue-400"></i>
                        </div>
                    </div>
                    
                    <form method="POST" action="" enctype="multipart/form-data" class="space-y-6">
                        <!-- Title -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">
                                Title <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="title" 
                                   class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input"
                                   value="<?php echo htmlspecialchars($report['title']); ?>" 
                                   required>
                        </div>
                        
                        <!-- Description -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">
                                Description <span class="text-red-400">*</span>
                            </label>
                            <textarea name="description" 
                                      class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input min-h-[120px]"
                                      required><?php echo htmlspecialchars($report['description']); ?></textarea>
                        </div>
                        
                        <!-- Address -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">
                                Address <span class="text-red-400">*</span>
                            </label>
                            <input type="text" name="address" 
                                   class="w-full px-4 py-3 bg-white/5 border border-gray-700 rounded-xl text-white placeholder-gray-400 focus:outline-none focus:border-green-500 focus:ring-1 focus:ring-green-500 transition-colors form-input"
                                   value="<?php echo htmlspecialchars($report['address']); ?>" 
                                   required>
                        </div>
                        
                        <!-- Waste Categories -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-3">
                                Waste Categories
                            </label>
                            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
                                <?php 
                                mysqli_data_seek($categories_result, 0); // Reset pointer
                                while($category = mysqli_fetch_assoc($categories_result)): 
                                    $isChecked = in_array($category['category_id'], $selected_categories);
                                ?>
                                <div class="relative">
                                    <input type="checkbox" 
                                           id="category_<?php echo $category['category_id']; ?>" 
                                           name="categories[]" 
                                           value="<?php echo $category['category_id']; ?>" 
                                           class="category-checkbox sr-only peer"
                                           <?php echo $isChecked ? 'checked' : ''; ?>>
                                    <label for="category_<?php echo $category['category_id']; ?>" 
                                           class="category-label block p-4 border border-gray-700 rounded-xl cursor-pointer hover:border-green-500 transition-colors peer-checked:border-green-500">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-10 h-10 rounded-lg bg-purple-500/20 flex items-center justify-center">
                                                <i class="<?php echo $category['icon_class']; ?> text-purple-400"></i>
                                            </div>
                                            <span class="text-white font-medium"><?php echo htmlspecialchars($category['category_name']); ?></span>
                                        </div>
                                    </label>
                                </div>
                                <?php endwhile; ?>
                            </div>
                        </div>
                        
                        <!-- Priority Level -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-2">Priority Level</label>
                            <div class="grid grid-cols-1 md:grid-cols-3 gap-3">
                                <div class="relative">
                                    <input type="radio" id="priority_low" name="priority" value="low" 
                                           class="sr-only peer" <?php echo $report['priority'] == 'low' ? 'checked' : ''; ?>>
                                    <label for="priority_low" 
                                           class="block p-4 border border-gray-700 rounded-xl cursor-pointer hover:border-green-500 transition-colors peer-checked:border-green-500 peer-checked:bg-green-500/10">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-lg bg-green-500/20 flex items-center justify-center">
                                                <i class="fas fa-arrow-down text-green-400"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-white">Low</div>
                                                <div class="text-gray-400 text-sm">Minor issue</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="relative">
                                    <input type="radio" id="priority_medium" name="priority" value="medium" 
                                           class="sr-only peer" <?php echo $report['priority'] == 'medium' ? 'checked' : ''; ?>>
                                    <label for="priority_medium" 
                                           class="block p-4 border border-gray-700 rounded-xl cursor-pointer hover:border-green-500 transition-colors peer-checked:border-green-500 peer-checked:bg-yellow-500/10">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-lg bg-yellow-500/20 flex items-center justify-center">
                                                <i class="fas fa-equals text-yellow-400"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-white">Medium</div>
                                                <div class="text-gray-400 text-sm">Regular issue</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                                
                                <div class="relative">
                                    <input type="radio" id="priority_high" name="priority" value="high" 
                                           class="sr-only peer" <?php echo $report['priority'] == 'high' ? 'checked' : ''; ?>>
                                    <label for="priority_high" 
                                           class="block p-4 border border-gray-700 rounded-xl cursor-pointer hover:border-green-500 transition-colors peer-checked:border-green-500 peer-checked:bg-red-500/10">
                                        <div class="flex items-center space-x-3">
                                            <div class="w-8 h-8 rounded-lg bg-red-500/20 flex items-center justify-center">
                                                <i class="fas fa-arrow-up text-red-400"></i>
                                            </div>
                                            <div>
                                                <div class="font-medium text-white">High</div>
                                                <div class="text-gray-400 text-sm">Urgent issue</div>
                                            </div>
                                        </div>
                                    </label>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Current Image -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-3">Current Image</label>
                            <?php if($report['image_path'] && file_exists($report['image_path'])): ?>
                            <div class="mb-4">
                                <div class="image-preview-container max-w-md rounded-xl overflow-hidden border-2 border-gray-700">
                                    <img src="<?php echo $report['image_path']; ?>" 
                                         alt="Current Report Image" 
                                         class="w-full h-48 object-cover">
                                </div>
                                <p class="text-gray-300 text-sm mt-2">
                                    <i class="fas fa-info-circle text-blue-400 mr-1"></i>
                                    Keep current image or upload a new one below
                                </p>
                            </div>
                            <?php else: ?>
                            <div class="mb-4 p-4 border border-gray-700 rounded-xl">
                                <div class="flex items-center text-gray-400">
                                    <i class="fas fa-image text-xl mr-3"></i>
                                    <span>No image currently attached to this report</span>
                                </div>
                            </div>
                            <?php endif; ?>
                        </div>
                        
                        <!-- New Image Upload -->
                        <div>
                            <label class="block text-gray-300 text-sm font-medium mb-3">Upload New Image (Optional)</label>
                            <div class="file-upload">
                                <label for="image-upload" 
                                       class="file-upload-label block w-full px-4 py-4 bg-white/5 border border-gray-700 border-dashed rounded-xl text-gray-400 text-center cursor-pointer hover:bg-white/10 transition-colors">
                                    <i class="fas fa-cloud-upload-alt text-xl mb-2 block"></i>
                                    <span class="font-medium">Click to upload new image</span>
                                    <p class="text-sm mt-1">PNG, JPG up to 5MB</p>
                                </label>
                                <input type="file" id="image-upload" name="image" accept="image/*" class="hidden" onchange="previewNewImage(event)">
                            </div>
                            
                            <!-- New Image Preview -->
                            <div id="new-image-preview" class="hidden mt-4">
                                <div class="flex items-center space-x-4 p-4 border border-green-500/30 rounded-xl bg-green-500/10">
                                    <div class="w-20 h-20 rounded-lg overflow-hidden border-2 border-green-500">
                                        <img id="preview-new-image" class="w-full h-full object-cover">
                                    </div>
                                    <div>
                                        <p class="text-green-400 text-sm font-medium">New image selected</p>
                                        <button type="button" onclick="removeNewImage()" class="text-red-400 text-sm hover:text-red-300 mt-1">
                                            <i class="fas fa-times mr-1"></i> Remove
                                        </button>
                                    </div>
                                </div>
                            </div>
                        </div>
                        
                        <!-- Form Actions -->
                        <div class="flex flex-col sm:flex-row gap-4 pt-6 border-t border-gray-700">
                            <a href="view_my_report.php?id=<?php echo $report_id; ?>" 
                               class="px-6 py-3 bg-gradient-to-r from-red-500 to-pink-600 text-white rounded-xl hover:from-red-600 hover:to-pink-700 transition-all duration-300 hover-lift flex items-center justify-center space-x-2 text-center">
                                <i class="fas fa-times"></i>
                                <span>Cancel</span>
                            </a>
                            
                            <button type="submit" 
                                    class="flex-1 px-6 py-3 bg-gradient-to-r from-green-500 to-emerald-600 text-white rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift flex items-center justify-center space-x-2">
                                <i class="fas fa-save"></i>
                                <span>Save Changes</span>
                            </button>
                        </div>
                    </form>
                </div>
                
                <!-- Editing Guidelines -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mt-6 fade-in-up">
                    <div class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-xl bg-blue-500/20 flex items-center justify-center">
                            <i class="fas fa-lightbulb text-blue-400"></i>
                        </div>
                        <div>
                            <h3 class="text-lg font-bold text-white">Editing Guidelines</h3>
                            <p class="text-gray-300 text-sm">Tips for updating your report</p>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-400 mt-1"></i>
                            <div>
                                <div class="text-white font-medium">Be Specific</div>
                                <p class="text-gray-300 text-sm">Provide clear, detailed descriptions of the waste issue</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-400 mt-1"></i>
                            <div>
                                <div class="text-white font-medium">Accurate Location</div>
                                <p class="text-gray-300 text-sm">Ensure the address is precise for efficient collection</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-400 mt-1"></i>
                            <div>
                                <div class="text-white font-medium">Proper Category</div>
                                <p class="text-gray-300 text-sm">Select all relevant waste categories for better processing</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-3">
                            <i class="fas fa-check-circle text-green-400 mt-1"></i>
                            <div>
                                <div class="text-white font-medium">Clear Images</div>
                                <p class="text-gray-300 text-sm">Upload clear photos showing the waste situation</p>
                            </div>
                        </div>
                    </div>
                </div>
            </main>
        </div>
    </div>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 lg:hidden z-40">
        <a href="view_my_report.php?id=<?php echo $report_id; ?>" class="w-14 h-14 bg-gradient-to-r from-blue-500 to-purple-600 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110">
            <i class="fas fa-eye text-white text-xl"></i>
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
        
        // New Image Preview
        function previewNewImage(event) {
            const preview = document.getElementById('preview-new-image');
            const previewContainer = document.getElementById('new-image-preview');
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
        
        // Remove New Image
        function removeNewImage() {
            const preview = document.getElementById('preview-new-image');
            const previewContainer = document.getElementById('new-image-preview');
            const fileInput = document.getElementById('image-upload');
            
            preview.src = '';
            previewContainer.classList.add('hidden');
            fileInput.value = '';
        }
        
        // Form Validation
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.querySelector('form');
            form.addEventListener('submit', function(e) {
                // Validate required fields
                const title = this.querySelector('[name="title"]');
                const description = this.querySelector('[name="description"]');
                const address = this.querySelector('[name="address"]');
                
                if (!title.value.trim() || !description.value.trim() || !address.value.trim()) {
                    e.preventDefault();
                    showNotification('Please fill in all required fields!', 'error');
                    return false;
                }
                
                // Show loading state
                const submitButton = this.querySelector('button[type="submit"]');
                if (submitButton) {
                    submitButton.innerHTML = '<i class="fas fa-spinner fa-spin mr-2"></i>Saving...';
                    submitButton.disabled = true;
                }
            });
            
            // Add character counters
            const textareas = document.querySelectorAll('textarea');
            textareas.forEach(textarea => {
                const counter = document.createElement('div');
                counter.className = 'text-gray-400 text-xs mt-1 text-right';
                counter.textContent = `${textarea.value.length}/5000 characters`;
                textarea.parentNode.appendChild(counter);
                
                textarea.addEventListener('input', function() {
                    counter.textContent = `${this.value.length}/5000 characters`;
                    
                    if (this.value.length > 5000) {
                        counter.classList.add('text-red-400');
                    } else {
                        counter.classList.remove('text-red-400');
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
        
        // Status badge animation
        const statusBadge = document.querySelector('.status-badge');
        if (statusBadge) {
            setInterval(() => {
                statusBadge.classList.toggle('opacity-70');
            }, 2000);
        }
        
        // Intersection Observer for fade-in animations
        const observerOptions = {
            threshold: 0.1,
            rootMargin: '0px 0px -50px 0px'
        };
        
        const observer = new IntersectionObserver((entries) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    entry.target.classList.add('animate-fade-in');
                }
            });
        }, observerOptions);
        
        // Observe sections for fade-in
        document.querySelectorAll('.fade-in-up').forEach(section => {
            observer.observe(section);
        });
    </script>
</body>
</html>