<?php
require_once 'config.php';

$error = '';
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $email = sanitize($_POST['email']);
    $password = sanitize($_POST['password']);
    
    $conn = getDBConnection();
    
    $query = "SELECT * FROM customer WHERE customer_email = '$email' AND status = 'active'";
    $result = mysqli_query($conn, $query);
    
    if (mysqli_num_rows($result) == 1) {
        $user = mysqli_fetch_assoc($result);
        
        // In real application, use password_verify()
        if (password_verify($password, $user['password'])) {
            $_SESSION['user_id'] = $user['customer_id'];
            $_SESSION['user_type'] = 'customer';
            $_SESSION['user_name'] = $user['full_name'];
            $_SESSION['user_email'] = $user['customer_email'];
            
            redirect('customer_dashboard.php');
        } else {
            $error = "Invalid email or password!";
        }
    } else {
        $error = "Invalid email or password!";
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Customer Login - WasteWise</title>
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
        
        .login-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.85), rgba(0, 0, 0, 0.85)), url('https://media.licdn.com/dms/image/v2/D4E12AQHclfeT7LmcfQ/article-cover_image-shrink_720_1280/article-cover_image-shrink_720_1280/0/1719988994510?e=2147483647&v=beta&t=74SoGzgjkc5M0QlK6kDsskfcy0ID8hIC6AxalcWtps4');
            background-size: cover;
            background-position: center;
            background-attachment: scroll;
            position: relative;
            min-height: 100vh;
        }
        
        /* Remove blur on mobile for better performance */
        @media (max-width: 768px) {
            .login-bg::before {
                display: none;
            }
            .login-bg {
                background-attachment: scroll;
            }
        }
        
        @media (min-width: 769px) {
            .login-bg::before {
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
        
        /* Swipe indicator for mobile */
        .swipe-indicator {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }
        
        /* Input styles */
        .input-focus:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
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
            <a href="forgot_password.php" class="block text-white text-lg py-3 hover:text-green-400 transition-colors border-b border-gray-700">
                <i class="fas fa-key mr-3"></i> Forgot Password
            </a>
            <a href="register_customer.php" class="block bg-gradient-to-r from-green-500 to-blue-500 text-white text-lg py-4 px-6 rounded-xl font-semibold text-center mt-8 hover:shadow-lg transition-all">
                <i class="fas fa-user-plus mr-2"></i> Get Started
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
    <div class="login-bg">
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
                    <a href="register_customer.php" class="text-white hover:text-green-400 transition-colors duration-300 font-medium">Register</a>
                    <a href="register_customer.php" class="bg-gradient-to-r from-green-400 to-blue-500 text-white px-6 py-2 rounded-full hover:from-green-500 hover:to-blue-600 transition-all duration-300 font-medium pulse-animation">
                        Get Started
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button onclick="openMobileNav()" class="md:hidden text-white text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>

            <!-- Login Content -->
            <div class="flex flex-col lg:flex-row items-center justify-between min-h-[calc(100vh-120px)] py-8 md:py-12">
                <!-- Left Column - Welcome Text -->
                <div class="lg:w-1/2 mb-12 lg:mb-0 stagger-animation px-2 md:px-0">
                    <h1 class="text-4xl sm:text-5xl md:text-6xl lg:text-7xl font-bold mb-4 md:mb-6 animate-title leading-tight">
                        Welcome Back
                    </h1>
                    
                    <p class="text-gray-200 text-base sm:text-lg md:text-xl mb-6 md:mb-8 leading-relaxed">
                        Sign in to your account to continue managing waste issues, tracking progress, and contributing to a cleaner community.
                    </p>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-8">
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3 text-lg"></i>
                            <span>Secure & Encrypted</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3 text-lg"></i>
                            <span>Real-time Updates</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3 text-lg"></i>
                            <span>Community Rewards</span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-check-circle text-green-400 mr-3 text-lg"></i>
                            <span>24/7 Support</span>
                        </div>
                    </div>
                    
                    <!-- Mobile Stats -->
                    <div class="sm:hidden w-full mt-6">
                        <div class="glass-card rounded-2xl p-4">
                            <div class="grid grid-cols-3 gap-2 mb-4">
                                <div class="text-center">
                                    <div class="text-green-400 font-bold text-lg">10K+</div>
                                    <div class="text-gray-300 text-xs">Active Users</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-blue-400 font-bold text-lg">99%</div>
                                    <div class="text-gray-300 text-xs">Satisfaction</div>
                                </div>
                                <div class="text-center">
                                    <div class="text-purple-400 font-bold text-lg">24/7</div>
                                    <div class="text-gray-300 text-xs">Support</div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
                
                <!-- Right Column - Login Form -->
                <div class="lg:w-1/2 lg:pl-12 fade-in-up">
                    <div class="glass-card rounded-2xl p-6 md:p-8 backdrop-blur-lg max-w-lg mx-auto">
                        <!-- Error Message -->
                        <?php if($error): ?>
                        <div class="mb-6 p-4 bg-red-500/20 border border-red-500/30 rounded-xl">
                            <div class="flex items-center">
                                <i class="fas fa-exclamation-circle text-red-400 mr-3"></i>
                                <span class="text-red-100"><?php echo $error; ?></span>
                            </div>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Login Form -->
                        <form method="POST" action="" class="space-y-6">
                            <!-- Email Field -->
                            <div>
                                <label class="text-white text-sm font-medium mb-2 block">
                                    <i class="fas fa-envelope mr-2 text-green-400"></i>
                                    Email Address
                                </label>
                                <input type="email" 
                                       id="email" 
                                       name="email" 
                                       required 
                                       class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-4 px-5 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                       placeholder="you@example.com">
                            </div>
                            
                            <!-- Password Field -->
                            <div>
                                <div class="flex justify-between items-center mb-2">
                                    <label class="text-white text-sm font-medium">
                                        <i class="fas fa-lock mr-2 text-green-400"></i>
                                        Password
                                    </label>
                                    <a href="forgot_password.php" class="text-green-400 hover:text-green-300 text-sm">
                                        Forgot password?
                                    </a>
                                </div>
                                <input type="password" 
                                       id="password" 
                                       name="password" 
                                       required 
                                       class="w-full bg-white/10 border border-white/20 text-white placeholder-gray-400 rounded-xl py-4 px-5 focus:outline-none focus:border-green-400 focus:ring-2 focus:ring-green-400/20 transition-all duration-200 input-focus"
                                       placeholder="••••••••">
                            </div>
                            
                            <!-- Remember Me -->
                            <div class="flex items-center">
                                <input type="checkbox" 
                                       id="remember" 
                                       class="w-5 h-5 rounded border-white/30 bg-white/10 text-green-400 focus:ring-green-400 focus:ring-offset-0">
                                <label for="remember" class="ml-2 text-gray-300 text-sm">
                                    Remember me for 30 days
                                </label>
                            </div>
                            
                            <!-- Login Button -->
                            <button type="submit" 
                                    class="w-full bg-gradient-to-r from-green-500 to-emerald-600 text-white font-semibold py-4 px-6 rounded-xl hover:from-green-600 hover:to-emerald-700 transition-all duration-300 hover-lift pulse-animation shadow-lg">
                                <i class="fas fa-sign-in-alt mr-2"></i>
                                Sign In
                            </button>
                            
                            <!-- Divider -->
                            <div class="relative">
                                <div class="absolute inset-0 flex items-center">
                                    <div class="w-full border-t border-white/20"></div>
                                </div>
                            </div>
                        
                            
                            <!-- Sign Up Link -->
                            <div class="text-center mt-6">
                                <p class="text-gray-400">
                                    Don't have an account?
                                    <a href="register_customer.php" 
                                       class="text-green-400 hover:text-green-300 font-semibold ml-2">
                                        Sign up here
                                    </a>
                                </p>
                            </div>
                        </form>
                    </div>
                    
                    <!-- Desktop Stats -->
                    <div class="hidden sm:block mt-8">
                        <div class="glass-card rounded-2xl p-6 backdrop-blur-lg">
                            <div class="grid grid-cols-3 gap-4 text-center">
                                <div>
                                    <div class="text-2xl md:text-3xl font-bold text-green-400">10K+</div>
                                    <div class="text-gray-300 text-sm">Active Users</div>
                                </div>
                                <div>
                                    <div class="text-2xl md:text-3xl font-bold text-blue-400">99%</div>
                                    <div class="text-gray-300 text-sm">Satisfaction</div>
                                </div>
                                <div>
                                    <div class="text-2xl md:text-3xl font-bold text-purple-400">24/7</div>
                                    <div class="text-gray-300 text-sm">Support</div>
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
                            <li><a href="forgot_password.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Forgot Password</a></li>
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
        <a href="register_customer.php" class="w-14 h-14 bg-gradient-to-r from-green-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
            <i class="fas fa-user-plus text-white text-xl"></i>
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
        
        // Toggle password visibility
        function togglePassword() {
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('passwordToggle');
            
            if (passwordInput.type === 'password') {
                passwordInput.type = 'text';
                passwordToggle.classList.remove('fa-eye');
                passwordToggle.classList.add('fa-eye-slash');
            } else {
                passwordInput.type = 'password';
                passwordToggle.classList.remove('fa-eye-slash');
                passwordToggle.classList.add('fa-eye');
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
        
        // Improve mobile performance by disabling some animations on scroll
        let scrollTimer;
        window.addEventListener('scroll', function() {
            document.body.classList.add('disable-animations');
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                document.body.classList.remove('disable-animations');
            }, 100);
        });
        
        // Add focus effects to inputs
        document.addEventListener('DOMContentLoaded', function() {
            const inputs = document.querySelectorAll('input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.classList.add('ring-2', 'ring-green-400/20');
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.classList.remove('ring-2', 'ring-green-400/20');
                });
            });
        });
    </script>
</body>
</html>