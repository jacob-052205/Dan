<?php
require_once '../config.php';

if (!isAdmin()) {
    redirect('index.php');
}

$conn = getDBConnection();

// Get collection teams
$query = "SELECT * FROM collection_teams ORDER BY team_name";
$teams = mysqli_query($conn, $query);

// Get pending reports for assignment
$reports_query = "SELECT r.*, c.full_name as customer_name 
                  FROM reports r 
                  JOIN customer c ON r.customer_id = c.customer_id 
                  WHERE r.collection_status = 'not_assigned' 
                  OR (r.collection_status = 'assigned' AND r.assigned_team_id IS NULL)
                  ORDER BY r.created_at DESC";
$reports_result = mysqli_query($conn, $reports_query);

// Get tracking statistics
$stats_query = "SELECT 
               SUM(CASE WHEN status = 'on_duty' THEN 1 ELSE 0 END) as on_duty,
               SUM(CASE WHEN status = 'available' THEN 1 ELSE 0 END) as available,
               SUM(CASE WHEN status = 'offline' THEN 1 ELSE 0 END) as offline,
               COUNT(*) as total
               FROM collection_teams";
$stats_result = mysqli_query($conn, $stats_query);
$stats = mysqli_fetch_assoc($stats_result);

// Get teams with location data
$located_teams_query = "SELECT COUNT(*) as count FROM collection_teams WHERE current_latitude IS NOT NULL";
$located_result = mysqli_query($conn, $located_teams_query);
$located_teams = mysqli_fetch_assoc($located_result)['count'];

// Get pending reports count
$pending_reports_query = "SELECT COUNT(*) as count FROM reports WHERE collection_status = 'not_assigned'";
$pending_result = mysqli_query($conn, $pending_reports_query);
$pending_reports = mysqli_fetch_assoc($pending_result)['count'];

// Get active assignments
$active_assignments_query = "SELECT COUNT(*) as count FROM team_assignments WHERE status IN ('assigned', 'in_progress')";
$active_result = mysqli_query($conn, $active_assignments_query);
$active_assignments = mysqli_fetch_assoc($active_result)['count'];

