<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

require_once 'includes/header.php';

// Build query based on user role
$where = "";
$params = [];

if (isFieldAgent()) {
    $where = "WHERE created_by = ?";
    $params[] = $_SESSION['user_id'];
} else {
    $where = "WHERE 1=1";
}

// Apply filters
if (isset($_GET['area']) && $_GET['area'] != '') {
    $where .= " AND area LIKE ?";
    $params[] = "%" . $_GET['area'] . "%";
}

if (isset($_GET['priority']) && $_GET['priority'] != '') {
    $where .= " AND priority = ?";
    $params[] = $_GET['priority'];
}

if (isset($_GET['district']) && $_GET['district'] != '') {
    $where .= " AND district = ?";
    $params[] = $_GET['district'];
}

if (isset($_GET['lead_stage']) && $_GET['lead_stage'] != '') {
    $where .= " AND lead_stage = ?";
    $params[] = $_GET['lead_stage'];
}

if (isset($_GET['search']) && $_GET['search'] != '') {
    $search = $_GET['search'];
    $where .= " AND (name LIKE ? OR phone LIKE ? OR user_custom_id LIKE ? OR lead_unique_id LIKE ?)";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
    $params[] = "%$search%";
}

$query = "SELECT * FROM leads $where ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Get unique values for filters
$districts = $pdo->query("SELECT DISTINCT district FROM leads WHERE district IS NOT NULL AND district != ''")->fetchAll();
$lead_stages = $pdo->query("SELECT DISTINCT lead_stage FROM leads WHERE lead_stage IS NOT NULL")->fetchAll();
?>

