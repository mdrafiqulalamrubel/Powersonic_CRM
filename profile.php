<?php
ob_start();
require_once 'config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$message = '';
$error = '';

// Get user data - only select columns that exist
$stmt = $pdo->prepare("SELECT id, username, password, full_name, role, created_at, 
                              status, last_login, profile_image 
                       FROM users WHERE id = ?");
$stmt->execute([$_SESSION['user_id']]);
$user = $stmt->fetch();

// Function to upload profile photo
function uploadProfilePhoto($file, $user_id) {
    $target_dir = "uploads/profiles/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['error' => 'Only JPG, JPEG, PNG, GIF, WEBP files are allowed'];
    }
    
    if ($file["size"] > 5 * 1024 * 1024) {
        return ['error' => 'File size must be less than 5MB'];
    }
    
    $unique_name = 'user_' . $user_id . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_name;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => $target_file];
    }
    
    return ['error' => 'Failed to upload file'];
}

// Handle profile photo only upload (AJAX or separate request)
if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0 && !isset($_POST['full_name'])) {
    $upload_result = uploadProfilePhoto($_FILES['profile_photo'], $_SESSION['user_id']);
    if (isset($upload_result['success'])) {
        $photo_update = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
        $photo_update->execute([$upload_result['success'], $_SESSION['user_id']]);
        $message = "Profile photo updated successfully!";
        // Refresh user data
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
    } elseif (isset($upload_result['error'])) {
        $error = $upload_result['error'];
    }
}

