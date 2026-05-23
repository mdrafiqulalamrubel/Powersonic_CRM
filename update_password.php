<?php
require_once 'config.php';

try {
    // Just update the passwords without deleting users
    $admin_password = password_hash('admin123', PASSWORD_DEFAULT);
    $agent_password = password_hash('agent123', PASSWORD_DEFAULT);
    
    // Check if admin exists
    $check = $pdo->prepare("SELECT COUNT(*) FROM users WHERE username = ?");
    $check->execute(['admin']);
    $adminExists = $check->fetchColumn();
    
    if ($adminExists) {
        // Update existing admin
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'admin'");
        $stmt->execute([$admin_password]);
        echo "✅ Admin password updated<br>";
    } else {
        // Insert new admin
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['admin', $admin_password, 'Administrator', 'admin']);
        echo "✅ Admin user created<br>";
    }
    
    // Check if fieldagent exists
    $check->execute(['fieldagent']);
    $agentExists = $check->fetchColumn();
    
    if ($agentExists) {
        // Update existing agent
        $stmt = $pdo->prepare("UPDATE users SET password = ? WHERE username = 'fieldagent'");
        $stmt->execute([$agent_password]);
        echo "✅ Field Agent password updated<br>";
    } else {
        // Insert new agent
        $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, role) VALUES (?, ?, ?, ?)");
        $stmt->execute(['fieldagent', $agent_password, 'Field Agent', 'field_agent']);
        echo "✅ Field Agent user created<br>";
    }
    
    echo "<br><strong>Login Credentials:</strong><br>";
    echo "Admin: admin / admin123<br>";
    echo "Agent: fieldagent / agent123<br>";
    echo "<br><a href='index.php' style='background: #27ae60; color: white; padding: 10px 15px; text-decoration: none; border-radius: 5px;'>Go to Login →</a>";
    
} catch(PDOException $e) {
    echo "Error: " . $e->getMessage();
}
?>