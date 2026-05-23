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

foreach($myLeads as $lead) {
    if($lead['priority'] == 'High') $highPriorityLeads++;
    if($lead['status'] == 'Converted' || $lead['lead_stage'] == 'Won') $wonLeads++;
    if($lead['next_followup_date'] && $lead['next_followup_date'] <= date('Y-m-d')) $pendingFollowups++;
}

// Get unread notifications count for this agent
$notifCount = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
$notifCount->execute([$_SESSION['user_id']]);
$unreadNotifications = $notifCount->fetchColumn();
?>

<!-- Welcome Section -->
<div style="background: linear-gradient(135deg, #667eea 0%, #764ba2 100%); color: white; padding: 30px; border-radius: 10px; margin-bottom: 30px;">
    <h2 style="margin-bottom: 10px;">Welcome back, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</h2>
    <p>Here's what's happening with your leads today.</p>
</div>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><i class="fas fa-users"></i> Total Leads</h3>
        <div class="number"><?php echo $totalLeads; ?></div>
        <small>Total leads created by you</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-exclamation-triangle"></i> High Priority</h3>
        <div class="number" style="color: #e74c3c;"><?php echo $highPriorityLeads; ?></div>
        <small>Requires immediate attention</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-trophy"></i> Won Leads</h3>
        <div class="number" style="color: #27ae60;"><?php echo $wonLeads; ?></div>
        <small>Successfully converted</small>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-bell"></i> Follow-ups Due</h3>
        <div class="number" style="color: #f39c12;"><?php echo $pendingFollowups; ?></div>
        <small>Need action today</small>
    </div>
</div>

<!-- Quick Actions -->
<div style="background: white; border-radius: 10px; padding: 20px; margin-bottom: 30px;">
    <h3 style="margin-bottom: 15px;"><i class="fas fa-bolt"></i> Quick Actions</h3>
    <div style="display: flex; gap: 15px; flex-wrap: wrap;">
        <a href="add_lead.php" class="btn" style="background: #27ae60; padding: 12px 20px;">
            <i class="fas fa-plus"></i> Add New Lead
        </a>
        <a href="view_leads.php" class="btn" style="background: #3498db; padding: 12px 20px;">
            <i class="fas fa-list"></i> View My Leads
        </a>
        <a href="notifications.php" class="btn" style="background: #e74c3c; padding: 12px 20px;">
            <i class="fas fa-bell"></i> Notifications 
            <?php if($unreadNotifications > 0): ?>
                <span style="background: white; color: #e74c3c; padding: 2px 8px; border-radius: 10px; margin-left: 5px;"><?php echo $unreadNotifications; ?></span>
            <?php endif; ?>
        </a>
    </div>
</div>

<!-- My Leads Table -->
<div style="background: white; border-radius: 10px; padding: 20px; margin-bottom: 30px;">
    <h3 style="margin-bottom: 15px;"><i class="fas fa-table"></i> My Recent Leads</h3>
    <?php if(count($myLeads) > 0): ?>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Lead ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Area</th>
                    <th>Priority</th>
                    <th>Stage</th>
                    <th>Next Follow-up</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach(array_slice($myLeads, 0, 10) as $lead): ?>
                <tr>
                    <td><?php echo $lead['lead_unique_id']; ?></td>
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo $lead['phone']; ?></td>
                    <td><?php echo htmlspecialchars($lead['area']); ?></td>
                    <td class="priority-<?php echo strtolower($lead['priority']); ?>">
                        <?php echo $lead['priority']; ?>
                    </td>
                    <td><?php echo $lead['lead_stage'] ?? 'Lead'; ?></td>
                    <td>
                        <?php 
                        if($lead['next_followup_date']) {
                            $followup_date = date('Y-m-d', strtotime($lead['next_followup_date']));
                            $today = date('Y-m-d');
                            if($followup_date == $today) {
                                echo '<span style="color: #e74c3c;">⚠️ Today</span>';
                            } else {
                                echo $followup_date;
                            }
                        } else {
                            echo 'Not scheduled';
                        }
                        ?>
                    </td>
                    <td>
                        <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-sm">View</a>
                        <a href="add_communication.php?lead_id=<?php echo $lead['id']; ?>" class="btn btn-sm btn-success">Log Call</a>
                        <a href="schedule_task.php?lead_id=<?php echo $lead['id']; ?>" class="btn btn-sm" style="background: #f39c12;">Schedule</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php if(count($myLeads) > 10): ?>
        <div style="text-align: center; margin-top: 15px;">
            <a href="view_leads.php" class="btn">View All <?php echo count($myLeads); ?> Leads →</a>
        </div>
    <?php endif; ?>
    <?php else: ?>
    <div style="text-align: center; padding: 40px;">
        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
        <p style="margin-top: 15px; color: #666;">No leads found. Start by adding your first lead!</p>
        <a href="add_lead.php" class="btn" style="margin-top: 15px; background: #27ae60;">+ Add New Lead</a>
    </div>
    <?php endif; ?>
</div>

<!-- Upcoming Tasks -->
<div style="background: white; border-radius: 10px; padding: 20px;">
    <h3 style="margin-bottom: 15px;"><i class="fas fa-calendar-check"></i> Upcoming Tasks & Follow-ups</h3>
    <?php if(count($tasks) > 0): ?>
    <div style="overflow-x: auto;">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Lead</th>
                    <th>Task</th>
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
                            echo '<span style="color: #e74c3c;">🔴 ' . $due . '</span>';
                        } elseif($due < $today) {
                            echo '<span style="color: #e74c3c;">⚠️ Overdue</span>';
                        } else {
                            echo $due;
                        }
                        ?>
                    </td>
                    <td><?php echo $task['status']; ?></td>
                    <td>
                        <a href="complete_task.php?id=<?php echo $task['id']; ?>&lead_id=<?php echo $task['lead_id']; ?>" class="btn btn-success">Complete</a>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
    <?php else: ?>
    <div style="text-align: center; padding: 30px;">
        <i class="fas fa-check-circle" style="font-size: 48px; color: #27ae60;"></i>
        <p style="margin-top: 15px; color: #666;">No pending tasks! Great job keeping up with follow-ups.</p>
    </div>
    <?php endif; ?>
</div>

<!-- Tips Section -->
<div style="margin-top: 30px; background: #e8f4f8; border-left: 4px solid #3498db; padding: 15px; border-radius: 5px;">
    <i class="fas fa-lightbulb" style="color: #3498db;"></i>
    <strong>Pro Tip:</strong> Regular follow-ups increase conversion rates by 70%. Schedule follow-ups immediately after each interaction!
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>