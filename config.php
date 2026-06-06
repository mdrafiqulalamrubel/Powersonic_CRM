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

// ============================================
// AUTHENTICATION & SECURITY FUNCTIONS
// ============================================

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

// ============================================
// COMPANY PREFIX FUNCTIONS
// ============================================

/**
 * Get company prefixes from database
 */
function getCompanyPrefixes($pdo) {
    try {
        $stmt = $pdo->query("SELECT lead_prefix, agent_prefix, customer_prefix FROM company_settings LIMIT 1");
        $prefixes = $stmt->fetch();
        if (!$prefixes) {
            return ['lead_prefix' => 'PSL', 'agent_prefix' => 'AG', 'customer_prefix' => 'CUST'];
        }
        return $prefixes;
    } catch (PDOException $e) {
        return ['lead_prefix' => 'PSL', 'agent_prefix' => 'AG', 'customer_prefix' => 'CUST'];
    }
}

// ============================================
// ID GENERATION FUNCTIONS (USING PREFIXES)
// ============================================

/**
 * Generate unique Lead ID with custom prefix
 * Format: PREFIX-YYYYMMDD-SEQUENCE
 */
function generateUniqueLeadId() {
    global $pdo;
    $prefixes = getCompanyPrefixes($pdo);
    $prefix = $prefixes['lead_prefix'];
    $date_prefix = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE lead_unique_id LIKE ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$prefix . '-' . $date_prefix . '-%']);
    $count = $stmt->fetchColumn() + 1;
    return $prefix . '-' . $date_prefix . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

/**
 * Generate Customer ID with custom prefix
 * Format: PREFIX-YYYYMMDD-SEQUENCE
 */
function generateCustomerID($pdo) {
    $prefixes = getCompanyPrefixes($pdo);
    $prefix = $prefixes['customer_prefix'];
    $date_prefix = date('Ymd');
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE customer_id LIKE ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$prefix . '-' . $date_prefix . '-%']);
    $count = $stmt->fetchColumn() + 1;
    
    return $prefix . '-' . $date_prefix . '-' . str_pad($count, 5, '0', STR_PAD_LEFT);
}

/**
 * Generate Agent Code with custom prefix
 * Format: PREFIX-YYYY-SEQUENCE
 */
function generateAgentCode($agent_id, $pdo) {
    $stmt = $pdo->prepare("SELECT agent_code FROM users WHERE id = ?");
    $stmt->execute([$agent_id]);
    $existing_code = $stmt->fetchColumn();
    
    if ($existing_code) {
        return $existing_code;
    }
    
    $prefixes = getCompanyPrefixes($pdo);
    $prefix = $prefixes['agent_prefix'];
    $year = date('Y');
    $code = $prefix . '-' . $year . '-' . str_pad($agent_id, 4, '0', STR_PAD_LEFT);
    
    $update = $pdo->prepare("UPDATE users SET agent_code = ? WHERE id = ?");
    $update->execute([$code, $agent_id]);
    
    return $code;
}

/**
 * Generate User Custom ID with district code
 * Format: YYYYMMDD-DISTRICTCODE-SEQUENCE
 */
function generateUserID($district, $pdo) {
    $date_prefix = date('Ymd');
    // Get first 3 letters of district, remove non-alphabetic characters
    $district_code = strtoupper(substr(preg_replace('/[^A-Za-z]/', '', $district), 0, 3));
    if (empty($district_code)) {
        $district_code = 'GEN'; // Generic code if no district
    }
    
    // Get count for today for this district
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE user_custom_id LIKE ? AND DATE(created_at) = CURDATE()");
    $stmt->execute([$date_prefix . '-' . $district_code . '-%']);
    $count = $stmt->fetchColumn() + 1;
    
    return $date_prefix . '-' . $district_code . '-' . str_pad($count, 4, '0', STR_PAD_LEFT);
}

// ============================================
// FILE UPLOAD FUNCTIONS
// ============================================

/**
 * Upload photo for lead
 */
function uploadPhoto($file, $leadId) {
    $target_dir = "uploads/leads/$leadId/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $target_file = $target_dir . time() . '_' . basename($file["name"]);
    $imageFileType = strtolower(pathinfo($target_file, PATHINFO_EXTENSION));
    
    // Allow certain file formats
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    if (!in_array($imageFileType, $allowed_types)) {
        return false;
    }
    
    // Check file size (5MB max)
    if ($file["size"] > 5 * 1024 * 1024) {
        return false;
    }
    
    $check = getimagesize($file["tmp_name"]);
    if ($check !== false) {
        if (move_uploaded_file($file["tmp_name"], $target_file)) {
            return $target_file;
        }
    }
    return false;
}

/**
 * Upload attachment for communication or task
 */
function uploadAttachment($file, $type, $parent_id) {
    $target_dir = "uploads/$type/$parent_id/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_name = time() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', basename($file["name"]));
    $target_file = $target_dir . $file_name;
    $file_size = $file["size"];
    
    // Max file size: 10MB
    if ($file_size > 10 * 1024 * 1024) {
        return false;
    }
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return [
            'path' => $target_file,
            'name' => $file_name,
            'size' => $file_size
        ];
    }
    return false;
}

// ============================================
// ACTIVITY LOGGING FUNCTIONS
// ============================================

