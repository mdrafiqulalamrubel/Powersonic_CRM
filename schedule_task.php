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

// Handle file upload function for tasks (FIXED - removed uploaded_by column)
function uploadTaskFiles($files, $task_id, $pdo) {
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
                // FIXED: Removed uploaded_by column
                $stmt = $pdo->prepare("INSERT INTO task_attachments (task_id, file_name, file_path, file_size, file_type) VALUES (?, ?, ?, ?, ?)");
                $stmt->execute([$task_id, $file_name, $file_path, $file_size, $file_type]);
                $uploaded_files[] = $file_name;
            }
        }
    }
    
    return $uploaded_files;
}

// Get existing tasks for this lead
$existing_tasks = $pdo->prepare("SELECT * FROM tasks WHERE lead_id = ? ORDER BY due_date ASC");
$existing_tasks->execute([$lead_id]);
$existing_tasks = $existing_tasks->fetchAll();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $reminder_days = $_POST['reminder_days'] ?? 1;
    $priority = $_POST['priority'] ?? 'Medium';
    
    if (empty($title)) {
        $error = "Task title is required";
    } elseif (empty($due_date)) {
        $error = "Due date is required";
    } else {
        try {
            $pdo->beginTransaction();
            
            // FIXED: Removed has_attachments, created_by, assigned_to, reminder_days columns
            $stmt = $pdo->prepare("INSERT INTO tasks (lead_id, task_title, description, due_date, priority, reminder_days, assigned_to, assigned_by, status, created_at) 
                                   VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([
                $lead_id, 
                $title, 
                $description, 
                $due_date, 
                $priority,
                $reminder_days,
                $_SESSION['user_id'],  // assigned_to
                $_SESSION['user_id']   // assigned_by
            ]);
            
            $task_id = $pdo->lastInsertId();
            
            // Handle multiple file uploads
            $uploaded_files = [];
            if (isset($_FILES['attachments']) && !empty($_FILES['attachments']['name'][0])) {
                $uploaded_files = uploadTaskFiles($_FILES['attachments'], $task_id, $pdo);
            }
            
            $pdo->commit();
            
            // Create notification
            $notif_msg = "New task scheduled: $title for lead {$lead['name']} due on $due_date";
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message, created_at) VALUES (?, ?, 'task', ?, NOW())");
            $notif->execute([$_SESSION['user_id'], $lead_id, $notif_msg]);
            
            $message = "Task scheduled successfully!";
            if (count($uploaded_files) > 0) {
                $message .= " " . count($uploaded_files) . " file(s) attached.";
            }
            
            // Clear form
            $_POST = [];
            
            // Refresh existing tasks
            $existing_tasks = $pdo->prepare("SELECT * FROM tasks WHERE lead_id = ? ORDER BY due_date ASC");
            $existing_tasks->execute([$lead_id]);
            $existing_tasks = $existing_tasks->fetchAll();
            
        } catch(Exception $e) {
            $pdo->rollBack();
            $error = "Error: " . $e->getMessage();
        }
    }
}

// Handle task completion
if (isset($_GET['complete']) && isset($_GET['task_id'])) {
    $task_id = $_GET['task_id'];
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'Completed', completed_at = NOW() WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    redirect("schedule_task.php?lead_id=$lead_id");
}

