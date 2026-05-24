<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$lead_id = $_GET['id'] ?? 0;

// Get lead with all details including creator name
$stmt = $pdo->prepare("SELECT l.*, u.full_name as agent_name 
                       FROM leads l 
                       LEFT JOIN users u ON l.created_by = u.id 
                       WHERE l.id = ?");
$stmt->execute([$lead_id]);
$lead = $stmt->fetch();

if (!$lead) {
    redirect('view_leads.php');
}

// Get photos
$photos = $pdo->prepare("SELECT * FROM lead_photos WHERE lead_id = ?");
$photos->execute([$lead_id]);
$photos = $photos->fetchAll();

// Get communications with attachments
$communications = $pdo->prepare("
    SELECT c.*, u.full_name 
    FROM communications c 
    JOIN users u ON c.created_by = u.id 
    WHERE c.lead_id = ? 
    ORDER BY c.created_at DESC
");
$communications->execute([$lead_id]);
$communications = $communications->fetchAll();

// Get attachments for each communication
foreach ($communications as &$comm) {
    $attachments = $pdo->prepare("SELECT * FROM communication_attachments WHERE communication_id = ?");
    $attachments->execute([$comm['id']]);
    $comm['attachments'] = $attachments->fetchAll();
}

// Get tasks with attachments
$tasks = $pdo->prepare("SELECT * FROM tasks WHERE lead_id = ? ORDER BY due_date ASC");
$tasks->execute([$lead_id]);
$tasks = $tasks->fetchAll();

// Get attachments for each task
foreach ($tasks as &$task) {
    $attachments = $pdo->prepare("SELECT * FROM task_attachments WHERE task_id = ?");
    $attachments->execute([$task['id']]);
    $task['attachments'] = $attachments->fetchAll();
}
?>

<style>
    .detail-card {
        background: white;
        border-radius: 12px;
        padding: 25px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
    }
    
    .detail-header {
        border-bottom: 2px solid #3498db;
        padding-bottom: 12px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .detail-header h3 {
        color: #2c3e50;
        margin: 0;
        font-size: 18px;
    }
    
    .detail-header h3 i {
        color: #3498db;
        margin-right: 8px;
    }
    
    .action-buttons {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
    }
    
    .info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 15px;
    }
    
    .info-item {
        padding: 12px;
        background: #f8f9fa;
        border-radius: 8px;
        transition: all 0.3s;
    }
    
    .info-item:hover {
        background: #e8f4f8;
        transform: translateX(5px);
    }
    
    .info-label {
        font-size: 11px;
        color: #666;
        text-transform: uppercase;
        letter-spacing: 0.5px;
        margin-bottom: 5px;
        display: flex;
        align-items: center;
        gap: 5px;
    }
    
    .info-label i {
        width: 14px;
        color: #3498db;
    }
    
    .info-value {
        font-size: 15px;
        font-weight: 500;
        color: #2c3e50;
        word-break: break-word;
    }
    
    .priority-high { color: #e74c3c; font-weight: bold; }
    .priority-medium { color: #f39c12; font-weight: bold; }
    .priority-low { color: #27ae60; font-weight: bold; }
    
    .stage-badge {
        display: inline-block;
        padding: 5px 12px;
        border-radius: 20px;
        font-size: 12px;
        font-weight: bold;
    }
    
    .stage-Lead { background: #e8e8e8; color: #666; }
    .stage-Pipeline { background: #d4edda; color: #155724; }
    .stage-Qualified { background: #d1ecf1; color: #0c5460; }
    .stage-Discussion { background: #fff3cd; color: #856404; }
    .stage-Quotation { background: #cce5ff; color: #004085; }
    .stage-Final { background: #f8d7da; color: #721c24; }
    .stage-Won { background: #27ae60; color: white; }
    .stage-Lost { background: #e74c3c; color: white; }
    
    .photo-gallery {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 15px;
    }
    
    .photo-item {
        position: relative;
        overflow: hidden;
        border-radius: 8px;
        cursor: pointer;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .photo-item:hover {
        transform: scale(1.02);
    }
    
    .photo-item img {
        width: 100%;
        height: 180px;
        object-fit: cover;
        display: block;
    }
    
    .timeline-item {
        border-left: 3px solid #3498db;
        padding-left: 18px;
        margin-bottom: 25px;
        padding-bottom: 15px;
        border-bottom: 1px solid #eee;
        position: relative;
    }
    
    .timeline-item:last-child {
        border-bottom: none;
    }
    
    .timeline-date {
        font-size: 11px;
        color: #666;
        margin-bottom: 8px;
        display: flex;
        align-items: center;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .timeline-type {
        display: inline-block;
        padding: 2px 10px;
        border-radius: 15px;
        font-size: 10px;
        font-weight: bold;
        background: #e8f4f8;
        color: #3498db;
    }
    
    .timeline-content {
        font-size: 14px;
        line-height: 1.5;
    }
    
    .attachment-list {
        margin-top: 10px;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    
    .attachment-link {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 4px 10px;
        background: #f0f0f0;
        border-radius: 5px;
        text-decoration: none;
        font-size: 12px;
        color: #3498db;
        transition: all 0.3s;
    }
    
    .attachment-link:hover {
        background: #3498db;
        color: white;
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 15px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-size: 13px;
        transition: all 0.3s;
        border: none;
        cursor: pointer;
    }
    
    .btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .btn-success { background: #27ae60; }
    .btn-danger { background: #e74c3c; }
    .btn-warning { background: #f39c12; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
    
    .nav-links {
        text-align: center;
        margin-top: 20px;
        padding: 20px;
        background: white;
        border-radius: 10px;
    }
    
    @media (max-width: 768px) {
        .info-grid {
            grid-template-columns: 1fr;
        }
        .detail-header {
            flex-direction: column;
            gap: 10px;
            align-items: flex-start;
        }
        .photo-gallery {
            grid-template-columns: 1fr;
        }
    }
</style>

<div class="detail-card">
    <div class="detail-header">
        <h3><i class="fas fa-user-circle"></i> Lead Information</h3>
        <div class="action-buttons">
            <a href="edit_lead.php?id=<?php echo $lead_id; ?>" class="btn btn-success btn-sm">
                <i class="fas fa-edit"></i> Edit Lead
            </a>
            <a href="add_communication.php?lead_id=<?php echo $lead_id; ?>" class="btn btn-sm">
                <i class="fas fa-comment"></i> Add Communication
            </a>
            <a href="schedule_task.php?lead_id=<?php echo $lead_id; ?>" class="btn btn-warning btn-sm">
                <i class="fas fa-calendar"></i> Schedule Follow-up
            </a>
            <a href="export_leads.php?format=csv&lead_id=<?php echo $lead_id; ?>" class="btn btn-sm" style="background: #27ae60;">
                <i class="fas fa-download"></i> Export
            </a>
        </div>
    </div>
    
    <div class="info-grid">
        <div class="info-item">
            <div class="info-label"><i class="fas fa-id-card"></i> User Custom ID</div>
            <div class="info-value"><code><?php echo $lead['user_custom_id'] ?? 'N/A'; ?></code></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-barcode"></i> Lead ID</div>
            <div class="info-value"><code><?php echo $lead['lead_unique_id']; ?></code></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-user"></i> Full Name</div>
            <div class="info-value"><strong><?php echo htmlspecialchars($lead['name']); ?></strong></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-phone"></i> Phone Number</div>
            <div class="info-value"><?php echo $lead['phone']; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-envelope"></i> Email Address</div>
            <div class="info-value"><?php echo $lead['email'] ?: '<span style="color: #999;">Not provided</span>'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-globe"></i> Country</div>
            <div class="info-value"><?php echo $lead['country'] ?? 'Bangladesh'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-map-marker-alt"></i> District</div>
            <div class="info-value"><?php echo $lead['district'] ?? 'N/A'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-building"></i> Police Station</div>
            <div class="info-value"><?php echo $lead['police_station'] ?? 'N/A'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-mail-bulk"></i> Post Office</div>
            <div class="info-value"><?php echo $lead['post_office'] ?? 'N/A'; ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-location-dot"></i> Area</div>
            <div class="info-value"><?php echo htmlspecialchars($lead['area'] ?? 'N/A'); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-address-card"></i> Full Address</div>
            <div class="info-value"><?php echo nl2br(htmlspecialchars($lead['address'] ?? 'N/A')); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-flag-checkered"></i> Priority</div>
            <div class="info-value">
                <span class="priority-<?php echo strtolower($lead['priority']); ?>">
                    <?php echo $lead['priority']; ?>
                </span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-chart-line"></i> Lead Stage</div>
            <div class="info-value">
                <span class="stage-badge stage-<?php echo str_replace(' ', '', $lead['lead_stage'] ?? 'Lead'); ?>">
                    <?php echo $lead['lead_stage'] ?? 'Lead'; ?>
                </span>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-percent"></i> Win Probability</div>
            <div class="info-value"><?php echo ($lead['probability'] ?? 0); ?>%</div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-money-bill-wave"></i> Expected Amount</div>
            <div class="info-value"><strong><?php echo number_format($lead['expected_amount'] ?? 0, 2); ?></strong> BDT</div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-calendar-alt"></i> Next Follow-up</div>
            <div class="info-value">
                <?php 
                if($lead['next_followup_date']) {
                    $followup = date('Y-m-d', strtotime($lead['next_followup_date']));
                    $today = date('Y-m-d');
                    if($followup == $today) {
                        echo '<span style="color: #e74c3c;">🔴 Today!</span>';
                    } elseif($followup < $today) {
                        echo '<span style="color: #e74c3c;">⚠️ Overdue: ' . $followup . '</span>';
                    } else {
                        echo $followup;
                    }
                } else {
                    echo 'Not scheduled';
                }
                ?>
            </div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-calendar-plus"></i> Created Date</div>
            <div class="info-value"><?php echo date('Y-m-d H:i', strtotime($lead['created_at'])); ?></div>
        </div>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-user-check"></i> Assigned Agent</div>
            <div class="info-value"><?php echo $lead['agent_name'] ?? 'Unknown'; ?></div>
        </div>
        <?php if($lead['google_map_link']): ?>
        <div class="info-item">
            <div class="info-label"><i class="fas fa-map"></i> Map Location</div>
            <div class="info-value">
                <a href="<?php echo $lead['google_map_link']; ?>" target="_blank" class="location-link">
                    <i class="fas fa-external-link-alt"></i> View on Google Maps
                </a>
            </div>
        </div>
        <?php endif; ?>
    </div>
</div>

<!-- Site Photos -->
<?php if(count($photos) > 0): ?>
<div class="detail-card">
    <div class="detail-header">
        <h3><i class="fas fa-images"></i> Site Photos (<?php echo count($photos); ?> photos)</h3>
    </div>
    <div class="photo-gallery">
        <?php foreach($photos as $index => $photo): ?>
        <div class="photo-item" onclick="window.open('<?php echo $photo['photo_path']; ?>')">
            <img src="<?php echo $photo['photo_path']; ?>" alt="Site Photo">
        </div>
        <?php endforeach; ?>
    </div>
</div>
<?php endif; ?>

<!-- Communication Timeline -->
<div class="detail-card">
    <div class="detail-header">
        <h3><i class="fas fa-comments"></i> Communication Timeline</h3>
        <a href="add_communication.php?lead_id=<?php echo $lead_id; ?>" class="btn btn-sm">
            <i class="fas fa-plus"></i> Add Communication
        </a>
    </div>
    
    <?php if(count($communications) > 0): ?>
        <?php foreach($communications as $comm): ?>
        <div class="timeline-item">
            <div class="timeline-date">
                <i class="fas fa-calendar"></i> <?php echo date('Y-m-d H:i', strtotime($comm['created_at'])); ?>
                <span class="timeline-type"><?php echo $comm['communication_type']; ?></span>
                <span><i class="fas fa-user"></i> by <?php echo $comm['full_name']; ?></span>
            </div>
            <div class="timeline-content">
                <?php echo nl2br(htmlspecialchars($comm['notes'])); ?>
            </div>
            <?php if(!empty($comm['attachments'])): ?>
            <div class="attachment-list">
                <?php foreach($comm['attachments'] as $attachment): ?>
                <a href="<?php echo $attachment['file_path']; ?>" class="attachment-link" target="_blank" download>
                    <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($attachment['file_name']); ?>
                    <small>(<?php echo round($attachment['file_size'] / 1024, 1); ?> KB)</small>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 30px;">
            <i class="fas fa-comment-slash" style="font-size: 48px;"></i><br>
            No communications recorded yet.
        </p>
    <?php endif; ?>
</div>

<!-- Scheduled Tasks -->
<div class="detail-card">
    <div class="detail-header">
        <h3><i class="fas fa-tasks"></i> Scheduled Tasks & Follow-ups</h3>
        <a href="schedule_task.php?lead_id=<?php echo $lead_id; ?>" class="btn btn-warning btn-sm">
            <i class="fas fa-plus"></i> Schedule Task
        </a>
    </div>
    
    <?php if(count($tasks) > 0): ?>
        <?php foreach($tasks as $task): ?>
        <div class="timeline-item">
            <div class="timeline-date">
                <i class="fas fa-calendar-check"></i> Due: <?php echo date('Y-m-d', strtotime($task['due_date'])); ?>
                <span class="timeline-type">
                    <?php echo $task['status'] == 'Pending' ? '<i class="fas fa-clock"></i> Pending' : '<i class="fas fa-check-circle"></i> Completed'; ?>
                </span>
            </div>
            <div class="timeline-content">
                <strong><?php echo htmlspecialchars($task['task_title']); ?></strong><br>
                <?php echo nl2br(htmlspecialchars($task['description'])); ?>
            </div>
            <?php if(!empty($task['attachments'])): ?>
            <div class="attachment-list">
                <?php foreach($task['attachments'] as $attachment): ?>
                <a href="<?php echo $attachment['file_path']; ?>" class="attachment-link" target="_blank" download>
                    <i class="fas fa-paperclip"></i> <?php echo htmlspecialchars($attachment['file_name']); ?>
                    <small>(<?php echo round($attachment['file_size'] / 1024, 1); ?> KB)</small>
                </a>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>
            <?php if($task['status'] == 'Pending'): ?>
            <div style="margin-top: 10px;">
                <a href="complete_task.php?id=<?php echo $task['id']; ?>&lead_id=<?php echo $lead_id; ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this task as completed?')">
                    <i class="fas fa-check"></i> Mark Complete
                </a>
            </div>
            <?php endif; ?>
        </div>
        <?php endforeach; ?>
    <?php else: ?>
        <p style="text-align: center; color: #999; padding: 30px;">
            <i class="fas fa-calendar" style="font-size: 48px;"></i><br>
            No scheduled tasks.
        </p>
    <?php endif; ?>
</div>

<div class="nav-links">
    <a href="view_leads.php" class="btn">
        <i class="fas fa-arrow-left"></i> Back to Leads List
    </a>
    <?php if(isAdmin()): ?>
    <a href="dashboard.php" class="btn">
        <i class="fas fa-tachometer-alt"></i> Dashboard
    </a>
    <?php else: ?>
    <a href="agent_dashboard.php" class="btn">
        <i class="fas fa-tachometer-alt"></i> My Dashboard
    </a>
    <?php endif; ?>
</div>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>