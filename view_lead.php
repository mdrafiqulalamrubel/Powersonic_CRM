<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$lead_id = $_GET['id'] ?? 0;
$lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$lead->execute([$lead_id]);
$lead = $lead->fetch();

if (!$lead) {
    redirect('view_leads.php');
}

// Get photos
$photos = $pdo->prepare("SELECT * FROM lead_photos WHERE lead_id = ?");
$photos->execute([$lead_id]);
$photos = $photos->fetchAll();

// Get communications
$communications = $pdo->prepare("SELECT c.*, u.full_name FROM communications c 
                                 JOIN users u ON c.created_by = u.id 
                                 WHERE c.lead_id = ? ORDER BY c.created_at DESC");
$communications->execute([$lead_id]);
$communications = $communications->fetchAll();

// Get tasks
$tasks = $pdo->prepare("SELECT * FROM tasks WHERE lead_id = ? ORDER BY due_date ASC");
$tasks->execute([$lead_id]);
$tasks = $tasks->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Lead Details - Power Sonic CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
        }
        .container {
            max-width: 1000px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .card {
            background: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        .card h3 {
            margin-bottom: 15px;
            color: #2c3e50;
            border-bottom: 2px solid #3498db;
            padding-bottom: 5px;
        }
        .lead-info {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 15px;
        }
        .info-item {
            padding: 10px;
            background: #f8f9fa;
            border-radius: 5px;
        }
        .info-label {
            font-weight: bold;
            color: #666;
            font-size: 12px;
        }
        .info-value {
            font-size: 16px;
            margin-top: 5px;
        }
        .photos {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        .photos img {
            width: 100%;
            height: auto;
            border-radius: 5px;
        }
        .timeline-item {
            border-left: 3px solid #3498db;
            padding-left: 15px;
            margin-bottom: 15px;
        }
        .timeline-date {
            color: #666;
            font-size: 12px;
        }
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin: 5px;
        }
        .btn-success {
            background: #27ae60;
        }
        .nav-links {
            text-align: center;
            margin-top: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Lead Details: <?php echo htmlspecialchars($lead['name']); ?></h2>
    </div>
    
    <div class="container">
        <div class="card">
            <h3>Lead Information</h3>
            <div class="lead-info">
                <div class="info-item">
                    <div class="info-label">Lead ID</div>
                    <div class="info-value"><?php echo $lead['lead_unique_id']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Priority</div>
                    <div class="info-value"><?php echo $lead['priority']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Status</div>
                    <div class="info-value"><?php echo $lead['status']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Phone</div>
                    <div class="info-value"><?php echo $lead['phone']; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Email</div>
                    <div class="info-value"><?php echo $lead['email'] ?: 'N/A'; ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Area</div>
                    <div class="info-value"><?php echo htmlspecialchars($lead['area']); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Address</div>
                    <div class="info-value"><?php echo nl2br(htmlspecialchars($lead['address'])); ?></div>
                </div>
                <div class="info-item">
                    <div class="info-label">Created</div>
                    <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($lead['created_at'])); ?></div>
                </div>
            </div>
        </div>
        
        <?php if(count($photos) > 0): ?>
        <div class="card">
            <h3>Site Photos</h3>
            <div class="photos">
                <?php foreach($photos as $photo): ?>
                <img src="<?php echo $photo['photo_path']; ?>" alt="Site Photo">
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>
        
        <div class="card">
            <h3>Communication Timeline</h3>
            <?php foreach($communications as $comm): ?>
            <div class="timeline-item">
                <div class="timeline-date"><?php echo date('Y-m-d H:i', strtotime($comm['created_at'])); ?> - <?php echo $comm['communication_type']; ?> by <?php echo $comm['full_name']; ?></div>
                <div><?php echo nl2br(htmlspecialchars($comm['notes'])); ?></div>
            </div>
            <?php endforeach; ?>
            
            <?php if(isFieldAgent() || isAdmin()): ?>
            <a href="add_communication.php?lead_id=<?php echo $lead_id; ?>" class="btn">+ Add Communication</a>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h3>Scheduled Tasks & Follow-ups</h3>
            <?php foreach($tasks as $task): ?>
            <div class="timeline-item">
                <div class="timeline-date">Due: <?php echo date('Y-m-d', strtotime($task['due_date'])); ?> - Status: <?php echo $task['status']; ?></div>
                <div><strong><?php echo htmlspecialchars($task['task_title']); ?></strong></div>
                <div><?php echo nl2br(htmlspecialchars($task['description'])); ?></div>
            </div>
            <?php endforeach; ?>
            
            <?php if(isFieldAgent() || isAdmin()): ?>
            <a href="schedule_task.php?lead_id=<?php echo $lead_id; ?>" class="btn btn-success">+ Schedule Follow-up</a>
            <?php endif; ?>
        </div>
        
        <div class="nav-links">
            <a href="view_leads.php">Back to Leads</a>
            <?php if(isAdmin()): ?>
            | <a href="edit_lead.php?id=<?php echo $lead_id; ?>">Edit Lead</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>