// Handle task deletion
if (isset($_GET['delete']) && isset($_GET['task_id'])) {
    $task_id = $_GET['task_id'];
    $stmt = $pdo->prepare("DELETE FROM tasks WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    redirect("schedule_task.php?lead_id=$lead_id");
}
?>

<style>
    .form-container {
        max-width: 800px;
        margin: 0 auto;
        background: white;
        border-radius: 10px;
        padding: 30px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
    }
    
    .form-group {
        margin-bottom: 20px;
    }
    
    label {
        display: block;
        margin-bottom: 8px;
        font-weight: 600;
        color: #333;
    }
    
    input[type="text"], 
    input[type="date"],
    select, 
    textarea {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    textarea {
        resize: vertical;
        min-height: 100px;
    }
    
    button {
        background: #f39c12;
        color: white;
        padding: 12px 30px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
        font-size: 16px;
    }
    
    button:hover {
        background: #e67e22;
    }
    
    .message {
        padding: 15px;
        border-radius: 5px;
        margin-bottom: 20px;
    }
    
    .success {
        background: #d4edda;
        color: #155724;
        border: 1px solid #c3e6cb;
    }
    
    .error {
        background: #f8d7da;
        color: #721c24;
        border: 1px solid #f5c6cb;
    }
    
    .lead-info {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
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
    
    .tasks-list {
        margin-top: 30px;
    }
    
    .task-item {
        background: #f8f9fa;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        padding: 15px;
        margin-bottom: 15px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        transition: all 0.3s;
    }
    
    .task-item:hover {
        box-shadow: 0 2px 8px rgba(0,0,0,0.1);
    }
    
    .task-item.completed {
        opacity: 0.7;
        background: #d4edda;
    }
    
    .task-info {
        flex: 1;
    }
    
    .task-title {
        font-weight: 600;
        font-size: 16px;
        margin-bottom: 5px;
    }
    
    .task-details {
        font-size: 12px;
        color: #666;
        margin-top: 5px;
    }
    
    .task-details i {
        margin-right: 5px;
    }
    
    .priority-High { color: #e74c3c; font-weight: bold; }
    .priority-Medium { color: #f39c12; font-weight: bold; }
    .priority-Low { color: #27ae60; font-weight: bold; }
    
    .task-actions {
        display: flex;
        gap: 10px;
    }
    
    .btn-sm {
        padding: 5px 10px;
        font-size: 12px;
        text-decoration: none;
        border-radius: 5px;
        display: inline-block;
    }
    
    .btn-complete {
        background: #27ae60;
        color: white;
    }
    
    .btn-delete {
        background: #e74c3c;
        color: white;
    }
    
    .badge {
        display: inline-block;
        padding: 2px 8px;
        border-radius: 12px;
        font-size: 11px;
        margin-left: 10px;
    }
    
    .badge-pending {
        background: #f39c12;
        color: white;
    }
    
    .badge-completed {
        background: #27ae60;
        color: white;
    }
    
    .btn-cancel {
        background: #95a5a6;
        color: white;
        padding: 12px 30px;
        text-decoration: none;
        border-radius: 5px;
        display: inline-block;
        margin-left: 10px;
    }
    
    .btn-cancel:hover {
        background: #7f8c8d;
    }
    
    hr {
        margin: 20px 0;
        border: none;
        border-top: 1px solid #ddd;
    }
</style>

<div class="form-container">
    <h2><i class="fas fa-calendar-plus"></i> Schedule Task / Follow-up</h2>
    
    <div class="lead-info">
        <strong>Lead:</strong> <?php echo htmlspecialchars($lead['name']); ?> 
        (<?php echo $lead['phone']; ?>)
    </div>
    
    <?php if($message): ?>
        <div class="message success"><?php echo $message; ?></div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="message error"><?php echo $error; ?></div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data" id="taskForm">
        <div class="form-group">
            <label>Task Title *</label>
            <input type="text" name="title" required placeholder="e.g., Follow-up call, Site visit, Document submission" value="<?php echo htmlspecialchars($_POST['title'] ?? ''); ?>">
        </div>
        
        <div class="form-group">
            <label>Description</label>
            <textarea name="description" placeholder="Detailed description of the task..."><?php echo htmlspecialchars($_POST['description'] ?? ''); ?></textarea>
        </div>
        
        <div class="form-row">
            <div class="form-group">
                <label>Due Date *</label>
                <input type="date" name="due_date" required min="<?php echo date('Y-m-d'); ?>" value="<?php echo $_POST['due_date'] ?? date('Y-m-d', strtotime('+1 day')); ?>">
            </div>
            
            <div class="form-group">
                <label>Priority</label>
                <select name="priority">
                    <option value="High" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'High') ? 'selected' : ''; ?>>🔴 High</option>
                    <option value="Medium" <?php echo (!isset($_POST['priority']) || $_POST['priority'] == 'Medium') ? 'selected' : ''; ?>>🟡 Medium</option>
                    <option value="Low" <?php echo (isset($_POST['priority']) && $_POST['priority'] == 'Low') ? 'selected' : ''; ?>>🟢 Low</option>
                </select>
            </div>
        </div>
        
        <div class="form-group">
            <label>Reminder (days before due date)</label>
            <select name="reminder_days">
                <option value="0">No reminder</option>
                <option value="1" selected>1 day before</option>
                <option value="2">2 days before</option>
                <option value="3">3 days before</option>
                <option value="7">1 week before</option>
            </select>
        </div>
        
        <div class="form-group">
            <label>Attachments (Max 10 files, up to 10MB each)</label>
            <div class="attachment-area" id="dropZone">
                <i class="fas fa-paperclip" style="font-size: 48px; color: #f39c12;"></i>
                <p>Drag & drop files here or click to select</p>
                <small>Supported: Images, PDF, DOC, DOCX, XLS, XLSX, TXT, ZIP (Max 10MB each)</small>
                <input type="file" name="attachments[]" id="fileInput" multiple style="display: none;">
            </div>
            <div class="file-list" id="fileList"></div>
        </div>
        
        <div>
            <button type="submit"><i class="fas fa-calendar-check"></i> Schedule Task</button>
            <a href="view_lead.php?id=<?php echo $lead_id; ?>" class="btn-cancel"><i class="fas fa-times"></i> Cancel</a>
        </div>
    </form>
    
    <?php if(count($existing_tasks) > 0): ?>
    <hr>
    
    <div class="tasks-list">
        <h3><i class="fas fa-list"></i> Existing Tasks</h3>
        
        <?php foreach($existing_tasks as $task): ?>
        <div class="task-item <?php echo $task['status'] == 'Completed' ? 'completed' : ''; ?>">
            <div class="task-info">
                <div class="task-title">
                    <?php echo htmlspecialchars($task['task_title']); ?>
                    <span class="badge <?php echo $task['status'] == 'Completed' ? 'badge-completed' : 'badge-pending'; ?>">
                        <?php echo $task['status']; ?>
                    </span>
                </div>
                <div class="task-details">
                    <i class="fas fa-calendar"></i> Due: <?php echo date('Y-m-d', strtotime($task['due_date'])); ?>
                    <span class="priority-<?php echo $task['priority']; ?>">
                        <i class="fas fa-flag"></i> <?php echo $task['priority']; ?>
                    </span>
                    <?php if($task['reminder_days'] > 0): ?>
                    <span><i class="fas fa-bell"></i> Reminder: <?php echo $task['reminder_days']; ?> day(s) before</span>
                    <?php endif; ?>
                </div>
                <?php if($task['description']): ?>
                <div class="task-details">
                    <i class="fas fa-align-left"></i> <?php echo nl2br(htmlspecialchars(substr($task['description'], 0, 100))); ?>
                </div>
                <?php endif; ?>
            </div>
            <?php if($task['status'] != 'Completed'): ?>
            <div class="task-actions">
                <a href="?lead_id=<?php echo $lead_id; ?>&complete=1&task_id=<?php echo $task['id']; ?>" class="btn-sm btn-complete" onclick="return confirm('Mark this task as completed?')">
                    <i class="fas fa-check"></i> Complete
                </a>
                <a href="?lead_id=<?php echo $lead_id; ?>&delete=1&task_id=<?php echo $task['id']; ?>" class="btn-sm btn-delete" onclick="return confirm('Delete this task?')">
                    <i class="fas fa-trash"></i> Delete
                </a>
            </div>
            <?php else: ?>
            <div class="task-actions">
                <span style="color: #27ae60;"><i class="fas fa-check-circle"></i> Completed</span>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
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
            <i class="fas fa-times remove-file" onclick="removeFile('${file.name.replace(/'/g, "\\'")}')"></i>
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