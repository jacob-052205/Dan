<?php
require_once '../config.php';

if (!isAdmin()) {
    http_response_code(403);
    die('Access denied');
}

$conn = getDBConnection();

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $team_id = (int)$_POST['team_id'];
    $latitude = (float)$_POST['latitude'];
    $longitude = (float)$_POST['longitude'];
    
    // Update team location
    $update_query = "UPDATE collection_teams SET 
                    current_latitude = '$latitude',
                    current_longitude = '$longitude',
                    last_location_update = NOW()
                    WHERE team_id = $team_id";
    
    if (mysqli_query($conn, $update_query)) {
        // Log location
        $log_query = "INSERT INTO team_locations (team_id, latitude, longitude) 
                     VALUES ($team_id, '$latitude', '$longitude')";
        mysqli_query($conn, $log_query);
        
        // Update estimated arrival times for assigned reports
        updateEstimatedArrivals($conn, $team_id, $latitude, $longitude);
        
        echo json_encode(['success' => true, 'message' => 'Location updated']);
    } else {
        echo json_encode(['success' => false, 'message' => 'Update failed']);
    }
}

function updateEstimatedArrivals($conn, $team_id, $team_lat, $team_lng) {
    // Get all active assignments for this team
    $assignments_query = "SELECT ta.*, r.latitude as report_lat, r.longitude as report_lng 
                         FROM team_assignments ta
                         JOIN reports r ON ta.report_id = r.report_id
                         WHERE ta.team_id = $team_id 
                         AND ta.status IN ('assigned', 'in_progress')";
    $assignments_result = mysqli_query($conn, $assignments_query);
    
    while ($assignment = mysqli_fetch_assoc($assignments_result)) {
        // Calculate distance (simplified - in production use proper distance calculation)
        $distance = calculateDistance($team_lat, $team_lng, 
                                    $assignment['report_lat'], $assignment['report_lng']);
        
        // Estimate arrival time (assuming 30km/h average speed)
        $travel_time_minutes = ($distance / 30) * 60;
        $estimated_arrival = date('Y-m-d H:i:s', 
                                 strtotime("+{$travel_time_minutes} minutes"));
        
        // Update assignment
        $update_query = "UPDATE team_assignments SET 
                        estimated_arrival_time = '$estimated_arrival'
                        WHERE assignment_id = {$assignment['assignment_id']}";
        mysqli_query($conn, $update_query);
        
        // Update report if team is close
        if ($distance < 1) { // Within 1km
            $update_report = "UPDATE reports SET 
                            collection_status = 'collecting'
                            WHERE report_id = {$assignment['report_id']}";
            mysqli_query($conn, $update_report);
            
            // Notify customer
            $report_query = "SELECT customer_id FROM reports WHERE report_id = {$assignment['report_id']}";
            $report_result = mysqli_query($conn, $report_query);
            $report_data = mysqli_fetch_assoc($report_result);
            
            $notification_query = "INSERT INTO notifications (user_id, user_type, title, message, type) 
                                  VALUES ({$report_data['customer_id']}, 'customer', 
                                  'Collection Team Arriving', 
                                  'The collection team is arriving at your location to collect your waste report.', 
                                  'status_update')";
            mysqli_query($conn, $notification_query);
        }
    }
}

function calculateDistance($lat1, $lon1, $lat2, $lon2) {
    // Simplified distance calculation (Haversine formula)
    $earth_radius = 6371; // Earth's radius in kilometers
    
    $dLat = deg2rad($lat2 - $lat1);
    $dLon = deg2rad($lon2 - $lon1);
    
    $a = sin($dLat/2) * sin($dLat/2) + 
         cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * 
         sin($dLon/2) * sin($dLon/2);
    
    $c = 2 * atan2(sqrt($a), sqrt(1-$a));
    $distance = $earth_radius * $c;
    
    return $distance; // Return distance in kilometers
}
?>