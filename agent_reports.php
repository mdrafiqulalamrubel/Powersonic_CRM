<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

// Get all agents for dropdown
$agents_list = $pdo->query("SELECT id, full_name, username, email, phone, join_date, status FROM users WHERE role = 'field_agent' OR role = 'supervisor' ORDER BY full_name")->fetchAll();

// Get selected agent
$selected_agent_id = $_GET['agent_id'] ?? 0;
$selected_agent = null;

if ($selected_agent_id) {
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ? AND (role = 'field_agent' OR role = 'supervisor')");
    $stmt->execute([$selected_agent_id]);
    $selected_agent = $stmt->fetch();
}

// Get date filter
$month = $_GET['month'] ?? date('Y-m');
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Get year for yearly report
$year = $_GET['year'] ?? date('Y');
$report_type = $_GET['report_type'] ?? 'monthly'; // monthly, yearly, custom

// Custom date range
$custom_start = $_GET['custom_start'] ?? date('Y-m-01');
$custom_end = $_GET['custom_end'] ?? date('Y-m-t');

// Adjust date range based on report type
if ($report_type == 'yearly') {
    $start_date = $year . '-01-01';
    $end_date = $year . '-12-31';
} elseif ($report_type == 'custom') {
    $start_date = $custom_start;
    $end_date = $custom_end;
}

