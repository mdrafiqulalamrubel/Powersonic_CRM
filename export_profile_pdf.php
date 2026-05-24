<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$user_id = $_GET['id'] ?? $_SESSION['user_id'];

// Check permission (admin can view any profile, users can only view their own)
if (!isAdmin() && $user_id != $_SESSION['user_id']) {
    redirect('index.php');
}

// Get user data
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$user_id]);
$user = $stmt->fetch();

if (!$user) {
    redirect('index.php');
}

// Get employee details
$detail_stmt = $pdo->prepare("SELECT * FROM employee_details WHERE user_id = ?");
$detail_stmt->execute([$user_id]);
$details = $detail_stmt->fetch();
if (!$details) {
    $details = [];
}

// Create PDF using HTML/CSS approach (simplest, no external library needed)
header('Content-Type: text/html');
header('Content-Disposition: inline; filename="profile_' . $user['username'] . '_' . date('Y-m-d') . '.html"');

?>
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Employee Profile - <?php echo htmlspecialchars($user['full_name']); ?></title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Arial, sans-serif;
            background: white;
            padding: 20px;
        }
        .profile-container {
            max-width: 1000px;
            margin: 0 auto;
            background: white;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 20px;
            display: flex;
            align-items: center;
            gap: 30px;
        }
        .avatar {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            background: rgba(255,255,255,0.2);
            display: flex;
            align-items: center;
            justify-content: center;
            font-size: 48px;
        }
        .avatar img {
            width: 120px;
            height: 120px;
            border-radius: 50%;
            object-fit: cover;
        }
        .profile-info h1 {
            margin-bottom: 5px;
        }
        .badge {
            display: inline-block;
            padding: 3px 10px;
            background: rgba(255,255,255,0.2);
            border-radius: 15px;
            font-size: 11px;
            margin-right: 8px;
            margin-top: 10px;
        }
        .section {
            background: #f8f9fa;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            page-break-inside: avoid;
        }
        .section h2 {
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 8px;
            margin-bottom: 15px;
            font-size: 18px;
        }
        .info-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            padding: 8px;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-label {
            font-size: 11px;
            color: #666;
            text-transform: uppercase;
            margin-bottom: 3px;
        }
        .info-value {
            font-size: 14px;
            font-weight: 500;
            color: #333;
        }
        .footer {
            text-align: center;
            margin-top: 30px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
            font-size: 10px;
            color: #999;
        }
        @media print {
            body {
                padding: 0;
                margin: 0;
            }
            .section {
                break-inside: avoid;
            }
            .no-print {
                display: none;
            }
        }
    </style>
