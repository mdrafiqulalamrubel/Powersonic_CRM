<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$message = '';
$error = '';

// Get company settings
$stmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
$company = $stmt->fetch();

if (!$company) {
    // Insert default if not exists
    $pdo->exec("INSERT INTO company_settings (company_name) VALUES ('Power Sonic')");
    $stmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
    $company = $stmt->fetch();
}

// Handle logo upload
function uploadCompanyLogo($file, $type = 'logo') {
    $target_dir = "uploads/company/";
    if (!file_exists($target_dir)) {
        mkdir($target_dir, 0777, true);
    }
    
    $file_extension = strtolower(pathinfo($file["name"], PATHINFO_EXTENSION));
    $allowed_types = ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg'];
    
    if (!in_array($file_extension, $allowed_types)) {
        return ['error' => 'Only JPG, JPEG, PNG, GIF, WEBP, SVG files are allowed'];
    }
    
    if ($file["size"] > 2 * 1024 * 1024) {
        return ['error' => 'File size must be less than 2MB'];
    }
    
    $unique_name = 'company_' . $type . '_' . time() . '.' . $file_extension;
    $target_file = $target_dir . $unique_name;
    
    if (move_uploaded_file($file["tmp_name"], $target_file)) {
        return ['success' => $target_file];
    }
    
    return ['error' => 'Failed to upload file'];
}

