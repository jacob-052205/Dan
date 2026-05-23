<?php
$host = getenv('DB_HOST') ?: 'mysql-db';
$user = getenv('DB_USER') ?: 'dan_user';
$password = getenv('DB_PASSWORD') ?: 'dan_password';
$database = getenv('DB_NAME') ?: 'dan_database';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$database;charset=utf8", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}
?>
