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

// Function to upload profile image
function uploadProfileImageEdit($file, $username) {
    $target_dir = "uploads/profiles/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['error' => 'Only JPG, JPEG, PNG, GIF, WEBP files are allowed'];
    }
    
    if ($file["size"] > 2 * 1024 * 1024) {
        return ['error' => 'File size must be less than 2MB'];
    }
    
    $unique_name = 'user_' . $username . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_name;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => $target_file];
    }
    
    return ['error' => 'Failed to upload file'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $full_name = trim($_POST['full_name']);
    $email = trim($_POST['email']);
    $phone = trim($_POST['phone']);
    $role = $_POST['role'];
    $department = $_POST['department'];
    $password = $_POST['password'];
    
    // Handle profile image upload
    $profile_image = $user['profile_image'];
    if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
        // Delete old image if exists
        if ($profile_image && file_exists($profile_image)) {
            unlink($profile_image);
        }
        
        $upload_result = uploadProfileImageEdit($_FILES['profile_image'], $user['username']);
        if (isset($upload_result['success'])) {
            $profile_image = $upload_result['success'];
            $success = "Profile image updated! ";
        } elseif (isset($upload_result['error'])) {
            $error = $upload_result['error'];
        }
    }
    
    // Update query
    $update_query = "UPDATE users SET full_name = ?, email = ?, phone = ?, role = ?, department = ?, profile_image = ?";
    $params = [$full_name, $email, $phone, $role, $department, $profile_image];
    
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
        
        // Update agent code if role changed to field_agent
        if ($role == 'field_agent' && empty($user['agent_code'])) {
            generateAgentCode($user_id, $pdo);
        }
        
        logActivity($_SESSION['user_id'], "User Updated", "Updated user ID $user_id");
        $success .= "User updated successfully!";
        
        // Refresh user data
        $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
        $stmt->execute([$user_id]);
        $user = $stmt->fetch();
    }
}
?>

<style>
    .image-preview {
        margin-top: 10px;
    }
    .image-preview img {
        width: 100px;
        height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 3px solid #3498db;
        padding: 3px;
    }
    .current-image {
        margin-bottom: 15px;
    }
    .current-image img {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        object-fit: cover;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 13px;
        color: #555;
    }
    .form-group input, 
    .form-group select {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
    }
    .form-group input:focus, 
    .form-group select:focus {
        border-color: #3498db;
        outline: none;
    }
    .btn-update {
        background: #3498db;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
    }
    .btn-cancel {
        background: #95a5a6;
        color: white;
        padding: 12px 30px;
        text-decoration: none;
        border-radius: 8px;
    }
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    .photo-upload {
        border: 2px dashed #ddd;
        border-radius: 10px;
        padding: 15px;
        text-align: center;
        cursor: pointer;
        background: #f8f9fa;
        margin-top: 10px;
    }
    .photo-upload:hover {
        border-color: #3498db;
        background: #e8f4f8;
    }
</style>

<div style="max-width: 600px; margin: 0 auto; background: white; border-radius: 12px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Edit User: <?php echo htmlspecialchars($user['full_name']); ?></h2>
    
    <?php if($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <?php if($success): ?>
        <div class="alert-success"><?php echo $success; ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <!-- Current Profile Image -->
        <div class="form-group">
            <label>Current Profile Image</label>
            <div class="current-image">
                <?php if(!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                    <img src="<?php echo $user['profile_image']; ?>" alt="Profile Image">
                    <div class="help-text">Current image</div>
                <?php else: ?>
                    <div style="width: 80px; height: 80px; background: #f0f0f0; border-radius: 50%; display: flex; align-items: center; justify-content: center;">
                        <i class="fas fa-user" style="font-size: 40px; color: #ccc;"></i>
                    </div>
                    <div class="help-text">No profile image</div>
                <?php endif; ?>
            </div>
        </div>
        
        <!-- Change Profile Image -->
        <div class="form-group">
            <label>Change Profile Image</label>
            <div class="photo-upload" onclick="document.getElementById('profile_image').click()">
                <i class="fas fa-camera" style="font-size: 32px;"></i>
                <p>Click to upload new image</p>
                <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                <div class="image-preview" id="imagePreview" style="display: none;">
                    <img id="previewImg" src="#" alt="Preview">
                </div>
            </div>
            <div class="help-text">Max 2MB, JPG, PNG, GIF, WEBP only</div>
        </div>
        
        <div class="form-group">
            <label>Username</label>
            <input type="text" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
        </div>
        
        <div class="form-group">
            <label>Full Name *</label>
            <input type="text" name="full_name" required value="<?php echo htmlspecialchars($user['full_name']); ?>">
        </div>
        
        <div class="form-group">
            <label>Email</label>
            <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Phone</label>
            <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Role *</label>
            <select name="role" required>
                <option value="field_agent" <?php echo $user['role'] == 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
                <option value="supervisor" <?php echo $user['role'] == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                <option value="admin" <?php echo $user['role'] == 'admin' ? 'selected' : ''; ?>>Administrator</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Department</label>
            <input type="text" name="department" value="<?php echo htmlspecialchars($user['department'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Agent Code</label>
            <input type="text" value="<?php echo htmlspecialchars($user['agent_code'] ?? 'Not assigned'); ?>" readonly>
        </div>
        
        <div class="form-group">
            <label>New Password (leave blank to keep current)</label>
            <input type="password" name="password" placeholder="Enter new password">
            <div class="help-text">Minimum 6 characters</div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" class="btn-update">Update User</button>
            <a href="manage_users.php" class="btn-cancel">Cancel</a>
        </div>
    </form>
</div>

<script>
    function previewImage(input) {
        const previewDiv = document.getElementById('imagePreview');
        const previewImg = document.getElementById('previewImg');
        
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                previewImg.src = e.target.result;
                previewDiv.style.display = 'block';
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>