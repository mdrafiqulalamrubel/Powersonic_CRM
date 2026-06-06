<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

// Get statistics
$total_leads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$total_users = $pdo->query("SELECT COUNT(*) FROM users WHERE role = 'field_agent'")->fetchColumn();
$total_converted = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_stage = 'Won'")->fetchColumn();
$total_amount = $pdo->query("SELECT COALESCE(SUM(expected_amount), 0) FROM leads WHERE lead_stage = 'Won'")->fetchColumn();

// Get leads by stage
$stage_data = $pdo->query("
    SELECT COALESCE(lead_stage, 'New Lead') as stage, COUNT(*) as count 
    FROM leads 
    GROUP BY lead_stage 
    ORDER BY count DESC
")->fetchAll();

// Get agent performance - FIXED
$agent_performance = $pdo->query("
    SELECT 
        u.id,
        u.full_name,
        u.username,
        u.profile_image,
        COUNT(l.id) as total_leads,
        SUM(CASE WHEN l.lead_stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
        SUM(CASE WHEN l.lead_stage = 'Won' THEN l.expected_amount ELSE 0 END) as total_amount,
        ROUND(
            CASE WHEN COUNT(l.id) > 0 
            THEN (SUM(CASE WHEN l.lead_stage = 'Won' THEN 1 ELSE 0 END) * 100.0 / COUNT(l.id))
            ELSE 0 END, 2
        ) as conversion_rate,
        MAX(l.created_at) as last_activity
    FROM users u
    LEFT JOIN leads l ON u.id = l.created_by
    WHERE u.role = 'field_agent'
    GROUP BY u.id, u.full_name, u.username, u.profile_image
    ORDER BY total_leads DESC
")->fetchAll();

// Get monthly performance
$monthly_performance = $pdo->query("
    SELECT 
        DATE_FORMAT(created_at, '%Y-%m') as month,
        COUNT(*) as total_leads,
        SUM(CASE WHEN lead_stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
        COALESCE(SUM(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE 0 END), 0) as total_amount
    FROM leads
    WHERE created_at >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
    GROUP BY DATE_FORMAT(created_at, '%Y-%m')
    ORDER BY month DESC
")->fetchAll();

// Get recent activities
function getRecentActivities($pdo, $user_id = null, $limit = 20) {
    $all_activities = [];
    
    // Get leads
    if ($user_id) {
        $stmt = $pdo->prepare("
            SELECT 
                'lead_created' as activity_type,
                l.id as lead_id,
                l.name as lead_name,
                l.created_at as activity_date,
                u.full_name as user_name
            FROM leads l
            LEFT JOIN users u ON l.created_by = u.id
            WHERE l.created_by = ?
            ORDER BY l.created_at DESC
            LIMIT " . intval($limit)
        );
        $stmt->execute([$user_id]);
        $leads = $stmt->fetchAll();
    } else {
        $stmt = $pdo->prepare("
            SELECT 
                'lead_created' as activity_type,
                l.id as lead_id,
                l.name as lead_name,
                l.created_at as activity_date,
                u.full_name as user_name
            FROM leads l
            LEFT JOIN users u ON l.created_by = u.id
            ORDER BY l.created_at DESC
            LIMIT " . intval($limit)
        );
        $stmt->execute();
        $leads = $stmt->fetchAll();
    }
    
    // Get communications
    if ($user_id) {
        $stmt2 = $pdo->prepare("
            SELECT 
                'communication' as activity_type,
                c.lead_id as lead_id,
                l.name as lead_name,
                c.created_at as activity_date,
                u.full_name as user_name
            FROM communications c
            LEFT JOIN leads l ON c.lead_id = l.id
            LEFT JOIN users u ON c.created_by = u.id
            WHERE c.created_by = ?
            ORDER BY c.created_at DESC
            LIMIT " . intval($limit)
        );
        $stmt2->execute([$user_id]);
        $communications = $stmt2->fetchAll();
    } else {
        $stmt2 = $pdo->prepare("
            SELECT 
                'communication' as activity_type,
                c.lead_id as lead_id,
                l.name as lead_name,
                c.created_at as activity_date,
                u.full_name as user_name
            FROM communications c
            LEFT JOIN leads l ON c.lead_id = l.id
            LEFT JOIN users u ON c.created_by = u.id
            ORDER BY c.created_at DESC
            LIMIT " . intval($limit)
        );
        $stmt2->execute();
        $communications = $stmt2->fetchAll();
    }
    
    // Get completed tasks
    if ($user_id) {
        $stmt3 = $pdo->prepare("
            SELECT 
                'task_completed' as activity_type,
                t.lead_id as lead_id,
                l.name as lead_name,
                t.created_at as activity_date,
                u.full_name as user_name
            FROM tasks t
            LEFT JOIN leads l ON t.lead_id = l.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status = 'Completed' AND t.assigned_to = ?
            ORDER BY t.created_at DESC
            LIMIT " . intval($limit)
        );
        $stmt3->execute([$user_id]);
        $tasks = $stmt3->fetchAll();
    } else {
        $stmt3 = $pdo->prepare("
            SELECT 
                'task_completed' as activity_type,
                t.lead_id as lead_id,
                l.name as lead_name,
                t.created_at as activity_date,
                u.full_name as user_name
            FROM tasks t
            LEFT JOIN leads l ON t.lead_id = l.id
            LEFT JOIN users u ON t.assigned_to = u.id
            WHERE t.status = 'Completed'
            ORDER BY t.created_at DESC
            LIMIT " . intval($limit)
        );
        $stmt3->execute();
        $tasks = $stmt3->fetchAll();
    }
    
    // Merge all activities
    $all_activities = array_merge($leads, $communications, $tasks);
    
    // Sort by date
    usort($all_activities, function($a, $b) {
        return strtotime($b['activity_date']) - strtotime($a['activity_date']);
    });
    
    // Limit results
    return array_slice($all_activities, 0, $limit);
}

// Get recent activities
$recent_activities = getRecentActivities($pdo, null, 20);

// Get filter parameters
$selected_agent = $_GET['agent_id'] ?? '';
$date_from = $_GET['date_from'] ?? '';
$date_to = $_GET['date_to'] ?? '';

// Build filtered leads query
$leads_query = "SELECT l.*, u.full_name as agent_name 
                FROM leads l 
                LEFT JOIN users u ON l.created_by = u.id 
                WHERE 1=1";
$params = [];

if ($selected_agent) {
    $leads_query .= " AND l.created_by = ?";
    $params[] = $selected_agent;
}
if ($date_from) {
    $leads_query .= " AND DATE(l.created_at) >= ?";
    $params[] = $date_from;
}
if ($date_to) {
    $leads_query .= " AND DATE(l.created_at) <= ?";
    $params[] = $date_to;
}

$leads_query .= " ORDER BY l.created_at DESC LIMIT 50";
$stmt = $pdo->prepare($leads_query);
$stmt->execute($params);
$filtered_leads = $stmt->fetchAll();

// Get agents list for filter
$agents = $pdo->query("SELECT id, full_name, profile_image FROM users WHERE role = 'field_agent' ORDER BY full_name")->fetchAll();
?>

<style>
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
        text-align: center;
    }
    .stat-card h3 {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
    }
    .stat-number {
        font-size: 28px;
        font-weight: bold;
        color: #2c3e50;
    }
    .stat-label {
        font-size: 11px;
        color: #27ae60;
        margin-top: 5px;
    }
    .section-card {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    .section-title {
        font-size: 18px;
        margin-bottom: 15px;
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
    }
    .section-title i {
        color: #3498db;
        margin-right: 8px;
    }
    .data-table {
        width: 100%;
        border-collapse: collapse;
        font-size: 13px;
    }
    .data-table th {
        background: #34495e;
        color: white;
        padding: 10px 8px;
        text-align: left;
    }
    .data-table td {
        padding: 10px 8px;
        border-bottom: 1px solid #eee;
    }
    .data-table tr:hover {
        background: #f8f9fa;
    }
    .filter-section {
        background: white;
        padding: 20px;
        border-radius: 12px;
        margin-bottom: 25px;
    }
    .filter-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    .filter-group label {
        font-size: 12px;
        font-weight: 600;
        margin-bottom: 5px;
        color: #555;
    }
    .filter-group select, .filter-group input {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 6px;
        min-width: 160px;
    }
    .btn-filter {
        background: #3498db;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 6px;
        cursor: pointer;
    }
    .btn-reset {
        background: #95a5a6;
        color: white;
        text-decoration: none;
        padding: 8px 20px;
        border-radius: 6px;
    }
    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
        border-radius: 4px;
        text-decoration: none;
        display: inline-block;
    }
    .btn-primary { background: #3498db; color: white; }
    .activity-timeline {
        max-height: 400px;
        overflow-y: auto;
    }
    .activity-item {
        padding: 12px;
        border-bottom: 1px solid #eee;
        display: flex;
        align-items: center;
        gap: 12px;
    }
    .activity-icon {
        width: 36px;
        height: 36px;
        border-radius: 50%;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 16px;
    }
    .activity-icon.lead_created { background: #d4edda; color: #27ae60; }
    .activity-icon.communication { background: #d1ecf1; color: #17a2b8; }
    .activity-icon.task_completed { background: #fff3cd; color: #f39c12; }
    .activity-content { flex: 1; }
    .activity-title { font-weight: 600; margin-bottom: 3px; font-size: 13px; }
    .activity-date { font-size: 10px; color: #999; }
    .conversion-high { color: #27ae60; font-weight: bold; }
    .conversion-medium { color: #f39c12; font-weight: bold; }
    .conversion-low { color: #e74c3c; font-weight: bold; }
    .priority-High { color: #e74c3c; font-weight: bold; }
    .priority-Medium { color: #f39c12; font-weight: bold; }
    .priority-Low { color: #27ae60; font-weight: bold; }
    .badge {
        padding: 3px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
        display: inline-block;
    }
    .badge-won { background: #27ae60; color: white; }
    .badge-lost { background: #e74c3c; color: white; }
    .badge-pending { background: #f39c12; color: white; }
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    .mb-20 { margin-bottom: 20px; }
    .overflow-auto { overflow-x: auto; }
</style>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><i class="fas fa-users"></i> Total Leads</h3>
        <div class="stat-number"><?php echo $total_leads; ?></div>
        <div class="stat-label">All time</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-user-check"></i> Field Agents</h3>
        <div class="stat-number"><?php echo $total_users; ?></div>
        <div class="stat-label">Active agents</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-trophy"></i> Won Deals</h3>
        <div class="stat-number"><?php echo $total_converted; ?></div>
        <div class="stat-label">Successfully converted</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-chart-line"></i> Conversion Rate</h3>
        <div class="stat-number"><?php echo $total_leads > 0 ? round(($total_converted / $total_leads) * 100, 1) : 0; ?>%</div>
        <div class="stat-label">Overall conversion</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-money-bill"></i> Total Revenue</h3>
        <div class="stat-number">৳<?php echo number_format($total_amount, 0); ?></div>
        <div class="stat-label">From won deals</div>
    </div>
</div>

<!-- Filter Section -->
<div class="filter-section">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label><i class="fas fa-user"></i> Select Agent</label>
            <select name="agent_id">
                <option value="">All Agents</option>
                <?php foreach($agents as $agent): ?>
                <option value="<?php echo $agent['id']; ?>" <?php echo $selected_agent == $agent['id'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($agent['full_name']); ?>
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Date From</label>
            <input type="date" name="date_from" value="<?php echo $date_from; ?>">
        </div>
        <div class="filter-group">
            <label><i class="fas fa-calendar"></i> Date To</label>
            <input type="date" name="date_to" value="<?php echo $date_to; ?>">
        </div>
        <div class="filter-group">
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filter</button>
        </div>
        <div class="filter-group">
            <a href="agent_reports.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Agent Performance Table -->
<div class="section-card">
    <h3 class="section-title"><i class="fas fa-chart-line"></i> Agent Performance</h3>
    <div class="overflow-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Agent Name</th>
                    <th class="text-center">Total Leads</th>
                    <th class="text-center">Won Deals</th>
                    <th class="text-center">Loss Deals</th>
                    <th class="text-center">Conversion Rate</th>
                    <th class="text-right">Total Amount</th>
                    <th>Last Activity</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($agent_performance) > 0): ?>
                    <?php foreach($agent_performance as $agent): 
                        $lost_leads = $agent['total_leads'] - $agent['won_leads'];
                    ?>
                    <tr>
                        <td>
                            <strong><?php echo htmlspecialchars($agent['full_name']); ?></strong><br>
                            <small style="color: #666;">@<?php echo htmlspecialchars($agent['username']); ?></small>
                        </td>
                        <td class="text-center"><?php echo $agent['total_leads']; ?></td>
                        <td class="text-center"><span class="badge badge-won"><?php echo $agent['won_leads']; ?></span></td>
                        <td class="text-center"><span class="badge badge-lost"><?php echo $lost_leads; ?></span></td>
                        <td class="text-center <?php 
                            if($agent['conversion_rate'] >= 30) echo 'conversion-high';
                            elseif($agent['conversion_rate'] >= 15) echo 'conversion-medium';
                            else echo 'conversion-low';
                        ?>">
                            <?php echo $agent['conversion_rate']; ?>%
                        </td>
                        <td class="text-right">৳<?php echo number_format($agent['total_amount'], 0); ?></td>
                        <td><?php echo $agent['last_activity'] ? date('Y-m-d', strtotime($agent['last_activity'])) : '<span style="color: #999;">No activity</span>'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">No agents found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Monthly Performance -->
<div class="section-card">
    <h3 class="section-title"><i class="fas fa-calendar-alt"></i> Monthly Performance (Last 6 Months)</h3>
    <div class="overflow-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th class="text-center">Total Leads</th>
                    <th class="text-center">Won Deals</th>
                    <th class="text-right">Total Amount</th>
                    <th class="text-center">Conversion Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($monthly_performance) > 0): ?>
                    <?php foreach($monthly_performance as $month): 
                        $month_conv_rate = $month['total_leads'] > 0 ? round(($month['won_leads'] / $month['total_leads']) * 100, 1) : 0;
                    ?>
                    <tr>
                        <td><strong><?php echo date('F Y', strtotime($month['month'] . '-01')); ?></strong></td>
                        <td class="text-center"><?php echo $month['total_leads']; ?></td>
                        <td class="text-center"><span class="badge badge-won"><?php echo $month['won_leads']; ?></span></td>
                        <td class="text-right">৳<?php echo number_format($month['total_amount'], 0); ?></td>
                        <td class="text-center <?php 
                            if($month_conv_rate >= 30) echo 'conversion-high';
                            elseif($month_conv_rate >= 15) echo 'conversion-medium';
                            else echo 'conversion-low';
                        ?>"><?php echo $month_conv_rate; ?>%</td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="5" style="text-align: center; padding: 40px;">No data available</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Leads List Table -->
<div class="section-card">
    <h3 class="section-title"><i class="fas fa-list"></i> Leads List (Last 50)</h3>
    <div class="overflow-auto">
        <table class="data-table">
            <thead>
                <tr>
                    <th>Customer ID</th>
                    <th>Lead ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Agent</th>
                    <th>Stage</th>
                    <th>Priority</th>
                    <th class="text-right">Amount</th>
                    <th>Created</th>
                    <th>Action</th>
                </tr>
            </thead>
            <tbody>
                <?php if(count($filtered_leads) > 0): ?>
                    <?php foreach($filtered_leads as $lead): ?>
                    <tr>
                        <td><code><?php echo $lead['customer_id'] ?? 'N/A'; ?></code></td>
                        <td><small><?php echo $lead['lead_unique_id']; ?></small></td>
                        <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                        <td><?php echo $lead['phone']; ?></td>
                        <td><?php echo htmlspecialchars($lead['agent_name'] ?? 'Unassigned'); ?></td>
                        <td><?php echo $lead['lead_stage'] ?? 'New Lead'; ?></td>
                        <td class="priority-<?php echo $lead['priority'] ?? 'Medium'; ?>"><?php echo $lead['priority'] ?? 'Medium'; ?></td>
                        <td class="text-right">৳<?php echo number_format($lead['expected_amount'] ?? 0, 0); ?></td>
                        <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                        <td><a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn-sm btn-primary">View</a></td>
                    </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="10" style="text-align: center; padding: 40px;">No leads found</td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
    </div>
</div>

<!-- Recent Activities -->
<div class="section-card">
    <h3 class="section-title"><i class="fas fa-history"></i> Recent Activities</h3>
    <div class="activity-timeline">
        <?php if(count($recent_activities) > 0): ?>
            <?php foreach($recent_activities as $activity): ?>
            <div class="activity-item">
                <div class="activity-icon <?php echo $activity['activity_type']; ?>">
                    <?php if($activity['activity_type'] == 'lead_created'): ?>
                        <i class="fas fa-user-plus"></i>
                    <?php elseif($activity['activity_type'] == 'communication'): ?>
                        <i class="fas fa-comment"></i>
                    <?php else: ?>
                        <i class="fas fa-check-circle"></i>
                    <?php endif; ?>
                </div>
                <div class="activity-content">
                    <div class="activity-title">
                        <?php if($activity['activity_type'] == 'lead_created'): ?>
                            New lead created: <strong><?php echo htmlspecialchars($activity['lead_name']); ?></strong>
                        <?php elseif($activity['activity_type'] == 'communication'): ?>
                            Communication added for: <strong><?php echo htmlspecialchars($activity['lead_name']); ?></strong>
                        <?php else: ?>
                            Task completed for: <strong><?php echo htmlspecialchars($activity['lead_name']); ?></strong>
                        <?php endif; ?>
                        <span style="font-size: 11px; color: #666;"> by <?php echo htmlspecialchars($activity['user_name']); ?></span>
                    </div>
                    <div class="activity-date">
                        <?php echo date('Y-m-d H:i', strtotime($activity['activity_date'])); ?>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div style="text-align: center; padding: 40px; color: #999;">
                <i class="fas fa-inbox" style="font-size: 48px;"></i>
                <p>No recent activities</p>
            </div>
        <?php endif; ?>
    </div>
</div>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>