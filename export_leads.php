<?php
ob_start();
require_once 'config.php';

if (!isLoggedIn()) {
    redirect('index.php');
}

// Check if user has permission to export (Admin only or allow agents to export their own)
if (!isAdmin() && !isFieldAgent()) {
    redirect('index.php');
}

// Get parameters
$format = $_GET['format'] ?? 'csv'; // csv or excel
$lead_id = $_GET['lead_id'] ?? 0; // For single lead export

// Build query based on filters (same as view_leads.php)
$where = "";
$params = [];

if (isFieldAgent()) {
    $where = "WHERE created_by = ?";
    $params[] = $_SESSION['user_id'];
} else {
    $where = "WHERE 1=1";
}

// Apply filters from URL
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

// Single lead export
if ($lead_id > 0) {
    $where = "WHERE id = ?";
    $params = [$lead_id];
}

// Get the data
$query = "SELECT 
    l.id,
    l.user_custom_id,
    l.lead_unique_id,
    l.name,
    l.email,
    l.phone,
    l.area,
    l.address,
    l.district,
    l.police_station,
    l.post_office,
    l.country,
    l.priority,
    l.lead_stage,
    l.status,
    l.expected_amount,
    l.probability,
    l.next_followup_date,
    l.last_contact_date,
    l.created_at,
    l.won_date,
    l.latitude,
    l.longitude,
    u.full_name as created_by_name
FROM leads l
LEFT JOIN users u ON l.created_by = u.id
$where
ORDER BY l.created_at DESC";

$stmt = $pdo->prepare($query);
$stmt->execute($params);
$leads = $stmt->fetchAll();

// Set filename
$filename = 'leads_export_' . date('Y-m-d_H-i-s');

// Export to CSV
if ($format == 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '.csv"');
    
    // Create output stream
    $output = fopen('php://output', 'w');
    
    // Add UTF-8 BOM for Excel compatibility
    fprintf($output, chr(0xEF).chr(0xBB).chr(0xBF));
    
    // Headers
    $headers = [
        'ID', 'User Custom ID', 'Lead ID', 'Name', 'Email', 'Phone', 
        'Area', 'Address', 'District', 'Police Station', 'Post Office', 
        'Country', 'Priority', 'Lead Stage', 'Status', 'Expected Amount (BDT)', 
        'Probability (%)', 'Next Follow-up', 'Last Contact', 'Created Date', 
        'Won Date', 'Latitude', 'Longitude', 'Created By'
    ];
    fputcsv($output, $headers);
    
    // Data rows
    foreach ($leads as $lead) {
        $row = [
            $lead['id'],
            $lead['user_custom_id'],
            $lead['lead_unique_id'],
            $lead['name'],
            $lead['email'],
            $lead['phone'],
            $lead['area'],
            $lead['address'],
            $lead['district'],
            $lead['police_station'],
            $lead['post_office'],
            $lead['country'],
            $lead['priority'],
            $lead['lead_stage'],
            $lead['status'],
            $lead['expected_amount'],
            $lead['probability'],
            $lead['next_followup_date'],
            $lead['last_contact_date'],
            $lead['created_at'],
            $lead['won_date'],
            $lead['latitude'],
            $lead['longitude'],
            $lead['created_by_name']
        ];
        fputcsv($output, $row);
    }
    
    fclose($output);
    exit();
}

