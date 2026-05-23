<?php
// send_update_notification.php
require_once '../config.php';

$conn = getDBConnection();

// Check if admin is logged in
if (!isAdmin()) {
    redirect('index.php');
}

// Check if report ID is provided
if (!isset($_GET['id'])) {
    redirect('admin_reports.php');
}

$report_id = (int)$_GET['id'];

// Get report details with customer information
$query = "SELECT r.*, c.full_name as customer_name, c.customer_email, 
          c.phone as customer_phone, a.full_name as assigned_admin
          FROM reports r 
          JOIN customer c ON r.customer_id = c.customer_id 
          LEFT JOIN admin a ON r.assigned_to = a.admin_id 
          WHERE r.report_id = $report_id";
$result = mysqli_query($conn, $query);

if (mysqli_num_rows($result) == 0) {
    redirect('admin_reports.php');
}

$report = mysqli_fetch_assoc($result);

// Get latest status history
$history_query = "SELECT * FROM report_status_history 
                  WHERE report_id = $report_id 
                  ORDER BY created_at DESC LIMIT 1";
$history_result = mysqli_query($conn, $history_query);
$latest_update = mysqli_fetch_assoc($history_result);

// Get available admins for assignment
$admins_query = "SELECT admin_id, full_name FROM admin WHERE status = 'active'";
$admins_result = mysqli_query($conn, $admins_query);