// Handle full profile update
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['full_name'])) {
    // Get POST values with null coalescing to avoid undefined warnings
    $full_name = $_POST['full_name'] ?? $user['full_name'];
    $email = $_POST['email'] ?? $user['email'];
    $phone = $_POST['phone'] ?? $user['phone'];
    $mobile_alternative = $_POST['mobile_alternative'] ?? null;
    $date_of_birth = $_POST['date_of_birth'] ?? null;
    $gender = $_POST['gender'] ?? null;
    $blood_group = $_POST['blood_group'] ?? null;
    $nationality = $_POST['nationality'] ?? 'Bangladeshi';
    $nid_number = $_POST['nid_number'] ?? null;
    $tin_number = $_POST['tin_number'] ?? null;
    $present_address = $_POST['present_address'] ?? null;
    $permanent_address = $_POST['permanent_address'] ?? null;
    $city = $_POST['city'] ?? null;
    $postal_code = $_POST['postal_code'] ?? null;
    $emergency_contact_name = $_POST['emergency_contact_name'] ?? null;
    $emergency_contact_relation = $_POST['emergency_contact_relation'] ?? null;
    $emergency_contact_phone = $_POST['emergency_contact_phone'] ?? null;
    $department = $_POST['department'] ?? $user['department'];
    $designation = $_POST['designation'] ?? null;
    $employee_id = $_POST['employee_id'] ?? null;
    $joining_date = $_POST['joining_date'] ?? $user['join_date'];
    $employment_type = $_POST['employment_type'] ?? null;
    $work_location = $_POST['work_location'] ?? null;
    $reporting_manager = $_POST['reporting_manager'] ?? null;
    $bank_name = $_POST['bank_name'] ?? null;
    $bank_account_name = $_POST['bank_account_name'] ?? null;
    $bank_account_number = $_POST['bank_account_number'] ?? null;
    $bank_routing_number = $_POST['bank_routing_number'] ?? null;
    $salary_amount = $_POST['salary_amount'] ?? null;
    $salary_currency = $_POST['salary_currency'] ?? 'BDT';
    $social_facebook = $_POST['social_facebook'] ?? null;
    $social_linkedin = $_POST['social_linkedin'] ?? null;
    $social_twitter = $_POST['social_twitter'] ?? null;
    $bio = $_POST['bio'] ?? null;
    
    // Handle profile photo upload if new photo is uploaded with form
    if (isset($_FILES['profile_photo']) && $_FILES['profile_photo']['error'] == 0) {
        $upload_result = uploadProfilePhoto($_FILES['profile_photo'], $_SESSION['user_id']);
        if (isset($upload_result['success'])) {
            $profile_photo_path = $upload_result['success'];
            $photo_update = $pdo->prepare("UPDATE users SET profile_image = ? WHERE id = ?");
            $photo_update->execute([$profile_photo_path, $_SESSION['user_id']]);
            $message = "Profile photo updated successfully! ";
        } elseif (isset($upload_result['error'])) {
            $error = $upload_result['error'];
        }
    }
    
    // Update user information
    try {
        // Update user information - only update columns that exist
        $update = $pdo->prepare("UPDATE users SET 
            full_name = ?
            WHERE id = ?");
        $update->execute([$full_name, $_SESSION['user_id']]);
        
        // Check if employee_details table exists, if not create it
        $pdo->exec("CREATE TABLE IF NOT EXISTS employee_details (
            id INT PRIMARY KEY AUTO_INCREMENT,
            user_id INT UNIQUE,
            mobile_alternative VARCHAR(20),
            date_of_birth DATE,
            gender VARCHAR(20),
            blood_group VARCHAR(10),
            nationality VARCHAR(50),
            nid_number VARCHAR(50),
            tin_number VARCHAR(50),
            present_address TEXT,
            permanent_address TEXT,
            city VARCHAR(100),
            postal_code VARCHAR(20),
            emergency_contact_name VARCHAR(100),
            emergency_contact_relation VARCHAR(50),
            emergency_contact_phone VARCHAR(20),
            designation VARCHAR(100),
            employee_id VARCHAR(50),
            employment_type VARCHAR(50),
            work_location VARCHAR(100),
            reporting_manager VARCHAR(100),
            bank_name VARCHAR(100),
            bank_account_name VARCHAR(100),
            bank_account_number VARCHAR(50),
            bank_routing_number VARCHAR(50),
            salary_amount DECIMAL(12,2),
            salary_currency VARCHAR(3),
            social_facebook VARCHAR(255),
            social_linkedin VARCHAR(255),
            social_twitter VARCHAR(255),
            bio TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE
        )");
        
        // Insert or update employee details
        $detail_check = $pdo->prepare("SELECT id FROM employee_details WHERE user_id = ?");
        $detail_check->execute([$_SESSION['user_id']]);
        
        if ($detail_check->fetch()) {
            $detail_update = $pdo->prepare("UPDATE employee_details SET 
                mobile_alternative = ?, date_of_birth = ?, gender = ?, blood_group = ?,
                nationality = ?, nid_number = ?, tin_number = ?, present_address = ?,
                permanent_address = ?, city = ?, postal_code = ?,
                emergency_contact_name = ?, emergency_contact_relation = ?, emergency_contact_phone = ?,
                designation = ?, employee_id = ?, employment_type = ?, work_location = ?,
                reporting_manager = ?, bank_name = ?, bank_account_name = ?,
                bank_account_number = ?, bank_routing_number = ?, salary_amount = ?,
                salary_currency = ?, social_facebook = ?, social_linkedin = ?,
                social_twitter = ?, bio = ?
                WHERE user_id = ?");
            $detail_update->execute([
                $mobile_alternative, $date_of_birth, $gender, $blood_group,
                $nationality, $nid_number, $tin_number, $present_address,
                $permanent_address, $city, $postal_code,
                $emergency_contact_name, $emergency_contact_relation, $emergency_contact_phone,
                $designation, $employee_id, $employment_type, $work_location,
                $reporting_manager, $bank_name, $bank_account_name,
                $bank_account_number, $bank_routing_number, $salary_amount,
                $salary_currency, $social_facebook, $social_linkedin,
                $social_twitter, $bio, $_SESSION['user_id']
            ]);
        } else {
            $detail_insert = $pdo->prepare("INSERT INTO employee_details (
                user_id, mobile_alternative, date_of_birth, gender, blood_group,
                nationality, nid_number, tin_number, present_address, permanent_address,
                city, postal_code, emergency_contact_name, emergency_contact_relation,
                emergency_contact_phone, designation, employee_id, employment_type,
                work_location, reporting_manager, bank_name, bank_account_name,
                bank_account_number, bank_routing_number, salary_amount, salary_currency,
                social_facebook, social_linkedin, social_twitter, bio
            ) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
            $detail_insert->execute([
                $_SESSION['user_id'], $mobile_alternative, $date_of_birth, $gender, $blood_group,
                $nationality, $nid_number, $tin_number, $present_address, $permanent_address,
                $city, $postal_code, $emergency_contact_name, $emergency_contact_relation,
                $emergency_contact_phone, $designation, $employee_id, $employment_type,
                $work_location, $reporting_manager, $bank_name, $bank_account_name,
                $bank_account_number, $bank_routing_number, $salary_amount, $salary_currency,
                $social_facebook, $social_linkedin, $social_twitter, $bio
            ]);
        }
        
        $_SESSION['full_name'] = $full_name;
        $message .= "Profile updated successfully!";
        
        // Refresh user data
        $stmt->execute([$_SESSION['user_id']]);
        $user = $stmt->fetch();
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get employee details
$detail_stmt = $pdo->prepare("SELECT * FROM employee_details WHERE user_id = ?");
$detail_stmt->execute([$_SESSION['user_id']]);
$details = $detail_stmt->fetch();

// If no details exist, create an empty array to avoid warnings
if (!$details) {
    $details = [];
}
?>

<style>
    .profile-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .profile-header {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        border-radius: 15px;
        padding: 30px;
        margin-bottom: 30px;
        color: white;
        display: flex;
        align-items: center;
        gap: 30px;
        flex-wrap: wrap;
    }
    
    .profile-avatar {
        text-align: center;
    }
    
    .profile-avatar img {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        object-fit: cover;
        border: 4px solid white;
        box-shadow: 0 5px 15px rgba(0,0,0,0.2);
        background: white;
    }
    
    .profile-avatar .default-avatar {
        width: 150px;
        height: 150px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 64px;
        border: 4px solid white;
    }
    
    .profile-info h2 {
        margin-bottom: 5px;
    }
    
    .profile-info .employee-id {
        opacity: 0.9;
        margin-bottom: 10px;
    }
    
    .profile-badge {
        display: inline-block;
        padding: 5px 12px;
        background: rgba(255,255,255,0.2);
        border-radius: 20px;
        font-size: 12px;
        margin-top: 10px;
        margin-right: 8px;
    }
    
    .profile-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .profile-card h3 {
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .profile-card h3 i {
        color: #3498db;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 5px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 13px;
        color: #555;
    }
    
    .form-group label .required {
        color: #e74c3c;
    }
    
    .form-group input, 
    .form-group select, 
    .form-group textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 8px;
        font-size: 14px;
        transition: all 0.3s;
    }
    
    .form-group input:focus, 
    .form-group select:focus, 
    .form-group textarea:focus {
        border-color: #3498db;
        outline: none;
        box-shadow: 0 0 0 3px rgba(52,152,219,0.1);
    }
    
    .form-group input[disabled] {
        background: #f5f5f5;
        color: #666;
    }
    
    .photo-upload {
        margin-top: 10px;
    }
    
    .photo-upload label {
        display: inline-block;
        padding: 8px 15px;
        background: rgba(255,255,255,0.2);
        border: 1px solid rgba(255,255,255,0.5);
        color: white;
        border-radius: 5px;
        cursor: pointer;
        font-size: 12px;
    }
    
    .photo-upload label:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .photo-upload input {
        display: none;
    }
    
    .btn-save {
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
    
    .btn-save:hover {
        background: #229954;
        transform: translateY(-2px);
    }
    
    .alert-success {
        background: #d4edda;
        color: #155724;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #27ae60;
    }
    
    .alert-error {
        background: #f8d7da;
        color: #721c24;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
        border-left: 4px solid #e74c3c;
    }
    
    @media (max-width: 768px) {
        .profile-header {
            flex-direction: column;
            text-align: center;
        }
        .form-grid {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="profile-container">
    <!-- Profile Header -->
    <div class="profile-header">
        <div class="profile-avatar">
            <?php if(!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                <img src="<?php echo $user['profile_image']; ?>" alt="Profile Photo">
            <?php else: ?>
                <div class="default-avatar">
                    <i class="fas fa-user-circle"></i>
                </div>
            <?php endif; ?>
            <form method="POST" enctype="multipart/form-data" style="margin-top: 10px;" id="photoForm">
                <div class="photo-upload">
                    <label for="profile_photo">
                        <i class="fas fa-camera"></i> Change Photo
                    </label>
                    <input type="file" name="profile_photo" id="profile_photo" accept="image/*" onchange="this.form.submit()">
                </div>
            </form>
        </div>
        <div class="profile-info">
            <h2><?php echo htmlspecialchars($user['full_name']); ?></h2>
            <div class="employee-id">
                <i class="fas fa-id-badge"></i> <?php echo htmlspecialchars($details['employee_id'] ?? 'Not assigned'); ?>
            </div>
            <div>
                <span class="profile-badge">
                    <i class="fas fa-briefcase"></i> <?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>
                </span>
                <?php if(!empty($details['designation'])): ?>
                <span class="profile-badge">
                    <i class="fas fa-user-tie"></i> <?php echo htmlspecialchars($details['designation']); ?>
                </span>
                <?php endif; ?>
                <?php if(!empty($details['department']) || !empty($user['department'])): ?>
                <span class="profile-badge">
                    <i class="fas fa-building"></i> <?php echo htmlspecialchars($details['department'] ?? $user['department'] ?? ''); ?>
                </span>
                <?php endif; ?>
            </div>
        </div>
    </div>
    
    <?php if($message): ?>
        <div class="alert-success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="alert-error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <!-- Personal Information -->
        <div class="profile-card">
            <h3><i class="fas fa-user"></i> Personal Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Full Name <span class="required">*</span></label>
                    <input type="text" name="full_name" value="<?php echo htmlspecialchars($user['full_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Username</label>
                    <input type="text" value="<?php echo $user['username']; ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mobile Number (Primary)</label>
                    <input type="tel" name="phone" value="<?php echo htmlspecialchars($user['phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Alternative Mobile</label>
                    <input type="tel" name="mobile_alternative" value="<?php echo htmlspecialchars($details['mobile_alternative'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Date of Birth</label>
                    <input type="date" name="date_of_birth" value="<?php echo $details['date_of_birth'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>Gender</label>
                    <select name="gender">
                        <option value="">Select Gender</option>
                        <option value="Male" <?php echo ($details['gender'] ?? '') == 'Male' ? 'selected' : ''; ?>>Male</option>
                        <option value="Female" <?php echo ($details['gender'] ?? '') == 'Female' ? 'selected' : ''; ?>>Female</option>
                        <option value="Other" <?php echo ($details['gender'] ?? '') == 'Other' ? 'selected' : ''; ?>>Other</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Blood Group</label>
                    <select name="blood_group">
                        <option value="">Select Blood Group</option>
                        <option value="A+" <?php echo ($details['blood_group'] ?? '') == 'A+' ? 'selected' : ''; ?>>A+</option>
                        <option value="A-" <?php echo ($details['blood_group'] ?? '') == 'A-' ? 'selected' : ''; ?>>A-</option>
                        <option value="B+" <?php echo ($details['blood_group'] ?? '') == 'B+' ? 'selected' : ''; ?>>B+</option>
                        <option value="B-" <?php echo ($details['blood_group'] ?? '') == 'B-' ? 'selected' : ''; ?>>B-</option>
                        <option value="O+" <?php echo ($details['blood_group'] ?? '') == 'O+' ? 'selected' : ''; ?>>O+</option>
                        <option value="O-" <?php echo ($details['blood_group'] ?? '') == 'O-' ? 'selected' : ''; ?>>O-</option>
                        <option value="AB+" <?php echo ($details['blood_group'] ?? '') == 'AB+' ? 'selected' : ''; ?>>AB+</option>
                        <option value="AB-" <?php echo ($details['blood_group'] ?? '') == 'AB-' ? 'selected' : ''; ?>>AB-</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Nationality</label>
                    <input type="text" name="nationality" value="<?php echo htmlspecialchars($details['nationality'] ?? 'Bangladeshi'); ?>">
                </div>
                <div class="form-group">
                    <label>NID Number</label>
                    <input type="text" name="nid_number" value="<?php echo htmlspecialchars($details['nid_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>TIN Number</label>
                    <input type="text" name="tin_number" value="<?php echo htmlspecialchars($details['tin_number'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Address Information -->
        <div class="profile-card">
            <h3><i class="fas fa-map-marker-alt"></i> Address Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Present Address</label>
                    <textarea name="present_address" rows="3"><?php echo htmlspecialchars($details['present_address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>Permanent Address</label>
                    <textarea name="permanent_address" rows="3"><?php echo htmlspecialchars($details['permanent_address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="city" value="<?php echo htmlspecialchars($details['city'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" name="postal_code" value="<?php echo htmlspecialchars($details['postal_code'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Emergency Contact -->
        <div class="profile-card">
            <h3><i class="fas fa-ambulance"></i> Emergency Contact</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Emergency Contact Name</label>
                    <input type="text" name="emergency_contact_name" value="<?php echo htmlspecialchars($details['emergency_contact_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Relationship</label>
                    <input type="text" name="emergency_contact_relation" value="<?php echo htmlspecialchars($details['emergency_contact_relation'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Emergency Contact Phone</label>
                    <input type="tel" name="emergency_contact_phone" value="<?php echo htmlspecialchars($details['emergency_contact_phone'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Employment Information -->
        <div class="profile-card">
            <h3><i class="fas fa-briefcase"></i> Employment Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Employee ID</label>
                    <input type="text" name="employee_id" value="<?php echo htmlspecialchars($details['employee_id'] ?? ''); ?>" placeholder="EMP-XXXX">
                </div>
                <div class="form-group">
                    <label>Department</label>
                    <select name="department">
                        <option value="">Select Department</option>
                        <option value="Sales" <?php echo (($details['department'] ?? $user['department'] ?? '') == 'Sales') ? 'selected' : ''; ?>>Sales</option>
                        <option value="Marketing" <?php echo (($details['department'] ?? $user['department'] ?? '') == 'Marketing') ? 'selected' : ''; ?>>Marketing</option>
                        <option value="IT" <?php echo (($details['department'] ?? $user['department'] ?? '') == 'IT') ? 'selected' : ''; ?>>IT</option>
                        <option value="HR" <?php echo (($details['department'] ?? $user['department'] ?? '') == 'HR') ? 'selected' : ''; ?>>HR</option>
                        <option value="Finance" <?php echo (($details['department'] ?? $user['department'] ?? '') == 'Finance') ? 'selected' : ''; ?>>Finance</option>
                        <option value="Operations" <?php echo (($details['department'] ?? $user['department'] ?? '') == 'Operations') ? 'selected' : ''; ?>>Operations</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Designation</label>
                    <input type="text" name="designation" value="<?php echo htmlspecialchars($details['designation'] ?? ''); ?>" placeholder="e.g., Senior Sales Executive">
                </div>
                <div class="form-group">
                    <label>Employment Type</label>
                    <select name="employment_type">
                        <option value="">Select Type</option>
                        <option value="Permanent" <?php echo ($details['employment_type'] ?? '') == 'Permanent' ? 'selected' : ''; ?>>Permanent</option>
                        <option value="Contractual" <?php echo ($details['employment_type'] ?? '') == 'Contractual' ? 'selected' : ''; ?>>Contractual</option>
                        <option value="Intern" <?php echo ($details['employment_type'] ?? '') == 'Intern' ? 'selected' : ''; ?>>Intern</option>
                        <option value="Probation" <?php echo ($details['employment_type'] ?? '') == 'Probation' ? 'selected' : ''; ?>>Probation</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Joining Date</label>
                    <input type="date" name="joining_date" value="<?php echo $user['join_date'] ?? $details['joining_date'] ?? ''; ?>">
                </div>
                <div class="form-group">
                    <label>Work Location</label>
                    <input type="text" name="work_location" value="<?php echo htmlspecialchars($details['work_location'] ?? ''); ?>" placeholder="Office/Branch Name">
                </div>
                <div class="form-group">
                    <label>Reporting Manager</label>
                    <input type="text" name="reporting_manager" value="<?php echo htmlspecialchars($details['reporting_manager'] ?? ''); ?>">
                </div>
            </div>
        </div>
        
        <!-- Banking Information -->
        <div class="profile-card">
            <h3><i class="fas fa-university"></i> Banking Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($details['bank_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($details['bank_account_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($details['bank_account_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Routing Number</label>
                    <input type="text" name="bank_routing_number" value="<?php echo htmlspecialchars($details['bank_routing_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Salary Amount</label>
                    <input type="number" step="0.01" name="salary_amount" value="<?php echo $details['salary_amount'] ?? ''; ?>" placeholder="0.00">
                </div>
                <div class="form-group">
                    <label>Currency</label>
                    <select name="salary_currency">
                        <option value="BDT" <?php echo ($details['salary_currency'] ?? 'BDT') == 'BDT' ? 'selected' : ''; ?>>BDT (Taka)</option>
                        <option value="USD" <?php echo ($details['salary_currency'] ?? '') == 'USD' ? 'selected' : ''; ?>>USD (Dollar)</option>
                    </select>
                </div>
            </div>
        </div>
        
        <!-- Social Media & Bio -->
        <div class="profile-card">
            <h3><i class="fas fa-share-alt"></i> Social Media & Bio</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fab fa-facebook"></i> Facebook</label>
                    <input type="url" name="social_facebook" value="<?php echo htmlspecialchars($details['social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/username">
                </div>
                <div class="form-group">
                    <label><i class="fab fa-linkedin"></i> LinkedIn</label>
                    <input type="url" name="social_linkedin" value="<?php echo htmlspecialchars($details['social_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/in/username">
                </div>
                <div class="form-group">
                    <label><i class="fab fa-twitter"></i> Twitter</label>
                    <input type="url" name="social_twitter" value="<?php echo htmlspecialchars($details['social_twitter'] ?? ''); ?>" placeholder="https://twitter.com/username">
                </div>
                <div class="form-group">
                    <label>Short Bio</label>
                    <textarea name="bio" rows="4" placeholder="Tell us about yourself..."><?php echo htmlspecialchars($details['bio'] ?? ''); ?></textarea>
                </div>
            </div>
        </div>
        
        <!-- Role Information (Read Only) -->
        <div class="profile-card">
            <h3><i class="fas fa-shield-alt"></i> Account Information</h3>
            <div class="form-grid">
                <div class="form-group">
                    <label>Role</label>
                    <input type="text" value="<?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Account Status</label>
                    <input type="text" value="<?php echo ucfirst($user['status'] ?? 'Active'); ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Last Login</label>
                    <input type="text" value="<?php echo $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : 'Never'; ?>" disabled>
                </div>
                <div class="form-group">
                    <label>Member Since</label>
                    <input type="text" value="<?php echo date('Y-m-d', strtotime($user['created_at'])); ?>" disabled>
                </div>
            </div>
        </div>
        
        <div style="text-align: center; margin-top: 30px;">
            <button type="submit" class="btn-save">
                <i class="fas fa-save"></i> Save All Changes
            </button>
            <a href="change_password.php" style="margin-left: 15px; color: #3498db; text-decoration: none;">
                <i class="fas fa-key"></i> Change Password
            </a>
        </div>                
    </form>

        <!-- Add this button next to the edit button or at the top right -->
        <a href="export_profile_pdf.php?id=<?php echo $_SESSION['user_id']; ?>" target="_blank" class="btn" style="background: #e74c3c;">
            <i class="fas fa-file-pdf"></i> Download PDF
        </a>

        <?php if(isAdmin()): ?>
        <!-- For admin, add button to download any user's profile -->
        <a href="export_profile_pdf.php?id=<?php echo $user['id']; ?>" target="_blank" class="btn" style="background: #e74c3c;">
            <i class="fas fa-file-pdf"></i> Export PDF
        </a>
        <?php endif; ?>
</div>

<script>
    // Auto-submit photo form when file is selected
    document.getElementById('profile_photo')?.addEventListener('change', function() {
        this.form.submit();
    });
</script>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>