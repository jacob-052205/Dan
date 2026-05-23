<?php
require_once 'config.php';
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Community Waste Management System</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        * {
            font-family: 'Inter', sans-serif;
        }
        
        .hero-bg {
            background-image: linear-gradient(rgba(0, 0, 0, 0.7), rgba(0, 0, 0, 0.7)), url('https://media.licdn.com/dms/image/v2/D4E12AQHclfeT7LmcfQ/article-cover_image-shrink_720_1280/article-cover_image-shrink_720_1280/0/1719988994510?e=2147483647&v=beta&t=74SoGzgjkc5M0QlK6kDsskfcy0ID8hIC6AxalcWtps4');
            background-size: cover;
            background-position: center;
            background-attachment: fixed;
            position: relative;
        }
        
        .hero-bg::before {
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
    </style>
</head>
<body class="bg-gray-50">
    <!-- Hero Section with Background -->
    <div class="hero-bg min-h-screen">
        <div class="container mx-auto px-4 py-8">
            <!-- Navigation -->
            <nav class="flex justify-between items-center py-6">
                <a href="index.php" class="flex items-center space-x-3">
                    <div class="w-12 h-12 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                        <i class="fas fa-recycle text-white text-2xl"></i>
                    </div>
                    <span class="text-white text-2xl font-bold">Waste<span class="text-green-400">Wise</span></span>
                </a>
                
                <div class="hidden md:flex space-x-8">
                    <a href="#features" class="text-white hover:text-green-400 transition-colors duration-300 font-medium">Features</a>
                    <a href="login_customer.php" class="text-white hover:text-green-400 transition-colors duration-300 font-medium">Login</a>
                    <a href="register_customer.php" class="bg-gradient-to-r from-green-400 to-blue-500 text-white px-6 py-2 rounded-full hover:from-green-500 hover:to-blue-600 transition-all duration-300 font-medium pulse-animation">
                        Get Started
                    </a>
                </div>
                
                <!-- Mobile Menu Button -->
                <button class="md:hidden text-white text-2xl">
                    <i class="fas fa-bars"></i>
                </button>
            </nav>

            <!-- Hero Content -->
            <div class="flex flex-col lg:flex-row items-center justify-between min-h-[80vh] py-12">
                <div class="lg:w-1/2 mb-12 lg:mb-0 stagger-animation">
                    <h1 class="text-5xl md:text-6xl lg:text-7xl font-bold mb-6 animate-title">
                        Community Waste Management
                    </h1>
                    
                    <p class="text-gray-200 text-lg md:text-xl mb-8 leading-relaxed">
                        Join our mission to create cleaner, greener communities. Report waste issues, track cleanup progress, 
                        and contribute to sustainable waste management in real-time.
                    </p>
                    
                    <div class="flex flex-col sm:flex-row gap-4 mb-8">
                        <a href="register_customer.php" class="bg-gradient-to-r from-green-500 to-emerald-600 text-white px-8 py-4 rounded-xl text-lg font-semibold hover-lift hover:shadow-xl transition-all duration-300 text-center">
                            <i class="fas fa-flag mr-2"></i> Report Waste Issue
                        </a>
                        <a href="#features" class="glass-card text-white px-8 py-4 rounded-xl text-lg font-semibold hover-lift border border-white/20 text-center">
                            <i class="fas fa-play-circle mr-2"></i> How It Works
                        </a>
                    </div>
                    
                    <div class="flex items-center space-x-6 text-gray-300">
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span>Real-time Tracking</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span>Instant Notifications</span>
                        </div>
                        <div class="flex items-center">
                            <i class="fas fa-check-circle text-green-400 mr-2"></i>
                            <span>Reward System</span>
                        </div>
                    </div>
                </div>
                
                <!-- Hero Image/Stats Card -->
                <div class="lg:w-1/2 lg:pl-12 fade-in-up">
                    <div class="glass-card rounded-2xl p-8 backdrop-blur-lg">
                        <div class="grid grid-cols-2 gap-6 mb-8">
                            <div class="bg-white/10 p-6 rounded-xl text-center">
                                <div class="text-4xl font-bold text-green-400 mb-2">1,234+</div>
                                <div class="text-gray-300">Issues Resolved</div>
                            </div>
                            <div class="bg-white/10 p-6 rounded-xl text-center">
                                <div class="text-4xl font-bold text-blue-400 mb-2">856+</div>
                                <div class="text-gray-300">Active Users</div>
                            </div>
                            <div class="bg-white/10 p-6 rounded-xl text-center">
                                <div class="text-4xl font-bold text-purple-400 mb-2">98%</div>
                                <div class="text-gray-300">Satisfaction Rate</div>
                            </div>
                            <div class="bg-white/10 p-6 rounded-xl text-center">
                                <div class="text-4xl font-bold text-yellow-400 mb-2">24h</div>
                                <div class="text-gray-300">Avg. Response Time</div>
                            </div>
                        </div>
                        
                        <div class="relative">
                            <img src="https://images.unsplash.com/photo-1562077981-4d7eafd3d74a?ixlib=rb-4.0.3&auto=format&fit=crop&w=1000&q=80" 
                                 alt="Clean Community" 
                                 class="rounded-xl shadow-2xl">
                            <div class="absolute -bottom-4 -right-4 bg-gradient-to-r from-green-500 to-blue-500 p-4 rounded-xl shadow-xl">
                                <div class="text-white text-sm">Live Tracking</div>
                                <div class="text-white text-xl font-bold">Active Now</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Features Section -->
    <section id="features" class="py-20 bg-gradient-to-b from-gray-50 to-white">
        <div class="container mx-auto px-4">
            <div class="text-center mb-16">
                <h2 class="text-4xl md:text-5xl font-bold text-gray-800 mb-4">
                    How <span class="text-gradient">WasteWise</span> Works
                </h2>
                <p class="text-gray-600 text-lg max-w-2xl mx-auto">
                    A simple four-step process to report, track, and resolve waste management issues in your community
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-8">
                <!-- Step 1 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover-lift hover:border-green-400 border-2 border-transparent transition-all duration-300 group">
                    <div class="feature-icon-bg w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-map-marker-alt text-white text-2xl"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                        <span class="bg-gradient-to-r from-green-500 to-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3">1</span>
                        Report Issues
                    </div>
                    <p class="text-gray-600 mb-4">
                        Snap a photo and report uncollected waste or request pickup from any location.
                    </p>
                    <div class="text-green-500 font-medium">Fast & Easy</div>
                </div>

                <!-- Step 2 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover-lift hover:border-blue-400 border-2 border-transparent transition-all duration-300 group">
                    <div class="feature-icon-bg w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-chart-line text-white text-2xl"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                        <span class="bg-gradient-to-r from-green-500 to-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3">2</span>
                        Track Progress
                    </div>
                    <p class="text-gray-600 mb-4">
                        Monitor real-time status updates from submission to completion on your dashboard.
                    </p>
                    <div class="text-blue-500 font-medium">Real-time Updates</div>
                </div>

                <!-- Step 3 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover-lift hover:border-purple-400 border-2 border-transparent transition-all duration-300 group">
                    <div class="feature-icon-bg w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-bell text-white text-2xl"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                        <span class="bg-gradient-to-r from-green-500 to-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3">3</span>
                        Get Notified
                    </div>
                    <p class="text-gray-600 mb-4">
                        Receive instant notifications via email or SMS when there's an update on your report.
                    </p>
                    <div class="text-purple-500 font-medium">Instant Alerts</div>
                </div>

                <!-- Step 4 -->
                <div class="bg-white rounded-2xl p-8 shadow-lg hover-lift hover:border-yellow-400 border-2 border-transparent transition-all duration-300 group">
                    <div class="feature-icon-bg w-16 h-16 rounded-2xl flex items-center justify-center mb-6 group-hover:scale-110 transition-transform duration-300">
                        <i class="fas fa-trophy text-white text-2xl"></i>
                    </div>
                    <div class="text-2xl font-bold text-gray-800 mb-4 flex items-center">
                        <span class="bg-gradient-to-r from-green-500 to-blue-500 text-white w-8 h-8 rounded-full flex items-center justify-center mr-3">4</span>
                        Earn Rewards
                    </div>
                    <p class="text-gray-600 mb-4">
                        Collect points for active reporting and redeem exciting rewards for your contributions.
                    </p>
                    <div class="text-yellow-500 font-medium">Rewarding</div>
                </div>
            </div>

            <!-- CTA Banner -->
            <div class="mt-20 bg-gradient-to-r from-green-500 to-blue-600 rounded-3xl p-12 text-center relative overflow-hidden">
                <div class="absolute top-0 left-0 w-32 h-32 bg-white/10 rounded-full -translate-x-16 -translate-y-16"></div>
                <div class="absolute bottom-0 right-0 w-40 h-40 bg-white/10 rounded-full translate-x-20 translate-y-20"></div>
                
                <div class="relative z-10">
                    <h3 class="text-3xl md:text-4xl font-bold text-white mb-6">
                        Ready to Make a Difference?
                    </h3>
                    <p class="text-white/90 text-lg mb-8 max-w-2xl mx-auto">
                        Join thousands of community members who are actively contributing to cleaner neighborhoods.
                    </p>
                    <a href="register_customer.php" class="inline-block bg-white text-gray-800 px-10 py-4 rounded-xl text-lg font-bold hover:bg-gray-100 hover-lift transition-all duration-300 shadow-lg">
                        <i class="fas fa-user-plus mr-2"></i> Join Now - It's Free!
                    </a>
                </div>
            </div>
        </div>
    </section>

    <!-- Footer -->
    <footer class="bg-gray-900 text-white py-12">
        <div class="container mx-auto px-4">
            <div class="flex flex-col md:flex-row justify-between items-center">
                <div class="mb-8 md:mb-0">
                    <a href="index.php" class="flex items-center space-x-3 mb-4">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-green-400 to-blue-500 flex items-center justify-center">
                            <i class="fas fa-recycle text-white"></i>
                        </div>
                        <span class="text-2xl font-bold">Waste<span class="text-green-400">Wise</span></span>
                    </a>
                    <p class="text-gray-400 max-w-md">
                        Empowering communities to manage waste effectively and sustainably.
                    </p>
                </div>
                
                <div class="grid grid-cols-2 gap-8 md:gap-16">
                    <div>
                        <h4 class="text-lg font-bold mb-4 text-green-400">Quick Links</h4>
                        <ul class="space-y-2">
                            <li><a href="index.php" class="text-gray-400 hover:text-white transition-colors">Home</a></li>
                            <li><a href="#features" class="text-gray-400 hover:text-white transition-colors">Features</a></li>
                            <li><a href="login_customer.php" class="text-gray-400 hover:text-white transition-colors">Login</a></li>
                        </ul>
                    </div>
                    <div>
                        <h4 class="text-lg font-bold mb-4 text-blue-400">Contact</h4>
                        <ul class="space-y-2">
                            <li class="text-gray-400 flex items-center">
                                <i class="fas fa-envelope mr-2 text-sm"></i> support@wastewise.com
                            </li>
                            <li class="text-gray-400 flex items-center">
                                <i class="fas fa-phone mr-2 text-sm"></i> +1 (555) 123-4567
                            </li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400">
                <p>&copy; 2024 Community Waste Management System. All rights reserved.</p>
                <p class="mt-2">Making our community cleaner, one report at a time.</p>
            </div>
        </div>
    </footer>

    <script>
        // Add scroll animations
        document.addEventListener('DOMContentLoaded', function() {
            const observerOptions = {
                threshold: 0.1,
                rootMargin: '0px 0px -50px 0px'
            };

            const observer = new IntersectionObserver((entries) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('fade-in-up');
                    }
                });
            }, observerOptions);

            // Observe all feature cards
            document.querySelectorAll('.feature-card').forEach(card => {
                observer.observe(card);
            });
        });
    </script>
</body>
</html>
