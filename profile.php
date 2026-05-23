<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$message = '';
$error = '';

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = $_POST['full_name'];
    
    $update = $pdo->prepare("UPDATE users SET full_name = ? WHERE id = ?");
    $update->execute([$full_name, $_SESSION['user_id']]);
    
    $_SESSION['full_name'] = $full_name;
    $message = "Profile updated successfully!";
    
    // Refresh user data
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
}
?>

<div style="max-width: 600px; margin: 0 auto;">
    <div style="background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 20px;">My Profile</h2>
        
        <?php if($message): ?>
            <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
                <?php echo $message; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST">
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Username</label>
                <input type="text" value="<?php echo $user['username']; ?>" disabled style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name</label>
                <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div style="margin-bottom: 20px;">
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Role</label>
                <input type="text" value="<?php echo ucfirst($user['role']); ?>" disabled style="width: 100%; padding: 10px; background: #f5f5f5; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <button type="submit" style="background: #3498db; color: white; padding: 10px 20px; border: none; border-radius: 5px; cursor: pointer;">
                Update Profile
            </button>
        </form>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="change_password.php" style="color: #3498db; text-decoration: none;">Change Password</a>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>