// Handle form submission
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Get all POST values
    $company_name = $_POST['company_name'] ?? '';
    $company_email = $_POST['company_email'] ?? '';
    $company_phone = $_POST['company_phone'] ?? '';
    $company_mobile = $_POST['company_mobile'] ?? '';
    $company_address = $_POST['company_address'] ?? '';
    $company_city = $_POST['company_city'] ?? '';
    $company_state = $_POST['company_state'] ?? '';
    $company_country = $_POST['company_country'] ?? 'Bangladesh';
    $company_postal_code = $_POST['company_postal_code'] ?? '';
    $company_website = $_POST['company_website'] ?? '';
    $company_business_hours = $_POST['company_business_hours'] ?? '';
    $company_social_facebook = $_POST['company_social_facebook'] ?? '';
    $company_social_linkedin = $_POST['company_social_linkedin'] ?? '';
    $company_social_twitter = $_POST['company_social_twitter'] ?? '';
    $company_social_instagram = $_POST['company_social_instagram'] ?? '';
    $company_registration_no = $_POST['company_registration_no'] ?? '';
    $company_tax_id = $_POST['company_tax_id'] ?? '';
    $company_bin_no = $_POST['company_bin_no'] ?? '';
    $bank_name = $_POST['bank_name'] ?? '';
    $bank_account_name = $_POST['bank_account_name'] ?? '';
    $bank_account_number = $_POST['bank_account_number'] ?? '';
    $bank_routing_number = $_POST['bank_routing_number'] ?? '';
    $footer_text = $_POST['footer_text'] ?? '';
    $invoice_prefix = $_POST['invoice_prefix'] ?? 'INV';
    $currency_symbol = $_POST['currency_symbol'] ?? 'BDT';
    $date_format = $_POST['date_format'] ?? 'Y-m-d';
    $timezone = $_POST['timezone'] ?? 'Asia/Dhaka';
    
    // Handle logo upload
    $logo_path = $company['company_logo'];
    if (isset($_FILES['company_logo']) && $_FILES['company_logo']['error'] == 0) {
        $upload_result = uploadCompanyLogo($_FILES['company_logo'], 'logo');
        if (isset($upload_result['success'])) {
            $logo_path = $upload_result['success'];
            $message = "Logo uploaded successfully! ";
        } elseif (isset($upload_result['error'])) {
            $error = $upload_result['error'];
        }
    }
    
    // Handle favicon upload
    $favicon_path = $company['company_favicon'];
    if (isset($_FILES['company_favicon']) && $_FILES['company_favicon']['error'] == 0) {
        $upload_result = uploadCompanyLogo($_FILES['company_favicon'], 'favicon');
        if (isset($upload_result['success'])) {
            $favicon_path = $upload_result['success'];
            $message .= "Favicon uploaded successfully! ";
        } elseif (isset($upload_result['error'])) {
            $error = $upload_result['error'];
        }
    }
    
    try {
        $update = $pdo->prepare("UPDATE company_settings SET 
            company_name = ?, company_logo = ?, company_favicon = ?,
            company_email = ?, company_phone = ?, company_mobile = ?,
            company_address = ?, company_city = ?, company_state = ?,
            company_country = ?, company_postal_code = ?, company_website = ?,
            company_business_hours = ?, company_social_facebook = ?,
            company_social_linkedin = ?, company_social_twitter = ?,
            company_social_instagram = ?, company_registration_no = ?,
            company_tax_id = ?, company_bin_no = ?, bank_name = ?,
            bank_account_name = ?, bank_account_number = ?, bank_routing_number = ?,
            footer_text = ?, invoice_prefix = ?, currency_symbol = ?,
            date_format = ?, timezone = ?
            WHERE id = ?");
        
        $update->execute([
            $company_name, $logo_path, $favicon_path,
            $company_email, $company_phone, $company_mobile,
            $company_address, $company_city, $company_state,
            $company_country, $company_postal_code, $company_website,
            $company_business_hours, $company_social_facebook,
            $company_social_linkedin, $company_social_twitter,
            $company_social_instagram, $company_registration_no,
            $company_tax_id, $company_bin_no, $bank_name,
            $bank_account_name, $bank_account_number, $bank_routing_number,
            $footer_text, $invoice_prefix, $currency_symbol,
            $date_format, $timezone, $company['id']
        ]);
        
        $message .= "Company information updated successfully!";
        
        // Refresh company data
        $stmt = $pdo->query("SELECT * FROM company_settings LIMIT 1");
        $company = $stmt->fetch();
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}

// Get currency symbols list
$currencies = [
    'BDT' => 'BDT (৳ Taka)',
    'USD' => 'USD ($ Dollar)',
    'EUR' => 'EUR (€ Euro)',
    'GBP' => 'GBP (£ Pound)',
    'INR' => 'INR (₹ Rupee)',
    'AED' => 'AED (د.إ Dirham)',
    'SAR' => 'SAR (﷼ Riyal)'
];
?>

<style>
    .settings-container {
        max-width: 1200px;
        margin: 0 auto;
    }
    
    .settings-section {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .settings-section h3 {
        color: #2c3e50;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #3498db;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .settings-section h3 i {
        color: #3498db;
    }
    
    .form-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 20px;
    }
    
    .form-group {
        margin-bottom: 5px;
    }
    
    .form-group label {
        display: block;
        margin-bottom: 6px;
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
        padding: 10px 12px;
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
    
    .logo-upload {
        display: flex;
        align-items: center;
        gap: 20px;
        flex-wrap: wrap;
    }
    
    .logo-preview {
        width: 120px;
        height: 120px;
        border: 2px solid #ddd;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #f8f9fa;
    }
    
    .logo-preview img {
        max-width: 100%;
        max-height: 100%;
        object-fit: contain;
    }
    
    .favicon-preview {
        width: 64px;
        height: 64px;
        border: 2px solid #ddd;
        border-radius: 12px;
        display: flex;
        align-items: center;
        justify-content: center;
        overflow: hidden;
        background: #f8f9fa;
    }
    
    .upload-btn {
        display: inline-block;
        padding: 8px 16px;
        background: #3498db;
        color: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 13px;
    }
    
    .upload-btn:hover {
        background: #2980b9;
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
    
    .info-text {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    
    @media (max-width: 768px) {
        .form-grid {
            grid-template-columns: 1fr;
        }
        .logo-upload {
            flex-direction: column;
            align-items: flex-start;
        }
    }
</style>

<div class="settings-container">
    <div class="settings-section">
        <h3><i class="fas fa-building"></i> Company Information</h3>
        
        <?php if($message): ?>
            <div class="alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="alert-error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <form method="POST" enctype="multipart/form-data">
            <!-- Company Logo & Favicon -->
            <div style="margin-bottom: 25px;">
                <h4 style="margin-bottom: 15px; color: #2c3e50;">Company Branding</h4>
                <div class="form-grid">
                    <div class="form-group">
                        <label>Company Logo</label>
                        <div class="logo-upload">
                            <div class="logo-preview">
                                <?php if($company['company_logo'] && file_exists($company['company_logo'])): ?>
                                    <img src="<?php echo $company['company_logo']; ?>" alt="Company Logo">
                                <?php else: ?>
                                    <i class="fas fa-building" style="font-size: 48px; color: #ccc;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="upload-btn">
                                    <i class="fas fa-upload"></i> Upload Logo
                                    <input type="file" name="company_logo" accept="image/*" style="display: none;" onchange="previewLogo(this, 'logo-preview-img')">
                                </label>
                                <div class="info-text">Recommended: 200x200px, Max 2MB</div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label>Favicon</label>
                        <div class="logo-upload">
                            <div class="favicon-preview">
                                <?php if($company['company_favicon'] && file_exists($company['company_favicon'])): ?>
                                    <img src="<?php echo $company['company_favicon']; ?>" alt="Favicon">
                                <?php else: ?>
                                    <i class="fas fa-globe" style="font-size: 32px; color: #ccc;"></i>
                                <?php endif; ?>
                            </div>
                            <div>
                                <label class="upload-btn">
                                    <i class="fas fa-upload"></i> Upload Favicon
                                    <input type="file" name="company_favicon" accept="image/*" style="display: none;" onchange="previewFavicon(this)">
                                </label>
                                <div class="info-text">Recommended: 32x32px or 64x64px</div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            
            <!-- Basic Information -->
            <h4 style="margin-bottom: 15px; color: #2c3e50;">Basic Information</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Company Name <span class="required">*</span></label>
                    <input type="text" name="company_name" value="<?php echo htmlspecialchars($company['company_name']); ?>" required>
                </div>
                <div class="form-group">
                    <label>Email Address</label>
                    <input type="email" name="company_email" value="<?php echo htmlspecialchars($company['company_email'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Phone Number</label>
                    <input type="text" name="company_phone" value="<?php echo htmlspecialchars($company['company_phone'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Mobile Number</label>
                    <input type="text" name="company_mobile" value="<?php echo htmlspecialchars($company['company_mobile'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Website</label>
                    <input type="url" name="company_website" value="<?php echo htmlspecialchars($company['company_website'] ?? ''); ?>" placeholder="https://www.powersonic.com">
                </div>
                <div class="form-group">
                    <label>Business Hours</label>
                    <input type="text" name="company_business_hours" value="<?php echo htmlspecialchars($company['company_business_hours'] ?? ''); ?>" placeholder="Sat-Thu: 9AM - 6PM">
                </div>
            </div>
            
            <!-- Address Information -->
            <h4 style="margin: 20px 0 15px; color: #2c3e50;">Address Information</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Address</label>
                    <textarea name="company_address" rows="3"><?php echo htmlspecialchars($company['company_address'] ?? ''); ?></textarea>
                </div>
                <div class="form-group">
                    <label>City</label>
                    <input type="text" name="company_city" value="<?php echo htmlspecialchars($company['company_city'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>State/Province</label>
                    <input type="text" name="company_state" value="<?php echo htmlspecialchars($company['company_state'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Country</label>
                    <input type="text" name="company_country" value="<?php echo htmlspecialchars($company['company_country'] ?? 'Bangladesh'); ?>">
                </div>
                <div class="form-group">
                    <label>Postal Code</label>
                    <input type="text" name="company_postal_code" value="<?php echo htmlspecialchars($company['company_postal_code'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Registration & Tax Information -->
            <h4 style="margin: 20px 0 15px; color: #2c3e50;">Registration & Tax Information</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Registration Number</label>
                    <input type="text" name="company_registration_no" value="<?php echo htmlspecialchars($company['company_registration_no'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Tax ID (TIN)</label>
                    <input type="text" name="company_tax_id" value="<?php echo htmlspecialchars($company['company_tax_id'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>BIN Number</label>
                    <input type="text" name="company_bin_no" value="<?php echo htmlspecialchars($company['company_bin_no'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Banking Information -->
            <h4 style="margin: 20px 0 15px; color: #2c3e50;">Banking Information</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Bank Name</label>
                    <input type="text" name="bank_name" value="<?php echo htmlspecialchars($company['bank_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Account Holder Name</label>
                    <input type="text" name="bank_account_name" value="<?php echo htmlspecialchars($company['bank_account_name'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Account Number</label>
                    <input type="text" name="bank_account_number" value="<?php echo htmlspecialchars($company['bank_account_number'] ?? ''); ?>">
                </div>
                <div class="form-group">
                    <label>Routing Number</label>
                    <input type="text" name="bank_routing_number" value="<?php echo htmlspecialchars($company['bank_routing_number'] ?? ''); ?>">
                </div>
            </div>
            
            <!-- Social Media Links -->
            <h4 style="margin: 20px 0 15px; color: #2c3e50;">Social Media Links</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label><i class="fab fa-facebook"></i> Facebook</label>
                    <input type="url" name="company_social_facebook" value="<?php echo htmlspecialchars($company['company_social_facebook'] ?? ''); ?>" placeholder="https://facebook.com/powersonic">
                </div>
                <div class="form-group">
                    <label><i class="fab fa-linkedin"></i> LinkedIn</label>
                    <input type="url" name="company_social_linkedin" value="<?php echo htmlspecialchars($company['company_social_linkedin'] ?? ''); ?>" placeholder="https://linkedin.com/company/powersonic">
                </div>
                <div class="form-group">
                    <label><i class="fab fa-twitter"></i> Twitter</label>
                    <input type="url" name="company_social_twitter" value="<?php echo htmlspecialchars($company['company_social_twitter'] ?? ''); ?>" placeholder="https://twitter.com/powersonic">
                </div>
                <div class="form-group">
                    <label><i class="fab fa-instagram"></i> Instagram</label>
                    <input type="url" name="company_social_instagram" value="<?php echo htmlspecialchars($company['company_social_instagram'] ?? ''); ?>" placeholder="https://instagram.com/powersonic">
                </div>
            </div>
            
            <!-- Invoice & System Settings -->
            <h4 style="margin: 20px 0 15px; color: #2c3e50;">Invoice & System Settings</h4>
            <div class="form-grid">
                <div class="form-group">
                    <label>Invoice Prefix</label>
                    <input type="text" name="invoice_prefix" value="<?php echo htmlspecialchars($company['invoice_prefix'] ?? 'INV'); ?>" placeholder="INV">
                    <div class="info-text">e.g., INV-2024-001</div>
                </div>
                <div class="form-group">
                    <label>Currency Symbol</label>
                    <select name="currency_symbol">
                        <?php foreach($currencies as $code => $name): ?>
                            <option value="<?php echo $code; ?>" <?php echo ($company['currency_symbol'] ?? 'BDT') == $code ? 'selected' : ''; ?>>
                                <?php echo $name; ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </div>
                <div class="form-group">
                    <label>Date Format</label>
                    <select name="date_format">
                        <option value="Y-m-d" <?php echo ($company['date_format'] ?? 'Y-m-d') == 'Y-m-d' ? 'selected' : ''; ?>>YYYY-MM-DD (2024-01-15)</option>
                        <option value="d-m-Y" <?php echo ($company['date_format'] ?? '') == 'd-m-Y' ? 'selected' : ''; ?>>DD-MM-YYYY (15-01-2024)</option>
                        <option value="m/d/Y" <?php echo ($company['date_format'] ?? '') == 'm/d/Y' ? 'selected' : ''; ?>>MM/DD/YYYY (01/15/2024)</option>
                        <option value="d/m/Y" <?php echo ($company['date_format'] ?? '') == 'd/m/Y' ? 'selected' : ''; ?>>DD/MM/YYYY (15/01/2024)</option>
                    </select>
                </div>
                <div class="form-group">
                    <label>Timezone</label>
                    <select name="timezone">
                        <option value="Asia/Dhaka" <?php echo ($company['timezone'] ?? 'Asia/Dhaka') == 'Asia/Dhaka' ? 'selected' : ''; ?>>Asia/Dhaka (GMT+6)</option>
                        <option value="Asia/Kolkata" <?php echo ($company['timezone'] ?? '') == 'Asia/Kolkata' ? 'selected' : ''; ?>>Asia/Kolkata (GMT+5:30)</option>
                        <option value="Asia/Dubai" <?php echo ($company['timezone'] ?? '') == 'Asia/Dubai' ? 'selected' : ''; ?>>Asia/Dubai (GMT+4)</option>
                        <option value="America/New_York" <?php echo ($company['timezone'] ?? '') == 'America/New_York' ? 'selected' : ''; ?>>America/New York (GMT-5)</option>
                        <option value="Europe/London" <?php echo ($company['timezone'] ?? '') == 'Europe/London' ? 'selected' : ''; ?>>Europe/London (GMT+0)</option>
                    </select>
                </div>
            </div>
            
            <!-- Footer Text -->
            <div class="form-group" style="margin-top: 20px;">
                <label>Footer Text (Copyright/Disclaimer)</label>
                <textarea name="footer_text" rows="3" placeholder="Copyright &copy; 2024 Power Sonic. All rights reserved."><?php echo htmlspecialchars($company['footer_text'] ?? ''); ?></textarea>
            </div>
            
            <div style="text-align: center; margin-top: 30px;">
                <button type="submit" class="btn-save">
                    <i class="fas fa-save"></i> Save Company Information
                </button>
            </div>
        </form>
    </div>
</div>

<script>
    function previewLogo(input, previewClass) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.querySelector('.logo-preview');
                preview.innerHTML = `<img src="${e.target.result}" alt="Logo Preview" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
    
    function previewFavicon(input) {
        if (input.files && input.files[0]) {
            const reader = new FileReader();
            reader.onload = function(e) {
                const preview = document.querySelector('.favicon-preview');
                preview.innerHTML = `<img src="${e.target.result}" alt="Favicon Preview" style="max-width: 100%; max-height: 100%; object-fit: contain;">`;
            };
            reader.readAsDataURL(input.files[0]);
        }
    }
</script>

<?php require_once 'includes/footer.php'; ob_end_flush(); ?>