</head>
<body>
    <div class="profile-container">
        <!-- Print Button -->
        <div class="no-print" style="text-align: right; margin-bottom: 15px;">
            <button onclick="window.print()" style="background: #3498db; color: white; padding: 8px 20px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-print"></i> Print / Save as PDF
            </button>
            <button onclick="window.close()" style="background: #95a5a6; color: white; padding: 8px 20px; border: none; border-radius: 5px; cursor: pointer; margin-left: 10px;">
                Close
            </button>
        </div>
        
        <!-- Header -->
        <div class="header">
            <div class="avatar">
                <?php if(!empty($user['profile_image']) && file_exists($user['profile_image'])): ?>
                    <img src="<?php echo $user['profile_image']; ?>" alt="Profile Photo">
                <?php else: ?>
                    <i class="fas fa-user-circle"></i>
                <?php endif; ?>
            </div>
            <div class="profile-info">
                <h1><?php echo htmlspecialchars($user['full_name']); ?></h1>
                <div><strong>Employee ID:</strong> <?php echo htmlspecialchars($details['employee_id'] ?? 'Not assigned'); ?></div>
                <div>
                    <span class="badge"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></span>
                    <?php if(!empty($details['designation'])): ?>
                    <span class="badge"><?php echo htmlspecialchars($details['designation']); ?></span>
                    <?php endif; ?>
                    <?php if(!empty($details['department']) || !empty($user['department'])): ?>
                    <span class="badge"><?php echo htmlspecialchars($details['department'] ?? $user['department'] ?? ''); ?></span>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Personal Information -->
        <div class="section">
            <h2>Personal Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Full Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['full_name']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Username</div>
                    <div class="info-value"><?php echo $user['username']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email Address</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['email'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Mobile Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($user['phone'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Alternative Mobile</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['mobile_alternative'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Date of Birth</div>
                    <div class="info-value"><?php echo $details['date_of_birth'] ?? 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Gender</div>
                    <div class="info-value"><?php echo $details['gender'] ?? 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Blood Group</div>
                    <div class="info-value"><?php echo $details['blood_group'] ?? 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Nationality</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['nationality'] ?? 'Bangladeshi'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">NID Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['nid_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">TIN Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['tin_number'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Address Information -->
        <div class="section">
            <h2>Address Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Present Address</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($details['present_address'] ?? 'N/A')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Permanent Address</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($details['permanent_address'] ?? 'N/A')); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">City</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['city'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Postal Code</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['postal_code'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Emergency Contact -->
        <div class="section">
            <h2>Emergency Contact</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Contact Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['emergency_contact_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Relationship</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['emergency_contact_relation'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['emergency_contact_phone'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Employment Information -->
        <div class="section">
            <h2>Employment Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Employee ID</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['employee_id'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Department</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['department'] ?? $user['department'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Designation</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['designation'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Employment Type</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['employment_type'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Joining Date</div>
                    <div class="info-value"><?php echo $user['join_date'] ?? $details['joining_date'] ?? 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Work Location</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['work_location'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Reporting Manager</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['reporting_manager'] ?? 'N/A'); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Banking Information -->
        <div class="section">
            <h2>Banking Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Bank Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['bank_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Holder Name</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['bank_account_name'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['bank_account_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Routing Number</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['bank_routing_number'] ?? 'N/A'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Salary Amount</div>
                    <div class="info-value"><?php echo number_format($details['salary_amount'] ?? 0, 2); ?> <?php echo $details['salary_currency'] ?? 'BDT'; ?></div>
                </div>
            </div>
        </div>
        
        <!-- Social Media -->
        <?php if(!empty($details['social_facebook']) || !empty($details['social_linkedin']) || !empty($details['social_twitter'])): ?>
        <div class="section">
            <h2>Social Media</h2>
            <div class="info-grid">
                <?php if(!empty($details['social_facebook'])): ?>
                <div class="info-item">
                    <div class="info-label">Facebook</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['social_facebook']); ?></div>
                </div>
                <?php endif; ?>
                <?php if(!empty($details['social_linkedin'])): ?>
                <div class="info-item">
                    <div class="info-label">LinkedIn</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['social_linkedin']); ?></div>
                </div>
                <?php endif; ?>
                <?php if(!empty($details['social_twitter'])): ?>
                <div class="info-item">
                    <div class="info-label">Twitter</div>
                    <div class="info-value"><?php echo htmlspecialchars($details['social_twitter']); ?></div>
                </div>
                <?php endif; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <!-- Bio -->
        <?php if(!empty($details['bio'])): ?>
        <div class="section">
            <h2>About</h2>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($details['bio'])); ?></div>
        </div>
        <?php endif; ?>
        
        <!-- Account Information -->
        <div class="section">
            <h2>Account Information</h2>
            <div class="info-grid">
                <div class="info-item">
                    <div class="info-label">Role</div>
                    <div class="info-value"><?php echo ucfirst(str_replace('_', ' ', $user['role'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Account Status</div>
                    <div class="info-value"><?php echo ucfirst($user['status'] ?? 'Active'); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Last Login</div>
                    <div class="info-value"><?php echo $user['last_login'] ? date('Y-m-d H:i:s', strtotime($user['last_login'])) : 'Never'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Member Since</div>
                    <div class="info-value"><?php echo date('Y-m-d', strtotime($user['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <!-- Footer -->
        <div class="footer">
            <p>Power Sonic CRM - Employee Profile Report</p>
            <p>Generated on: <?php echo date('Y-m-d H:i:s'); ?></p>
        </div>
    </div>
    
    <script>
        // Auto-print (optional - remove if you don't want auto print)
        // window.print();
    </script>
</body>
</html>
<?php
ob_end_flush();
?>