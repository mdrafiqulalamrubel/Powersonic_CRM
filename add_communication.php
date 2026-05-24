<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$lead_id = $_GET['lead_id'] ?? 0;
$lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$lead->execute([$lead_id]);
$lead = $lead->fetch();

if (!$lead) {
    redirect('view_leads.php');
}

$message = '';
$error = '';

// Handle file upload function
function uploadMultipleFiles($files, $communication_id, $pdo, $user_id) {
    $upload_dir = "uploads/communications/$communication_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $uploaded_files = [];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                      'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                      'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                      'text/plain', 'application/zip'];
    $max_size = 10 * 1024 * 1024; // 10MB
    
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] == 0 && !empty($tmp_name)) {
            $file_name = basename($files['name'][$key]);
            $file_size = $files['size'][$key];
            $file_type = $files['type'][$key];
            
            // Validate file type
            if (!in_array($file_type, $allowed_types)) {
                continue;
            }
            
            // Validate file size
            if ($file_size > $max_size) {
                continue;
            }
            
            // Generate unique filename
            $unique_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
            $file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                $stmt = $pdo->prepare("INSERT INTO communication_attachments (communication_id, file_name, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$communication_id, $file_name, $file_path, $file_size, $file_type, $user_id]);
                $uploaded_files[] = $file_name;
            }
        }
    }
    
    return $uploaded_files;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $type = $_POST['type'];
    $notes = $_POST['notes'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO communications (lead_id, communication_type, notes, created_by, has_attachments) VALUES (?, ?, ?, ?, ?)");
        $stmt->execute([$lead_id, $type, $notes, $_SESSION['user_id'], isset($_FILES['attachments']) ? true : false]);
        
        $communication_id = $pdo->lastInsertId();
        
        // Handle multiple file uploads
        $uploaded_files = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $uploaded_files = uploadMultipleFiles($_FILES['attachments'], $communication_id, $pdo, $_SESSION['user_id']);
        }
        
        $pdo->commit();
        
        $message = "Communication logged successfully!";
        if (count($uploaded_files) > 0) {
            $message .= " " . count($uploaded_files) . " file(s) uploaded.";
        }
        
    } catch(Exception $e) {
        $pdo->rollBack();
        $error = "Error: " . $e->getMessage();
    }
}
?>

