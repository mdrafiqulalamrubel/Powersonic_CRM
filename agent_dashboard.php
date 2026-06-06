<?php
ob_start();
require_once 'config.php';

// Check if user is logged in and is a field agent
if (!isLoggedIn()) {
    redirect('index.php');
}

if (!isFieldAgent()) {
    // If admin tries to access, redirect to admin dashboard
    if (isAdmin()) {
        redirect('dashboard.php');
    } else {
        redirect('index.php');
    }
}

require_once 'includes/header.php';

// Fetch leads created by this agent
$myLeads = $pdo->prepare("SELECT * FROM leads WHERE created_by = ? ORDER BY created_at DESC");
$myLeads->execute([$_SESSION['user_id']]);
$myLeads = $myLeads->fetchAll();

// Fetch upcoming tasks for this agent
$tasks = $pdo->prepare("SELECT t.*, l.name as lead_name, l.lead_unique_id, l.id as lead_id 
                        FROM tasks t 
                        JOIN leads l ON t.lead_id = l.id 
                        WHERE t.assigned_to = ? AND t.status = 'Pending' 
                        ORDER BY t.due_date ASC LIMIT 10");
$tasks->execute([$_SESSION['user_id']]);
$tasks = $tasks->fetchAll();

// Get statistics for this agent
$totalLeads = count($myLeads);
$highPriorityLeads = 0;
$wonLeads = 0;
$pendingFollowups = 0;
$totalPipelineValue = 0;

foreach($myLeads as $lead) {
    if($lead['priority'] == 'High') $highPriorityLeads++;
    if($lead['lead_stage'] == 'Won' || $lead['status'] == 'Converted') $wonLeads++;
    if($lead['next_followup_date'] && $lead['next_followup_date'] <= date('Y-m-d')) $pendingFollowups++;
    if($lead['lead_stage'] != 'Won' && $lead['lead_stage'] != 'Lost' && $lead['lead_stage'] != 'Cancelled') {
        $totalPipelineValue += ($lead['expected_amount'] ?? 0);
    }
}

// Get unread notifications count for this agent
$notifCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifCount->execute([$_SESSION['user_id']]);
$unreadNotifications = $notifCount->fetchColumn();

// Get agent profile image
$agentImage = $pdo->prepare("SELECT profile_image FROM users WHERE id = ?");
$agentImage->execute([$_SESSION['user_id']]);
$profile_image = $agentImage->fetchColumn();

// Get agent code
$agentCode = $pdo->prepare("SELECT agent_code FROM users WHERE id = ?");
$agentCode->execute([$_SESSION['user_id']]);
$agent_code = $agentCode->fetchColumn();
?>

<style>
    .welcome-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 30px;
        border-radius: 15px;
        margin-bottom: 30px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    .agent-avatar {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        border: 3px solid white;
        object-fit: cover;
    }
    .agent-avatar-placeholder {
        width: 80px;
        height: 80px;
        border-radius: 50%;
        background: rgba(255,255,255,0.2);
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 32px;
        border: 3px solid white;
    }
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    .stat-card {
        background: white;
        padding: 20px;
        border-radius: 12px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.08);
        transition: transform 0.3s;
        text-align: center;
    }
    .stat-card:hover {
        transform: translateY(-5px);
    }
    .stat-card h3 {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
    }
    .stat-card .number {
        font-size: 28px;
        font-weight: bold;
        color: #2c3e50;
    }
    .stat-card small {
        font-size: 11px;
        color: #999;
    }
    .quick-actions {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 8px;
        padding: 10px 20px;
        border-radius: 8px;
        text-decoration: none;
        font-size: 13px;
        transition: all 0.3s;
    }
    .btn:hover {
        transform: translateY(-2px);
    }
    .btn-success { background: #27ae60; color: white; }
    .btn-primary { background: #3498db; color: white; }
    .btn-danger { background: #e74c3c; color: white; }
    .btn-warning { background: #f39c12; color: white; }
    .btn-sm { padding: 5px 10px; font-size: 11px; }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .data-table th {
        background: #34495e;
        color: white;
        padding: 12px 10px;
        text-align: left;
        white-space: nowrap;
    }
    .data-table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
        vertical-align: middle;
    }
    .data-table tr:hover {
        background: #f8f9fa;
    }
    .priority-high { color: #e74c3c; font-weight: bold; }
    .priority-medium { color: #f39c12; font-weight: bold; }
    .priority-low { color: #27ae60; font-weight: bold; }
    .tip-box {
        background: #e8f4f8;
        border-left: 4px solid #3498db;
        padding: 15px;
        border-radius: 8px;
        margin-top: 30px;
    }
    .section-title {
        margin-bottom: 15px;
        font-size: 18px;
        color: #2c3e50;
    }
    .section-title i {
        margin-right: 8px;
        color: #3498db;
    }
    .badge-pending {
        background: #f39c12;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 10px;
    }
    .badge-won {
        background: #27ae60;
        color: white;
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 10px;
    }
    .table-container {
        overflow-x: auto;
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 30px;
    }
    @media (max-width: 768px) {
        .welcome-card {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<!-- Welcome Section with Agent Avatar -->
<div class="welcome-card">
    <div>
        <h2 style="margin-bottom: 10px;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
        <p>Here's what's happening with your leads today.</p>
        <div style="margin-top: 10px;">
            <span style="background: rgba(255,255,255,0.2); padding: 5px 12px; border-radius: 20px; font-size: 12px;">
                <i class="fas fa-id-card"></i> Agent Code: <?php echo $agent_code ?? 'Not assigned'; ?>
            </span>
        </div>
    </div>
    <div>
        <?php if(!empty($profile_image) && file_exists($profile_image)): ?>
            <img src="<?php echo $profile_image; ?>" class="agent-avatar" alt="Profile">
        <?php else: ?>
            <div class="agent-avatar-placeholder">
                <i class="fas fa-user-circle"></i>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><i class="fas fa-users"></i> Total Leads</h3>
        <div class="number"><?php echo $totalLeads; ?></div>
        <small>Created by you</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-exclamation-triangle"></i> High Priority</h3>
        <div class="number" style="color: #e74c3c;"><?php echo $highPriorityLeads; ?></div>
        <small>Need attention</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-trophy"></i> Won Leads</h3>
        <div class="number" style="color: #27ae60;"><?php echo $wonLeads; ?></div>
        <small>Successfully converted</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-chart-line"></i> Pipeline Value</h3>
        <div class="number" style="color: #3498db;">৳<?php echo number_format($totalPipelineValue, 0); ?></div>
        <small>Potential deals</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-bell"></i> Follow-ups Due</h3>
        <div class="number" style="color: #f39c12;"><?php echo $pendingFollowups; ?></div>
        <small>Need action today</small>
    </div>
</div>

<!-- Quick Actions -->
<div class="quick-actions">
    <h3 class="section-title"><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="add_lead.php" class="btn btn-success">
            <i class="fas fa-plus"></i> Add New Lead
        </a>
        <a href="view_leads.php" class="btn btn-primary">
            <i class="fas fa-list"></i> View My Leads
        </a>
        <a href="notifications.php" class="btn btn-danger">
            <i class="fas fa-bell"></i> Notifications 
            <?php if($unreadNotifications > 0): ?>
                <span style="background: white; color: #e74c3c; padding: 2px 8px; border-radius: 10px;"><?php echo $unreadNotifications; ?></span>
            <?php endif; ?>
        </a>
        <a href="profile.php" class="btn btn-warning">
            <i class="fas fa-user-edit"></i> My Profile
        </a>
    </div>
</div>

<!-- My Leads Table -->
<div class="table-container">
    <h3 class="section-title"><i class="fas fa-table"></i> My Recent Leads</h3>
    <?php if(count($myLeads) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Customer ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>District</th>
                <th>Area</th>
                <th>Priority</th>
                <th>Stage</th>
                <th>Amount (BDT)</th>
                <th>Next Follow-up</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach(array_slice($myLeads, 0, 10) as $lead): ?>
            <tr>
                <td><code><?php echo $lead['customer_id'] ?? 'N/A'; ?></code></td>
                <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                <td><?php echo $lead['phone']; ?></td>
                <td><?php echo htmlspecialchars($lead['district'] ?? 'N/A'); ?></td>
                <td><?php echo htmlspecialchars($lead['area'] ?? 'N/A'); ?></td>
                <td class="priority-<?php echo strtolower($lead['priority']); ?>">
                    <?php echo $lead['priority']; ?>
                </td>
                <td>
                    <?php 
                    $stage = $lead['lead_stage'] ?? 'Lead';
                    $stageClass = '';
                    if($stage == 'Won') $stageClass = 'badge-won';
                    if($stage == 'Lost') $stageClass = 'badge-pending';
                    ?>
                    <span class="<?php echo $stageClass; ?>" style="background: #e8e8e8; color: #666; padding: 3px 8px; border-radius: 12px; font-size: 11px;">
                        <?php echo $stage; ?>
                    </span>
                </td>
                <td>৳<?php echo number_format($lead['expected_amount'] ?? 0, 0); ?></td>
                <td>
                    <?php 
                    if($lead['next_followup_date']) {
                        $followup_date = date('Y-m-d', strtotime($lead['next_followup_date']));
                        $today = date('Y-m-d');
                        if($followup_date == $today) {
                            echo '<span style="color: #e74c3c;"><i class="fas fa-bell"></i> Today</span>';
                        } elseif($followup_date < $today) {
                            echo '<span style="color: #e74c3c;">Overdue</span>';
                        } else {
                            echo $followup_date;
                        }
                    } else {
                        echo '<span style="color: #999;">Not scheduled</span>';
                    }
                    ?>
                </td>
                <td>
                    <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                        <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-primary btn-sm" title="View">
                            <i class="fas fa-eye"></i>
                        </a>
                        <a href="add_communication.php?lead_id=<?php echo $lead['id']; ?>" class="btn btn-success btn-sm" title="Add Communication">
                            <i class="fas fa-comment"></i>
                        </a>
                        <a href="schedule_task.php?lead_id=<?php echo $lead['id']; ?>" class="btn btn-warning btn-sm" title="Schedule Follow-up">
                            <i class="fas fa-calendar"></i>
                        </a>
                    </div>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php if(count($myLeads) > 10): ?>
        <div style="text-align: center; margin-top: 15px;">
            <a href="view_leads.php" class="btn btn-primary">View All <?php echo count($myLeads); ?> Leads →</a>
        </div>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align: center; padding: 50px;">
        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
        <p style="margin-top: 15px; color: #666;">No leads found. Start by adding your first lead!</p>
        <a href="add_lead.php" class="btn btn-success" style="margin-top: 15px;">+ Add New Lead</a>
    </div>
    <?php endif; ?>
</div>

<!-- Upcoming Tasks -->
<div class="table-container">
    <h3 class="section-title"><i class="fas fa-calendar-check"></i> Upcoming Tasks & Follow-ups</h3>
    <?php if(count($tasks) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Lead</th>
                <th>Task Title</th>
                <th>Due Date</th>
                <th>Status</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($tasks as $task): ?>
            <tr>
                <td>
                    <strong><?php echo htmlspecialchars($task['lead_name']); ?></strong><br>
                    <small><?php echo $task['lead_unique_id']; ?></small>
                </td>
                <td><?php echo htmlspecialchars($task['task_title']); ?></td>
                <td>
                    <?php 
                    $due = date('Y-m-d', strtotime($task['due_date']));
                    $today = date('Y-m-d');
                    if($due == $today) {
                        echo '<span style="color: #e74c3c;"><i class="fas fa-bell"></i> Today</span>';
                    } elseif($due < $today) {
                        echo '<span style="color: #e74c3c;"><i class="fas fa-exclamation-triangle"></i> Overdue</span>';
                    } else {
                        echo '<i class="fas fa-calendar"></i> ' . $due;
                    }
                    ?>
                </td>
                <td><?php echo $task['status']; ?></td>
                <td>
                    <a href="complete_task.php?id=<?php echo $task['id']; ?>&lead_id=<?php echo $task['lead_id']; ?>" class="btn btn-success btn-sm" onclick="return confirm('Mark this task as completed?')">
                        <i class="fas fa-check"></i> Complete
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
    <?php else: ?>
    <div style="text-align: center; padding: 40px;">
        <i class="fas fa-check-circle" style="font-size: 48px; color: #27ae60;"></i>
        <p style="margin-top: 15px; color: #666;">No pending tasks! Great job keeping up with follow-ups.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Tips Section -->
<div class="tip-box">
    <i class="fas fa-lightbulb" style="color: #3498db; font-size: 18px;"></i>
    <strong>Pro Tip:</strong> Regular follow-ups increase conversion rates by 70%. Schedule follow-ups immediately after each interaction!
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>