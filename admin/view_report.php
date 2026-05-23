<?php
require_once '../config.php';

$conn = getDBConnection();

if (!isset($_GET['id'])) {
    redirect('admin_reports.php');
}

$report_id = (int)$_GET['id'];

// Get report details
$query = "SELECT r.*, c.full_name as customer_name, c.customer_email, c.phone as customer_phone, c.address as customer_address,
          a.full_name as assigned_admin
          FROM reports r 
          JOIN customer c ON r.customer_id = c.customer_id 
          LEFT JOIN admin a ON r.assigned_to = a.admin_id 
          WHERE r.report_id = $report_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect('admin_reports.php');
}

$report = mysqli_fetch_assoc($result);

// FIXED: Check if image exists using relative path from document root
$image_path = $report['image_path'];
$image_exists = false;
$display_image_path = '';

if (!empty($image_path)) {
    // Clean up the path
    $clean_path = ltrim($image_path, '/\\');

    // Try document root path
    $doc_root = $_SERVER['DOCUMENT_ROOT'];
    $full_path = $doc_root . '/' . $clean_path;

    // Try alternative paths
    $full_path_alt1 = str_replace('\\', '/', $full_path);
    $full_path_alt2 = str_replace('/', '\\', $full_path);

    // Check all possible paths
    if (file_exists($full_path)) {
        $image_exists = true;
        $display_image_path = $clean_path;
    } elseif (file_exists($full_path_alt1)) {
        $image_exists = true;
        $display_image_path = $clean_path;
    } elseif (file_exists($full_path_alt2)) {
        $image_exists = true;
        $display_image_path = $clean_path;
    } else {
        // Try relative path
        $relative_path = __DIR__ . '/../' . $clean_path;
        if (file_exists($relative_path)) {
            $image_exists = true;
            $display_image_path = '../' . $clean_path;
        }
    }
}

// Get waste categories for this report
$categories_query = "SELECT wc.* FROM waste_categories wc 
                    JOIN report_categories rc ON wc.category_id = rc.category_id 
                    WHERE rc.report_id = $report_id";
$categories_result = mysqli_query($conn, $categories_query);

// Get status history
$history_query = "SELECT * FROM report_status_history WHERE report_id = $report_id ORDER BY created_at DESC";
$history_result = mysqli_query($conn, $history_query);

// Handle status update
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['update_status'])) {
    $new_status = sanitize($_POST['status']);
    $notes = sanitize($_POST['notes']);
    $assigned_to = isset($_POST['assigned_to']) ? (int)$_POST['assigned_to'] : null;
    $priority = sanitize($_POST['priority']);

    // Get old status
    $old_status = $report['status'];

    // Update report
    $update_query = "UPDATE reports SET 
                    status = '$new_status',
                    assigned_to = " . ($assigned_to ?: 'NULL') . ",
                    priority = '$priority',
                    updated_at = NOW()
                    WHERE report_id = $report_id";

    if (mysqli_query($conn, $update_query)) {
        // Record status history
        $history_query = "INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, user_type, notes)
                         VALUES ($report_id, '$old_status', '$new_status', {$_SESSION['user_id']}, 'admin', '$notes')";
        mysqli_query($conn, $history_query);

        // Create notification for customer
        $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                              VALUES ({$report['customer_id']}, 'customer', 'Report Status Updated', 
                              'Your report \"{$report['title']}\" status has been changed to $new_status', 'status_update')";
        mysqli_query($conn, $notification_query);

        $success = "Report updated successfully!";

        // Refresh report data
        $result = mysqli_query($conn, $query);
        $report = mysqli_fetch_assoc($result);
    } else {
        $error = "Error updating report: " . mysqli_error($conn);
    }
}

