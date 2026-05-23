<?php
require_once 'config.php';

// Check if customer is logged in
if (!isCustomer()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Not authorized']);
    exit;
}

$conn = getDBConnection();
$customer_id = $_SESSION['user_id'];

// Get POST data
$data = json_decode(file_get_contents('php://input'), true);

if (isset($data['action']) && $data['action'] === 'auto_update_status') {
    $report_id = intval($data['report_id']);
    $progress = floatval($data['progress']);
    $team_id = isset($data['team_id']) ? intval($data['team_id']) : null;
    
    // Check if report belongs to this customer
    $check_query = "SELECT report_id FROM reports WHERE report_id = $report_id AND customer_id = $customer_id";
    $check_result = mysqli_query($conn, $check_query);
    
    if (mysqli_num_rows($check_result) > 0) {
        $new_status = 'not_assigned';
        $assignment_status = 'assigned';
        $points_awarded = 0;
        
        // Determine new status based on progress
        if ($progress >= 100) {
            $new_status = 'collected';
            $assignment_status = 'completed';
            $points_awarded = 10; // Award 10 points for completed collection
            
            // Update customer points
            $update_points = "UPDATE customer SET points = points + $points_awarded WHERE customer_id = $customer_id";
            mysqli_query($conn, $update_points);
        } elseif ($progress >= 90) {
            // If progress is high but not completed, check if it should be marked as failed
            // This is a simple logic - you might want to add more complex logic
            $new_status = 'failed';
            $assignment_status = 'failed';
        } else {
            // Still in progress
            $new_status = 'collecting';
            $assignment_status = 'in_progress';
        }
        
        // Update report status
        $update_report = "UPDATE reports SET collection_status = '$new_status', 
                          points_awarded = $points_awarded WHERE report_id = $report_id";
        mysqli_query($conn, $update_report);
        
        // Update team assignment status if team exists
        if ($team_id) {
            $update_assignment = "UPDATE team_assignments SET status = '$assignment_status', 
                                  progress_percentage = $progress WHERE report_id = $report_id AND team_id = $team_id";
            mysqli_query($conn, $update_assignment);
        }
        
        // Create notification for status change
        $notification_title = $new_status === 'collected' ? 'Collection Completed' : 
                             ($new_status === 'failed' ? 'Collection Failed' : 'Status Updated');
        $notification_message = $new_status === 'collected' ? 
            'Your waste report has been successfully collected. You earned ' . $points_awarded . ' points!' :
            ($new_status === 'failed' ? 'The collection for your report has failed or was rejected.' :
            'Your report progress has been updated to ' . round($progress) . '%');
        
        $insert_notification = "INSERT INTO notifications (user_id, user_type, title, message, type, is_read) 
                               VALUES ($customer_id, 'customer', '$notification_title', '$notification_message', 'status_update', 0)";
        mysqli_query($conn, $insert_notification);
        
        header('Content-Type: application/json');
        echo json_encode([
            'success' => true,
            'new_status' => $new_status,
            'assignment_status' => $assignment_status,
            'points_awarded' => $points_awarded,
            'notification' => [
                'title' => $notification_title,
                'message' => $notification_message
            ]
        ]);
    } else {
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'message' => 'Report not found']);
    }
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>