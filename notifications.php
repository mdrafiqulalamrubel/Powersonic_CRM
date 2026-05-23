<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Include header AFTER all PHP logic
require_once 'includes/header.php';

// Mark all as read
if (isset($_GET['mark_read'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE user_id = ?")->execute([$_SESSION['user_id']]);
    redirect('notifications.php');
}

// Mark single as read
if (isset($_GET['read']) && isset($_GET['id'])) {
    $pdo->prepare("UPDATE notifications SET is_read = 1 WHERE id = ? AND user_id = ?")->execute([$_GET['id'], $_SESSION['user_id']]);
    redirect('notifications.php');
}

// Get notifications
$notifications = $pdo->prepare("
    SELECT n.*, l.name as lead_name, l.lead_unique_id 
    FROM notifications n
    LEFT JOIN leads l ON n.lead_id = l.id
    WHERE n.user_id = ?
    ORDER BY n.created_at DESC
");
$notifications->execute([$_SESSION['user_id']]);
$notifications = $notifications->fetchAll();

$unread_count = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$unread_count->execute([$_SESSION['user_id']]);
$unread_count = $unread_count->fetchColumn();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Notifications - Power Sonic CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; }
        .container { max-width: 800px; margin: 30px auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .notification-item { padding: 15px; border-bottom: 1px solid #eee; display: flex; justify-content: space-between; align-items: center; }
        .notification-item.unread { background: #e8f4f8; border-left: 4px solid #3498db; }
        .notification-message { flex: 1; }
        .notification-date { font-size: 12px; color: #666; margin-top: 5px; }
        .btn { background: #3498db; color: white; padding: 5px 10px; text-decoration: none; border-radius: 3px; font-size: 12px; }
        .btn-success { background: #27ae60; }
        h2 { margin-bottom: 20px; }
        .badge { background: #e74c3c; color: white; padding: 2px 8px; border-radius: 10px; font-size: 12px; margin-left: 10px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Power Sonic CRM - Notifications</h2>
        <div>Welcome, <?php echo $_SESSION['full_name']; ?> | <a href="logout.php" style="color: white;">Logout</a></div>
    </div>
    
    <div class="container">
        <h2>
            Notifications 
            <?php if($unread_count > 0): ?>
                <span class="badge"><?php echo $unread_count; ?> new</span>
            <?php endif; ?>
        </h2>
        
        <div style="margin-bottom: 20px;">
            <a href="?mark_read=1" class="btn">Mark All as Read</a>
            <a href="agent_dashboard.php" class="btn btn-success">Back to Dashboard</a>
        </div>
        
        <?php if(count($notifications) == 0): ?>
            <p>No notifications yet.</p>
        <?php else: ?>
            <?php foreach($notifications as $notif): ?>
            <div class="notification-item <?php echo $notif['is_read'] ? '' : 'unread'; ?>">
                <div class="notification-message">
                    <?php echo htmlspecialchars($notif['message']); ?>
                    <?php if($notif['lead_name']): ?>
                        <br><small>Lead: <?php echo htmlspecialchars($notif['lead_name']); ?> (<?php echo $notif['lead_unique_id']; ?>)</small>
                    <?php endif; ?>
                    <div class="notification-date"><?php echo date('Y-m-d H:i', strtotime($notif['created_at'])); ?></div>
                </div>
                <?php if(!$notif['is_read']): ?>
                    <a href="?read=1&id=<?php echo $notif['id']; ?>" class="btn">Mark Read</a>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        <?php endif; ?>
    </div>
</body>
</html>

<?php require_once 'includes/footer.php'; ?>