// Get available admins for assignment
$admins_query = "SELECT admin_id, full_name FROM admin WHERE status = 'active'";
$admins_result = mysqli_query($conn, $admins_query);
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>View Report - WasteWise Admin</title>
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

        html,
        body {
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
            background: linear-gradient(90deg,
                    #4ade80,
                    #22d3ee,
                    #4ade80);
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

        .stagger-animation>* {
            opacity: 0;
            animation: fadeInUp 0.8s ease-out forwards;
        }

        .stagger-animation>*:nth-child(1) {
            animation-delay: 0.1s;
        }

        .stagger-animation>*:nth-child(2) {
            animation-delay: 0.2s;
        }

        .stagger-animation>*:nth-child(3) {
            animation-delay: 0.3s;
        }

        .stagger-animation>*:nth-child(4) {
            animation-delay: 0.4s;
        }

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

            a,
            button {
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

            0%,
            20%,
            50%,
            80%,
            100% {
                transform: translateY(0);
            }

            40% {
                transform: translateY(-10px);
            }

            60% {
                transform: translateY(-5px);
            }
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

        .status-pending {
            background: rgba(254, 243, 199, 0.2);
            color: #fef3c7;
            border: 1px solid rgba(254, 243, 199, 0.3);
        }

        .status-in_progress {
            background: rgba(219, 234, 254, 0.2);
            color: #dbeafe;
            border: 1px solid rgba(219, 234, 254, 0.3);
        }

        .status-completed {
            background: rgba(220, 252, 231, 0.2);
            color: #dcfce7;
            border: 1px solid rgba(220, 252, 231, 0.3);
        }

        .status-rejected {
            background: rgba(254, 226, 226, 0.2);
            color: #fee2e2;
            border: 1px solid rgba(254, 226, 226, 0.3);
        }

        .btn-action {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
        }

        .btn-view {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-view:hover {
            background: rgba(59, 130, 246, 0.3);
        }

        .btn-edit {
            background: rgba(74, 222, 128, 0.2);
            color: #4ade80;
            border: 1px solid rgba(74, 222, 128, 0.3);
        }

        .btn-edit:hover {
            background: rgba(74, 222, 128, 0.3);
        }

        .btn-delete {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        .btn-delete:hover {
            background: rgba(239, 68, 68, 0.3);
        }

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

        /* Report specific styles */
        .info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-bottom: 2rem;
        }

        .info-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
        }

        .info-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            font-weight: 600;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            margin-bottom: 0.5rem;
        }

        .info-value {
            color: white;
            font-size: 1rem;
            font-weight: 500;
        }

        .priority-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }

        .priority-low {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .priority-medium {
            background: rgba(234, 179, 8, 0.2);
            color: #eab308;
            border: 1px solid rgba(234, 179, 8, 0.3);
        }

        .priority-high {
            background: rgba(239, 68, 68, 0.2);
            color: #ef4444;
            border: 1px solid rgba(239, 68, 68, 0.3);
        }

        /* Timeline styles */
        .timeline {
            position: relative;
            padding-left: 30px;
        }

        .timeline::before {
            content: '';
            position: absolute;
            left: 0;
            top: 0;
            bottom: 0;
            width: 2px;
            background: linear-gradient(180deg, #667eea, #764ba2);
        }

        .timeline-item {
            position: relative;
            margin-bottom: 1.5rem;
            padding-bottom: 1.5rem;
        }

        .timeline-item:last-child {
            margin-bottom: 0;
            padding-bottom: 0;
        }

        .timeline-item::before {
            content: '';
            position: absolute;
            left: -38px;
            top: 0;
            width: 16px;
            height: 16px;
            border-radius: 50%;
            background: #4ade80;
            border: 3px solid rgba(255, 255, 255, 0.2);
        }

        .timeline-date {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.75rem;
            margin-bottom: 0.5rem;
        }

        .timeline-content {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1rem;
        }

        .timeline-status {
            color: white;
            font-weight: 600;
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .timeline-details {
            color: rgba(255, 255, 255, 0.7);
            font-size: 0.875rem;
            margin-bottom: 0.5rem;
        }

        .timeline-user {
            color: #4ade80;
            font-size: 0.75rem;
        }

        /* Category badges */
        .category-badge {
            display: inline-flex;
            align-items: center;
            background: rgba(255, 255, 255, 0.05);
            color: rgba(255, 255, 255, 0.8);
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.875rem;
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }

        .category-icon {
            margin-right: 0.5rem;
            color: #4ade80;
        }

        /* Image styles - FIXED VERSION */
        .report-image-container {
            transition: all 0.3s ease;
            max-width: 600px;
            margin: 0 auto;
        }

        .report-image-container:hover {
            transform: scale(1.02);
        }

        .report-image {
            width: 100%;
            max-height: 400px;
            object-fit: contain;
            border-radius: 1rem;
            border: 2px solid rgba(255, 255, 255, 0.1);
            cursor: pointer;
        }

        .no-image {
            background: rgba(255, 255, 255, 0.05);
            border: 2px dashed rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 3rem 2rem;
            text-align: center;
        }

        .no-image-icon {
            font-size: 3rem;
            color: rgba(255, 255, 255, 0.2);
            margin-bottom: 1rem;
        }

        /* Description box */
        .description-box {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            margin-bottom: 1.5rem;
        }

        .description-content {
            color: rgba(255, 255, 255, 0.8);
            line-height: 1.6;
            white-space: pre-wrap;
        }

        /* Alert styles */
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

        /* Action buttons */
        .action-btn {
            padding: 0.75rem 1.5rem;
            border-radius: 0.75rem;
            font-weight: 600;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
            border: none;
        }

        .btn-back {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-back:hover {
            background: rgba(59, 130, 246, 0.3);
        }

        .btn-customer-view {
            background: rgba(139, 92, 246, 0.2);
            color: #8b5cf6;
            border: 1px solid rgba(139, 92, 246, 0.3);
        }

        .btn-customer-view:hover {
            background: rgba(139, 92, 246, 0.3);
        }

        .btn-notify {
            background: rgba(34, 197, 94, 0.2);
            color: #22c55e;
            border: 1px solid rgba(34, 197, 94, 0.3);
        }

        .btn-notify:hover {
            background: rgba(34, 197, 94, 0.3);
        }

        .btn-edit {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
        }

        .btn-edit:hover {
            background: rgba(59, 130, 246, 0.3);
        }

        /* Report header */
        .report-header {
            display: flex;
            justify-content: space-between;
            align-items: flex-start;
            margin-bottom: 2rem;
            padding-bottom: 1.5rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }

        .report-title-section {
            flex: 1;
        }

        .report-title {
            color: white;
            font-size: 1.75rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }

        .report-id {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
        }

        .report-status-section {
            display: flex;
            flex-direction: column;
            align-items: flex-end;
            gap: 0.5rem;
        }

        /* Section headings */
        .section-heading {
            color: white;
            font-size: 1.25rem;
            font-weight: 600;
            margin-bottom: 1rem;
            display: flex;
            align-items: center;
        }

        .section-heading i {
            margin-right: 0.5rem;
        }

        /* Image modal styles */
        .image-modal {
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 1000;
            opacity: 0;
            visibility: hidden;
            transition: opacity 0.3s, visibility 0.3s;
        }

        .image-modal.active {
            opacity: 1;
            visibility: visible;
        }

        .modal-image {
            max-width: 90%;
            max-height: 90%;
            object-fit: contain;
            border-radius: 1rem;
        }

        .close-modal {
            position: absolute;
            top: 20px;
            right: 20px;
            background: rgba(239, 68, 68, 0.8);
            color: white;
            border: none;
            width: 40px;
            height: 40px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            font-size: 1.2rem;
            transition: background 0.3s;
        }

        .close-modal:hover {
            background: rgba(239, 68, 68, 1);
        }
    </style>
</head>

<body class="dashboard-bg">
    <!-- Image Modal -->
    <div class="image-modal" id="imageModal">
        <button class="close-modal" onclick="closeImageModal()">
            <i class="fas fa-times"></i>
        </button>
        <img class="modal-image" id="modalImage" src="" alt="Full size image">
    </div>

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

            <!-- Quick Info -->
            <div class="mt-8 pt-6 border-t border-white/10">
                <div class="text-gray-400 text-sm mb-3">Viewing Report</div>
                <div class="bg-white/5 p-4 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-flag text-white"></i>
                        </div>
                        <div>
                            <div class="text-white text-sm font-semibold">#<?php echo $report['report_id']; ?></div>
                            <div class="text-gray-400 text-xs">
                                <?php echo date('M d, Y', strtotime($report['created_at'])); ?>
                            </div>
                        </div>
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
                <h1 class="text-white text-xl md:text-2xl font-bold">Report Details</h1>
                <p class="text-gray-400 text-sm">Report ID: #<?php echo $report['report_id']; ?></p>
            </div>

            <!-- Desktop Right Actions -->
            <div class="flex items-center space-x-4">
                <div class="flex space-x-2">
                    <a href="admin_reports.php" class="action-btn btn-back">
                        <i class="fas fa-arrow-left mr-2"></i> Back to Reports
                    </a>
                    
                </div>

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
            <!-- Alerts -->
            <?php if (isset($success)): ?>
                <div class="alert-success fade-in-up">
                    <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
                </div>
            <?php endif; ?>

            <?php if (isset($error)): ?>
                <div class="alert-error fade-in-up">
                    <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
                </div>
            <?php endif; ?>

            <!-- Report Container -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 fade-in-up">
                <!-- Report Header -->
                <div class="report-header">
                    <div class="report-title-section">
                        <h2 class="report-title"><?php echo htmlspecialchars($report['title']); ?></h2>
                        <div class="report-id">
                            Submitted on <?php echo date('F d, Y \a\t h:i A', strtotime($report['created_at'])); ?>
                        </div>
                    </div>
                    <div class="report-status-section">
                        <div class="flex items-center space-x-2">
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                            </span>
                            <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                <?php echo ucfirst($report['priority']); ?>
                            </span>
                        </div>
                        <div class="flex space-x-2 mt-2">
                            
                            <a href="send_update_notification.php?id=<?php echo $report_id; ?>" class="action-btn btn-notify">
                                <i class="fas fa-bell mr-1"></i> Notify
                            </a>
                        </div>
                    </div>
                </div>

                <!-- Report Information Grid -->
                <div class="info-grid mb-6">
                    <div class="info-card hover-lift">
                        <div class="info-label">Report Information</div>
                        <div class="space-y-2">
                            <div>
                                <div class="info-label">Report Type</div>
                                <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $report['report_type'])); ?></div>
                            </div>
                            <div>
                                <div class="info-label">Submitted</div>
                                <div class="info-value"><?php echo date('M d, Y', strtotime($report['created_at'])); ?></div>
                            </div>
                            <div>
                                <div class="info-label">Last Updated</div>
                                <div class="info-value"><?php echo date('M d, Y', strtotime($report['updated_at'])); ?></div>
                            </div>
                            <?php if ($report['assigned_admin']): ?>
                                <div>
                                    <div class="info-label">Assigned To</div>
                                    <div class="info-value"><?php echo htmlspecialchars($report['assigned_admin']); ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="info-card hover-lift">
                        <div class="info-label">Customer Information</div>
                        <div class="space-y-2">
                            <div>
                                <div class="info-label">Name</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['customer_name']); ?></div>
                            </div>
                            <div>
                                <div class="info-label">Email</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['customer_email']); ?></div>
                            </div>
                            <div>
                                <div class="info-label">Phone</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['customer_phone']); ?></div>
                            </div>
                            <div>
                                <div class="info-label">Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['customer_address']); ?></div>
                            </div>
                        </div>
                    </div>

                    <div class="info-card hover-lift">
                        <div class="info-label">Location Details</div>
                        <div class="space-y-2">
                            <div>
                                <div class="info-label">Report Address</div>
                                <div class="info-value"><?php echo htmlspecialchars($report['address']); ?></div>
                            </div>
                            <?php if ($report['latitude'] && $report['longitude']): ?>
                                <div>
                                    <div class="info-label">Coordinates</div>
                                    <div class="info-value"><?php echo $report['latitude']; ?>, <?php echo $report['longitude']; ?></div>
                                </div>
                            <?php endif; ?>
                        </div>
                    </div>
                </div>

                <!-- Description -->
                <div class="description-box mb-6">
                    <div class="section-heading">
                        <i class="fas fa-align-left text-blue-400"></i>
                        <span>Description</span>
                    </div>
                    <div class="description-content">
                        <?php echo nl2br(htmlspecialchars($report['description'])); ?>
                    </div>
                </div>

                <!-- Waste Categories -->
                <?php if (mysqli_num_rows($categories_result) > 0): ?>
                    <div class="mb-6">
                        <div class="section-heading">
                            <i class="fas fa-trash text-green-400"></i>
                            <span>Waste Categories</span>
                        </div>
                        <div class="flex flex-wrap gap-2">
                            <?php while ($category = mysqli_fetch_assoc($categories_result)): ?>
                                <span class="category-badge">
                                    <i class="category-icon <?php echo $category['icon_class']; ?>"></i>
                                    <?php echo htmlspecialchars($category['category_name']); ?>
                                </span>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Attached Image - FIXED WITH ABSOLUTE PATH -->
                <div class="glass-card rounded-2xl p-6 backdrop-blur-lg mb-6 fade-in-up">
                    <div class="flex items-center justify-between mb-6">
                        <h3 class="text-xl font-bold text-white">Attached Image</h3>
                        <div class="w-10 h-10 rounded-xl bg-pink-500/20 flex items-center justify-center">
                            <i class="fas fa-image text-pink-400"></i>
                        </div>
                    </div>

                    <?php if (!empty($report['image_path'])):
                        // FIX: Use absolute path from root
                        // Since your project is at http://localhost/Systems/
                        $image_path = $report['image_path'];

                        // Make it absolute by adding /Systems/ prefix if not already there
                        if (strpos($image_path, '/Systems/') === false) {
                            $web_path = '/Systems/' . $image_path;
                        } else {
                            $web_path = $image_path;
                        }

                        // Clean up any double slashes
                        $web_path = str_replace('//', '/', $web_path);

                        // Also check if file exists for debugging
                        $full_path = $_SERVER['DOCUMENT_ROOT'] . $web_path;
                        $file_exists = file_exists($full_path);
                    ?>

                        <div class="text-center">
                            <div class="report-image-container max-w-2xl mx-auto">
                                <img src="<?php echo htmlspecialchars($web_path); ?>"
                                    alt="Report Image"
                                    class="w-full h-auto max-h-96 object-contain rounded-xl shadow-lg border-2 border-white/10 cursor-pointer"
                                    onclick="openImageModal('<?php echo htmlspecialchars($web_path); ?>')">
                                <p class="text-gray-400 text-sm mt-2 text-center">Click image to view full size</p>
                            </div>
                        </div>

                    <?php else: ?>
                        <div class="text-center py-12">
                            <div class="w-24 h-24 mx-auto mb-6 rounded-full bg-white/10 flex items-center justify-center">
                                <i class="fas fa-image text-gray-400 text-3xl"></i>
                            </div>
                            <h3 class="text-gray-300 font-medium text-xl mb-2">No image attached</h3>
                            <p class="text-gray-400">No image was attached to this report</p>
                        </div>
                    <?php endif; ?>
                </div>

                <!-- Status History Timeline -->
                <?php if (mysqli_num_rows($history_result) > 0): ?>
                    <div class="mb-6">
                        <div class="section-heading">
                            <i class="fas fa-history text-purple-400"></i>
                            <span>Status History</span>
                        </div>
                        <div class="timeline">
                            <?php while ($history = mysqli_fetch_assoc($history_result)): ?>
                                <div class="timeline-item">
                                    <div class="timeline-date">
                                        <?php echo date('F d, Y h:i A', strtotime($history['created_at'])); ?>
                                    </div>
                                    <div class="timeline-content hover-lift">
                                        <div class="timeline-status">
                                            <?php if ($history['old_status']): ?>
                                                Changed from <?php echo ucfirst(str_replace('_', ' ', $history['old_status'])); ?>
                                                to <?php echo ucfirst(str_replace('_', ' ', $history['new_status'])); ?>
                                            <?php else: ?>
                                                Status set to <?php echo ucfirst(str_replace('_', ' ', $history['new_status'])); ?>
                                            <?php endif; ?>
                                        </div>
                                        <?php if ($history['notes']): ?>
                                            <div class="timeline-details"><?php echo htmlspecialchars($history['notes']); ?></div>
                                        <?php endif; ?>
                                        <div class="timeline-user">
                                            By <?php echo ucfirst($history['user_type']); ?>
                                            <?php echo $history['changed_by'] ? '(ID: ' . $history['changed_by'] . ')' : '(System)'; ?>
                                        </div>
                                    </div>
                                </div>
                            <?php endwhile; ?>
                        </div>
                    </div>
                <?php endif; ?>

                <!-- Quick Actions -->
                <div class="glass-card rounded-2xl p-6 mt-6">
                    <div class="section-heading">
                        <i class="fas fa-bolt text-green-400"></i>
                        <span>Quick Actions</span>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                        <a href="edit_report.php?id=<?php echo $report_id; ?>" class="action-btn btn-edit hover-lift">
                            <i class="fas fa-edit mr-2"></i> Edit Report Details
                        </a>
                        <a href="send_update_notification.php?id=<?php echo $report_id; ?>" class="action-btn btn-notify hover-lift">
                            <i class="fas fa-bell mr-2"></i> Send Customer Update
                        </a>
                        <a href="admin_reports.php" class="action-btn btn-back hover-lift">
                            <i class="fas fa-list mr-2"></i> Back to Reports List
                        </a>
                    </div>
                </div>
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
                    <p class="mt-2">Viewing Report: <span class="text-green-400">#<?php echo $report['report_id']; ?></span> - <?php echo htmlspecialchars($report['title']); ?></p>
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

        // Image Modal Functions
        function openImageModal(imageSrc) {
            const modal = document.getElementById('imageModal');
            const modalImage = document.getElementById('modalImage');

            modalImage.src = imageSrc;
            modal.classList.add('active');
            document.body.style.overflow = 'hidden';
        }

        function closeImageModal() {
            const modal = document.getElementById('imageModal');
            modal.classList.remove('active');
            document.body.style.overflow = 'auto';
        }

        // Close image modal with Escape key
        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeImageModal();
            }
        });

        // Close image modal when clicking outside
        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeImageModal();
            }
        });

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
        });
    </script>
</body>

</html>