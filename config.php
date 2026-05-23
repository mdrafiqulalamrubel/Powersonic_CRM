<?php
// Start session at the VERY beginning - before ANY output
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$host = 'localhost';
$dbname = 'powersonic_crm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch(PDOException $e) {
    die("Connection failed: " . $e->getMessage());
}

// Security functions
function isLoggedIn() {
    return isset($_SESSION['user_id']);
}

function isAdmin() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'admin';
}

function isFieldAgent() {
    return isset($_SESSION['role']) && $_SESSION['role'] === 'field_agent';
}

function redirect($url) {
    header("Location: $url");
    exit();
}

function generateUniqueLeadId() {
    return 'PSL-' . date('Ymd') . '-' . rand(1000, 9999);
}

function uploadPhoto($file, $leadId) {
    $target_dir = "uploads/leads/$leadId/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . time() . '_' . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    $check = getimagesize($file["tmp_name"]);
    if($check !== false) {
        if(move_uploaded_file($file["tmp_name"], $target_file)) {
            return $target_file;
        }
    }
    return false;
}


// Log user activity
function logActivity($user_id, $action, $description) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, action, description, ip_address, user_agent) VALUES (?, ?, ?, ?, ?)");
    $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
}

// Update last login
function updateLastLogin($user_id) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
    $stmt->execute([$ip, $user_id]);
}

?>