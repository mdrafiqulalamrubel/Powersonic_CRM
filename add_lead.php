<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

require_once 'includes/header.php';

$message = '';
$error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = $_POST['name'];
    $email = $_POST['email'] ?? '';
    $phone = $_POST['phone'];
    $area = $_POST['area'];
    $address = $_POST['address'] ?? '';
    $priority = $_POST['priority'];
    $lead_stage = $_POST['lead_stage'] ?? 'Lead';
    $expected_amount = $_POST['expected_amount'] ?? 0;
    $next_followup = $_POST['next_followup'] ?? null;
    
    $lead_unique_id = generateUniqueLeadId();
    
    try {
        $stmt = $pdo->prepare("INSERT INTO leads (lead_unique_id, name, email, phone, area, address, priority, lead_stage, expected_amount, next_followup_date, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?)");
        $stmt->execute([$lead_unique_id, $name, $email, $phone, $area, $address, $priority, $lead_stage, $expected_amount, $next_followup, $_SESSION['user_id']]);
        
        $lead_id = $pdo->lastInsertId();
        
        // Handle file uploads
        for($i = 1; $i <= 3; $i++) {
            if(isset($_FILES["photo$i"]) && $_FILES["photo$i"]["error"] == 0) {
                $photo_path = uploadPhoto($_FILES["photo$i"], $lead_id);
                if($photo_path) {
                    $stmt = $pdo->prepare("INSERT INTO lead_photos (lead_id, photo_path) VALUES (?, ?)");
                    $stmt->execute([$lead_id, $photo_path]);
                }
            }
        }
        
        $message = "Lead created successfully! Lead ID: $lead_unique_id";
        
        // Create notification for next followup
        if ($next_followup && $next_followup > date('Y-m-d')) {
            $notif_msg = "Follow-up required for $name on " . date('Y-m-d', strtotime($next_followup));
            $notif = $pdo->prepare("INSERT INTO notifications (user_id, lead_id, notification_type, message) VALUES (?, ?, 'followup', ?)");
            $notif->execute([$_SESSION['user_id'], $lead_id, $notif_msg]);
        }
        
    } catch(Exception $e) {
        $error = "Error: " . $e->getMessage();
    }
}
?>

<div style="max-width: 800px; margin: 0 auto; background: white; border-radius: 10px; padding: 30px;">
    <h2 style="margin-bottom: 20px;">Add New Lead</h2>
    
    <?php if($message): ?>
        <div style="background: #d4edda; color: #155724; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $message; ?>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div style="background: #f8d7da; color: #721c24; padding: 10px; border-radius: 5px; margin-bottom: 20px;">
            <?php echo $error; ?>
        </div>
    <?php endif; ?>
    
    <form method="POST" enctype="multipart/form-data">
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Name *</label>
            <input type="text" name="name" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Email</label>
            <input type="email" name="email" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Phone Number *</label>
            <input type="tel" name="phone" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Area *</label>
            <input type="text" name="area" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Full Address</label>
            <textarea name="address" rows="3" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;"></textarea>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Priority Grade *</label>
            <select name="priority" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="High">High - Likely to convert within 3 months</option>
                <option value="Medium" selected>Medium - Potential within 3-6 months</option>
                <option value="Low">Low - Long term prospect</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Lead Stage</label>
            <select name="lead_stage" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
                <option value="Lead">Lead - Initial Contact</option>
                <option value="Pipeline">Pipeline - Qualified Interest</option>
                <option value="Qualified">Qualified - Ready for Discussion</option>
                <option value="Discussion Ongoing">Discussion Ongoing - Active Negotiation</option>
                <option value="Quotation Submitted">Quotation Submitted - Proposal Sent</option>
                <option value="Final Negotiation">Final Negotiation - Closing Stage</option>
            </select>
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Expected Sales Amount (BDT)</label>
            <input type="number" name="expected_amount" step="0.01" placeholder="0.00" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Next Follow-up Date</label>
            <input type="date" name="next_followup" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 5px;">
        </div>
        
        <div style="margin-bottom: 20px;">
            <label style="display: block; margin-bottom: 5px; font-weight: 600;">Site Photos (Max 3 photos)</label>
            <input type="file" name="photo1" accept="image/*" style="margin-bottom: 10px; display: block;">
            <input type="file" name="photo2" accept="image/*" style="margin-bottom: 10px; display: block;">
            <input type="file" name="photo3" accept="image/*" style="display: block;">
        </div>
        
        <button type="submit" style="background: #27ae60; color: white; padding: 12px 30px; border: none; border-radius: 5px; cursor: pointer; font-size: 16px;">
            Submit Lead
        </button>
    </form>
</div>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>