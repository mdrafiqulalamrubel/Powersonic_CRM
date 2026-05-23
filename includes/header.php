<?php
// No session_start() here - it's already in config.php
// config.php should be included BEFORE this file

// Get notification count for current user
$notif_count = 0;
if (isLoggedIn() && isset($pdo)) {
    $stmt = $pdo->prepare("SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0");
    $stmt->execute([$_SESSION['user_id']]);
    $notif_count = $stmt->fetchColumn();
}

// Get current page name for active menu highlighting
$current_page = basename($_SERVER['PHP_SELF']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Power Sonic CRM - Lead Management System</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: 'Segoe UI', 'Poppins', 'Roboto', sans-serif;
            background: #f0f2f5;
            overflow-x: hidden;
        }

        /* Professional Left Menu Styles */
        .crm-wrapper {
            display: flex;
            min-height: 100vh;
        }

        /* Sidebar Styles */
        .sidebar {
            width: 280px;
            background: linear-gradient(180deg, #1a1e2b 0%, #2d3748 100%);
            color: white;
            position: fixed;
            height: 100vh;
            overflow-y: auto;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            z-index: 1000;
            box-shadow: 4px 0 20px rgba(0, 0, 0, 0.1);
        }

        .sidebar.collapsed {
            width: 80px;
        }

        .sidebar.collapsed .sidebar-header h3,
        .sidebar.collapsed .menu-text,
        .sidebar.collapsed .menu-badge {
            display: none;
        }

        .sidebar.collapsed .menu-item {
            justify-content: center;
            padding: 12px;
        }

        .sidebar.collapsed .menu-item i {
            margin: 0;
            font-size: 1.5rem;
        }

        /* Sidebar Header */
        .sidebar-header {
            padding: 25px 20px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
            text-align: center;
        }

        .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
        }

        .logo i {
            font-size: 32px;
            color: #48bb78;
        }

        .logo h3 {
            font-size: 18px;
            font-weight: 600;
            letter-spacing: 1px;
        }

        .logo p {
            font-size: 10px;
            opacity: 0.7;
            margin-top: 5px;
        }

        /* User Profile Section */
        .user-profile {
            padding: 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            margin-bottom: 20px;
        }

        .avatar {
            width: 60px;
            height: 60px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 10px;
            font-size: 24px;
            font-weight: bold;
        }

        .user-name {
            font-weight: 600;
            margin-bottom: 5px;
        }

        .user-role {
            font-size: 12px;
            opacity: 0.7;
            text-transform: uppercase;
            letter-spacing: 1px;
        }

        /* Menu Items */
        .menu-section {
            padding: 0 15px;
            margin-bottom: 20px;
        }

        .menu-section-title {
            font-size: 11px;
            text-transform: uppercase;
            letter-spacing: 1px;
            opacity: 0.5;
            padding: 10px 15px;
            margin-top: 10px;
        }

        .menu-item {
            display: flex;
            align-items: center;
            padding: 12px 15px;
            margin: 5px 0;
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.8);
            text-decoration: none;
            transition: all 0.3s ease;
            position: relative;
            overflow: hidden;
        }

        .menu-item:hover {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            transform: translateX(5px);
        }

        .menu-item.active {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }

        .menu-item i {
            width: 25px;
            font-size: 1.2rem;
            margin-right: 15px;
        }

        .menu-text {
            flex: 1;
            font-size: 14px;
            font-weight: 500;
        }

        .menu-badge {
            background: #e74c3c;
            color: white;
            border-radius: 20px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: bold;
        }

        /* Submenu Styles */
        .submenu {
            margin-left: 40px;
            max-height: 0;
            overflow: hidden;
            transition: max-height 0.3s ease-out;
        }

        .submenu.open {
            max-height: 500px;
        }

        .submenu .menu-item {
            padding: 10px 15px;
            font-size: 13px;
        }

        .menu-item.has-submenu {
            cursor: pointer;
        }

        .toggle-icon {
            margin-left: auto;
            transition: transform 0.3s;
        }

        .menu-item.has-submenu.open .toggle-icon {
            transform: rotate(90deg);
        }

        /* Main Content Area */
        .main-content {
            flex: 1;
            margin-left: 280px;
            transition: margin-left 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            min-height: 100vh;
        }

        .main-content.expanded {
            margin-left: 80px;
        }

        /* Top Navigation Bar */
        .top-nav {
            background: white;
            padding: 15px 30px;
            box-shadow: 0 2px 10px rgba(0, 0, 0, 0.05);
            display: flex;
            justify-content: space-between;
            align-items: center;
            position: sticky;
            top: 0;
            z-index: 999;
        }

        .toggle-sidebar {
            background: none;
            border: none;
            font-size: 1.5rem;
            cursor: pointer;
            color: #2d3748;
            transition: transform 0.3s;
        }

        .toggle-sidebar:hover {
            transform: scale(1.1);
        }

        .page-title {
            font-size: 1.5rem;
            font-weight: 600;
            color: #2d3748;
        }

        .top-nav-right {
            display: flex;
            align-items: center;
            gap: 20px;
        }

        .notification-icon {
            position: relative;
            cursor: pointer;
        }

        .notification-icon i {
            font-size: 1.3rem;
            color: #4a5568;
        }

        .notification-badge {
            position: absolute;
            top: -8px;
            right: -8px;
            background: #e74c3c;
            color: white;
            border-radius: 50%;
            padding: 2px 6px;
            font-size: 10px;
            font-weight: bold;
        }

        .user-menu {
            display: flex;
            align-items: center;
            gap: 10px;
            cursor: pointer;
            padding: 5px 10px;
            border-radius: 8px;
            transition: background 0.3s;
        }

        .user-menu:hover {
            background: #f7fafc;
        }

        .user-avatar-small {
            width: 35px;
            height: 35px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            font-weight: bold;
        }

        /* Content Area */
        .content-area {
            padding: 30px;
        }

        /* Stats Grid */
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin-bottom: 30px;
        }

        .stat-card {
            background: white;
            padding: 20px;
            border-radius: 10px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            transition: transform 0.3s;
        }

        .stat-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 5px 20px rgba(0,0,0,0.1);
        }

        .stat-card h3 {
            color: #666;
            font-size: 14px;
            margin-bottom: 10px;
        }

        .stat-card .number {
            font-size: 32px;
            font-weight: bold;
            color: #2c3e50;
        }

        /* Scrollbar Styling */
        .sidebar::-webkit-scrollbar {
            width: 5px;
        }

        .sidebar::-webkit-scrollbar-track {
            background: rgba(255, 255, 255, 0.1);
        }

        .sidebar::-webkit-scrollbar-thumb {
            background: rgba(255, 255, 255, 0.3);
            border-radius: 5px;
        }

        /* Responsive */
        @media (max-width: 768px) {
            .sidebar {
                transform: translateX(-100%);
            }
            .sidebar.mobile-open {
                transform: translateX(0);
            }
            .main-content {
                margin-left: 0;
            }
        }

        /* Animations */
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translateY(10px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }

        .content-area > * {
            animation: fadeIn 0.5s ease-out;
        }
        
        /* Tables */
        .data-table {
            width: 100%;
            background: white;
            border-radius: 10px;
            overflow: hidden;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
        }
        
        .data-table th {
            background: #34495e;
            color: white;
            padding: 12px;
            text-align: left;
        }
        
        .data-table td {
            padding: 12px;
            border-bottom: 1px solid #ddd;
        }
        
        .priority-high { color: #e74c3c; font-weight: bold; }
        .priority-medium { color: #f39c12; font-weight: bold; }
        .priority-low { color: #27ae60; font-weight: bold; }
        
        .btn {
            display: inline-block;
            padding: 8px 15px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            font-size: 12px;
            margin: 2px;
        }
        
        .btn-success { background: #27ae60; }
        .btn-danger { background: #e74c3c; }
        .btn-warning { background: #f39c12; }
        .btn-sm { padding: 5px 10px; font-size: 11px; }
    </style>
</head>
<body>
    <div class="crm-wrapper">
        <!-- Sidebar -->
        <div class="sidebar" id="sidebar">
            <div class="sidebar-header">
                <div class="logo">
                    <i class="fas fa-bolt"></i>
                    <div>
                        <h3>Power Sonic CRM</h3>
                        <p>Lead Management System</p>
                    </div>
                </div>
            </div>

            <div class="user-profile">
                <div class="avatar">
                    <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 2)); ?>
                </div>
                <div class="user-name"><?php echo $_SESSION['full_name'] ?? 'User'; ?></div>
                <div class="user-role">
                    <i class="fas <?php echo isAdmin() ? 'fa-crown' : 'fa-user-check'; ?>"></i>
                    <?php 
                    $role_display = isAdmin() ? 'Administrator' : (isFieldAgent() ? 'Field Agent' : 'User');
                    echo $role_display; 
                    ?>
                </div>
            </div>

            <!-- Main Menu -->
            <div class="menu-section">
                <div class="menu-section-title">MAIN NAVIGATION</div>
                
                <a href="<?php echo isAdmin() ? 'dashboard.php' : 'agent_dashboard.php'; ?>" class="menu-item <?php echo ($current_page == 'dashboard.php' || $current_page == 'agent_dashboard.php') ? 'active' : ''; ?>">
                    <i class="fas fa-tachometer-alt"></i>
                    <span class="menu-text">Dashboard</span>
                </a>

                <!-- Lead Management Section -->
                <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
                    <i class="fas fa-users"></i>
                    <span class="menu-text">Lead Management</span>
                    <i class="fas fa-chevron-right toggle-icon"></i>
                </div>
                <div class="submenu">
                    <a href="add_lead.php" class="menu-item <?php echo $current_page == 'add_lead.php' ? 'active' : ''; ?>">
                        <i class="fas fa-plus-circle"></i>
                        <span class="menu-text">Add New Lead</span>
                    </a>
                    <a href="view_leads.php" class="menu-item <?php echo $current_page == 'view_leads.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                        <span class="menu-text">View All Leads</span>
                    </a>
                </div>

                <!-- Communications -->
                <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
                    <i class="fas fa-comments"></i>
                    <span class="menu-text">Communications</span>
                    <i class="fas fa-chevron-right toggle-icon"></i>
                </div>
                <div class="submenu">
                    <a href="add_communication.php?lead_id=0" class="menu-item">
                        <i class="fas fa-plus-circle"></i>
                        <span class="menu-text">Add Communication</span>
                    </a>
                    <a href="schedule_task.php?lead_id=0" class="menu-item">
                        <i class="fas fa-calendar-alt"></i>
                        <span class="menu-text">Schedule Follow-up</span>
                    </a>
                </div>

                <!-- User Management Section (Admin Only) -->
                <?php if(isAdmin()): ?>
                <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
                    <i class="fas fa-users-cog"></i>
                    <span class="menu-text">User Management</span>
                    <i class="fas fa-chevron-right toggle-icon"></i>
                </div>
                <div class="submenu">
                    <a href="manage_users.php" class="menu-item <?php echo $current_page == 'manage_users.php' ? 'active' : ''; ?>">
                        <i class="fas fa-list"></i>
                        <span class="menu-text">All Users</span>
                    </a>
                    <a href="add_user.php" class="menu-item <?php echo $current_page == 'add_user.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-plus"></i>
                        <span class="menu-text">Add New User</span>
                    </a>
                    <a href="user_activity.php" class="menu-item <?php echo $current_page == 'user_activity.php' ? 'active' : ''; ?>">
                        <i class="fas fa-history"></i>
                        <span class="menu-text">Activity Log</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Reports Section (Admin Only) -->
                <?php if(isAdmin()): ?>
                <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
                    <i class="fas fa-chart-bar"></i>
                    <span class="menu-text">Reports</span>
                    <i class="fas fa-chevron-right toggle-icon"></i>
                </div>
                <div class="submenu">
                    <a href="agent_reports.php" class="menu-item <?php echo $current_page == 'agent_reports.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-tie"></i>
                        <span class="menu-text">Agent Performance</span>
                    </a>
                </div>
                <?php endif; ?>

                <!-- Settings -->
                <div class="menu-item has-submenu" onclick="toggleSubmenu(this)">
                    <i class="fas fa-cog"></i>
                    <span class="menu-text">Settings</span>
                    <i class="fas fa-chevron-right toggle-icon"></i>
                </div>
                <div class="submenu">
                    <a href="profile.php" class="menu-item <?php echo $current_page == 'profile.php' ? 'active' : ''; ?>">
                        <i class="fas fa-user-edit"></i>
                        <span class="menu-text">My Profile</span>
                    </a>
                    <a href="change_password.php" class="menu-item <?php echo $current_page == 'change_password.php' ? 'active' : ''; ?>">
                        <i class="fas fa-key"></i>
                        <span class="menu-text">Change Password</span>
                    </a>
                </div>
            </div>

            <!-- Bottom Menu -->
            <div class="menu-section" style="margin-top: auto; border-top: 1px solid rgba(255,255,255,0.1);">
                <a href="notifications.php" class="menu-item <?php echo $current_page == 'notifications.php' ? 'active' : ''; ?>">
                    <i class="fas fa-bell"></i>
                    <span class="menu-text">Notifications</span>
                    <?php if($notif_count > 0): ?>
                    <span class="menu-badge"><?php echo $notif_count; ?></span>
                    <?php endif; ?>
                </a>
                <a href="logout.php" class="menu-item">
                    <i class="fas fa-sign-out-alt"></i>
                    <span class="menu-text">Logout</span>
                </a>
            </div>
        </div>

        <!-- Main Content -->
        <div class="main-content" id="mainContent">
            <div class="top-nav">
                <button class="toggle-sidebar" onclick="toggleSidebar()">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="page-title">
                    <?php
                    $page_titles = [
                        'dashboard.php' => 'Admin Dashboard',
                        'agent_dashboard.php' => 'Field Agent Dashboard',
                        'add_lead.php' => 'Add New Lead',
                        'view_leads.php' => 'Lead Management',
                        'view_lead.php' => 'Lead Details',
                        'edit_lead.php' => 'Edit Lead',
                        'agent_reports.php' => 'Performance Reports',
                        'notifications.php' => 'Notifications',
                        'profile.php' => 'My Profile',
                        'change_password.php' => 'Change Password',
                        'manage_users.php' => 'User Management',
                        'add_user.php' => 'Add New User',
                        'user_activity.php' => 'User Activity Log'
                    ];
                    echo $page_titles[$current_page] ?? 'Power Sonic CRM';
                    ?>
                </div>
                <div class="top-nav-right">
                    <div class="notification-icon" onclick="window.location.href='notifications.php'">
                        <i class="fas fa-bell"></i>
                        <?php if($notif_count > 0): ?>
                        <span class="notification-badge"><?php echo $notif_count; ?></span>
                        <?php endif; ?>
                    </div>
                    <div class="user-menu" onclick="window.location.href='profile.php'">
                        <div class="user-avatar-small">
                            <?php echo strtoupper(substr($_SESSION['full_name'] ?? 'U', 0, 2)); ?>
                        </div>
                        <span><?php echo $_SESSION['full_name'] ?? 'User'; ?></span>
                        <i class="fas fa-chevron-down" style="font-size: 12px;"></i>
                    </div>
                </div>
            </div>
            <div class="content-area">
<!-- Header ends here - content will be inserted by individual pages -->