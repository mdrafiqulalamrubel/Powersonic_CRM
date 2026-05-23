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
    $type = $_POST['type'];
    $notes = $_POST['notes'];
    
    $stmt = $pdo->prepare("INSERT INTO communications (lead_id, communication_type, notes, created_by) VALUES (?, ?, ?, ?)");
    $stmt->execute([$lead_id, $type, $notes, $_SESSION['user_id']]);
    
    $message = "Communication logged successfully!";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Add Communication - Power Sonic CRM</title>
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
        select, textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        button {
            background: #3498db;
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
        .nav-links {
            margin-top: 20px;
            text-align: center;
        }
        .nav-links a {
            color: #3498db;
            text-decoration: none;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Add Communication for: <?php echo htmlspecialchars($lead['name']); ?></h2>
    </div>
    
    <div class="container">
        <?php if($message): ?>
            <div class="message"><?php echo $message; ?></div>
        <?php endif; ?>
        
        <form method="POST">
            <div class="form-group">
                <label>Communication Type</label>
                <select name="type" required>
                    <option value="Phone Call">Phone Call</option>
                    <option value="Site Visit">Site Visit</option>
                    <option value="Offer/Quotation">Offer/Quotation</option>
                    <option value="Email">Email</option>
                    <option value="Meeting">Meeting</option>
                </select>
            </div>
            
            <div class="form-group">
                <label>Notes / Details</label>
                <textarea name="notes" rows="5" required></textarea>
            </div>
            
            <button type="submit">Save Communication</button>
        </form>
        
        <div class="nav-links">
            <a href="view_lead.php?id=<?php echo $lead_id; ?>">Back to Lead Details</a>
        </div>
    </div>
</body>
</html>