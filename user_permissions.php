<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$user_id = $_GET['id'] ?? 0;
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('manage_users.php');
}

// Save permissions
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Delete existing permissions for this user
    $delete = $pdo->prepare("DELETE FROM user_permissions WHERE role = ?");
    $delete->execute([$user['role']]);
    
    // Insert new permissions
    $permissions = $_POST['permissions'] ?? [];
    foreach($permissions as $key => $value) {
        $insert = $pdo->prepare("INSERT INTO user_permissions (role, permission_key, permission_value) VALUES (?, ?, ?)");
        $insert->execute([$user['role'], $key, $value == 'on' ? 1 : 0]);
    }
    
    logActivity($_SESSION['user_id'], "Permissions Updated", "Updated permissions for role: {$user['role']}");
    $success = "Permissions updated successfully!";
}

// Get current permissions
$perms = $pdo->prepare("SELECT * FROM user_permissions WHERE role = ?");
$perms->execute([$user['role']]);
$current_perms = [];
while($row = $perms->fetch()) {
    $current_perms[$row['permission_key']] = $row['permission_value'];
}

// Define all possible permissions
$all_permissions = [
    'view_all_leads' => 'View All Leads',
    'edit_all_leads' => 'Edit All Leads',
    'delete_leads' => 'Delete Leads',
    'manage_users' => 'Manage Users',
    'view_reports' => 'View Reports',
    'export_data' => 'Export Data',
    'system_settings' => 'System Settings'
];
?>

<div style="max-width: 700px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">User Permissions</h2>
    <p style="margin-bottom: 20px;">Managing permissions for: <strong><?php echo htmlspecialchars($user['full_name']); ?></strong> (<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>)</p>
    
    <?php if(isset($success)): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div style="margin-bottom: 20px;">
            <h3>Permission Settings</h3>
            <p style="color: #666; margin-bottom: 15px;">Grant or restrict access to various system features</p>
            
            <table style="width: 100%; border-collapse: collapse;">
                <?php foreach($all_permissions as $key => $label): ?>
                <tr style="border-bottom: 1px solid #eee;">
                    <td style="padding: 12px;">
                        <strong><?php echo $label; ?></strong><br>
                        <small style="color: #666;"><?php echo getPermissionDescription($key); ?></small>
                    </td>
                    <td style="padding: 12px; text-align: right;">
                        <label style="display: inline-flex; align-items: center; cursor: pointer;">
                            <input type="checkbox" name="permissions[<?php echo $key; ?>]" 
                                   <?php echo isset($current_perms[$key]) && $current_perms[$key] ? 'checked' : ''; ?>
                                   <?php echo $user['role'] == 'admin' ? 'disabled' : ''; ?>>
                            <span style="margin-left: 8px;">Allow</span>
                        </label>
                    </td>
                </tr>
                <?php endforeach; ?>
            </table>
        </div>
        
        <?php if($user['role'] != 'admin'): ?>
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" style="background: #3498db; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer;">
                Save Permissions
            </button>
            <a href="manage_users.php" style="background: #95a5a6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">
                Back to Users
            </a>
        </div>
        <?php else: ?>
        <div style="background: #e8f4f8; padding: 15px; border-radius: 5px; margin-top: 20px;">
            <i class="fas fa-info-circle"></i> Administrators have all permissions by default.
        </div>
        <?php endif; ?>
    </form>
</div>

<?php 
function getPermissionDescription($key) {
    $descriptions = [
        'view_all_leads' => 'View leads created by all users',
        'edit_all_leads' => 'Edit any lead in the system',
        'delete_leads' => 'Permanently delete leads',
        'manage_users' => 'Create, edit, and delete user accounts',
        'view_reports' => 'Access all reports and analytics',
        'export_data' => 'Export data to CSV/Excel',
        'system_settings' => 'Modify system configuration'
    ];
    return $descriptions[$key] ?? '';
}

require_once 'includes/footer.php'; 
ob_end_flush(); 
?>