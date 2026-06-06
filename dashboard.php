<?php
// Start output buffering to prevent header issues
ob_start();

require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Include header AFTER all PHP logic
require_once 'includes/header.php';

// Get company prefixes
$prefixes = getCompanyPrefixes($pdo);

// Handle search/filter
$search_agent = $_GET['search_agent'] ?? '';
$search_priority = $_GET['search_priority'] ?? '';
$search_stage = $_GET['search_stage'] ?? '';

// Fetch statistics
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$highPriority = $pdo->query("SELECT COUNT(*) FROM leads WHERE priority = 'High'")->fetchColumn();
$convertedThisMonth = $pdo->query("SELECT COUNT(*) FROM leads WHERE lead_stage = 'Won' AND MONTH(created_at) = MONTH(CURRENT_DATE())")->fetchColumn();
$totalPipelineValue = $pdo->query("SELECT COALESCE(SUM(expected_amount), 0) FROM leads WHERE lead_stage NOT IN ('Won', 'Lost', 'Cancelled')")->fetchColumn();

// Fetch leads grouped by area and priority
$areaWiseLeads = $pdo->query("SELECT area, priority, COUNT(*) as count FROM leads GROUP BY area, priority ORDER BY area, FIELD(priority, 'High', 'Medium', 'Low')")->fetchAll();

// Build query for recent leads with filters
$leads_query = "SELECT l.*, u.agent_code, u.full_name as agent_name, u.username as agent_username 
                FROM leads l 
                LEFT JOIN users u ON l.created_by = u.id 
                WHERE 1=1";
$params = [];

if ($search_agent) {
    $leads_query .= " AND (u.full_name LIKE ? OR u.agent_code LIKE ? OR u.username LIKE ?)";
    $params[] = "%$search_agent%";
    $params[] = "%$search_agent%";
    $params[] = "%$search_agent%";
}
if ($search_priority) {
    $leads_query .= " AND l.priority = ?";
    $params[] = $search_priority;
}
if ($search_stage) {
    $leads_query .= " AND l.lead_stage = ?";
    $params[] = $search_stage;
}

$leads_query .= " ORDER BY l.created_at DESC LIMIT 20";
$stmt = $pdo->prepare($leads_query);
$stmt->execute($params);
$recentLeads = $stmt->fetchAll();

// Get all agents for filter dropdown
$agents = $pdo->query("SELECT id, full_name, agent_code FROM users WHERE role = 'field_agent' ORDER BY full_name")->fetchAll();

// Get all lead stages for filter dropdown
$lead_stages = $pdo->query("SELECT DISTINCT lead_stage FROM leads WHERE lead_stage IS NOT NULL AND lead_stage != ''")->fetchAll();
?>

