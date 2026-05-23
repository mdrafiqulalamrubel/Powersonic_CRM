<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$user_id = $_GET['id'] ?? 0;
$user = null;

if ($user_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute([$user_id]);
    $user = $stmt->fetch();
}

// Get activity logs
$query = "SELECT l.*, u.full_name as user_name 
          FROM user_activity_log l 
          JOIN users u ON l.user_id = u.id";
$params = [];

if ($user_id) {
    $query .= " WHERE l.user_id = ?";
    $params[] = $user_id;
}

$query .= " ORDER BY l.created_at DESC LIMIT 100";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$activities = $stmt->fetchAll();
?>

<div style="background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">
        User Activity Log
        <?php if($user): ?>
            - <?php echo htmlspecialchars($user['full_name']); ?>
        <?php endif; ?>
    </h2>
    
    <div style="overflow-x: auto;">
        <table class="user-table">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Action</th>
                    <th>Description</th>
                    <th>IP Address</th>
                    <th>Time</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($activities as $activity): ?>
                <tr>
                    <td><?php echo htmlspecialchars($activity['user_name']); ?></td>
                    <td>
                        <span style="background: #e8f4f8; padding: 3px 8px; border-radius: 3px;">
                            <?php echo htmlspecialchars($activity['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars($activity['description']); ?></td>
                    <td><?php echo $activity['ip_address'] ?? 'N/A'; ?></td>
                    <td><?php echo date('Y-m-d H:i:s', strtotime($activity['created_at'])); ?></td>
                </tr>
                <?php endforeach; ?>
                
                <?php if(count($activities) == 0): ?>
                <tr>
                    <td colspan="5" style="text-align: center; padding: 40px;">
                        No activity records found.
                    </td>
                </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
    
    <div style="margin-top: 20px;">
        <a href="manage_users.php" class="btn btn-primary">Back to Users</a>
    </div>
</div>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>