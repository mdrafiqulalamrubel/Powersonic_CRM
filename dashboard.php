<?php
// Start output buffering to prevent header issues
ob_start();

require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

// Include header AFTER all PHP logic
require_once 'includes/header.php';

// Fetch statistics
$totalLeads = $pdo->query("SELECT COUNT(*) FROM leads")->fetchColumn();
$highPriority = $pdo->query("SELECT COUNT(*) FROM leads WHERE priority = 'High'")->fetchColumn();
$convertedThisMonth = $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'Converted' AND MONTH(created_at) = MONTH(CURRENT_DATE())")->fetchColumn();

// Fetch leads grouped by area and priority
$areaWiseLeads = $pdo->query("SELECT area, priority, COUNT(*) as count FROM leads GROUP BY area, priority ORDER BY area, FIELD(priority, 'High', 'Medium', 'Low')")->fetchAll();

// Fetch recent leads
$recentLeads = $pdo->query("SELECT * FROM leads ORDER BY created_at DESC LIMIT 10")->fetchAll();
?>

<div class="stats-grid">
    <div class="stat-card">
        <h3>Total Leads</h3>
        <div class="number"><?php echo $totalLeads; ?></div>
    </div>
    <div class="stat-card">
        <h3>High Priority Leads</h3>
        <div class="number"><?php echo $highPriority; ?></div>
    </div>
    <div class="stat-card">
        <h3>Converted This Month</h3>
        <div class="number"><?php echo $convertedThisMonth; ?></div>
    </div>
</div>

<div style="background: white; border-radius: 10px; padding: 20px; margin-bottom: 20px;">
    <h3 style="margin-bottom: 15px;">Leads by Area & Priority</h3>
    <?php
    $currentArea = '';
    foreach($areaWiseLeads as $row):
        if($currentArea != $row['area']):
            if($currentArea != ''): echo '</div>'; endif;
            $currentArea = $row['area'];
            echo '<div style="margin-bottom: 15px;">';
            echo '<strong style="background: #ecf0f1; padding: 8px; display: block;">Area: ' . htmlspecialchars($row['area']) . '</strong>';
        endif;
        $priorityClass = 'priority-' . strtolower($row['priority']);
        echo '<div style="padding: 8px 15px;">';
        echo '<span class="' . $priorityClass . '">' . $row['priority'] . '</span>: ';
        echo $row['count'] . ' leads';
        echo '</div>';
    endforeach;
    echo '</div>';
    ?>
</div>

<div style="background: white; border-radius: 10px; padding: 20px;">
    <h3 style="margin-bottom: 15px;">Recent Leads</h3>
    <table class="data-table">
        <thead>
            <tr>
                <th>Lead ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>Area</th>
                <th>Priority</th>
                <th>Status</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($recentLeads as $lead): ?>
            <tr>
                <td><?php echo $lead['lead_unique_id']; ?></td>
                <td><?php echo htmlspecialchars($lead['name']); ?></td>
                <td><?php echo $lead['phone']; ?></td>
                <td><?php echo htmlspecialchars($lead['area']); ?></td>
                <td class="priority-<?php echo strtolower($lead['priority']); ?>"><?php echo $lead['priority']; ?></td>
                <td><?php echo $lead['status']; ?></td>
                <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                <td>
                    <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn">View</a>
                    <a href="edit_lead.php?id=<?php echo $lead['id']; ?>" class="btn btn-success">Edit</a>
                </td>
            </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>