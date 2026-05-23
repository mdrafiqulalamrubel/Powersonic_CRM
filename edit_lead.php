<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn() || !isAdmin()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$lead_id = $_GET['id'] ?? 0;
$lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$lead->execute([$lead_id]);
$lead = $lead->fetch();

if (!$lead) {
    redirect('view_leads.php');
}

// Get stages for dropdown
$stages = $pdo->query("SELECT * FROM lead_stages WHERE is_active = TRUE ORDER BY stage_order")->fetchAll();

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'];
    $phone = $_POST['phone'];
    $area = $_POST['area'];
    $address = $_POST['address'];
    $priority = $_POST['priority'];
    $lead_stage = $_POST['lead_stage'];
    $expected_amount = $_POST['expected_amount'];
    $probability = $_POST['probability'];
    $next_followup = $_POST['next_followup'];
    $status = $_POST['status'];
    
    // Check if amount changed for history
    $old_amount = $lead['expected_amount'];
    
    try {
        $stmt = $pdo->prepare("UPDATE leads SET 
            name = ?, email = ?, phone = ?, area = ?, address = ?, 
            priority = ?, lead_stage = ?, expected_amount = ?, 
            probability = ?, next_followup_date = ?, status = ?,
            updated_at = NOW()
            WHERE id = ?");
        
        $stmt->execute([$name, $email, $phone, $area, $address, 
            $priority, $lead_stage, $expected_amount, $probability, 
            $next_followup, $status, $lead_id]);
        
        // Log amount change if needed
        if ($old_amount != $expected_amount && $expected_amount > 0) {
            $hist = $pdo->prepare("INSERT INTO lead_amount_history (lead_id, previous_amount, new_amount, changed_by) VALUES (?, ?, ?, ?)");
            $hist->execute([$lead_id, $old_amount, $expected_amount, $_SESSION['user_id']]);
        }
        
        // Handle stage change and create notification if won/lost
        if ($lead_stage == 'Won' && $lead['lead_stage'] != 'Won') {
            $pdo->prepare("UPDATE leads SET won_date = NOW() WHERE id = ?")->execute([$lead_id]);
            
            // Update agent performance
            $agent_id = $lead['created_by'];
            $perf = $pdo->prepare("INSERT INTO agent_performance (agent_id, report_month, won_leads, total_amount_won) 
                VALUES (?, DATE_FORMAT(NOW(), '%Y-%m-01'), 1, ?)
                ON DUPLICATE KEY UPDATE won_leads = won_leads + 1, total_amount_won = total_amount_won + ?");
            $perf->execute([$agent_id, $expected_amount, $expected_amount]);
        }
        
        // Create notification for next followup
        if ($next_followup && $next_followup > date('Y-m-d')) {
            $notif_msg = "Follow-up required for {$lead['name']} on " . date('Y-m-d', strtotime($next_followup));
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'followup', ?)");
            $notif->execute([$lead['created_by'], $lead_id, $notif_msg]);
        }
        
        $message = "Lead updated successfully!";
        
        // Refresh lead data
        $lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $lead->execute([$lead_id]);
        $lead = $lead->fetch();
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Edit Lead - Power Sonic CRM</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif; background: #f5f5f5; }
        .header { background: #2c3e50; color: white; padding: 15px 20px; }
        .container { max-width: 800px; margin: 30px auto; background: white; border-radius: 10px; padding: 30px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .form-group { margin-bottom: 20px; }
        label { display: block; margin-bottom: 5px; font-weight: 600; color: #333; }
        input, select, textarea { width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px; font-size: 14px; }
        button { background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px; }
        .message { padding: 10px; border-radius: 5px; margin-bottom: 20px; }
        .success { background: #d4edda; color: #155724; border: 1px solid #c3e6cb; }
        .error { background: #f8d7da; color: #721c24; border: 1px solid #f5c6cb; }
        .nav-links { margin-top: 20px; text-align: center; }
        .nav-links a { color: #3498db; text-decoration: none; margin: 0 10px; }
        h2 { margin-bottom: 20px; color: #333; }
        .stage-progress { margin-bottom: 20px; padding: 15px; background: #f8f9fa; border-radius: 5px; }
        .progress-bar { background: #e0e0e0; border-radius: 10px; overflow: hidden; height: 20px; }
        .progress-fill { background: #27ae60; height: 100%; transition: width 0.3s; }
    </style>
</head>
<body>
    <div class="header">
        <h2>Power Sonic CRM - Edit Lead</h2>
        <div>Welcome, <?php echo $_SESSION['full_name']; ?> | <a href="logout.php" style="color: white;">Logout</a></div>
    </div>
    
    <div class="container">
        <h2>Edit Lead: <?php echo htmlspecialchars($lead['name']); ?></h2>
        
        <?php if($message): ?>
            <div class="message success"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <?php if($error): ?>
            <div class="message error"><?php echo $error; ?></div>
        <?php endif; ?>
        
        <div class="stage-progress">
            <strong>Current Stage: <?php echo $lead['lead_stage']; ?></strong>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $lead['probability']; ?>%;"></div>
            </div>
            <small>Win Probability: <?php echo $lead['probability']; ?>%</small>
        </div>
        
        <form method="POST">
            <div class="form-group">
                <label>Full Name *</label>
                <input type="text" name="name" value="<?php echo htmlspecialchars($lead['name']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Email</label>
                <input type="email" name="email" value="<?php echo htmlspecialchars($lead['email']); ?>">
            </div>
            
            <div class="form-group">
                <label>Phone Number *</label>
                <input type="tel" name="phone" value="<?php echo $lead['phone']; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Area *</label>
                <input type="text" name="area" value="<?php echo htmlspecialchars($lead['area']); ?>" required>
            </div>
            
            <div class="form-group">
                <label>Address</label>
                <textarea name="address" rows="3"><?php echo htmlspecialchars($lead['address']); ?></textarea>
            </div>
            
            <div class="form-group">
                <label>Priority</label>
                <select name="priority" required>
                    <option value="High" <?php echo $lead['priority'] == 'High' ? 'selected' : ''; ?>>High</option>
                    <option value="Medium" <?php echo $lead['priority'] == 'Medium' ? 'selected' : ''; ?>>Medium</option>
                    <option value="Low" <?php echo $lead['priority'] == 'Low' ? 'selected' : ''; ?>>Low</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Lead Stage</label>
                <select name="lead_stage" id="lead_stage" required>
                    <?php foreach($stages as $stage): ?>
                    <option value="<?php echo $stage['stage_name']; ?>" 
                        data-probability="<?php echo $stage['probability_percent']; ?>"
                        <?php echo $lead['lead_stage'] == $stage['stage_name'] ? 'selected' : ''; ?>>
                        <?php echo $stage['stage_name']; ?>
                    </option>
                    <?php endforeach; ?>
                </select>
            </div>
            
            <div class="form-group">
                <label>Expected Amount/Sales Volume (BDT)</label>
                <input type="number" name="expected_amount" step="0.01" value="<?php echo $lead['expected_amount']; ?>" placeholder="0.00">
            </div>
            
            <div class="form-group">
                <label>Win Probability (%)</label>
                <input type="number" name="probability" id="probability" min="0" max="100" value="<?php echo $lead['probability']; ?>" required>
            </div>
            
            <div class="form-group">
                <label>Next Follow-up Date</label>
                <input type="date" name="next_followup" value="<?php echo $lead['next_followup_date']; ?>">
            </div>
            
            <div class="form-group">
                <label>Status</label>
                <select name="status">
                    <option value="New" <?php echo $lead['status'] == 'New' ? 'selected' : ''; ?>>New</option>
                    <option value="Contacted" <?php echo $lead['status'] == 'Contacted' ? 'selected' : ''; ?>>Contacted</option>
                    <option value="Negotiation" <?php echo $lead['status'] == 'Negotiation' ? 'selected' : ''; ?>>Negotiation</option>
                    <option value="Converted" <?php echo $lead['status'] == 'Converted' ? 'selected' : ''; ?>>Converted</option>
                    <option value="Lost" <?php echo $lead['status'] == 'Lost' ? 'selected' : ''; ?>>Lost</option>
                </select>
            </div>
            
            <button type="submit">Update Lead</button>
        </form>
        
        <div class="nav-links">
            <a href="view_lead.php?id=<?php echo $lead_id; ?>">Back to Lead Details</a>
            <a href="dashboard.php">Dashboard</a>
        </div>
    </div>
    
    <script>
        // Auto-update probability when stage changes
        document.getElementById('lead_stage').addEventListener('change', function() {
            var selected = this.options[this.selectedIndex];
            var probability = selected.getAttribute('data-probability');
            if (probability) {
                document.getElementById('probability').value = probability;
            }
        });
    </script>
</body>
</html>