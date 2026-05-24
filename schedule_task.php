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

// Handle file upload function for tasks
function uploadTaskFiles($files, $task_id, $pdo, $user_id) {
    $upload_dir = "uploads/tasks/$task_id/";
    if (!file_exists($upload_dir)) {
        mkdir($upload_dir, 0777, true);
    }
    
    $uploaded_files = [];
    $allowed_types = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf', 
                      'application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                      'application/vnd.ms-excel', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                      'text/plain', 'application/zip'];
    $max_size = 10 * 1024 * 1024;
    
    foreach ($files['tmp_name'] as $key => $tmp_name) {
        if ($files['error'][$key] == 0 && !empty($tmp_name)) {
            $file_name = basename($files['name'][$key]);
            $file_size = $files['size'][$key];
            $file_type = $files['type'][$key];
            
            if (!in_array($file_type, $allowed_types) || $file_size > $max_size) {
                continue;
            }
            
            $unique_name = time() . '_' . uniqid() . '_' . preg_replace('/[^a-zA-Z0-9._-]/', '', $file_name);
            $file_path = $upload_dir . $unique_name;
            
            if (move_uploaded_file($tmp_name, $file_path)) {
                $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_name, file_path, file_size, file_type, uploaded_by) VALUES (?, ?, ?, ?, ?, ?)");
                $stmt->execute([$task_id, $file_name, $file_path, $file_size, $file_type, $user_id]);
                $uploaded_files[] = $file_name;
            }
        }
    }
    
    return $uploaded_files;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $reminder_days = $_POST['reminder_days'];
    
    try {
        $pdo->beginTransaction();
        
        $stmt = $pdo->prepare("INSERT INTO tasks (lead_id, task_title, description, due_date, reminder_days, assigned_to, created_by, has_attachments) VALUES (?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$lead_id, $title, $description, $due_date, $reminder_days, $_SESSION['user_id'], $_SESSION['user_id'], isset($_FILES['attachments']) ? true : false]);
        
        $task_id = $pdo->lastInsertId();
        
        // Handle multiple file uploads
        $uploaded_files = [];
        if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
            $uploaded_files = uploadTaskFiles($_FILES['attachments'], $task_id, $pdo, $_SESSION['user_id']);
        }
        
        $pdo->commit();
        
        // Create notification
        $notif_msg = "New task scheduled: $title for lead {$lead['name']} on $due_date";
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'task', ?)");
        $notif->execute([$_SESSION['user_id'], $lead_id, $notif_msg]);
        
        $message = "Task scheduled successfully!";
        if (count($uploaded_files) > 0) {
            $message .= " " . count($uploaded_files) . " file(s) attached.";
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
        border-color: #f39c12;
        background: #fff8e7;
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
        color: #f39c12;
    }
    .remove-file {
        color: #e74c3c;
        cursor: pointer;
        margin-left: 5px;
    }
    .form-row {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin-bottom: 20px;
    }
</style>

<div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Schedule Follow-up for: <?php echo htmlspecialchars($lead['name']); ?></h2>
    
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
    
    <form method="POST" enctype="multipart/form-data" id="taskForm">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Task Title *</label>
            <input type="text" name="title" required placeholder="e.g., Follow-up call, Site visit, Document submission" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Description</label>
            <textarea name="description" rows="4" placeholder="Detailed description of the task..." style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
        </div>
        
        <div class="form-row">
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Due Date *</label>
                <input type="date" name="due_date" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
            </div>
            
            <div>
                <label style="display: block; margin-bottom: 5px; font-weight: 600;">Reminder (days before)</label>
                <select name="reminder_days" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                    <option value="0">No reminder</option>
                    <option value="1">1 day before</option>
                    <option value="2">2 days before</option>
                    <option value="3">3 days before</option>
                    <option value="7">1 week before</option>
                </select>
            </div>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Attachments (Max 10 files, up to 10MB each)</label>
            <div class="attachment-area" id="dropZone">
                <i class="fas fa-paperclip" style="font-size: 48px; color: #f39c12;"></i>
                <p>Drag & drop files here or click to select</p>
                <small>Supported: Images, PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP (Max 10MB each)</small>
                <input type="file" name="attachments[]" id="fileInput" multiple style="display: none;">
            </div>
            <div class="file-list" id="fileList"></div>
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button type="submit" style="background: #f39c12; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer;">
                <i class="fas fa-calendar-check"></i> Schedule Task
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
        const maxSize = 10 * 1024 * 1024;
        
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
</script>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>