/**
 * Log user activity
 */
function logActivity($user_id, $action, $description) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    $user_agent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    
    try {
        // Check if table exists
        $pdo->exec("CREATE TABLE IF NOT EXISTS user_activity_log (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            action VARCHAR(100),
            description TEXT,
            ip_address VARCHAR(45),
            user_agent TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $pdo->prepare("INSERT INTO user_activity_log (user_id, action, description, ip_address, user_agent, created_at) VALUES (?, ?, ?, ?, ?, NOW())");
        $stmt->execute([$user_id, $action, $description, $ip, $user_agent]);
    } catch (PDOException $e) {
        // Silently fail - don't break the app for logging errors
        error_log("Activity log failed: " . $e->getMessage());
    }
}

/**
 * Update last login information
 */
function updateLastLogin($user_id) {
    global $pdo;
    $ip = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';
    try {
        $stmt = $pdo->prepare("UPDATE users SET last_login = NOW(), last_ip = ? WHERE id = ?");
        $stmt->execute([$ip, $user_id]);
    } catch (PDOException $e) {
        error_log("Update last login failed: " . $e->getMessage());
    }
}

// ============================================
// NOTIFICATION FUNCTIONS
// ============================================

/**
 * Create a notification for a user
 */
function createNotification($user_id, $lead_id, $message, $type = 'general') {
    global $pdo;
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS notifications (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT,
            lead_id INT,
            notification_type VARCHAR(50),
            message TEXT,
            is_read BOOLEAN DEFAULT FALSE,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
        )");
        
        $stmt = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message, is_read, created_at) VALUES (?, ?, ?, ?, 0, NOW())");
        $stmt->execute([$user_id, $lead_id, $type, $message]);
        return true;
    } catch (PDOException $e) {
        error_log("Notification creation failed: " . $e->getMessage());
        return false;
    }
}

/**
 * Get unread notification count for user
 */
function getUnreadNotificationCount($user_id) {
    global $pdo;
    try {
        $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
        $stmt->execute([$user_id]);
        return $stmt->fetchColumn();
    } catch (PDOException $e) {
        return 0;
    }
}

// ============================================
// SANITIZATION FUNCTIONS
// ============================================

/**
 * Sanitize input data
 */
function sanitizeInput($data) {
    $data = trim($data);
    $data = stripslashes($data);
    $data = htmlspecialchars($data, ENT_QUOTES, 'UTF-8');
    return $data;
}

/**
 * Validate phone number (Bangladesh format)
 */
function validatePhone($phone) {
    // Remove any non-numeric characters
    $phone = preg_replace('/[^0-9]/', '', $phone);
    // Check if it's a valid Bangladesh mobile number (11 digits, starts with 01)
    return preg_match('/^01[3-9][0-9]{8}$/', $phone);
}

/**
 * Validate email
 */
function validateEmail($email) {
    return filter_var($email, FILTER_VALIDATE_EMAIL);
}

// ============================================
// LEAD STAGE FUNCTIONS
// ============================================

/**
 * Get all available lead stages
 */
function getLeadStages() {
    return [
        'Lead' => 10,
        'Pipeline' => 20,
        'Qualified' => 35,
        'Discussion Ongoing' => 50,
        'Quotation Submitted' => 70,
        'Final Negotiation' => 85,
        'Won' => 100,
        'Lost' => 0,
        'Cancelled' => 0
    ];
}

/**
 * Get probability percentage for a stage
 */
function getStageProbability($stage) {
    $stages = getLeadStages();
    return $stages[$stage] ?? 10;
}

// ============================================
// PRIORITY FUNCTIONS
// ============================================

/**
 * Get priority options
 */
function getPriorityOptions() {
    return ['High', 'Medium', 'Low'];
}

/**
 * Get priority color class
 */
function getPriorityClass($priority) {
    $classes = [
        'High' => 'priority-high',
        'Medium' => 'priority-medium',
        'Low' => 'priority-low'
    ];
    return $classes[$priority] ?? 'priority-medium';
}

// ============================================
// FORMATTING FUNCTIONS
// ============================================

/**
 * Format currency
 */
function formatCurrency($amount, $currency = 'BDT') {
    if (!$amount) return $currency . '0';
    
    $symbols = [
        'BDT' => '৳',
        'USD' => '$',
        'EUR' => '€',
        'GBP' => '£',
        'INR' => '₹'
    ];
    
    $symbol = $symbols[$currency] ?? $currency;
    return $symbol . number_format($amount, 2);
}

/**
 * Format date for display
 */
function formatDate($date, $format = 'Y-m-d') {
    if (!$date || $date == '0000-00-00') return 'N/A';
    return date($format, strtotime($date));
}

/**
 * Time ago function
 */
function timeAgo($datetime) {
    if (!$datetime) return 'Never';
    
    $time = strtotime($datetime);
    $now = time();
    $diff = $now - $time;
    
    if ($diff < 60) return $diff . ' seconds ago';
    if ($diff < 3600) return floor($diff / 60) . ' minutes ago';
    if ($diff < 86400) return floor($diff / 3600) . ' hours ago';
    if ($diff < 2592000) return floor($diff / 86400) . ' days ago';
    if ($diff < 31536000) return floor($diff / 2592000) . ' months ago';
    return date('Y-m-d', $time);
}
?>