<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$error = '';
$success = '';

// Function to upload profile image
function uploadProfileImage($file, $username) {
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
            // Handle profile image upload
            $profile_image = null;
            if (isset($_FILES['profile_image']) && $_FILES['profile_image']['error'] == 0) {
                $upload_result = uploadProfileImage($_FILES['profile_image'], $username);
                if (isset($upload_result['success'])) {
                    $profile_image = $upload_result['success'];
                } elseif (isset($upload_result['error'])) {
                    $error = $upload_result['error'];
                }
            }
            
            // Only create user if no image upload error
            if (empty($error)) {
                // Create user
                $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                $stmt = $pdo->prepare("INSERT INTO users (username, password, full_name, email, phone, role, department, join_date, status, profile_image) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'active', ?)");
                $stmt->execute([$username, $hashed_password, $full_name, $email, $phone, $role, $department, $join_date, $profile_image]);
                
                $user_id = $pdo->lastInsertId();
                
                // Generate agent code for field agents
                if ($role == 'field_agent') {
                    generateAgentCode($user_id, $pdo);
                }
                
                // Log activity
                logActivity($_SESSION['user_id'], "User Created", "Created new user: $username ($role)");
                
                $success = "User created successfully!";
                
                // Clear form
                $_POST = [];
            }
        }
    }
}
?>

<style>
    .image-preview {
        margin-top: 10px;
        display: none;
    }
    .image-preview img {
        max-width: 100px;
        max-height: 100px;
        border-radius: 50%;
        object-fit: cover;
        border: 2px solid #3498db;
        padding: 3px;
    }
    .form-group {
        margin-bottom: 20px;
    }
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 14px;
        color: #333;
    }
    .form-group label .required {
        color: #e74c3c;
    }
    .form-group input, 
    .form-group select {
        width: 100%;
        padding: 10px 12px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    .form-group input:focus, 
    .form-group select:focus {
        border-color: #3498db;
        outline: none;
        box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
    }
    .form-group input[readonly] {
        background: #f5f5f5;
        cursor: not-allowed;
    }
    .help-text {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    .btn-create {
        background: #27ae60;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 8px;
        cursor: pointer;
        font-size: 16px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-create:hover {
        background: #229954;
        transform: translateY(-2px);
    }
    .btn-cancel {
        background: #95a5a6;
        color: white;
        padding: 12px 30px;
        text-decoration: none;
        border-radius: 8px;
        display: inline-flex;
        align-items: center;
        gap: 8px;
    }
    .btn-cancel:hover {
        background: #7f8c8d;
    }
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #e74c3c;
    }
    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #27ae60;
    }
    .photo-upload {
        border: 2px dashed #ddd;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        background: #f8f9fa;
    }
    .photo-upload:hover {
        border-color: #3498db;
        background: #e8f4f8;
    }
    .photo-upload i {
        font-size: 48px;
        color: #999;
        margin-bottom: 10px;
    }
    .photo-upload p {
        color: #666;
        font-size: 12px;
    }
</style>

<div style="max-width: 650px; margin: 0 auto;">
    <div style="background: white; border-radius: 12px; padding: 35px; box-shadow: 0 5px 20px rgba(0,0,0,0.1);">
        <h2 style="margin-bottom: 25px; color: #2c3e50; display: flex; align-items: center; gap: 10px;">
            <i class="fas fa-user-plus" style="color: #27ae60;"></i> Add New User
        </h2>
        
        <?php if($error): ?>
            <div class="alert-error">
                <i class="fas fa-exclamation-triangle"></i> <?php echo $error; ?>
            </div>
        <?php endif; ?>
        
        <?php if($success): ?>
            <div class="alert-success">
                <i class="fas fa-check-circle"></i> <?php echo $success; ?>
            </div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Profile Image Upload -->
            <div class="form-group">
                <label>Profile Image</label>
                <div class="photo-upload" onclick="document.getElementById('profile_image').click()">
                    <i class="fas fa-camera"></i>
                    <p>Click to upload profile image</p>
                    <input type="file" name="profile_image" id="profile_image" accept="image/*" style="display: none;" onchange="previewImage(this)">
                    <div class="image-preview" id="imagePreview">
                        <img id="previewImg" src="#" alt="Preview">
                    </div>
                </div>
                <div class="help-text">Max 2MB, JPG, PNG, GIF, WEBP only</div>
            </div>
            
            <div class="form-group">
                <label><span class="required">*</span> Username</label>
                <input type="text" name="username" required value="<?php echo htmlspecialchars($_POST['username'] ?? ''); ?>" 
                       placeholder="Enter unique username">
                <div class="help-text">This will be used for login</div>
            </div>
            
            <div class="form-group">
                <label><span class="required">*</span> Full Name</label>
                <input type="text" name="full_name" required value="<?php echo htmlspecialchars($_POST['full_name'] ?? ''); ?>" 
                       placeholder="Enter full name">
            </div>
            
            <div class="form-group">
                <label>Email Address</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($_POST['email'] ?? ''); ?>" 
                       placeholder="example@company.com">
            </div>
            
            <div class="form-group">
                <label>Phone Number</label>
                <input type="tel" name="phone" value="<?php echo htmlspecialchars($_POST['phone'] ?? ''); ?>" 
                       placeholder="+8801XXXXXXXXX">
            </div>
            
            <div class="form-group">
                <label><span class="required">*</span> Role</label>
                <select name="role" required>
                    <option value="field_agent" <?php echo ($_POST['role'] ?? '') == 'field_agent' ? 'selected' : ''; ?>>Field Agent</option>
                    <option value="supervisor" <?php echo ($_POST['role'] ?? '') == 'supervisor' ? 'selected' : ''; ?>>Supervisor</option>
                    <option value="admin" <?php echo ($_POST['role'] ?? '') == 'admin' ? 'selected' : ''; ?>>Administrator</option>
                </select>
                <div class="help-text">Field Agent: Can add leads only | Supervisor: Can view reports | Admin: Full access</div>
            </div>
            
            <div class="form-group">
                <label>Department</label>
                <input type="text" name="department" value="<?php echo htmlspecialchars($_POST['department'] ?? ''); ?>" 
                       placeholder="e.g., Sales, Marketing, Support, IT">
            </div>
            
            <div class="form-group">
                <label>Join Date</label>
                <input type="date" name="join_date" value="<?php echo $_POST['join_date'] ?? date('Y-m-d'); ?>">
            </div>
            
            <div class="form-group">
                <label><span class="required">*</span> Password</label>
                <input type="password" name="password" required placeholder="Enter password">
                <div class="help-text">Minimum 6 characters, at least one number and one letter</div>
            </div>
            
            <div class="form-group">
                <label><span class="required">*</span> Confirm Password</label>
                <input type="password" name="confirm_password" required placeholder="Re-enter password">
            </div>
            
            <div style="display: flex; gap: 15px; justify-content: flex-end; margin-top: 30px;">
                <a href="manage_users.php" class="btn-cancel">
                    <i class="fas fa-times"></i> Cancel
                </a>
                <button type="submit" class="btn-create">
                    <i class="fas fa-save"></i> Create User
                </button>
            </div>
        </form>
    </div>
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