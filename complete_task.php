<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$task_id = $_GET['id'] ?? 0;
$lead_id = $_GET['lead_id'] ?? 0;

if ($task_id) {
    // Update task status
    $stmt = $pdo->prepare("UPDATE tasks SET status = 'Completed' WHERE id = ? AND assigned_to = ?");
    $stmt->execute([$task_id, $_SESSION['user_id']]);
    
    // Create notification
    $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'task_completed', 'Task completed successfully')");
    $notif->execute([$_SESSION['user_id'], $lead_id]);
    
    $_SESSION['flash_message'] = "Task marked as completed!";
}

// Redirect back to dashboard
if (isFieldAgent()) {
    redirect('agent_dashboard.php');
} else {
    redirect('dashboard.php');
}
?>