<style>
    * {
        box-sizing: border-box;
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
        text-align: center;
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
    }
    
    .stat-card h3 {
        font-size: 13px;
        color: #666;
        margin-bottom: 8px;
    }
    
    .stat-card .number {
        font-size: 32px;
        font-weight: bold;
        color: #2c3e50;
    }
    
    .stat-card .small {
        font-size: 11px;
        color: #27ae60;
        margin-top: 5px;
    }
    
    .filter-bar {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
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
        min-width: 160px;
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
        font-size: 13px;
        background: white;
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
        display: inline-block;
    }
    
    .area-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        margin-bottom: 25px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
    }
    
    .area-title {
        background: #ecf0f1;
        padding: 10px 15px;
        font-weight: bold;
        margin-bottom: 10px;
        border-radius: 6px;
    }
    
    .priority-item {
        padding: 6px 15px;
        margin: 3px 0;
        margin-left: 20px;
    }
    
    .recent-leads-section {
        background: white;
        border-radius: 12px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.05);
        overflow-x: auto;
    }
    
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
        font-size: 12px;
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
    
    .btn {
        display: inline-block;
        padding: 5px 10px;
        font-size: 11px;
        border-radius: 4px;
        text-decoration: none;
        margin: 2px;
    }
    
    .btn-primary { background: #3498db; color: white; }
    .btn-success { background: #27ae60; color: white; }
    
    .agent-badge {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        background: #e8f4f8;
        padding: 4px 10px;
        border-radius: 15px;
        font-size: 12px;
    }
    
    .agent-badge i {
        color: #3498db;
    }
    
    .customer-id {
        font-family: monospace;
        font-size: 11px;
        background: #e8f4f8;
        padding: 3px 6px;
        border-radius: 4px;
        display: inline-block;
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
    
    .stage-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .stage-Lead, .stage-lead { background: #e8e8e8; color: #666; }
    .stage-Pipeline, .stage-pipeline { background: #d4edda; color: #155724; }
    .stage-Qualified, .stage-qualified { background: #d1ecf1; color: #0c5460; }
    .stage-Discussion, .stage-discussion { background: #fff3cd; color: #856404; }
    .stage-Quotation, .stage-quotation { background: #cce5ff; color: #004085; }
    .stage-Final, .stage-final { background: #f8d7da; color: #721c24; }
    .stage-Won, .stage-won { background: #27ae60; color: white; }
    .stage-Lost, .stage-lost { background: #e74c3c; color: white; }
    
    .text-center { text-align: center; }
    .text-right { text-align: right; }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        color: #999;
    }
    
    .empty-state i {
        font-size: 48px;
        margin-bottom: 15px;
    }
</style>

<!-- Statistics Cards -->
<div class="stats-grid">
    <div class="stat-card">
        <h3><i class="fas fa-users"></i> Total Leads</h3>
        <div class="number"><?php echo $totalLeads; ?></div>
        <div class="small">All time</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-exclamation-triangle"></i> High Priority</h3>
        <div class="number" style="color: #e74c3c;"><?php echo $highPriority; ?></div>
        <div class="small">Requires attention</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-trophy"></i> Won This Month</h3>
        <div class="number" style="color: #27ae60;"><?php echo $convertedThisMonth; ?></div>
        <div class="small">Successfully won</div>
    </div>
    <div class="stat-card">
        <h3><i class="fas fa-chart-line"></i> Pipeline Value</h3>
        <div class="number">৳<?php echo number_format($totalPipelineValue, 0); ?></div>
        <div class="small">Potential deals</div>
    </div>
</div>

<!-- Filter Bar -->
<div class="filter-bar">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label><i class="fas fa-user-tie"></i> Search by Agent</label>
            <select name="search_agent">
                <option value="">All Agents</option>
                <?php foreach($agents as $agent): ?>
                <option value="<?php echo htmlspecialchars($agent['full_name']); ?>" <?php echo $search_agent == $agent['full_name'] ? 'selected' : ''; ?>>
                    <?php echo htmlspecialchars($agent['full_name']); ?> (<?php echo $agent['agent_code']; ?>)
                </option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-flag"></i> Priority</label>
            <select name="search_priority">
                <option value="">All Priorities</option>
                <option value="High" <?php echo $search_priority == 'High' ? 'selected' : ''; ?>>High</option>
                <option value="Medium" <?php echo $search_priority == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                <option value="Low" <?php echo $search_priority == 'Low' ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        <div class="filter-group">
            <label><i class="fas fa-chart-line"></i> Lead Stage</label>
            <select name="search_stage">
                <option value="">All Stages</option>
                <?php foreach($lead_stages as $stage): ?>
                    <?php if(!empty($stage['lead_stage'])): ?>
                    <option value="<?php echo htmlspecialchars($stage['lead_stage']); ?>" <?php echo $search_stage == $stage['lead_stage'] ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($stage['lead_stage']); ?>
                    </option>
                    <?php endif; ?>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="filter-group">
            <button type="submit" class="btn-filter"><i class="fas fa-search"></i> Apply Filters</button>
        </div>
        <div class="filter-group">
            <a href="dashboard.php" class="btn-reset"><i class="fas fa-sync-alt"></i> Reset</a>
        </div>
    </form>
</div>

<!-- Leads by Area & Priority -->
<div class="area-section">
    <h3 class="section-title"><i class="fas fa-chart-pie"></i> Leads by Area & Priority</h3>
    <?php
    $currentArea = '';
    foreach($areaWiseLeads as $row):
        if($currentArea != $row['area']):
            if($currentArea != ''): echo '</div>'; endif;
            $currentArea = $row['area'];
            echo '<div class="area-title"><i class="fas fa-map-marker-alt"></i> ' . htmlspecialchars($row['area']) . '</div>';
        endif;
        $priorityClass = 'priority-' . strtolower($row['priority']);
        echo '<div class="priority-item">';
        echo '<span class="' . $priorityClass . '"><i class="fas fa-circle"></i> ' . $row['priority'] . '</span>: ';
        echo $row['count'] . ' leads';
        echo '</div>';
    endforeach;
    if($currentArea != ''): echo '</div>'; endif;
    ?>
</div>

<!-- Recent Leads Table -->
<div class="recent-leads-section">
    <h3 class="section-title"><i class="fas fa-table"></i> Recent Leads</h3>
    
    <?php if(count($recentLeads) > 0): ?>
    <table class="data-table">
        <thead>
            <tr>
                <th>Customer ID</th>
                <th>Lead ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Area/District</th>
                <th>Agent Name</th>
                <th>Agent Code</th>
                <th>Priority</th>
                <th>Stage</th>
                <th>Amount (BDT)</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($recentLeads as $lead): ?>
            <tr>
                <td><span class="customer-id"><?php echo $lead['customer_id'] ?? 'N/A'; ?></span></td>
                <td><code><?php echo $lead['lead_unique_id']; ?></code></td>
                <td><strong><?php echo htmlspecialchars($lead['name']); ?></strong></td>
                <td><?php echo $lead['phone']; ?></td>
                <td>
                    <?php echo htmlspecialchars($lead['area'] ?? 'N/A'); ?>
                    <?php if(!empty($lead['district'])): ?>
                        <br><small><?php echo htmlspecialchars($lead['district']); ?></small>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if(!empty($lead['agent_name'])): ?>
                        <div class="agent-badge">
                            <i class="fas fa-user-circle"></i>
                            <?php echo htmlspecialchars($lead['agent_name']); ?>
                        </div>
                    <?php else: ?>
                        <span style="color: #999;">Unassigned</span>
                    <?php endif; ?>
                </td>
                <td>
                    <?php if($lead['agent_code']): ?>
                        <code><?php echo $lead['agent_code']; ?></code>
                    <?php else: ?>
                        <span style="color: #999;">-</span>
                    <?php endif; ?>
                </td>
                <td class="priority-<?php echo strtolower($lead['priority']); ?>">
                    <i class="fas fa-flag"></i> <?php echo $lead['priority']; ?>
                </td>
                <td>
                    <span class="stage-badge stage-<?php echo str_replace(' ', '', $lead['lead_stage'] ?? 'Lead'); ?>">
                        <?php echo $lead['lead_stage'] ?? 'Lead'; ?>
                    </span>
                </td>
                <td class="text-right">৳<?php echo number_format($lead['expected_amount'] ?? 0, 0); ?></td>
                <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                <td>
                    <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-primary" title="View Details">
                        <i class="fas fa-eye"></i> View
                    </a>
                    <a href="edit_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-success" title="Edit Lead">
                        <i class="fas fa-edit"></i> Edit
                    </a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
     </table>
    <?php else: ?>
    <div class="empty-state">
        <i class="fas fa-inbox"></i>
        <p>No leads found matching your criteria.</p>
        <a href="add_lead.php" class="btn" style="background: #27ae60; color: white; padding: 10px 20px; display: inline-block; margin-top: 10px;">
            <i class="fas fa-plus"></i> Add New Lead
        </a>
    </div>
    <?php endif; ?>
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>