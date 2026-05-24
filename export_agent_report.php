<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

$agent_id = $_GET['agent_id'] ?? 0;
$start_date = $_GET['start_date'] ?? date('Y-m-01');
$end_date = $_GET['end_date'] ?? date('Y-m-t');
$format = $_GET['format'] ?? 'excel';

// Get agent info
$stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
$stmt->execute([$agent_id]);
$agent = $stmt->fetch();

if (!$agent) {
    die("Agent not found");
}

// Get performance data
$lead_stats = $pdo->prepare("
    SELECT 
        COUNT(*) as total_leads,
        SUM(CASE WHEN lead_stage NOT IN ('Won', 'Lost', 'Cancelled') THEN 1 ELSE 0 END) as ongoing_leads,
        SUM(CASE WHEN lead_stage = 'Won' THEN 1 ELSE 0 END) as won_leads,
        SUM(CASE WHEN lead_stage IN ('Lost', 'Cancelled') THEN 1 ELSE 0 END) as lost_leads,
        COALESCE(SUM(CASE WHEN lead_stage = 'Won' THEN expected_amount ELSE 0 END), 0) as total_revenue
    FROM leads 
    WHERE created_by = ? AND DATE(created_at) BETWEEN ? AND ?
");
$lead_stats->execute([$agent_id, $start_date, $end_date]);
$stats = $lead_stats->fetch();

$win_rate = $stats['total_leads'] > 0 ? round(($stats['won_leads'] / $stats['total_leads']) * 100, 1) : 0;

// Get leads data
$leads = $pdo->prepare("
    SELECT lead_unique_id, name, phone, district, priority, lead_stage, expected_amount, created_at
    FROM leads WHERE created_by = ? AND DATE(created_at) BETWEEN ? AND ?
    ORDER BY created_at DESC
");
$leads->execute([$agent_id, $start_date, $end_date]);
$leads_data = $leads->fetchAll();

// Set filename
$filename = "agent_report_{$agent['username']}_{$start_date}_to_{$end_date}";

if ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html>';
    echo '<head><meta charset="UTF-8"><title>Agent Report</title></head>';
    echo '<body>';
    echo '<h2>Agent Performance Report</h2>';
    echo '<p><strong>Agent:</strong> ' . htmlspecialchars($agent['full_name']) . '</p>';
    echo '<p><strong>Period:</strong> ' . $start_date . ' to ' . $end_date . '</p>';
    echo '<p><strong>Generated:</strong> ' . date('Y-m-d H:i:s') . '</p>';
    echo '<br>';
    
    // Summary
    echo '<h3>Performance Summary</h3>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Metric</th><th>Value</th></tr>';
    echo '<tr><td>Total Leads</td><td>' . $stats['total_leads'] . '</td></tr>';
    echo '<tr><td>Ongoing Leads</td><td>' . $stats['ongoing_leads'] . '</td></tr>';
    echo '<tr><td>Won Leads</td><td>' . $stats['won_leads'] . '</td></tr>';
    echo '<tr><td>Lost Leads</td><td>' . $stats['lost_leads'] . '</td></tr>';
    echo '<tr><td>Win Rate</td><td>' . $win_rate . '%</td></tr>';
    echo '<tr><td>Total Revenue</td><td>' . number_format($stats['total_revenue'], 2) . ' BDT</td></tr>';
    echo '</table><br>';
    
    // Leads details
    echo '<h3>Lead Details</h3>';
    echo '<table border="1" cellpadding="5">';
    echo '<tr><th>Lead ID</th><th>Name</th><th>Phone</th><th>District</th><th>Priority</th><th>Stage</th><th>Amount</th><th>Created</th></tr>';
    foreach ($leads_data as $lead) {
        echo '<tr>';
        echo '<td>' . $lead['lead_unique_id'] . '</td>';
        echo '<td>' . htmlspecialchars($lead['name']) . '</td>';
        echo '<td>' . $lead['phone'] . '</td>';
        echo '<td>' . htmlspecialchars($lead['district'] ?? 'N/A') . '</td>';
        echo '<td>' . $lead['priority'] . '</td>';
        echo '<td>' . ($lead['lead_stage'] ?? 'Lead') . '</td>';
        echo '<td>' . number_format($lead['expected_amount'] ?? 0, 2) . '</td>';
        echo '<td>' . $lead['created_at'] . '</td>';
        echo '</tr>';
    }
    echo '</table>';
    echo '</body></html>';
    exit();
}

// PDF format (HTML that can be printed)
else {
    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="' . $filename . '.html"');
    ?>
    <!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Agent Report - <?php echo htmlspecialchars($agent['full_name']); ?></title>
        <style>
            body { font-family: Arial, sans-serif; margin: 20px; }
            h1 { color: #2c3e50; }
            .summary { background: #f8f9fa; padding: 15px; margin: 20px 0; }
            table { width: 100%; border-collapse: collapse; margin: 20px 0; }
            th, td { border: 1px solid #ddd; padding: 8px; text-align: left; }
            th { background: #34495e; color: white; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; }
            button { padding: 10px 20px; margin: 10px; cursor: pointer; }
        </style>
    </head>
    <body>
        <button onclick="window.print()" style="background: #3498db; color: white; border: none;">Print / Save as PDF</button>
        <button onclick="window.close()" style="background: #95a5a6; color: white; border: none;">Close</button>
        
        <h1>Agent Performance Report</h1>
        <p><strong>Agent:</strong> <?php echo htmlspecialchars($agent['full_name']); ?></p>
        <p><strong>Period:</strong> <?php echo $start_date; ?> to <?php echo $end_date; ?></p>
        <p><strong>Generated:</strong> <?php echo date('Y-m-d H:i:s'); ?></p>
        
        <div class="summary">
            <h3>Performance Summary</h3>
            <table>
                <tr><th>Metric</th><th>Value</th></tr>
                <tr><td>Total Leads</td><td><?php echo $stats['total_leads']; ?></td></tr>
                <tr><td>Ongoing Leads</td><td><?php echo $stats['ongoing_leads']; ?></td></tr>
                <tr><td>Won Leads</td><td><?php echo $stats['won_leads']; ?></td></tr>
                <tr><td>Lost Leads</td><td><?php echo $stats['lost_leads']; ?></td></tr>
                <tr><td>Win Rate</td><td><?php echo $win_rate; ?>%</td></tr>
                <tr><td>Total Revenue</td><td><?php echo number_format($stats['total_revenue'], 2); ?> BDT</td></tr>
            </table>
        </div>
        
        <h3>Lead Details</h3>
        <table>
            <tr><th>Lead ID</th><th>Name</th><th>Phone</th><th>District</th><th>Priority</th><th>Stage</th><th>Amount</th><th>Created</th></tr>
            <?php foreach($leads_data as $lead): ?>
            <tr>
                <td><?php echo $lead['lead_unique_id']; ?></td>
                <td><?php echo htmlspecialchars($lead['name']); ?></td>
                <td><?php echo $lead['phone']; ?></td>
                <td><?php echo htmlspecialchars($lead['district'] ?? 'N/A'); ?></td>
                <td><?php echo $lead['priority']; ?></td>
                <td><?php echo $lead['lead_stage'] ?? 'Lead'; ?></td>
                <td><?php echo number_format($lead['expected_amount'] ?? 0, 2); ?></td>
                <td><?php echo $lead['created_at']; ?></td>
            </tr>
            <?php endforeach; ?>
        </table>
        
        <div class="footer">
            <p>Power Sonic CRM - Agent Performance Report</p>
        </div>
    </body>
    </html>
    <?php
    exit();
}
?>