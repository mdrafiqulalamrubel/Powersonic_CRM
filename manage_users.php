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
        // Delete profile image if exists
        $stmt = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $image = $stmt->fetchColumn();
        if ($image && file_exists($image)) {
            unlink($image);
        }
        
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
    $query .= " AND (username LIKE ? OR full_name LIKE ? OR email LIKE ? OR agent_code LIKE ?)";
    $params[] = "%$search%";
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
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-box {
        background: white;
        padding: 20px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    .stat-box:hover {
        transform: translateY(-3px);
    }
    .stat-box .number {
        font-size: 32px;
        font-weight: bold;
    }
    .stat-box .label {
        color: #666;
        margin-top: 5px;
    }
    .table-container {
        overflow-x: auto;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .user-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1200px;
    }
    .user-table th {
        background: #34495e;
        color: white;
        padding: 14px 12px;
        text-align: left;
        font-size: 13px;
        white-space: nowrap;
    }
    .user-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        font-size: 13px;
        vertical-align: middle;
    }
    .user-table tr:hover {
        background: #f8f9fa;
    }
    .status-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
        white-space: nowrap;
    }
    .status-active { background: #d4edda; color: #155724; }
    .status-inactive { background: #f8d7da; color: #721c24; }
    .role-badge {
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        display: inline-block;
        white-space: nowrap;
    }
    .role-admin { background: #cce5ff; color: #004085; }
    .role-field_agent, .role-agent { background: #d4edda; color: #155724; }
    .role-supervisor { background: #fff3cd; color: #856404; }
    .filter-bar {
        background: white;
        padding: 20px;
        border-radius: 10px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    .filter-form {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filter-group {
        flex: 1;
        min-width: 150px;
    }
    .filter-group label {
        display: block;
        font-size: 11px;
        color: #666;
        margin-bottom: 3px;
    }
    .filter-group input, .filter-group select {
        width: 100%;
        padding: 8px 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 13px;
    }
    .btn {
        padding: 8px 15px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        text-decoration: none;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        font-size: 12px;
        transition: all 0.3s;
    }
    .btn:hover {
        transform: translateY(-2px);
    }
    .btn-primary { background: #3498db; color: white; }
    .btn-success { background: #27ae60; color: white; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-warning { background: #f39c12; color: white; }
    .btn-info { background: #00bcd4; color: white; }
    .btn-secondary { background: #6c757d; color: white; }
    .btn-sm { padding: 5px 8px; font-size: 11px; }
    .agent-code {
        font-family: monospace;
        font-size: 12px;
        background: #f0f0f0;
        padding: 3px 8px;
        border-radius: 4px;
        display: inline-block;
        white-space: nowrap;
    }
    .leads-count {
        text-align: center;
        font-weight: bold;
    }
    .leads-count small {
        font-weight: normal;
        font-size: 10px;
        display: block;
        color: #666;
    }
    .action-buttons {
        display: flex;
        gap: 5px;
        flex-wrap: wrap;
    }
    .contact-info {
        font-size: 12px;
    }
    .contact-info i {
        width: 14px;
        color: #666;
    }
    .user-name {
        font-weight: 600;
    }
    .user-username {
        font-size: 11px;
        color: #666;
    }
    
    /* User Image/Avatar Styles */
    .user-avatar {
        width: 45px;
        height: 45px;
        border-radius: 50%;
        object-fit: cover;
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 18px;
        font-weight: bold;
        color: white;
        cursor: pointer;
        transition: transform 0.3s, box-shadow 0.3s;
    }
    .user-avatar:hover {
        transform: scale(1.1);
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    }
    .user-avatar img {
        width: 100%;
        height: 100%;
        border-radius: 50%;
        object-fit: cover;
    }
    
    /* Image Modal */
    .image-modal {
        display: none;
        position: fixed;
        z-index: 9999;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.9);
        cursor: pointer;
    }
    .image-modal-content {
        margin: auto;
        display: block;
        width: 90%;
        max-width: 700px;
        position: absolute;
        top: 50%;
        left: 50%;
        transform: translate(-50%, -50%);
        animation: zoomIn 0.3s;
    }
    .image-modal-content img {
        width: 100%;
        height: auto;
        border-radius: 10px;
        box-shadow: 0 5px 25px rgba(0,0,0,0.3);
    }
    .modal-close {
        position: absolute;
        top: 20px;
        right: 35px;
        color: #f1f1f1;
        font-size: 40px;
        font-weight: bold;
        cursor: pointer;
        transition: 0.3s;
        z-index: 10000;
    }
    .modal-close:hover {
        color: #bbb;
    }
    @keyframes zoomIn {
        from { transform: translate(-50%, -50%) scale(0.8); opacity: 0; }
        to { transform: translate(-50%, -50%) scale(1); opacity: 1; }
    }
    
    /* User info cell with image */
    .user-info-cell {
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .user-details {
        flex: 1;
    }
</style>

<!-- Statistics Cards -->
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

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label><i class="fas fa-search"></i> Search</label>
            <input type="text" name="search" placeholder="Name, email, agent code..." value="<?php echo htmlspecialchars($search); ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-user-tag"></i> Role</label>
            <select name="role">
                <option value="">All Roles</option>
                <option value="admin" <?php echo $role_filter == 'admin' ? 'selected' : ''; ?>>Admin</option>
                <option value="supervisor" <?php echo $role_filter == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                <option value="field_agent" <?php echo $role_filter == 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-circle"></i> Status</label>
            <select name="status">
                <option value="">All Status</option>
                <option value="active" <?php echo $status_filter == 'active' ? 'selected' : ''; ?>>Active</option>
                <option value="inactive" <?php echo $status_filter == 'inactive' ? 'selected' : ''; ?>>Inactive</option>
            </select>
        </div>
        <div class="filter-group" style="flex: 0 0 auto;">
            <button type="submit" class="btn btn-primary"><i class="fas fa-filter"></i> Filter</button>
            <a href="manage_users.php" class="btn btn-warning"><i class="fas fa-undo"></i> Reset</a>
            <a href="add_user.php" class="btn btn-success"><i class="fas fa-user-plus"></i> Add User</a>
        </div>
    </form>
</div>

<!-- Image Modal -->
<div id="imageModal" class="image-modal">
    <span class="modal-close">&times;</span>
    <div class="image-modal-content">
        <img id="modalImage" src="" alt="User Profile Image">
    </div>
</div>

<!-- Users Table -->
<div class="table-container">
    <table class="user-table">
        <thead>
            <tr>
                <th style="width: 60px;">Photo</th>
                <th>Agent Code</th>
                <th>User</th>
                <th>Contact</th>
                <th>Role</th>
                <th>Status</th>
                <th>Leads</th>
                <th>Last Login</th>
                <th>Joined</th>
                <th style="width: 180px;">Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($users as $user): 
                // Get lead count for this user
                $leadCount = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE created_by = ?");
                $leadCount->execute([$user['id']]);
                $totalLeads = $leadCount->fetchColumn();
                
                // Get won leads count
                $wonLeads = $pdo->prepare("SELECT COUNT(*) FROM leads WHERE created_by = ? AND lead_stage = 'Won'");
                $wonLeads->execute([$user['id']]);
                $totalWon = $wonLeads->fetchColumn();
            ?>
            <tr>
                <!-- Photo Column -->
                <td style="text-align: center;">
                    <?php if(!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                        <div class="user-avatar" onclick="showImage('<?php echo $user['profile_image']; ?>')">
                            <img src="<?php echo $user['profile_image']; ?>" alt="<?php echo htmlspecialchars($user['full_name']); ?>">
                        </div>
                    <?php else: ?>
                        <div class="user-avatar" onclick="showImage(null, '<?php echo htmlspecialchars($user['full_name']); ?>')" style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);">
                            <?php echo strtoupper(substr($user['full_name'], 0, 2)); ?>
                        </div>
                    <?php endif; ?>
                </td>
                
                <!-- Agent Code Column -->
                <td>
                    <?php if($user['agent_code']): ?>
                        <span class="agent-code">
                            <i class="fas fa-id-card"></i> <?php echo htmlspecialchars($user['agent_code']); ?>
                        </span>
                    <?php else: ?>
                        <span class="agent-code" style="background: #fff3cd; color: #856404;">
                            <i class="fas fa-clock"></i> Not assigned
                        </span>
                    <?php endif; ?>
                 </td>
                
                <!-- User Column -->
                <td>
                    <div class="user-info-cell">
                        <div class="user-details">
                            <div class="user-name"><?php echo htmlspecialchars($user['full_name']); ?></div>
                            <div class="user-username">
                                <i class="fas fa-at"></i> <?php echo htmlspecialchars($user['username']); ?>
                            </div>
                        </div>
                    </div>
                 </td>
                
                <!-- Contact Column -->
                <td class="contact-info">
                    <?php if($user['email']): ?>
                        <div><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($user['email']); ?></div>
                    <?php endif; ?>
                    <?php if($user['phone']): ?>
                        <div><i class="fas fa-phone"></i> <?php echo htmlspecialchars($user['phone']); ?></div>
                    <?php endif; ?>
                    <?php if(!$user['email'] && !$user['phone']): ?>
                        <span style="color: #999;"><i class="fas fa-ban"></i> No contact</span>
                    <?php endif; ?>
                </td>
                
                <!-- Role Column -->
                <td>
                    <span class="role-badge role-<?php echo $user['role']; ?>">
                        <i class="fas <?php echo $user['role'] == 'admin' ? 'fa-crown' : ($user['role'] == 'supervisor' ? 'fa-chart-line' : 'fa-user-check'); ?>"></i>
                        <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                    </span>
                </td>
                
                <!-- Status Column -->
                <td>
                    <span class="status-badge status-<?php echo $user['status']; ?>">
                        <i class="fas <?php echo $user['status'] == 'active' ? 'fa-check-circle' : 'fa-ban'; ?>"></i>
                        <?php echo ucfirst($user['status']); ?>
                    </span>
                </td>
                
                <!-- Leads Column -->
                <td class="leads-count">
                    <?php echo $totalLeads; ?>
                    <?php if($totalWon > 0): ?>
                        <small><i class="fas fa-trophy"></i> <?php echo $totalWon; ?> won</small>
                    <?php else: ?>
                        <small>total</small>
                    <?php endif; ?>
                </td>
                
                <!-- Last Login Column -->
                <td>
                    <?php 
                    if($user['last_login']) {
                        echo '<i class="fas fa-clock"></i> ' . date('Y-m-d', strtotime($user['last_login']));
                    } else {
                        echo '<span style="color: #999;"><i class="fas fa-hourglass-start"></i> Never</span>';
                    }
                    ?>
                </td>
                
                <!-- Joined Column -->
                <td>
                    <i class="fas fa-calendar"></i> <?php echo date('Y-m-d', strtotime($user['created_at'])); ?>
                </td>
                
                <!-- Actions Column -->
                <td>
                    <div class="action-buttons">
                        <a href="edit_user.php?id=<?php echo $user['id']; ?>" class="btn btn-primary btn-sm" title="Edit User">
                            <i class="fas fa-edit"></i>
                        </a>
                        <a href="user_permissions.php?id=<?php echo $user['id']; ?>" class="btn btn-warning btn-sm" title="Permissions">
                            <i class="fas fa-lock"></i>
                        </a>
                        <a href="user_activity.php?id=<?php echo $user['id']; ?>" class="btn btn-info btn-sm" title="Activity Log">
                            <i class="fas fa-history"></i>
                        </a>
                        <?php if($user['role'] == 'field_agent'): ?>
                        <a href="agent_reports.php?agent_id=<?php echo $user['id']; ?>" class="btn btn-success btn-sm" title="View Report">
                            <i class="fas fa-chart-line"></i>
                        </a>
                        <?php endif; ?>
                        <a href="view_user.php?id=<?php echo $user['id']; ?>" class="btn btn-secondary btn-sm" title="View Profile">
                            <i class="fas fa-eye"></i>
                        </a>
                        <?php if($user['id'] != $_SESSION['user_id']): ?>
                            <a href="?toggle_status=1&id=<?php echo $user['id']; ?>" class="btn btn-sm <?php echo $user['status'] == 'active' ? 'btn-danger' : 'btn-success'; ?>" 
                               onclick="return confirm('Are you sure you want to <?php echo $user['status'] == 'active' ? 'deactivate' : 'activate'; ?> this user?')" title="<?php echo $user['status'] == 'active' ? 'Deactivate' : 'Activate'; ?>">
                                <i class="fas <?php echo $user['status'] == 'active' ? 'fa-ban' : 'fa-check'; ?>"></i>
                            </a>
                            <a href="?delete=1&id=<?php echo $user['id']; ?>" class="btn btn-danger btn-sm" 
                               onclick="return confirm('Delete this user permanently? This will also delete all leads created by this user.')" title="Delete">
                                <i class="fas fa-trash"></i>
                            </a>
                        <?php endif; ?>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
            
            <?php if(count($users) == 0): ?>
            <tr>
                <td colspan="10" style="text-align: center; padding: 50px;">
                    <i class="fas fa-users" style="font-size: 48px; color: #ccc;"></i>
                    <p style="margin-top: 15px;">No users found matching your criteria.</p>
                    <a href="add_user.php" class="btn btn-success">+ Add New User</a>
                </td>
            </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Footer Info -->
<div style="margin-top: 20px; background: #e8f4f8; padding: 15px; border-radius: 10px; display: flex; justify-content: space-between; flex-wrap: wrap; gap: 15px;">
    <div>
        <i class="fas fa-chart-pie"></i> <strong>Summary:</strong>
        Total: <?php echo $totalUsers; ?> | 
        Active: <span style="color: #27ae60;"><?php echo $activeUsers; ?></span> | 
        Inactive: <span style="color: #e74c3c;"><?php echo $totalUsers - $activeUsers; ?></span>
    </div>
    <div>
        <i class="fas fa-info-circle"></i> <strong>Agent Code Format:</strong> AG-YYYY-XXXX (Auto-generated)
    </div>
    <div>
        <i class="fas fa-trophy"></i> <strong>Total Leads System:</strong> 
        <?php 
        $totalSystemLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
        echo $totalSystemLeads . ' leads';
        ?>
    </div>
</div>

<script>
    // Image Modal Functions
    function showImage(imageUrl, userName = null) {
        const modal = document.getElementById('imageModal');
        const modalImg = document.getElementById('modalImage');
        
        if (imageUrl) {
            modalImg.src = imageUrl;
        } else {
            // Show placeholder for users without image
            modalImg.src = 'data:image/svg+xml,%3Csvg xmlns="http://www.w3.org/2000/svg" width="200" height="200" viewBox="0 0 24 24" fill="%23667eea"%3E%3Cpath d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"%3E%3C/path%3E%3C/svg%3E';
        }
        
        modal.style.display = "block";
    }
    
    // Close modal
    document.querySelector('.modal-close').addEventListener('click', function() {
        document.getElementById('imageModal').style.display = "none";
    });
    
    // Close modal when clicking outside
    window.addEventListener('click', function(event) {
        const modal = document.getElementById('imageModal');
        if (event.target == modal) {
            modal.style.display = "none";
        }
    });
    
    // Close modal with ESC key
    document.addEventListener('keydown', function(event) {
        if (event.key === 'Escape') {
            document.getElementById('imageModal').style.display = "none";
        }
    });
</script>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>