// Function to get agent performance data
function getAgentPerformance($pdo, $agent_id, $start_date, $end_date) {
    // Get lead statistics
    $lead_stats = $pdo->prepare("
        SELECT 
            COUNT(*) as total_leads,
            SUM(CASE WHEN lead_stage NOT IN ('Won', 'Lost', 'Cancelled') THEN 1 ELSE 0 END) as ongoing_leads,
            SUM(CASE WHEN lead_stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
            SUM(CASE WHEN lead_stage IN ('Lost', 'Cancelled') THEN 1 ELSE 0 END) as lost_leads,
            COALESCE(SUM(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE 0 END), 0) as total_revenue,
            COALESCE(AVG(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE NULL END), 0) as avg_deal_size,
            SUM(expected_amount) as pipeline_value
        FROM leads 
        WHERE created_by = ? 
        AND DATE(created_at) BETWEEN ? AND ?
    ");
    $lead_stats->execute([$agent_id, $start_date, $end_date]);
    return $lead_stats->fetch();
}

// Function to get stage-wise distribution
function getStageDistribution($pdo, $agent_id, $start_date, $end_date) {
    $stmt = $pdo->prepare("
        SELECT lead_stage, COUNT(*) as count, SUM(expected_amount) as total_value
        FROM leads
        WHERE created_by = ? AND DATE(created_at) BETWEEN ? AND ?
        GROUP BY lead_stage
        ORDER BY FIELD(lead_stage, 'Lead', 'Pipeline', 'Qualified', 'Discussion Ongoing', 'Quotation Submitted', 'Final Negotiation', 'Won', 'Lost', 'Cancelled')
    ");
    $stmt->execute([$agent_id, $start_date, $end_date]);
    return $stmt->fetchAll();
}

// Function to get monthly performance trend
function getMonthlyTrend($pdo, $agent_id, $year) {
    $stmt = $pdo->prepare("
        SELECT 
            DATE_FORMAT(created_at, '%Y-%m') as month,
            COUNT(*) as total_leads,
            SUM(CASE WHEN lead_stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
            COALESCE(SUM(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE 0 END), 0) as revenue
        FROM leads
        WHERE created_by = ? AND YEAR(created_at) = ?
        GROUP BY DATE_FORMAT(created_at, '%Y-%m')
        ORDER BY month ASC
    ");
    $stmt->execute([$agent_id, $year]);
    return $stmt->fetchAll();
}

// Function to get recent activities
function getRecentActivities($pdo, $agent_id, $limit = 10) {
    $stmt = $pdo->prepare("
        (SELECT 'lead' as type, id, lead_unique_id as ref_id, name, created_at, 'created' as action, NULL as notes
         FROM leads WHERE created_by = ?)
        UNION ALL
        (SELECT 'communication' as type, c.id, l.lead_unique_id as ref_id, l.name, c.created_at, c.communication_type as action, c.notes
         FROM communications c JOIN leads l ON c.lead_id = l.id WHERE c.created_by = ?)
        UNION ALL
        (SELECT 'task' as type, t.id, l.lead_unique_id as ref_id, l.name, t.created_at, t.task_title as action, t.description as notes
         FROM tasks t JOIN leads l ON t.lead_id = l.id WHERE t.created_by = ?)
        ORDER BY created_at DESC LIMIT $limit
    ");
    $stmt->execute([$agent_id, $agent_id, $agent_id]);
    return $stmt->fetchAll();
}

// Get data for selected agent
$agent_stats = null;
$stage_distribution = [];
$monthly_trend = [];
$recent_activities = [];

if ($selected_agent_id) {
    $agent_stats = getAgentPerformance($pdo, $selected_agent_id, $start_date, $end_date);
    $stage_distribution = getStageDistribution($pdo, $selected_agent_id, $start_date, $end_date);
    $monthly_trend = getMonthlyTrend($pdo, $selected_agent_id, $year);
    $recent_activities = getRecentActivities($pdo, $selected_agent_id, 15);
}

// Calculate win rate
$win_rate = 0;
if ($selected_agent_id && $agent_stats && $agent_stats['total_leads'] > 0) {
    $win_rate = round(($agent_stats['won_leads'] / $agent_stats['total_leads']) * 100, 1);
}

// Predictive sales based on pipeline
$predicted_revenue = 0;
if ($selected_agent_id && $stage_distribution) {
    $weights = [
        'Lead' => 0.10,
        'Pipeline' => 0.20,
        'Qualified' => 0.35,
        'Discussion Ongoing' => 0.50,
        'Quotation Submitted' => 0.70,
        'Final Negotiation' => 0.85
    ];
    foreach ($stage_distribution as $stage) {
        if (isset($weights[$stage['lead_stage']])) {
            $predicted_revenue += $stage['total_value'] * $weights[$stage['lead_stage']];
        }
    }
}
?>

<style>
    .agent-selector {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .agent-selector h3 {
        margin-bottom: 15px;
        color: #2c3e50;
    }
    
    .selector-form {
        display: flex;
        gap: 15px;
        flex-wrap: wrap;
        align-items: flex-end;
    }
    
    .selector-group {
        flex: 1;
        min-width: 200px;
    }
    
    .selector-group label {
        display: block;
        margin-bottom: 5px;
        font-weight: 600;
        font-size: 12px;
        color: #666;
    }
    
    .selector-group select, 
    .selector-group input {
        width: 100%;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .btn-primary {
        background: #3498db;
        color: white;
        padding: 10px 20px;
        border: none;
        border-radius: 5px;
        cursor: pointer;
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
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        transition: transform 0.3s;
    }
    
    .stat-card:hover {
        transform: translateY(-3px);
    }
    
    .stat-card .stat-icon {
        font-size: 32px;
        margin-bottom: 10px;
    }
    
    .stat-card h4 {
        color: #666;
        font-size: 12px;
        margin-bottom: 8px;
    }
    
    .stat-card .stat-number {
        font-size: 28px;
        font-weight: bold;
    }
    
    .stat-card .stat-label {
        font-size: 11px;
        color: #666;
        margin-top: 5px;
    }
    
    .section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 30px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .section h3 {
        margin-bottom: 15px;
        color: #2c3e50;
        border-bottom: 2px solid #3498db;
        padding-bottom: 8px;
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
    }
    
    .data-table th {
        background: #34495e;
        color: white;
        padding: 10px;
        text-align: left;
        font-size: 12px;
    }
    
    .data-table td {
        padding: 10px;
        border-bottom: 1px solid #eee;
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
    }
    
    .won { color: #27ae60; font-weight: bold; }
    .lost { color: #e74c3c; }
    .ongoing { color: #f39c12; }
    
    .stage-badge {
        display: inline-block;
        padding: 3px 10px;
        border-radius: 15px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .progress-bar {
        background: #e0e0e0;
        border-radius: 10px;
        overflow: hidden;
        height: 8px;
        margin: 10px 0;
    }
    
    .progress-fill {
        background: #27ae60;
        height: 100%;
        transition: width 0.5s;
    }
    
    .agent-info-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 10px;
        margin-bottom: 20px;
        display: flex;
        justify-content: space-between;
        align-items: center;
        flex-wrap: wrap;
    }
    
    .agent-info h2 {
        margin-bottom: 5px;
    }
    
    .agent-info p {
        opacity: 0.9;
        margin: 5px 0;
    }
    
    .export-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn-export {
        background: rgba(255,255,255,0.2);
        color: white;
        padding: 8px 15px;
        text-decoration: none;
        border-radius: 5px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
    }
    
    .btn-export:hover {
        background: rgba(255,255,255,0.3);
    }
    
    .empty-state {
        text-align: center;
        padding: 50px;
        color: #999;
    }
    
    @media (max-width: 768px) {
        .selector-form {
            flex-direction: column;
        }
        .agent-info-card {
            flex-direction: column;
            text-align: center;
            gap: 15px;
        }
        .stats-grid {
            grid-template-columns: repeat(2, 1fr);
        }
    }
</style>

<div class="agent-selector">
    <h3><i class="fas fa-user-tie"></i> Select Agent for Performance Report</h3>
    <form method="GET" class="selector-form">
        <div class="selector-group">
            <label>Select Agent</label>
            <select name="agent_id" required>
                <option value="">-- Select an Agent --</option>
                <?php foreach($agents_list as $agent): ?>
                    <option value="<?php echo $agent['id']; ?>" 
                        <?php echo ($selected_agent_id == $agent['id']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($agent['full_name']); ?> 
                        (<?php echo htmlspecialchars($agent['username']); ?>)
                        <?php echo $agent['status'] != 'active' ? ' - ' . ucfirst($agent['status']) : ''; ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="selector-group">
            <label>Report Type</label>
            <select name="report_type" id="report_type">
                <option value="monthly" <?php echo $report_type == 'monthly' ? 'selected' : ''; ?>>Monthly</option>
                <option value="yearly" <?php echo $report_type == 'yearly' ? 'selected' : ''; ?>>Yearly</option>
                <option value="custom" <?php echo $report_type == 'custom' ? 'selected' : ''; ?>>Custom Range</option>
            </select>
        </div>
        
        <div class="selector-group" id="monthly_div">
            <label>Select Month</label>
            <input type="month" name="month" value="<?php echo $month; ?>">
        </div>
        
        <div class="selector-group" id="yearly_div" style="display: none;">
            <label>Select Year</label>
            <select name="year">
                <?php for($y = 2023; $y <= date('Y') + 1; $y++): ?>
                    <option value="<?php echo $y; ?>" <?php echo $year == $y ? 'selected' : ''; ?>><?php echo $y; ?></option>
                <?php endfor; ?>
            </select>
        </div>
        
        <div class="selector-group" id="custom_div" style="display: none;">
            <label>Start Date</label>
            <input type="date" name="custom_start" value="<?php echo $custom_start; ?>">
        </div>
        
        <div class="selector-group" id="custom_end_div" style="display: none;">
            <label>End Date</label>
            <input type="date" name="custom_end" value="<?php echo $custom_end; ?>">
        </div>
        
        <div class="selector-group">
            <button type="submit" class="btn-primary">
                <i class="fas fa-chart-line"></i> View Report
            </button>
        </div>
    </form>
</div>

<?php if($selected_agent_id && $selected_agent): ?>
    <!-- Agent Info Card -->
    <div class="agent-info-card">
        <div class="agent-info">
            <h2><i class="fas fa-user-circle"></i> <?php echo htmlspecialchars($selected_agent['full_name']); ?></h2>
            <p><i class="fas fa-envelope"></i> <?php echo htmlspecialchars($selected_agent['email'] ?? 'No email'); ?> | 
               <i class="fas fa-phone"></i> <?php echo htmlspecialchars($selected_agent['phone'] ?? 'No phone'); ?> |
               <i class="fas fa-calendar"></i> Joined: <?php echo date('Y-m-d', strtotime($selected_agent['join_date'] ?? $selected_agent['created_at'])); ?></p>
            <p><i class="fas fa-chart-line"></i> Report Period: <?php echo date('Y-m-d', strtotime($start_date)); ?> to <?php echo date('Y-m-d', strtotime($end_date)); ?></p>
        </div>
        <div class="export-buttons">
            <a href="export_agent_report.php?agent_id=<?php echo $selected_agent_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=pdf" target="_blank" class="btn-export">
                <i class="fas fa-file-pdf"></i> Export PDF
            </a>
            <a href="export_agent_report.php?agent_id=<?php echo $selected_agent_id; ?>&start_date=<?php echo $start_date; ?>&end_date=<?php echo $end_date; ?>&format=excel" class="btn-export">
                <i class="fas fa-file-excel"></i> Export Excel
            </a>
        </div>
    </div>
    
    <!-- Statistics Grid -->
    <div class="stats-grid">
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-users"></i></div>
            <h4>Total Leads</h4>
            <div class="stat-number"><?php echo $agent_stats['total_leads'] ?? 0; ?></div>
            <div class="stat-label">Created in period</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <h4>Ongoing Leads</h4>
            <div class="stat-number ongoing"><?php echo $agent_stats['ongoing_leads'] ?? 0; ?></div>
            <div class="stat-label">In pipeline</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-trophy"></i></div>
            <h4>Won Leads</h4>
            <div class="stat-number won"><?php echo $agent_stats['won_leads'] ?? 0; ?></div>
            <div class="stat-label">Successfully converted</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-times-circle"></i></div>
            <h4>Lost Leads</h4>
            <div class="stat-number lost"><?php echo $agent_stats['lost_leads'] ?? 0; ?></div>
            <div class="stat-label">Lost/Cancelled</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-simple"></i></div>
            <h4>Win Rate</h4>
            <div class="stat-number"><?php echo $win_rate; ?>%</div>
            <div class="stat-label">Conversion rate</div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $win_rate; ?>%;"></div>
            </div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-dollar-sign"></i></div>
            <h4>Total Revenue</h4>
            <div class="stat-number won"><?php echo number_format($agent_stats['total_revenue'] ?? 0, 0); ?> BDT</div>
            <div class="stat-label">From won deals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-pie"></i></div>
            <h4>Pipeline Value</h4>
            <div class="stat-number"><?php echo number_format($agent_stats['pipeline_value'] ?? 0, 0); ?> BDT</div>
            <div class="stat-label">Potential deals</div>
        </div>
        <div class="stat-card">
            <div class="stat-icon"><i class="fas fa-chart-line"></i></div>
            <h4>Avg Deal Size</h4>
            <div class="stat-number"><?php echo number_format($agent_stats['avg_deal_size'] ?? 0, 0); ?> BDT</div>
            <div class="stat-label">Average won deal</div>
        </div>
    </div>
    
    <!-- Predictive Sales -->
    <div class="section">
        <h3><i class="fas fa-chart-line"></i> Predictive Sales Forecast</h3>
        <div class="stats-grid">
            <div class="stat-card">
                <h4>Predicted Revenue</h4>
                <div class="stat-number" style="color: #3498db;"><?php echo number_format($predicted_revenue, 0); ?> BDT</div>
                <div class="stat-label">Based on weighted pipeline</div>
            </div>
            <div class="stat-card">
                <h4>Leads in Pipeline</h4>
                <div class="stat-number"><?php echo $agent_stats['ongoing_leads'] ?? 0; ?></div>
                <div class="stat-label">Active opportunities</div>
            </div>
            <div class="stat-card">
                <h4>Projected Completion</h4>
                <div class="stat-number" style="color: #f39c12;"><?php echo round($predicted_revenue / max(($agent_stats['avg_deal_size'] ?? 1), 1), 0); ?></div>
                <div class="stat-label">Expected number of deals</div>
            </div>
        </div>
    </div>
    
    <!-- Stage-wise Pipeline -->
    <div class="section">
        <h3><i class="fas fa-filter"></i> Lead Pipeline by Stage</h3>
        <?php if(count($stage_distribution) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Stage</th>
                    <th>Number of Leads</th>
                    <th>Total Value (BDT)</th>
                    <th>Average Value</th>
                    <th>Weighted Value</th>
                </tr>
            </thead>
            <tbody>
                <?php 
                $weights = ['Lead' => 0.10, 'Pipeline' => 0.20, 'Qualified' => 0.35, 'Discussion Ongoing' => 0.50, 'Quotation Submitted' => 0.70, 'Final Negotiation' => 0.85];
                foreach($stage_distribution as $stage): 
                    $weighted = isset($weights[$stage['lead_stage']]) ? $stage['total_value'] * $weights[$stage['lead_stage']] : 0;
                ?>
                <tr>
                    <td><?php echo $stage['lead_stage']; ?></td>
                    <td><?php echo $stage['count']; ?></td>
                    <td><?php echo number_format($stage['total_value'], 2); ?></td>
                    <td><?php echo $stage['count'] > 0 ? number_format($stage['total_value'] / $stage['count'], 2) : 0; ?></td>
                    <td><?php echo number_format($weighted, 2); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
         </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-bar" style="font-size: 48px;"></i>
                <p>No lead data available for this period</p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Monthly Performance Trend -->
    <div class="section">
        <h3><i class="fas fa-chart-line"></i> Monthly Performance Trend (<?php echo $year; ?>)</h3>
        <?php if(count($monthly_trend) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Month</th>
                    <th>Total Leads</th>
                    <th>Won Leads</th>
                    <th>Revenue (BDT)</th>
                    <th>Conversion Rate</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($monthly_trend as $month_data): 
                    $month_name = date('F Y', strtotime($month_data['month'] . '-01'));
                    $conv_rate = $month_data['total_leads'] > 0 ? round(($month_data['won_leads'] / $month_data['total_leads']) * 100, 1) : 0;
                ?>
                <tr>
                    <td><?php echo $month_name; ?></td>
                    <td><?php echo $month_data['total_leads']; ?></td>
                    <td class="won"><?php echo $month_data['won_leads']; ?></td>
                    <td class="won"><?php echo number_format($month_data['revenue'], 2); ?></td>
                    <td><?php echo $conv_rate; ?>%</td>
                </tr>
                <?php endforeach; ?>
            </tbody>
         </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-chart-line" style="font-size: 48px;"></i>
                <p>No monthly data available for <?php echo $year; ?></p>
            </div>
        <?php endif; ?>
    </div>
    
    <!-- Recent Activities -->
    <div class="section">
        <h3><i class="fas fa-history"></i> Recent Activities</h3>
        <?php if(count($recent_activities) > 0): ?>
        <table class="data-table">
            <thead>
                <tr>
                    <th>Date & Time</th>
                    <th>Lead</th>
                    <th>Action</th>
                    <th>Details</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($recent_activities as $activity): ?>
                <tr>
                    <td><?php echo date('Y-m-d H:i', strtotime($activity['created_at'])); ?></td>
                    <td>
                        <a href="view_lead.php?id=<?php echo $activity['ref_id']; ?>" target="_blank">
                            <?php echo htmlspecialchars($activity['name']); ?>
                        </a>
                    </td>
                    <td>
                        <span class="stage-badge" style="background: #e8f4f8; color: #3498db;">
                            <?php echo ucfirst($activity['action']); ?>
                        </span>
                    </td>
                    <td><?php echo htmlspecialchars(substr($activity['notes'] ?? '', 0, 100)); ?></td>
                </tr>
                <?php endforeach; ?>
            </tbody>
         </table>
        <?php else: ?>
            <div class="empty-state">
                <i class="fas fa-history" style="font-size: 48px;"></i>
                <p>No recent activities found</p>
            </div>
        <?php endif; ?>
    </div>
    
<?php elseif($selected_agent_id && !$selected_agent): ?>
    <div class="empty-state" style="background: white; border-radius: 10px; padding: 50px;">
        <i class="fas fa-exclamation-triangle" style="font-size: 48px; color: #e74c3c;"></i>
        <h3>Agent Not Found</h3>
        <p>The selected agent does not exist or is not a field agent.</p>
        <a href="agent_reports.php" class="btn-primary" style="display: inline-block; margin-top: 15px;">Go Back</a>
    </div>
<?php else: ?>
    <div class="empty-state" style="background: white; border-radius: 10px; padding: 50px;">
        <i class="fas fa-user-tie" style="font-size: 48px; color: #3498db;"></i>
        <h3>Select an Agent to View Report</h3>
        <p>Please select an agent from the dropdown above to view their performance report.</p>
        <?php if(count($agents_list) == 0): ?>
            <p style="color: #e74c3c; margin-top: 10px;">
                <i class="fas fa-info-circle"></i> No field agents found. 
                <a href="add_user.php">Click here to add an agent</a>
            </p>
        <?php endif; ?>
    </div>
<?php endif; ?>

<script>
    // Toggle date inputs based on report type
    const reportType = document.getElementById('report_type');
    const monthlyDiv = document.getElementById('monthly_div');
    const yearlyDiv = document.getElementById('yearly_div');
    const customDiv = document.getElementById('custom_div');
    const customEndDiv = document.getElementById('custom_end_div');
    
    function toggleDateInputs() {
        const type = reportType.value;
        monthlyDiv.style.display = 'none';
        yearlyDiv.style.display = 'none';
        customDiv.style.display = 'none';
        customEndDiv.style.display = 'none';
        
        if (type === 'monthly') {
            monthlyDiv.style.display = 'block';
        } else if (type === 'yearly') {
            yearlyDiv.style.display = 'block';
        } else if (type === 'custom') {
            customDiv.style.display = 'block';
            customEndDiv.style.display = 'block';
        }
    }
    
    reportType.addEventListener('change', toggleDateInputs);
    toggleDateInputs();
</script>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>