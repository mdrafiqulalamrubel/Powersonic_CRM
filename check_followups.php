<?php
// This file should be run daily via cron job
require_once 'config.php';

// Find leads that need follow-up today
$today = date('Y-m-d');
$leads = $pdo->prepare("
    SELECT l.*, u.id as agent_id, u.full_name as agent_name
    FROM leads l
    JOIN users u ON l.created_by = u.id
    WHERE l.next_followup_date = ?
    AND l.lead_stage NOT IN ('Won', 'Lost', 'Cancelled')
");
$leads->execute([$today]);
$followups = $leads->fetchAll();

foreach($followups as $lead) {
    // Create notification
    $message = "Follow-up reminder: Please contact {$lead['name']} today (Lead: {$lead['lead_unique_id']})";
    $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'followup_reminder', ?)");
    $notif->execute([$lead['agent_id'], $lead['id'], $message]);
    
    echo "Notification created for {$lead['agent_name']} to followup with {$lead['name']}\n";
}

echo "Done. Processed " . count($followups) . " follow-ups.\n";
?>