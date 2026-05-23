<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$lead_id = $_GET['lead_id'] ?? 0;
$new_stage = $_GET['stage'] ?? '';

if ($lead_id && $new_stage) {
    // Get current lead info
    $lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
    $lead->execute([$lead_id]);
    $lead = $lead->fetch();
    
    if ($lead) {
        // Get stage probability
        $stage_info = $pdo->prepare("SELECT probability_percent FROM lead_stages WHERE stage_name = ?");
        $stage_info->execute([$new_stage]);
        $probability = $stage_info->fetchColumn();
        
        // Update lead stage
        $update = $pdo->prepare("UPDATE leads SET lead_stage = ?, probability = ?, updated_at = NOW() WHERE id = ?");
        $update->execute([$new_stage, $probability ?: 0, $lead_id]);
        
        // Create notification
        $message = "Lead {$lead['name']} moved to {$new_stage} stage";
        $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'stage_change', ?)");
        $notif->execute([$lead['created_by'], $lead_id, $message]);
        
        // If won, update performance
        if ($new_stage == 'Won') {
            $pdo->prepare("UPDATE leads SET won_date = NOW(), status = 'Converted' WHERE id = ?")->execute([$lead_id]);
            
            // Update agent performance
            $perf = $pdo->prepare("INSERT INTO agent_performance (agent_id, report_month, won_leads, total_amount_won) 
                VALUES (?, DATE_FORMAT(NOW(), '%Y-%m-01'), 1, ?)
                ON DUPLICATE KEY UPDATE won_leads = won_leads + 1, total_amount_won = total_amount_won + ?");
            $perf->execute([$lead['created_by'], $lead['expected_amount'], $lead['expected_amount']]);
        }
        
        $_SESSION['flash_message'] = "Lead stage updated to {$new_stage}!";
    }
}

redirect("view_lead.php?id={$lead_id}");
?>