<style>
    .filter-section {
        background: white;
        border-radius: 10px;
        padding: 20px;
        margin-bottom: 20px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .filter-form {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        align-items: end;
    }
    
    .filter-group {
        display: flex;
        flex-direction: column;
    }
    
    .filter-group label {
        font-size: 12px;
        font-weight: 600;
        color: #666;
        margin-bottom: 5px;
    }
    
    .filter-group input, 
    .filter-group select {
        padding: 8px 12px;
        border: 1px solid #ddd;
        border-radius: 5px;
        font-size: 14px;
    }
    
    .filter-buttons {
        display: flex;
        gap: 10px;
    }
    
    .btn-filter {
        background: #3498db;
        color: white;
        border: none;
        padding: 8px 20px;
        border-radius: 5px;
        cursor: pointer;
    }
    
    .btn-reset {
        background: #95a5a6;
        color: white;
        text-decoration: none;
        padding: 8px 20px;
        border-radius: 5px;
        display: inline-block;
        text-align: center;
    }
    
    .lead-stats {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(180px, 1fr));
        gap: 15px;
        margin-bottom: 20px;
    }
    
    .stat-badge {
        background: white;
        padding: 15px;
        border-radius: 10px;
        text-align: center;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .stat-badge .count {
        font-size: 24px;
        font-weight: bold;
    }
    
    .stat-badge .label {
        color: #666;
        font-size: 12px;
        margin-top: 5px;
    }
    
    .table-container {
        overflow-x: auto;
        background: white;
        border-radius: 10px;
        box-shadow: 0 2px 5px rgba(0,0,0,0.1);
    }
    
    .data-table {
        width: 100%;
        border-collapse: collapse;
        min-width: 1000px;
    }
    
    .data-table th {
        background: #34495e;
        color: white;
        padding: 12px;
        text-align: left;
        font-size: 13px;
    }
    
    .data-table td {
        padding: 12px;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }
    
    .data-table tr:hover {
        background: #f8f9fa;
    }
    
    .priority-high { color: #e74c3c; font-weight: bold; }
    .priority-medium { color: #f39c12; font-weight: bold; }
    .priority-low { color: #27ae60; font-weight: bold; }
    
    .stage-badge {
        display: inline-block;
        padding: 4px 10px;
        border-radius: 20px;
        font-size: 11px;
        font-weight: bold;
    }
    
    .stage-Lead { background: #e8e8e8; color: #666; }
    .stage-Pipeline { background: #d4edda; color: #155724; }
    .stage-Qualified { background: #d1ecf1; color: #0c5460; }
    .stage-Discussion { background: #fff3cd; color: #856404; }
    .stage-Quotation { background: #cce5ff; color: #004085; }
    .stage-Final { background: #f8d7da; color: #721c24; }
    .stage-Won { background: #27ae60; color: white; }
    .stage-Lost { background: #e74c3c; color: white; }
    
    .btn-sm {
        padding: 4px 8px;
        font-size: 11px;
        margin: 2px;
        display: inline-flex;
        align-items: center;
        gap: 3px;
    }
    
    .export-buttons {
        margin-bottom: 15px;
        display: flex;
        gap: 10px;
        justify-content: flex-end;
        flex-wrap: wrap;
    }
    
    .btn-export {
        background: #27ae60;
        color: white;
        padding: 8px 15px;
        text-decoration: none;
        border-radius: 5px;
        font-size: 12px;
        display: inline-flex;
        align-items: center;
        gap: 5px;
        transition: all 0.3s;
    }
    
    .btn-export:hover {
        transform: translateY(-2px);
        box-shadow: 0 2px 8px rgba(0,0,0,0.2);
    }
    
    .btn {
        display: inline-flex;
        align-items: center;
        gap: 5px;
        padding: 8px 15px;
        background: #3498db;
        color: white;
        text-decoration: none;
        border-radius: 5px;
        font-size: 13px;
        transition: all 0.3s;
    }
</style>

<!-- Statistics Section -->
<div class="lead-stats">
    <div class="stat-badge">
        <div class="count"><?php echo count($leads); ?></div>
        <div class="label">Total Leads</div>
    </div>
    <div class="stat-badge">
        <div class="count" style="color: #e74c3c;">
            <?php 
            $highCount = 0;
            foreach($leads as $l) {
                if($l['priority'] == 'High') $highCount++;
            }
            echo $highCount;
            ?>
        </div>
        <div class="label">High Priority</div>
    </div>
    <div class="stat-badge">
        <div class="count" style="color: #27ae60;">
            <?php 
            $wonCount = 0;
            foreach($leads as $l) {
                if($l['lead_stage'] == 'Won') $wonCount++;
            }
            echo $wonCount;
            ?>
        </div>
        <div class="label">Won Leads</div>
    </div>
    <div class="stat-badge">
        <div class="count" style="color: #f39c12;">
            <?php 
            $totalAmount = 0;
            foreach($leads as $l) {
                $totalAmount += ($l['expected_amount'] ?? 0);
            }
            echo number_format($totalAmount / 1000, 0);
            ?>K
        </div>
        <div class="label">Pipeline Value (BDT)</div>
    </div>
</div>

<!-- Export Options -->
<div class="export-buttons">
    <a href="export_leads.php?format=csv&<?php echo http_build_query($_GET); ?>" class="btn-export" style="background: #27ae60;">
        <i class="fas fa-file-csv"></i> CSV
    </a>
    <a href="export_leads.php?format=excel&<?php echo http_build_query($_GET); ?>" class="btn-export" style="background: #217346;">
        <i class="fas fa-file-excel"></i> Excel
    </a>
    <a href="export_leads.php?format=json&<?php echo http_build_query($_GET); ?>" class="btn-export" style="background: #f39c12;">
        <i class="fas fa-code"></i> JSON
    </a>
    <a href="export_leads.php?format=pdf&<?php echo http_build_query($_GET); ?>" class="btn-export" style="background: #e74c3c;">
        <i class="fas fa-file-pdf"></i> PDF
    </a>
</div>

<!-- Filters -->
<div class="filter-section">
    <form method="GET" class="filter-form">
        <div class="filter-group">
            <label>🔍 Search</label>
            <input type="text" name="search" placeholder="Name, Phone, ID..." value="<?php echo htmlspecialchars($_GET['search'] ?? ''); ?>">
        </div>
        
        <div class="filter-group">
            <label>📍 District</label>
            <select name="district">
                <option value="">All Districts</option>
                <?php foreach($districts as $d): ?>
                    <option value="<?php echo htmlspecialchars($d['district']); ?>" 
                        <?php echo (isset($_GET['district']) && $_GET['district'] == $d['district']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($d['district']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>⭐ Priority</label>
            <select name="priority">
                <option value="">All Priorities</option>
                <option value="High" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'High') ? 'selected' : ''; ?>>High</option>
                <option value="Medium" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                <option value="Low" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'Low') ? 'selected' : ''; ?>>Low</option>
            </select>
        </div>
        
        <div class="filter-group">
            <label>🔄 Lead Stage</label>
            <select name="lead_stage">
                <option value="">All Stages</option>
                <?php foreach($lead_stages as $stage): ?>
                    <option value="<?php echo htmlspecialchars($stage['lead_stage']); ?>" 
                        <?php echo (isset($_GET['lead_stage']) && $_GET['lead_stage'] == $stage['lead_stage']) ? 'selected' : ''; ?>>
                        <?php echo htmlspecialchars($stage['lead_stage']); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div class="filter-group">
            <label>📍 Area</label>
            <input type="text" name="area" placeholder="Area name..." value="<?php echo htmlspecialchars($_GET['area'] ?? ''); ?>">
        </div>
        
        <div class="filter-buttons">
            <button type="submit" class="btn-filter">
                <i class="fas fa-filter"></i> Apply Filters
            </button>
            <a href="view_leads.php" class="btn-reset">
                <i class="fas fa-undo"></i> Reset
            </a>
        </div>
    </form>
</div>

<!-- Leads Table -->
<div class="table-container">
    <table class="data-table">
        <thead>
            <tr>
                <th>User ID</th>
                <th>Lead ID</th>
                <th>Name</th>
                <th>Phone</th>
                <th>District</th>
                <th>Priority</th>
                <th>Stage</th>
                <th>Amount</th>
                <th>Next Follow-up</th>
                <th>Created</th>
                <th>Actions</th>
            </tr>
        </thead>
        <tbody>
            <?php if(count($leads) > 0): ?>
                <?php foreach($leads as $lead): ?>
                <tr>
                    <td>
                        <small style="font-family: monospace;"><?php echo $lead['user_custom_id'] ?? 'N/A'; ?></small>
                    </td>
                    <td>
                        <small style="font-family: monospace;"><?php echo $lead['lead_unique_id']; ?></small>
                    </td>
                    <td>
                        <strong><?php echo htmlspecialchars($lead['name']); ?></strong>
                    </td>
                    <td><?php echo $lead['phone']; ?></td>
                    <td><?php echo htmlspecialchars($lead['district'] ?? 'N/A'); ?></td>
                    <td class="priority-<?php echo strtolower($lead['priority']); ?>">
                        <?php echo $lead['priority']; ?>
                    </td>
                    <td>
                        <span class="stage-badge stage-<?php echo str_replace(' ', '', $lead['lead_stage'] ?? 'Lead'); ?>">
                            <?php echo $lead['lead_stage'] ?? 'Lead'; ?>
                        </span>
                    </td>
                    <td>
                        <?php echo ($lead['expected_amount'] ?? 0) > 0 ? number_format($lead['expected_amount'], 0) : '-'; ?>
                    </td>
                    <td>
                        <?php 
                        if($lead['next_followup_date']) {
                            $followup = date('Y-m-d', strtotime($lead['next_followup_date']));
                            $today = date('Y-m-d');
                            if($followup == $today) {
                                echo '<span style="color: #e74c3c;"><i class="fas fa-bell"></i> Today</span>';
                            } elseif($followup < $today) {
                                echo '<span style="color: #e74c3c;">Overdue</span>';
                            } else {
                                echo $followup;
                            }
                        } else {
                            echo '-';
                        }
                        ?>
                    </td>
                    <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                    <td>
                        <div style="display: flex; gap: 5px; flex-wrap: wrap;">
                            <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn-sm" style="background: #3498db; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none;" title="View Details">
                                <i class="fas fa-eye"></i>
                            </a>
                            <?php if(isAdmin()): ?>
                            <a href="edit_lead.php?id=<?php echo $lead['id']; ?>" class="btn-sm" style="background: #27ae60; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none;" title="Edit Lead">
                                <i class="fas fa-edit"></i>
                            </a>
                            <?php endif; ?>
                            <a href="add_communication.php?lead_id=<?php echo $lead['id']; ?>" class="btn-sm" style="background: #f39c12; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none;" title="Add Communication">
                                <i class="fas fa-comment"></i>
                            </a>
                            <a href="schedule_task.php?lead_id=<?php echo $lead['id']; ?>" class="btn-sm" style="background: #9b59b6; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none;" title="Schedule Follow-up">
                                <i class="fas fa-calendar"></i>
                            </a>
                            <?php if($lead['google_map_link']): ?>
                            <a href="<?php echo $lead['google_map_link']; ?>" target="_blank" class="btn-sm" style="background: #e74c3c; color: white; padding: 4px 8px; border-radius: 3px; text-decoration: none;" title="View on Map">
                                <i class="fas fa-map-marker-alt"></i>
                            </a>
                            <?php endif; ?>
                        </div>
                    </td>
                </tr>
                <?php endforeach; ?>
            <?php else: ?>
                <tr>
                    <td colspan="11" style="text-align: center; padding: 40px;">
                        <i class="fas fa-inbox" style="font-size: 48px; color: #ccc;"></i>
                        <p style="margin-top: 15px;">No leads found matching your criteria.</p>
                        <a href="add_lead.php" class="btn" style="margin-top: 10px;">+ Add New Lead</a>
                    </td>
                </tr>
            <?php endif; ?>
        </tbody>
    </table>
</div>

<!-- Pagination Info -->
<?php if(count($leads) > 20): ?>
<div style="margin-top: 20px; text-align: center;">
    <small>Showing <?php echo count($leads); ?> total leads. Use filters to narrow down results.</small>
</div>
<?php endif; ?>

<?php 
require_once 'includes/footer.php'; 
ob_end_flush();
?>