<style>
    .attachment-area {
        border: 2px dashed #ddd;
        border-radius: 10px;
        padding: 20px;
        text-align: center;
        cursor: pointer;
        transition: all 0.3s;
        margin-top: 10px;
    }
    .attachment-area:hover {
        border-color: #3498db;
        background: #f0f8ff;
    }
    .attachment-area.dragover {
        border-color: #27ae60;
        background: #d4edda;
    }
    .file-list {
        margin-top: 15px;
        display: flex;
        flex-wrap: wrap;
        gap: 10px;
    }
    .file-item {
        background: #f8f9fa;
        padding: 5px 10px;
        border-radius: 5px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    .file-item i {
        color: #3498db;
    }
    .remove-file {
        color: #e74c3c;
        cursor: pointer;
        margin-left: 5px;
    }
    .progress-bar {
        width: 100%;
        height: 5px;
        background: #e0e0e0;
        border-radius: 5px;
        overflow: hidden;
        margin-top: 10px;
        display: none;
    }
    .progress-fill {
        width: 0%;
        height: 100%;
        background: #27ae60;
        transition: width 0.3s;
    }
</style>

<div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Add Communication for: <?php echo htmlspecialchars($lead['name']); ?></h2>
    
    <?php if($message): ?>
        <div style="background: #d4edda; color: #155724; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 15px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="commForm">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Communication Type *</label>
            <select name="type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="Phone Call">📞 Phone Call</option>
                <option value="Site Visit">🏠 Site Visit</option>
                <option value="Offer/Quotation">📄 Offer/Quotation</option>
                <option value="Email">✉️ Email</option>
                <option value="Meeting">👥 Meeting</option>
                <option value="WhatsApp">💬 WhatsApp</option>
                <option value="SMS">📱 SMS</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Notes / Details *</label>
            <textarea name="notes" rows="5" required placeholder="Enter detailed notes about this communication..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Attachments (Max 10 files, up to 10MB each)</label>
            <div class="attachment-area" id="dropZone">
                <i class="fas fa-cloud-upload-alt" style="font-size: 48px; color: #3498db;"></i>
                <p>Drag & drop files here or click to select</p>
                <small>Supported: Images, PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP (Max 10MB each)</small>
                <input type="file" name="attachments[]" id="fileInput" multiple style="display: none;" accept=".jpg,.jpeg,.png,.gif,.pdf,.doc,.docx,.xls,.xlsx,.txt,.zip">
                <div class="progress-bar" id="progressBar">
                    <div class="progress-fill" id="progressFill"></div>
                </div>
            </div>
            <div class="file-list" id="fileList"></div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" style="background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-save"></i> Save Communication
            </button>
            <a href="view_lead.php?id=<?php echo $lead_id; ?>" style="background: #95a5a6; color: white; padding: 12px 30px; text-decoration: none; border-radius: 5px;">
                <i class="fas fa-times"></i> Cancel
            </a>
        </div>
    </form>
</div>

<script>
    const dropZone = document.getElementById('dropZone');
    const fileInput = document.getElementById('fileInput');
    const fileList = document.getElementById('fileList');
    let selectedFiles = [];
    
    dropZone.addEventListener('click', () => fileInput.click());
    
    dropZone.addEventListener('dragover', (e) => {
        e.preventDefault();
        dropZone.classList.add('dragover');
    });
    
    dropZone.addEventListener('dragleave', () => {
        dropZone.classList.remove('dragover');
    });
    
    dropZone.addEventListener('drop', (e) => {
        e.preventDefault();
        dropZone.classList.remove('dragover');
        const files = Array.from(e.dataTransfer.files);
        addFiles(files);
    });
    
    fileInput.addEventListener('change', (e) => {
        const files = Array.from(e.target.files);
        addFiles(files);
    });
    
    function addFiles(files) {
        const maxFiles = 10;
        const maxSize = 10 * 1024 * 1024; // 10MB
        
        for (let file of files) {
            if (selectedFiles.length >= maxFiles) {
                alert('Maximum 10 files allowed');
                break;
            }
            
            if (file.size > maxSize) {
                alert(`File ${file.name} exceeds 10MB limit`);
                continue;
            }
            
            selectedFiles.push(file);
            displayFile(file);
        }
        
        updateFileInput();
    }
    
    function displayFile(file) {
        const fileItem = document.createElement('div');
        fileItem.className = 'file-item';
        fileItem.innerHTML = `
            <i class="fas fa-file"></i>
            <span>${file.name} (${(file.size / 1024).toFixed(1)} KB)</span>
            <i class="fas fa-times remove-file" onclick="removeFile('${file.name}')"></i>
        `;
        fileList.appendChild(fileItem);
    }
    
    function removeFile(fileName) {
        selectedFiles = selectedFiles.filter(f => f.name !== fileName);
        refreshFileList();
        updateFileInput();
    }
    
    function refreshFileList() {
        fileList.innerHTML = '';
        selectedFiles.forEach(file => displayFile(file));
    }
    
    function updateFileInput() {
        const dataTransfer = new DataTransfer();
        selectedFiles.forEach(file => dataTransfer.items.add(file));
        fileInput.files = dataTransfer.files;
    }
    
    // Progress bar simulation (optional)
    document.getElementById('commForm').addEventListener('submit', function() {
        document.getElementById('progressBar').style.display = 'block';
        let width = 0;
        const interval = setInterval(() => {
            if (width >= 100) {
                clearInterval(interval);
            } else {
                width += 10;
                document.getElementById('progressFill').style.width = width + '%';
            }
        }, 200);
    });
</script>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>