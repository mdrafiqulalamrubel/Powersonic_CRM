<?php
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

// Get date filter
$month = $_GET['month'] ?? date('Y-m');
$start_date = $month . '-01';
$end_date = date('Y-m-t', strtotime($start_date));

// Agent performance query
$agent_performance = $pdo->prepare("
    SELECT 
        u.id,
        u.full_name,
        COUNT(DISTINCT l.id) as total_leads,
        SUM(CASE WHEN l.lead_stage NOT IN ('Won', 'Lost', 'Cancelled') THEN 1 ELSE 0 END) as ongoing_leads,
        SUM(CASE WHEN l.lead_stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
        SUM(CASE WHEN l.lead_stage IN ('Lost', 'Cancelled') THEN 1 ELSE 0 END) as lost_leads,
        COALESCE(SUM(CASE WHEN l.lead_stage = 'Won' THEN l.expected_amount ELSE 0 END), 0) as total_sales_value,
        COALESCE(AVG(CASE WHEN l.lead_stage = 'Won' THEN l.expected_amount ELSE NULL END), 0) as avg_deal_size
    FROM users u
    LEFT JOIN leads l ON u.id = l.created_by AND DATE(l.created_at) BETWEEN ? AND ?
    WHERE u.role = 'field_agent'
    GROUP BY u.id, u.full_name
    ORDER BY total_sales_value DESC
");

$agent_performance->execute([$start_date, $end_date]);
$agents = $agent_performance->fetchAll();

// Overall statistics
$overall = $pdo->prepare("
    SELECT 
        COUNT(*) as total_leads,
        SUM(CASE WHEN lead_stage NOT IN ('Won', 'Lost', 'Cancelled') THEN 1 ELSE 0 END) as ongoing,
        SUM(CASE WHEN lead_stage = 'Won' THEN 1 ELSE 0 END) as won,
        SUM(CASE WHEN lead_stage IN ('Lost', 'Cancelled') THEN 1 ELSE 0 END) as lost,
        COALESCE(SUM(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE 0 END), 0) as total_revenue,
        COALESCE(AVG(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE NULL END), 0) as avg_deal,
        SUM(expected_amount) as pipeline_value
    FROM leads
    WHERE DATE(created_at) BETWEEN ? AND ?
");
$overall->execute([$start_date, $end_date]);
$stats = $overall->fetch();

// Stage wise leads
$stage_stats = $pdo->prepare("
    SELECT lead_stage, COUNT(*) as count, SUM(expected_amount) as total_value
    FROM leads
    WHERE DATE(created_at) BETWEEN ? AND ?
    GROUP BY lead_stage
    ORDER BY FIELD(lead_stage, 'Lead', 'Pipeline', 'Qualified', 'Discussion Ongoing', 'Quotation Submitted', 'Final Negotiation', 'Won', 'Lost', 'Cancelled')
");
$stage_stats->execute([$start_date, $end_date]);
$stages = $stage_stats->fetchAll();

// Predictive sales volume
$predictive = $pdo->prepare("
    SELECT 
        SUM(CASE 
            WHEN lead_stage = 'Lead' THEN expected_amount * 0.10
            WHEN lead_stage = 'Pipeline' THEN expected_amount * 0.20
            WHEN lead_stage = 'Qualified' THEN expected_amount * 0.35
            WHEN lead_stage = 'Discussion Ongoing' THEN expected_amount * 0.50
            WHEN lead_stage = 'Quotation Submitted' THEN expected_amount * 0.70
            WHEN lead_stage = 'Final Negotiation' THEN expected_amount * 0.85
            ELSE 0
        END) as predicted_revenue,
        COUNT(CASE WHEN lead_stage IN ('Lead', 'Pipeline', 'Qualified') THEN 1 END) as early_stage,
        COUNT(CASE WHEN lead_stage IN ('Discussion Ongoing', 'Quotation Submitted', 'Final Negotiation') THEN 1 END) as advanced_stage
    FROM leads
    WHERE lead_stage NOT IN ('Won', 'Lost', 'Cancelled')
    AND DATE(created_at) BETWEEN ? AND ?
");
$predictive->execute([$start_date, $end_date]);
$prediction = $predictive->fetch();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Agent Reports - Power Sonic CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; }
        .container { max-width: 1200px; margin: 30px auto; padding: 0 20px; }
        .stats-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(250px, 1fr)); gap: 20px; margin-bottom: 30px; }
        .stat-card { background: white; padding: 20px; border-radius: 10px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .stat-card h3 { color: #666; font-size: 14px; margin-bottom: 10px; }
        .stat-card .number { font-size: 32px; font-weight: bold; color: #2c3e50; }
        .stat-card .small { font-size: 12px; color: #666; margin-top: 5px; }
        .section { background: white; border-radius: 10px; padding: 20px; margin-bottom: 30px; box-shadow: 0 2px 5px rgba(0,0,0,0.1); }
        .section h3 { margin-bottom: 15px; color: #2c3e50; border-bottom: 2px solid #3498db; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; }
        th, td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        th { background: #34495e; color: white; }
        .won { color: #27ae60; font-weight: bold; }
        .lost { color: #e74c3c; }
        .ongoing { color: #f39c12; }
        .filter-bar { background: white; padding: 15px; border-radius: 10px; margin-bottom: 20px; }
        .btn { background: #3498db; color: white; padding: 8px 15px; text-decoration: none; border-radius: 5px; display: inline-block; }
        .nav-links { text-align: center; margin-top: 20px; }
        .progress-bar { background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 30px; margin: 10px 0; }
        .progress-fill { background: #27ae60; height: 100%; display: flex; align-items: center; justify-content: center; color: white; font-size: 12px; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Power Sonic CRM - Agent Performance Reports</h2>
        <div>Welcome, <?php echo $_SESSION['full_name']; ?> | <a href="logout.php" style="color: white;">Logout</a></div>
    </div>
    
    <div class="container">
        <div class="filter-bar">
            <form method="GET" style="display: flex; gap: 10px; align-items: center;">
                <label>Report Month:</label>
                <input type="month" name="month" value="<?php echo $month; ?>">
                <button type="submit" class="btn">Filter</button>
            </form>
        </div>
        
        <!-- Overall Stats -->
        <div class="stats-grid">
            <div class="stat-card">
                <h3>Total Leads</h3>
                <div class="number"><?php echo $stats['total_leads']; ?></div>
                <div class="small">This month</div>
            </div>
            <div class="stat-card">
                <h3>Ongoing Leads</h3>
                <div class="number ongoing"><?php echo $stats['ongoing']; ?></div>
                <div class="small">In pipeline</div>
            </div>
            <div class="stat-card">
                <h3>Won Leads</h3>
                <div class="number won"><?php echo $stats['won']; ?></div>
                <div class="small">Successfully converted</div>
            </div>
            <div class="stat-card">
                <h3>Lost Leads</h3>
                <div class="number lost"><?php echo $stats['lost']; ?></div>
                <div class="small">Lost/Cancelled</div>
            </div>
            <div class="stat-card">
                <h3>Total Revenue</h3>
                <div class="number"><?php echo number_format($stats['total_revenue'], 2); ?> BDT</div>
                <div class="small">From won deals</div>
            </div>
            <div class="stat-card">
                <h3>Pipeline Value</h3>
                <div class="number"><?php echo number_format($stats['pipeline_value'], 2); ?> BDT</div>
                <div class="small">Potential deals</div>
            </div>
        </div>
        
        <!-- Predictive Sales Volume -->
        <div class="section">
            <h3>📊 Predictive Sales Volume</h3>
            <div class="stats-grid">
                <div class="stat-card">
                    <h3>Predicted Revenue (Next 3 Months)</h3>
                    <div class="number"><?php echo number_format($prediction['predicted_revenue'], 2); ?> BDT</div>
                    <div class="small">Based on weighted probability</div>
                </div>
                <div class="stat-card">
                    <h3>Early Stage Leads</h3>
                    <div class="number"><?php echo $prediction['early_stage']; ?></div>
                    <div class="small">10-35% probability</div>
                </div>
                <div class="stat-card">
                    <h3>Advanced Stage Leads</h3>
                    <div class="number"><?php echo $prediction['advanced_stage']; ?></div>
                    <div class="small">50-85% probability</div>
                </div>
            </div>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo ($stats['won'] > 0 ? ($stats['won'] / max($stats['total_leads'], 1) * 100) : 0); ?>%;">
                    Win Rate: <?php echo $stats['total_leads'] > 0 ? round(($stats['won'] / $stats['total_leads']) * 100, 1) : 0; ?>%
                </div>
            </div>
        </div>
        
        <!-- Agent Performance Table -->
        <div class="section">
            <h3>👥 Agent-wise Performance</h3>
            <table>
                <thead>
                    <tr>
                        <th>Agent Name</th>
                        <th>Total Leads</th>
                        <th>Ongoing</th>
                        <th>Won</th>
                        <th>Lost</th>
                        <th>Win Rate</th>
                        <th>Total Sales (BDT)</th>
                        <th>Avg Deal Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($agents as $agent): ?>
                    <tr>
                        <td><?php echo htmlspecialchars($agent['full_name']); ?></td>
                        <td><?php echo $agent['total_leads']; ?></td>
                        <td class="ongoing"><?php echo $agent['ongoing_leads']; ?></td>
                        <td class="won"><?php echo $agent['won_leads']; ?></td>
                        <td class="lost"><?php echo $agent['lost_leads']; ?></td>
                        <td>
                            <?php 
                            $win_rate = $agent['total_leads'] > 0 ? round(($agent['won_leads'] / $agent['total_leads']) * 100, 1) : 0;
                            echo $win_rate . '%';
                            ?>
                        </td>
                        <td><?php echo number_format($agent['total_sales_value'], 2); ?></td>
                        <td><?php echo number_format($agent['avg_deal_size'], 2); ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <!-- Stage-wise Pipeline -->
        <div class="section">
            <h3>🔄 Lead Pipeline by Stage</h3>
            <table>
                <thead>
                    <tr>
                        <th>Stage</th>
                        <th>Number of Leads</th>
                        <th>Total Value (BDT)</th>
                        <th>Average Value</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($stages as $stage): ?>
                    <tr>
                        <td><?php echo $stage['lead_stage']; ?></td>
                        <td><?php echo $stage['count']; ?></td>
                        <td><?php echo number_format($stage['total_value'], 2); ?></td>
                        <td><?php echo $stage['count'] > 0 ? number_format($stage['total_value'] / $stage['count'], 2) : 0; ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
        
        <div class="nav-links">
            <a href="dashboard.php" class="btn">Dashboard</a>
            <a href="add_lead.php" class="btn">Add Lead</a>
            <a href="view_leads.php" class="btn">View All Leads</a>
        </div>
    </div>
</body>
</html>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>

