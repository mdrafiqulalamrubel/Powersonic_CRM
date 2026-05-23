<?php
ob_start();
require_once 'config.php';

// Check if user is admin
if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

// Handle user status toggle
if (isset($_GET['toggle_status']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    $current_status = $pdo->prepare("SELECT status FROM users WHERE id = ?");
    $current_status->execute([$user_id]);
    $status = $current_status->fetchColumn();
    
    $new_status = ($status == 'active') ? 'inactive' : 'active';
    $update = $pdo->prepare("UPDATE users SET status = ? WHERE id = ?");
    $update->execute([$new_status, $user_id]);
    
    // Log activity
    logActivity($_SESSION['user_id'], "User Status Changed", "User ID $user_id status changed to $new_status");
    
    redirect('manage_users.php');
}

// Handle user deletion
if (isset($_GET['delete']) && isset($_GET['id'])) {
    $user_id = $_GET['id'];
    
    // Don't allow deleting self
    if ($user_id != $_SESSION['user_id']) {
        $delete = $pdo->prepare("DELETE FROM users WHERE id = ?");
        $delete->execute([$user_id]);
        logActivity($_SESSION['user_id'], "User Deleted", "Deleted user ID $user_id");
    }
    redirect('manage_users.php');
}

// Get all users with filters
$search = $_GET['search'] ?? '';
$role_filter = $_GET['role'] ?? '';
$status_filter = $_GET['status'] ?? '';

$query = "SELECT * FROM users WHERE 1=1";
$params = [];

if ($search) {
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}
if ($role_filter) {
    $query .= " AND role = ?";
    $params[] = $role_filter;
}
if ($status_filter) {
    $query .= " AND status = ?";
    $params[] = $status_filter;
}

$query .= " ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$users = $stmt->fetchAll();

// Get statistics
$totalUsers = $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$activeUsers = $pdo->query("SELECT COUNT(*) FROM users WHERE status = 'active'")->fetchColumn();
$adminCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'admin'")->fetchColumn();
$agentCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'field_agent'")->fetchColumn();
$supervisorCount = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'supervisor'")->fetchColumn();
?>

<style>
    .user-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .stat-box .number {
        font-size: 32px;
        font-weight: bold;
    }
    .stat-box .label {
        color: #666;
        margin-top: 5px;
    }
    .user-table {
        width: 100%;
        background: white;
        border-radius: 10px;
        overflow: hidden;
    }
    .user-table th {
        background: #34495e;
        color: white;
        padding: 12px;
        text-align: left;
    }
    .user-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
    }
    .status-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
        display: inline-block;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #f8d7da; color: #721c24; }
    .role-badge {
        padding: 4px 12px;
        border-radius: 20px;
        font-size: 12px;
        display: inline-block;
    }
    .role-admin { background: #cce5ff; color: #004085; }
    .role-agent { background: #d4edda; color: #155724; }
    .role-supervisor { background: #fff3cd; color: #856404; }
    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: center;
    }
    .filter-bar input, .filter-bar select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
    }
    .btn {
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-block;
        font-size: 12px;
        margin: 2px;
    }
    .btn-primary { background: #3498db; color: white; }
    .btn-success { background: #27ae60; color: white; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-warning { background: #f39c12; color: white; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
</style>

<div class="user-stats">
    <div class="stat-box">
        <div class="number"><?php echo $totalUsers; ?></div>
        <div class="label">Total Users</div>
    </div>
    <div class="stat-box">
        <div class="number" style="color: #27ae60;"><?php echo $activeUsers; ?></div>
        <div class="label">Active Users</div>
    </div>
    <div class="stat-box">
        <div class="number" style="color: #3498db;"><?php echo $adminCount; ?></div>
        <div class="label">Administrators</div>
    </div>
    <div class="stat-box">
        <div class="number" style="color: #f39c12;"><?php echo $supervisorCount; ?></div>
        <div class="label">Supervisors</div>
    </div>
    <div class="stat-box">
        <div class="number" style="color: #27ae60;"><?php echo $agentCount; ?></div>
        <div class="label">Field Agents</div>
    </div>
</div>

<div class="filter-bar">
    <form method="GET" style="display: flex; gap: 10px; flex-wrap: wrap; width: 100%;">
        <input type="text" name="search" placeholder="Search users..." value="<?php echo htmlspecialchars($search); ?>" style="flex: 1;">
        <select name="role">
            <option value="">All Roles</option>
            <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
            <option value="supervisor" <?php echo $role_filter == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
            <option value="field_agent" <?php echo $role_filter == 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
        </select>
        <select name="status">
            <option value="">All Status</option>
            <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
            <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
        </select>
        <button type="submit" class="btn btn-primary">Filter</button>
        <a href="manage_users.php" class="btn btn-warning">Reset</a>
        <a href="add_user.php" class="btn btn-success" style="margin-left: auto;">+ Add New User</a>
    </form>
</div>

<div style="background: white; border-radius: 10px; overflow: hidden;">
    <table class="user-table">
        <thead>
            <tr>
                <th>ID</th>
                <th>User</th>
                <th>Contact</th>
                <th>Role</th>
                <th>Status</th>
                <th>Last Login</th>
                <th>Joined</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): ?>
            <tr>
                <td><?php echo $user['id']; ?></td>
                <td>
                    <strong><?php echo htmlspecialchars($user['full_name']); ?></strong><br>
                    <small style="color: #666;">@<?php echo htmlspecialchars($user['username']); ?></small>
                </td>
                <td>
                    <?php echo $user['email'] ?? 'N/A'; ?><br>
                    <small><?php echo $user['phone'] ?? 'No phone'; ?></small>
                </td>
                <td>
                    <span class="role-badge role-<?php echo $user['role'] == 'field_agent' ? 'agent' : $user['role']; ?>">
                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                    </span>
                </td>
                <td>
                    <span class="status-badge status-<?php echo $user['status']; ?>">
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </td>
                <td>
                    <?php 
                    if($user['last_login']) {
                        echo date('Y-m-d H:i', strtotime($user['last_login']));
                    } else {
                        echo 'Never';
                    }
                    ?>
                </td>
                <td><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></td>
                <td>
                    <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm">Edit</a>
                    <a href="user_permissions.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm">Permissions</a>
                    <a href="user_activity.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm">Activity</a>
                    <?php if($user['id'] != $_SESSION['user_id']): ?>
                        <a href="?toggle_status=1&id=<?php echo $user['id']; ?>" class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>" 
                           onclick="return confirm('Are you sure?')">
                            <?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>
                        </a>
                        <a href="?delete=1&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" 
                           onclick="return confirm('Delete this user permanently?')">Delete</a>
                    <?php endif; ?>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>