// Handle team actions
if (isset($_GET['action'])) {
    $team_id = (int)$_GET['id'];
    
    switch ($_GET['action']) {
        case 'delete':
            $delete_query = "DELETE FROM collection_teams WHERE team_id = $team_id";
            if (mysqli_query($conn, $delete_query)) {
                $success = "Team deleted successfully!";
            } else {
                $error = "Error deleting team!";
            }
            break;
    }
    
    redirect('admin_teams.php');
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    if (isset($_POST['add_team'])) {
        $team_name = sanitize($_POST['team_name']);
        $vehicle_number = sanitize($_POST['vehicle_number']);
        $status = sanitize($_POST['status']);
        
        $insert_query = "INSERT INTO collection_teams (team_name, vehicle_number, status) 
                        VALUES ('$team_name', '$vehicle_number', '$status')";
        
        if (mysqli_query($conn, $insert_query)) {
            $success = "Team added successfully!";
            redirect('admin_teams.php');
        } else {
            $error = "Error adding team: " . mysqli_error($conn);
        }
    }
    
    if (isset($_POST['update_team'])) {
        $team_id = (int)$_POST['team_id'];
        $team_name = sanitize($_POST['team_name']);
        $vehicle_number = sanitize($_POST['vehicle_number']);
        $status = sanitize($_POST['status']);
        
        $update_query = "UPDATE collection_teams SET 
                        team_name = '$team_name',
                        vehicle_number = '$vehicle_number',
                        status = '$status'
                        WHERE team_id = $team_id";
        
        if (mysqli_query($conn, $update_query)) {
            $success = "Team updated successfully!";
            redirect('admin_teams.php');
        } else {
            $error = "Error updating team: " . mysqli_error($conn);
        }
    }
    
    if (isset($_POST['assign_reports'])) {
        $team_id = (int)$_POST['team_id'];
        $report_ids = isset($_POST['report_ids']) ? $_POST['report_ids'] : [];
        
        if (!empty($report_ids)) {
            foreach ($report_ids as $report_id) {
                $report_id = (int)$report_id;
                
                // Update report
                $update_report = "UPDATE reports SET 
                                 assigned_team_id = $team_id,
                                 collection_status = 'assigned'
                                 WHERE report_id = $report_id";
                mysqli_query($conn, $update_report);
                
                // Create assignment
                $assign_query = "INSERT INTO team_assignments (team_id, report_id, status) 
                                VALUES ($team_id, $report_id, 'assigned')";
                mysqli_query($conn, $assign_query);
                
                // Get customer ID for notification
                $customer_query = "SELECT customer_id FROM reports WHERE report_id = $report_id";
                $customer_result = mysqli_query($conn, $customer_query);
                $customer_data = mysqli_fetch_assoc($customer_result);
                $customer_id = $customer_data['customer_id'];
                
                // Create notification for customer
                $team_query = "SELECT team_name FROM collection_teams WHERE team_id = $team_id";
                $team_result = mysqli_query($conn, $team_query);
                $team_data = mysqli_fetch_assoc($team_result);
                $team_name = $team_data['team_name'];
                
                $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                                      VALUES ($customer_id, 'customer', 'Report Assigned', 
                                      'Your report has been assigned to collection team: $team_name. They will arrive shortly.', 'status_update')";
                mysqli_query($conn, $notification_query);
            }
            
            // Update team assigned reports count
            $count_query = "UPDATE collection_teams 
                           SET assigned_reports_count = assigned_reports_count + " . count($report_ids) . "
                           WHERE team_id = $team_id";
            mysqli_query($conn, $count_query);
            
            $success = "Reports assigned successfully! Customers have been notified.";
            redirect('admin_teams.php');
        } else {
            $error = "Please select at least one report to assign.";
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0, maximum-scale=1.0, user-scalable=no">
    <title>Collection Teams - WasteWise Admin</title>
    <!-- Tailwind CSS -->
    <script src="https://cdn.tailwindcss.com"></script>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <!-- Leaflet CSS -->
    <link rel="stylesheet" href="https://unpkg.com/leaflet@1.9.4/dist/leaflet.css" />
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
        
        /* Team specific styles */
        .team-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 16px;
            padding: 24px;
            transition: all 0.3s;
            border-left: 4px solid #3b82f6;
        }
        
        .team-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .team-status {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 12px;
            font-weight: 600;
        }
        
        .team-status-available { background: rgba(220, 252, 231, 0.2); color: #dcfce7; border: 1px solid rgba(220, 252, 231, 0.3); }
        .team-status-on_duty { background: rgba(219, 234, 254, 0.2); color: #dbeafe; border: 1px solid rgba(219, 234, 254, 0.3); }
        .team-status-offline { background: rgba(255, 255, 255, 0.1); color: rgba(255, 255, 255, 0.7); border: 1px solid rgba(255, 255, 255, 0.2); }
        
        .team-action-btn {
            padding: 8px 16px;
            border-radius: 8px;
            font-size: 14px;
            font-weight: 500;
            transition: all 0.3s;
            display: inline-flex;
            align-items: center;
            justify-content: center;
            cursor: pointer;
            border: none;
            margin-right: 8px;
            margin-bottom: 8px;
        }
        
        .team-action-edit { background: rgba(74, 222, 128, 0.2); color: #4ade80; border: 1px solid rgba(74, 222, 128, 0.3); }
        .team-action-edit:hover { background: rgba(74, 222, 128, 0.3); }
        .team-action-assign { background: rgba(168, 85, 247, 0.2); color: #a855f7; border: 1px solid rgba(168, 85, 247, 0.3); }
        .team-action-assign:hover { background: rgba(168, 85, 247, 0.3); }
        .team-action-location { background: rgba(59, 130, 246, 0.2); color: #3b82f6; border: 1px solid rgba(59, 130, 246, 0.3); }
        .team-action-location:hover { background: rgba(59, 130, 246, 0.3); }
        .team-action-delete { background: rgba(239, 68, 68, 0.2); color: #ef4444; border: 1px solid rgba(239, 68, 68, 0.3); }
        .team-action-delete:hover { background: rgba(239, 68, 68, 0.3); }
        
        /* Modal styles */
        .modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(5px);
            z-index: 1000;
            align-items: center;
            justify-content: center;
        }
        
        .modal-content {
            background: rgba(15, 23, 42, 0.95);
            backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            padding: 2rem;
            border-radius: 1rem;
            width: 90%;
            max-width: 600px;
            max-height: 80vh;
            overflow-y: auto;
        }
        
        .modal-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 1.5rem;
            padding-bottom: 1rem;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .modal-title {
            color: white;
            font-size: 1.5rem;
            font-weight: 600;
        }
        
        .close-modal {
            background: none;
            border: none;
            color: rgba(255, 255, 255, 0.5);
            font-size: 1.5rem;
            cursor: pointer;
            transition: color 0.3s;
        }
        
        .close-modal:hover {
            color: white;
        }
        
        /* Checkbox styles */
        .report-checkbox {
            display: flex;
            align-items: center;
            padding: 1rem;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            margin-bottom: 0.75rem;
            transition: all 0.3s;
            cursor: pointer;
        }
        
        .report-checkbox:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
        }
        
        .report-checkbox input {
            margin-right: 1rem;
            width: 18px;
            height: 18px;
            cursor: pointer;
        }
        
        .report-info {
            flex: 1;
        }
        
        .report-title {
            color: white;
            font-weight: 500;
            margin-bottom: 0.25rem;
        }
        
        .report-address {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
            margin-bottom: 0.25rem;
        }
        
        /* Map styles */
        .map-container {
            width: 100%;
            height: 400px;
            border-radius: 0.75rem;
            overflow: hidden;
            margin-bottom: 1.5rem;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        #locationMap {
            width: 100%;
            height: 100%;
        }
        
        .location-address {
            margin-bottom: 1.5rem;
        }
        
        .location-address-label {
            color: rgba(255, 255, 255, 0.8);
            font-weight: 500;
            margin-bottom: 0.5rem;
            font-size: 0.875rem;
        }
        
        .location-address-value {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 0.75rem;
            padding: 0.875rem 1rem;
            color: rgba(255, 255, 255, 0.7);
            font-size: 1rem;
        }
        
        /* Statistics cards */
        .stat-card {
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 1rem;
            padding: 1.5rem;
            text-align: center;
            transition: all 0.3s;
        }
        
        .stat-card:hover {
            background: rgba(255, 255, 255, 0.08);
            border-color: rgba(255, 255, 255, 0.2);
            transform: translateY(-5px);
        }
        
        .stat-icon {
            font-size: 2.5rem;
            margin-bottom: 1rem;
        }
        
        .stat-value {
            color: white;
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 0.5rem;
        }
        
        .stat-label {
            color: rgba(255, 255, 255, 0.6);
            font-size: 0.875rem;
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
                
                <a href="admin_teams.php" class="nav-item active flex items-center space-x-3 p-4 text-white rounded-lg">
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
            
            <!-- Quick Stats -->
            <div class="mt-8 pt-6 border-t border-white/10">
                <div class="text-gray-400 text-sm mb-3">Team Stats</div>
                <div class="grid grid-cols-2 gap-2">
                    <div class="bg-white/5 p-3 rounded-lg">
                        <div class="text-green-400 font-bold text-lg"><?php echo $stats['on_duty']; ?></div>
                        <div class="text-gray-400 text-xs">On Duty</div>
                    </div>
                    <div class="bg-white/5 p-3 rounded-lg">
                        <div class="text-blue-400 font-bold text-lg"><?php echo $active_assignments; ?></div>
                        <div class="text-gray-400 text-xs">Active</div>
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
                <h1 class="text-white text-xl md:text-2xl font-bold">Collection Teams</h1>
                <p class="text-gray-400 text-sm">Manage waste collection teams and track their status</p>
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
            <!-- Alerts -->
            <?php if(isset($success)): ?>
            <div class="alert-success fade-in-up">
                <i class="fas fa-check-circle mr-2"></i> <?php echo $success; ?>
            </div>
            <?php endif; ?>
            
            <?php if(isset($error)): ?>
            <div class="alert-error fade-in-up">
                <i class="fas fa-exclamation-circle mr-2"></i> <?php echo $error; ?>
            </div>
            <?php endif; ?>

            <!-- Statistics Cards -->
            <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4 md:gap-6 mb-8 fade-in-up">
                <div class="stat-card hover-lift">
                    <div class="stat-icon text-green-400">
                        <i class="fas fa-truck"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Total Teams</div>
                </div>
                
                <div class="stat-card hover-lift">
                    <div class="stat-icon text-blue-400">
                        <i class="fas fa-road"></i>
                    </div>
                    <div class="stat-value"><?php echo $stats['on_duty']; ?></div>
                    <div class="stat-label">On Duty</div>
                </div>
                
                <div class="stat-card hover-lift">
                    <div class="stat-icon text-purple-400">
                        <i class="fas fa-clipboard-list"></i>
                    </div>
                    <div class="stat-value"><?php echo $active_assignments; ?></div>
                    <div class="stat-label">Active Assignments</div>
                </div>
                
                <div class="stat-card hover-lift">
                    <div class="stat-icon text-yellow-400">
                        <i class="fas fa-hourglass-half"></i>
                    </div>
                    <div class="stat-value"><?php echo $pending_reports; ?></div>
                    <div class="stat-label">Pending Reports</div>
                </div>
            </div>

            <!-- Main Content Grid -->
            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6 md:gap-8">
                <!-- Teams List -->
                <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-white text-xl md:text-2xl font-bold flex items-center">
                            <i class="fas fa-truck mr-3 text-yellow-400"></i>
                            Collection Teams
                        </h2>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <?php 
                        mysqli_data_seek($teams, 0);
                        while($team = mysqli_fetch_assoc($teams)): 
                            $assigned_query = "SELECT COUNT(*) as count FROM team_assignments 
                                             WHERE team_id = {$team['team_id']} AND status IN ('assigned', 'in_progress')";
                            $assigned_result = mysqli_query($conn, $assigned_query);
                            $assigned_data = mysqli_fetch_assoc($assigned_result);
                            $active_assignments = $assigned_data['count'];
                        ?>
                        <div class="team-card hover-lift">
                            <div class="flex items-center justify-between mb-4">
                                <div class="flex items-center space-x-3">
                                    <div class="w-12 h-12 rounded-xl bg-gradient-to-r from-yellow-500 to-orange-500 flex items-center justify-center">
                                        <i class="fas fa-truck text-white text-xl"></i>
                                    </div>
                                    <div>
                                        <div class="text-white font-bold"><?php echo htmlspecialchars($team['team_name']); ?></div>
                                        <span class="team-status team-status-<?php echo $team['status']; ?>">
                                            <?php echo ucfirst(str_replace('_', ' ', $team['status'])); ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="space-y-3 mb-4">
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-car text-blue-400 w-6"></i>
                                    <span class="ml-3 text-sm"><?php echo htmlspecialchars($team['vehicle_number']); ?></span>
                                </div>
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-tasks text-purple-400 w-6"></i>
                                    <span class="ml-3 text-sm">Assigned Reports: <?php echo $active_assignments; ?></span>
                                </div>
                                <div class="flex items-center text-gray-300">
                                    <i class="fas fa-clock text-green-400 w-6"></i>
                                    <span class="ml-3 text-sm">Last updated: <?php echo date('h:i A', strtotime($team['last_updated'])); ?></span>
                                </div>
                            </div>
                            
                            <div class="flex flex-wrap">
                                <button type="button" class="team-action-btn team-action-edit" onclick="editTeam(<?php echo $team['team_id']; ?>)">
                                    <i class="fas fa-edit mr-1"></i> Edit
                                </button>
                                <button type="button" class="team-action-btn team-action-assign" onclick="openAssignModal(<?php echo $team['team_id']; ?>, '<?php echo htmlspecialchars($team['team_name']); ?>')">
                                    <i class="fas fa-tasks mr-1"></i> Assign
                                </button>
                                <button type="button" class="team-action-btn team-action-location" onclick="openLocationModal(<?php echo $team['team_id']; ?>)">
                                    <i class="fas fa-map-marker-alt mr-1"></i> Location
                                </button>
                                <a href="?action=delete&id=<?php echo $team['team_id']; ?>" 
                                   class="team-action-btn team-action-delete" 
                                   onclick="return confirm('Are you sure you want to delete this team?')">
                                    <i class="fas fa-trash mr-1"></i> Delete
                                </a>
                            </div>
                        </div>
                        <?php endwhile; ?>
                    </div>
                </div>

                <!-- Add/Edit Form -->
                <div class="glass-card rounded-2xl p-6 md:p-8 fade-in-up">
                    <div class="flex justify-between items-center mb-6">
                        <h2 class="text-white text-xl md:text-2xl font-bold flex items-center" id="formTitle">
                            <i class="fas fa-plus-circle mr-3 text-green-400"></i>
                            Add New Team
                        </h2>
                    </div>
                    
                    <form method="POST" action="" id="teamForm">
                        <input type="hidden" name="team_id" id="team_id" value="">
                        
                        <div class="form-group">
                            <label class="form-label">Team Name *</label>
                            <input type="text" name="team_name" id="team_name" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Vehicle Number *</label>
                            <input type="text" name="vehicle_number" id="vehicle_number" class="form-input" required>
                        </div>
                        
                        <div class="form-group">
                            <label class="form-label">Status</label>
                            <select name="status" id="status" class="form-select" required>
                                <option value="available">Available</option>
                                <option value="on_duty">On Duty</option>
                                <option value="offline">Offline</option>
                            </select>
                        </div>
                        
                        <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 mt-8">
                            <button type="button" id="cancelBtn" class="btn-cancel" style="display: none;" onclick="resetForm()">
                                <i class="fas fa-times mr-2"></i> Cancel
                            </button>
                            <button type="submit" name="add_team" id="submitBtn" class="btn-submit">
                                <i class="fas fa-plus mr-2"></i> Add Team
                            </button>
                        </div>
                    </form>
                </div>
            </div>

            <!-- Tracking Information -->
            <div class="glass-card rounded-2xl p-6 md:p-8 mt-8 fade-in-up">
                <h2 class="text-white text-xl md:text-2xl font-bold mb-6 flex items-center">
                    <i class="fas fa-satellite-dish text-blue-400 mr-3"></i>
                    Tracking Information
                </h2>
                
                <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                    <div>
                        <h3 class="text-white text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-info-circle text-green-400 mr-2"></i>
                            About Live Tracking
                        </h3>
                        <p class="text-gray-300 leading-relaxed">
                            Teams with live tracking enabled can be monitored in real-time on customer maps. 
                            When reports are assigned to a team, customers can track their arrival time and location.
                            Estimated collection times are calculated based on distance and traffic conditions.
                        </p>
                        <div class="mt-4 p-4 bg-gradient-to-r from-blue-500/10 to-purple-500/10 rounded-lg border border-blue-500/20">
                            <div class="flex items-center text-blue-400 mb-2">
                                <i class="fas fa-lightbulb mr-2"></i>
                                <span class="font-semibold">Pro Tip</span>
                            </div>
                            <p class="text-gray-300 text-sm">
                                Keep team locations updated regularly for accurate ETA calculations and better customer service.
                            </p>
                        </div>
                    </div>
                    
                    <div>
                        <h3 class="text-white text-lg font-semibold mb-4 flex items-center">
                            <i class="fas fa-chart-line text-yellow-400 mr-2"></i>
                            Tracking Statistics
                        </h3>
                        <div class="space-y-3">
                            <div class="flex justify-between items-center">
                                <span class="text-gray-300">Teams with Live Location</span>
                                <span class="text-white font-semibold"><?php echo $located_teams; ?> / <?php echo $stats['total']; ?></span>
                            </div>
                            <div class="w-full bg-gray-700/50 rounded-full h-2">
                                <div class="bg-gradient-to-r from-green-400 to-blue-500 h-2 rounded-full" 
                                     style="width: <?php echo ($located_teams / max($stats['total'], 1) * 100); ?>%"></div>
                            </div>
                            
                            <div class="flex justify-between items-center mt-4">
                                <span class="text-gray-300">Average Reports per Team</span>
                                <span class="text-white font-semibold">
                                    <?php echo $stats['total'] > 0 ? round($active_assignments / $stats['total'], 1) : 0; ?>
                                </span>
                            </div>
                            
                            <div class="flex justify-between items-center">
                                <span class="text-gray-300">Pending Assignments</span>
                                <span class="text-white font-semibold"><?php echo $pending_reports; ?></span>
                            </div>
                        </div>
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
                    <p class="mt-2">Managing: <span class="text-green-400"><?php echo $stats['total']; ?> Collection Teams</span></p>
                </div>
            </div>
        </footer>
    </main>

    <!-- Modals -->
    
    <!-- Assign Reports Modal -->
    <div id="assignModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title" id="assignModalTitle">Assign Reports to Team</h2>
                <button class="close-modal" onclick="closeAssignModal()">&times;</button>
            </div>
            
            <form method="POST" action="" id="assignForm">
                <input type="hidden" name="team_id" id="assignTeamId" value="">
                
                <p class="text-gray-300 mb-4">Select reports to assign to this collection team:</p>
                
                <div class="max-h-60 overflow-y-auto pr-2">
                    <?php 
                    mysqli_data_seek($reports_result, 0);
                    while($report = mysqli_fetch_assoc($reports_result)): 
                    ?>
                    <div class="report-checkbox">
                        <input type="checkbox" name="report_ids[]" value="<?php echo $report['report_id']; ?>" 
                               id="report_<?php echo $report['report_id']; ?>">
                        <div class="report-info">
                            <div class="report-title"><?php echo htmlspecialchars($report['title']); ?></div>
                            <div class="report-address"><?php echo htmlspecialchars($report['address']); ?></div>
                            <div class="report-address">Customer: <?php echo htmlspecialchars($report['customer_name']); ?></div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4 mt-6">
                    <button type="button" class="btn-cancel" onclick="closeAssignModal()">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                    <button type="submit" name="assign_reports" class="btn-submit">
                        <i class="fas fa-check mr-2"></i> Assign Selected Reports
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Update Location Modal -->
    <div id="locationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Update Team Location</h2>
                <button class="close-modal" onclick="closeLocationModal()">&times;</button>
            </div>
            
            <div class="map-container">
                <div id="locationMap"></div>
            </div>
            
            <form method="POST" action="" id="locationForm">
                <input type="hidden" id="locationTeamId" name="team_id">
                <input type="hidden" id="selectedLat" name="latitude">
                <input type="hidden" id="selectedLng" name="longitude">
                
                <div class="location-address">
                    <div class="location-address-label">Selected Location:</div>
                    <div class="location-address-value" id="locationAddress">Click on the map to select a location</div>
                </div>
                
                <div class="flex flex-col sm:flex-row justify-end space-y-3 sm:space-y-0 sm:space-x-4">
                    <button type="button" class="team-action-btn team-action-location" onclick="useCurrentLocation()">
                        <i class="fas fa-location-crosshairs mr-2"></i> Use Current Location
                    </button>
                    <button type="submit" class="btn-submit" name="update_location">
                        <i class="fas fa-save mr-2"></i> Save Location
                    </button>
                    <button type="button" class="btn-cancel" onclick="closeLocationModal()">
                        <i class="fas fa-times mr-2"></i> Cancel
                    </button>
                </div>
            </form>
        </div>
    </div>

    <!-- Floating Action Button for Mobile -->
    <div class="fixed bottom-6 right-6 md:hidden z-40">
        <button onclick="toggleSidebar()" class="w-14 h-14 bg-gradient-to-r from-purple-500 to-blue-500 rounded-full flex items-center justify-center shadow-lg hover:shadow-xl transition-all hover:scale-110 pulse-animation">
            <i class="fas fa-bars text-white text-xl"></i>
        </button>
    </div>

    <!-- Leaflet JS -->
    <script src="https://unpkg.com/leaflet@1.9.4/dist/leaflet.js"></script>
    
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
        
        // Team Form Functions
        function editTeam(teamId) {
            // This would normally fetch data via AJAX
            // For demo purposes, we'll simulate with prompts
            const teamName = prompt("Enter new team name:");
            const vehicleNumber = prompt("Enter new vehicle number:");
            const status = prompt("Enter status (available/on_duty/offline):");
            
            if (teamName && vehicleNumber && status) {
                document.getElementById('formTitle').innerHTML = '<i class="fas fa-edit mr-3 text-green-400"></i> Edit Team';
                document.getElementById('team_id').value = teamId;
                document.getElementById('team_name').value = teamName;
                document.getElementById('vehicle_number').value = vehicleNumber;
                document.getElementById('status').value = status;
                document.getElementById('submitBtn').name = 'update_team';
                document.getElementById('submitBtn').innerHTML = '<i class="fas fa-save mr-2"></i> Update Team';
                document.getElementById('cancelBtn').style.display = 'inline-block';
                
                // Scroll to form
                document.getElementById('teamForm').scrollIntoView({ behavior: 'smooth' });
            }
        }
        
        function resetForm() {
            document.getElementById('formTitle').innerHTML = '<i class="fas fa-plus-circle mr-3 text-green-400"></i> Add New Team';
            document.getElementById('teamForm').reset();
            document.getElementById('team_id').value = '';
            document.getElementById('submitBtn').name = 'add_team';
            document.getElementById('submitBtn').innerHTML = '<i class="fas fa-plus mr-2"></i> Add Team';
            document.getElementById('cancelBtn').style.display = 'none';
        }
        
        // Assign Reports Modal
        function openAssignModal(teamId, teamName) {
            document.getElementById('assignModalTitle').textContent = 'Assign Reports to ' + teamName;
            document.getElementById('assignTeamId').value = teamId;
            document.getElementById('assignModal').style.display = 'flex';
        }
        
        function closeAssignModal() {
            document.getElementById('assignModal').style.display = 'none';
            document.getElementById('assignForm').reset();
        }
        
        // Location Update Modal
        let locationMap;
        let locationMarker;
        let selectedLat = 14.5995;
        let selectedLng = 120.9842;
        
        function openLocationModal(teamId) {
            document.getElementById('locationTeamId').value = teamId;
            document.getElementById('locationModal').style.display = 'flex';
            
            // Initialize map if not already done
            setTimeout(() => {
                initLocationMap();
            }, 100);
        }
        
        function closeLocationModal() {
            document.getElementById('locationModal').style.display = 'none';
            document.getElementById('locationForm').reset();
            selectedLat = 14.5995;
            selectedLng = 120.9842;
        }
        
        function initLocationMap() {
            if (!locationMap) {
                locationMap = L.map('locationMap').setView([selectedLat, selectedLng], 13);
                
                L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                    attribution: '© OpenStreetMap contributors'
                }).addTo(locationMap);
                
                locationMarker = L.marker([selectedLat, selectedLng], {
                    draggable: true
                }).addTo(locationMap);
                
                // Update location on marker drag
                locationMarker.on('dragend', function(e) {
                    const position = locationMarker.getLatLng();
                    updateSelectedLocation(position.lat, position.lng);
                    reverseGeocode(position.lat, position.lng);
                });
                
                // Update location on map click
                locationMap.on('click', function(e) {
                    locationMarker.setLatLng(e.latlng);
                    updateSelectedLocation(e.latlng.lat, e.latlng.lng);
                    reverseGeocode(e.latlng.lat, e.latlng.lng);
                });
                
                // Initial location update
                updateSelectedLocation(selectedLat, selectedLng);
                reverseGeocode(selectedLat, selectedLng);
            } else {
                locationMap.invalidateSize();
            }
        }
        
        function updateSelectedLocation(lat, lng) {
            selectedLat = lat;
            selectedLng = lng;
            document.getElementById('selectedLat').value = lat;
            document.getElementById('selectedLng').value = lng;
        }
        
        function useCurrentLocation() {
            if (navigator.geolocation) {
                navigator.geolocation.getCurrentPosition(function(position) {
                    const lat = position.coords.latitude;
                    const lng = position.coords.longitude;
                    
                    locationMap.setView([lat, lng], 15);
                    locationMarker.setLatLng([lat, lng]);
                    updateSelectedLocation(lat, lng);
                    reverseGeocode(lat, lng);
                });
            } else {
                alert('Geolocation is not supported by your browser.');
            }
        }
        
        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}`)
                .then(response => response.json())
                .then(data => {
                    if (data.display_name) {
                        document.getElementById('locationAddress').textContent = data.display_name;
                    }
                })
                .catch(error => {
                    console.error('Geocoding error:', error);
                    document.getElementById('locationAddress').textContent = `Location at ${lat.toFixed(6)}, ${lng.toFixed(6)}`;
                });
        }
        
        // Close modals when clicking outside
        window.onclick = function(event) {
            if (event.target.classList.contains('modal')) {
                closeAssignModal();
                closeLocationModal();
            }
        };
        
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
        });
    </script>
</body>
</html>