// Handle form submission
$message = '';
$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['send_notification'])) {
        $subject = sanitize($_POST['subject']);
        $notification_type = sanitize($_POST['notification_type']);
        $custom_message = sanitize($_POST['message']);
        $send_email = isset($_POST['send_email']) ? true : false;
        $send_sms = isset($_POST['send_sms']) ? true : false;
        
        // Prepare notification message
        if ($notification_type == 'status_update') {
            $default_message = "Dear " . $report['customer_name'] . ",\n\n" .
                              "Your report \"" . $report['title'] . "\" (ID: #" . $report['report_id'] . ") " .
                              "has been updated to: " . ucfirst(str_replace('_', ' ', $report['status'])) . "\n\n" .
                              "Current Status: " . ucfirst(str_replace('_', ' ', $report['status'])) . "\n" .
                              "Priority: " . ucfirst($report['priority']) . "\n";
            
            if (!empty($report['assigned_admin'])) {
                $default_message .= "Assigned To: " . $report['assigned_admin'] . "\n";
            }
            
            if ($latest_update && !empty($latest_update['notes'])) {
                $default_message .= "\nUpdate Notes: " . $latest_update['notes'] . "\n";
            }
            
            $default_message .= "\nYou can view the full report details here:\n" .
                               base_url("customer_report_status.php?id=" . $report_id) . "\n\n" .
                               "Thank you for using WasteWise.\n" .
                               "Community Waste Management System";
            
            $message_content = !empty($custom_message) ? $custom_message : $default_message;
            
        } elseif ($notification_type == 'custom') {
            $message_content = $custom_message;
        }
        
        // Save notification in database
        $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                              VALUES ({$report['customer_id']}, 'customer', '$subject', 
                              '" . mysqli_real_escape_string($conn, $message_content) . "', 
                              '$notification_type')";
        
        if (mysqli_query($conn, $notification_query)) {
            $notification_id = mysqli_insert_id($conn);
            $message = "Notification saved successfully! ";
            
            // Send email if selected
            if ($send_email && !empty($report['customer_email'])) {
                $headers = "From: WasteWise <noreply@wastewise.com>\r\n";
                $headers .= "Content-Type: text/plain; charset=UTF-8\r\n";
                $email_sent = mail($report['customer_email'], $subject, $message_content, $headers);
                if ($email_sent) {
                    $message .= "Email sent to " . $report['customer_email'] . ". ";
                } else {
                    $error .= "Failed to send email. ";
                }
            }
            
            // Send SMS if selected
            if ($send_sms && !empty($report['customer_phone'])) {
                $sms_log_query = "INSERT INTO notification_logs (notification_id, type, recipient, status, notes) 
                                 VALUES ($notification_id, 'sms', '{$report['customer_phone']}', 'pending', 'SMS requires API integration')";
                mysqli_query($conn, $sms_log_query);
                $message .= "SMS notification queued (requires API setup). ";
            }
            
            if (!$error) {
                $message .= "Notification ID: #$notification_id";
            }
            
        } else {
            $error = "Failed to save notification: " . mysqli_error($conn);
        }
    }
    
    // Handle status update
    if (isset($_POST['update_status'])) {
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
            $history_insert_query = "INSERT INTO report_status_history (report_id, old_status, new_status, changed_by, user_type, notes)
                             VALUES ($report_id, '$old_status', '$new_status', {$_SESSION['user_id']}, 'admin', '$notes')";
            mysqli_query($conn, $history_insert_query);
            
            // Create notification for customer
            $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                                  VALUES ({$report['customer_id']}, 'customer', 'Report Status Updated', 
                                  'Your report \"{$report['title']}\" status has been changed to $new_status', 'status_update')";
            mysqli_query($conn, $notification_query);
            
            $success = "Report status updated successfully!";
            
            // Refresh report data
            $result = mysqli_query($conn, $query);
            $report = mysqli_fetch_assoc($result);
            
            // Refresh history data - Use a different variable name
            $history_refresh_query = "SELECT * FROM report_status_history WHERE report_id = $report_id ORDER BY created_at DESC LIMIT 1";
            $history_refresh_result = mysqli_query($conn, $history_refresh_query);
            $latest_update = mysqli_fetch_assoc($history_refresh_result);
        } else {
            $error = "Error updating report: " . mysqli_error($conn);
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Manage Report - WasteWise Admin</title>
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
            min-height: 150px;
            resize: vertical;
            transition: all 0.3s;
            font-family: 'Inter', monospace;
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
        
        /* Report info grid */
        .report-info-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
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
            font-size: 1.125rem;
            font-weight: 600;
        }
        
        /* Priority badges */
        .priority-badge {
            padding: 0.5rem 1rem;
            border-radius: 20px;
            font-size: 0.75rem;
            font-weight: 600;
        }
        
        .priority-low { background: rgba(34, 197, 94, 0.2); color: #22c55e; border: 1px solid rgba(34, 197, 94, 0.3); }
        .priority-medium { background: rgba(234, 179, 8, 0.2); color: #eab308; border: 1px solid rgba(234, 179, 8, 0.3); }
        .priority-high { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        
        /* Action buttons */
        .action-buttons {
            display: flex;
            gap: 1rem;
            margin-top: 2rem;
            flex-wrap: wrap;
        }
        
        .btn-back {
            background: rgba(107, 114, 128, 0.2);
            color: rgba(255, 255, 255, 0.8);
            border: 1px solid rgba(107, 114, 128, 0.3);
            border-radius: 0.75rem;
            padding: 1rem 2rem;
            font-weight: 600;
            font-size: 1rem;
            cursor: pointer;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            text-decoration: none;
        }
        
        .btn-back:hover {
            background: rgba(107, 114, 128, 0.3);
            color: white;
        }
        
        /* Checkbox styles */
        .checkbox-group {
            display: flex;
            flex-wrap: wrap;
            gap: 1.5rem;
            margin-top: 1rem;
        }
        
        .checkbox-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            cursor: pointer;
        }
        
        .checkbox-input {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 4px;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .checkbox-input:checked {
            background: #4ade80;
            border-color: #4ade80;
        }
        
        .checkbox-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        /* Tab styles */
        .tab-buttons {
            display: flex;
            gap: 0.5rem;
            margin-bottom: 2rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .tab-btn {
            padding: 1rem 1.5rem;
            background: transparent;
            border: none;
            color: rgba(255, 255, 255, 0.6);
            font-weight: 500;
            font-size: 0.875rem;
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .tab-btn:hover {
            color: white;
        }
        
        .tab-btn.active {
            color: #4ade80;
        }
        
        .tab-btn.active::after {
            content: '';
            position: absolute;
            bottom: -1px;
            left: 0;
            right: 0;
            height: 3px;
            background: #4ade80;
            border-radius: 3px 3px 0 0;
        }
        
        .tab-content {
            display: none;
            animation: fadeIn 0.5s ease-out;
        }
        
        .tab-content.active {
            display: block;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        /* Radio button styles */
        .radio-group {
            display: flex;
            gap: 1rem;
            margin-top: 0.5rem;
        }
        
        .radio-item {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }
        
        .radio-input {
            width: 18px;
            height: 18px;
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 50%;
            background: transparent;
            cursor: pointer;
            transition: all 0.3s;
        }
        
        .radio-input:checked {
            background: #4ade80;
            border-color: #4ade80;
        }
        
        .radio-label {
            color: rgba(255, 255, 255, 0.8);
            font-size: 0.875rem;
            cursor: pointer;
        }
        
        /* Preview section */
        .preview-section {
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 1.5rem;
            margin-top: 2rem;
            display: none;
        }
        
        .preview-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1rem;
        }
        
        .preview-title {
            color: white;
            font-weight: 600;
            font-size: 1.125rem;
        }
        
        .preview-content {
            background: rgba(0, 0, 0, 0.3);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.5rem;
            padding: 1rem;
            max-height: 300px;
            overflow-y: auto;
            font-family: 'Courier New', monospace;
            font-size: 0.875rem;
            line-height: 1.5;
            color: rgba(255, 255, 255, 0.8);
            white-space: pre-wrap;
            word-wrap: break-word;
        }
        
        .btn-preview {
            background: rgba(59, 130, 246, 0.2);
            color: #3b82f6;
            border: 1px solid rgba(59, 130, 246, 0.3);
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
        
        .btn-preview:hover {
            background: rgba(59, 130, 246, 0.3);
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
                
                <a href="admin_reports.php" class="nav-item flex items-center space-x-3 p-4 text-white rounded-lg hover:bg-white/5 transition-colors">
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
                <div class="text-gray-400 text-sm mb-3">Managing Report</div>
                <div class="bg-white/5 p-4 rounded-lg">
                    <div class="flex items-center space-x-3">
                        <div class="w-10 h-10 rounded-full bg-gradient-to-r from-blue-500 to-purple-600 flex items-center justify-center">
                            <i class="fas fa-flag text-white"></i>
                        </div>
                        <div>
                            <div class="text-white text-sm font-semibold">#<?php echo $report['report_id']; ?></div>
                            <div class="text-gray-400 text-xs"><?php echo htmlspecialchars($report['customer_name']); ?></div>
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
                <h1 class="text-white text-xl md:text-2xl font-bold">Manage Report</h1>
                <p class="text-gray-400 text-sm">Report ID: #<?php echo $report['report_id']; ?></p>
            </div>
            
            <!-- Desktop Right Actions -->
            <div class="flex items-center space-x-4">
                <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn-back">
                    <i class="fas fa-arrow-left mr-2"></i> Back to Report
                </a>
                
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
            <?php if(isset($success)): ?>
            <div class="alert-success fade-in-up">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>

            <!-- Report Information -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mb-6 fade-in-up">
                <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                    <i class="fas fa-file-alt text-blue-400 mr-3"></i>
                    Report Information
                </h2>
                
                <div class="report-info-grid">
                    <div class="info-card hover-lift">
                        <div class="info-label">Report Details</div>
                        <div class="text-white text-lg font-bold mb-2"><?php echo htmlspecialchars($report['title']); ?></div>
                        <div class="flex items-center space-x-2">
                            <span class="status-badge status-<?php echo $report['status']; ?>">
                                <?php echo ucfirst(str_replace('_', ' ', $report['status'])); ?>
                            </span>
                            <span class="priority-badge priority-<?php echo $report['priority']; ?>">
                                <?php echo ucfirst($report['priority']); ?> Priority
                            </span>
                        </div>
                    </div>
                    
                    <div class="info-card hover-lift">
                        <div class="info-label">Customer Information</div>
                        <div class="text-white text-lg font-bold mb-2"><?php echo htmlspecialchars($report['customer_name']); ?></div>
                        <div class="text-gray-300 text-sm"><?php echo htmlspecialchars($report['customer_email']); ?></div>
                        <div class="text-gray-300 text-sm"><?php echo htmlspecialchars($report['customer_phone']); ?></div>
                    </div>
                    
                    <div class="info-card hover-lift">
                        <div class="info-label">Assignment & Timeline</div>
                        <?php if(!empty($report['assigned_admin'])): ?>
                        <div class="text-white text-sm mb-1">Assigned to: <?php echo htmlspecialchars($report['assigned_admin']); ?></div>
                        <?php endif; ?>
                        <div class="text-gray-300 text-sm">Created: <?php echo date('M j, Y', strtotime($report['created_at'])); ?></div>
                        <div class="text-gray-300 text-sm">Last updated: <?php echo date('M j, Y', strtotime($report['updated_at'])); ?></div>
                    </div>
                </div>
                
                <?php if($latest_update && !empty($latest_update['notes'])): ?>
                <div class="mt-4 p-4 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-lg border border-blue-500/20">
                    <div class="flex items-center text-blue-400 mb-1">
                        <i class="fas fa-sticky-note mr-2"></i>
                        <span class="font-semibold">Latest Update Notes</span>
                    </div>
                    <p class="text-gray-300 text-sm"><?php echo htmlspecialchars($latest_update['notes']); ?></p>
                </div>
                <?php endif; ?>
            </div>

            <!-- Main Content Tabs -->
            <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="status-tab">
                        <i class="fas fa-sync-alt"></i>
                        <span>Update Status</span>
                    </button>
                </div>

                <!-- Status Update Tab -->
                <div id="status-tab" class="tab-content active">
                    <form method="POST" action="">
                        <div class="grid grid-cols-1 md:grid-cols-3 gap-6 mb-6">
                            <div class="form-group">
                                <label class="form-label">Status *</label>
                                <select name="status" class="form-select" required>
                                    <option value="pending" <?php echo $report['status'] == 'pending' ? 'selected' : ''; ?>>Pending</option>
                                    <option value="in_progress" <?php echo $report['status'] == 'in_progress' ? 'selected' : ''; ?>>In Progress</option>
                                    <option value="completed" <?php echo $report['status'] == 'completed' ? 'selected' : ''; ?>>Completed</option>
                                    <option value="rejected" <?php echo $report['status'] == 'rejected' ? 'selected' : ''; ?>>Rejected</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Priority *</label>
                                <select name="priority" class="form-select" required>
                                    <option value="low" <?php echo $report['priority'] == 'low' ? 'selected' : ''; ?>>Low</option>
                                    <option value="medium" <?php echo $report['priority'] == 'medium' ? 'selected' : ''; ?>>Medium</option>
                                    <option value="high" <?php echo $report['priority'] == 'high' ? 'selected' : ''; ?>>High</option>
                                </select>
                            </div>
                            
                            <div class="form-group">
                                <label class="form-label">Assign To</label>
                                <select name="assigned_to" class="form-select">
                                    <option value="">-- Not Assigned --</option>
                                    <?php 
                                    mysqli_data_seek($admins_result, 0);
                                    while($admin = mysqli_fetch_assoc($admins_result)): 
                                    ?>
                                    <option value="<?php echo $admin['admin_id']; ?>" 
                                        <?php echo $report['assigned_to'] == $admin['admin_id'] ? 'selected' : ''; ?>>
                                        <?php echo htmlspecialchars($admin['full_name']); ?>
                                    </option>
                                    <?php endwhile; ?>
                                </select>
                            </div>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Update Notes (Optional)</label>
                            <textarea name="notes" class="form-textarea" placeholder="Add notes about this update..."><?php echo isset($_POST['notes']) ? htmlspecialchars($_POST['notes']) : ''; ?></textarea>
                            <div class="text-gray-400 text-xs mt-2">These notes will be recorded in the report history and may be included in notifications.</div>
                        </div>
                        
                        <div class="action-buttons">
                            <button type="submit" name="update_status" class="btn-submit">
                                <i class="fas fa-save mr-2"></i> Update Report Status
                            </button>
                            <a href="view_report.php?id=<?php echo $report_id; ?>" class="btn-cancel">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </a>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Quick Tips -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mt-6 fade-in-up">
                <h3 class="text-white text-lg font-bold mb-4 flex items-center">
                    <i class="fas fa-lightbulb text-yellow-400 mr-3"></i>
                    Tips for Effective Status Updates
                </h3>
                
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div class="bg-gradient-to-r from-green-500/10 to-blue-500/10 p-4 rounded-lg border border-green-500/20">
                        <div class="flex items-center text-green-400 mb-2">
                            <i class="fas fa-check-circle mr-2"></i>
                            <span class="font-semibold">Best Practices</span>
                        </div>
                        <ul class="text-gray-300 text-sm space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-chevron-right text-green-400 mt-1 mr-2 text-xs"></i>
                                <span>Update status promptly when changes occur</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-chevron-right text-green-400 mt-1 mr-2 text-xs"></i>
                                <span>Provide clear, specific notes about the update</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-chevron-right text-green-400 mt-1 mr-2 text-xs"></i>
                                <span>Assign appropriate priority based on urgency</span>
                            </li>
                        </ul>
                    </div>
                    
                    <div class="bg-gradient-to-r from-blue-500/10 to-purple-500/10 p-4 rounded-lg border border-blue-500/20">
                        <div class="flex items-center text-blue-400 mb-2">
                            <i class="fas fa-bell mr-2"></i>
                            <span class="font-semibold">Notification Impact</span>
                        </div>
                        <ul class="text-gray-300 text-sm space-y-2">
                            <li class="flex items-start">
                                <i class="fas fa-chevron-right text-blue-400 mt-1 mr-2 text-xs"></i>
                                <span>Customers are automatically notified of status changes</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-chevron-right text-blue-400 mt-1 mr-2 text-xs"></i>
                                <span>Include relevant details in the notes field</span>
                            </li>
                            <li class="flex items-start">
                                <i class="fas fa-chevron-right text-blue-400 mt-1 mr-2 text-xs"></i>
                                <span>Consider assignment impact on response times</span>
                            </li>
                        </ul>
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
                    <p class="mt-2">Managing Report: <span class="text-green-400">#<?php echo $report['report_id']; ?></span> - <?php echo htmlspecialchars($report['title']); ?></p>
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
        
        // Tab functionality
        document.addEventListener('DOMContentLoaded', function() {
            const tabButtons = document.querySelectorAll('.tab-btn');
            const tabContents = document.querySelectorAll('.tab-content');
            
            tabButtons.forEach(button => {
                button.addEventListener('click', () => {
                    const tabId = button.getAttribute('data-tab');
                    
                    // Remove active class from all buttons and contents
                    tabButtons.forEach(btn => btn.classList.remove('active'));
                    tabContents.forEach(content => content.classList.remove('active'));
                    
                    // Add active class to clicked button and corresponding content
                    button.classList.add('active');
                    document.getElementById(tabId).classList.add('active');
                });
            });
            
            // Form validation
            const forms = document.querySelectorAll('form');
            forms.forEach(form => {
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
            });
            
            // Add hover effects to cards
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
    </script>
</body>
</html>