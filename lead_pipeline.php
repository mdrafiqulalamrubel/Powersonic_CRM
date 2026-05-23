<?php
require_once 'config.php';
require_once 'includes/header.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Get leads grouped by stage
$stages = ['Lead', 'Pipeline', 'Qualified', 'Discussion Ongoing', 'Quotation Submitted', 'Final Negotiation', 'Won', 'Lost'];
$pipeline_data = [];

foreach($stages as $stage) {
    $stmt = $pdo->prepare("SELECT COUNT(*) as count, COALESCE(SUM(expected_amount), 0) as value FROM leads WHERE lead_stage = ?");
    $stmt->execute([$stage]);
    $pipeline_data[$stage] = $stmt->fetch();
}
?>

<div class="pipeline-container">
    <h2>Lead Pipeline</h2>
    <div style="display: flex; gap: 20px; overflow-x: auto; padding: 20px 0;">
        <?php foreach($stages as $stage): ?>
        <div style="min-width: 250px; background: white; border-radius: 10px; padding: 15px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);">
            <h3 style="margin-bottom: 10px;"><?php echo $stage; ?></h3>
            <div style="font-size: 24px; font-weight: bold;"><?php echo $pipeline_data[$stage]['count']; ?></div>
            <div style="color: #666;">Leads</div>
            <div style="margin-top: 10px; font-size: 14px; color: #27ae60;">
                Value: <?php echo number_format($pipeline_data[$stage]['value'], 2); ?> BDT
            </div>
        </div>
        <?php endforeach; ?>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>