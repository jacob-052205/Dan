<?php
require_once 'config.php';

$errors = [];
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $full_name = sanitize($_POST['full_name']);
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    $confirm_password = sanitize($_POST['confirm_password']);
    $address = sanitize($_POST['address']);
    $phone = sanitize($_POST['phone']);
    
    if ($password !== $confirm_password) {
        $errors[] = "Passwords do not match!";
    }
    
    if (strlen($password) < 6) {
        $errors[] = "Password must be at least 6 characters!";
    }
    
    $conn = getDBConnection();
    
    // Check if email exists
    $check_email = "SELECT * FROM customer WHERE customer_email = '$email'";
    $result = mysqli_query($conn, $check_email);
    
    if (mysqli_num_rows($result) > 0) {
        $errors[] = "Email already registered!";
    }
    
    if (empty($errors)) {
        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
        
        $query = "INSERT INTO customer (customer_email, password, full_name, address, phone) 
                  VALUES ('$email', '$hashed_password', '$full_name', '$address', '$phone')";
        
        if (mysqli_query($conn, $query)) {
            $success = "Registration successful! You can now login.";
            // Clear form data on success
            $_POST = array();
        } else {
            $errors[] = "Registration failed. Please try again.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Register - WasteWise</title>
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
        
        .register-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.85)), url('https://media.licdn.com/dms/image/v2/D4E12AQHclfeT7LmcfQ/article-cover_image-shrink_720_1280/article-cover_image-shrink_720_1280/0/1719988994510?e=2147483647&v=beta&t=74SoGzgjkc5M0QlK6kDsskfcy0ID8hIC6AxalcWtps4');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            position: relative;
            min-height: 100vh;
        }
        
        /* Remove blur on mobile for better performance */
        @media (max-width: 768px) {
            .register-bg::before {
                display: none;
            }
            .register-bg {
                background-attachment: scroll;
            }
        }
        
        @media (min-width: 769px) {
            .register-bg::before {
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
        
        /* Success animation */
        .success-animation {
            animation: successPulse 0.5s ease-out;
        }
        
        @keyframes successPulse {
            0% { transform: scale(0.8); opacity: 0; }
            70% { transform: scale(1.1); }
            100% { transform: scale(1); opacity: 1; }
        }
        
        /* Mobile Navigation */
        .mobile-nav {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-nav.active {
            transform: translateX(0);
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
        
        /* Input styles */
        .input-focus:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        
        /* Password strength indicator */
        .password-strength {
            height: 4px;
            transition: all 0.3s ease;
        }
        
        .strength-weak { background: #ef4444; width: 25%; }
        .strength-fair { background: #f59e0b; width: 50%; }
        .strength-good { background: #10b981; width: 75%; }
        .strength-strong { background: #10b981; width: 100%; }
    </style>
</head>
<body class="bg-gray-50">
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
        
        <nav class="space-y-6">
            <a href="index.php" class="block text-white text-lg py-3 hover:text-green-400 transition-colors border-b border-gray-700">
                <i class="fas fa-home mr-3"></i> Home
            </a>
            <a href="login_customer.php" class="block text-white text-lg py-3 hover:text-green-400 transition-colors border-b border-gray-700">
                <i class="fas fa-sign-in-alt mr-3"></i> Login
            </a>
            <a href="forgot.php" class="block text-white text-lg py-3 hover:text-green-400 transition-colors border-b border-gray-700">
                <i class="fas fa-key mr-3"></i> Forgot Password
            </a>
            <a href="index.php" class="block bg-gradient-to-r from-green-500 to-blue-500 text-white text-lg py-4 px-6 rounded-xl font-semibold text-center mt-8 hover:shadow-lg transition-all">
                <i class="fas fa-flag mr-2"></i> Report Issue
            </a>
        </nav>
        
        <div class="absolute bottom-6 left-6 right-6">
            <div class="text-gray-400 text-sm">
                <p class="mb-2">Need help?</p>
                <a href="tel:+15551234567" class="text-green-400">
                    <i class="fas fa-phone mr-2"></i> +1 (555) 123-4567
                </a>
            </div>
        </div>
    </div>

    <!-- Hero Section with Background -->
    <div class="register-bg">
        <div class="container mx-auto px-4 py-4 md:py-8 safe-area-top">
            <!-- Navigation -->
            <nav class="flex justify-between items-center py-4 md:py-6">
                <a href="index.php" class="flex items-center space-x-3">
                    <div class="w-10 h-10 md:w-12 md:h-12 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                        <i class="fas fa-recycle text-white text-xl md:text-2xl"></i>
                    </div>
                    <span class="text-white text-xl md:text-2xl font-bold">Waste<span class="text-green-400">Wise</span></span>
                </a>
                
                <!-- Desktop Navigation -->
                <div class="hidden md:flex space-x-8">
                    <a href="index.php" class="text-white hover:text-green-400 transition-colors duration-300 font-medium">Home</a>
                    <a href="login_customer.php" class="text-white hover:text-green-400 transition-colors duration-300 font-medium">Login</a>
                    <a href="forgot.php" class="text-white hover:text-green-400 transition-colors duration-300 font-medium">Forgot Password</a>
                    <a href="index.php" class="bg-gradient-to-r from-green-400 to-blue-500 text-white px-6 py-2 rounded-full hover:from-green-500 hover:to-blue-600 transition-all duration-300 font-medium pulse-animation">
                        Report Issue
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button onclick="openMobileNav()" class="md:hidden text-white text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>

            <!-- Register Content -->
            <div class="flex flex-col lg:flex-row items-center justify-between min-h-[calc(100vh-120px)] py-8 md:py-12">
                <!-- Left Column - Information -->
                <div class="lg:w-1/2 mb-12 lg:mb-0 stagger-animation px-2 md:px-0">
                    <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold mb-4 md:mb-6 animate-title leading-tight">
                        Join Our Community
                    </h1>
                    
                    <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                        Become part of a growing community dedicated to cleaner neighborhoods. Report waste issues, track progress, and earn rewards for making a difference.
                    </p>
                    
                    <!-- Benefits -->
                    <div class="space-y-6 mb-8">
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-bolt text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">Instant Reporting</h3>
                                <p class="text-gray-300 text-sm">Report waste issues in seconds with photo upload</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-trophy text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">Earn Rewards</h3>
                                <p class="text-gray-300 text-sm">Collect points for active participation</p>
                            </div>
                        </div>
                        
                        <div class="flex items-start space-x-4">
                            <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center flex-shrink-0">
                                <i class="fas fa-shield-alt text-white"></i>
                            </div>
                            <div>
                                <h3 class="text-white font-semibold mb-1">Secure & Private</h3>
                                <p class="text-gray-300 text-sm">Your data is protected with encryption</p>
                            </div>
                        </div>
                    </div>
                    
                    <!-- Stats -->
                    <div class="glass-card rounded-2xl p-4 md:p-6">
                        <div class="grid grid-cols-3 gap-4 text-center">
                            <div>
                                <div class="text-green-400 font-bold text-lg md:text-xl">10K+</div>
                                <div class="text-gray-300 text-xs md:text-sm">Community Members</div>
                            </div>
                            <div>
                                <div class="text-blue-400 font-bold text-lg md:text-xl">1.2K+</div>
                                <div class="text-gray-300 text-xs md:text-sm">Issues Resolved</div>
                            </div>
                            <div>
                                <div class="text-purple-400 font-bold text-lg md:text-xl">99%</div>
                                <div class="text-gray-300 text-xs md:text-sm">Satisfaction Rate</div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Register Form -->
                <div class="lg:w-1/2 lg:pl-12 fade-in-up">
                    <div class="glass-card rounded-2xl p-6 md:p-8 backdrop-blur-lg max-w-lg mx-auto">
                        <!-- Success Message -->
                        <?php if($success): ?>
                        <div class="mb-6 p-4 bg-green-500/20 border border-green-500/30 rounded-xl success-animation">
                            <div class="flex items-center">
                                <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center mr-3">
                                    <i class="fas fa-check text-green-400"></i>
                                </div>
                                <div>
                                    <h4 class="text-green-100 font-semibold">Registration Successful!</h4>
                                    <p class="text-green-200 text-sm"><?php echo $success; ?></p>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Error Messages -->
                        <?php if(!empty($errors)): ?>
                        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl">
                            <div class="flex items-start">
                                <i class="fas fa-exclamation-circle text-red-400 mr-3 mt-1"></i>
                                <div>
                                    <?php foreach($errors as $error): ?>
                                        <p class="text-red-100 mb-1">• <?php echo $error; ?></p>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Form Icon -->
                        <div class="text-center mb-6">
                            <div class="w-16 h-16 bg-gradient-to-r from-green-400 to-blue-500 rounded-full flex items-center justify-center mx-auto mb-4">
                                <i class="fas fa-user-plus text-white text-2xl"></i>
                            </div>
                            <h2 class="text-2xl font-bold text-white mb-2">Create Account</h2>
                            <p class="text-gray-300 text-sm">Join thousands making their community cleaner</p>
                        </div>
                        
                        <!-- Register Form -->
                        <form method="POST" action="" class="space-y-6">
                            <!-- Name and Email Row -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-white text-sm font-medium mb-2 block">
                                        <i class="fas fa-user mr-2 text-green-400"></i>
                                        Full Name
                                    </label>
                                    <input type="text" 
                                           name="full_name" 
                                           required 
                                           class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-3 px-4 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                           placeholder="John Doe"
                                           value="<?php echo isset($_POST['full_name']) ? htmlspecialchars($_POST['full_name']) : ''; ?>">
                                </div>
                                
                                <div>
                                    <label class="text-white text-sm font-medium mb-2 block">
                                        <i class="fas fa-envelope mr-2 text-green-400"></i>
                                        Email Address
                                    </label>
                                    <input type="email" 
                                           name="email" 
                                           required 
                                           class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-3 px-4 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                           placeholder="you@example.com"
                                           value="<?php echo isset($_POST['email']) ? htmlspecialchars($_POST['email']) : ''; ?>">
                                </div>
                            </div>
                            
                            <!-- Password Row -->
                            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                                <div>
                                    <label class="text-white text-sm font-medium mb-2 block">
                                        <i class="fas fa-lock mr-2 text-green-400"></i>
                                        Password
                                    </label>
                                    <input type="password" 
                                           id="password" 
                                           name="password" 
                                           required 
                                           class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-3 px-4 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                           placeholder="••••••••"
                                           onkeyup="checkPasswordStrength()">
                                    <!-- Password strength indicator -->
                                    <div class="mt-2">
                                        <div class="flex justify-between text-xs text-gray-400 mb-1">
                                            <span>Password strength:</span>
                                            <span id="strength-text">None</span>
                                        </div>
                                        <div class="w-full bg-gray-700 rounded-full h-1.5">
                                            <div id="password-strength" class="password-strength rounded-full"></div>
                                        </div>
                                    </div>
                                </div>
                                
                                <div>
                                    <label class="text-white text-sm font-medium mb-2 block">
                                        <i class="fas fa-lock mr-2 text-green-400"></i>
                                        Confirm Password
                                    </label>
                                    <input type="password" 
                                           id="confirm_password" 
                                           name="confirm_password" 
                                           required 
                                           class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-3 px-4 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                           placeholder="••••••••"
                                           onkeyup="checkPasswordMatch()">
                                    <!-- Password match indicator -->
                                    <div class="mt-2 text-sm" id="password-match"></div>
                                </div>
                            </div>
                            
                            <!-- Address -->
                            <div>
                                <label class="text-white text-sm font-medium mb-2 block">
                                    <i class="fas fa-map-marker-alt mr-2 text-green-400"></i>
                                    Address
                                </label>
                                <textarea name="address" 
                                          required 
                                          rows="3"
                                          class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-3 px-4 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus resize-none"
                                          placeholder="Enter your full address"><?php echo isset($_POST['address']) ? htmlspecialchars($_POST['address']) : ''; ?></textarea>
                            </div>
                            
                            <!-- Phone -->
                            <div>
                                <label class="text-white text-sm font-medium mb-2 block">
                                    <i class="fas fa-phone mr-2 text-green-400"></i>
                                    Phone Number
                                </label>
                                <input type="tel" 
                                       name="phone" 
                                       required 
                                       class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-3 px-4 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                       placeholder="+1 (555) 123-4567"
                                       value="<?php echo isset($_POST['phone']) ? htmlspecialchars($_POST['phone']) : ''; ?>">
                            </div>
                            
                            <!-- Password Requirements -->
                            <div class="bg-white/5 rounded-xl p-4">
                                <h4 class="text-white text-sm font-medium mb-2">Registration Requirements:</h4>
                                <ul class="space-y-1 text-sm text-gray-300">
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-400 mr-2" id="req-length"></i>
                                        Password must be at least 6 characters
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-400 mr-2" id="req-match"></i>
                                        Passwords must match
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                        Valid email address required
                                    </li>
                                    <li class="flex items-center">
                                        <i class="fas fa-check-circle text-green-400 mr-2"></i>
                                        All fields are required
                                    </li>
                                </ul>
                            </div>
                            
                            <!-- Terms and Conditions -->
                            <div class="flex items-start">
                                <input type="checkbox" 
                                       id="terms" 
                                       name="terms"
                                       required
                                       class="mt-1 w-4 h-4 text-green-400 bg-white/10 border-white/20 rounded focus:ring-green-400 focus:ring-offset-0">
                                <label for="terms" class="ml-2 text-gray-300 text-sm">
                                    I agree to the <a href="#" class="text-green-400 hover:text-green-300">Terms of Service</a> and <a href="#" class="text-green-400 hover:text-green-300">Privacy Policy</a>
                                </label>
                            </div>
                            
                            <!-- Submit Button -->
                            <button type="submit" 
                                    id="register-button"
                                    class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold py-4 px-6 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift pulse-animation shadow-lg disabled:opacity-50 disabled:cursor-not-allowed">
                                <i class="fas fa-user-plus mr-2"></i>
                                Create Account
                            </button>
                            
                            <!-- Login Link -->
                            <div class="text-center">
                                <p class="text-gray-400">
                                    Already have an account?
                                    <a href="login_customer.php" 
                                       class="text-green-400 hover:text-green-300 font-semibold ml-2">
                                        Sign in here
                                    </a>
                                </p>
                                <a href="index.php" 
                                   class="inline-flex items-center text-gray-400 hover:text-white mt-2 text-sm">
                                    <i class="fas fa-arrow-left mr-2"></i>
                                    Back to Home
                                </a>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Security Badge -->
                    <div class="hidden sm:block mt-8">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg">
                            <div class="flex items-center justify-center space-x-4">
                                <div class="w-10 h-10 rounded-full bg-green-500/20 flex items-center justify-center">
                                    <i class="fas fa-shield-alt text-green-400"></i>
                                </div>
                                <div class="text-center">
                                    <div class="text-white font-semibold">SSL Secured</div>
                                    <div class="text-gray-300 text-sm">Your data is encrypted and protected</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-8 md:py-12 safe-area-bottom">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-start">
                <div class="mb-8 md:mb-0 md:w-1/3">
                    <a href="index.php" class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                            <i class="fas fa-recycle text-white"></i>
                        </div>
                        <span class="text-2xl font-bold">Waste<span class="text-green-400">Wise</span></span>
                    </a>
                    <p class="text-gray-400 text-sm md:text-base">
                        Empowering communities to manage waste effectively and sustainably.
                    </p>
                </div>
                
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                    <div>
                        <h4 class="text-lg font-bold mb-4 text-green-400">Quick Links</h4>
                        <ul class="space-y-2">
                            <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Home</a></li>
                            <li><a href="login_customer.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Login</a></li>
                            <li><a href="register_customer.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Register</a></li>
                            <li><a href="forgot.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Forgot Password</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold mb-4 text-blue-400">Contact</h4>
                        <ul class="space-y-2">
                            <li class="text-gray-400 text-sm md:text-base flex items-start">
                                <i class="fas fa-envelope mr-2 text-sm mt-1"></i> support@wastewise.com
                            </li>
                            <li class="text-gray-400 text-sm md:text-base flex items-start">
                                <i class="fas fa-phone mr-2 text-sm mt-1"></i> +1 (555) 123-4567
                            </li>
                            <li class="text-gray-400 text-sm md:text-base flex items-start">
                                <i class="fas fa-map-marker-alt mr-2 text-sm mt-1"></i> 123 Green Street, Eco City
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                <p>&copy; 2024 Community Waste Management System. All rights reserved.</p>
                <p class="mt-2">Making our community cleaner, one report at a time.</p>
            </div>
        </div>
    </footer>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 md:hidden z-40">
        <a href="index.php" class="w-14 h-14 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
            <i class="fas fa-home text-white text-xl"></i>
        </a>
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
        
        // Close mobile nav when clicking outside
        document.addEventListener('click', function(event) {
            const mobileNav = document.querySelector('.mobile-nav');
            const menuButton = document.querySelector('[onclick="openMobileNav()"]');
            
            if (mobileNav.classList.contains('active') && 
                !mobileNav.contains(event.target) && 
                !menuButton.contains(event.target)) {
                closeMobileNav();
            }
        });
        
        // Password strength checker
        function checkPasswordStrength() {
            const password = document.getElementById('password').value;
            const strengthBar = document.getElementById('password-strength');
            const strengthText = document.getElementById('strength-text');
            const reqLength = document.getElementById('req-length');
            
            let strength = 0;
            let text = 'None';
            let className = 'strength-weak';
            
            // Check length
            if (password.length >= 6) {
                strength += 25;
                reqLength.classList.remove('text-gray-400');
                reqLength.classList.add('text-green-400');
            } else {
                reqLength.classList.remove('text-green-400');
                reqLength.classList.add('text-gray-400');
            }
            
            // Check for lowercase
            if (password.match(/[a-z]/)) strength += 25;
            
            // Check for uppercase
            if (password.match(/[A-Z]/)) strength += 25;
            
            // Check for numbers
            if (password.match(/[0-9]/)) strength += 25;
            
            // Update strength bar and text
            if (strength <= 25) {
                text = 'Weak';
                className = 'strength-weak';
            } else if (strength <= 50) {
                text = 'Fair';
                className = 'strength-fair';
            } else if (strength <= 75) {
                text = 'Good';
                className = 'strength-good';
            } else {
                text = 'Strong';
                className = 'strength-strong';
            }
            
            strengthBar.className = 'password-strength ' + className;
            strengthText.textContent = text;
            
            // Check if we should enable the register button
            checkFormValidity();
        }
        
        // Password match checker
        function checkPasswordMatch() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const matchDiv = document.getElementById('password-match');
            const reqMatch = document.getElementById('req-match');
            
            if (confirmPassword === '') {
                matchDiv.innerHTML = '';
                reqMatch.classList.remove('text-green-400', 'text-red-400');
                reqMatch.classList.add('text-gray-400');
            } else if (password === confirmPassword) {
                matchDiv.innerHTML = '<span class="text-green-400"><i class="fas fa-check-circle mr-1"></i> Passwords match</span>';
                reqMatch.classList.remove('text-gray-400', 'text-red-400');
                reqMatch.classList.add('text-green-400');
            } else {
                matchDiv.innerHTML = '<span class="text-red-400"><i class="fas fa-times-circle mr-1"></i> Passwords do not match</span>';
                reqMatch.classList.remove('text-gray-400', 'text-green-400');
                reqMatch.classList.add('text-red-400');
            }
            
            // Check if we should enable the register button
            checkFormValidity();
        }
        
        // Check if form is valid for submission
        function checkFormValidity() {
            const password = document.getElementById('password').value;
            const confirmPassword = document.getElementById('confirm_password').value;
            const terms = document.getElementById('terms');
            const registerButton = document.getElementById('register-button');
            
            if (password.length >= 6 && password === confirmPassword && terms.checked) {
                registerButton.disabled = false;
                registerButton.classList.remove('disabled:opacity-50', 'disabled:cursor-not-allowed');
            } else {
                registerButton.disabled = true;
                registerButton.classList.add('disabled:opacity-50', 'disabled:cursor-not-allowed');
            }
        }
        
        // Initialize form validation
        document.addEventListener('DOMContentLoaded', function() {
            checkPasswordStrength();
            checkPasswordMatch();
            
            // Add focus effects to inputs
            const inputs = document.querySelectorAll('input, textarea');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-green-400/20');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-green-400/20');
                });
            });
            
            // Check terms checkbox
            const terms = document.getElementById('terms');
            terms.addEventListener('change', checkFormValidity);
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
    </script>
</body>
</html>