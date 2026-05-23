<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('login_admin.php');
}

$conn = getDBConnection();

if (!isset($_GET['id'])) {
    redirect('admin_reports.php');
}

$report_id = (int)$_GET['id'];

// Get report details with customer info
$query = "SELECT r.*, c.full_name as customer_name, c.customer_email 
          FROM reports r 
          LEFT JOIN customer c ON r.customer_id = c.customer_id 
          WHERE r.report_id = $report_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect('admin_reports.php');
}

$report = mysqli_fetch_assoc($result);

// Get waste categories
$categories_query = "SELECT * FROM waste_categories";
$categories_result = mysqli_query($conn, $categories_query);

// Get selected categories
$selected_categories_query = "SELECT category_id FROM report_categories WHERE report_id = $report_id";
$selected_result = mysqli_query($conn, $selected_categories_query);
$selected_categories = [];
while ($row = mysqli_fetch_assoc($selected_result)) {
    $selected_categories[] = $row['category_id'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $title = sanitize($_POST['title']);
    $description = sanitize($_POST['description']);
    $address = sanitize($_POST['address']);
    $report_type = sanitize($_POST['report_type']);
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
                     report_type = '$report_type',
                     image_path = '$image_path',
                     priority = '$priority',
                     updated_at = NOW()
                     WHERE report_id = $report_id";

    if (mysqli_query($conn, $update_query)) {
        // Update categories
        mysqli_query($conn, "DELETE FROM report_categories WHERE report_id = $report_id");

        foreach ($categories as $category_id) {
            $category_id = (int)$category_id;
            $category_query = "INSERT INTO report_categories (report_id, category_id) VALUES ($report_id, $category_id)";
            mysqli_query($conn, $category_query);
        }

        // Record history
        $history_query = "INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, user_type, notes)
                         VALUES ($report_id, '{$report['status']}', '{$report['status']}', {$_SESSION['user_id']}, 'admin', 'Report details edited by admin')";
        mysqli_query($conn, $history_query);

        $success = "Report updated successfully!";
        // Refresh report data
        $result = mysqli_query($conn, $query);
        $report = mysqli_fetch_assoc($result);
    } else {
        $error = "Error updating report: " . mysqli_error($conn);
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Edit Report - WasteWise Admin</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <style>
        /* Reuse all CSS from admin_dashboard.php */
        * {
            font-family: 'Inter', sans-serif;
            -webkit-tap-highlight-color: transparent;
        }
        
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
        
        .mobile-nav {
            transform: translateX(100%);
            transition: transform 0.3s ease-in-out;
        }
        
        .mobile-nav.active {
            transform: translateX(0);
        }
        
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
        
        .btn-touch {
            padding: 16px 24px;
            min-height: 54px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
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
            
            a, button {
                min-height: 44px;
                min-width: 44px;
            }
        }
        
        .safe-area-top {
            padding-top: env(safe-area-inset-top);
        }
        
        .safe-area-bottom {
            padding-bottom: env(safe-area-inset-bottom);
        }
        
        .swipe-indicator {
            animation: bounce 2s infinite;
        }
        
        @keyframes bounce {
            0%, 20%, 50%, 80%, 100% {transform: translateY(0);}
            40% {transform: translateY(-10px);}
            60% {transform: translateY(-5px);}
        }
        
        .input-focus:focus {
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
        }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .status-pending { background: rgba(254, 243, 199, 0.2); color: #fef3c7; border: 1px solid rgba(254, 243, 199, 0.3); }
        .status-in_progress { background: rgba(219, 234, 254, 0.2); color: #dbeafe; border: 1px solid rgba(219, 234, 254, 0.3); }
        .status-completed { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .status-rejected { background: rgba(254, 226, 226, 0.2); color: #fee2e2; border: 1px solid rgba(254, 226, 226, 0.3); }
        
        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-view { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .btn-view:hover { background: rgba(59, 130, 246, 0.3); }
        .btn-edit { background: rgba(74, 222, 128, 0.2); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
        .btn-edit:hover { background: rgba(74, 222, 128, 0.3); }
        .btn-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .btn-delete:hover { background: rgba(239, 68, 68, 0.3); }
        
        .table-container {
            overflow-x: auto;
            -webkit-overflow-scrolling: touch;
        }
        
        .table th {
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            font-weight: 600;
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .table td {
            padding: 16px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.7);
        }
        
        .table tr:hover {
            background: rgba(255, 255, 255, 0.05);
        }
        
        .customer-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
        }
        
        .customer-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
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
        
        .main-content {
            transition: margin-left 0.3s ease;
        }
        
        @media (min-width: 1024px) {
            .main-content {
                margin-left: 280px;
            }
        }
        
        /* Form specific styles */
        .form-group {
            margin-bottom: 1.5rem;
        }
        
        .form-label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .form-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            color: white;
            font-size: 1rem;
            transition: all 0.3s;
        }
        
        .form-input:focus {
            outline: none;
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-input::placeholder {
            color: rgba(255, 255, 255, 0.4);
        }
        
        .form-select {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            color: white;
            font-size: 1rem;
            appearance: none;
            background-image: url("data:image/svg+xml,%3csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 20 20'%3e%3cpath stroke='%23ffffff' stroke-linecap='round' stroke-linejoin='round' stroke-width='1.5' d='M6 8l4 4 4-4'/%3e%3c/svg%3e");
            background-position: right 0.75rem center;
            background-repeat: no-repeat;
            background-size: 1.5em 1.5em;
            padding-right: 2.5rem;
        }
        
        .form-select:focus {
            outline: none;
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .form-textarea {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            color: white;
            font-size: 1rem;
            min-height: 120px;
            resize: vertical;
            transition: all 0.3s;
        }
        
        .form-textarea:focus {
            outline: none;
            border-color: #4ade80;
            box-shadow: 0 0 0 3px rgba(74, 222, 128, 0.1);
            background: rgba(255, 255, 255, 0.08);
        }
        
        .btn-submit {
            background: linear-gradient(135deg, #4ade80 0%, #22d3ee 100%);
            color: white;
            border: none;
            border-radius: 0.75rem;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-submit:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 25px rgba(74, 222, 128, 0.3);
        }
        
        .btn-cancel {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
            border-radius: 0.75rem;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }
        
        .btn-cancel:hover {
            background: rgba(239, 68, 68, 0.3);
        }
        
        .alert-success {
            background: rgba(220, 252, 231, 0.2);
            border: 1px solid rgba(220, 252, 231, 0.3);
            color: #dcfce7;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        .alert-error {
            background: rgba(254, 226, 226, 0.2);
            border: 1px solid rgba(254, 226, 226, 0.3);
            color: #fee2e2;
            padding: 1rem;
            border-radius: 0.75rem;
            margin-bottom: 1.5rem;
        }
        
        /* Radio button styles */
        .radio-group {
            display: flex;
            gap: 1.5rem;
            margin-top: 0.5rem;
        }
        
        .radio-option {
            display: flex;
            align-items: center;
        }
        
        .radio-input {
            display: none;
        }
        
        .radio-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 0.75rem 1.5rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
        }
        
        .radio-label:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .radio-input:checked + .radio-label {
            background: rgba(74, 222, 128, 0.2);
            border-color: #4ade80;
            color: white;
        }
        
        /* Checkbox styles */
        .checkbox-group {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .checkbox-option {
            display: flex;
            align-items: center;
        }
        
        .checkbox-input {
            display: none;
        }
        
        .checkbox-label {
            display: flex;
            align-items: center;
            cursor: pointer;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 2px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            color: rgba(255, 255, 255, 0.7);
            transition: all 0.3s;
            width: 100%;
        }
        
        .checkbox-label:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-2px);
        }
        
        .checkbox-input:checked + .checkbox-label {
            background: rgba(74, 222, 128, 0.2);
            border-color: #4ade80;
            color: white;
        }
        
        .category-icon {
            margin-right: 0.75rem;
            font-size: 1.25rem;
            color: #4ade80;
        }
        
        /* Image preview */
        .image-preview-container {
            margin-top: 1rem;
        }
        
        .current-image-label {
            display: block;
            color: rgba(255, 255, 255, 0.8);
            margin-bottom: 0.5rem;
            font-weight: 500;
        }
        
        .image-preview {
            max-width: 300px;
            margin-top: 0.5rem;
        }
        
        .image-preview img {
            width: 100%;
            border-radius: 0.75rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
        }
        
        .no-image {
            color: rgba(255, 255, 255, 0.5);
            font-style: italic;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 0.75rem;
            text-align: center;
        }
        
        /* File input */
        .file-input-wrapper {
            position: relative;
            margin-top: 1rem;
        }
        
        .file-input {
            width: 100%;
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .file-input:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .file-input input[type="file"] {
            display: none;
        }
        
        .file-label {
            display: flex;
            flex-direction: column;
            align-items: center;
            color: rgba(255, 255, 255, 0.7);
            cursor: pointer;
        }
        
        .file-label i {
            font-size: 2rem;
            margin-bottom: 0.5rem;
            color: #4ade80;
        }
        
        .file-name {
            margin-top: 0.5rem;
            font-size: 0.875rem;
            color: rgba(255, 255, 255, 0.5);
        }
        
        /* Report info badges */
        .report-type-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        
        .type-uncollected { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .type-pickup_request { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        
        .priority-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            font-weight: 600;
            display: inline-flex;
            align-items: center;
        }
        
        .priority-low { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .priority-medium { background: rgba(234, 179, 8, 0.2); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); }
        .priority-high { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
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
            <a href="admin_settings.php" class="flex items-center space-x-3 p-4 text-white hover:bg-white/5 rounded-xl transition-colors">
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
                
                <a href="admin_reports.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
                    <i class="fas fa-flag text-blue-400 w-6"></i>
                    <span>Reports Management</span>
                </a>
                
                <a href="admin_customers.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-users text-green-400 w-6"></i>
                    <span>Customers</span>
                </a>
                
                <a href="admin_teams.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-truck text-yellow-400 w-6"></i>
                    <span>Collection Teams</span>
                </a>
                
                <a href="admin_categories.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
                    <i class="fas fa-trash text-red-400 w-6"></i>
                    <span>Waste Categories</span>
                </a>
                
                <a href="admin_settings.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
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
            
            <!-- Report Info -->
            <div class="mt-8 pt-6 border-t border-white/10">
                <div class="text-gray-400 text-sm mb-3">Editing Report</div>
                <div class="bg-white/5 p-4 rounded-lg">
                    <div class="flex items-center justify-between mb-2">
                        <div class="text-white text-lg font-semibold">#<?php echo $report['report_id']; ?></div>
                        <span class="status-badge status-<?php echo $report['status']; ?>">
                            <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                        </span>
                    </div>
                    <div class="text-gray-400 text-xs">Created: <?php echo date('M j, Y', strtotime($report['created_at'])); ?></div>
                    <div class="mt-2">
                        <span class="report-type-badge type-<?php echo $report['report_type']; ?>">
                            <?php echo $report['report_type'] == 'uncollected' ? 'Uncollected Waste' : 'Pickup Request'; ?>
                        </span>
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
            <div>
                <h1 class="text-white text-xl md:text-2xl font-bold">Edit Report</h1>
                <p class="text-gray-400 text-sm">Update report information</p>
            </div>
            
            <!-- Desktop Right Actions -->
            <div class="flex items-center space-x-4">
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
            <!-- Report Information -->
            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6 md:gap-8 mb-8">
                <!-- Report Overview -->
                <div class="glass-card rounded-2xl p-6 fade-in-up">
                    <div class="flex items-center space-x-4 mb-6">
                        <div class="w-16 h-16 rounded-xl bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-flag text-white text-2xl"></i>
                        </div>
                        <div>
                            <h2 class="text-white text-xl font-bold">Report #<?php echo $report['report_id']; ?></h2>
                            <div class="flex items-center space-x-2 mt-2">
                                <span class="status-badge status-<?php echo $report['status']; ?>">
                                    <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                                </span>
                                <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                    <?php echo ucfirst($report['priority']); ?> Priority
                                </span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="space-y-4">
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-user text-green-400 w-6"></i>
                            <span class="ml-3"><?php echo htmlspecialchars($report['customer_name']); ?></span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-envelope text-blue-400 w-6"></i>
                            <span class="ml-3"><?php echo htmlspecialchars($report['customer_email']); ?></span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-calendar text-yellow-400 w-6"></i>
                            <span class="ml-3"><?php echo date('F j, Y', strtotime($report['created_at'])); ?></span>
                        </div>
                        <div class="flex items-center text-gray-300">
                            <i class="fas fa-sync text-purple-400 w-6"></i>
                            <span class="ml-3">Last updated: <?php echo date('F j, Y', strtotime($report['updated_at'])); ?></span>
                        </div>
                    </div>
                </div>

                <!-- Quick Actions -->
                <div class="glass-card rounded-2xl p-6 fade-in-up">
                    <h3 class="text-white text-lg font-bold mb-6 flex items-center">
                        <i class="fas fa-bolt text-green-400 mr-3"></i>
                        Quick Actions
                    </h3>
                    
                    <div class="space-y-3">
                        <a href="view_report.php?id=<?php echo $report_id; ?>" 
                           class="flex items-center justify-between p-3 bg-blue-500/10 hover:bg-blue-500/20 border border-blue-500/20 rounded-lg transition-colors group">
                            <div class="flex items-center">
                                <i class="fas fa-eye text-blue-400 mr-3"></i>
                                <span class="text-white">View Report</span>
                            </div>
                            <i class="fas fa-arrow-right text-blue-400 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        
                        <a href="admin_reports.php" 
                           class="flex items-center justify-between p-3 bg-purple-500/10 hover:bg-purple-500/20 border border-purple-500/20 rounded-lg transition-colors group">
                            <div class="flex items-center">
                                <i class="fas fa-list text-purple-400 mr-3"></i>
                                <span class="text-white">Back to Reports</span>
                            </div>
                            <i class="fas fa-arrow-right text-purple-400 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                        
                        <a href="update_status.php?id=<?php echo $report_id; ?>" 
                           class="flex items-center justify-between p-3 bg-green-500/10 hover:bg-green-500/20 border border-green-500/20 rounded-lg transition-colors group">
                            <div class="flex items-center">
                                <i class="fas fa-exchange-alt text-green-400 mr-3"></i>
                                <span class="text-white">Change Status</span>
                            </div>
                            <i class="fas fa-arrow-right text-green-400 group-hover:translate-x-1 transition-transform"></i>
                        </a>
                    </div>
                </div>

                <!-- Report Details -->
                <div class="glass-card rounded-2xl p-6 fade-in-up">
                    <h3 class="text-white text-lg font-bold mb-6 flex items-center">
                        <i class="fas fa-info-circle text-yellow-400 mr-3"></i>
                        Report Details
                    </h3>
                    
                    <div class="space-y-3">
                        <div>
                            <div class="text-gray-400 text-sm">Report Type</div>
                            <span class="report-type-badge type-<?php echo $report['report_type']; ?>">
                                <?php echo $report['report_type'] == 'uncollected' ? 'Uncollected Waste' : 'Pickup Request'; ?>
                            </span>
                        </div>
                        
                        <div>
                            <div class="text-gray-400 text-sm">Priority Level</div>
                            <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                <?php echo ucfirst($report['priority']); ?>
                            </span>
                        </div>
                        
                        <div>
                            <div class="text-gray-400 text-sm">Current Categories</div>
                            <div class="flex flex-wrap gap-2 mt-2">
                                <?php 
                                mysqli_data_seek($categories_result, 0);
                                while ($category = mysqli_fetch_assoc($categories_result)):
                                    if (in_array($category['category_id'], $selected_categories)):
                                ?>
                                <span class="px-3 py-1 bg-gradient-to-r from-green-500 to-emerald-600 text-white text-xs rounded-full">
                                    <?php echo $category['category_name']; ?>
                                </span>
                                <?php 
                                    endif;
                                endwhile; 
                                ?>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Edit Form -->
            <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                <div class="flex justify-between items-center mb-6">
                    <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                        <i class="fas fa-edit text-green-400 mr-3"></i>
                        Edit Report Information
                    </h2>
                </div>
                
                <?php if (isset($success)): ?>
                <div class="alert-success mb-6">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                </div>
                <?php endif; ?>
                
                <?php if (isset($error)): ?>
                <div class="alert-error mb-6">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
                <?php endif; ?>
                
                <form method="POST" action="" enctype="multipart/form-data">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Title *</label>
                            <input type="text" name="title" class="form-input" 
                                   value="<?php echo htmlspecialchars($report['title']); ?>" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Report Type *</label>
                            <div class="radio-group">
                                <div class="radio-option">
                                    <input type="radio" id="uncollected" name="report_type" value="uncollected"
                                           class="radio-input"
                                           <?php echo $report['report_type'] == 'uncollected' ? 'checked' : ''; ?> required>
                                    <label for="uncollected" class="radio-label">
                                        <i class="fas fa-trash mr-2"></i> Uncollected Waste
                                    </label>
                                </div>
                                <div class="radio-option">
                                    <input type="radio" id="pickup_request" name="report_type" value="pickup_request"
                                           class="radio-input"
                                           <?php echo $report['report_type'] == 'pickup_request' ? 'checked' : ''; ?> required>
                                    <label for="pickup_request" class="radio-label">
                                        <i class="fas fa-truck mr-2"></i> Pickup Request
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group mt-6">
                        <label class="form-label">Description *</label>
                        <textarea name="description" class="form-textarea" required><?php echo htmlspecialchars($report['description']); ?></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Address *</label>
                        <input type="text" name="address" class="form-input" 
                               value="<?php echo htmlspecialchars($report['address']); ?>" required>
                    </div>
                    
                    <div class="form-group">
                        <label class="form-label">Waste Categories</label>
                        <div class="checkbox-group">
                            <?php
                            mysqli_data_seek($categories_result, 0);
                            while ($category = mysqli_fetch_assoc($categories_result)):
                            ?>
                            <div class="checkbox-option">
                                <input type="checkbox"
                                       id="category_<?php echo $category['category_id']; ?>"
                                       name="categories[]"
                                       value="<?php echo $category['category_id']; ?>"
                                       class="checkbox-input"
                                       <?php echo in_array($category['category_id'], $selected_categories) ? 'checked' : ''; ?>>
                                <label for="category_<?php echo $category['category_id']; ?>" class="checkbox-label">
                                    <i class="category-icon <?php echo $category['icon_class']; ?>"></i>
                                    <span><?php echo $category['category_name']; ?></span>
                                </label>
                            </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                        <div class="form-group">
                            <label class="form-label">Priority Level</label>
                            <select name="priority" class="form-select">
                                <option value="low" <?php echo $report['priority'] == 'low' ? 'selected' : ''; ?>>Low - Minor issue</option>
                                <option value="medium" <?php echo $report['priority'] == 'medium' ? 'selected' : ''; ?>>Medium - Regular issue</option>
                                <option value="high" <?php echo $report['priority'] == 'high' ? 'selected' : ''; ?>>High - Urgent issue</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group mt-6">
                        <label class="form-label">Current Image</label>
                        <div class="image-preview-container">
                            <?php if (!empty($report['image_path'])):
                                // Determine the correct image URL
                                $display_image = $report['image_path'];
                                if (!filter_var($display_image, FILTER_VALIDATE_URL)) {
                                    $display_image = '/' . ltrim($report['image_path'], '/');
                                }
                            ?>
                                <div class="image-preview">
                                    <img src="<?php echo htmlspecialchars($display_image); ?>" 
                                         alt="Current Image"
                                         onerror="this.style.display='none'; document.getElementById('no-image-message').style.display='block';">
                                    <div id="no-image-message" class="no-image" style="display: none;">
                                        Image not found at specified path
                                    </div>
                                    <div class="current-image-label mt-2">Keep current image or upload new:</div>
                                </div>
                            <?php else: ?>
                                <div class="no-image">No image currently attached</div>
                            <?php endif; ?>
                        </div>
                        
                        <div class="file-input-wrapper">
                            <div class="file-input">
                                <label class="file-label">
                                    <input type="file" name="image" accept="image/*" onchange="previewImage(this)">
                                    <i class="fas fa-cloud-upload-alt"></i>
                                    <span>Click to upload new image</span>
                                    <span class="file-name" id="file-name">No file chosen</span>
                                </label>
                            </div>
                            <div class="image-preview mt-4" id="image-preview" style="display: none;">
                                <img id="preview-img" src="#" alt="Preview">
                            </div>
                        </div>
                    </div>
                    
                    <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 mt-8">
                        <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn-cancel">
                            <i class="fas fa-times mr-2"></i> Cancel
                        </a>
                        <button type="submit" class="btn-submit">
                            <i class="fas fa-save mr-2"></i> Save Changes
                        </button>
                    </div>
                </form>
            </div>
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
                            Administrative portal for managing waste management systems and community contributions.
                        </p>
                    </div>
                    
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-8 md:gap-16 w-full md:w-2/3">
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-blue-400">Admin Links</h4>
                            <ul class="space-y-2">
                                <li><a href="admin_dashboard.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Dashboard</a></li>
                                <li><a href="admin_reports.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Reports</a></li>
                                <li><a href="admin_customers.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Customers</a></li>
                                <li><a href="admin_teams.php" class="text-gray-400 hover:text-white transition-colors text-sm md:text-base">Teams</a></li>
                            </ul>
                        </div>
                        <div>
                            <h4 class="text-lg font-bold mb-4 text-purple-400">Admin Support</h4>
                            <ul class="space-y-2">
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-envelope mr-2 text-sm mt-1"></i> admin@wastewise.com
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-phone mr-2 text-sm mt-1"></i> +1 (555) 987-6543
                                </li>
                                <li class="text-gray-400 text-sm md:text-base flex items-start">
                                    <i class="fas fa-shield-alt mr-2 text-sm mt-1"></i> Secure Admin Portal
                                </li>
                            </ul>
                        </div>
                    </div>
                </div>
                
                <div class="border-t border-gray-800 mt-8 pt-8 text-center text-gray-400 text-sm md:text-base">
                    <p>&copy; 2024 WasteWise Admin Portal. Restricted access only.</p>
                    <p class="mt-2">Editing Report: <span class="text-green-400">#<?php echo $report['report_id']; ?></span> - <?php echo htmlspecialchars($report['title']); ?></p>
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
        
        // Toggle User Menu Dropdown
        function toggleUserMenu() {
            const dropdown = document.getElementById('userMenuDropdown');
            dropdown.classList.toggle('hidden');
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
            
            // Close user menu dropdown
            const userMenuBtn = document.querySelector('[onclick="toggleUserMenu()"]');
            const userMenuDropdown = document.getElementById('userMenuDropdown');
            if (userMenuDropdown && !userMenuDropdown.classList.contains('hidden') &&
                !userMenuDropdown.contains(event.target) && 
                userMenuBtn && !userMenuBtn.contains(event.target)) {
                userMenuDropdown.classList.add('hidden');
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
        
        // Improve mobile performance by disabling some animations on scroll
        let scrollTimer;
        window.addEventListener('scroll', function() {
            document.body.classList.add('disable-animations');
            clearTimeout(scrollTimer);
            scrollTimer = setTimeout(function() {
                document.body.classList.remove('disable-animations');
            }, 100);
        });
        
        // Add hover effects to cards
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.hover-lift');
            cards.forEach(card => {
                card.addEventListener('mouseenter', function() {
                    this.style.transform = 'translateY(-5px)';
                    this.style.boxShadow = '0 10px 25px rgba(0, 0, 0, 0.2)';
                });
                
                card.addEventListener('mouseleave', function() {
                    this.style.transform = 'translateY(0)';
                    this.style.boxShadow = 'none';
                });
            });
            
            // Add active class to current nav item
            const currentPage = window.location.pathname.split('/').pop();
            const navItems = document.querySelectorAll('.nav-item');
            navItems.forEach(item => {
                const href = item.getAttribute('href');
                if (href && href.includes(currentPage)) {
                    item.classList.add('active');
                }
            });
            
            // Image preview functionality
            function previewImage(input) {
                const preview = document.getElementById('image-preview');
                const previewImg = document.getElementById('preview-img');
                const fileName = document.getElementById('file-name');
                
                if (input.files && input.files[0]) {
                    const reader = new FileReader();
                    
                    reader.onload = function(e) {
                        previewImg.src = e.target.result;
                        preview.style.display = 'block';
                        fileName.textContent = input.files[0].name;
                    }
                    
                    reader.readAsDataURL(input.files[0]);
                } else {
                    preview.style.display = 'none';
                    fileName.textContent = 'No file chosen';
                }
            }
            
            // Form validation
            const form = document.querySelector('form');
            if (form) {
                form.addEventListener('submit', function(e) {
                    const requiredFields = form.querySelectorAll('[required]');
                    let valid = true;
                    
                    requiredFields.forEach(field => {
                        if (!field.value.trim()) {
                            valid = false;
                            field.style.borderColor = '#ef4444';
                        } else {
                            field.style.borderColor = '';
                        }
                    });
                    
                    if (!valid) {
                        e.preventDefault();
                        alert('Please fill in all required fields.');
                    }
                });
            }
        });
    </script>
</body>
</html>