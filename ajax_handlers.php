<?php
/**
 * AJAX Handlers for Power Sonic CRM
 */

require_once 'config.php';

if (!isLoggedIn()) {
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Unauthorized']);
    exit();
}

header('Content-Type: application/json');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// Handle POST data from JSON
$input = json_decode(file_get_contents('php://input'), true);
if ($input && isset($input['action'])) {
    $action = $input['action'];
    $_POST = array_merge($_POST, $input);
}

switch ($action) {
    
    // ============================================
    // GET ALL LEADS FOR KANBAN
    // ============================================
    case 'get_leads':
        try {
            if (isAdmin()) {
                $query = "SELECT 
                            l.id,
                            l.name,
                            l.phone,
                            l.email,
                            l.priority,
                            l.lead_stage,
                            l.expected_amount,
                            l.district,
                            l.address,
                            l.created_at,
                            u.full_name as created_by_name
                          FROM leads l 
                          LEFT JOIN users u ON l.created_by = u.id 
                          ORDER BY l.created_at DESC";
                $stmt = $pdo->query($query);
            } else {
                $query = "SELECT 
                            l.id,
                            l.name,
                            l.phone,
                            l.email,
                            l.priority,
                            l.lead_stage,
                            l.expected_amount,
                            l.district,
                            l.address,
                            l.created_at,
                            u.full_name as created_by_name
                          FROM leads l 
                          LEFT JOIN users u ON l.created_by = u.id 
                          WHERE l.created_by = ? 
                          ORDER BY l.created_at DESC";
                $stmt = $pdo->prepare($query);
                $stmt->execute([$_SESSION['user_id']]);
            }
            
            $leads = $stmt->fetchAll(PDO::FETCH_ASSOC);
            
            // Set default values for NULL fields
            foreach ($leads as &$lead) {
                if (empty($lead['lead_stage'])) {
                    $lead['lead_stage'] = 'New Lead';
                }
                if (empty($lead['priority'])) {
                    $lead['priority'] = 'Medium';
                }
                if (empty($lead['expected_amount'])) {
                    $lead['expected_amount'] = 0;
                }
            }
            
            echo json_encode($leads);
        } catch (Exception $e) {
            echo json_encode(['error' => $e->getMessage()]);
        }
        break;
    
    // ============================================
    // GET SINGLE LEAD
    // ============================================
    case 'get_lead':
        $lead_id = $_GET['id'] ?? 0;
        
        if (!$lead_id) {
            echo json_encode(['success' => false, 'error' => 'Lead ID required']);
            break;
        }
        
        $stmt = $pdo->prepare("SELECT * FROM leads WHERE id = ?");
        $stmt->execute([$lead_id]);
        $lead = $stmt->fetch(PDO::FETCH_ASSOC);
        
        if ($lead) {
            echo json_encode($lead);
        } else {
            echo json_encode(['success' => false, 'error' => 'Lead not found']);
        }
        break;
    
    // ============================================
    // UPDATE LEAD STAGE (Drag & Drop)
    // ============================================
    case 'update_lead_stage':
        $lead_id = $_POST['lead_id'] ?? 0;
        $stage = $_POST['stage'] ?? '';
        
        $allowedStages = ['New Lead', 'Contacted', 'Qualified', 'Proposal', 'Negotiation', 'Won', 'Lost'];
        
        if (!$lead_id || !$stage || !in_array($stage, $allowedStages)) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE leads SET lead_stage = ?, updated_at = NOW() WHERE id = ?");
            $stmt->execute([$stage, $lead_id]);
            
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
    
    // ============================================
    // DELETE LEAD
    // ============================================
    case 'delete_lead':
        $lead_id = $_GET['id'] ?? 0;
        
        if (!$lead_id) {
            echo json_encode(['success' => false, 'error' => 'Lead ID required']);
            break;
        }
        
        // Check permission
        $checkStmt = $pdo->prepare("SELECT created_by FROM leads WHERE id = ?");
        $checkStmt->execute([$lead_id]);
        $lead = $checkStmt->fetch();
        
        if (!$lead) {
            echo json_encode(['success' => false, 'error' => 'Lead not found']);
            break;
        }
        
        if (!isAdmin() && $lead['created_by'] != $_SESSION['user_id']) {
            echo json_encode(['success' => false, 'error' => 'Permission denied']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("DELETE FROM leads WHERE id = ?");
            $stmt->execute([$lead_id]);
            echo json_encode(['success' => true]);
        } catch (PDOException $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
    
    // ============================================
    // DASHBOARD STATS
    // ============================================
    case 'dashboard_stats':
        $user_id = $_SESSION['user_id'];
        $is_admin = isAdmin();
        
        $where = $is_admin ? "1=1" : "created_by = $user_id";
        
        $total_leads = $pdo->query("SELECT COUNT(*) FROM leads WHERE $where")->fetchColumn();
        $won_deals = $pdo->query("SELECT COUNT(*) FROM leads WHERE $where AND lead_stage = 'Won'")->fetchColumn();
        $expected_revenue = $pdo->query("SELECT COALESCE(SUM(expected_amount), 0) FROM leads WHERE $where")->fetchColumn();
        $conversion_rate = $total_leads > 0 ? round(($won_deals / $total_leads) * 100, 2) : 0;
        
        // Leads by stage
        $stageStmt = $pdo->query("SELECT COALESCE(lead_stage, 'New Lead') as lead_stage, COUNT(*) as count FROM leads WHERE $where GROUP BY lead_stage");
        $stages = [];
        $stageCounts = [];
        while ($row = $stageStmt->fetch()) {
            $stages[] = $row['lead_stage'];
            $stageCounts[] = $row['count'];
        }
        
        echo json_encode([
            'total_leads' => $total_leads,
            'won_deals' => $won_deals,
            'conversion_rate' => $conversion_rate,
            'expected_revenue' => $expected_revenue,
            'stages' => $stages,
            'stage_counts' => $stageCounts
        ]);
        break;
    
    // ============================================
    // RECENT LEADS FOR DASHBOARD
    // ============================================
    case 'recent_leads':
        $limit = min($_GET['limit'] ?? 5, 20);
        
        if (isAdmin()) {
            $stmt = $pdo->prepare("SELECT id, name, phone, lead_stage, priority, created_at FROM leads ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$limit]);
        } else {
            $stmt = $pdo->prepare("SELECT id, name, phone, lead_stage, priority, created_at FROM leads WHERE created_by = ? ORDER BY created_at DESC LIMIT ?");
            $stmt->execute([$_SESSION['user_id'], $limit]);
        }
        
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;
    
    // ============================================
    // GET TASKS
    // ============================================
    case 'get_tasks':
        try {
            $stmt = $pdo->prepare("SELECT * FROM tasks WHERE assigned_to = ? AND status != 'Completed' ORDER BY due_date ASC LIMIT 10");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;
    
    // ============================================
    // ADD TASK
    // ============================================
    case 'add_task':
        $title = $_POST['title'] ?? '';
        $lead_id = $_POST['lead_id'] ?? null;
        $due_date = $_POST['due_date'] ?? date('Y-m-d', strtotime('+1 day'));
        
        if (!$title) {
            echo json_encode(['success' => false, 'error' => 'Task title required']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("INSERT INTO tasks (title, lead_id, assigned_to, assigned_by, due_date, status, created_at) VALUES (?, ?, ?, ?, ?, 'Pending', NOW())");
            $stmt->execute([$title, $lead_id, $_SESSION['user_id'], $_SESSION['user_id'], $due_date]);
            echo json_encode(['success' => true, 'task_id' => $pdo->lastInsertId()]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
    
    // ============================================
    // UPDATE TASK
    // ============================================
    case 'update_task':
        $task_id = $_POST['task_id'] ?? 0;
        $status = $_POST['status'] ?? '';
        
        $allowedStatus = ['Pending', 'In Progress', 'Completed', 'Cancelled'];
        
        if (!$task_id || !in_array($status, $allowedStatus)) {
            echo json_encode(['success' => false, 'error' => 'Invalid parameters']);
            break;
        }
        
        try {
            $stmt = $pdo->prepare("UPDATE tasks SET status = ? WHERE id = ? AND assigned_to = ?");
            $stmt->execute([$status, $task_id, $_SESSION['user_id']]);
            echo json_encode(['success' => true]);
        } catch (Exception $e) {
            echo json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        break;
    
    // ============================================
    // GET NOTIFICATIONS
    // ============================================
    case 'get_notifications':
        try {
            $stmt = $pdo->prepare("SELECT * FROM notifications WHERE user_id = ? ORDER BY created_at DESC LIMIT 10");
            $stmt->execute([$_SESSION['user_id']]);
            echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        } catch (Exception $e) {
            echo json_encode([]);
        }
        break;
    
    // ============================================
    // DEFAULT
    // ============================================
    default:
        echo json_encode(['success' => false, 'error' => 'Unknown action: ' . $action]);
        break;
}
?>