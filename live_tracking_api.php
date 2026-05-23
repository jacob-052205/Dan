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

if (isset($_GET['action']) && $_GET['action'] === 'get_locations') {
    // Get active team locations for this customer
    $query = "SELECT ct.team_id, ct.team_name, ct.vehicle_number, 
              ct.current_latitude as latitude, ct.current_longitude as longitude,
              ta.progress_percentage, ta.status as assignment_status,
              r.report_id, r.title, r.collection_status
              FROM collection_teams ct
              JOIN reports r ON ct.team_id = r.assigned_team_id
              LEFT JOIN team_assignments ta ON r.report_id = ta.report_id AND ta.team_id = ct.team_id
              WHERE r.customer_id = $customer_id 
              AND r.collection_status NOT IN ('collected', 'failed')
              AND ta.status NOT IN ('completed', 'failed')";
    
    $result = mysqli_query($conn, $query);
    $teams = [];
    $reports = [];
    
    if ($result && mysqli_num_rows($result) > 0) {
        while ($row = mysqli_fetch_assoc($result)) {
            $teams[] = [
                'team_id' => $row['team_id'],
                'team_name' => $row['team_name'],
                'vehicle_number' => $row['vehicle_number'],
                'latitude' => $row['latitude'],
                'longitude' => $row['longitude'],
                'progress_percentage' => $row['progress_percentage'],
                'status' => $row['assignment_status']
            ];
            
            $reports[] = [
                'report_id' => $row['report_id'],
                'title' => $row['title'],
                'collection_status' => $row['collection_status'],
                'progress' => $row['progress_percentage']
            ];
        }
    }
    
    // Check for auto-updates
    foreach ($reports as $report) {
        if ($report['progress'] >= 100 && $report['collection_status'] !== 'collected') {
            // Auto-update to completed
            $update_report = "UPDATE reports SET collection_status = 'collected', 
                              points_awarded = 10 WHERE report_id = {$report['report_id']}";
            mysqli_query($conn, $update_report);
            
            // Update customer points
            $update_points = "UPDATE customer SET points = points + 10 WHERE customer_id = $customer_id";
            mysqli_query($conn, $update_points);
            
            // Create notification
            $insert_notification = "INSERT INTO notifications (user_id, user_type, title, message, type, is_read) 
                                   VALUES ($customer_id, 'customer', 'Collection Completed', 
                                   'Your waste report \"{$report['title']}\" has been successfully collected. You earned 10 points!', 
                                   'status_update', 0)";
            mysqli_query($conn, $insert_notification);
        }
    }
    
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'teams' => $teams,
        'reports' => $reports,
        'timestamp' => date('Y-m-d H:i:s')
    ]);
} else {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'message' => 'Invalid action']);
}
?>