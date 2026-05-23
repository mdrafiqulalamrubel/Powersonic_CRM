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

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $department = $_POST['department'];
    $password = $_POST['password'];
    
    // Update query
    $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, department = ?";
    $params = [$full_name, $email, $phone, $role, $department];
    
    if (!empty($password)) {
        if (strlen($password) >= 6) {
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $update_query .= ", password = ?";
            $params[] = $hashed_password;
        } else {
            $error = "Password must be at least 6 characters";
        }
    }
    
    $update_query .= " WHERE id = ?";
    $params[] = $user_id;
    
    if (!$error) {
        $stmt = $pdo->prepare($update_query);
        $stmt->execute($params);
        
        logActivity($_SESSION['user_id'], "User Updated", "Updated user ID $user_id");
        $success = "User updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}
?>

<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h2>
    
    <?php if($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $success; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Username</label>
            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" disabled 
                   style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name *</label>
            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email']); ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Phone</label>
            <input type="text" name="phone" value="<?php echo htmlspecialchars($user['phone']); ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Role *</label>
            <select name="role" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="field_agent" <?php echo $user['role'] == 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
                <option value="supervisor" <?php echo $user['role'] == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Department</label>
            <input type="text" name="department" value="<?php echo htmlspecialchars($user['department']); ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">New Password (leave blank to keep current)</label>
            <input type="password" name="password" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <small style="color: #666;">Minimum 6 characters</small>
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" style="background: #3498db; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer;">
                Update User
            </button>
            <a href="manage_users.php" style="background: #95a5a6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>