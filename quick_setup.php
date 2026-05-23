<?php
$host = 'localhost';
$dbname = 'powersonic_crm';
$username = 'root';
$password = '';

try {
    $pdo = new PDO("mysql:host=$host;dbname=$dbname", $username, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    
    // Create users table if not exists
    $pdo->exec("CREATE TABLE IF NOT EXISTS users (
        id INT PRIMARY KEY AUTO_INCREMENT,
        username VARCHAR(50) UNIQUE NOT NULL,
        password VARCHAR(255) NOT NULL,
        full_name VARCHAR(100) NOT NULL,
        role ENUM('admin', 'field_agent') NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
    )");
    
    // Clear existing
    $pdo->exec("TRUNCATE TABLE users");
    
    // Insert with correct hashes
    $admin_hash = password_hash('admin123', PASSWORD_DEFAULT);
    $agent_hash = password_hash('agent123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $admin_hash, 'Administrator', 'admin']);
    $stmt->execute(['fieldagent', $agent_hash, 'Field Agent', 'field_agent']);
    
    echo "<h2>✅ Setup Complete!</h2>";
    echo "<p><strong>Admin Login:</strong> admin / admin123</p>";
    echo "<p><strong>Agent Login:</strong> fieldagent / agent123</p>";
    echo "<p><a href='index.php' style='background: #27ae60; color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px;'>Go to Login →</a></p>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>