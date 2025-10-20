<?php
// config.php - Enhanced version
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Database configuration
$host = 'localhost';
$dbname = 'fuel_station_db';  // Make sure this matches your database name
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Simple function to check if user is logged in
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

// Redirect if not logged in
function requireLogin() {
    if (!isLoggedIn()) {
        header("Location: login.php");
        exit();
    }
}

// Simple email function for development
function sendVerificationCode($email, $code) {
    // For development, we'll store in session
    $_SESSION['verification_code'] = $code;
    $_SESSION['verification_email'] = $email;
    return true;
    
    // For production, you would use:
    // mail($email, "Verification Code", "Your code is: $code");
}
?>