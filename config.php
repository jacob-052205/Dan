<?php
// Database configuration for LOCAL DOCKER
define('DB_HOST', 'mysql-db');  // Use the service name from docker-compose.yml
define('DB_USER', 'dan_user');
define('DB_PASS', 'dan_password');
define('DB_NAME', 'dan_database');

// Start session
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// Create connection
function getDBConnection() {
    $conn = mysqli_connect(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    if (!$conn) {
        die("Connection failed: " . mysqli_connect_error());
    }
    
    return $conn;
}

// Sanitize input
function sanitize($input) {
    $conn = getDBConnection();
    if (is_array($input)) {
        return array_map('sanitize', $input);
    }
    return mysqli_real_escape_string($conn, htmlspecialchars(strip_tags(trim($input))));
}

// Redirect function
function redirect($url) {
    header("Location: $url");
    exit();
}

// Check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Check if user is admin
function isAdmin() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'admin';
}

// Check if user is customer
function isCustomer() {
    return isset($_SESSION['user_type']) && $_SESSION['user_type'] === 'customer';
}

// Generate random token for password reset
function generateToken($length = 32) {
    return bin2hex(random_bytes($length));
}

// Send email function (simplified for demo)
function sendEmail($to, $subject, $message) {
    // In production, use PHPMailer or similar
    $headers = "MIME-Version: 1.0" . "\r\n";
    $headers .= "Content-type:text/html;charset=UTF-8" . "\r\n";
    $headers .= 'From: Waste Management System <noreply@wastemanagement.com>' . "\r\n";
    
    return mail($to, $subject, $message, $headers);
}

// Get base URL
function base_url($path = '') {
    $protocol = isset($_SERVER['HTTPS']) && $_SERVER['HTTPS'] === 'on' ? "https://" : "http://";
    $host = $_SERVER['HTTP_HOST'];
    $base = str_replace(basename($_SERVER['SCRIPT_NAME']), '', $_SERVER['SCRIPT_NAME']);
    return $protocol . $host . $base . $path;
}

// Password reset tokens table
function createPasswordResetTable() {
    $conn = getDBConnection();
    $sql = "CREATE TABLE IF NOT EXISTS password_resets (
        id INT PRIMARY KEY AUTO_INCREMENT,
        email VARCHAR(100) NOT NULL,
        token VARCHAR(255) NOT NULL,
        user_type ENUM('admin', 'customer') NOT NULL,
        expires_at DATETIME NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX (email),
        INDEX (token)
    )";
    
    return mysqli_query($conn, $sql);
}

// Initialize password reset table
createPasswordResetTable();
?>
