<?php
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
}

// Apply filters
if (isset($_GET['area']) && $_GET['area'] != '') {
    $where .= (strpos($where, 'WHERE') !== false ? " AND" : " WHERE") . " area LIKE ?";
    $params[] = "%" . $_GET['area'] . "%";
}

if (isset($_GET['priority']) && $_GET['priority'] != '') {
    $where .= (strpos($where, 'WHERE') !== false ? " AND" : " WHERE") . " priority = ?";
    $params[] = $_GET['priority'];
}

$query = "SELECT * FROM leads $where ORDER BY created_at DESC";
$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Get unique areas for filter
$areas = $pdo->query("SELECT DISTINCT area FROM leads")->fetchAll();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>View Leads - Power Sonic CRM</title>
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
            max-width: 1200px;
            margin: 30px auto;
            padding: 0 20px;
        }
        .filters {
            background: white;
            padding: 20px;
            border-radius: 10px;
            margin-bottom: 20px;
        }
        .filter-form {
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        .filter-form select, .filter-form input {
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 5px;
        }
        table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        th, td {
            padding: 12px;
            text-align: left;
            border-bottom: 1px solid #ddd;
        }
        th {
            background: #34495e;
            color: white;
        }
        .priority-high {
            color: #e74c3c;
            font-weight: bold;
        }
        .priority-medium {
            color: #f39c12;
            font-weight: bold;
        }
        .priority-low {
            color: #27ae60;
            font-weight: bold;
        }
        .btn {
            padding: 5px 10px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 3px;
            font-size: 12px;
        }
        .nav-links {
            margin-top: 20px;
            text-align: center;
        }
    </style>
</head>
<body>
    <div class="header">
        <h2>Power Sonic CRM - Lead Management</h2>
        <div>Welcome, <?php echo $_SESSION['full_name']; ?> | <a href="logout.php" style="color: white;">Logout</a></div>
    </div>
    
    <div class="container">
        <div class="filters">
            <form method="GET" class="filter-form">
                <input type="text" name="area" placeholder="Filter by Area" value="<?php echo $_GET['area'] ?? ''; ?>">
                <select name="priority">
                    <option value="">All Priorities</option>
                    <option value="High" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'High') ? 'selected' : ''; ?>>High</option>
                    <option value="Medium" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'Medium') ? 'selected' : ''; ?>>Medium</option>
                    <option value="Low" <?php echo (isset($_GET['priority']) && $_GET['priority'] == 'Low') ? 'selected' : ''; ?>>Low</option>
                </select>
                <button type="submit" class="btn">Filter</button>
                <a href="view_leads.php" class="btn">Reset</a>
            </form>
        </div>
        
        <table>
            <thead>
                <tr>
                    <th>Lead ID</th>
                    <th>Name</th>
                    <th>Phone</th>
                    <th>Area</th>
                    <th>Priority</th>
                    <th>Status</th>
                    <th>Created</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php foreach($leads as $lead): ?>
                <tr>
                    <td><?php echo $lead['lead_unique_id']; ?></td>
                    <td><?php echo htmlspecialchars($lead['name']); ?></td>
                    <td><?php echo $lead['phone']; ?></td>
                    <td><?php echo htmlspecialchars($lead['area']); ?></td>
                    <td class="priority-<?php echo strtolower($lead['priority']); ?>"><?php echo $lead['priority']; ?></td>
                    <td><?php echo $lead['status']; ?></td>
                    <td><?php echo date('Y-m-d', strtotime($lead['created_at'])); ?></td>
                    <td>
                        <a href="view_lead.php?id=<?php echo $lead['id']; ?>" class="btn">View</a>
                        <?php if(isAdmin()): ?>
                        <a href="edit_lead.php?id=<?php echo $lead['id']; ?>" class="btn">Edit</a>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
        
        <div class="nav-links">
            <a href="add_lead.php">Add New Lead</a>
            <?php if(isAdmin()): ?>
            | <a href="dashboard.php">Admin Dashboard</a>
            <?php else: ?>
            | <a href="agent_dashboard.php">Agent Dashboard</a>
            <?php endif; ?>
        </div>
    </div>
</body>
</html>

<?php 
require_once 'includes/footer.php';
ob_end_flush();
?>