// Export to Excel (HTML format)
elseif ($format == 'excel') {
    header('Content-Type: application/vnd.ms-excel');
    header('Content-Disposition: attachment; filename="' . $filename . '.xls"');
    
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Leads Export</title>';
    echo '<style>';
    echo 'th { background-color: #34495e; color: white; padding: 8px; }';
    echo 'td { padding: 6px; border: 1px solid #ddd; }';
    echo 'table { border-collapse: collapse; width: 100%; }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<h2>Power Sonic CRM - Leads Export</h2>';
    echo '<p>Export Date: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>Total Records: ' . count($leads) . '</p>';
    echo '<table border="1">';
    
    // Headers
    echo '<tr>';
    echo '<th>ID</th>';
    echo '<th>User Custom ID</th>';
    echo '<th>Lead ID</th>';
    echo '<th>Name</th>';
    echo '<th>Email</th>';
    echo '<th>Phone</th>';
    echo '<th>Area</th>';
    echo '<th>Address</th>';
    echo '<th>District</th>';
    echo '<th>Police Station</th>';
    echo '<th>Post Office</th>';
    echo '<th>Country</th>';
    echo '<th>Priority</th>';
    echo '<th>Lead Stage</th>';
    echo '<th>Status</th>';
    echo '<th>Expected Amount</th>';
    echo '<th>Probability</th>';
    echo '<th>Next Follow-up</th>';
    echo '<th>Last Contact</th>';
    echo '<th>Created Date</th>';
    echo '<th>Won Date</th>';
    echo '<th>Created By</th>';
    echo '</tr>';
    
    // Data rows
    foreach ($leads as $lead) {
        echo '<tr>';
        echo '<td>' . $lead['id'] . '</td>';
        echo '<td>' . htmlspecialchars($lead['user_custom_id'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['lead_unique_id']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['name']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['email'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['area'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['address'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['district'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['police_station'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['post_office'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['country'] ?? 'Bangladesh') . '</td>';
        echo '<td>' . $lead['priority'] . '</td>';
        echo '<td>' . ($lead['lead_stage'] ?? 'Lead') . '</td>';
        echo '<td>' . $lead['status'] . '</td>';
        echo '<td>' . number_format($lead['expected_amount'] ?? 0, 2) . '</td>';
        echo '<td>' . ($lead['probability'] ?? 0) . '%</td>';
        echo '<td>' . ($lead['next_followup_date'] ?? '') . '</td>';
        echo '<td>' . ($lead['last_contact_date'] ?? '') . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($lead['created_at'])) . '</td>';
        echo '<td>' . ($lead['won_date'] ?? '') . '</td>';
        echo '<td>' . htmlspecialchars($lead['created_by_name'] ?? '') . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '</body>';
    echo '</html>';
    exit();
}

// JSON export option
elseif ($format == 'json') {
    header('Content-Type: application/json');
    header('Content-Disposition: attachment; filename="' . $filename . '.json"');
    
    $output = [];
    foreach ($leads as $lead) {
        $output[] = [
            'id' => $lead['id'],
            'user_custom_id' => $lead['user_custom_id'],
            'lead_unique_id' => $lead['lead_unique_id'],
            'name' => $lead['name'],
            'email' => $lead['email'],
            'phone' => $lead['phone'],
            'area' => $lead['area'],
            'address' => $lead['address'],
            'district' => $lead['district'],
            'police_station' => $lead['police_station'],
            'post_office' => $lead['post_office'],
            'country' => $lead['country'],
            'priority' => $lead['priority'],
            'lead_stage' => $lead['lead_stage'],
            'status' => $lead['status'],
            'expected_amount' => $lead['expected_amount'],
            'probability' => $lead['probability'],
            'next_followup_date' => $lead['next_followup_date'],
            'last_contact_date' => $lead['last_contact_date'],
            'created_at' => $lead['created_at'],
            'won_date' => $lead['won_date'],
            'created_by' => $lead['created_by_name']
        ];
    }
    
    echo json_encode($output, JSON_PRETTY_PRINT);
    exit();
}

// PDF export option (simple)
elseif ($format == 'pdf') {
    // For PDF, we'll output HTML that can be printed to PDF
    header('Content-Type: text/html');
    header('Content-Disposition: inline; filename="' . $filename . '.html"');
    
    echo '<!DOCTYPE html>';
    echo '<html>';
    echo '<head>';
    echo '<meta charset="UTF-8">';
    echo '<title>Leads Export</title>';
    echo '<style>';
    echo 'body { font-family: Arial, sans-serif; margin: 20px; }';
    echo 'h1 { color: #2c3e50; }';
    echo 'table { width: 100%; border-collapse: collapse; margin-top: 20px; }';
    echo 'th { background: #34495e; color: white; padding: 10px; text-align: left; }';
    echo 'td { padding: 8px; border-bottom: 1px solid #ddd; }';
    echo '.header { margin-bottom: 20px; }';
    echo '.footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }';
    echo '@media print { .no-print { display: none; } }';
    echo '</style>';
    echo '</head>';
    echo '<body>';
    echo '<div class="header">';
    echo '<h1>Power Sonic CRM - Leads Report</h1>';
    echo '<p>Generated: ' . date('Y-m-d H:i:s') . '</p>';
    echo '<p>Total Records: ' . count($leads) . '</p>';
    echo '</div>';
    
    echo '<table>';
    echo '<tr>';
    echo '<th>Lead ID</th>';
    echo '<th>Name</th>';
    echo '<th>Phone</th>';
    echo '<th>District</th>';
    echo '<th>Priority</th>';
    echo '<th>Stage</th>';
    echo '<th>Amount</th>';
    echo '<th>Created</th>';
    echo '</tr>';
    
    foreach ($leads as $lead) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($lead['lead_unique_id']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['name']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['phone']) . '</td>';
        echo '<td>' . htmlspecialchars($lead['district'] ?? '') . '</td>';
        echo '<td>' . $lead['priority'] . '</td>';
        echo '<td>' . ($lead['lead_stage'] ?? 'Lead') . '</td>';
        echo '<td>' . number_format($lead['expected_amount'] ?? 0, 2) . '</td>';
        echo '<td>' . date('Y-m-d', strtotime($lead['created_at'])) . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    echo '<div class="footer">';
    echo '<p>Power Sonic CRM - Lead Management System</p>';
    echo '<button class="no-print" onclick="window.print()" style="padding: 10px 20px; margin-top: 20px; cursor: pointer;">Print / Save as PDF</button>';
    echo '</div>';
    echo '</body>';
    echo '</html>';
    exit();
}

redirect('view_leads.php');
?>