<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$error = '';
$success = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $department = $_POST['department'];
    $join_date = $_POST['join_date'];
    
    // Validation
    if (empty($username) || empty($password) || empty($full_name)) {
        $error = "Please fill all required fields";
    } elseif ($password != $confirm_password) {
        $error = "Passwords do not match";
    } elseif (strlen($password) < 6) {
        $error = "Password must be at least 6 characters";
    } else {
        // Check if username exists
        $check = $pdo->prepare("SELECT id FROM users WHERE username = ?");
        $check->execute([$username]);
        if ($check->fetch()) {
            $error = "Username already exists";
        } else {
            // Create user
            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
            $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, department, join_date, status) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active')");
            $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role, $department, $join_date]);
            
            $user_id = $pdo->lastInsertId();
            
            // Log activity
            logActivity($_SESSION['user_id'], "User Created", "Created new user: $username ($role)");
            
            $success = "User created successfully!";
            
            // Clear form
            $_POST = [];
        }
    }
}
?>

<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Add New User</h2>
    
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
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Username *</label>
            <input type="text" name="username" required value="<?php echo $_POST['username'] ?? ''; ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name *</label>
            <input type="text" name="full_name" required value="<?php echo $_POST['full_name'] ?? ''; ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
            <input type="email" name="email" value="<?php echo $_POST['email'] ?? ''; ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Phone</label>
            <input type="text" name="phone" value="<?php echo $_POST['phone'] ?? ''; ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Role *</label>
            <select name="role" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="field_agent" <?php echo ($_POST['role'] ?? '') == 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
                <option value="supervisor" <?php echo ($_POST['role'] ?? '') == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                <option value="admin" <?php echo ($_POST['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Administrator</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Department</label>
            <input type="text" name="department" value="<?php echo $_POST['department'] ?? ''; ?>" 
                   placeholder="e.g., Sales, Marketing, Support"
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Join Date</label>
            <input type="date" name="join_date" value="<?php echo $_POST['join_date'] ?? date('Y-m-d'); ?>" 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Password *</label>
            <input type="password" name="password" required 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            <small style="color: #666;">Minimum 6 characters</small>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Confirm Password *</label>
            <input type="password" name="confirm_password" required 
                   style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="display: flex; gap: 10px;">
            <button type="submit" style="background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer;">
                Create User
            </button>
            <a href="manage_users.php" style="background: #95a5a6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">
                Cancel
            </a>
        </div>
    </form>
</div>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>