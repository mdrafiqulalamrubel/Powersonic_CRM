<?php
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

$lead_id = $_GET['lead_id'] ?? 0;
$lead = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
$lead->execute([$lead_id]);
$lead = $lead->fetch();

if (!$lead) {
    redirect('view_leads.php');
}

$message = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $title = $_POST['title'];
    $description = $_POST['description'];
    $due_date = $_POST['due_date'];
    $reminder_days = $_POST['reminder_days'];
    
    $stmt = $pdo->prepare("INSERT INTO tasks (lead_id, task_title, description, due_date, reminder_days, assigned_to, created_by) VALUES (?, ?, ?, ?, ?, ?, ?)");
    $stmt->execute([$lead_id, $title, $description, $due_date, $reminder_days, $_SESSION['user_id'], $_SESSION['user_id']]);
    
    $message = "Task scheduled successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Schedule Task - Power Sonic CRM</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            background: #f5f5f5;
        }
        .header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
        }
        .container {
            max-width: 600px;
            margin: 30px auto;
            background: white;
            padding: 30px;
            border-radius: 10px;
        }
        .form-group {
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            font-weight: 600;
        }
        input, select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background: #27ae60;
            color: white;
            padding: 10px 20px;
            border: none;
            border-radius: 5px;
            cursor: pointer;
        }
        .message {
            background: #d4edda;
            padding: 10px;
            border-radius: 5px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Schedule Follow-up for: <?php echo htmlspecialchars($lead['name']); ?></h2>
    </div>
    
    <div class="container">
        <?php if($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Task Title</label>
                <input type="text" name="title" required placeholder="e.g., Follow-up call, Site visit">
            </div>
            
            <div class="form-group">
                <label>Description</label>
                <textarea name="description" rows="3"></textarea>
            </div>
            
            <div class="form-group">
                <label>Due Date</label>
                <input type="date" name="due_date" required>
            </div>
            
            <div class="form-group">
                <label>Reminder (days before)</label>
                <select name="reminder_days">
                    <option value="1">1 day before</option>
                    <option value="2">2 days before</option>
                    <option value="3">3 days before</option>
                    <option value="7">1 week before</option>
                </select>
            </div>
            
            <button type="submit">Schedule Task</button>
        </form>
        
        <div style="margin-top: 20px; text-align: center;">
            <a href="view_lead.php?id=<?php echo $lead_id; ?>">Back to Lead Details</a>
        </div>
    </div>
</body>
</html>