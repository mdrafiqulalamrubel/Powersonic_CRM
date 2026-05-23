<?php
require_once 'config.php';

try {
    // Use DELETE instead of TRUNCATE (works with foreign keys)
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
    $pdo->exec("DELETE FROM users");
    $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
    
    // Create new users with correct passwords
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $agent_password = password_hash('agent123', PASSWORD_DEFAULT);
    
    $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
    $stmt->execute(['admin', $admin_password, 'Administrator', 'admin']);
    $stmt->execute(['fieldagent', $agent_password, 'Field Agent', 'field_agent']);
    
    echo "✅ Users created successfully!<br>";
    echo "Admin: admin / admin123<br>";
    echo "Agent: fieldagent / agent123<br>";
    echo "<a href='index.php'>Go to Login</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>