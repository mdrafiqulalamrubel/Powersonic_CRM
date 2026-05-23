<div style="background: #34495e; padding: 10px; text-align: center;">
    <a href="dashboard.php" style="color: white; margin: 0 10px;">Dashboard</a>
    <a href="add_lead.php" style="color: white; margin: 0 10px;">Add Lead</a>
    <a href="view_leads.php" style="color: white; margin: 0 10px;">View Leads</a>
    <a href="agent_reports.php" style="color: white; margin: 0 10px;">Reports</a>
    <a href="notifications.php" style="color: white; margin: 0 10px;">Notifications 
        <?php if($notif_count > 0): ?>[<?php echo $notif_count; ?>]<?php endif; ?>
    </a>
    <a href="logout.php" style="color: white; margin: 0 10px;">Logout</a>
</div>